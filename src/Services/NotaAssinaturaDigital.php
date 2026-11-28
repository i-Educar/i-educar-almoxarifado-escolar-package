<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Autenticidade da nota (código único do documento) + de cada etapa assinado,
 * com envio ao LibreSign quando o módulo de assinatura digital estiver ativo.
 */
final class NotaAssinaturaDigital
{
    /** @var list<string> Assinaturas formais (sem Separação / Em trânsito). */
    private const ETAPAS = ['aceite', 'entrega', 'conferencia'];

    public function ativo(): bool
    {
        if (! class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\AssinaturaOrquestrador::class)) {
            return false;
        }

        try {
            return app(\iEducar\Packages\BuritiAssinaturaDigital\Services\AssinaturaOrquestrador::class)->disponivel();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Após gravar a etapa localmente: garante código do documento, código da assinatura
     * e (se possível) pedido LibreSign daquela etapa.
     *
     * @param  array<string,mixed>  $payload  payload já com a etapa assinada
     * @return array<string,mixed> payload enriquecido
     */
    public function aposAssinarEtapa(
        int $pedidoId,
        string $etapa,
        array $payload,
        string $responsavel,
        ?string $login = null,
    ): array {
        if (! in_array($etapa, self::ETAPAS, true)) {
            return $payload;
        }

        $payload = $this->garantirValidacaoDocumento($pedidoId, $payload);

        $assinatura = is_array($payload['assinaturas'][$etapa] ?? null)
            ? $payload['assinaturas'][$etapa]
            : [];

        if (trim((string) ($assinatura['codigo_validacao'] ?? '')) === '') {
            $reg = $this->registrarValidacao(
                'almox_assinatura_'.$etapa,
                [
                    'pedido_id' => $pedidoId,
                    'etapa' => $etapa,
                    'rotulo' => PedidoCarrinho::rotuloEtapa($etapa),
                    'responsavel' => $responsavel,
                    'login' => $login,
                    'escola' => (string) ($payload['escola'] ?? ''),
                    'documento_codigo' => (string) ($payload['validacao']['codigo'] ?? ''),
                    'quando' => (string) ($assinatura['quando_iso'] ?? now()->toIso8601String()),
                ],
            );
            if ($reg !== null) {
                $assinatura['codigo_validacao'] = $reg['codigo'];
                $assinatura['url_validacao'] = $reg['url'];
            }
        }

        if ($this->ativo() && empty($assinatura['pedido_assinatura_id'])) {
            $ls = $this->enviarLibreSignEtapa($pedidoId, $etapa, $payload, $responsavel, $login);
            if ($ls !== null) {
                $assinatura['pedido_assinatura_id'] = (int) ($ls['pedido']['id'] ?? 0) ?: null;
                $assinatura['libresign_uuid'] = $ls['pedido']['libresign_uuid'] ?? null;
                $assinatura['status_libresign'] = $ls['pedido']['status'] ?? 'aguardando';
                // Prefere código próprio + URL do portal (verificar), não host.docker.internal.
                $visual = $ls['assinaturas_visuais'][0] ?? null;
                if (is_array($visual)) {
                    if (! empty($visual['codigo_validacao'])) {
                        $assinatura['codigo_validacao'] = $visual['codigo_validacao'];
                    }
                    $portal = trim((string) ($visual['url_validacao_portal'] ?? $visual['url_validacao'] ?? ''));
                    if ($portal !== '') {
                        $assinatura['url_validacao'] = $portal;
                    }
                    if (! empty($visual['url_validacao_libresign'])) {
                        $assinatura['url_validacao_libresign'] = $visual['url_validacao_libresign'];
                    }
                }
                if (! empty($ls['codigo_validacao']) && empty($payload['validacao']['codigo_libresign'])) {
                    $payload['validacao']['codigo_libresign'] = $ls['codigo_validacao'];
                    $payload['validacao']['url_libresign'] = $ls['url_validacao'] ?? null;
                }

                // Se ainda ficou "aguardando", tenta concluir agora (mesma pessoa da etapa).
                $assinatura = $this->concluirLibreSignSePendente($assinatura);
            }
        }

        $payload['assinaturas'][$etapa] = $assinatura;

        return $payload;
    }

    /**
     * Repara notas antigas: documento + etapas já assinadas sem código/LibreSign.
     *
     * @param  array<string,mixed>  $payload
     * @return array{payload:array<string,mixed>,alterado:bool}
     */
    public function repararSeNecessario(int $pedidoId, array $payload): array
    {
        $antes = json_encode($payload);
        $payload = $this->garantirValidacaoDocumento($pedidoId, $payload);

        foreach (self::ETAPAS as $etapa) {
            $a = is_array($payload['assinaturas'][$etapa] ?? null) ? $payload['assinaturas'][$etapa] : null;
            if ($a === null || trim((string) ($a['responsavel'] ?? '')) === '') {
                continue;
            }

            $temCodigo = trim((string) ($a['codigo_validacao'] ?? '')) !== '';
            $temPedidoLs = ! empty($a['pedido_assinatura_id']);

            if (! $temCodigo || ! $temPedidoLs) {
                $payload = $this->aposAssinarEtapa(
                    $pedidoId,
                    $etapa,
                    $payload,
                    (string) $a['responsavel'],
                    isset($a['login']) ? (string) $a['login'] : null,
                );
                $a = is_array($payload['assinaturas'][$etapa] ?? null) ? $payload['assinaturas'][$etapa] : $a;
            }

            // Etapas já vinculadas ao LibreSign: conclui se ficou "aguardando" e troca URL docker → portal.
            $a = $this->sincronizarEtapaLibreSign($a, $etapa);
            $payload['assinaturas'][$etapa] = $a;
        }

        $alterado = json_encode($payload) !== $antes;

        return ['payload' => $payload, 'alterado' => $alterado];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function garantirValidacaoDocumento(int $pedidoId, array $payload): array
    {
        $atual = is_array($payload['validacao'] ?? null) ? $payload['validacao'] : [];
        if (trim((string) ($atual['codigo'] ?? '')) !== '') {
            $payload['validacao'] = $atual;

            return $payload;
        }

        $reg = $this->registrarValidacao('almox_nota_pedido', [
            'pedido_id' => $pedidoId,
            'escola' => (string) ($payload['escola'] ?? ''),
            'setor' => (string) ($payload['setor'] ?? ''),
            'status' => (string) ($payload['status'] ?? ''),
            'itens' => count($payload['itens'] ?? []),
            'ver_url' => url('/almoxarifado-escolar/pedidos/'.$pedidoId.'/nota'),
        ]);

        if ($reg === null) {
            return $payload;
        }

        $payload['validacao'] = [
            'codigo' => $reg['codigo'],
            'url' => $reg['url'],
            'qr_data_uri' => $reg['qr_data_uri'] ?? null,
            'via' => $reg['via'] ?? null,
            'emitido_em' => now()->toDateTimeString(),
        ];

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|null
     */

    /**
     * Signatários LibreSign = etapas concluídas até a atual (cada uma com conta técnica distinta).
     *
     * @param  array<string,mixed>  $payload
     * @return list<array<string,mixed>>
     */
    private function signatariosAcumuladosAteEtapa(
        array $payload,
        string $etapaAtual,
        string $responsavelAtual,
        ?string $loginAtual,
    ): array {
        $assin = is_array($payload['assinaturas'] ?? null) ? $payload['assinaturas'] : [];
        $contas = [];
        foreach ([1, 2, 3, 4] as $n) {
            $acc = trim((string) config('assinatura.libresign.signer'.$n, ''));
            if ($acc === '') {
                $acc = 'signer'.$n;
            }
            $pwd = trim((string) config('assinatura.libresign.signer'.$n.'_password', ''));
            if ($pwd === '') {
                $pwd = 'Signer'.$n.'-Dev!';
            }
            $contas[] = ['account' => $acc, 'password' => $pwd];
        }

        $posAtual = array_search($etapaAtual, self::ETAPAS, true);
        if ($posAtual === false) {
            $posAtual = count(self::ETAPAS) - 1;
        }

        $out = [];
        $idx = 0;
        $vistosAccount = [];
        foreach (self::ETAPAS as $pos => $etapa) {
            if ($pos > $posAtual) {
                break;
            }
            $cargo = PedidoCarrinho::rotuloEtapa($etapa);
            $a = is_array($assin[$etapa] ?? null) ? $assin[$etapa] : null;
            $ehAtual = $etapa === $etapaAtual;

            $nome = $ehAtual
                ? trim($responsavelAtual)
                : trim((string) ($a['responsavel'] ?? ''));
            if ($nome === '') {
                if (! $ehAtual) {
                    continue;
                }
                $nome = $cargo;
            }
            $login = $ehAtual
                ? $loginAtual
                : (isset($a['login']) ? (string) $a['login'] : null);
            $userId = $ehAtual
                ? (\Illuminate\Support\Facades\Auth::id() ? (int) \Illuminate\Support\Facades\Auth::id() : null)
                : (isset($a['responsavel_id']) ? (int) $a['responsavel_id'] : null);

            // Uma conta LibreSign por etapa (índice) — a validação LS lista todas as assinaturas.
            $slot = $contas[$idx % count($contas)];
            $account = $slot['account'];
            $password = $slot['password'];
            if (isset($vistosAccount[strtolower($account)])) {
                foreach ($contas as $c) {
                    if (! isset($vistosAccount[strtolower($c['account'])])) {
                        $account = $c['account'];
                        $password = $c['password'];
                        break;
                    }
                }
            }
            $vistosAccount[strtolower($account)] = true;

            $ident = $this->identificadorSignatario($login, $userId, $nome);
            $modulo = $this->rotuloModuloHumanizado();
            // LibreSign: matrícula/login + setor/módulo (etapa fica no nome do documento).
            $rotuloLs = $ident.' — '.$modulo;

            $out[] = array_filter([
                'nome' => $nome,
                'cargo' => $cargo,
                'displayName' => $rotuloLs,
                'description' => $rotuloLs,
                'login' => $login,
                'user_id' => $userId,
                'account' => $account,
                'password' => $password !== '' ? $password : null,
            ], static fn ($v) => $v !== null && $v !== '');

            $idx++;
        }

        if ($out === []) {
            $slot = $contas[0];
            $userId = \Illuminate\Support\Facades\Auth::id() ? (int) \Illuminate\Support\Facades\Auth::id() : null;
            $ident = $this->identificadorSignatario($loginAtual, $userId, $responsavelAtual);
            $rotuloLs = $ident.' — '.$this->rotuloModuloHumanizado();
            $out[] = array_filter([
                'nome' => $responsavelAtual,
                'cargo' => PedidoCarrinho::rotuloEtapa($etapaAtual),
                'displayName' => $rotuloLs,
                'description' => $rotuloLs,
                'login' => $loginAtual,
                'user_id' => $userId,
                'account' => $slot['account'],
                'password' => $slot['password'] !== '' ? $slot['password'] : null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    /** Matrícula funcional (portal) ou login do servidor. */
    private function identificadorSignatario(?string $login, ?int $userId, string $fallbackNome = ''): string
    {
        $login = $login !== null ? trim($login) : '';
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('portal.funcionario')) {
                $q = \Illuminate\Support\Facades\DB::table('portal.funcionario as f');
                if ($userId !== null && $userId > 0) {
                    $q->where('f.ref_cod_pessoa_fj', $userId);
                } elseif ($login !== '') {
                    $q->whereRaw('lower(f.matricula) = ?', [mb_strtolower($login)]);
                } else {
                    $q = null;
                }
                if ($q !== null) {
                    $mat = trim((string) ($q->value('f.matricula') ?? ''));
                    if ($mat !== '') {
                        return $mat;
                    }
                }
            }
        } catch (Throwable) {
            //
        }

        if ($login !== '') {
            return $login;
        }

        $nome = trim($fallbackNome);

        return $nome !== '' ? $nome : 'servidor';
    }

    private function rotuloModuloHumanizado(): string
    {
        return 'Almoxarifado escolar';
    }

    private function enviarLibreSignEtapa(
        int $pedidoId,
        string $etapa,
        array $payload,
        string $responsavel,
        ?string $login,
    ): ?array {
        if (! class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\AssinaturaOrquestrador::class)) {
            return null;
        }

        try {
            $pdf = $this->gerarPdfNota($pedidoId, $payload);
            if ($pdf === null || $pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                return null;
            }

            $orch = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\AssinaturaOrquestrador::class);
            $cargo = PedidoCarrinho::rotuloEtapa($etapa);

            // LibreSign deve listar TODAS as etapas já assinadas (com quem assinou), não só a atual.
            $signers = $this->signatariosAcumuladosAteEtapa($payload, $etapa, $responsavel, $login);

            return $orch->tentarSolicitarDeDocumento(
                'Nota almoxarifado #'.$pedidoId.' — '.$cargo,
                $pdf,
                $signers,
                [
                    'modulo_origem' => 'almoxarifado-escolar',
                    'origem' => 'almox_nota_etapa',
                    'pedido_id' => $pedidoId,
                    'etapa' => $etapa,
                    'etapa_rotulo' => $cargo,
                    'status_documento' => $cargo,
                    'etapas_esperadas' => array_map(
                        static fn (string $e): string => PedidoCarrinho::rotuloEtapa($e),
                        self::ETAPAS,
                    ),
                    // Todas as etapas já confirmadas no almox — conclui no LS agora.
                    'assinar_imediatamente' => true,
                    'forcar_assinatura_imediata' => true,
                    'codigo_validacao_exportacao' => (string) ($payload['validacao']['codigo'] ?? ''),
                    'url_validacao_exportacao' => (string) ($payload['validacao']['url'] ?? ''),
                ],
                'almox-nota-'.$pedidoId.'.pdf',
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }


    /**
     * Regenera o PDF visual (declaração institucional) da etapa.
     *
     * @return array{ok:bool,pdf:?string,uuid:?string,pedido_assinatura_id:?int}
     */

    /**
     * @param  array<string,mixed>  $assinatura
     * @return array<string,mixed>
     */

    /**
     * Alinha status/URL da etapa com o pedido LibreSign (portal, não host.docker.internal).
     *
     * @param  array<string,mixed>  $assinatura
     * @return array<string,mixed>
     */
    private function sincronizarEtapaLibreSign(array $assinatura, string $etapa): array
    {
        $pedidoId = (int) ($assinatura['pedido_assinatura_id'] ?? 0);
        $uuid = trim((string) ($assinatura['libresign_uuid'] ?? ''));
        $cod = trim((string) ($assinatura['codigo_validacao'] ?? ''));

        // Sempre preferir URL do portal i-Educar sob a assinatura.
        if ($cod !== '' && class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)) {
            $portal = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)
                ->urlPublica($cod);
            $atual = trim((string) ($assinatura['url_validacao'] ?? ''));
            if ($portal !== '' && (
                $atual === ''
                || str_contains($atual, 'host.docker.internal')
                || str_contains($atual, '/apps/libresign/')
            )) {
                if ($atual !== '' && str_contains($atual, '/apps/libresign/')) {
                    $assinatura['url_validacao_libresign'] = $atual;
                }
                $assinatura['url_validacao'] = $portal;
            }
        }

        if ($pedidoId <= 0 || $uuid === '') {
            return $assinatura;
        }
        if (! class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\PedidoAssinaturaService::class)) {
            return $assinatura;
        }

        try {
            $pedidos = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\PedidoAssinaturaService::class);
            $statusAtual = mb_strtolower(trim((string) ($assinatura['status_libresign'] ?? '')));
            if (! in_array($statusAtual, ['assinado', 'completo', '3'], true)) {
                $assinatura['_etapa_rotulo'] = PedidoCarrinho::rotuloEtapa($etapa);
                $assinatura = $this->concluirLibreSignSePendente($assinatura);
                unset($assinatura['_etapa_rotulo']);
            }

            $pedido = $pedidos->obter($pedidoId);
            $st = mb_strtolower(trim((string) ($pedido['status'] ?? '')));
            if ($st !== '') {
                $assinatura['status_libresign'] = $st;
            }

            // Espelha URL portal do visual, se existir.
            $meta = is_array($pedido['meta'] ?? null) ? $pedido['meta'] : [];
            $visual = is_array($meta['assinaturas_visuais'][0] ?? null) ? $meta['assinaturas_visuais'][0] : null;
            if (is_array($visual)) {
                $portalVisual = trim((string) ($visual['url_validacao_portal'] ?? ''));
                if ($portalVisual !== '') {
                    $assinatura['url_validacao'] = $portalVisual;
                }
                if (! empty($visual['url_validacao_libresign'])) {
                    $assinatura['url_validacao_libresign'] = $visual['url_validacao_libresign'];
                } elseif (! empty($visual['url_validacao']) && str_contains((string) $visual['url_validacao'], '/apps/libresign/')) {
                    $assinatura['url_validacao_libresign'] = $visual['url_validacao'];
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $assinatura;
    }

    private function concluirLibreSignSePendente(array $assinatura): array
    {
        $status = (string) ($assinatura['status_libresign'] ?? '');
        $uuid = trim((string) ($assinatura['libresign_uuid'] ?? ''));
        $pedidoId = (int) ($assinatura['pedido_assinatura_id'] ?? 0);
        if ($uuid === '' || in_array($status, ['assinado', 'completo', '3'], true)) {
            return $assinatura;
        }
        if (! class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\PedidoAssinaturaService::class)) {
            return $assinatura;
        }

        try {
            $pedidos = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\PedidoAssinaturaService::class);
            $pedido = $pedidoId > 0 ? $pedidos->obter($pedidoId) : [];
            $signatarios = is_array($pedido['signatarios'] ?? null) ? $pedido['signatarios'] : [];
            $account = '';
            $password = '';
            foreach ($signatarios as $s) {
                if (! is_array($s)) {
                    continue;
                }
                $account = trim((string) ($s['account'] ?? ''));
                $password = trim((string) ($s['password'] ?? ''));
                if ($account !== '') {
                    break;
                }
            }
            if ($account === '' && class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\UsuarioLibreSign::class)) {
                $usuarios = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\UsuarioLibreSign::class);
                $cred = $usuarios->credenciaisParaAssinar([
                    'login' => $assinatura['login'] ?? null,
                    'user_id' => $assinatura['responsavel_id'] ?? null,
                    'cargo' => $assinatura['cargo'] ?? $assinatura['_etapa_rotulo'] ?? 'Separação',
                    'nome' => $assinatura['responsavel'] ?? null,
                ], null);
                $account = trim((string) ($cred['account'] ?? ''));
                $password = trim((string) ($cred['password'] ?? ''));
            }
            if ($account === '') {
                return $assinatura;
            }
            if ($password === '' && class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\UsuarioLibreSign::class)) {
                $password = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\UsuarioLibreSign::class)->senhaDaConta($account);
            }

            $res = $pedidos->assinarUm($uuid, $account, $password, $account);
            if ($pedidoId > 0) {
                $pedidos->sincronizarAposAssinatura($pedidoId, $uuid);
                $pedido = $pedidos->obter($pedidoId);
                $assinatura['status_libresign'] = (string) ($pedido['status'] ?? ($res['ok'] ?? false ? 'assinado' : $status));
            } elseif (! empty($res['ok'])) {
                $assinatura['status_libresign'] = 'assinado';
            }

            // Portal sob a assinatura (código individual).
            $cod = trim((string) ($assinatura['codigo_validacao'] ?? ''));
            if ($cod !== '' && class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)) {
                $assinatura['url_validacao'] = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)
                    ->urlPublica($cod);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $assinatura;
    }

    /**
     * Gera o PDF institucional da nota (sem tocar no LibreSign).
     * Usado na pré-visualização /assinatura-digital/pedidos/{id}/ver|pdf.
     */
    public function pdfInstitucional(int $pedidoId): ?string
    {
        try {
            $pedido = PedidoCarrinho::obter($pedidoId);
        } catch (Throwable) {
            return null;
        }

        $payload = is_array($pedido['payload'] ?? null) ? $pedido['payload'] : [];
        $pdf = $this->gerarPdfNota($pedidoId, $payload);
        if ($pdf === null || $pdf === '' || ! str_starts_with($pdf, '%PDF') || str_contains($pdf, 'BT /F1 10 Tf')) {
            return null;
        }

        return $pdf;
    }

    public function regenerarPdfLibreSign(int $pedidoId, string $etapa): array
    {
        $vazio = ['ok' => false, 'pdf' => null, 'uuid' => null, 'pedido_assinatura_id' => null];
        if (! in_array($etapa, self::ETAPAS, true)) {
            return $vazio;
        }

        try {
            $pedido = PedidoCarrinho::obter($pedidoId);
        } catch (Throwable) {
            return $vazio;
        }

        $payload = is_array($pedido['payload'] ?? null) ? $pedido['payload'] : [];
        $assinatura = is_array($payload['assinaturas'][$etapa] ?? null) ? $payload['assinaturas'][$etapa] : [];
        $uuidAntigo = trim((string) ($assinatura['libresign_uuid'] ?? ''));
        $pedidoAssinaturaId = (int) ($assinatura['pedido_assinatura_id'] ?? 0);

        $pdf = $this->gerarPdfNota($pedidoId, $payload);
        if ($pdf === null || $pdf === '' || ! str_starts_with($pdf, '%PDF') || str_contains($pdf, 'BT /F1 10 Tf')) {
            return $vazio;
        }

        if (class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\LibreSign\LibreSignClient::class) && $uuidAntigo !== '') {
            try {
                $ok = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\LibreSign\LibreSignClient::class)
                    ->substituirPdf($uuidAntigo, $pdf, 'almox-nota-'.$pedidoId.'-'.$etapa.'.pdf');
                if ($ok) {
                    return [
                        'ok' => true,
                        'pdf' => $pdf,
                        'uuid' => $uuidAntigo,
                        'pedido_assinatura_id' => $pedidoAssinaturaId > 0 ? $pedidoAssinaturaId : null,
                    ];
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Sempre devolve o PDF visual mesmo se o LibreSign não aceitar substituição.
        if (! class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\AssinaturaOrquestrador::class)) {
            return [
                'ok' => true,
                'pdf' => $pdf,
                'uuid' => $uuidAntigo !== '' ? $uuidAntigo : null,
                'pedido_assinatura_id' => $pedidoAssinaturaId > 0 ? $pedidoAssinaturaId : null,
            ];
        }

        try {
            $responsavel = (string) ($assinatura['responsavel'] ?? 'sistema');
            $login = isset($assinatura['login']) ? (string) $assinatura['login'] : null;
            $signers = $this->signatariosAcumuladosAteEtapa($payload, $etapa, $responsavel, $login);

            $ls = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\AssinaturaOrquestrador::class)
                ->tentarSolicitarDeDocumento(
                    'Nota almoxarifado #'.$pedidoId,
                    $pdf,
                    $signers,
                    [
                        'modulo_origem' => 'almoxarifado-escolar',
                        'origem' => 'almox_nota_etapa',
                        'pedido_id' => $pedidoId,
                        'etapa' => $etapa,
                        'etapas_esperadas' => array_map(
                            static fn (string $e): string => PedidoCarrinho::rotuloEtapa($e),
                            self::ETAPAS,
                        ),
                        'assinar_imediatamente' => true,
                        'forcar_assinatura_imediata' => true,
                        'codigo_validacao_exportacao' => (string) ($payload['validacao']['codigo'] ?? ''),
                        'url_validacao_exportacao' => (string) ($payload['validacao']['url'] ?? ''),
                    ],
                    'almox-nota-'.$pedidoId.'.pdf',
                );

            $novoUuid = (string) ($ls['pedido']['libresign_uuid'] ?? '');
            $novoId = (int) ($ls['pedido']['id'] ?? 0);
            if ($ls === null || $novoUuid === '') {
                return [
                    'ok' => true,
                    'pdf' => $pdf,
                    'uuid' => null,
                    'pedido_assinatura_id' => $pedidoAssinaturaId > 0 ? $pedidoAssinaturaId : null,
                ];
            }

            $assinatura['libresign_uuid'] = $novoUuid;
            $assinatura['status_libresign'] = $ls['pedido']['status'] ?? 'aguardando';
            if ($pedidoAssinaturaId > 0) {
                $assinatura['pedido_assinatura_id'] = $pedidoAssinaturaId;
            } elseif ($novoId > 0) {
                $assinatura['pedido_assinatura_id'] = $novoId;
            }
            $payload['assinaturas'][$etapa] = $assinatura;
            PocRegistro::atualizar($pedidoId, $payload);

            if ($pedidoAssinaturaId > 0) {
                \Illuminate\Support\Facades\DB::table('assinatura_digital.pedido')
                    ->where('id', $pedidoAssinaturaId)
                    ->update([
                        'libresign_uuid' => $novoUuid,
                        'libresign_file_id' => $ls['pedido']['libresign_file_id'] ?? null,
                        'status' => 'aguardando',
                        'status_libresign' => $ls['pedido']['status_libresign'] ?? ($ls['pedido']['status'] ?? null),
                        'updated_at' => now(),
                    ]);
                if ($novoId > 0 && $novoId !== $pedidoAssinaturaId) {
                    \Illuminate\Support\Facades\DB::table('assinatura_digital.pedido')
                        ->where('id', $novoId)
                        ->update([
                            'status' => 'cancelado',
                            'updated_at' => now(),
                        ]);
                }
            }

            return [
                'ok' => true,
                'pdf' => $pdf,
                'uuid' => $novoUuid,
                'pedido_assinatura_id' => $pedidoAssinaturaId > 0 ? $pedidoAssinaturaId : ($novoId ?: null),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => true,
                'pdf' => $pdf,
                'uuid' => null,
                'pedido_assinatura_id' => $pedidoAssinaturaId > 0 ? $pedidoAssinaturaId : null,
            ];
        }
    }
    /**
     * @param  array<string,mixed>  $payload
     */
    /**
     * Quatro blocos de assinatura da nota (mesmo contrato visual da tela/PDF do almox).
     *
     * @return list<array{
     *   nome:string,
     *   cargo:string,
     *   quando:?string,
     *   status:string,
     *   codigo_validacao:?string,
     *   url_validacao:?string,
     *   etapa:string,
     *   concluida:bool,
     *   pedido_assinatura_id:?int
     * }>
     */
    public function assinantesParaExibicao(int $pedidoAlmoxId): array
    {
        try {
            $pedido = PedidoCarrinho::obter($pedidoAlmoxId);
        } catch (Throwable) {
            return [];
        }

        $payload = is_array($pedido['payload'] ?? null) ? $pedido['payload'] : [];
        $assin = is_array($payload['assinaturas'] ?? null) ? $payload['assinaturas'] : [];
        $out = [];

        foreach (self::ETAPAS as $etapa) {
            $a = is_array($assin[$etapa] ?? null) ? $assin[$etapa] : null;
            $concluida = $a !== null && trim((string) ($a['responsavel'] ?? '')) !== '';
            $cod = $concluida ? trim((string) ($a['codigo_validacao'] ?? '')) : '';
            $url = $concluida ? trim((string) ($a['url_validacao'] ?? '')) : '';
            if ($cod !== '' && (
                $url === ''
                || str_contains($url, 'host.docker.internal')
                || str_contains($url, '/apps/libresign/')
            )) {
                $url = class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)
                    ? app(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)->urlPublica($cod)
                    : url('/assinatura-digital/verificar/'.$cod);
            }

            $pedidoLs = $concluida ? (int) ($a['pedido_assinatura_id'] ?? 0) : 0;
            $statusLs = $concluida ? mb_strtolower(trim((string) ($a['status_libresign'] ?? ''))) : '';
            if ($concluida) {
                $status = in_array($statusLs, ['assinado', 'completo', '3'], true) || $pedidoLs > 0
                    ? 'Assinatura digital registrada'
                    : 'Assinado';
            } else {
                $status = 'Aguardando assinatura';
            }

            $out[] = [
                'nome' => $concluida ? (string) ($a['responsavel'] ?? '') : '',
                'cargo' => PedidoCarrinho::rotuloEtapa($etapa),
                'quando' => $concluida ? $this->formatarQuandoExibicao($a['quando'] ?? null) : null,
                'status' => $status,
                'codigo_validacao' => $cod !== '' ? $cod : null,
                'url_validacao' => $url !== '' ? $url : null,
                'etapa' => $etapa,
                'concluida' => $concluida,
                'pedido_assinatura_id' => $pedidoLs > 0 ? $pedidoLs : null,
            ];
        }

        return $out;
    }

    private function formatarQuandoExibicao(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\DataHora::class)) {
            return \iEducar\Packages\BuritiLagunaAmbiente\Support\DataHora::formatar($valor, false);
        }
        try {
            return \Carbon\Carbon::parse((string) $valor)
                ->timezone(config('app.timezone', 'America/Sao_Paulo'))
                ->format('d/m/Y H:i');
        } catch (Throwable) {
            return (string) $valor;
        }
    }

    /**
     * PDF institucional alinhado ao export `/almoxarifado-escolar/pedidos/{id}/nota/pdf`
     * (4 slots de assinatura, itens não atendidos, subtítulo completo).
     *
     * @param  array<string,mixed>  $payload
     */
    private function gerarPdfNota(int $pedidoId, array $payload): ?string
    {
        if (! class_exists(\iEducar\Packages\BuritiExportacaoUnificada\Services\EmissorDocumento::class)) {
            return null;
        }

        $status = PedidoCarrinho::normalizarStatus((string) ($payload['status'] ?? PedidoCarrinho::STATUS_SOLICITADO));
        $escola = (string) ($payload['escola'] ?? '—');
        $cabecalhos = ['#', 'SKU', 'Item', 'Qtd', 'Situação'];
        $linhas = [];
        foreach (array_values($payload['itens'] ?? []) as $i => $item) {
            $origem = (string) ($item['origem'] ?? 'catalogo');
            $linhas[] = [
                (string) ($i + 1),
                (string) ($item['sku'] ?? ''),
                (string) ($item['titulo'] ?? ''),
                (string) ($item['quantidade'] ?? 1),
                in_array($origem, ['sem_saldo', 'fora_catalogo'], true) ? 'Faltoso' : 'Atendido',
            ];
        }
        foreach (array_values($payload['itens_nao_atendidos'] ?? []) as $i => $item) {
            $linhas[] = [
                'N'.($i + 1),
                (string) ($item['sku'] ?? ''),
                (string) ($item['titulo'] ?? ''),
                (string) ($item['quantidade'] ?? 1),
                'Não atendido',
            ];
        }

        $assinaturas = [];
        foreach (self::ETAPAS as $et) {
            $a = is_array($payload['assinaturas'][$et] ?? null) ? $payload['assinaturas'][$et] : null;
            if ($a === null || trim((string) ($a['responsavel'] ?? '')) === '') {
                $assinaturas[] = [
                    'cargo' => PedidoCarrinho::rotuloEtapa($et),
                    'nome' => '',
                    'quando' => null,
                ];
                continue;
            }
            $assinaturas[] = [
                'cargo' => PedidoCarrinho::rotuloEtapa($et),
                'nome' => (string) $a['responsavel'],
                'quando' => $this->formatarQuandoExibicao($a['quando'] ?? null),
                'codigo_validacao' => $a['codigo_validacao'] ?? null,
                'url_validacao' => $a['url_validacao'] ?? null,
                'status' => ! empty($a['pedido_assinatura_id']) ? 'Assinatura digital registrada' : null,
            ];
        }

        $solicitante = trim((string) ($payload['solicitante'] ?? ''));
        $quandoSolicBruto = $payload['solicitado_em']
            ?? $payload['fechado_em']
            ?? ($payload['assinaturas']['aceite']['quando_raw'] ?? null)
            ?? ($payload['assinaturas']['aceite']['quando'] ?? null);
        if (($quandoSolicBruto === null || $quandoSolicBruto === '') && class_exists(PocRegistro::class)) {
            try {
                $quandoSolicBruto = PedidoCarrinho::obter($pedidoId)['created_at'] ?? null;
            } catch (Throwable) {
                $quandoSolicBruto = null;
            }
        }
        $quandoSolic = $this->formatarQuandoExibicao($quandoSolicBruto);
        $subtitulo = 'Pedido #'.$pedidoId
            .' · Escola: '.$escola
            .' · Status: '.PedidoCarrinho::rotuloStatus($status, $payload);
        if ($solicitante !== '') {
            $subtitulo .= "\nSolicitado por ".$solicitante
                .($quandoSolic !== '' && $quandoSolic !== '—' ? ' · '.$quandoSolic : '');
        } elseif (! empty($payload['fechado_em'])) {
            $subtitulo .= ' · Fechado em '.$this->formatarQuandoExibicao($payload['fechado_em']);
        }

        $tabela = new \iEducar\Packages\BuritiExportacaoUnificada\Support\TabelaExportacao(
            'Nota de material — Almoxarifado',
            $cabecalhos,
            $linhas,
            $subtitulo,
        );

        $validacao = is_array($payload['validacao'] ?? null) ? $payload['validacao'] : [];
        $codigoDoc = trim((string) ($validacao['codigo'] ?? ''));
        $urlDoc = trim((string) ($validacao['url'] ?? ''));
        if ($urlDoc === '' && $codigoDoc !== ''
            && class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)
        ) {
            $urlDoc = app(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)
                ->urlPublica($codigoDoc);
        }
        $qrDoc = $validacao['qr_data_uri'] ?? null;
        $validacaoExistente = ($codigoDoc !== '' && $urlDoc !== '')
            ? [
                'codigo' => $codigoDoc,
                'url' => $urlDoc,
                'qr_data_uri' => is_string($qrDoc) ? $qrDoc : null,
            ]
            : null;

        $prev = Config::get('exportacao.assinatura_digital.habilitado');
        Config::set('exportacao.assinatura_digital.habilitado', false);
        try {
            // Código do documento já registrado em garantirValidacaoDocumento — reutiliza no rodapé/QR.
            $emitidoPor = '';
            foreach (array_reverse($assinaturas) as $slot) {
                if (trim((string) ($slot['nome'] ?? '')) !== '') {
                    $emitidoPor = (string) $slot['nome'];
                    break;
                }
            }
            $arquivo = app(\iEducar\Packages\BuritiExportacaoUnificada\Services\EmissorDocumento::class)->emitir(
                $tabela,
                \iEducar\Packages\BuritiExportacaoUnificada\Support\Formato::Pdf,
                'almoxarifado-escolar',
                $emitidoPor !== '' ? $emitidoPor : 'sistema',
                false,
                $assinaturas,
                'almox-nota-'.$pedidoId,
                $validacaoExistente,
            );

            return $arquivo->conteudo;
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            Config::set('exportacao.assinatura_digital.habilitado', $prev);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{codigo:string,url:string,qr_data_uri:?string,via?:string}|null
     */
    private function registrarValidacao(string $tipo, array $payload): ?array
    {
        if (! class_exists(\iEducar\Packages\BuritiExportacaoUnificada\Services\ValidadorDocumentoBridge::class)) {
            return null;
        }

        try {
            $bridge = app(\iEducar\Packages\BuritiExportacaoUnificada\Services\ValidadorDocumentoBridge::class);
            if (! $bridge->habilitado()) {
                return null;
            }
            $reg = $bridge->registrar($tipo, $payload);
            if (($reg['codigo'] ?? '') === '') {
                return null;
            }

            return [
                'codigo' => (string) $reg['codigo'],
                'url' => (string) $reg['url'],
                'qr_data_uri' => $reg['qr_data_uri'] ?? null,
                'via' => (string) ($reg['via'] ?? ''),
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
