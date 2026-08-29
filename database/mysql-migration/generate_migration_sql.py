#!/usr/bin/env python3
"""
Gera o script SQL de migracao SQLite -> MySQL 8.0 para o banco Maranatha.

Uso:
    python3 generate_migration_sql.py <database.sqlite> <saida.sql> [--schema-only|--data-only]

O SQL gerado e' auto-contido e idempotente no nivel de tabela (DROP + CREATE).
Ele NAO cria o database nem o usuario — faca isso antes (ver runbook).

Garantias de fidelidade:
  - DOUBLE para dinheiro, escrito com repr() (shortest round-trip) => bits identicos ao SQLite
  - VARCHAR para as datas ISO-8601 com offset => string preservada byte a byte
  - LONGTEXT para JSON => sem normalizacao/reordenacao de chaves do MySQL
  - AUTO_INCREMENT restaurado a partir de sqlite_sequence
  - tabela `migrations` preservada => `php artisan migrate` nao re-executa nada
"""
from __future__ import annotations

import sys
import os
import re
import sqlite3

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from mapping import (  # noqa: E402
    MONEY_COLUMNS, ISO_DATE_COLUMNS, TYPE_LIES, JSON_COLUMNS,
    LARAVEL_DATETIME_COLUMNS, INTEGER_EPOCH_COLUMNS, TRUNCATE_ON_IMPORT,
    SKIP_TABLES, varchar_size, safe_index_name, CREATE_FOREIGN_KEYS,
    TEXT_COLLATION, TABLE_COLLATION, JSON_LIST_COLUMNS,
)
import json as _json

BATCH = 500  # linhas por INSERT


# --------------------------------------------------------------------------- util
def ident(name: str) -> str:
    return "`" + name.replace("`", "``") + "`"


def esc_bytes(b: bytes) -> str:
    """Escapa uma string para literal MySQL preservando os bytes exatos."""
    try:
        sval = b.decode("utf-8")
    except UnicodeDecodeError:
        # Byte invalido em UTF-8: emite literal hexadecimal para nao perder dado.
        return "0x" + b.hex()
    out = []
    for ch in sval:
        if ch == "\\":
            out.append("\\\\")
        elif ch == "'":
            out.append("''")
        elif ch == "\n":
            out.append("\\n")
        elif ch == "\r":
            out.append("\\r")
        elif ch == "\0":
            out.append("\\0")
        elif ch == "\x1a":
            out.append("\\Z")
        else:
            out.append(ch)
    return "'" + "".join(out) + "'"


def literal(v) -> str:
    if v is None:
        return "NULL"
    if isinstance(v, bool):
        return "1" if v else "0"
    if isinstance(v, int):
        return str(v)
    if isinstance(v, float):
        # repr() = menor representacao decimal que faz round-trip exato no binary64.
        # Garante que o DOUBLE do MySQL receba exatamente os mesmos bits do SQLite.
        if v != v:
            return "NULL"          # NaN
        if v in (float("inf"), float("-inf")):
            raise SystemExit(f"Valor infinito encontrado: {v!r}")
        return repr(v)
    if isinstance(v, bytes):
        return esc_bytes(v)
    return esc_bytes(str(v).encode("utf-8"))



def normalize_json_list(raw, table: str, col: str, rowid) -> bytes:
    """
    Converte lista-gravada-como-objeto de volta para array. Ver mapping.py 8.5.

    {"0":48,"1":49,"4":359}  ->  [48,49,359]

    Preserva a ORDEM das chaves como elas aparecem no documento, que e' a ordem em
    que o PHP as gravou — o mesmo que json_each() do SQLite devolve hoje.
    """
    if raw is None or (table, col) not in JSON_LIST_COLUMNS:
        return raw
    try:
        texto = raw.decode("utf-8") if isinstance(raw, bytes) else str(raw)
        doc = _json.loads(texto)
    except Exception:
        return raw
    if not isinstance(doc, dict):
        return raw
    if doc and not all(str(k).lstrip("-").isdigit() for k in doc):
        raise SystemExit(
            f"{table}.{col} (linha {rowid}) e' um objeto com chaves nao numericas "
            f"({list(doc)[:5]}), mas esta declarada como lista em JSON_LIST_COLUMNS.")
    normalizado = _json.dumps(list(doc.values()), separators=(",", ":"),
                              ensure_ascii=False)
    return normalizado.encode("utf-8")


# --------------------------------------------------------------------------- perfil
def profile_column(cur, table: str, col: str) -> dict:
    cur.execute(
        f'SELECT typeof({ident(col)}) t, COUNT(*) FROM {ident(table)} GROUP BY t')
    types = {r[0]: r[1] for r in cur.fetchall()}
    cur.execute(
        f'SELECT MAX(LENGTH(CAST({ident(col)} AS BLOB))) FROM {ident(table)}')
    max_bytes = cur.fetchone()[0]
    return {"types": types, "max_bytes": max_bytes}


def choose_type(table: str, col: str, decl: str, notnull: int, default,
                is_pk: bool, prof: dict) -> str:
    """Decide o tipo MySQL da coluna. A ordem das regras importa."""
    key = (table, col)
    types = {t for t in prof["types"] if t != "null"}
    has_null_values = "null" in prof["types"]
    nullable = (not notnull) and not is_pk

    def nn(sqltype: str, dflt: str | None = None) -> str:
        s = sqltype
        s += " NULL" if nullable else " NOT NULL"
        if dflt is not None:
            s += f" DEFAULT {dflt}"
        elif nullable:
            s += " DEFAULT NULL"
        return s

    # 1. chave primaria auto-incremento
    if is_pk and decl.upper() == "INTEGER":
        return "BIGINT UNSIGNED NOT NULL AUTO_INCREMENT"

    # 2. colunas cujo tipo declarado mente sobre o dado (override manual)
    if key in TYPE_LIES:
        return TYPE_LIES[key]

    # 3. dinheiro -> DOUBLE (jamais double(8,2))
    if key in MONEY_COLUMNS:
        d = None
        if default is not None:
            d = str(default).strip("'\"")
        return nn("DOUBLE", d)

    # 4. datas ISO-8601 com offset -> VARCHAR
    if key in ISO_DATE_COLUMNS:
        return nn("VARCHAR(40)")

    # 5. colunas JSON -> LONGTEXT (defaults literais sao permitidos em TEXT? nao)
    #    MySQL nao aceita DEFAULT em BLOB/TEXT: o default vive no model/migration.
    if key in JSON_COLUMNS:
        return "LONGTEXT NULL" if nullable else "LONGTEXT NOT NULL"

    # 6. epoch inteiro na tabela de fila
    if key in INTEGER_EPOCH_COLUMNS:
        return nn("BIGINT")

    # 7. datetimes reais geridos pelo Laravel
    if key in LARAVEL_DATETIME_COLUMNS or ("*", col) in LARAVEL_DATETIME_COLUMNS:
        return nn("DATETIME")

    # 8. booleanos
    if decl.lower().startswith("tinyint"):
        d = str(default).strip("'\"") if default is not None else None
        return nn("TINYINT(1)", d)

    # 9. derivacao pelo dado real observado
    if types == {"integer"}:
        d = str(default).strip("'\"") if default is not None else None
        return nn("BIGINT", d)
    if types == {"real"} or types == {"integer", "real"}:
        d = str(default).strip("'\"") if default is not None else None
        return nn("DOUBLE", d)
    if types == {"text"} or types == {"text", "integer"} or types == {"text", "real"}:
        t = varchar_size(prof["max_bytes"])
        if t.endswith("TEXT"):
            return f"{t} NULL" if nullable else f"{t} NOT NULL"
        d = None
        if default is not None:
            d = literal(str(default).strip("'\""))
        return nn(t, d)

    # 10. coluna 100% NULL ou tabela vazia -> honra a intencao da migration
    if not types or types == set():
        dl = decl.lower()
        if dl.startswith("int") or dl == "integer":
            return nn("BIGINT")
        if dl in ("datetime", "timestamp", "date"):
            return nn("DATETIME")
        if dl.startswith("float") or dl.startswith("double") or dl.startswith("numeric"):
            return nn("DOUBLE")
        if dl == "text":
            return "LONGTEXT NULL" if nullable else "LONGTEXT NOT NULL"
        d = literal(str(default).strip("'\"")) if default is not None else None
        return nn(f"VARCHAR({255})", d)

    raise SystemExit(
        f"Nao foi possivel decidir o tipo de {table}.{col} "
        f"(decl={decl!r}, storage={prof['types']})")


def apply_collation(coltype: str) -> str:
    """Colunas de texto comparam byte a byte, como o SQLite. Ver mapping.py secao 9."""
    m = re.match(r"^(VARCHAR\(\d+\)|(?:LONG|MEDIUM|TINY)?TEXT)(.*)$", coltype, re.S)
    if not m:
        return coltype
    return f"{m.group(1)} COLLATE {TEXT_COLLATION}{m.group(2)}"


# --------------------------------------------------------------------------- indices
# InnoDB limita uma chave de indice a 3072 bytes. Em utf8mb4 cada caractere custa
# ate' 4 bytes, entao um indice composto por VARCHAR estoura facilmente — o
# indice UNIQUE de pulse_aggregates (bucket, period, type, aggregate, key_hash)
# custaria 3076 bytes. Quando isso acontece, aplicamos prefixo nas colunas de texto.
INNODB_MAX_KEY_BYTES = 3072
MIN_PREFIX_CHARS = 32


def column_key_bytes(coltype: str) -> tuple[int, bool]:
    """(custo em bytes no indice, e' coluna de texto?)"""
    m = re.match(r"^VARCHAR\((\d+)\)", coltype)
    if m:
        return int(m.group(1)) * 4, True
    if re.match(r"^(LONG|MEDIUM|TINY)?TEXT", coltype):
        return 10 ** 9, True          # exige prefixo obrigatoriamente
    if coltype.startswith("BIGINT"):
        return 8, False
    if coltype.startswith("DOUBLE"):
        return 8, False
    if coltype.startswith("TINYINT"):
        return 1, False
    if coltype.startswith("DATETIME"):
        return 8, False
    return 8, False


def index_parts(table: str, cols: list[str], types: dict, profs: dict,
                idx_name: str, unique: bool) -> list[str]:
    custos = [column_key_bytes(types.get(c, "")) for c in cols]
    total = sum(c for c, _ in custos)
    texto = [i for i, (_, t) in enumerate(custos) if t]

    if total <= INNODB_MAX_KEY_BYTES or not texto:
        return [ident(c) for c in cols]

    fixo = sum(c for i, (c, _) in enumerate(custos) if i not in texto)
    prefixo = max(MIN_PREFIX_CHARS,
                  (INNODB_MAX_KEY_BYTES - fixo) // (4 * len(texto)))

    partes = []
    for i, c in enumerate(cols):
        if i not in texto:
            partes.append(ident(c))
            continue
        declarado = re.match(r"^VARCHAR\((\d+)\)", types.get(c, ""))
        limite = int(declarado.group(1)) if declarado else prefixo
        p = min(prefixo, limite)

        # Um prefixo menor que o maior valor real tornaria o indice UNIQUE mais
        # restritivo que a coluna: duas linhas distintas colidiriam no prefixo.
        maior = profs.get(c, {}).get("max_bytes") or 0
        if unique and p < maior:
            raise SystemExit(
                f"Indice UNIQUE '{idx_name}' em {table} exigiria prefixo de {p} chars "
                f"na coluna '{c}', mas ha' valores de {maior} bytes. Isso mudaria a "
                f"semantica de unicidade. Reduza o VARCHAR da coluna ou revise o indice.")
        partes.append(f"{ident(c)}({p})")
    return partes


# --------------------------------------------------------------------------- schema
def build_schema(con, out) -> dict:
    cur = con.cursor()
    cur.execute(
        "SELECT name, sql FROM sqlite_master WHERE type='table' "
        "AND name NOT LIKE 'sqlite_%' ORDER BY name")
    tables = cur.fetchall()
    coltypes: dict[str, dict[str, str]] = {}
    profiles: dict[str, dict[str, dict]] = {}

    for table, _sql in tables:
        if table in SKIP_TABLES:
            continue
        cur.execute(f'PRAGMA table_info({ident(table)})')
        cols = cur.fetchall()
        pk_cols = [c[1] for c in cols if c[5]]
        autoinc = bool(re.search(r"AUTOINCREMENT", _sql or "", re.I))

        defs, coltypes[table], profiles[table] = [], {}, {}
        for cid, name, decl, notnull, default, pk in cols:
            prof = profile_column(cur, table, name)
            profiles[table][name] = prof
            is_pk_auto = bool(pk) and autoinc and len(pk_cols) == 1
            t = choose_type(table, name, decl or "", notnull, default,
                            is_pk_auto, prof)
            t = apply_collation(t)
            coltypes[table][name] = t
            defs.append(f"  {ident(name)} {t}")

        if pk_cols:
            defs.append("  PRIMARY KEY (" +
                        ", ".join(ident(c) for c in pk_cols) + ")")

        # indices
        cur.execute(
            "SELECT name, sql FROM sqlite_master WHERE type='index' "
            "AND tbl_name=? AND sql IS NOT NULL ORDER BY name", (table,))
        for idx_name, idx_sql in cur.fetchall():
            cur.execute(f'PRAGMA index_info({ident(idx_name)})')
            idx_cols = [r[2] for r in cur.fetchall()]
            if not idx_cols:
                continue
            unique = bool(re.search(r"CREATE\s+UNIQUE\s+INDEX", idx_sql, re.I))
            parts = index_parts(table, idx_cols, coltypes[table], profiles[table],
                                idx_name, unique)
            kind = "UNIQUE KEY" if unique else "KEY"
            defs.append(
                f"  {kind} {ident(safe_index_name(idx_name))} (" +
                ", ".join(parts) + ")")

        out.write(f"DROP TABLE IF EXISTS {ident(table)};\n")
        out.write(f"CREATE TABLE {ident(table)} (\n")
        out.write(",\n".join(defs))
        out.write("\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 "
                  f"COLLATE={TABLE_COLLATION};\n\n")

    if CREATE_FOREIGN_KEYS:
        raise SystemExit("CREATE_FOREIGN_KEYS=True nao suportado: ha' 2.342 orfaos.")
    return coltypes


# --------------------------------------------------------------------------- dados
def build_data(con, out, coltypes: dict) -> None:
    con.text_factory = bytes
    cur = con.cursor()
    cur.execute(
        "SELECT name FROM sqlite_master WHERE type='table' "
        "AND name NOT LIKE 'sqlite_%' ORDER BY name")
    tables = [r[0].decode() if isinstance(r[0], bytes) else r[0]
              for r in cur.fetchall()]

    for table in tables:
        if table in SKIP_TABLES:
            continue
        if table in TRUNCATE_ON_IMPORT:
            out.write(f"-- {table}: importada vazia por decisao de projeto "
                      f"(estrutura efemera/telemetria)\n\n")
            continue

        cur.execute(f'PRAGMA table_info({ident(table)})')
        cols = [c[1].decode() if isinstance(c[1], bytes) else c[1]
                for c in cur.fetchall()]
        collist = ", ".join(ident(c) for c in cols)

        cur.execute(f'SELECT COUNT(*) FROM {ident(table)}')
        total = cur.fetchone()[0]
        if not total:
            out.write(f"-- {table}: 0 linhas\n\n")
            continue

        out.write(f"-- {table}: {total} linhas\n")
        cur.execute(f'SELECT {collist} FROM {ident(table)}')
        batch, written = [], 0
        while True:
            rows = cur.fetchmany(BATCH)
            if not rows:
                break
            for row in rows:
                valores = [normalize_json_list(v, table, cols[i], row[0])
                           for i, v in enumerate(row)]
                batch.append("(" + ",".join(literal(v) for v in valores) + ")")
            out.write(f"INSERT INTO {ident(table)} ({collist}) VALUES\n")
            out.write(",\n".join(batch))
            out.write(";\n")
            written += len(batch)
            batch = []
        assert written == total, f"{table}: {written} != {total}"
        out.write("\n")

    # AUTO_INCREMENT a partir de sqlite_sequence
    con.text_factory = str
    cur = con.cursor()
    try:
        cur.execute("SELECT name, seq FROM sqlite_sequence ORDER BY name")
        seqs = cur.fetchall()
    except sqlite3.OperationalError:
        seqs = []
    if seqs:
        out.write("-- AUTO_INCREMENT restaurado a partir de sqlite_sequence\n")
        for name, seq in seqs:
            if name in SKIP_TABLES:
                continue
            if name in TRUNCATE_ON_IMPORT:
                continue
            out.write(f"ALTER TABLE {ident(name)} AUTO_INCREMENT = {int(seq) + 1};\n")
        out.write("\n")


# --------------------------------------------------------------------------- main
def main() -> None:
    if len(sys.argv) < 3:
        raise SystemExit(__doc__)
    src, dst = sys.argv[1], sys.argv[2]
    mode = sys.argv[3] if len(sys.argv) > 3 else "--all"

    con = sqlite3.connect(f"file:{src}?mode=ro", uri=True)
    with open(dst, "w", encoding="utf-8") as out:
        out.write("-- ============================================================\n")
        out.write("-- Migracao Maranatha: SQLite -> MySQL 8.0\n")
        out.write(f"-- Origem: {os.path.abspath(src)}\n")
        out.write("-- Gerado por database/mysql-migration/generate_migration_sql.py\n")
        out.write("-- ============================================================\n\n")
        out.write("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n")
        out.write("SET SESSION sql_mode = "
                  "'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,"
                  "NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';\n")
        out.write("SET SESSION foreign_key_checks = 0;\n")
        out.write("SET SESSION unique_checks = 0;\n")
        out.write("SET SESSION autocommit = 0;\n\n")

        coltypes = {}
        if mode in ("--all", "--schema-only"):
            out.write("-- ---------- ESQUEMA ----------\n\n")
            coltypes = build_schema(con, out)
        if mode in ("--all", "--data-only"):
            if not coltypes:
                coltypes = build_schema(con, open(os.devnull, "w"))
            out.write("-- ---------- DADOS ----------\n\n")
            build_data(con, out, coltypes)

        out.write("COMMIT;\n")
        out.write("SET SESSION foreign_key_checks = 1;\n")
        out.write("SET SESSION unique_checks = 1;\n")
    con.close()
    size = os.path.getsize(dst)
    print(f"OK -> {dst} ({size/1024/1024:.1f} MB)")


if __name__ == "__main__":
    main()
