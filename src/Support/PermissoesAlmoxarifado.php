<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Support;

use App\Models\LegacyUserType;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permissões do módulo (config/almoxarifado.php → permissoes.*).
 */
final class PermissoesAlmoxarifado
{
    /** @var array<string, int> */
    private const MAPA_NIVEL = [
        'admin' => LegacyUserType::LEVEL_ADMIN,
        'poli' => LegacyUserType::LEVEL_ADMIN,
        'poli-institucional' => LegacyUserType::LEVEL_ADMIN,
        'institucional' => LegacyUserType::LEVEL_INSTITUTIONAL,
        'escolar' => LegacyUserType::LEVEL_SCHOOLING,
        'escola' => LegacyUserType::LEVEL_SCHOOLING,
        'biblioteca' => LegacyUserType::LEVEL_LIBRARY,
    ];

    /** @return array<string, bool> */
    public static function menu(): array
    {
        $cfg = (array) config('almoxarifado.permissoes.menu', []);

        return [
            'painel' => (bool) ($cfg['painel'] ?? true),
            'itens' => (bool) ($cfg['itens'] ?? true),
            'saldos' => (bool) ($cfg['saldos'] ?? true),
            'pedidos' => (bool) ($cfg['pedidos'] ?? true),
            'alertas' => (bool) ($cfg['alertas'] ?? true),
            'movimentos' => (bool) ($cfg['movimentos'] ?? true),
        ];
    }

    /**
     * Abrir carrinho de pedidos pelo setor.
     * Default: admin do sistema (ALMOXARIFADO_CARRINHO_SETOR_NIVEIS).
     */
    public static function podeCarrinhoSetor(?User $user = null): bool
    {
        return self::podeAcao('carrinho_setor', Processos::CARRINHO_SETOR, $user);
    }

    /**
     * Pedir item não cadastrado / sem saldo (gera alerta de compra) — default false.
     */
    public static function podePedidoForaCatalogo(?User $user = null): bool
    {
        return self::podeAcao('pedido_fora_catalogo', Processos::PEDIDO_FORA_CATALOGO, $user);
    }

    /**
     * Retirar itens faltosos do pedido (viram «não atendidos» / nota parcial).
     * Default: admin + institucional (ou processo cadastra=1).
     */
    public static function podeRetirarFaltosos(?User $user = null): bool
    {
        return self::podeAcao('retirar_faltosos', Processos::RETIRAR_FALTOSOS, $user);
    }

    /**
     * @return list<string|int>
     */
    public static function niveisAssinatura(string $etapa): array
    {
        $cfg = (array) config('almoxarifado.permissoes.assinaturas', []);
        $lista = $cfg[$etapa] ?? ['admin', 'institucional'];

        return is_array($lista) ? array_values($lista) : ['admin'];
    }


    /**
     * Usuários ativos cujo nível está autorizado a assinar a etapa.
     *
     * @return list<int>
     */
    public static function usuariosQuePodemAssinar(string $etapa, ?int $escolaId = null): array
    {
        $niveis = self::niveisAssinatura($etapa);
        if ($niveis === []) {
            return [];
        }
        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            return \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::usuariosDosNiveis($niveis, $escolaId);
        }

        return [];
    }

    public static function podeAssinar(?User $user, string $etapa): bool
    {
        if ($user === null) {
            return false;
        }

        $permitidos = self::niveisAssinatura($etapa);
        if ($permitidos === []) {
            return false;
        }

        return self::usuarioEmNiveis($user, $permitidos);
    }

    /**
     * Operação do depósito (catálogo, movimentos, alertas, remessa/QR).
     * Alinhado às etapas aceite/separação do novo ciclo.
     */
    public static function podeOperarDeposito(?User $user = null): bool
    {
        $user ??= self::usuarioAtual();
        if ($user === null) {
            return false;
        }

        return self::podeAssinar($user, 'aceite')
            || self::podeAssinar($user, 'separacao')
            || self::podeRetirarFaltosos($user);
    }

    /** Emitir QR / etiquetas (marca «em trânsito»). */
    public static function podeEmitirRemessa(?User $user = null): bool
    {
        return self::podeOperarDeposito($user);
    }

    /** Gerir alertas de compra (entrada / status). */
    public static function podeGerirAlertas(?User $user = null): bool
    {
        return self::podeOperarDeposito($user);
    }

    public static function usuarioAtual(): ?User
    {
        $u = Auth::user();

        return $u instanceof User ? $u : null;
    }

    /**
     * @return array{id:int|null,nome:string,login:?string}
     */
    public static function dadosUsuarioLogado(): array
    {
        $u = self::usuarioAtual();
        if ($u === null) {
            return ['id' => null, 'nome' => 'sistema', 'login' => null];
        }

        return [
            'id' => (int) $u->id,
            'nome' => (string) ($u->name ?? $u->login ?? $u->email ?? 'Usuário'),
            'login' => isset($u->login) ? (string) $u->login : null,
        ];
    }

    private static function podeAcao(string $chave, int $processo, ?User $user = null): bool
    {
        $user ??= self::usuarioAtual();
        $cfg = (array) config('almoxarifado.permissoes.acoes.'.$chave, []);
        if (! (bool) ($cfg['habilitado'] ?? false)) {
            // Ainda permite se o tipo_usuario tiver cadastra=1 no processo do menu.
            return $user !== null && self::menuCadastra($user, $processo);
        }

        if ($user === null) {
            return false;
        }

        $niveis = $cfg['niveis'] ?? [];
        if (is_array($niveis) && $niveis !== [] && self::usuarioEmNiveis($user, $niveis)) {
            return true;
        }

        return self::menuCadastra($user, $processo);
    }

    /**
     * @param  list<string|int>  $permitidos
     */
    public static function usuarioEmNiveis(User $user, array $permitidos): bool
    {
        foreach ($permitidos as $token) {
            if ($token === '*' || $token === 'todos' || $token === 'any') {
                return true;
            }
        }

        $nivel = self::nivelDoUsuario($user);
        if ($nivel === null) {
            return false;
        }

        foreach ($permitidos as $token) {
            if (is_numeric($token) && (int) $token === $nivel) {
                return true;
            }
            $chave = strtolower(trim((string) $token));
            if (isset(self::MAPA_NIVEL[$chave]) && self::MAPA_NIVEL[$chave] === $nivel) {
                return true;
            }
        }

        return false;
    }

    private static function menuCadastra(User $user, int $processo): bool
    {
        if (! Schema::hasTable('menus') && ! Schema::hasTable('portal.menu')) {
            return false;
        }
        if (! Schema::hasTable('pmieducar.menu_tipo_usuario')) {
            return false;
        }

        try {
            $tipoId = (int) ($user->ref_cod_tipo_usuario ?? $user->type->cod_tipo_usuario ?? 0);
            if ($tipoId <= 0) {
                return false;
            }

            $menuTable = Schema::hasTable('menus') ? 'menus' : 'portal.menu';
            $menuId = DB::table($menuTable)->where('process', $processo)->value('id');
            if ($menuId === null) {
                return false;
            }

            $row = DB::table('pmieducar.menu_tipo_usuario')
                ->where('ref_cod_tipo_usuario', $tipoId)
                ->where('menu_id', $menuId)
                ->first();

            return $row !== null && (int) ($row->cadastra ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function nivelDoUsuario(User $user): ?int
    {
        try {
            if (method_exists($user, 'getLevel')) {
                return (int) $user->getLevel();
            }
            if (isset($user->type)) {
                return (int) ($user->type->level ?? $user->type->nivel ?? 0) ?: null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
