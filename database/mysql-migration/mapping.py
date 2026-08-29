#!/usr/bin/env python3
"""
Mapa autoritativo SQLite -> MySQL 8.0 para o banco Maranatha.

PRINCIPIO CENTRAL
-----------------
O SQLite guarda o tipo POR VALOR (storage class), nao por coluna. O tipo declarado
na coluna e' so' uma "affinity" e varias colunas deste banco foram declaradas com um
tipo que contradiz o que elas realmente guardam (ex.: `timestamp('attendance_id')`
guardando um inteiro).

Para garantir resultados IDENTICOS entre SQLite e MySQL, o tipo MySQL de cada coluna
e' derivado do que o dado REALMENTE e' (storage class observada em producao), e nao
do que a migration declarou:

    todos os valores 'integer'  -> BIGINT
    todos os valores 'real'     -> DOUBLE          (mesmo IEEE-754 binary64 do SQLite)
    todos os valores 'text'     -> VARCHAR/TEXT    (preserva bytes exatos)
    tabela vazia                -> tipo pretendido pela migration Laravel

OVERRIDES abaixo documentam cada caso em que o tipo declarado mente sobre o dado,
ou em que uma decisao de projeto foi tomada (datas ISO-8601 como VARCHAR, dinheiro
como DOUBLE, JSON como LONGTEXT).
"""

# ---------------------------------------------------------------------------
# 1. DINHEIRO  ->  DOUBLE  (nunca double(8,2))
# ---------------------------------------------------------------------------
# As migrations usam $table->float(total: 8, places: 2), que o Laravel 10 traduz
# para MySQL como `double(8, 2)`: no maximo 8 digitos totais e 2 casas decimais,
# ou seja, valor maximo 999999.99.
#
# Os dados de producao estouram isso de longe:
#   invoices.amount                            max 221.137.500,00   (507 linhas >= 1e6)
#   inventory_product_item_uncountables.buy_amount max 171.674.590,00 (43 linhas >= 1e6)
#   inventory_product_items.buy_amount/sell_amount max 9.900.000,00  (9 linhas >= 1e6)
#   worker_payments.amount                     max   8.732.717,00   (257 linhas >= 1e6)
#   balances.amount                            max     702.360,00
#
# E ainda ha' valores com mais de 2 casas decimais, que double(8,2) arredondaria:
#   balances.amount                 2.448 linhas (ex.: 4881.3351)
#   inventory_product_items.buy_amount / sell_amount  2.752 linhas (ex.: 0.3333)
#
# Em sql_mode strict isso da' ERROR 1264 (out of range) nas linhas grandes e
# corrompe silenciosamente as demais. DOUBLE sem precisao declarada e' o unico
# tipo que reproduz bit-a-bit o REAL do SQLite.
MONEY_COLUMNS = {
    ("invoices", "amount"),
    ("balances", "amount"),
    ("worker_payments", "amount"),
    ("inventory_product_items", "buy_amount"),
    ("inventory_product_items", "sell_amount"),
    ("inventory_product_item_uncountables", "buy_amount"),
    ("inventory_product_item_uncountables", "quantity_inserted"),
    ("inventory_product_item_uncountables", "quantity_used"),
    ("inventory_product_item_uncountables", "quantity_remaining"),
}

# ---------------------------------------------------------------------------
# 2. DATAS DE NEGOCIO  ->  VARCHAR  (decisao aprovada pelo usuario)
# ---------------------------------------------------------------------------
# Estas colunas NAO guardam datetime: guardam a string ISO-8601 COM offset de fuso,
# exatamente como o app a escreve (`$date->format('c')`), por exemplo
#   2024-04-01T00:00:00.000-05:00   (Peru)
#   2024-12-12T00:00:00.000-03:00   (Paraguai)
#
# Nenhum model faz cast de data nessas colunas; o codigo le a string crua
# (`new DateTime($this->date)`, `Carbon::parse(...)`) e em Attendance::datesWorkers()
# chega a comparar `format('c') === format('c')`.
#
# MySQL DATETIME nao aceita 'T' nem offset -> ERROR 1292 em strict mode. Converter
# para DATETIME mudaria os valores lidos pelo app. VARCHAR reproduz exatamente o
# que o SQLite faz hoje (typeof() = 'text'), inclusive a ordenacao lexicografica
# usada por `where('date', '>=', ...)` e `orderBy('date')`.
ISO_DATE_COLUMNS = {
    ("invoices", "date"),
    ("reports", "from_date"),
    ("reports", "to_date"),
    ("reports", "approved_at"),
    ("reports", "rejected_at"),
    ("reports", "submitted_at"),
    ("reports", "restituted_at"),
    ("balances", "date"),
    ("attendances", "from_date"),
    ("attendances", "to_date"),
    ("attendance_day_workers", "date"),
    ("inventory_warehouse_incomes", "date"),
    ("inventory_warehouse_outcomes", "date"),
}

# ---------------------------------------------------------------------------
# 3. COLUNAS CUJO TIPO DECLARADO MENTE SOBRE O DADO
# ---------------------------------------------------------------------------
# Bugs de copy-paste nas migrations: `$table->timestamp(...)` foi usado para
# colunas que guardam IDs inteiros. Em MySQL isso viraria TIMESTAMP e a insercao
# do inteiro falharia ("Incorrect datetime value").
TYPE_LIES = {
    # migration: $table->timestamp('attendance_id')  -> guarda FK inteira (48..2493)
    ("attendance_day_workers", "attendance_id"): "BIGINT NOT NULL",
    # migration: $table->timestamp('loaned_to_user_id') -> guarda users.id (10..19)
    ("inventory_warehouse_product_item_loans", "loaned_to_user_id"): "BIGINT NOT NULL",
    ("inventory_warehouse_product_item_loans", "loaned_by_user_id"): "BIGINT NOT NULL",
    # migration: $table->integer('pdf') -> guarda o nome do arquivo (texto, ate 40 bytes)
    ("invoices", "pdf"): "VARCHAR(255) DEFAULT NULL",
    # migration: $table->json('origin_...') -> guarda um id inteiro ou NULL
    ("inventory_warehouse_incomes", "origin_inventory_warehouse_income_id"): "BIGINT DEFAULT NULL",
    ("inventory_product_items", "origin_inventory_product_item_id"): "BIGINT DEFAULT NULL",

    # Tabelas do Laravel Pulse: o SQLite perdeu os tipos originais (binary(16) virou
    # varchar, decimal(20,2) virou integer). Como elas sao importadas vazias, usamos
    # os tipos da propria migration do Pulse para que o schema importado e o schema
    # de um `php artisan migrate` do zero fiquem identicos.
    ("pulse_values", "key_hash"): "BINARY(16) NOT NULL",
    ("pulse_entries", "key_hash"): "BINARY(16) NOT NULL",
    ("pulse_aggregates", "key_hash"): "BINARY(16) NOT NULL",
    ("pulse_aggregates", "value"): "DECIMAL(20,2) NOT NULL",
}

# ---------------------------------------------------------------------------
# 4. COLUNAS JSON  ->  LONGTEXT  (nao o tipo nativo JSON)
# ---------------------------------------------------------------------------
# Motivos para NAO usar o tipo `json` nativo do MySQL nesta migracao:
#
#  a) MySQL NORMALIZA documentos JSON: reordena as chaves dos objetos e remove
#     espacos. Como os models fazem cast 'array', a ordem das chaves mudaria nas
#     respostas da API -> saida diferente da atual.
#  b) MySQL 8 NAO aceita DEFAULT literal em coluna JSON (ERROR 1101). As migrations
#     usam ->default('[]') e ->default('{}') em 15 colunas; todas falhariam no
#     CREATE TABLE.
#  c) LONGTEXT preserva os bytes exatamente como o SQLite guarda hoje.
#  d) A unica relacao JSON do projeto (`hasManyJson` em InventoryWarehouseOutcome
#     -> inventory_warehouse_outcome_ids) compila para JSON_CONTAINS(), que
#     funciona normalmente sobre LONGTEXT com conteudo JSON valido.
#
# Auditoria dos dados: 100% dos valores nessas colunas sao JSON valido.
# kv_storage.value chega a 285.374 bytes -> excede TEXT (64KB), exige LONGTEXT.
JSON_COLUMNS = {
    ("users", "roles"),
    ("users", "permissions"),
    ("users", "metadata"),
    ("reports", "metadata"),
    ("workers", "history"),
    ("worker_payments", "divisions"),
    ("expenses", "uses"),
    ("jobs", "location"),
    ("inventory_warehouses", "owners"),
    ("inventory_products", "inventory_warehouses_ids"),
    ("inventory_products_packs", "products"),
    ("inventory_warehouse_outcome_requests", "requested_products"),
    ("inventory_warehouse_outcome_requests", "received_products"),
    ("inventory_warehouse_outcome_requests", "messages"),
    ("inventory_warehouse_product_item_loans", "movements"),
    ("inventory_warehouse_product_item_loans", "intercurrences"),
    ("inventory_product_item_uncountables", "inventory_warehouse_outcome_ids"),
    ("inventory_product_item_uncountables", "outcomes_details"),
    ("personal_access_tokens", "abilities"),
    ("kv_storage", "value"),
}

# ---------------------------------------------------------------------------
# 5. COLUNAS DE TIMESTAMP REAIS (geridas pelo Laravel)  ->  DATETIME
# ---------------------------------------------------------------------------
# created_at / updated_at e os *_at geridos por `now()` guardam sempre
# 'Y-m-d H:i:s' (19 chars) — formato que o MySQL DATETIME aceita.
#
# Usamos DATETIME e NAO TIMESTAMP porque:
#   - TIMESTAMP tem alcance 1970..2038 e converte fuso na leitura/escrita,
#     o que introduziria deslocamento silencioso;
#   - DATETIME guarda o valor literal, igual ao SQLite.
LARAVEL_DATETIME_COLUMNS = {
    ("*", "created_at"),
    ("*", "updated_at"),
    ("users", "email_verified_at"),
    ("personal_access_tokens", "last_used_at"),
    ("personal_access_tokens", "expires_at"),
    ("password_reset_tokens", "created_at"),
    ("failed_tasks", "failed_at"),
    ("inventory_warehouse_outcome_requests", "requested_at"),
    ("inventory_warehouse_outcome_requests", "rejected_at"),
    ("inventory_warehouse_outcome_requests", "approved_at"),
    ("inventory_warehouse_outcome_requests", "dispatched_at"),
    ("inventory_warehouse_outcome_requests", "on_the_way_at"),
    ("inventory_warehouse_outcome_requests", "delivered_at"),
    ("inventory_warehouse_outcome_requests", "finished_at"),
    ("inventory_warehouse_product_item_loans", "loaned_at"),
    ("inventory_warehouse_product_item_loans", "received_at"),
    ("inventory_warehouse_product_item_loans", "returned_at"),
    ("inventory_warehouse_product_item_loans", "confirm_returned_at"),
}

# `tasks` (fila) guarda epoch como INTEGER, nao datetime.
INTEGER_EPOCH_COLUMNS = {
    ("tasks", "reserved_at"),
    ("tasks", "available_at"),
    ("tasks", "created_at"),
}

# ---------------------------------------------------------------------------
# 6. TAMANHOS DE VARCHAR
# ---------------------------------------------------------------------------
# As migrations declaram tamanhos menores do que o dado real em producao:
#   invoices.description  -> string('description', 100), mas 238 linhas passam
#                            de 100 chars (max 158). Em strict mode: ERROR 1406.
#   inventory_products.image -> max real 297 bytes.
#   invoices.qrcode_data     -> max real 432 bytes.
#
# Regra: VARCHAR dimensionado a partir do maximo observado com folga, com piso de
# 255. Acima de 8.000 bytes a coluna vira TEXT/MEDIUMTEXT/LONGTEXT para nao
# estourar o limite de 65.535 bytes por linha do InnoDB.
VARCHAR_MIN = 255
VARCHAR_MAX_BEFORE_TEXT = 8000


def varchar_size(max_bytes: int | None) -> str:
    """Escolhe VARCHAR(n)/TEXT/MEDIUMTEXT/LONGTEXT a partir do maior valor observado."""
    if max_bytes is None:          # coluna 100% NULL ou tabela vazia
        return f"VARCHAR({VARCHAR_MIN})"
    folga = max(max_bytes * 3, VARCHAR_MIN)
    if folga <= VARCHAR_MIN:
        return f"VARCHAR({VARCHAR_MIN})"
    if folga <= 1000:
        return "VARCHAR(1000)"
    if folga <= VARCHAR_MAX_BEFORE_TEXT:
        return f"VARCHAR({VARCHAR_MAX_BEFORE_TEXT})"
    if max_bytes <= 65535 // 4:
        return "TEXT"
    if max_bytes <= 16777215 // 4:
        return "MEDIUMTEXT"
    return "LONGTEXT"


# ---------------------------------------------------------------------------
# 7. TABELAS QUE NAO DEVEM SER MIGRADAS COM DADOS
# ---------------------------------------------------------------------------
# Estruturas efemeras: recriadas vazias. Levar os dados so' traria lixo.
TRUNCATE_ON_IMPORT = {
    "cache",            # 0 linhas
    "cache_locks",      # 0 linhas
    "tasks",            # fila (0 linhas)
    "failed_tasks",     # 0 linhas
    "password_reset_tokens",  # 0 linhas
    "pulse_values",     # 0 linhas
    "pulse_entries",    # telemetria Laravel Pulse (9.164 linhas descartaveis)
    "pulse_aggregates",  # telemetria Laravel Pulse (2.068 linhas descartaveis)
}

# `sqlite_sequence` e' interno do SQLite: vira AUTO_INCREMENT no MySQL.
SKIP_TABLES = {"sqlite_sequence"}

# ---------------------------------------------------------------------------
# 8. INDICES COM NOME > 64 CARACTERES
# ---------------------------------------------------------------------------
# O limite de identificador do MySQL e' 64 chars. Estes indices criados pelo
# Laravel estouram e fariam o CREATE INDEX falhar:
#   inventory_warehouse_product_item_loans_inventory_warehouse_id_status_index (74)
#   inventory_warehouse_product_item_loans_inventory_product_item_id_index     (70)
#   inventory_warehouse_product_item_loans_loaned_to_user_id_status_index      (69)
#   inventory_warehouse_product_item_loans_inventory_warehouse_id_index        (66)
INDEX_NAME_MAX = 64

# Renomeacoes deterministicas e estaveis (usadas tambem nas migrations corrigidas).
INDEX_RENAMES = {
    "inventory_warehouse_product_item_loans_inventory_warehouse_id_index":
        "iwpil_warehouse_id_index",
    "inventory_warehouse_product_item_loans_inventory_product_item_id_index":
        "iwpil_product_item_id_index",
    "inventory_warehouse_product_item_loans_loaned_to_user_id_index":
        "iwpil_loaned_to_user_id_index",
    "inventory_warehouse_product_item_loans_loaned_by_user_id_index":
        "iwpil_loaned_by_user_id_index",
    "inventory_warehouse_product_item_loans_status_index":
        "iwpil_status_index",
    "inventory_warehouse_product_item_loans_inventory_warehouse_id_status_index":
        "iwpil_warehouse_id_status_index",
    "inventory_warehouse_product_item_loans_loaned_to_user_id_status_index":
        "iwpil_loaned_to_user_id_status_index",
    "inventory_warehouse_outcomes_inventory_warehouse_id_date_index":
        "iwo_warehouse_id_date_index",
    "inventory_product_items_inventory_warehouse_outcome_id_index":
        "ipi_warehouse_outcome_id_index",
    "pulse_aggregates_bucket_period_type_aggregate_key_hash_unique":
        "pulse_agg_bucket_period_type_agg_keyhash_unique",
    "pulse_entries_timestamp_type_key_hash_value_index":
        "pulse_entries_ts_type_keyhash_value_index",
    "personal_access_tokens_tokenable_type_tokenable_id_index":
        "pat_tokenable_type_id_index",
}


def safe_index_name(name: str) -> str:
    if name in INDEX_RENAMES:
        return INDEX_RENAMES[name]
    if len(name) <= INDEX_NAME_MAX:
        return name
    raise SystemExit(
        f"Indice '{name}' tem {len(name)} caracteres (limite MySQL: {INDEX_NAME_MAX}) "
        f"e nao ha' renomeacao registrada em INDEX_RENAMES."
    )


# ---------------------------------------------------------------------------
# 8.5. LISTAS JSON GRAVADAS COMO OBJETO — NORMALIZACAO OBRIGATORIA
# ---------------------------------------------------------------------------
# Estas colunas sao semanticamente LISTAS, mas parte das linhas guarda um OBJETO:
#
#   inventory_product_item_uncountables.inventory_warehouse_outcome_ids
#       138 linhas: [48,49,359]                      <- correto
#        15 linhas: {"0":48,"1":49,"4":359,"5":361}  <- corrompido
#   inventory_products_packs.products
#         9 linhas array, 1 linha objeto
#
# Causa: array_diff() do PHP PRESERVA as chaves. Ao remover um elemento do meio,
# o array fica com buracos e json_encode() emite objeto em vez de array.
# O culpado era app/Http/Controllers/InventoryWarehouseIncomeController.php:498
# (os metodos do model ja' usavam array_values(); so' o controller esquecia).
#
# Por que isso quebra a migracao: a relacao hasManyJson de InventoryWarehouseOutcome
# compila para `? MEMBER OF(col)` no MySQL, que so' percorre ARRAYS. O json_each()
# do SQLite percorre arrays E objetos. Sem normalizar, itens de inventario
# desapareceriam silenciosamente do kardex e do balance.
#
# A normalizacao preserva o comportamento no SQLite: json_each() devolve os mesmos
# valores nos dois formatos, e nenhum trecho de PHP indexa essas colunas por chave
# (o uso e' in_array / array_diff / iteracao).
#
# NAO inclua outcomes_details aqui: ela e' legitimamente um MAPA chaveado pelo id
# do outcome (141 objetos), e o codigo faz $outcomes_details[$outcome->id].
JSON_LIST_COLUMNS = {
    ("inventory_product_item_uncountables", "inventory_warehouse_outcome_ids"),
    ("inventory_products_packs", "products"),
    ("inventory_products", "inventory_warehouses_ids"),
    ("inventory_warehouses", "owners"),
    ("users", "roles"),
    ("users", "permissions"),
    ("workers", "history"),
    ("worker_payments", "divisions"),
    ("expenses", "uses"),
    ("inventory_warehouse_outcome_requests", "requested_products"),
    ("inventory_warehouse_outcome_requests", "received_products"),
    ("inventory_warehouse_outcome_requests", "messages"),
    ("inventory_warehouse_product_item_loans", "movements"),
    ("inventory_warehouse_product_item_loans", "intercurrences"),
    ("personal_access_tokens", "abilities"),
}


# ---------------------------------------------------------------------------
# 9. COLACAO — utf8mb4_bin NAS COLUNAS DE TEXTO
# ---------------------------------------------------------------------------
# O SQLite compara TEXT como BINARY: 'ABC' <> 'abc' e 'Rigida' <> 'Rigida-com-acento'.
# A colacao utf8mb4_unicode_ci do MySQL equipara caixa E acento, o que muda o
# resultado de consultas reais nesta base:
#
#   WHERE job_code = '2000.01-pe[ups]'   (gravado '2000.01-PE[UPS]')
#       SQLite -> 0 linhas        MySQL _ci -> 276 linhas
#   WHERE dni = 'n10989566'              (gravado 'N10989566')
#       SQLite -> 0 linhas        MySQL _ci ->   1 linha
#   WHERE name = 'Rodillera Rigida'      (gravado 'Rodillera Rigida' com acento)
#       SQLite -> 0 linhas        MySQL _ci ->   1 linha
#
# Alem disso ha' 4 valores distintos em inventory_products.name e 4 em
# balances.ticket_number que colidem sob _ci — dois registros hoje distintos
# passariam a ser tratados como iguais em GROUP BY, DISTINCT e joins.
#
# Como o requisito e' resultado identico, as colunas de texto recebem
# utf8mb4_bin, que compara byte a byte igual ao SQLite. Em UTF-8 a ordem de
# bytes coincide com a ordem de code points, entao ORDER BY tambem bate.
#
# A colacao PADRAO da tabela continua utf8mb4_unicode_ci, para que colunas novas
# criadas por migrations futuras nasçam com o comportamento usual do MySQL.
# Adotar _ci nas colunas existentes e' uma mudanca de UX (busca insensivel a
# caixa/acento) que merece sua propria validacao — ver README, secao 7.
TEXT_COLLATION = "utf8mb4_bin"
TABLE_COLLATION = "utf8mb4_unicode_ci"


# ---------------------------------------------------------------------------
# 10. INTEGRIDADE REFERENCIAL — POR QUE NAO CRIAMOS FOREIGN KEYS
# ---------------------------------------------------------------------------
# O banco de producao tem 2.342 invoices apontando para reports inexistentes
# (report_id 2335 -> 1.909 linhas; 2338 -> 384; 2339 -> 32; alem de ids
# claramente corrompidos como 300660, 10367576 e 10574091), somando
# R$ 34.507.800,32 em amount.
#
# Criar FOREIGN KEY nessa carga faria a importacao falhar. Mais importante: hoje
# o app funciona com esses orfaos e os relatorios os ignoram via JOIN. Adicionar
# FKs mudaria o comportamento. A migracao replica o estado atual; a limpeza dos
# orfaos e' um projeto separado (ver runbook, secao "Pos-migracao").
CREATE_FOREIGN_KEYS = False
