<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Executa a suite de paridade SQLite x MySQL dentro do PHPUnit, para CI.
 *
 * Os testes sao PULADOS quando as duas conexoes nao estao disponiveis, entao a
 * suite continua verde numa maquina sem MySQL. Para rodar de verdade:
 *
 *   DB_REF_DATABASE=/caminho/database.sqlite \
 *   DB_CONNECTION=mysql DB_DATABASE=maranatha_parity \
 *   ./vendor/bin/phpunit tests/Feature/MysqlParityTest.php
 *
 * A logica vive em App\Console\Commands\MysqlParityCheck; aqui so' a envolvemos
 * em asserts. Rodar `php artisan mysql:parity` da' a saida detalhada.
 */
class MysqlParityTest extends TestCase
{
    private function pularSeIndisponivel(): void
    {
        foreach (['sqlite_ref', 'mysql'] as $conn) {
            try {
                DB::connection($conn)->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped("Conexao '{$conn}' indisponivel: " . $e->getMessage());
            }
        }

        // Um MySQL vazio passaria em tudo por vacuidade.
        if (DB::connection('mysql')->table('invoices')->count() === 0) {
            $this->markTestSkipped('O MySQL alvo esta vazio — importe os dados antes.');
        }
    }

    private function rodarNivel(string $nivel): void
    {
        $this->pularSeIndisponivel();

        $codigo = $this->artisan('mysql:parity', ['--level' => $nivel])->run();

        $this->assertSame(0, $codigo,
            "Divergencias no nivel '{$nivel}'. Rode `php artisan mysql:parity --level={$nivel}` "
            . 'para ver o detalhe de cada uma.');
    }

    public function test_estrutura_identica(): void
    {
        $this->rodarNivel('structure');
    }

    /** Compara valor E tipo PHP de cada celula de cada tabela. */
    public function test_dados_identicos_linha_a_linha(): void
    {
        $this->rodarNivel('rows');
    }

    /** SUM/AVG/MIN/MAX de dinheiro: pega double(8,2) e arredondamento. */
    public function test_agregados_de_dinheiro_identicos(): void
    {
        $this->rodarNivel('aggregates');
    }

    /** Datas ISO em VARCHAR, GROUP BY, colacao, whereIn grande, JSON_CONTAINS. */
    public function test_consultas_da_aplicacao_identicas(): void
    {
        $this->rodarNivel('queries');
    }

    /** Kardex, balances, stock e custos — o resultado contabil. */
    public function test_geradores_de_relatorio_identicos(): void
    {
        $this->rodarNivel('logic');
    }

    /**
     * Respostas HTTP reais dos 47 endpoints, rota a rota.
     *
     * Cobre o que o nivel de banco nao alcanca: middleware, serializacao JSON,
     * tipo numerico no corpo da resposta e ordenacao das linhas — foi aqui que
     * apareceram os erros 1054 de alias no WHERE e as divergencias de ordem.
     */
    public function test_respostas_da_api_identicas(): void
    {
        $this->pularSeIndisponivel();

        $codigo = $this->artisan('mysql:parity-api')->run();

        $this->assertSame(0, $codigo,
            'Divergencias nas respostas da API. Rode `php artisan mysql:parity-api` '
            . 'para ver o detalhe de cada uma.');
    }

    /**
     * Caminho de ESCRITA. Pulado por padrao: o comando grava nos dois bancos e so'
     * pode rodar contra copias descartaveis, indicadas por MYSQL_PARITY_WRITE=1.
     *
     * Os erros de strict mode do MySQL (1364, 1406, 1264, 1292) acontecem no INSERT,
     * nao no SELECT — sem este teste, a correcao dos defaults em $attributes nunca
     * seria exercitada.
     */
    public function test_caminho_de_escrita_identico(): void
    {
        if (env('MYSQL_PARITY_WRITE') !== '1') {
            $this->markTestSkipped(
                'Escreve nos dois bancos. Aponte DB_REF_DATABASE e DB_DATABASE para '
                . 'copias descartaveis e defina MYSQL_PARITY_WRITE=1.');
        }
        $this->pularSeIndisponivel();

        $codigo = $this->artisan('mysql:parity-write', ['--confirmo' => true])->run();

        $this->assertSame(0, $codigo,
            'Divergencias no caminho de escrita. Rode `php artisan mysql:parity-write '
            . '--confirmo` para ver o detalhe.');
    }

    /**
     * Verifica que o cache nao consegue falsear a comparacao.
     *
     * RecordsCache e DataCache montam a chave sem incluir a conexao do banco: com o
     * cache ligado, a segunda passada devolveria o resultado memorizado da primeira
     * e TODA a suite de API passaria por engano.
     *
     * O comando aceita dois estados, e so' dois:
     *   - cache LIGADO   -> a contaminacao tem de ser demonstravel (e a suite limpa
     *                       o cache antes de cada requisicao);
     *   - cache DESLIGADO -> a aplicacao nao pode escrever nenhuma chave no Redis.
     * Qualquer estado intermediario (grava mas nao le) reprova.
     */
    public function test_cache_nao_consegue_falsear_a_comparacao(): void
    {
        $this->pularSeIndisponivel();

        $codigo = $this->artisan('mysql:parity-api', ['--poison' => true])->run();

        $this->assertSame(0, $codigo,
            'O teste de contaminacao falhou: ou nenhum endpoint com cache foi exercitado, '
            . 'ou o cache nao esta ativo neste ambiente — nos dois casos a checagem de '
            . 'is_cached da suite principal nao garantiria nada.');
    }
}
