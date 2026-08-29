# Migração SQLite → MySQL 8.0 — Maranatha

Runbook operacional. O **porquê** de cada decisão de tipo está em [`mapping.py`](mapping.py);
este arquivo é o passo a passo.

---

## 1. Por que a primeira tentativa falhou

Cinco defeitos no schema, todos invisíveis no SQLite porque o SQLite guarda tipo
**por valor**, não por coluna. O tipo declarado é só uma "affinity" e várias colunas
deste banco foram declaradas com um tipo que contradiz o dado que guardam.

### 1.1 Dinheiro truncado e estourado — `double(8,2)`

Todas as colunas monetárias usam `$table->float(total: 8, places: 2)`. O Laravel 10
traduz `float()` para MySQL como **`double(8, 2)`**: no máximo 8 dígitos totais e 2
casas decimais, ou seja, teto de **999.999,99**.

Os dados de produção estouram isso de longe:

| Coluna | Máximo real | Linhas ≥ 1e6 | Linhas com >2 casas decimais |
|---|---:|---:|---:|
| `invoices.amount` | 221.137.500,00 | 507 | 0 |
| `inventory_product_item_uncountables.buy_amount` | 171.674.590,00 | 43 | 1 |
| `inventory_product_items.buy_amount` / `sell_amount` | 9.900.000,00 | 9 | 2.752 |
| `worker_payments.amount` | 8.732.717,00 | 257 | 0 |
| `balances.amount` | 702.360,00 | 0 | 2.448 |

Em `sql_mode` strict, as linhas grandes dão **ERROR 1264 (out of range)** e as demais
são **arredondadas em silêncio** — um erro contábil que só aparece no fechamento.

**Correção:** `DOUBLE` sem precisão declarada. É o mesmo IEEE-754 binary64 que o SQLite
usa em `REAL`, então os valores são preservados bit a bit.

### 1.2 Datas ISO-8601 com offset em coluna `TIMESTAMP`

As datas de negócio **não** são datetimes: são as strings que o app escreve com
`$date->format('c')`.

```
attendance_day_workers.date   2024-04-01T00:00:00-05:00        (14.388 linhas)
invoices.date                 2021-09-06T00:00:00.000-05:00    (16.907 linhas)
inventory_warehouse_outcomes.date  2024-12-12T00:00:00.000-03:00
reports.approved_at           2024-03-25T15:56:45+00:00
```

O offset é **real e varia por linha**: `-05:00` (Peru) e `-03:00` (Paraguai). Nenhum
model faz cast dessas colunas; o código lê a string crua e `Attendance::datesWorkers()`
chega a comparar `format('c') === format('c')`.

O MySQL **não rejeita** esses valores — e é justamente aí que mora o perigo. Ele
converte o instante para a `time_zone` da sessão e grava o resultado **sem warning**:

| Valor original | Gravado em `DATETIME` | Deslocamento |
|---|---|---|
| `2024-04-01T00:00:00-05:00` | `2024-04-01 02:00:00` | **+2h** |
| `2024-03-25T15:56:45+00:00` | `2024-03-25 12:56:45` | **−3h** |
| `2026-02-20T14:30:00.000-03:00` | `2026-02-20 14:30:00` | nenhum |

(medido com `time_zone = SYSTEM` = −03:00; o deslocamento muda conforme o fuso do
servidor, então a mesma migração daria resultados diferentes em máquinas diferentes)

Migrar essas colunas para `DATETIME` deslocaria em silêncio **16.907** datas de invoice,
**3.207** de report e **14.388** de assistência — sem um único erro para avisar.

**Correção:** `VARCHAR(40)`. É o mapeamento fiel — no SQLite esses valores já são
`typeof() = 'text'`. Preserva o offset, a comparação do código e a ordenação
lexicográfica usada por `where('date','>=',…)` e `orderBy('date')`.

> Normalizar essas colunas para `DATETIME` é um projeto separado (§7), não parte
> desta migração: mudaria os valores lidos pelo app.

### 1.3 `timestamp()` usado em colunas de ID inteiro

Erros de copy-paste nas migrations:

| Coluna | Migration declara | Dado real |
|---|---|---|
| `attendance_day_workers.attendance_id` | `$table->timestamp(...)` | FK inteira, 48..2493 |
| `inventory_warehouse_product_item_loans.loaned_to_user_id` | `$table->timestamp(...)` | `users.id`, 10..19 |
| `inventory_warehouse_product_item_loans.loaned_by_user_id` | `$table->timestamp(...)` | `users.id`, 11..21 |
| `invoices.pdf` | `$table->integer(...)` | nome de arquivo (texto) |

No MySQL viram `TIMESTAMP`/`INT` e a inserção falha. **Correção:** `BIGINT` e `VARCHAR(255)`.

### 1.4 `DEFAULT` em coluna JSON/TEXT

15 colunas usam `$table->json('x')->default('[]')`. O MySQL **não aceita DEFAULT em
BLOB/TEXT/JSON** — `CREATE TABLE` falha com **ERROR 1101**, antes mesmo de qualquer dado.

**Correção em duas partes:**
1. As colunas viram `LONGTEXT` sem default (ver §2 sobre por que não `JSON` nativo).
2. Os defaults foram movidos para `protected $attributes` nos 11 models afetados.
   Sem isso, `Model::create()` sem o campo daria **ERROR 1364** em strict mode.

### 1.5 `VARCHAR` menor que o dado

`invoices.description` é `string('description', 100)`, mas **238 linhas passam de 100
caracteres** (máx. 158) → **ERROR 1406 (Data too long)**. Idem `inventory_products.image`
(297 bytes) e `invoices.qrcode_data` (432 bytes).

**Correção:** `VARCHAR` dimensionado a partir do máximo observado com folga (piso 255).

### 1.6 Nomes de índice acima de 64 caracteres

O limite de identificador do MySQL é 64. Quatro índices estouram, o maior com 74:

```
inventory_warehouse_product_item_loans_inventory_warehouse_id_status_index   (74)
```

**Correção:** renomeação determinística em `INDEX_RENAMES` (`mapping.py`).

---

## 1.7 Três bugs que só apareceram ao rodar a suíte

Estes não estavam no schema — estavam no código, escondidos por comportamentos
permissivos do SQLite. A suíte de paridade os encontrou executando os geradores
de relatório reais nos dois bancos.

### a) `ONLY_FULL_GROUP_BY` derruba o relatório de saldo

[`RecordInventoryProductsBalance.php`](../../app/Support/Generators/Records/Inventory/RecordInventoryProductsBalance.php)
fazia `groupBy(['inventory_product_id','inventory_warehouse_id'])->select()` e depois lia
`$item->buy_currency` — uma coluna **fora** do `GROUP BY`. O SQLite escolhe uma linha
arbitrária do grupo; o MySQL 8 rejeita com **erro 1055**.

Havia ainda um segundo 1055: `->each()` chama `chunk()`, que injeta `ORDER BY id`
quando não há ordenação explícita — também inválido sob `ONLY_FULL_GROUP_BY`.

**Correção:** `buy_currency` entrou no `GROUP BY` e no `SELECT`, mais ordenação
explícita pelas colunas agrupadas. Não muda resultado: **nenhuma** combinação
produto+almacén tem mais de uma moeda nesta base (verificado).

### b) Filtro `where('date', ...)` numa tabela sem coluna `date`

[`RecordInventoryProductsKardex.php`](../../app/Support/Generators/Records/Inventory/RecordInventoryProductsKardex.php)
aplicava `->where('date', '>=', $startDate)` sobre `inventory_product_items` e
`inventory_product_item_uncountables` — **nenhuma das duas tem coluna `date`**.

O SQLite tem uma misfeature: identificador entre aspas duplas que não resolve para
uma coluna vira **string literal**. A condição virava `'date' >= '2024-01-01 00:00:00'`,
sempre verdadeira — o filtro era um **no-op silencioso** (casava 90.373 de 90.373 linhas).
No MySQL é erro 1054.

**Correção:** os quatro filtros foram removidos. O recorte de período já é aplicado
nas consultas de `incomes`/`outcomes` acima. Comportamento preservado exatamente.

### c) Lista JSON gravada como objeto some do inventário

`array_diff()` do PHP **preserva as chaves**. Ao remover um elemento do meio de
`inventory_warehouse_outcome_ids`, o array fica com buracos e `json_encode()` emite
**objeto** em vez de array:

```
[48,49,359,361,425]                      <- 138 linhas, correto
{"0":48,"1":49,"4":359,"5":361,"6":425}  <-  15 linhas, corrompido
```

A relação `hasManyJson` compila para `? MEMBER OF(col)` no MySQL, que **só percorre
arrays**. O `json_each()` do SQLite percorre arrays *e* objetos. Sem correção, itens
de inventário sumiriam silenciosamente do kardex e do balance.

**Correção em duas partes:** o importador normaliza objeto → array (preservando ordem
e valores), e [`InventoryWarehouseIncomeController.php:498`](../../app/Http/Controllers/InventoryWarehouseIncomeController.php:498)
passou a usar `array_values(array_diff(...))` — como os métodos do model já faziam.

> `outcomes_details` **não** é normalizada: ela é legitimamente um mapa chaveado pelo
> id do outcome, e o código faz `$outcomes_details[$outcome->id]`.

---

## 1.7bis Seis bugs que só apareceram nos endpoints da API

O teste de banco valida os geradores isoladamente. Rodar as **respostas HTTP reais**
pelo kernel do Laravel — rota, middleware, serialização JSON — revelou outra camada.

### d) Alias do `SELECT` usado no `WHERE` (erro 1054)

Três geradores fazem `->select('jobs.country as job_country')` e depois
`->where('job_country', ...)`. **O MySQL não aceita alias do `SELECT` no `WHERE`**
(só em `HAVING`/`GROUP BY`/`ORDER BY`); o SQLite aceita.

Derrubava com **500** os relatórios `jobs/by-costs`, `invoices/by-items` e
`general/general-records` sempre que o filtro de país era usado.
**Correção:** usar a coluna real `jobs.country`.

### e) Colunas fantasma no eager loading (erro 1054)

`'productItem:id,inventory_product_id,status,batch,amount,currency'` —
`inventory_product_items` **não tem** `amount` nem `currency` (são `buy_amount`/
`sell_amount` e `buy_currency`/`sell_currency`).

Mesma misfeature do SQLite do item (b): o identificador desconhecido virava string
literal e a API devolvia `"amount":"amount"`, `"currency":"currency"` — lixo que o
frontend nunca leu. No MySQL, **500** em `warehouse/{id}/loans-by-users`.
**Correção:** remover as duas colunas. Uma varredura confirmou que não há outros
eager-loads ou `select()` com coluna inexistente.

### f) Ordenação não-determinística em 8 pontos

Consultas com `->get()` sem `ORDER BY`, ou com `orderBy('date')` sobre colunas cujos
valores se repetem. O SQLite devolve na ordem de `rowid`; o InnoDB devolve na ordem
do índice que o otimizador escolher. A ordem chegava à resposta da API.

| Local | Correção |
|---|---|
| `RecordInventoryIncomesLoanables` (incomes + itens do eager load) | `orderBy('id')` |
| `RecordJobsByCosts`, `RecordInvoicesByItems`, `RecordGeneralRecords` | `orderBy('invoices.id')` |
| `RecordGeneralRecords` (incomes/outcomes de inventário e produtos por income) | desempate por `id` |
| `RecordInventoryProductsLoansKardex` | desempate por `id` |
| `BalanceAssistant` (4 consultas) | desempate por `id` |
| `ReportAssistant`, `ReportPDFCreator` | desempate por `id` |
| `InventoryWarehouseController` — incomes, outcomes, loans, loans-by-users | `orderBy('id')` |

Todas usam `id` como critério, que é exatamente a ordem `rowid` que o SQLite
entregava — o resultado atual é preservado, e passa a ser determinístico.

### g) Recorte de data em coluna `DATETIME`

`RecordReportsByTime` comparava `reports.created_at` (a **única** coluna `DATETIME`
comparada com uma data neste projeto) contra `format('c')`, que produz
`2024-12-31T00:00:00+00:00`:

- **SQLite** compara como texto — `' '` (0x20) < `'T'` (0x54) — e acaba incluindo o
  dia inteiro: **799 reports**.
- **MySQL** converte para `DATETIME` e corta em `00:00:00`: **796 reports**.

O MySQL chegava a aplicar o offset de fuso: `CAST('2024-01-01T00:00:00+00:00' AS
DATETIME)` devolve `2023-12-31 21:00:00`.

**Correção:** comparar com `'Y-m-d H:i:s'` e o dia fechado — **799 nos dois**,
preservando o resultado atual de produção.

> As demais comparações de data usam colunas `date`/`from_date`, que são `VARCHAR`
> (§1.2) e portanto comparam como texto nos dois motores. Não foram afetadas.

### h) Ruído de somatório vazando para a tela

`InventoryWarehouseIncome::amount()`, `Report::amount()` e o `total_amount` de
`ReportController` devolviam `364` no SQLite e `364.0000000000005` no MySQL (§1.8).
`WarehouseIncomes.vue:20` renderiza `{{ income.amount }}` **cru** no template — o
usuário veria os decimais de lixo.

**Correção:** `round(..., 6)` nesses três agregados. Seis casas preservam toda a
precisão real (as colunas têm no máximo 4) e eliminam o ruído.

---

## 1.9 Controle negativo: prova de que cada tipo é indispensável

Um teste que passa não vale nada se passaria de qualquer jeito. Estas são as mesmas
operações rodadas contra os tipos que a migration **original** geraria, no mesmo
servidor MySQL 8.0.46 em strict mode:

| Tipo original | Operação | Resultado |
|---|---|---|
| `DOUBLE(8,2)` (o que `float()` gera) | inserir `221137500.0` | **ERROR 1264** out of range |
| `DOUBLE(8,2)` | inserir `4881.3351` | grava **`4881.34`** — corrupção silenciosa |
| `VARCHAR(100)` | inserir 158 chars | **ERROR 1406** data too long |
| `JSON DEFAULT '[]'` | criar a tabela | **ERROR 1101** |
| `TIMESTAMP` | inserir o id `2493` | **ERROR 1292** incorrect datetime |
| `TEXT` | inserir 392.011 bytes | **ERROR 1406** data too long |
| `utf8mb4_unicode_ci` | `WHERE a='abc'` com `'ABC'` gravado | casa (**1**) — muda o resultado |
| `utf8mb4_bin` | idem | não casa (**0**) — igual ao SQLite |

Ou seja: sem as correções de tipo, a importação **abortaria** em cinco pontos e
**corromperia dinheiro em silêncio** num sexto.

---

## 1.8 `SUM()` de dinheiro: por que existe uma tolerância

Os valores **por linha** são bit-idênticos — 0 divergências em 114.000 linhas monetárias.
O que difere é a **acumulação**: o SQLite 3.44+ usa somatório compensado de
Kahan-Babuška-Neumaier em `sum()`, o MySQL acumula ingenuamente em binary64.

Medido nesta base:

| | valor |
|---|---|
| diferença relativa máxima | 4,9 × 10⁻¹⁴ |
| diferença absoluta máxima | 1,7 × 10⁻⁵ (sobre um total de 3,7 bilhões) |
| grupos em que `round(x, 2)` diverge | **0** |

Por isso `assertMoneyAggregate()` exige **igualdade exata do valor arredondado a 2 casas**
(o número que aparece em PDFs, planilhas e telas) **e** diferença relativa abaixo de
10⁻⁹ — cinco ordens de grandeza acima do ruído observado, mas apertado o bastante para
pegar truncamento real: `double(8,2)` produziria diferença na casa de 10⁻³.

---

## 2. Por que `LONGTEXT` e não o tipo `JSON` nativo

1. O MySQL **normaliza** documentos JSON: reordena as chaves dos objetos e remove
   espaços. Como os models fazem cast `'array'`, a ordem das chaves mudaria nas
   respostas da API.
2. Não aceita `DEFAULT` literal (§1.4).
3. `LONGTEXT` preserva os bytes exatamente como o SQLite guarda hoje.
4. A única relação JSON do projeto — `hasManyJson` em `InventoryWarehouseOutcome`
   → `inventory_warehouse_outcome_ids` — compila para `JSON_CONTAINS()`, que funciona
   normalmente sobre `LONGTEXT` com conteúdo JSON válido.

Auditoria: **100% dos valores** nessas colunas são JSON válido.
`kv_storage.value` chega a **285.374 bytes**, o que já excede `TEXT` (64 KB) e por si
só exige `LONGTEXT`.

---

## 3. Integridade referencial — por que não criamos FOREIGN KEYs

O banco de produção tem **2.342 `invoices` órfãs** (apontam para `reports` inexistentes),
somando **34.507.800,32** em `amount`:

| `report_id` | linhas |
|---:|---:|
| 2335 | 1.909 |
| 2338 | 384 |
| 2339 | 32 |
| 300660 / 10367576 / 10574091 | ids corrompidos |

Criar FKs faria a importação falhar. Mais importante: **hoje o app funciona com esses
órfãos** e os relatórios os ignoram via JOIN — adicionar FKs mudaria o comportamento.
Esta migração replica o estado atual; a limpeza é um projeto separado (§7).

---

## 4. Execução — dia da migração

### Pré-requisitos

```bash
mysql -u root -p -e "CREATE DATABASE maranatha CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Passo 1 — congelar a aplicação

```bash
php artisan down --render="errors::503"
```

### Passo 2 — copiar o SQLite de produção

```bash
sqlite3 /caminho/producao/database.sqlite ".backup '/tmp/migracao.sqlite'"
```

> `.backup` é consistente com escritas concorrentes; `cp` não é.

### Passos 3 a 5 — um comando só

O script faz snapshot, gera o SQL, importa, aplica as migrations pendentes e valida a
paridade. Ele **aborta** se a paridade falhar.

```bash
MYSQL_USER=root MYSQL_PWD='sua-senha' ./database/mysql-migration/migrate.sh /tmp/migracao.sqlite maranatha
```

Para executar os passos manualmente:

```bash
python3 database/mysql-migration/generate_migration_sql.py /tmp/migracao.sqlite /tmp/migracao.sql
mysql -u root -p --default-character-set=utf8mb4 maranatha < /tmp/migracao.sql
php artisan migrate --force
DB_REF_DATABASE=/tmp/migracao.sqlite php artisan mysql:parity --json=storage/parity.json
```

**Só prossiga com `0 divergências`.** Qualquer falha aqui é um bug de dados em produção.

> **Ao conferir dados pelo cliente `mysql`, passe sempre `--default-character-set=utf8mb4`.**
> Sem isso o terminal mostra `M?quina de soldar ? ??? ??` e parece corrupção de dados,
> quando os bytes gravados estão intactos (verificado por `HEX()`: acentos, CJK e
> emoji de 4 bytes idênticos aos do SQLite).

### Passo 6 — trocar a conexão

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maranatha
DB_USERNAME=...
DB_PASSWORD=...
```

```bash
php artisan config:clear && php artisan cache:clear
php artisan migrate:status   # deve listar tudo como "Ran" — nada a executar
```

### Passo 7 — liberar

```bash
php artisan up
```

### Rollback

Reverter `DB_CONNECTION=sqlite` no `.env` e rodar `php artisan config:clear`.
O arquivo SQLite original não é tocado em nenhum passo.

---

## 5. Configuração obrigatória do servidor MySQL

```ini
[mysqld]
sql_mode = ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci
max_allowed_packet = 256M          # a maior linha de kv_storage tem 285 KB
innodb_buffer_pool_size = 1G       # >= o tamanho do banco (46 MB hoje)
```

`max_allowed_packet` também limita o tamanho dos `INSERT` em lote do script.

---

## 6. Colação: por que `utf8mb4_bin`

O SQLite compara `TEXT` como **BINARY**. A colação `utf8mb4_unicode_ci` equipara caixa
**e** acento, o que muda o resultado de consultas reais nesta base:

| Consulta | SQLite | MySQL `_ci` | MySQL `_bin` |
|---|---:|---:|---:|
| `job_code = '2000.01-pe[ups]'` (gravado maiúsculo) | 0 | 276 | **0** |
| `jobs.code = '0000-pe[ups]'` | 0 | 1 | **0** |
| `workers.dni = 'n10989566'` | 0 | 1 | **0** |
| `name = 'Rodillera Rigida'` (gravado com acento) | 0 | 1 | **0** |

Há ainda 4 valores distintos em `inventory_products.name` e 4 em `balances.ticket_number`
que **colidem** sob `_ci` — dois registros hoje distintos passariam a ser tratados como
iguais em `GROUP BY`, `DISTINCT` e joins.

Por isso as colunas de texto usam `utf8mb4_bin`. A colação **padrão da tabela** continua
`utf8mb4_unicode_ci`, para que colunas criadas por migrations futuras nasçam com o
comportamento usual do MySQL.

### A única divergência aceita: `LIKE`

Não existe colação do MySQL que reproduza as duas regras do SQLite ao mesmo tempo:

- `=` no SQLite é BINARY (sensível a caixa) → `utf8mb4_bin` reproduz ✓
- `LIKE` no SQLite **ignora** caixa (em ASCII) → `utf8mb4_bin` **não** reproduz ✗

Escolhemos `_bin` porque `=`, `ORDER BY`, `DISTINCT`, `GROUP BY` e os índices `UNIQUE`
dependem dele, enquanto **`LIKE` não é usado**: o único `where(..., 'LIKE', ...)` do
projeto está dentro de um bloco comentado em
[`InventoryWarehouseOutcome.php:51`](../../app/Models/InventoryWarehouseOutcome.php:51).

O guard test `test_nenhuma_query_like_sem_colacao_explicita` falha se alguém introduzir
um. Quando precisar de `LIKE` insensível a caixa:

```php
->whereRaw('name LIKE ? COLLATE utf8mb4_0900_as_ci', ["%{$termo}%"])
```

`utf8mb4_0900_as_ci` ignora caixa mas **respeita acento**, como o SQLite.

**Colisão nos índices UNIQUE:** verificada — `users.email`, `users.username` e
`personal_access_tokens.token` não têm colisões nem sob `_ci`. A migração não quebra logins.

---

## 6.1 Outras diferenças que a suíte cobre

| Área | SQLite | MySQL 8.0 | Nível |
|---|---|---|---|
| `GROUP BY` | aceita coluna não agregada | `ONLY_FULL_GROUP_BY` rejeita (1055) | `logic` |
| Identificador inexistente entre aspas duplas | vira string literal | erro 1054 | `logic` |
| `MEMBER OF` / `json_each` | percorre array **e** objeto | só array | `logic` |
| Ordem sem `ORDER BY` | ordem de `rowid` | ordem do índice InnoDB | `queries`, `logic` |
| Tipo PHP devolvido | nativo (int/float/bool) | idem no PHP 8.3 + `pdo_mysql` | `rows` |
| `SUM()` de float | Kahan compensado | acumulação ingênua | `aggregates` |

**Tipos PHP:** verificado empiricamente que `pdo_mysql` no PHP 8.3 devolve `double`,
`integer` e `boolean` nativos — o JSON da API sai byte-idêntico
(`{"amount":642.45,"report_id":36}`). O risco de o frontend quebrar em
`invoice.amount.toFixed(2)` **não se materializa** nesta stack. Ele voltaria se alguém
ativasse `PDO::ATTR_EMULATE_PREPARES => true` nas opções da conexão.

---

## 6.2 Deriva entre o schema importado e `php artisan migrate`

Um banco criado do zero precisa ser equivalente ao de produção. Após as correções:

- **0 divergências semânticas** (tipo com comportamento diferente)
- 86 diferenças apenas de **largura**, sempre com o importado ≥ o do `migrate` — sem risco

A migration [`2026_08_14_000000_align_schema_for_mysql`](../migrations/2026_08_14_000000_align_schema_for_mysql.php)
cuida do que não dá para corrigir nas migrations originais:

- `TIMESTAMP` → `DATETIME` em todas as colunas de data geridas pelo Laravel.
  `$table->timestamps()` gera `TIMESTAMP`, que só cobre 1970–2038 e **converte fuso**
  a cada leitura/escrita. Para uma base contábil, um deslocamento silencioso em
  `created_at` é inaceitável.
- `kv_storage.value` → `LONGTEXT`. A maior linha tem **285.374 bytes**; `TEXT` para em
  65.535. A migration vem do pacote `softinklab/laravel-keyvalue-storage` e não pode ser editada.
- `ENUM` → `VARCHAR` em `invoices.type`, `reports.type`, `balances.type`. **`ENUM` ordena
  pela ordem de declaração**, `VARCHAR` ordena alfabeticamente como o SQLite.
- `invoices.pdf` `INT` → `VARCHAR(255)` (guarda nome de arquivo).
- `inventory_products.image` → `VARCHAR(1000)` (dado real: 297 bytes).

Ela é idempotente e não faz nada em SQLite.

---

## 6.3 Cache desativado para a comparação entre servidores

Todo o cache de **resultado** está comentado no código, cada ponto marcado com:

```php
// !!!TODO: Uncomment on production
```

São 13 blocos de leitura (`if (RecordsCache::getRecord(...)){...}`), 61 chamadas de
uma linha (`storeRecord`, `clearRecord`, `clearAll`) em 19 arquivos, mais o cache de
30 minutos da planilha de workers em `Excel.php`.

Para restaurar: `grep -rn '!!!TODO: Uncomment on production' app/`

**Por que:** com o cache ligado, comparar dois servidores é enganoso — um deles pode
responder de memória. Desligado, toda requisição recalcula a partir do banco, e a
diferença que aparecer é diferença de verdade.

### O que NÃO foi comentado, e por quê

| Local | Motivo |
|---|---|
| `RecordsCache.php`, `DataCache.php` | São a implementação, não o uso. Ficam intactas para o revert ser trivial — sem nenhum ponto de chamada ativo, já estão inertes. |
| `ReportPDFCreator.php:451` + `ReportController.php:239` | Não é cache de resultado: é o canal de progresso da geração de PDF. Comentar quebraria o endpoint `check-progress-pdf-download`. |

Verificação de que o cache está mesmo desligado (duas vias independentes):

```
php artisan mysql:parity-api --poison
   -> "Nenhuma chave foi escrita no Redis em 26 endpoints"

redis-cli flushall && <uma chamada de relatório> && redis-cli dbsize
   -> 0
```

O comando `--poison` reprova o estado intermediário — cache que **grava mas não lê** —
porque nesse caso a checagem de `is_cached` da suíte principal não garantiria nada.

---

## 7. Pós-migração (projetos separados, fora deste escopo)

1. **Nenhuma transação no código.** Não existe um único `DB::transaction`,
   `beginTransaction` ou `lockForUpdate` em `app/`. No SQLite isso passava despercebido
   porque só há um escritor por vez; o InnoDB permite escrita concorrente de verdade.
   `InventoryWarehouseOutcomeController::store` marca item por item como vendido —
   uma falha no meio deixa o inventário inconsistente. **É o maior risco novo que o
   MySQL introduz**, e não é resolvível na migração de dados.
2. **Limpar as 2.342 invoices órfãs** e então adicionar FOREIGN KEYs.
3. **Normalizar as datas** de `VARCHAR` para `DATETIME` em UTC, ajustando models,
   controllers e frontend juntos.
4. **Trocar `DOUBLE` por `DECIMAL(18,4)`** nas colunas monetárias. É o tipo correto para
   contabilidade e eliminaria o ruído de somatório descrito em §1.8 — mas muda o
   resultado dos `SUM()` na última casa e precisa da sua própria rodada de validação.
5. **Avaliar `utf8mb4_unicode_ci`** nas colunas de busca (`name`, `description`). Busca
   insensível a caixa e acento costuma ser melhor UX, mas é mudança de comportamento
   (§6) e merece validação própria.

---

## 8. Estado da validação

Executado contra a cópia de produção (46 MB) e um MySQL 8.0.46 local:

```
php artisan mysql:parity          ->  163 checagens, 0 divergências
php artisan mysql:parity-api      ->   47 endpoints, 0 divergências
php artisan mysql:parity-write    ->   22 cenários de escrita, 0 divergências
php artisan mysql:parity-api --poison -> 20 de 26 endpoints seriam contaminados
./vendor/bin/phpunit              ->   18 testes, OK
./database/mysql-migration/migrate.sh -> ponta a ponta, paridade OK
php artisan migrate               ->   57 migrations do zero no MySQL, todas DONE
```

### Cobertura

| Nível | O que prova |
|---|---|
| `structure` | 22 tabelas, colunas e contagens idênticas |
| `rows` | valor **e** tipo PHP de cada célula de cada tabela |
| `aggregates` | `SUM`/`AVG`/`MIN`/`MAX`/`COUNT` de dinheiro, com e sem `GROUP BY` |
| `queries` | datas ISO em `VARCHAR`, colação, `ONLY_FULL_GROUP_BY`, `whereIn` grande, `JSON_CONTAINS`, ordenação |
| `logic` | kardex, balance, stock, custos, `hasManyJson`, `belongsToJson`, FKs que eram `timestamp` |
| **API** | **47 respostas HTTP reais**, incluindo 2,2 MB de `general-records` e 8,7 MB de `invoices` |
| **escrita** | **22 cenários de INSERT/UPDATE/DELETE** — é onde o strict mode falha |

### Por que a suíte de escrita existe

`mysql:parity` e `mysql:parity-api` só **leem**. Mas os erros de strict mode acontecem
no `INSERT`: 1364 (coluna TEXT sem default), 1406 (VARCHAR curto), 1264 (fora de faixa),
1292 (datetime inválido). A correção do §1.4 — mover os defaults `'[]'`/`'{}'` para
`$attributes` nos models — só é exercitada gravando de verdade.

`mysql:parity-write` grava nos **dois** bancos e compara a linha **relida do banco**
(não o objeto em memória — é assim que truncamento e arredondamento silencioso
aparecem). Cobre: defaults de coluna TEXT em 7 models, dinheiro acima de 1e6 e com 4
e 12 casas decimais, `VARCHAR` no limite exato do dado real, datas ISO com offset
`-03:00`, as FKs que eram `timestamp`, acentos/CJK/emoji de 4 bytes, round-trip de
JSON, o ciclo `addOutcome`/`removeOutcome` (o bug do `array_diff`), 392 KB em
`kv_storage`, `UPDATE` em massa e `DELETE`.

Ele **escreve**, então exige `--confirmo` e alvos descartáveis:

```bash
sqlite3 producao.sqlite ".backup '/tmp/scratch.sqlite'"
mysql -u root -p -e "CREATE DATABASE maranatha_write CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
python3 database/mysql-migration/generate_migration_sql.py /tmp/scratch.sqlite /tmp/w.sql
mysql -u root -p --default-character-set=utf8mb4 maranatha_write < /tmp/w.sql
DB_REF_DATABASE=/tmp/scratch.sqlite DB_DATABASE=maranatha_write \
  php artisan mysql:parity-write --confirmo
```

Os 8 guard tests em [`MysqlSchemaGuardTest`](../../tests/Feature/MysqlSchemaGuardTest.php)
rodam sem banco e impedem a reintrodução de cada defeito.

### Como o cache é neutralizado

`RecordsCache` e `DataCache` montam a chave como `md5(json_encode($params))` — **sem
incluir a conexão do banco**. Rodar o mesmo endpoint no SQLite e depois no MySQL
devolveria, na segunda vez, o resultado memorizado da primeira, e a comparação
passaria mesmo com o MySQL completamente quebrado.

Três defesas, todas obrigatórias:

1. **Cache limpo antes de cada requisição** — Redis (`Cache::store('redis')->flush()`),
   o store padrão e o `flushdb()` da conexão crua.
2. **`is_cached` tem de ser `false`** em toda resposta que carrega o campo. Se a
   limpeza falhar, a segunda passada denuncia com `true`.
3. **`--poison` prova que a defesa é necessária**: roda o SQLite, depois o MySQL *sem*
   limpar, e confirma que **20 de 26** endpoints com cache devolveriam o resultado do
   SQLite. Se esse teste não detectasse contaminação, significaria que o cache não está
   ativo — e aí a defesa 2 seria vazia. O comando falha nesse caso.

O comando também verifica, antes de tudo, que o Redis grava e lê de volta.

> Os 6 endpoints não contaminados têm a **leitura** do cache comentada no código
> (kardex, balance, incomes-loanables, warehouse stock): gravam em Redis a cada
> requisição e nunca leem de volta. Não é problema de migração, mas é desperdício
> — vale limpar depois.
