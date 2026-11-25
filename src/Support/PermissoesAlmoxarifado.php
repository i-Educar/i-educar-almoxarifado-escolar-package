<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Support;

use App\Models\LegacyUserType;
use App\User;
use Illuminate\Support\Facades\Auth;

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
            'movimentos' => (bool) ($cfg['movimentos'] ?? true),
        ];
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

    public static function podeAssinar(?User $user, string $etapa): bool
    {
        if ($user === null) {
            return false;
        }

        $permitidos = self::niveisAssinatura($etapa);
        if ($permitidos === []) {
            return false;
        }

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
