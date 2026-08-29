<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Define uma nova senha para o usuario admin.
 *
 * Diferente de `users:change-password`, que so' funciona com um humano no terminal
 * (usa choice() e secret()), este comando tambem roda em script de deploy.
 *
 *   php artisan admin:set-password                 # pergunta a senha, oculta, duas vezes
 *   php artisan admin:set-password --generate      # gera uma senha forte e a exibe uma vez
 *   php artisan admin:set-password --from-env      # le de ADMIN_NEW_PASSWORD (deploy)
 *   php artisan admin:set-password --user=joao     # outro usuario, nao o admin
 *
 * NAO existe a opcao --password=... de proposito: o valor ficaria no historico do
 * shell e visivel em `ps` para qualquer usuario da maquina. Para automacao use
 * --from-env, que le de uma variavel de ambiente.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'admin:set-password
        {--user=admin : username do usuario alvo}
        {--generate : gera uma senha forte em vez de perguntar}
        {--from-env : le a senha da variavel de ambiente ADMIN_NEW_PASSWORD}
        {--force : nao pede confirmacao (para scripts)}';

    protected $description = 'Define uma nova senha para o usuario admin';

    private const TAMANHO_MINIMO = 8;

    public function handle(): int
    {
        $username = (string) $this->option('user');

        $user = User::where('username', $username)->first();
        if (!$user) {
            $this->error("Usuario '{$username}' nao encontrado.");
            $existentes = User::orderBy('username')->pluck('username')->take(15)->implode(', ');
            $this->line("Usuarios disponiveis: {$existentes}");
            return 1;
        }

        // Mostrar o alvo antes de escrever: este projeto convive com varios bancos
        // (maranatha, maranatha_write, ...) e trocar a senha no banco errado e' um
        // erro facil de cometer e dificil de perceber.
        $conexao = DB::getDefaultConnection();
        $this->line('');
        $this->line('  Usuario : ' . $user->username . ' (' . $user->name . ', id ' . $user->id . ')');
        $this->line('  Conexao : ' . $conexao);
        $this->line('  Banco   : ' . config("database.connections.{$conexao}.database"));
        $this->line('');

        [$senha, $erro] = $this->obterSenha();
        if ($erro !== null) {
            $this->error($erro);
            return 1;
        }

        if (!$this->option('force') && !$this->confirm("Trocar a senha de '{$user->username}'?", true)) {
            $this->line('Cancelado. Nada foi alterado.');
            return 1;
        }

        // O model tem o cast 'password' => 'hashed', entao atribuir o texto puro ja'
        // gera o hash. Nao usamos Hash::make() aqui para nao haver dois caminhos de
        // hashing no projeto — o cast e' o unico responsavel.
        $user->password = $senha;
        $user->save();

        // Reler do banco e verificar: prova que o hash foi gravado e confere, em vez
        // de confiar que o save() fez o certo.
        $gravado = User::where('id', $user->id)->first();
        if (!Hash::check($senha, $gravado->password)) {
            $this->error('A senha foi gravada mas nao confere na verificacao. '
                . 'Nada garante que o login vai funcionar — investigue antes de prosseguir.');
            return 1;
        }

        $this->info("Senha de '{$user->username}' alterada e verificada.");

        if ($this->option('generate')) {
            $this->line('');
            $this->warn('  Senha gerada (mostrada uma unica vez):');
            $this->line('  ' . $senha);
            $this->line('');
            $this->line('  Guarde-a agora — ela nao pode ser recuperada depois.');
        }

        return 0;
    }

    /** @return array{0: ?string, 1: ?string} [senha, mensagem de erro] */
    private function obterSenha(): array
    {
        if ($this->option('generate')) {
            // 24 chars de Str::password(): letras, numeros e simbolos, com CSPRNG.
            return [Str::password(24), null];
        }

        if ($this->option('from-env')) {
            $senha = env('ADMIN_NEW_PASSWORD');
            if (!is_string($senha) || $senha === '') {
                return [null, 'A variavel de ambiente ADMIN_NEW_PASSWORD esta vazia ou nao definida.'];
            }
            if (strlen($senha) < self::TAMANHO_MINIMO) {
                return [null, 'ADMIN_NEW_PASSWORD tem menos de ' . self::TAMANHO_MINIMO . ' caracteres.'];
            }
            return [$senha, null];
        }

        // isInteractive() so' reflete a flag --no-interaction; num deploy com stdin
        // redirecionado ele ainda diz "true" e o prompt aborta com uma mensagem
        // generica do Symfony. Checar o TTY da' um erro que explica o que fazer.
        $temTerminal = $this->input->isInteractive()
            && (!defined('STDIN') || !function_exists('stream_isatty') || stream_isatty(STDIN));

        if (!$temTerminal) {
            return [null, 'Sem terminal interativo. Use --generate ou --from-env.'];
        }

        // Duas vezes: um erro de digitacao aqui deixa o admin sem acesso.
        $senha = (string) $this->secret('Nova senha');
        if (strlen($senha) < self::TAMANHO_MINIMO) {
            return [null, 'A senha precisa ter ao menos ' . self::TAMANHO_MINIMO . ' caracteres.'];
        }

        $confirmacao = (string) $this->secret('Repita a nova senha');
        if ($senha !== $confirmacao) {
            return [null, 'As senhas nao coincidem. Nada foi alterado.'];
        }

        return [$senha, null];
    }
}
