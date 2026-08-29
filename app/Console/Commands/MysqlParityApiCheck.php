<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Compara as RESPOSTAS HTTP REAIS da API entre SQLite e MySQL.
 *
 * O comando mysql:parity valida o banco e os geradores isoladamente. Este aqui
 * exercita o caminho completo — rota, middleware, autenticacao, controller,
 * serializacao JSON — que e' o que o aplicativo realmente consome.
 *
 * ARMADILHA DO CACHE
 * ------------------
 * RecordsCache e DataCache montam a chave como md5(json_encode($params)). A chave
 * NAO inclui a conexao do banco. Rodar o mesmo endpoint no SQLite e depois no MySQL
 * devolveria, na segunda vez, o resultado memorizado da primeira — e a comparacao
 * passaria mesmo com o MySQL completamente quebrado.
 *
 * Tres defesas, todas obrigatorias:
 *   1. Cache limpo (Redis + store padrao) antes de CADA requisicao.
 *   2. Toda resposta que carrega `is_cached` precisa vir com `false`. Se a limpeza
 *      falhar, a segunda passada denuncia com `true`.
 *   3. O teste `--poison` prova que a defesa e' necessaria: sem limpar, o MySQL
 *      devolve o resultado do SQLite. Se esse teste NAO detectar contaminacao,
 *      significa que o cache nao esta ativo e as garantias 1 e 2 seriam vazias.
 *
 *   php artisan mysql:parity-api
 *   php artisan mysql:parity-api --poison
 *   php artisan mysql:parity-api --filter=kardex --json=storage/logs/api.json
 */
class MysqlParityApiCheck extends Command
{
    protected $signature = 'mysql:parity-api
        {--a=sqlite_ref : conexao de referencia (SQLite de producao)}
        {--b=mysql : conexao alvo (MySQL 8.0 importado)}
        {--filter= : roda apenas os casos cujo nome contenha este texto}
        {--poison : prova que sem limpar o cache a comparacao seria enganada}
        {--json= : grava o relatorio completo em JSON}';

    protected $description = 'Compara as respostas HTTP da API entre SQLite e MySQL';

    private array $results = [];
    private int $checks = 0;
    private int $failures = 0;

    public function handle(): int
    {
        $a = $this->option('a');
        $b = $this->option('b');

        $this->line('');
        $this->info("Paridade de API   A={$a}  vs  B={$b}");
        $this->line(str_repeat('=', 78));

        if (!$this->assertRedisAtivo()) {
            return 1;
        }

        $casos = $this->casos();
        if ($f = $this->option('filter')) {
            $casos = array_filter($casos, fn($n) => str_contains($n, $f), ARRAY_FILTER_USE_KEY);
        }

        if ($this->option('poison')) {
            return $this->provaContaminacaoDeCache($a, $b, $casos);
        }

        $this->line('');
        $this->comment('Comparando ' . count($casos) . ' chamadas de API');

        foreach ($casos as $nome => [$uri, $params]) {
            $ra = $this->request($a, $uri, $params);
            $rb = $this->request($b, $uri, $params);
            $this->comparar($nome, $uri, $params, $ra, $rb);
        }

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

    /**
     * Se o Redis estiver fora, Cache::store('redis') falha silenciosamente em alguns
     * drivers e NADA e' memorizado — o que tornaria a checagem de is_cached vazia.
     * Confirmamos que o cache realmente grava e le antes de confiar nele.
     */
    private function assertRedisAtivo(): bool
    {
        try {
            $chave = 'Maranatha/ParityProbe/' . getmypid();
            Cache::store('redis')->put($chave, 'ok', 60);
            $lido = Cache::store('redis')->get($chave);
            Cache::store('redis')->forget($chave);
            if ($lido !== 'ok') {
                $this->error('O cache redis nao devolveu o valor gravado. Sem cache ativo, '
                    . 'a checagem de is_cached seria vazia — nao da' . "'" . ' para confiar no teste.');
                return false;
            }
            $this->line('  ok  cache redis ativo (gravacao e leitura confirmadas)');
            return true;
        } catch (\Throwable $e) {
            $this->error('Cache redis indisponivel: ' . $e->getMessage());
            $this->line('Suba o redis antes de rodar: brew services start redis');
            return false;
        }
    }

    /** Limpa TODAS as camadas que poderiam devolver resultado da passada anterior. */
    private function limparCaches(): void
    {
        Cache::store('redis')->flush();
        Cache::flush();
        try {
            Redis::connection()->flushdb();
        } catch (\Throwable $e) {
            // A conexao redis crua e' opcional; o flush do store ja' cobre as chaves da app.
        }
    }

    /**
     * Executa a requisicao pelo kernel HTTP real, com a conexao informada como default.
     */
    private function request(string $conn, string $uri, array $params): array
    {
        $anterior = config('database.default');
        try {
            config(['database.default' => $conn]);
            DB::setDefaultConnection($conn);
            Model::clearBootedModels();
            $this->limparCaches();

            // O usuario precisa ser lido da conexao ativa.
            $user = \App\Models\User::where('username', 'admin')->first();
            if (!$user) {
                return ['erro' => "usuario admin nao encontrado em {$conn}"];
            }
            Auth::guard('sanctum')->setUser($user);

            $request = Request::create('/api/' . ltrim($uri, '/'), 'GET', $params);
            $request->headers->set('Accept', 'application/json');

            $kernel = app(HttpKernel::class);
            $response = $kernel->handle($request);
            $conteudo = $response->getContent();

            return [
                'status' => $response->getStatusCode(),
                'json' => json_decode($conteudo, true),
                'raw' => $conteudo,
            ];
        } catch (\Throwable $e) {
            return ['erro' => get_class($e) . ': ' . $e->getMessage()];
        } finally {
            Auth::forgetGuards();
            config(['database.default' => $anterior]);
            DB::setDefaultConnection($anterior);
            Model::clearBootedModels();
        }
    }

    /**
     * Divergencias ESPERADAS, analisadas e aceitas — nao sao regressoes.
     */
    private const CONHECIDAS = [
        'inventory/products-packs index' =>
            'A importacao normaliza listas JSON gravadas como objeto: o pack id 6 tinha '
            . '{"0":{...},"1":{...}} e passa a ter [{...},{...}]. Isto CORRIGE um bug: '
            . 'InventoryProductsPackSelector.vue:58 faz pack.products.map(), que estoura '
            . 'em objeto, e a linha 18 renderiza products.length como undefined. '
            . 'Ver database/mysql-migration/README.md secao 1.7c.',
    ];

    private function comparar(string $nome, string $uri, array $params, array $ra, array $rb): void
    {
        $this->checks++;

        if (isset($ra['erro']) || isset($rb['erro'])) {
            $this->failures++;
            $this->registrar($nome, $uri, 'FAIL', $ra['erro'] ?? 'ok', $rb['erro'] ?? 'ok',
                'Excecao durante a requisicao.');
            $this->line('');
            $this->error("  FAIL  {$nome}");
            $this->line('        SQLite: ' . substr($ra['erro'] ?? 'ok', 0, 200));
            $this->line('        MySQL : ' . substr($rb['erro'] ?? 'ok', 0, 200));
            return;
        }

        if ($ra['status'] !== $rb['status']) {
            $this->failures++;
            $this->registrar($nome, $uri, 'FAIL', $ra['status'], $rb['status'], 'Status HTTP diferente.');
            $this->line('');
            $this->error("  FAIL  {$nome}  status {$ra['status']} vs {$rb['status']}");
            $this->line('        MySQL: ' . substr($rb['raw'] ?? '', 0, 300));
            return;
        }

        if ($ra['status'] >= 400) {
            $this->failures++;
            $this->registrar($nome, $uri, 'FAIL', $ra['status'], $rb['status'],
                'Os dois lados retornaram erro — o caso de teste esta mal montado.');
            $this->line('');
            $this->error("  FAIL  {$nome}  ambos retornaram {$ra['status']}");
            $this->line('        ' . substr($ra['raw'] ?? '', 0, 300));
            return;
        }

        // Defesa 2: nenhuma das duas respostas pode ter vindo do cache.
        foreach ([['SQLite', $ra], ['MySQL', $rb]] as [$lado, $r]) {
            if (is_array($r['json']) && array_key_exists('is_cached', $r['json'])
                && $r['json']['is_cached'] !== false) {
                $this->failures++;
                $this->registrar($nome, $uri, 'FAIL', 'is_cached', $r['json']['is_cached'],
                    "Resposta do lado {$lado} veio do cache — a limpeza falhou e a comparacao "
                    . 'seria enganosa.');
                $this->line('');
                $this->error("  FAIL  {$nome}  resposta de {$lado} veio do CACHE (is_cached=true)");
                return;
            }
        }

        $ja = $this->canonico($ra['json']);
        $jb = $this->canonico($rb['json']);

        if ($ja === $jb) {
            $bytes = strlen($ra['raw'] ?? '');
            $this->registrar($nome, $uri, 'ok', null, null, null);
            $this->line(sprintf('  ok  %-52s %s bytes', $nome, number_format($bytes)));
            return;
        }

        if (isset(self::CONHECIDAS[$nome])) {
            $this->registrar($nome, $uri, 'conhecida', null, null, self::CONHECIDAS[$nome]);
            $this->line("  ~   {$nome}  (divergencia conhecida e aceita)");
            $this->line('      ' . self::CONHECIDAS[$nome]);
            return;
        }

        $this->failures++;
        $this->registrar($nome, $uri, 'FAIL', $this->recorte($ja, $jb), $this->recorte($jb, $ja),
            'Corpo JSON diferente.');
        $this->line('');
        $this->error("  FAIL  {$nome}");
        $this->line('        SQLite: ' . $this->recorte($ja, $jb));
        $this->line('        MySQL : ' . $this->recorte($jb, $ja));
    }

    /**
     * Prova que a limpeza de cache e' load-bearing: roda o SQLite (populando o cache),
     * depois o MySQL SEM limpar. Se o MySQL devolver is_cached=true, esta confirmado
     * que sem a defesa a comparacao seria enganada.
     */
    private function provaContaminacaoDeCache(string $a, string $b, array $casos): int
    {
        $this->line('');
        $this->comment('Prova de contaminacao de cache (--poison)');
        $this->line('  Roda A, depois B SEM limpar o cache. Esperado: B responder do cache.');
        $this->line('');

        $comCache = 0;
        $semCache = 0;
        $testaveis = 0;
        $chavesEscritas = 0;

        foreach ($casos as $nome => [$uri, $params]) {
            $ra = $this->request($a, $uri, $params);        // limpa e popula o cache
            if (isset($ra['erro']) || !is_array($ra['json'])
                || !array_key_exists('is_cached', $ra['json'])) {
                continue;                                    // endpoint sem cache: nao aplica
            }
            $testaveis++;

            // Sem limpar: repete o mesmo caminho apontando para o MySQL.
            $anterior = config('database.default');
            config(['database.default' => $b]);
            DB::setDefaultConnection($b);
            Model::clearBootedModels();
            $user = \App\Models\User::where('username', 'admin')->first();
            Auth::guard('sanctum')->setUser($user);
            $request = Request::create('/api/' . ltrim($uri, '/'), 'GET', $params);
            $request->headers->set('Accept', 'application/json');
            $resp = app(HttpKernel::class)->handle($request);
            $json = json_decode($resp->getContent(), true);
            Auth::forgetGuards();
            config(['database.default' => $anterior]);
            DB::setDefaultConnection($anterior);
            Model::clearBootedModels();

            $veioDoCache = is_array($json) && ($json['is_cached'] ?? false) === true;
            $veioDoCache ? $comCache++ : $semCache++;
            $chavesEscritas += $this->contarChavesRedis();
            $this->line(sprintf('  %-52s %s', $nome,
                $veioDoCache ? 'CONTAMINADO (is_cached=true)' : 'nao contaminado'));
        }

        $this->limparCaches();
        $this->line('');
        $this->line(str_repeat('=', 78));
        $this->line("  endpoints com cache testados: {$testaveis}");
        $this->line("  contaminados sem a limpeza:   {$comCache}");

        if ($testaveis === 0) {
            $this->error('Nenhum endpoint com cache foi exercitado — o teste nao prova nada.');
            return 1;
        }
        if ($comCache === 0) {
            // Duas causas possiveis, com consequencias opostas:
            //   a) o cache foi DESATIVADO no codigo (blocos marcados com
            //      "!!!TODO: Uncomment on production") -> nao ha' contaminacao possivel,
            //      que e' o estado desejado para comparar dois servidores;
            //   b) o cache deveria estar ativo e nao esta funcionando -> a checagem de
            //      is_cached da suite principal nao garantiria nada.
            // Distinguimos pelos bytes que a aplicacao escreveu no Redis.
            if ($chavesEscritas === 0) {
                $this->info('Nenhuma chave foi escrita no Redis em ' . $testaveis . ' endpoints: '
                    . 'o cache esta DESATIVADO no codigo. Nao ha' . "'" . ' contaminacao possivel — '
                    . 'e' . "'" . ' exatamente o estado desejado para comparar dois servidores.');
                $this->line('Para reativar, procure por "!!!TODO: Uncomment on production".');
                return 0;
            }

            $this->error('A aplicacao escreveu ' . $chavesEscritas . ' chaves no Redis, mas NENHUM '
                . 'endpoint foi servido do cache. O cache grava e nao le — investigue antes de '
                . 'confiar na checagem de is_cached da suite principal.');
            return 1;
        }

        $this->info("Confirmado: {$comCache} de {$testaveis} endpoints devolveriam o resultado do "
            . 'SQLite se o cache nao fosse limpo. A limpeza feita pela suite principal e' . "'"
            . ' indispensavel — e esta funcionando (ela roda antes de cada requisicao).');
        return 0;
    }

    /** Quantas chaves a aplicacao gravou no Redis desde o ultimo flush. */
    private function contarChavesRedis(): int
    {
        try {
            return (int) Redis::connection()->dbsize();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Normaliza o JSON para comparacao: floats viram representacao decimal fixa, para
     * que o ruido de somatorio entre os dois motores (ver mysql:parity, secao de
     * agregados) nao seja confundido com divergencia de dados.
     */
    private function canonico($v): string
    {
        return json_encode($this->normalizar($v),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizar($v)
    {
        if (is_float($v)) {
            return rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') ?: '0';
        }
        if (is_array($v)) {
            return array_map(fn($x) => $this->normalizar($x), $v);
        }
        return $v;
    }

    /** Recorta a vizinhanca do primeiro byte divergente. */
    private function recorte(string $s, string $outro): string
    {
        $len = min(strlen($s), strlen($outro));
        $i = 0;
        while ($i < $len && $s[$i] === $outro[$i]) {
            $i++;
        }
        $de = max(0, $i - 70);
        return '…' . substr($s, $de, 220) . "…  (byte {$i})";
    }

    private function registrar(string $nome, string $uri, string $status, $a, $b, ?string $nota): void
    {
        $this->results[] = [
            'check' => $nome, 'uri' => $uri, 'status' => $status,
            'sqlite' => is_scalar($a) || $a === null ? $a : json_encode($a),
            'mysql' => is_scalar($b) || $b === null ? $b : json_encode($b),
            'note' => $nota,
        ];
    }

    /**
     * Casos de teste. Os valores vem dos dados reais de producao (almacens 1..14,
     * categorias existentes, faixas de data com movimento) para que as respostas
     * tenham volume — um endpoint que devolve lista vazia nao prova nada.
     */
    private function casos(): array
    {
        $ini = '2024-01-01';
        $fim = '2024-12-31';
        // warehouse_ids.* e' validado como 'string' — e' assim que o frontend envia
        // na query string. Passar inteiros da' 422.
        $armazens = ['1', '2', '3', '4'];

        return [
            // ---- Relatorios de inventario: os mais pesados em analise de dados ----
            'inventory/by-products-kardex (PEN, alm 1)' => [
                'management/records/inventory/by-products-kardex',
                ['money_type' => 'PEN', 'warehouse_ids' => ['1'],
                 'start_date' => $ini, 'end_date' => $fim],
            ],
            'inventory/by-products-kardex (PYG, alm 2-4)' => [
                'management/records/inventory/by-products-kardex',
                ['money_type' => 'PYG', 'warehouse_ids' => ['2', '3', '4'],
                 'start_date' => $ini, 'end_date' => $fim],
            ],
            'inventory/by-products-balance (PEN)' => [
                'management/records/inventory/by-products-balance',
                ['money_type' => 'PEN', 'warehouse_ids' => $armazens,
                 'start_date' => $ini, 'end_date' => $fim],
            ],
            'inventory/by-products-balance (ignore void)' => [
                'management/records/inventory/by-products-balance',
                ['money_type' => 'PEN', 'warehouse_ids' => $armazens,
                 'start_date' => $ini, 'end_date' => $fim, 'ignore_void_pricing' => 'true'],
            ],
            'inventory/by-products-stock (todos)' => [
                'management/records/inventory/by-products-stock',
                ['warehouse_ids' => $armazens],
            ],
            'inventory/by-products-stock (categoria)' => [
                'management/records/inventory/by-products-stock',
                ['warehouse_ids' => $armazens, 'categories' => ['Herramientas', 'EPP']],
            ],
            'inventory/by-products-loans-kardex' => [
                'management/records/inventory/by-products-loans-kardex',
                ['warehouse_ids' => $armazens, 'start_date' => $ini, 'end_date' => $fim],
            ],
            'inventory/by-products' => [
                'management/records/inventory/by-products', [],
            ],
            'inventory/by-incomes-loanables' => [
                'management/records/inventory/by-incomes-loanables',
                ['warehouse_ids' => $armazens, 'start_date' => $ini, 'end_date' => $fim],
            ],

            // ---- Relatorios financeiros ----
            'jobs/by-costs' => [
                'management/records/jobs/by-costs',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'jobs/by-costs (PE)' => [
                'management/records/jobs/by-costs',
                ['start_date' => $ini, 'end_date' => $fim, 'country' => 'PE'],
            ],
            'users/by-costs' => [
                'management/records/users/by-costs',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'users/by-costs (expense 704)' => [
                'management/records/users/by-costs',
                ['start_date' => $ini, 'end_date' => $fim, 'expense_code' => '704'],
            ],
            'reports/by-time' => [
                'management/records/reports/by-time',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'reports/by-time (PEN/Facture)' => [
                'management/records/reports/by-time',
                ['start_date' => $ini, 'end_date' => $fim,
                 'money_type' => 'PEN', 'type' => 'Facture'],
            ],
            'invoices/by-items' => [
                'management/records/invoices/by-items',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'invoices/by-items (PE, job)' => [
                'management/records/invoices/by-items',
                ['start_date' => $ini, 'end_date' => $fim,
                 'country' => 'PE', 'job_code' => '2000.01-PE[UPS]'],
            ],
            'general/general-records' => [
                'management/records/general/general-records',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'general/general-records (PE, PEN)' => [
                'management/records/general/general-records',
                ['start_date' => $ini, 'end_date' => $fim,
                 'country' => 'PE', 'money_type' => 'PEN'],
            ],

            // ---- Assistencias (folha e custo de mao de obra) ----
            'attendances/by-worker' => [
                'management/records/attendances/by-worker',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'attendances/by-jobs' => [
                'management/records/attendances/by-jobs',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'attendances/by-jobs-expenses' => [
                'management/records/attendances/by-jobs-expenses',
                ['start_date' => $ini, 'end_date' => $fim],
            ],
            'attendances/by-workers-jobs-expenses' => [
                'management/records/attendances/by-workers-jobs-expenses',
                ['start_date' => $ini, 'end_date' => $fim],
            ],

            // ---- Saldos (carteira de cada usuario) ----
            'management/balances/users (2024)' => [
                'management/balances/users', ['year' => 2024],
            ],
            'management/balances/users (2025)' => [
                'management/balances/users', ['year' => 2025],
            ],

            // ---- Listagens e detalhes ----
            'reports index' => ['reports', []],
            'invoices index' => ['invoices', []],
            'balances index' => ['balances', []],
            'workers index' => ['workers', []],
            'worker-payments index' => ['worker-payments', []],
            'jobs index' => ['jobs', []],
            'expenses index' => ['expenses', []],
            'users index' => ['users', []],
            'inventory/products index' => ['inventory/products', []],
            'inventory/products-packs index' => ['inventory/products-packs', []],
            'inventory/warehouses index' => ['inventory/warehouses', []],
            'inventory/warehouse-incomes index' => ['inventory/warehouse-incomes', []],
            'inventory/warehouse-outcomes index' => ['inventory/warehouse-outcomes', []],
            'inventory/warehouse-loans index' => ['inventory/warehouse-loans', []],
            'inventory/warehouse-outcome-requests index' => ['inventory/warehouse-outcome-requests', []],

            // ---- Detalhes por id (exercitam relacoes e acessores) ----
            'warehouse 1 stock' => ['inventory/warehouses/1/stock', []],
            'warehouse 1 products' => ['inventory/warehouses/1/products', []],
            'warehouse 1 loans-by-users' => ['inventory/warehouses/1/loans-by-users', []],
            'warehouse 1 incomes' => ['inventory/warehouses/1/incomes', []],
            'warehouse 1 outcomes' => ['inventory/warehouses/1/outcomes', []],
            'balance me 2024' => ['balance/me/years/2024', []],
            'account/me' => ['account/me', []],
        ];
    }
}
