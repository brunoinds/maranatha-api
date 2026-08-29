<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardas estaticas contra os cinco defeitos de schema que inviabilizaram a
 * primeira tentativa de migracao para MySQL 8.0.
 *
 * Nao precisam de banco: leem as migrations e os models. Rodam em CI e falham
 * assim que alguem reintroduzir um dos padroes.
 *
 * O "porque" de cada regra esta em database/mysql-migration/README.md.
 */
class MysqlSchemaGuardTest extends TestCase
{
    private function migrationFiles(): array
    {
        return glob(database_path('migrations/*.php')) ?: [];
    }

    private function modelFiles(): array
    {
        return glob(app_path('Models/*.php')) ?: [];
    }

    /** A coluna e' declarada ->nullable() em alguma migration? */
    private function colunaEhNullable(string $col): bool
    {
        $files = array_merge($this->migrationFiles(),
            glob(database_path('migrate-later/*.php')) ?: []);
        foreach ($files as $file) {
            $src = file_get_contents($file);
            if (preg_match('/[\'"]' . preg_quote($col, '/') . '[\'"]\s*\)[^;]*->nullable\s*\(/', $src)) {
                return true;
            }
        }
        return false;
    }

    /**
     * $table->float() vira double(8,2) no MySQL: teto de 999.999,99 e 2 casas.
     * invoices.amount ja' chega a 221.137.500,00 em producao.
     */
    public function test_nenhuma_migration_usa_float_para_dinheiro(): void
    {
        $ofensores = [];
        foreach ($this->migrationFiles() as $file) {
            foreach (file($file) as $n => $line) {
                if (preg_match('/->float\s*\(/', $line)) {
                    $ofensores[] = basename($file) . ':' . ($n + 1) . '  ' . trim($line);
                }
            }
        }

        $this->assertSame([], $ofensores,
            "\$table->float() gera double(8,2) no MySQL (max 999999.99, 2 casas decimais).\n"
            . "Use ->double() para preservar o REAL do SQLite bit a bit, ou ->decimal(18,4)\n"
            . "se aceitar mudanca no resultado dos SUM(). Ocorrencias:\n  "
            . implode("\n  ", $ofensores));
    }

    /**
     * MySQL nao aceita DEFAULT em BLOB/TEXT/JSON -> ERROR 1101 no CREATE TABLE.
     */
    public function test_nenhuma_coluna_json_ou_text_declara_default(): void
    {
        $ofensores = [];
        foreach ($this->migrationFiles() as $file) {
            foreach (file($file) as $n => $line) {
                if (preg_match('/->(json|jsonb|text|longText|mediumText)\s*\([^)]*\)[^;]*->default\s*\(/', $line)) {
                    $ofensores[] = basename($file) . ':' . ($n + 1) . '  ' . trim($line);
                }
            }
        }

        $this->assertSame([], $ofensores,
            "MySQL nao aceita DEFAULT em coluna BLOB/TEXT/JSON (ERROR 1101).\n"
            . "Mova o default para \$attributes no model. Ocorrencias:\n  "
            . implode("\n  ", $ofensores));
    }

    /**
     * Contraparte da regra acima: toda coluna com cast 'array' precisa de um
     * default no model, senao Model::create() sem o campo da' ERROR 1364
     * (Field doesn't have a default value) em strict mode.
     */
    public function test_models_com_cast_array_declaram_default_em_attributes(): void
    {
        $faltando = [];
        foreach ($this->modelFiles() as $file) {
            $src = file_get_contents($file);
            $model = basename($file, '.php');

            if (!preg_match('/protected \$casts\s*=\s*\[(.*?)\];/s', $src, $m)) {
                continue;
            }
            preg_match_all("/'([a-z_]+)'\s*=>\s*'(array|json|object|collection)'/", $m[1], $casts);
            if (!$casts[1]) {
                continue;
            }

            $attrs = [];
            if (preg_match('/protected \$attributes\s*=\s*\[(.*?)\];/s', $src, $a)) {
                preg_match_all("/'([a-z_]+)'\s*=>/", $a[1], $found);
                $attrs = $found[1];
            }

            foreach ($casts[1] as $col) {
                // Coluna nullable nao precisa de default: o proprio NULL serve.
                if (!in_array($col, $attrs, true) && !$this->colunaEhNullable($col)) {
                    $faltando[] = "{$model}::\${$col}";
                }
            }
        }

        $this->assertSame([], $faltando,
            "Colunas com cast 'array' sem default em \$attributes.\n"
            . "No SQLite o default vem do schema; no MySQL colunas TEXT nao podem ter DEFAULT,\n"
            . "entao um insert sem o campo falha com ERROR 1364. Faltando:\n  "
            . implode("\n  ", $faltando));
    }

    /**
     * O limite de identificador do MySQL e' 64 caracteres. O Laravel gera nomes
     * de indice a partir de tabela + colunas, e a tabela
     * inventory_warehouse_product_item_loans (38 chars) estoura facilmente.
     */
    public function test_nenhum_nome_de_indice_passa_de_64_caracteres(): void
    {
        $longos = [];
        foreach ($this->migrationFiles() as $file) {
            $src = file_get_contents($file);

            // Um arquivo pode ter varios Schema::table(); cada indice pertence ao
            // bloco em que aparece, entao fatiamos o codigo por bloco.
            $blocos = preg_split('/(Schema::(?:table|create)\s*\(\s*[\'"][a-z_]+[\'"])/',
                $src, -1, PREG_SPLIT_DELIM_CAPTURE);

            $tabela = null;
            foreach ($blocos as $pedaco) {
                if (preg_match('/Schema::(?:table|create)\s*\(\s*[\'"]([a-z_]+)[\'"]/', $pedaco, $tm)) {
                    $tabela = $tm[1];
                    continue;
                }
                if ($tabela === null) {
                    continue;
                }

                // $table->index('a')  e  $table->index(['a', 'b'])  — sem nome explicito
                preg_match_all('/->(index|unique)\s*\(\s*(\[[^\]]*\]|[\'"][a-z_]+[\'"])\s*\)/',
                    $pedaco, $im, PREG_SET_ORDER);
                foreach ($im as $match) {
                    preg_match_all("/[\'\"]([a-z_]+)[\'\"]/", $match[2], $cm);
                    $sufixo = $match[1] === 'unique' ? 'unique' : 'index';
                    $nome = $tabela . '_' . implode('_', $cm[1]) . '_' . $sufixo;
                    if (strlen($nome) > 64) {
                        $longos[] = $nome . ' (' . strlen($nome) . ' chars, ' . basename($file) . ')';
                    }
                }
            }
        }

        $this->assertSame([], $longos,
            "Nome de indice acima do limite de 64 caracteres do MySQL.\n"
            . "Passe um nome curto explicito: \$table->index([...], 'nome_curto').\n"
            . "Ocorrencias:\n  " . implode("\n  ", $longos));
    }

    /**
     * $table->timestamp() em coluna que guarda ID inteiro vira TIMESTAMP no MySQL
     * e a insercao do inteiro falha. Foi o caso de attendance_id, loaned_to_user_id
     * e loaned_by_user_id.
     */
    public function test_nenhuma_coluna_id_e_declarada_como_timestamp(): void
    {
        $ofensores = [];
        foreach ($this->migrationFiles() as $file) {
            foreach (file($file) as $n => $line) {
                if (preg_match('/->(timestamp|dateTime|date)\s*\(\s*[\'"]([a-z_]*_id)[\'"]/', $line, $m)) {
                    $ofensores[] = basename($file) . ':' . ($n + 1) . '  coluna "' . $m[2]
                        . '" declarada como ' . $m[1] . '()';
                }
            }
        }

        $this->assertSame([], $ofensores,
            "Coluna terminada em _id declarada como data. No MySQL isso vira TIMESTAMP\n"
            . "e a insercao do ID inteiro falha (Incorrect datetime value). Ocorrencias:\n  "
            . implode("\n  ", $ofensores));
    }

    /**
     * As colunas de texto usam utf8mb4_bin para reproduzir o `=` BINARY do SQLite.
     * Efeito colateral: LIKE passa a diferenciar maiusculas, enquanto no SQLite ele
     * as ignora (em ASCII). Hoje o projeto nao tem nenhuma query LIKE ativa; se
     * alguem introduzir uma, precisa declarar a colacao explicitamente:
     *
     *   ->whereRaw('name LIKE ? COLLATE utf8mb4_0900_as_ci', ["%{$termo}%"])
     */
    public function test_nenhuma_query_like_sem_colacao_explicita(): void
    {
        $ofensores = [];
        $arquivos = array_merge(
            glob(app_path('*.php')) ?: [],
            glob(app_path('*/*.php')) ?: [],
            glob(app_path('*/*/*.php')) ?: [],
            glob(app_path('*/*/*/*.php')) ?: [],
            glob(app_path('*/*/*/*/*.php')) ?: [],
        );

        foreach ($arquivos as $file) {
            if (str_contains($file, 'MysqlParityCheck.php')) {
                continue; // a propria suite testa a diferenca de proposito
            }
            $src = file_get_contents($file);
            // Remove comentarios /* */ e // para nao acusar codigo morto.
            $src = preg_replace('#/\*.*?\*/#s', '', $src);
            $src = preg_replace('#//[^\n]*#', '', $src);

            foreach (explode("\n", $src) as $n => $line) {
                if (!preg_match('/[\'"]like[\'"]/i', $line)) {
                    continue;
                }
                if (stripos($line, 'COLLATE') !== false) {
                    continue;
                }
                $ofensores[] = basename($file) . ':' . ($n + 1) . '  ' . trim($line);
            }
        }

        $this->assertSame([], $ofensores,
            "Query LIKE sem colacao explicita.\n"
            . "No SQLite LIKE ignora maiusculas (ASCII); com utf8mb4_bin o MySQL as diferencia,\n"
            . "entao a busca retornaria menos resultados. Use:\n"
            . "  ->whereRaw('col LIKE ? COLLATE utf8mb4_0900_as_ci', [\"%\$termo%\"])\n"
            . "Ocorrencias:\n  " . implode("\n  ", $ofensores));
    }

    /**
     * A conexao de referencia usada por `php artisan mysql:parity` precisa existir.
     */
    public function test_conexao_sqlite_ref_esta_configurada(): void
    {
        $this->assertIsArray(config('database.connections.sqlite_ref'),
            "A conexao 'sqlite_ref' e' obrigatoria para `php artisan mysql:parity`.");
        $this->assertSame('sqlite', config('database.connections.sqlite_ref.driver'));
    }

    /**
     * O MySQL precisa rodar em strict mode: sem isso, um valor fora de faixa e'
     * truncado em silencio em vez de gerar erro — o pior cenario para contabilidade.
     */
    public function test_conexao_mysql_esta_em_strict_mode_e_utf8mb4(): void
    {
        $this->assertTrue(config('database.connections.mysql.strict'),
            'strict=false permitiria truncamento silencioso de valores monetarios.');
        $this->assertSame('utf8mb4', config('database.connections.mysql.charset'));
        $this->assertSame('utf8mb4_unicode_ci', config('database.connections.mysql.collation'));
    }
}
