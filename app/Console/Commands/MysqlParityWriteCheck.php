<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Compara o caminho de ESCRITA entre SQLite e MySQL.
 *
 * As suites mysql:parity e mysql:parity-api so' leem. Mas os erros de strict mode do
 * MySQL acontecem no INSERT/UPDATE, nao no SELECT:
 *
 *   1364  Field 'roles' doesn't have a default value   (coluna TEXT nao aceita DEFAULT)
 *   1406  Data too long for column 'description'       (VARCHAR curto demais)
 *   1264  Out of range value for column 'amount'       (double(8,2))
 *   1292  Incorrect datetime value                     (ISO-8601 com offset)
 *
 * Cada cenario abaixo grava nos DOIS bancos e compara a linha COMO ELA FICOU GRAVADA
 * (releitura do banco), nao o objeto em memoria — e' assim que truncamento e
 * arredondamento silencioso aparecem.
 *
 * ATENCAO: este comando ESCREVE nos dois bancos. Aponte-o para copias descartaveis.
 *
 *   DB_REF_DATABASE=/tmp/scratch.sqlite DB_DATABASE=maranatha_write \
 *     php artisan mysql:parity-write --confirmo
 */
class MysqlParityWriteCheck extends Command
{
    protected $signature = 'mysql:parity-write
        {--a=sqlite_ref : conexao SQLite descartavel}
        {--b=mysql : conexao MySQL descartavel}
        {--confirmo : obrigatorio — confirma que os dois alvos sao descartaveis}
        {--json= : grava o relatorio em JSON}';

    protected $description = 'Compara INSERT/UPDATE entre SQLite e MySQL (escreve nos dois)';

    private array $results = [];
    private int $checks = 0;
    private int $failures = 0;

    public function handle(): int
    {
        $a = $this->option('a');
        $b = $this->option('b');

        if (!$this->option('confirmo')) {
            $this->error('Este comando ESCREVE nos dois bancos.');
            $this->line('Alvos atuais:');
            $this->line('  A (sqlite): ' . config("database.connections.{$a}.database"));
            $this->line('  B (mysql) : ' . config("database.connections.{$b}.database"));
            $this->line('Aponte-os para copias descartaveis e repita com --confirmo.');
            return 1;
        }

        $this->line('');
        $this->info("Paridade de ESCRITA   A={$a}  vs  B={$b}");
        $this->line('  A: ' . config("database.connections.{$a}.database"));
        $this->line('  B: ' . config("database.connections.{$b}.database"));
        $this->line(str_repeat('=', 78));

        foreach ($this->cenarios() as $nome => $fn) {
            $ra = $this->executar($a, $fn);
            $rb = $this->executar($b, $fn);
            $this->comparar($nome, $ra, $rb);
        }

        $this->line('');
        $this->line(str_repeat('=', 78));
        if ($this->failures === 0) {
            $this->info("OK — {$this->checks} cenarios de escrita, 0 divergencias.");
        } else {
            $this->error("FALHA — {$this->checks} cenarios, {$this->failures} divergencias.");
        }

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode([
                'checks' => $this->checks, 'failures' => $this->failures,
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("Relatorio JSON: {$path}");
        }

        return $this->failures === 0 ? 0 : 1;
    }

    private function executar(string $conn, \Closure $fn): array
    {
        $anterior = config('database.default');
        try {
            config(['database.default' => $conn]);
            DB::setDefaultConnection($conn);
            Model::clearBootedModels();
            return ['ok' => true, 'valor' => $fn()];
        } catch (\Throwable $e) {
            // A mensagem traz o SQL completo, que difere entre os motores (aspas,
            // nomes). Guardamos so' o codigo SQLSTATE e o texto do erro.
            $msg = $e->getMessage();
            if (preg_match('/SQLSTATE\[(\w+)\]:?\s*([^(]{0,120})/', $msg, $m)) {
                $msg = 'SQLSTATE[' . $m[1] . '] ' . trim($m[2]);
            }
            return ['ok' => false, 'erro' => get_class($e) . ': ' . substr($msg, 0, 200)];
        } finally {
            config(['database.default' => $anterior]);
            DB::setDefaultConnection($anterior);
            Model::clearBootedModels();
        }
    }

    private function comparar(string $nome, array $ra, array $rb): void
    {
        $this->checks++;

        if ($ra['ok'] !== $rb['ok']) {
            $this->failures++;
            $this->registrar($nome, 'FAIL',
                $ra['ok'] ? 'gravou' : $ra['erro'],
                $rb['ok'] ? 'gravou' : $rb['erro'],
                'Um motor aceitou a escrita e o outro rejeitou.');
            $this->line('');
            $this->error("  FAIL  {$nome}");
            $this->line('        SQLite: ' . ($ra['ok'] ? 'gravou' : $ra['erro']));
            $this->line('        MySQL : ' . ($rb['ok'] ? 'gravou' : $rb['erro']));
            return;
        }

        if (!$ra['ok']) {
            // Os dois rejeitaram. Isso e' esperado em cenarios de limite, desde que o
            // motivo seja o mesmo; caso contrario o cenario esta mal montado.
            $this->registrar($nome, 'ok-ambos-rejeitaram', $ra['erro'], $rb['erro'], null);
            $this->line("  ok  {$nome}  (ambos rejeitaram)");
            return;
        }

        $ja = json_encode($this->normalizar($ra['valor']), JSON_UNESCAPED_UNICODE);
        $jb = json_encode($this->normalizar($rb['valor']), JSON_UNESCAPED_UNICODE);

        if ($ja === $jb) {
            $this->registrar($nome, 'ok', null, null, null);
            $this->line("  ok  {$nome}");
            return;
        }

        $this->failures++;
        $this->registrar($nome, 'FAIL', $ja, $jb, 'Linha gravada diferente.');
        $this->line('');
        $this->error("  FAIL  {$nome}");
        $this->line('        SQLite: ' . substr($ja, 0, 240));
        $this->line('        MySQL : ' . substr($jb, 0, 240));
    }

    private function registrar(string $nome, string $status, $a, $b, ?string $nota): void
    {
        $this->results[] = ['check' => $nome, 'status' => $status,
            'sqlite' => $a, 'mysql' => $b, 'note' => $nota];
    }

    private function normalizar($v)
    {
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.8F', $v), '0'), '.') ?: '0';
        }
        if (is_array($v)) {
            return array_map(fn($x) => $this->normalizar($x), $v);
        }
        return $v;
    }

    /**
     * Le a linha DE VOLTA do banco, sem passar pelo cache de atributos do model.
     * E' a unica forma de flagrar truncamento de VARCHAR e arredondamento de DOUBLE.
     */
    private function relerCru(string $tabela, int $id, array $colunas): array
    {
        $row = (array) DB::table($tabela)->where('id', $id)->first();
        return array_intersect_key($row, array_flip($colunas));
    }

    /**
     * Cenarios de escrita. Cada um devolve a linha relida do banco.
     *
     * IMPORTANTE: nenhum cenario depende de AUTO_INCREMENT alinhado entre os dois
     * bancos — sempre reconsultamos pelo id devolvido na propria conexao, e as
     * comparacoes excluem o id.
     */
    private function cenarios(): array
    {
        $sufixo = 'PARITY-' . getmypid();

        // bcrypt usa salt aleatorio: dois Hash::make() do mesmo texto geram hashes
        // diferentes por definicao. Calculamos UM hash aqui e usamos nos dois bancos,
        // para que o estado final continue comparavel por mysql:parity --level=rows.
        // O cast 'hashed' do model reconhece um valor ja' hasheado e nao re-hasheia.
        $senhaHash = \Illuminate\Support\Facades\Hash::make('descartavel-' . $sufixo);

        return [
            // --- Defaults de coluna TEXT (risco de ERROR 1364) ---------------------
            'User sem roles/permissions/metadata' => function () use ($sufixo, $senhaHash) {
                $u = \App\Models\User::create([
                    'name' => 'Teste Paridade', 'email' => "p{$sufixo}@maranatha.org",
                    'username' => "u{$sufixo}", 'password' => $senhaHash,
                ]);
                return $this->relerCru('users', $u->id, ['roles', 'permissions', 'metadata', 'name', 'password']);
            },
            'Worker sem history' => function () use ($sufixo) {
                $w = \App\Models\Worker::create([
                    'dni' => substr("D{$sufixo}", 0, 10), 'name' => 'Trabalhador Paridade',
                    'is_active' => true, 'supervisor' => 'Sup', 'team' => 'Equipe',
                    'country' => 'PE', 'role' => 'Oficial',
                ]);
                return $this->relerCru('workers', $w->id, ['history', 'is_active', 'name']);
            },
            'InventoryWarehouse sem owners' => function () use ($sufixo) {
                $x = \App\Models\InventoryWarehouse::create([
                    'name' => "Almacen {$sufixo}", 'zone' => 'UPS', 'country' => 'PE',
                ]);
                return $this->relerCru('inventory_warehouses', $x->id, ['owners', 'name']);
            },
            'InventoryProductsPack sem products' => function () use ($sufixo) {
                $x = \App\Models\InventoryProductsPack::create(['name' => "Pack {$sufixo}"]);
                return $this->relerCru('inventory_products_packs', $x->id, ['products', 'name']);
            },
            'WorkerPayment sem divisions' => function () use ($sufixo) {
                $w = \App\Models\Worker::orderBy('id')->first();
                $x = \App\Models\WorkerPayment::create([
                    'worker_id' => $w->id, 'month' => 6, 'year' => 2026,
                    'amount' => 1234.56, 'currency' => 'PEN',
                ]);
                return $this->relerCru('worker_payments', $x->id, ['divisions', 'amount', 'currency']);
            },
            'OutcomeRequest sem os tres campos JSON' => function () use ($sufixo) {
                $u = \App\Models\User::orderBy('id')->first();
                $x = \App\Models\InventoryWarehouseOutcomeRequest::create([
                    'inventory_warehouse_id' => 1, 'user_id' => $u->id,
                    'description' => "Pedido {$sufixo}",
                ]);
                return $this->relerCru('inventory_warehouse_outcome_requests', $x->id,
                    ['requested_products', 'received_products', 'messages', 'status', 'type']);
            },
            'Report sem metadata' => function () use ($sufixo) {
                $u = \App\Models\User::orderBy('id')->first();
                $r = \App\Models\Report::create([
                    'user_id' => $u->id, 'title' => "Relatorio {$sufixo}", 'type' => 'Bill',
                    'money_type' => 'PEN', 'from_date' => '2026-01-01T00:00:00.000-05:00',
                    'to_date' => '2026-01-31T00:00:00.000-05:00', 'country' => 'PE', 'zone' => 'UPS',
                ]);
                return $this->relerCru('reports', $r->id,
                    ['metadata', 'status', 'money_type', 'from_date', 'to_date', 'country', 'zone']);
            },

            // --- Dinheiro: faixa e precisao (risco de ERROR 1264 / arredondamento) --
            'Invoice com valor acima de 1e6' => function () use ($sufixo) {
                $r = \App\Models\Report::orderBy('id')->first();
                $i = \App\Models\Invoice::create([
                    'report_id' => $r->id, 'type' => 'Bill', 'description' => "Grande {$sufixo}",
                    'ticket_number' => 'T1', 'commerce_number' => 'C1',
                    'date' => '2026-01-15T00:00:00.000-05:00', 'job_code' => '0000',
                    'expense_code' => '704', 'amount' => 221137500.0,
                ]);
                return $this->relerCru('invoices', $i->id, ['amount', 'description']);
            },
            'Invoice com 4 casas decimais' => function () use ($sufixo) {
                $r = \App\Models\Report::orderBy('id')->first();
                $i = \App\Models\Invoice::create([
                    'report_id' => $r->id, 'type' => 'Bill', 'description' => "Decimal {$sufixo}",
                    'ticket_number' => 'T2', 'commerce_number' => 'C2',
                    'date' => '2026-01-15T00:00:00.000-05:00', 'job_code' => '0000',
                    'expense_code' => '704', 'amount' => 4881.3351,
                ]);
                return $this->relerCru('invoices', $i->id, ['amount']);
            },
            'Balance com 12 casas decimais' => function () use ($sufixo) {
                $u = \App\Models\User::orderBy('id')->first();
                $b = \App\Models\Balance::create([
                    'user_id' => (string) $u->id, 'description' => "Saldo {$sufixo}",
                    'date' => '2026-01-15T00:00:00.000-05:00', 'type' => 'Credit',
                    'model' => 'Direct', 'amount' => 1234.567890123456,
                ]);
                return $this->relerCru('balances', $b->id, ['amount', 'type', 'model', 'user_id']);
            },
            'ProductItem com buy/sell de 4 casas' => function () {
                $it = \App\Models\InventoryProductItem::create([
                    'order' => 999999, 'batch' => 'PARIDADE',
                    'buy_amount' => 0.3333, 'sell_amount' => 0.3333,
                    'buy_currency' => 'PEN', 'sell_currency' => 'PEN', 'status' => 'InStock',
                    'inventory_product_id' => 3, 'inventory_warehouse_id' => 1,
                    'inventory_warehouse_income_id' => 430,
                ]);
                return $this->relerCru('inventory_product_items', $it->id,
                    ['buy_amount', 'sell_amount', 'status', 'batch']);
            },

            // --- Largura de VARCHAR (risco de ERROR 1406) --------------------------
            'Invoice com description de 158 chars' => function () {
                $r = \App\Models\Report::orderBy('id')->first();
                $texto = str_repeat('a', 158);
                $i = \App\Models\Invoice::create([
                    'report_id' => $r->id, 'type' => 'Bill', 'description' => $texto,
                    'ticket_number' => 'T3', 'commerce_number' => 'C3',
                    'date' => '2026-01-15T00:00:00.000-05:00', 'job_code' => '0000',
                    'expense_code' => '704', 'amount' => 10.0,
                ]);
                $row = $this->relerCru('invoices', $i->id, ['description']);
                return ['tamanho' => strlen($row['description']), 'intacto' => $row['description'] === $texto];
            },
            'Balance com description de 106 chars' => function () {
                $u = \App\Models\User::orderBy('id')->first();
                $texto = str_repeat('b', 106);
                $b = \App\Models\Balance::create([
                    'user_id' => (string) $u->id, 'description' => $texto,
                    'date' => '2026-01-15T00:00:00.000-05:00', 'type' => 'Debit',
                    'model' => 'Direct', 'amount' => 1.0,
                ]);
                $row = $this->relerCru('balances', $b->id, ['description']);
                return ['tamanho' => strlen($row['description']), 'intacto' => $row['description'] === $texto];
            },

            // --- Datas ISO-8601 com offset (risco de ERROR 1292) -------------------
            'Invoice com data ISO offset -03:00' => function () {
                $r = \App\Models\Report::orderBy('id')->first();
                $i = \App\Models\Invoice::create([
                    'report_id' => $r->id, 'type' => 'Facture', 'description' => 'Data PY',
                    'ticket_number' => 'T4', 'commerce_number' => 'C4',
                    'date' => '2026-02-20T14:30:00.000-03:00', 'job_code' => '0000',
                    'expense_code' => '704', 'amount' => 5.0,
                ]);
                return $this->relerCru('invoices', $i->id, ['date']);
            },
            'AttendanceDayWorker (attendance_id era timestamp)' => function () {
                $at = \App\Models\Attendance::orderBy('id')->first();
                $w = \App\Models\Worker::orderBy('id')->first();
                $x = \App\Models\AttendanceDayWorker::create([
                    'worker_dni' => $w->dni, 'attendance_id' => $at->id,
                    'date' => '2026-03-01T00:00:00-05:00', 'status' => 'Present',
                ]);
                return $this->relerCru('attendance_day_workers', $x->id,
                    ['attendance_id', 'date', 'status', 'worker_dni']);
            },
            'Loan (loaned_to/by_user_id eram timestamp)' => function () {
                $u = \App\Models\User::orderBy('id')->first();
                $it = \App\Models\InventoryProductItem::orderBy('id')->first();
                $l = \App\Models\InventoryWarehouseProductItemLoan::create([
                    'loaned_to_user_id' => $u->id, 'loaned_by_user_id' => $u->id,
                    'inventory_product_item_id' => $it->id, 'inventory_warehouse_id' => 1,
                    'status' => 'SendingToLoan',
                ]);
                return $this->relerCru('inventory_warehouse_product_item_loans', $l->id,
                    ['loaned_to_user_id', 'loaned_by_user_id', 'movements', 'intercurrences', 'status']);
            },

            // --- utf8mb4: acentos e emoji ------------------------------------------
            'Texto com acento e emoji (utf8mb4)' => function () {
                $r = \App\Models\Report::orderBy('id')->first();
                $texto = 'Máquina de soldar — çãõ ñ 日本語 🔧⚡';
                $i = \App\Models\Invoice::create([
                    'report_id' => $r->id, 'type' => 'Bill', 'description' => $texto,
                    'ticket_number' => 'T5', 'commerce_number' => 'C5',
                    'date' => '2026-01-15T00:00:00.000-05:00', 'job_code' => '0000',
                    'expense_code' => '704', 'amount' => 1.0,
                ]);
                $row = $this->relerCru('invoices', $i->id, ['description']);
                return ['intacto' => $row['description'] === $texto, 'valor' => $row['description']];
            },

            // --- JSON: escrita, releitura e o caminho do array_diff ----------------
            'Worker::history round-trip com acento' => function () {
                $w = \App\Models\Worker::orderBy('id')->first();
                $hist = [['data' => '2026-01-01', 'nota' => 'Promoção — çã'], ['data' => '2026-02-01', 'nota' => 'ok']];
                $w->history = $hist;
                $w->save();
                $lido = \App\Models\Worker::find($w->id)->history;
                return ['igual' => $lido === $hist, 'lido' => $lido];
            },
            'Uncountable: addOutcome depois removeOutcome' => function () {
                $u = \App\Models\InventoryProductItemUncountable::where('quantity_remaining', '>', 5)
                    ->orderBy('id')->first();
                if (!$u) {
                    return ['pulado' => 'sem item com saldo'];
                }
                $out = \App\Models\InventoryWarehouseOutcome::orderBy('id')->first();
                $antes = $u->inventory_warehouse_outcome_ids;
                $u->addOutcome($out, 1.0);
                $u->refresh();
                $u->removeOutcome($out);
                $u->refresh();
                $row = $this->relerCru('inventory_product_item_uncountables', $u->id,
                    ['inventory_warehouse_outcome_ids', 'outcomes_details',
                     'quantity_used', 'quantity_remaining']);
                // Depois de adicionar e remover, a lista tem de voltar a ser um ARRAY
                // (nao um objeto com buracos) — e' o bug do array_diff sem array_values.
                return [
                    'volta_ao_estado_anterior' => json_decode($row['inventory_warehouse_outcome_ids'], true) === $antes,
                    'e_array' => str_starts_with(trim($row['inventory_warehouse_outcome_ids']), '['),
                    'ids' => $row['inventory_warehouse_outcome_ids'],
                    'quantidades' => [$row['quantity_used'], $row['quantity_remaining']],
                ];
            },
            'kv_storage com JSON grande (>64KB)' => function () use ($sufixo) {
                $grande = json_encode(['itens' => array_fill(0, 8000, ['id' => 1, 'nome' => 'áéíóú'])]);
                DB::table('kv_storage')->insert([
                    'key' => "paridade/{$sufixo}", 'value' => $grande, 'comment' => null,
                ]);
                $lido = DB::table('kv_storage')->where('key', "paridade/{$sufixo}")->value('value');
                return ['bytes' => strlen($lido), 'intacto' => $lido === $grande];
            },

            // --- UPDATE em massa ---------------------------------------------------
            'UPDATE em massa de status' => function () {
                $ids = \App\Models\InventoryProductItem::where('status', 'InStock')
                    ->orderBy('id')->limit(50)->pluck('id')->all();
                $n = \App\Models\InventoryProductItem::whereIn('id', $ids)
                    ->update(['status' => 'Sold']);
                $conf = DB::table('inventory_product_items')->whereIn('id', $ids)
                    ->where('status', 'Sold')->count();
                return ['afetadas' => $n, 'confirmadas' => $conf, 'ids' => count($ids)];
            },
            'DELETE e recontagem' => function () use ($sufixo) {
                $b = \App\Models\Balance::create([
                    'user_id' => '1', 'description' => "Apagavel {$sufixo}",
                    'date' => '2026-01-15T00:00:00.000-05:00', 'type' => 'Credit',
                    'model' => 'Direct', 'amount' => 99.99,
                ]);
                $id = $b->id;
                $b->delete();
                return ['existe_depois' => DB::table('balances')->where('id', $id)->exists()];
            },
        ];
    }
}
