<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

/**
 * Compara EXAUSTIVAMENTE o banco SQLite de referencia com o MySQL 8.0 importado.
 *
 * O objetivo nao e' "parecer igual": e' provar que toda leitura que a aplicacao faz
 * devolve o MESMO valor E o MESMO TIPO PHP nas duas conexoes. Diferenca de tipo
 * importa tanto quanto diferenca de valor, porque o frontend chama
 * `invoice.amount.toFixed(2)` — se o driver devolver "123.45" (string) em vez de
 * 123.45 (float), o app quebra em runtime sem nenhum erro no backend.
 *
 *   php artisan mysql:parity
 *   php artisan mysql:parity --level=rows
 *   php artisan mysql:parity --level=aggregates,queries
 *   php artisan mysql:parity --json=storage/parity.json
 */
class MysqlParityCheck extends Command
{
    protected $signature = 'mysql:parity
        {--a=sqlite_ref : conexao de referencia (SQLite de producao)}
        {--b=mysql : conexao alvo (MySQL 8.0 importado)}
        {--level=all : structure,rows,aggregates,queries,logic — separados por virgula}
        {--table= : limita as checagens de linha/agregado a uma tabela}
        {--json= : grava o relatorio completo em JSON no caminho informado}';

    protected $description = 'Prova que SQLite e MySQL devolvem resultados identicos';

    private array $results = [];
    private int $failures = 0;
    private int $checks = 0;

    /** Tabelas efemeras/telemetria que a migracao importa vazias de proposito. */
    private const IGNORED_TABLES = [
        'cache', 'cache_locks', 'tasks', 'failed_tasks', 'password_reset_tokens',
        'pulse_values', 'pulse_entries', 'pulse_aggregates', 'sqlite_sequence',
    ];

    /**
     * Colunas que sao listas JSON e que a importacao NORMALIZA de objeto para array.
     *
     * Parte das linhas guarda {"0":48,"1":49,"4":359} em vez de [48,49,359], porque
     * array_diff() do PHP preserva as chaves e json_encode() emite objeto quando ha'
     * buracos. O `MEMBER OF` do MySQL so' percorre arrays, entao sem normalizar os
     * itens sumiriam da relacao hasManyJson.
     *
     * Aqui a comparacao e' SEMANTICA (lista de valores), nao byte a byte: e' assim
     * que provamos que a normalizacao nao perdeu nem reordenou nenhum valor.
     * Espelha JSON_LIST_COLUMNS em database/mysql-migration/mapping.py.
     */
    private const JSON_LIST_COLUMNS = [
        'inventory_product_item_uncountables.inventory_warehouse_outcome_ids',
        'inventory_products_packs.products',
        'inventory_products.inventory_warehouses_ids',
        'inventory_warehouses.owners',
        'users.roles',
        'users.permissions',
        'workers.history',
        'worker_payments.divisions',
        'expenses.uses',
        'inventory_warehouse_outcome_requests.requested_products',
        'inventory_warehouse_outcome_requests.received_products',
        'inventory_warehouse_outcome_requests.messages',
        'inventory_warehouse_product_item_loans.movements',
        'inventory_warehouse_product_item_loans.intercurrences',
        'personal_access_tokens.abilities',
    ];

    public function handle(): int
    {
        $a = $this->option('a');
        $b = $this->option('b');
        $levels = $this->option('level') === 'all'
            ? ['structure', 'rows', 'aggregates', 'queries', 'logic']
            : array_map('trim', explode(',', $this->option('level')));

        $this->line('');
        $this->info("Paridade  A={$a}  vs  B={$b}");
        $this->line(str_repeat('=', 78));

        foreach ([$a, $b] as $conn) {
            try {
                DB::connection($conn)->getPdo();
            } catch (\Throwable $e) {
                $this->error("Conexao '{$conn}' indisponivel: " . $e->getMessage());
                return 1;
            }
        }

        if (in_array('structure', $levels, true)) $this->checkStructure($a, $b);
        if (in_array('rows', $levels, true))      $this->checkRows($a, $b);
        if (in_array('aggregates', $levels, true)) $this->checkAggregates($a, $b);
        if (in_array('queries', $levels, true))   $this->checkQueries($a, $b);
        if (in_array('logic', $levels, true))     $this->checkBusinessLogic($a, $b);

        $this->line('');
        $this->line(str_repeat('=', 78));
        if ($this->failures === 0) {
            $this->info("OK — {$this->checks} checagens, 0 divergencias.");
        } else {
            $this->error("FALHA — {$this->checks} checagens, {$this->failures} divergencias.");
        }

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode([
                'checks' => $this->checks,
                'failures' => $this->failures,
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("Relatorio JSON: {$path}");
        }

        return $this->failures === 0 ? 0 : 1;
    }

    // ------------------------------------------------------------------ helpers
    private function pass(string $group, string $name): void
    {
        $this->checks++;
        $this->results[] = ['group' => $group, 'check' => $name, 'status' => 'ok'];
    }

    private function fail(string $group, string $name, $expected, $actual, string $note = ''): void
    {
        $this->checks++;
        $this->failures++;
        $this->results[] = [
            'group' => $group, 'check' => $name, 'status' => 'FAIL',
            'sqlite' => $this->shorten($expected),
            'mysql' => $this->shorten($actual),
            'note' => $note,
        ];
        $this->line('');
        $this->error("  FAIL  [{$group}] {$name}");
        $this->line('        SQLite: ' . $this->shorten($expected));
        $this->line('        MySQL : ' . $this->shorten($actual));
        if ($note !== '') $this->line('        ' . $note);
    }

    private function shorten($v, int $max = 300): string
    {
        $s = is_scalar($v) || $v === null
            ? var_export($v, true)
            : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $s = (string) $s;
        return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s;
    }

    private function tables(string $conn): array
    {
        $driver = DB::connection($conn)->getDriverName();
        if ($driver === 'sqlite') {
            $rows = DB::connection($conn)->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $names = array_map(fn($r) => $r->name, $rows);
        } else {
            $db = DB::connection($conn)->getDatabaseName();
            $rows = DB::connection($conn)->select(
                "SELECT table_name AS name FROM information_schema.tables
                 WHERE table_schema = ? AND table_type='BASE TABLE' ORDER BY table_name", [$db]);
            $names = array_map(fn($r) => $r->name, $rows);
        }
        return array_values(array_diff($names, self::IGNORED_TABLES));
    }

    private function columns(string $conn, string $table): array
    {
        $driver = DB::connection($conn)->getDriverName();
        if ($driver === 'sqlite') {
            return array_map(fn($r) => $r->name,
                DB::connection($conn)->select("PRAGMA table_info(\"{$table}\")"));
        }
        $db = DB::connection($conn)->getDatabaseName();
        return array_map(fn($r) => $r->name, DB::connection($conn)->select(
            "SELECT column_name AS name FROM information_schema.columns
             WHERE table_schema=? AND table_name=? ORDER BY ordinal_position", [$db, $table]));
    }

    // ------------------------------------------------------------- 1. estrutura
    private function checkStructure(string $a, string $b): void
    {
        $this->line('');
        $this->comment('[1] Estrutura');

        $ta = $this->tables($a);
        $tb = $this->tables($b);
        sort($ta); sort($tb);

        $missing = array_diff($ta, $tb);
        $extra = array_diff($tb, $ta);
        if ($missing || $extra) {
            $this->fail('structure', 'conjunto de tabelas', $ta, $tb,
                'faltando no MySQL: ' . implode(',', $missing) .
                ' | sobrando: ' . implode(',', $extra));
        } else {
            $this->pass('structure', 'conjunto de tabelas (' . count($ta) . ')');
            $this->line('  ok  ' . count($ta) . ' tabelas presentes nos dois lados');
        }

        foreach (array_intersect($ta, $tb) as $t) {
            $ca = $this->columns($a, $t);
            $cb = $this->columns($b, $t);
            sort($ca); sort($cb);
            if ($ca !== $cb) {
                $this->fail('structure', "colunas de {$t}", $ca, $cb);
            } else {
                $this->pass('structure', "colunas de {$t}");
            }
        }

        foreach (array_intersect($ta, $tb) as $t) {
            // A tabela `migrations` legitimamente cresce no MySQL: as migrations de
            // alinhamento (que so' fazem sentido no MySQL) rodam depois da importacao.
            // Exigimos que o MySQL contenha TODAS as do SQLite e que as extras sejam
            // apenas essas — assim uma migration esquecida ainda seria detectada.
            if ($t === 'migrations') {
                $ma = DB::connection($a)->table($t)->pluck('migration')->all();
                $mb = DB::connection($b)->table($t)->pluck('migration')->all();
                $faltando = array_values(array_diff($ma, $mb));
                $extras = array_values(array_diff($mb, $ma));
                $inesperadas = array_values(array_filter($extras,
                    fn($m) => !str_contains($m, 'align_schema_for_mysql')));

                if ($faltando || $inesperadas) {
                    $this->fail('structure', 'conteudo de migrations', $faltando, $inesperadas,
                        'faltando no MySQL: ' . (implode(',', $faltando) ?: 'nenhuma')
                        . ' | extras inesperadas: ' . (implode(',', $inesperadas) ?: 'nenhuma'));
                } else {
                    $this->pass('structure', 'conteudo de migrations (' . count($ma)
                        . ' + ' . count($extras) . ' de alinhamento)');
                }
                continue;
            }

            $na = DB::connection($a)->table($t)->count();
            $nb = DB::connection($b)->table($t)->count();
            if ($na !== $nb) {
                $this->fail('structure', "contagem de {$t}", $na, $nb);
            } else {
                $this->pass('structure', "contagem de {$t} ({$na})");
            }
        }
        $this->line('  ok  contagens de linha conferem');
    }

    // ----------------------------------------------------------- 2. linha a linha
    /**
     * Le TODAS as linhas dos dois lados, ordenadas pela PK, e compara valor E tipo.
     * E' a checagem mais forte da suite: pega coercao de tipo do driver
     * (float virando string), truncamento de VARCHAR, arredondamento de dinheiro,
     * normalizacao de JSON e alteracao de datetime.
     */
    private function checkRows(string $a, string $b): void
    {
        $this->line('');
        $this->comment('[2] Linha a linha (valor + tipo PHP)');

        $tables = $this->option('table')
            ? [$this->option('table')]
            : array_intersect($this->tables($a), $this->tables($b));

        foreach ($tables as $t) {
            // Comparada por conteudo em checkStructure(): o MySQL tem as migrations
            // de alinhamento a mais, entao a comparacao posicional nao se aplica.
            if ($t === 'migrations') {
                continue;
            }
            $cols = $this->columns($a, $t);
            $order = in_array('id', $cols, true) ? 'id' : $cols[0];

            $diffs = 0;
            $typeDiffs = [];
            $chunk = 2000;
            $offset = 0;

            while (true) {
                $ra = DB::connection($a)->table($t)->orderBy($order)
                    ->offset($offset)->limit($chunk)->get()->all();
                if (!$ra) break;
                $rb = DB::connection($b)->table($t)->orderBy($order)
                    ->offset($offset)->limit($chunk)->get()->all();

                foreach ($ra as $i => $rowA) {
                    $rowB = $rb[$i] ?? null;
                    if ($rowB === null) {
                        $diffs++;
                        if ($diffs <= 3) {
                            $this->fail('rows', "{$t}: linha ausente no MySQL",
                                (array) $rowA, null);
                        }
                        continue;
                    }
                    foreach ($cols as $c) {
                        $va = $rowA->{$c} ?? null;
                        $vb = $rowB->{$c} ?? null;

                        // Listas JSON normalizadas na importacao: compara os VALORES,
                        // nao os bytes. Ver JSON_LIST_COLUMNS.
                        if (in_array("{$t}.{$c}", self::JSON_LIST_COLUMNS, true)) {
                            if (!$this->sameJsonList($va, $vb)) {
                                $diffs++;
                                if ($diffs <= 3) {
                                    $this->fail('rows', "{$t}.{$c} (pk={$rowA->{$order}})", $va, $vb,
                                        'Lista JSON com valores diferentes — a normalizacao '
                                        . 'objeto->array deveria preservar valor e ordem.');
                                }
                            }
                            continue;
                        }

                        // valor (comparacao tolerante a tipo)
                        if (!$this->sameValue($va, $vb)) {
                            $diffs++;
                            if ($diffs <= 3) {
                                $this->fail('rows', "{$t}.{$c} (pk={$rowA->{$order}})", $va, $vb);
                            }
                            continue;
                        }
                        // tipo PHP devolvido pelo driver
                        if (gettype($va) !== gettype($vb)) {
                            $k = "{$t}.{$c}";
                            $typeDiffs[$k] = ($typeDiffs[$k] ?? 0) + 1;
                            $typeDiffs[$k . '#sample'] = gettype($va) . ' -> ' . gettype($vb);
                        }
                    }
                }
                $offset += $chunk;
            }

            if ($diffs === 0) {
                $this->pass('rows', "{$t} identica");
            }

            foreach ($typeDiffs as $k => $v) {
                if (str_ends_with($k, '#sample')) continue;
                $this->fail('rows', "TIPO divergente em {$k}",
                    explode(' -> ', $typeDiffs[$k . '#sample'])[0],
                    explode(' -> ', $typeDiffs[$k . '#sample'])[1],
                    "{$v} linhas. O driver devolve tipo PHP diferente — o JSON da API muda de forma "
                    . "e o frontend (ex.: amount.toFixed(2)) quebra. Corrija com \$casts no model.");
            }

            $status = $diffs === 0 && !$typeDiffs ? 'ok  ' : 'FAIL';
            $this->line("  {$status}{$t}");
        }
    }

    /**
     * Criterio para SUM()/AVG() sobre dinheiro.
     *
     * Os valores POR LINHA sao bit-identicos (o nivel `rows` prova isso: 0 divergencias
     * em 114.000 linhas monetarias). O que difere e' a ACUMULACAO: o SQLite 3.44+ usa
     * somatorio compensado de Kahan-Babuska-Neumaier em sum(), enquanto o MySQL acumula
     * ingenuamente em binary64. A ordem das somas muda os ultimos bits.
     *
     * Medido nesta base: diferenca relativa maxima 4,9e-14 e diferenca absoluta maxima
     * 1,7e-05 sobre um total de 3,7 bilhoes — e round(x, 2) NUNCA diverge.
     *
     * Portanto exigimos:
     *   (a) igualdade exata do valor arredondado a 2 casas — que e' o numero que o
     *       negocio ve em PDFs, planilhas e telas; e
     *   (b) diferenca relativa abaixo de 1e-9 — cinco ordens de grandeza acima do ruido
     *       observado, mas apertado o bastante para pegar truncamento real
     *       (double(8,2) produziria diferenca relativa na casa de 1e-3 ou maior).
     */
    private const EPSILON_RELATIVO = 1e-9;

    private function assertMoneyAggregate(string $nome, float $va, float $vb): void
    {
        $rel = $va != 0.0 ? abs($va - $vb) / abs($va) : abs($va - $vb);

        if (round($va, 2) !== round($vb, 2)) {
            $this->fail('aggregates', $nome, $va, $vb,
                'O valor arredondado a 2 casas DIFERE — isto e' . "'" . 'visivel para o negocio. '
                . 'Suspeite de double(8,2) ou de truncamento na importacao.');
            return;
        }
        if ($rel > self::EPSILON_RELATIVO) {
            $this->fail('aggregates', $nome, $va, $vb,
                sprintf('Diferenca relativa %.2e acima do limite %.0e — grande demais para ser '
                    . 'ruido de somatorio.', $rel, self::EPSILON_RELATIVO));
            return;
        }
        $this->pass('aggregates', $nome);
    }

    /**
     * Duas listas JSON sao equivalentes se, achatadas em lista de valores, forem
     * identicas — mesma ordem, mesmo conteudo. Aceita objeto de um lado e array do
     * outro, que e' exatamente o que a normalizacao produz.
     */
    private function sameJsonList($a, $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        $decode = function ($v) {
            $d = json_decode((string) $v, true);
            if ($d === null) {
                return null;                 // JSON invalido: compara como texto
            }
            return is_array($d) ? array_values($d) : $d;
        };
        $da = $decode($a);
        $db = $decode($b);
        if ($da === null || $db === null) {
            return (string) $a === (string) $b;
        }
        return json_encode($da) === json_encode($db);
    }

    /** Compara valores tolerando a representacao (string "1.5" vs float 1.5). */
    private function sameValue($a, $b): bool
    {
        if ($a === null || $b === null) return $a === $b;
        if (is_numeric($a) && is_numeric($b)) {
            // comparacao exata em binary64: pega arredondamento de dinheiro
            return (float) $a === (float) $b;
        }
        return (string) $a === (string) $b;
    }

    // ------------------------------------------------------------- 3. agregados
    /** SUM/AVG/MIN/MAX/COUNT no nivel do banco — pega double(8,2) e coercao. */
    private function checkAggregates(string $a, string $b): void
    {
        $this->line('');
        $this->comment('[3] Agregados no banco (dinheiro)');

        $money = [
            'invoices' => ['amount'],
            'balances' => ['amount'],
            'worker_payments' => ['amount'],
            'inventory_product_items' => ['buy_amount', 'sell_amount'],
            'inventory_product_item_uncountables' =>
                ['buy_amount', 'quantity_inserted', 'quantity_used', 'quantity_remaining'],
        ];

        foreach ($money as $table => $cols) {
            foreach ($cols as $col) {
                // min/max/count devolvem um valor armazenado: exigimos igualdade exata.
                foreach (['min', 'max', 'count'] as $fn) {
                    $va = DB::connection($a)->table($table)->{$fn}($col);
                    $vb = DB::connection($b)->table($table)->{$fn}($col);
                    if (!$this->sameValue($va, $vb)) {
                        $this->fail('aggregates', "{$fn}({$table}.{$col})", $va, $vb,
                            'Valor armazenado divergente — verifique se a coluna virou '
                            . 'double(8,2) em vez de DOUBLE.');
                    } else {
                        $this->pass('aggregates', "{$fn}({$table}.{$col})");
                    }
                }
                // sum/avg acumulam: ver sameMoneyAggregate() para o criterio.
                foreach (['sum', 'avg'] as $fn) {
                    $va = (float) DB::connection($a)->table($table)->{$fn}($col);
                    $vb = (float) DB::connection($b)->table($table)->{$fn}($col);
                    $this->assertMoneyAggregate("{$fn}({$table}.{$col})", $va, $vb);
                }
            }
        }

        // Soma por moeda e por status — reproduz o fechamento contabil.
        foreach ([['invoices', 'type', 'amount'],
                  ['balances', 'type', 'amount'],
                  ['balances', 'model', 'amount'],
                  ['worker_payments', 'currency', 'amount'],
                  ['inventory_product_items', 'buy_currency', 'buy_amount'],
                  ['inventory_product_items', 'sell_currency', 'sell_amount']] as [$t, $grp, $col]) {
            $linhas = fn($c) => DB::connection($c)->table($t)->groupBy($grp)
                ->selectRaw("{$grp} as k, SUM({$col}) as s, COUNT(*) as c")
                ->orderBy('k')->get()
                ->mapWithKeys(fn($r) => [(string) $r->k => [(float) $r->s, (int) $r->c]])->all();
            $qa = $linhas($a);
            $qb = $linhas($b);

            if (array_keys($qa) !== array_keys($qb)) {
                $this->fail('aggregates', "grupos de {$t} por {$grp}",
                    array_keys($qa), array_keys($qb));
                continue;
            }
            foreach ($qa as $k => [$soma, $qtd]) {
                [$somaB, $qtdB] = $qb[$k];
                if ($qtd !== $qtdB) {
                    $this->fail('aggregates', "COUNT({$t}) [{$grp}={$k}]", $qtd, $qtdB);
                    continue;
                }
                $this->assertMoneyAggregate("SUM({$t}.{$col}) [{$grp}={$k}]", $soma, $somaB);
            }
        }
        $this->line('  ok  agregados de dinheiro conferem');
    }

    // --------------------------------------------------------------- 4. consultas
    /** Consultas extraidas dos padroes reais da aplicacao. */
    private function checkQueries(string $a, string $b): void
    {
        $this->line('');
        $this->comment('[4] Consultas reais da aplicacao');

        $queries = [
            // filtro por intervalo de datas ISO-8601 em VARCHAR (BalanceAssistant, kardex)
            'balances por intervalo de data' => fn($c) => DB::connection($c)->table('balances')
                ->where('date', '>=', '2024-01-01T00:00:00.000-05:00')
                ->where('date', '<=', '2024-12-31T23:59:59.000-05:00')
                ->orderBy('id')->pluck('id')->all(),

            'invoices por intervalo de data' => fn($c) => DB::connection($c)->table('invoices')
                ->where('date', '>=', '2024-01-01T00:00:00.000-05:00')
                ->where('date', '<=', '2024-06-30T23:59:59.000-05:00')
                ->orderBy('id')->pluck('id')->all(),

            'reports from_date/to_date' => fn($c) => DB::connection($c)->table('reports')
                ->where('from_date', '>=', '2024-01-01T00:00:00.000-05:00')
                ->where('to_date', '<=', '2024-12-31T00:00:00.000-05:00')
                ->orderBy('id')->pluck('id')->all(),

            // ordenacao por coluna de data em VARCHAR (ReportAssistant, ReportPDFCreator)
            'invoices orderBy(date) — 200 primeiros' => fn($c) => DB::connection($c)->table('invoices')
                ->orderBy('date')->orderBy('id')->limit(200)->pluck('id')->all(),

            // GROUP BY com ONLY_FULL_GROUP_BY (InventoryWarehouseOutcomeController:58).
            // total_buy_amount e' um SUM() acumulado: arredondamos a 2 casas, o mesmo
            // que a aplicacao exibe. Ver assertMoneyAggregate() para o criterio.
            'agregacao items por buy_currency+buy_amount' => fn($c) => DB::connection($c)
                ->table('inventory_product_items')
                ->whereNotNull('inventory_warehouse_outcome_id')
                ->groupBy(['buy_currency', 'buy_amount'])
                ->selectRaw('buy_currency, buy_amount, COUNT(*) as count, SUM(buy_amount) as total_buy_amount')
                ->orderBy('buy_currency')->orderBy('buy_amount')
                ->get()->map(fn($r) => [(string) $r->buy_currency, (float) $r->buy_amount,
                    (int) $r->count, round((float) $r->total_buy_amount, 2)])->all(),

            // comparacao varchar x inteiro (balances.user_id e' VARCHAR)
            'balances where user_id = 5 (int)' => fn($c) => DB::connection($c)->table('balances')
                ->where('user_id', 5)->orderBy('id')->pluck('id')->all(),
            'balances where user_id = "5" (string)' => fn($c) => DB::connection($c)->table('balances')
                ->where('user_id', '5')->orderBy('id')->pluck('id')->all(),

            // Colacao. O SQLite compara TEXT como BINARY; utf8mb4_unicode_ci equipara
            // caixa E acento. Estes casos usam valores REAIS da base com a caixa/acento
            // trocados — sob _ci o MySQL devolveria linhas que o SQLite nao devolve.
            // Provam que utf8mb4_bin reproduz o comportamento atual.
            'job_code com caixa trocada' => fn($c) => DB::connection($c)->table('invoices')
                ->where('job_code', '2000.01-pe[ups]')->count(),
            'jobs.code com caixa trocada' => fn($c) => DB::connection($c)->table('jobs')
                ->where('code', '0000-pe[ups]')->count(),
            'workers.dni com caixa trocada' => fn($c) => DB::connection($c)->table('workers')
                ->where('dni', 'n10989566')->count(),
            'produto com acento removido' => fn($c) => DB::connection($c)->table('inventory_products')
                ->where('name', 'Rodillera Rigida')->count(),
            // Ver $conhecidas abaixo: divergencia esperada e sem impacto hoje.
            'LIKE sensivel a caixa' => fn($c) => DB::connection($c)->table('inventory_products')
                ->where('name', 'like', '%alambre%')->orderBy('id')->pluck('id')->all(),
            'ORDER BY texto (maiuscula antes de minuscula)' => fn($c) => DB::connection($c)
                ->table('inventory_products')->orderBy('name')->orderBy('id')
                ->limit(100)->pluck('id')->all(),
            'DISTINCT sobre nomes que colidem sob _ci' => fn($c) => DB::connection($c)
                ->table('inventory_products')->distinct()->orderBy('name')
                ->pluck('name')->count(),
            'GROUP BY ticket_number (colide sob _ci)' => fn($c) => DB::connection($c)
                ->table('balances')->groupBy('ticket_number')->selectRaw('COUNT(*) c')
                ->get()->count(),

            // whereIn grande (batchQuery em InventoryWarehouseController)
            'whereIn com 5000 ids' => function ($c) {
                $ids = range(71237, 76236);
                return round((float) DB::connection($c)->table('inventory_product_items')
                    ->whereIn('id', $ids)->sum('buy_amount'), 2);
            },

            // JSON em LONGTEXT: relacao hasManyJson de InventoryWarehouseOutcome
            'JSON_CONTAINS em outcome_ids' => function ($c) {
                $driver = DB::connection($c)->getDriverName();
                $sql = $driver === 'sqlite'
                    ? "SELECT id FROM inventory_product_item_uncountables
                       WHERE EXISTS (SELECT 1 FROM json_each(inventory_warehouse_outcome_ids)
                                     WHERE json_each.value = 232) ORDER BY id"
                    : "SELECT id FROM inventory_product_item_uncountables
                       WHERE JSON_CONTAINS(inventory_warehouse_outcome_ids, '232') ORDER BY id";
                return array_map(fn($r) => (int) $r->id, DB::connection($c)->select($sql));
            },

            // paginacao sem ordenacao estavel (risco de ordem diferente no InnoDB)
            'items limit/offset com order by id' => fn($c) => DB::connection($c)
                ->table('inventory_product_items')->orderBy('id')
                ->offset(50000)->limit(50)->pluck('id')->all(),

            // status/enum
            'reports por status' => fn($c) => DB::connection($c)->table('reports')
                ->groupBy('status')->selectRaw('status, COUNT(*) as c')
                ->orderBy('status')->get()->map(fn($r) => [(string) $r->status, (int) $r->c])->all(),

            // NULL ordering
            'items com outcome nulo primeiro' => fn($c) => DB::connection($c)
                ->table('inventory_product_items')->orderBy('inventory_warehouse_outcome_id')
                ->orderBy('id')->limit(50)->pluck('id')->all(),
        ];

        /*
         * Divergencias ESPERADAS, analisadas e aceitas. Nao ha' colacao do MySQL que
         * reproduza as duas regras do SQLite ao mesmo tempo:
         *   - `=`    no SQLite e' BINARY (sensivel a caixa)      -> utf8mb4_bin reproduz
         *   - `LIKE` no SQLite e' insensivel a caixa em ASCII    -> utf8mb4_bin nao reproduz
         * Escolhemos utf8mb4_bin porque `=`, ORDER BY, DISTINCT, GROUP BY e os indices
         * UNIQUE dependem dele, enquanto LIKE nao e' usado: o unico `where(...,'LIKE',...)`
         * do projeto esta dentro de um bloco comentado em
         * app/Models/InventoryWarehouseOutcome.php:51. O guard test
         * `test_nenhuma_query_like_sem_colacao_explicita` falha se alguem introduzir um.
         * Quando precisar de LIKE insensivel a caixa, use:
         *     ->whereRaw('name LIKE ? COLLATE utf8mb4_0900_as_ci', ["%{$termo}%"])
         * (utf8mb4_0900_as_ci ignora caixa mas respeita acento, como o SQLite.)
         */
        $conhecidas = [
            'LIKE sensivel a caixa' =>
                'SQLite aplica LIKE sem diferenciar maiusculas (apenas ASCII); utf8mb4_bin '
                . 'diferencia. Sem impacto: o projeto nao possui nenhuma query LIKE ativa.',
        ];

        foreach ($queries as $name => $fn) {
            try {
                $va = $fn($a);
            } catch (\Throwable $e) {
                $va = 'ERRO: ' . $e->getMessage();
            }
            try {
                $vb = $fn($b);
            } catch (\Throwable $e) {
                $vb = 'ERRO: ' . $e->getMessage();
            }

            $na = is_array($va) ? json_encode($va) : (string) $va;
            $nb = is_array($vb) ? json_encode($vb) : (string) $vb;

            if ($na === $nb) {
                $this->pass('queries', $name);
                $this->line("  ok  {$name}");
                continue;
            }

            if (isset($conhecidas[$name])) {
                $this->checks++;
                $this->results[] = [
                    'group' => 'queries', 'check' => $name, 'status' => 'conhecida',
                    'sqlite' => $this->shorten($va), 'mysql' => $this->shorten($vb),
                    'note' => $conhecidas[$name],
                ];
                $this->line("  ~   {$name}  (divergencia conhecida e aceita)");
                $this->line('      ' . $conhecidas[$name]);
                continue;
            }

            $this->fail('queries', $name, $va, $vb);
        }
    }

    // ------------------------------------------------------- 5. logica de negocio
    /**
     * Roda os geradores de relatorio REAIS nas duas conexoes e compara o JSON.
     * E' o teste que importa para a contabilidade: kardex, balances, stock e custos.
     */
    private function checkBusinessLogic(string $a, string $b): void
    {
        $this->line('');
        $this->comment('[5] Geradores de relatorio (contabilidade)');

        $start = new \DateTime('2024-01-01 00:00:00');
        $end = new \DateTime('2024-12-31 23:59:59');

        $cases = [
            'RecordInventoryProductsStock' => [
                \App\Support\Generators\Records\Inventory\RecordInventoryProductsStock::class,
                ['warehouseIds' => [1, 2, 3, 4], 'moneyType' => 'PEN',
                 'startDate' => $start, 'endDate' => $end],
            ],
            'RecordInventoryProductsBalance' => [
                \App\Support\Generators\Records\Inventory\RecordInventoryProductsBalance::class,
                ['warehouseIds' => [1, 2, 3, 4], 'moneyType' => 'PEN',
                 'startDate' => $start, 'endDate' => $end],
            ],
            'RecordInventoryProductsKardex' => [
                \App\Support\Generators\Records\Inventory\RecordInventoryProductsKardex::class,
                ['warehouseIds' => [1], 'moneyType' => 'PEN',
                 'startDate' => $start, 'endDate' => $end],
            ],
            'RecordJobsByCosts' => [
                \App\Support\Generators\Records\Jobs\RecordJobsByCosts::class,
                ['startDate' => $start, 'endDate' => $end],
            ],
            'RecordUsersByCosts' => [
                \App\Support\Generators\Records\Users\RecordUsersByCosts::class,
                ['startDate' => $start, 'endDate' => $end],
            ],
            'RecordReportsByTime' => [
                \App\Support\Generators\Records\Reports\RecordReportsByTime::class,
                ['startDate' => $start, 'endDate' => $end],
            ],
            'RecordInvoicesByItems' => [
                \App\Support\Generators\Records\Invoices\RecordInvoicesByItems::class,
                ['startDate' => $start, 'endDate' => $end],
            ],
            'RecordAttendancesByJobs' => [
                \App\Support\Generators\Records\Attendances\RecordAttendancesByJobs::class,
                ['startDate' => $start, 'endDate' => $end],
            ],
        ];

        foreach ($cases as $name => [$class, $options]) {
            $va = $this->runOn($a, fn() => (new $class($options))->generate());
            $vb = $this->runOn($b, fn() => (new $class($options))->generate());
            $this->compareDeep('logic', $name, $va, $vb);
        }

        // Metodos de model que fazem contas de dinheiro.
        $modelCases = [
            'Report::amount() dos 30 primeiros' => function () {
                return \App\Models\Report::orderBy('id')->limit(30)->get()
                    ->mapWithKeys(fn($r) => [$r->id => $r->amount()])->all();
            },
            'Invoice::amount tipo+valor (50)' => function () {
                return \App\Models\Invoice::orderBy('id')->limit(50)->get()
                    ->mapWithKeys(fn($i) => [$i->id => [gettype($i->amount), (float) $i->amount]])->all();
            },
            'InventoryProductItem buy/sell (50)' => function () {
                return \App\Models\InventoryProductItem::orderBy('id')->limit(50)->get()
                    ->mapWithKeys(fn($i) => [$i->id => [
                        gettype($i->buy_amount), (float) $i->buy_amount,
                        gettype($i->sell_amount), (float) $i->sell_amount]])->all();
            },
            'Worker::history cast array (20)' => function () {
                return \App\Models\Worker::orderBy('id')->limit(20)->get()
                    ->mapWithKeys(fn($w) => [$w->id => $w->history])->all();
            },
            'User::roles/permissions cast array' => function () {
                return \App\Models\User::orderBy('id')->get()
                    ->mapWithKeys(fn($u) => [$u->id => [$u->roles, $u->permissions, $u->metadata]])->all();
            },
            'OutcomeRequest requested_products (todos)' => function () {
                return \App\Models\InventoryWarehouseOutcomeRequest::orderBy('id')->get()
                    ->mapWithKeys(fn($r) => [$r->id => $r->requested_products])->all();
            },
            'Loan::movements cast array (50)' => function () {
                return \App\Models\InventoryWarehouseProductItemLoan::orderBy('id')->limit(50)->get()
                    ->mapWithKeys(fn($l) => [$l->id => $l->movements])->all();
            },
            'Outcome::uncountableItems (hasManyJson)' => function () {
                return \App\Models\InventoryWarehouseOutcome::orderBy('id')->limit(40)->get()
                    ->mapWithKeys(fn($o) => [$o->id => $o->uncountableItems->pluck('id')->sort()->values()->all()])->all();
            },
            'Uncountable::outcomes (belongsToJson)' => function () {
                return \App\Models\InventoryProductItemUncountable::orderBy('id')->get()
                    ->mapWithKeys(fn($u) => [$u->id => $u->outcomes->pluck('id')->sort()->values()->all()])->all();
            },
            'Attendance::dayWorkers (FK que era timestamp)' => function () {
                return \App\Models\Attendance::orderBy('id')->limit(40)->get()
                    ->mapWithKeys(fn($t) => [$t->id => $t->dayWorkers()->pluck('id')->sort()->values()->all()])->all();
            },
            'Loan::loanedTo/loanedBy (FK que era timestamp)' => function () {
                return \App\Models\InventoryWarehouseProductItemLoan::orderBy('id')->limit(40)->get()
                    ->mapWithKeys(fn($l) => [$l->id => [
                        optional($l->loanedTo)->username, optional($l->loanedBy)->username]])->all();
            },
        ];

        foreach ($modelCases as $name => $fn) {
            $va = $this->runOn($a, $fn);
            $vb = $this->runOn($b, $fn);
            $this->compareDeep('logic', $name, $va, $vb);
        }
    }

    /** Executa a closure com a conexao informada como default, sem cache residual. */
    private function runOn(string $conn, \Closure $fn)
    {
        $previous = config('database.default');
        try {
            config(['database.default' => $conn]);
            DB::setDefaultConnection($conn);
            Model::clearBootedModels();
            $this->flushAppCaches();
            return $fn();
        } catch (\Throwable $e) {
            return 'ERRO: ' . get_class($e) . ': ' . $e->getMessage();
        } finally {
            config(['database.default' => $previous]);
            DB::setDefaultConnection($previous);
            Model::clearBootedModels();
        }
    }

    /**
     * RecordsCache e DataCache usam Cache::store('redis') fixo. Sem limpar,
     * a segunda execucao devolveria o resultado memorizado da primeira e a
     * comparacao passaria por engano.
     */
    private function flushAppCaches(): void
    {
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable $e) {
            // Redis indisponivel: os geradores recalculam de qualquer forma.
        }
        Cache::flush();
    }

    private function compareDeep(string $group, string $name, $va, $vb): void
    {
        $ja = json_encode($this->normalize($va), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jb = json_encode($this->normalize($vb), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($ja === $jb) {
            $this->pass($group, $name);
            $this->line("  ok  {$name}");
            return;
        }

        $note = '';
        if (is_string($va) && str_starts_with($va, 'ERRO:')) $note = 'SQLite lancou excecao.';
        if (is_string($vb) && str_starts_with($vb, 'ERRO:')) $note = 'MySQL lancou excecao.';
        $this->fail($group, $name, $this->firstDiff($ja, $jb, 'A'), $this->firstDiff($jb, $ja, 'B'), $note);
    }

    /** Recorta a vizinhanca do primeiro byte divergente, para o diff caber na tela. */
    private function firstDiff(string $s, string $other, string $side): string
    {
        $len = min(strlen($s), strlen($other));
        $i = 0;
        while ($i < $len && $s[$i] === $other[$i]) $i++;
        $from = max(0, $i - 80);
        return "…" . substr($s, $from, 240) . "…  (divergencia no byte {$i}, lado {$side})";
    }

    /** Normaliza floats para representacao canonica antes de comparar. */
    private function normalize($v)
    {
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.10F', $v), '0'), '.') ?: '0';
        }
        if (is_array($v)) {
            return array_map(fn($x) => $this->normalize($x), $v);
        }
        if ($v instanceof \Illuminate\Support\Collection) {
            return $this->normalize($v->all());
        }
        if (is_object($v)) {
            return $this->normalize(json_decode(json_encode($v), true));
        }
        return $v;
    }
}
