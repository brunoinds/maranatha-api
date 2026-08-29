#!/usr/bin/env bash
#
# Migracao Maranatha: SQLite -> MySQL 8.0
#
#   ./database/mysql-migration/migrate.sh <database.sqlite> <banco_mysql>
#
# Le as credenciais do MySQL do ambiente (nunca da linha de comando, que fica no
# histórico do shell e visivel em `ps`):
#
#   export MYSQL_USER=root
#   export MYSQL_PWD=...        # lida diretamente pelo cliente mysql
#   export MYSQL_HOST=127.0.0.1 # opcional
#
# O arquivo SQLite de origem nunca e' modificado.
set -euo pipefail

SQLITE_DB="${1:?uso: migrate.sh <database.sqlite> <banco_mysql>}"
MYSQL_DB="${2:?uso: migrate.sh <database.sqlite> <banco_mysql>}"
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_USER="${MYSQL_USER:?defina MYSQL_USER}"

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$AQUI/../.." && pwd)"
TRABALHO="$(mktemp -d)"
trap 'rm -rf "$TRABALHO"' EXIT

msg() { printf '\n\033[1;34m==>\033[0m %s\n' "$*"; }

[[ -f "$SQLITE_DB" ]] || { echo "arquivo nao encontrado: $SQLITE_DB" >&2; exit 1; }

# ---------------------------------------------------------------- 1. copia consistente
# `.backup` respeita escritas concorrentes; `cp` de um SQLite ativo pode capturar
# um arquivo corrompido no meio de uma transacao.
msg "Copiando o SQLite de origem (snapshot consistente)"
sqlite3 "$SQLITE_DB" ".backup '$TRABALHO/origem.sqlite'"
sqlite3 "$TRABALHO/origem.sqlite" "PRAGMA integrity_check;" | head -1

# ---------------------------------------------------------------- 2. gerar o SQL
msg "Gerando o SQL de migracao"
python3 "$AQUI/generate_migration_sql.py" "$TRABALHO/origem.sqlite" "$TRABALHO/migracao.sql"

# ---------------------------------------------------------------- 3. importar
msg "Importando em $MYSQL_DB@$MYSQL_HOST"
mysql --host="$MYSQL_HOST" --user="$MYSQL_USER" \
      --default-character-set=utf8mb4 \
      --max_allowed_packet=256M \
      "$MYSQL_DB" < "$TRABALHO/migracao.sql"

# ---------------------------------------------------------------- 4. migrations pendentes
# A tabela `migrations` vem do SQLite, entao tudo o que ja' rodou continua marcado
# como executado. So' as migrations posteriores (ex.: a de alinhamento MySQL) rodam.
msg "Aplicando migrations pendentes"
cd "$RAIZ"
php artisan migrate --force

# ---------------------------------------------------------------- 5. validar
msg "Validando paridade SQLite x MySQL"
if DB_REF_DATABASE="$TRABALHO/origem.sqlite" \
   php -d memory_limit=2G artisan mysql:parity --json="$TRABALHO/parity.json" \
   && DB_REF_DATABASE="$TRABALHO/origem.sqlite" \
   php -d memory_limit=2G artisan mysql:parity-api --json="$TRABALHO/parity-api.json"; then
    msg "PARIDADE OK — a migracao pode ser liberada"
    CARIMBO="$(date +%Y%m%d-%H%M%S)"
    cp "$TRABALHO/parity.json" "$RAIZ/storage/logs/parity-$CARIMBO.json"
    cp "$TRABALHO/parity-api.json" "$RAIZ/storage/logs/parity-api-$CARIMBO.json"
else
    echo >&2
    echo "PARIDADE FALHOU — NAO libere a aplicacao." >&2
    cp "$TRABALHO/parity.json" "$RAIZ/storage/logs/parity-FALHOU.json" 2>/dev/null || true
    cp "$TRABALHO/parity-api.json" "$RAIZ/storage/logs/parity-api-FALHOU.json" 2>/dev/null || true
    echo "Relatorios: storage/logs/parity-FALHOU.json e parity-api-FALHOU.json" >&2
    exit 1
fi
