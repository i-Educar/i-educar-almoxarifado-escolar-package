<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * 5.7.3: QR / etiquetas para confirmação de recebimento do pedido.
 *
 * Token estável por pedido (gravado no payload) para reimpressão.
 * QR visual: SVG/Canvas no browser (sem Google Charts, que deixou de servir imagem).
 */
class QrCodeRemessa
{
    /**
     * @return array{pedido_id:int,token:string,codigo:string,url:string,escola:string,status:string,itens:int,aberto_em:?string}
     */
    public static function emitir(int $pedidoId): array
    {
        // Remessa = material em deslocamento.
        try {
            PedidoCarrinho::marcarEmTransito($pedidoId);
        } catch (Throwable) {
            //
        }

        $pedido = PedidoCarrinho::obter($pedidoId);
        $p = $pedido['payload'];
        $token = trim((string) ($p['qr_token'] ?? ''));

        if ($token === '' || strlen($token) < 16) {
            $token = self::tokenEstavel($pedidoId);
            $p['qr_token'] = $token;
            $p['qr_emitido_em'] = now()->toDateTimeString();
            PocRegistro::atualizar(
                $pedidoId,
                $p,
                (string) ($p['escola'] ?? ''),
                ($p['status'] ?? '').' · QR emitido',
            );
        }

        self::armazenarToken($token, $pedidoId);

        $url = url('/almoxarifado-escolar/confirmar-recebimento/'.$token);
        $apiPath = '/api/apps/v1/equipe/almox/confirmar';
        // QR / APP: payload canônico da API (status = assinatura de entrega).
        $payloadScan = [
            'tipo' => 'almox_confirmar_recebimento',
            'pedido_id' => $pedidoId,
            'token' => $token,
            'endpoint' => $apiPath,
            'metodo' => 'POST',
            'permissao' => 'almoxarifado.permissoes.assinaturas.entrega',
            'web_url' => $url,
        ];

        return [
            'pedido_id' => $pedidoId,
            'token' => $token,
            'codigo' => strtoupper(substr($token, 0, 4).'-'.substr($token, 4, 4).'-'.substr($token, 8, 4)),
            'url' => $url,
            'api_path' => $apiPath,
            'api_method' => 'POST',
            'payload_scan' => $payloadScan,
            'escola' => (string) ($p['escola'] ?? '—'),
            'status' => (string) ($p['status'] ?? ''),
            'itens' => count($p['itens'] ?? []),
            'aberto_em' => isset($p['aberto_em']) ? (string) $p['aberto_em'] : ($pedido['created_at'] ?? null),
            'setor' => (string) ($p['setor'] ?? ''),
            'payload' => $p,
        ];
    }

    public static function gerarUrlConfirmacao(int $pedidoId): string
    {
        return self::emitir($pedidoId)['url'];
    }

    /** @deprecated use emitir() */
    public static function gerarToken(int $pedidoId): string
    {
        return self::emitir($pedidoId)['token'];
    }

    /** @deprecated Google Charts — mantido só como fallback remoto */
    public static function gerarUrlQrCode(string $url, int $size = 240): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&ecc=M&margin=8&data='.rawurlencode($url);
    }

    public static function validarToken(string $token): ?int
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $pedidoId = cache()->get('qr_remessa_'.$token);
        if ($pedidoId !== null) {
            return (int) $pedidoId;
        }

        // Busca no payload dos pedidos PoC (token estável)
        if (PocRegistro::disponivel()) {
            try {
                foreach (PocRegistro::listar('pedido', 500) as $r) {
                    if (($r['payload']['qr_token'] ?? '') === $token) {
                        self::armazenarToken($token, (int) $r['id']);

                        return (int) $r['id'];
                    }
                }
            } catch (Throwable) {
                //
            }
        }

        if (Schema::hasTable('almoxarifado.remessa_tokens')) {
            try {
                $registro = DB::table('almoxarifado.remessa_tokens')
                    ->where('token', $token)
                    ->whereNull('usado_em')
                    ->where('expira_em', '>', now())
                    ->first();
                if ($registro) {
                    return (int) ($registro->remessa_id ?? $registro->pedido_id ?? 0) ?: null;
                }
            } catch (Throwable) {
                //
            }
        }

        return null;
    }

    public static function confirmarRecebimento(string $token, ?int $usuarioId = null): bool
    {
        $pedidoId = self::validarToken($token);
        if ($pedidoId === null || $pedidoId < 1) {
            return false;
        }

        $usuarioId = $usuarioId ?? (Auth::id() ? (int) Auth::id() : null);
        if ($usuarioId === null || $usuarioId < 1) {
            return false;
        }
        // QR faz o papel da assinatura de entrega: só quem tem permissão da etapa.
        if (! self::usuarioPodeConfirmarEntrega($usuarioId)) {
            return false;
        }
        $ok = false;

        if (Schema::hasTable('almoxarifado.remessas')) {
            try {
                $afetados = DB::table('almoxarifado.remessas')
                    ->where('id', $pedidoId)
                    ->update([
                        'status' => 'recebido',
                        'recebido_em' => now(),
                        'recebido_por' => $usuarioId,
                        'updated_at' => now(),
                    ]);
                $ok = $afetados > 0;
            } catch (Throwable) {
                $ok = false;
            }
        }

        if (! $ok) {
            $ok = self::atualizarPedidoPoc($pedidoId, $usuarioId);
        }

        if (! $ok) {
            return false;
        }

        if (Schema::hasTable('almoxarifado.remessa_tokens')) {
            try {
                DB::table('almoxarifado.remessa_tokens')
                    ->where('token', $token)
                    ->update(['usado_em' => now()]);
            } catch (Throwable) {
                //
            }
        }

        self::registrarConfirmacao($pedidoId, $token, $usuarioId);

        return true;
    }

    /** @deprecated prefer view pedido-qr */
    public static function gerarHtmlQrCode(int $pedidoId): string
    {
        $d = self::emitir($pedidoId);
        $qrUrl = e(self::gerarUrlQrCode($d['url'], 240));
        $codigo = e($d['codigo']);
        $url = e($d['url']);

        return <<<HTML
        <div style="text-align:center;padding:1rem;border:2px solid var(--linha,#cfd8df);border-radius:12px;background:#fff;max-width:320px;margin:1rem auto">
            <img src="{$qrUrl}" alt="QR Pedido #{$pedidoId}" width="240" height="240" style="display:block;margin:0 auto;image-rendering:pixelated">
            <p style="margin:10px 0 0;font-weight:800">Pedido #{$pedidoId}</p>
            <p style="margin:4px 0;font-family:ui-monospace,monospace;letter-spacing:.06em">{$codigo}</p>
            <p style="margin:6px 0 0;font-size:.72rem;word-break:break-all;color:#5b6b76">{$url}</p>
        </div>
        HTML;
    }

    private static function tokenEstavel(int $pedidoId): string
    {
        $salt = (string) config('app.key', 'ieducar-laguna');

        return substr(hash('sha256', 'almox-qr|'.$pedidoId.'|'.$salt), 0, 32);
    }

    private static function atualizarPedidoPoc(int $pedidoId, ?int $usuarioId): bool
    {
        if (! PocRegistro::disponivel()) {
            return false;
        }

        try {
            $pedido = PedidoCarrinho::obter($pedidoId);
        } catch (Throwable) {
            return false;
        }

        $p = $pedido['payload'];
        $statusAtual = (string) ($p['status'] ?? '');

        if ($statusAtual === PedidoCarrinho::STATUS_CARRINHO) {
            return false;
        }

        // Parcial bloqueado ou já conferido: QR não confirma.
        if (! PedidoCarrinho::podeConfirmarRecebimento($p)) {
            return false;
        }

        if (! in_array($statusAtual, [PedidoCarrinho::STATUS_ENTREGUE, PedidoCarrinho::STATUS_CONFERIDO], true)) {
            $p['status'] = PedidoCarrinho::STATUS_ENTREGUE;
        }

        $agora = now();
        $p['recebido_em'] = $agora->toDateTimeString();
        $p['recebido_por'] = $usuarioId;
        $p['recebido_via'] = 'qrcode';

        if (empty($p['assinaturas']['entrega'])) {
            $nome = 'QR Code';
            $login = null;
            if ($usuarioId) {
                $u = Auth::user();
                $nome = $u?->name ?? $u?->login ?? $u?->email ?? ('Usuário #'.$usuarioId);
                $login = $u?->login ?? null;
            }
            $p['assinaturas']['entrega'] = [
                'responsavel' => $nome,
                'responsavel_id' => $usuarioId,
                'login' => $login,
                'observacao' => 'Confirmado via QR Code / API',
                'quando' => $agora->format('d/m/Y H:i:s'),
                'quando_iso' => $agora->toIso8601String(),
                'quando_raw' => $agora->toDateTimeString(),
                'via' => 'qrcode',
            ];
            try {
                $p = (new NotaAssinaturaDigital())->aposAssinarEtapa(
                    $pedidoId,
                    'entrega',
                    $p,
                    $nome,
                    $login,
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (empty($p['baixa_estoque'])) {
            try {
                foreach ($p['itens'] ?? [] as $item) {
                    if (($item['origem'] ?? 'catalogo') !== 'catalogo') {
                        continue;
                    }
                    MovimentoEstoque::registrar(
                        MovimentoEstoque::SAIDA,
                        (string) $item['sku'],
                        (string) $item['titulo'],
                        (int) $item['quantidade'],
                        'pedido',
                        $pedidoId,
                        'Baixa automática na confirmação QR do pedido #'.$pedidoId,
                        (string) ($p['assinaturas']['entrega']['responsavel'] ?? 'QR Code'),
                        $usuarioId,
                    );
                }
                $p['baixa_estoque'] = true;
                $p['baixa_em'] = $agora->toDateTimeString();
            } catch (Throwable) {
                //
            }
        }

        $ok = PocRegistro::atualizar(
            $pedidoId,
            $p,
            (string) ($p['escola'] ?? ''),
            $p['status'].' · '.count($p['itens'] ?? []).' item(ns) · QR',
        );
        if ($ok) {
            $proxima = PedidoCarrinho::proximaEtapaPendente($p);
            if ($proxima !== null) {
                PedidoCarrinho::notificarEtapaPendente($pedidoId, $proxima, $p);
            }
        }

        return $ok;
    }

    private static function usuarioPodeConfirmarEntrega(int $usuarioId): bool
    {
        if (! class_exists(\iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::class)) {
            return false;
        }
        try {
            $user = Auth::user();
            if ($user instanceof \App\User && (int) $user->getAuthIdentifier() === $usuarioId) {
                return \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::podeAssinar($user, 'entrega');
            }
            $user = \App\User::query()->find($usuarioId);
            if (! $user instanceof \App\User) {
                return false;
            }

            return \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::podeAssinar($user, 'entrega');
        } catch (Throwable) {
            return false;
        }
    }

    private static function armazenarToken(string $token, int $pedidoId): void
    {
        cache()->put('qr_remessa_'.$token, $pedidoId, now()->addDays(90));

        if (Schema::hasTable('almoxarifado.remessa_tokens')) {
            try {
                $existe = DB::table('almoxarifado.remessa_tokens')->where('token', $token)->exists();
                if (! $existe) {
                    DB::table('almoxarifado.remessa_tokens')->insert([
                        'token' => $token,
                        'remessa_id' => $pedidoId,
                        'criado_em' => now(),
                        'expira_em' => now()->addDays(90),
                        'usado_em' => null,
                    ]);
                }
            } catch (Throwable) {
                //
            }
        }
    }

    private static function registrarConfirmacao(int $pedidoId, string $token, ?int $usuarioId): void
    {
        if (! Schema::hasTable('almoxarifado.auditoria_remessas')) {
            return;
        }

        try {
            DB::table('almoxarifado.auditoria_remessas')->insert([
                'remessa_id' => $pedidoId,
                'acao' => 'confirmacao_qrcode',
                'usuario_id' => $usuarioId,
                'detalhes' => json_encode(['token' => substr($token, 0, 8).'...', 'pedido_id' => $pedidoId]),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            //
        }
    }
}
