<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use RuntimeException;
use Throwable;

/**
 * Pedido com carrinho multi-item, fechamento e nota com assinaturas.
 */
final class PedidoCarrinho
{
    public const string TIPO = 'pedido';

    public const string STATUS_CARRINHO = 'carrinho';

    /** @deprecated use STATUS_SOLICITADO — mantido para pedidos legados */
    public const string STATUS_FECHADO = 'fechado';

    public const string STATUS_SOLICITADO = 'solicitado';

    public const string STATUS_ACEITO = 'aceito';

    public const string STATUS_SEPARACAO = 'separacao';

    public const string STATUS_SEPARADO = 'separado';

    public const string STATUS_EM_TRANSITO = 'em_transito';

    public const string STATUS_ENTREGUE = 'entregue';

    public const string STATUS_CONFERIDO = 'conferido';

    /**
     * Ciclo operacional (após o carrinho). Conferido = finalizado.
     *
     * @var list<string>
     */
    public const array ORDEM_CICLO = [
        self::STATUS_SOLICITADO,
        self::STATUS_ACEITO,
        self::STATUS_SEPARACAO,
        self::STATUS_SEPARADO,
        self::STATUS_EM_TRANSITO,
        self::STATUS_ENTREGUE,
        self::STATUS_CONFERIDO,
    ];

    /**
     * Etapas com assinatura formal (PDF / verificação pública).
     * Separação e Em trânsito são só operacionais (avaliação do sistema).
     *
     * @var list<string>
     */
    public const array ETAPAS_ASSINATURA = ['aceite', 'entrega', 'conferencia'];

    /**
     * @return array{id:int,payload:array<string,mixed>}
     */
    public static function abrir(
        string $escola,
        ?string $solicitante = null,
        ?int $solicitanteId = null,
        ?string $setor = null,
    ): array {
        $escola = trim($escola);
        if ($escola === '') {
            throw new RuntimeException('Escola é obrigatória.');
        }
        $setor = $setor !== null ? trim($setor) : null;
        if ($setor === '') {
            $setor = null;
        }

        $payload = [
            'escola' => $escola,
            'setor' => $setor,
            'solicitante' => $solicitante,
            'solicitante_id' => $solicitanteId,
            'status' => self::STATUS_CARRINHO,
            'itens' => [],
            'assinaturas' => [
                'aceite' => null,
                'separacao' => null,
                'entrega' => null,
                'conferencia' => null,
            ],
            'baixa_estoque' => false,
            'parcial' => false,
            'bloqueio_entrega' => false,
            'itens_nao_atendidos' => [],
            'nota_parcial' => null,
            'fechado_em' => null,
        ];

        $titulo = $escola.($setor ? ' / '.$setor : '');
        $id = PocRegistro::gravar(self::TIPO, $titulo, self::STATUS_CARRINHO, $payload);

        return ['id' => $id, 'payload' => $payload];
    }

    /**
     * @return array{id:int,tipo:string,titulo:string,meta:?string,payload:array<string,mixed>,created_at:?string}
     */
    public static function obter(int $id): array
    {
        $pedido = PocRegistro::buscar($id);
        if ($pedido === null || $pedido['tipo'] !== self::TIPO) {
            throw new RuntimeException("Pedido #{$id} não encontrado.");
        }

        $pedido['payload'] = self::normalizarPayload($pedido['payload']);

        return $pedido;
    }

    /**
     * @param  'catalogo'|'sem_saldo'|'fora_catalogo'  $origem
     */
    public static function adicionarItem(
        int $pedidoId,
        string $sku,
        string $titulo,
        int $quantidade,
        string $origem = 'catalogo',
        ?int $alertaId = null,
        ?string $foto = null,
    ): void {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        if (($p['status'] ?? '') !== self::STATUS_CARRINHO) {
            throw new RuntimeException('Só é possível incluir itens em pedidos em aberto (carrinho).');
        }

        $sku = strtoupper(trim($sku));
        $titulo = trim($titulo);
        $quantidade = max(1, $quantidade);
        $foto = $foto !== null && trim($foto) !== '' ? trim($foto) : null;
        if ($titulo === '') {
            throw new RuntimeException('Informe o item (SKU/título).');
        }
        if ($sku === '') {
            $sku = 'NOVO-'.substr(md5($titulo.microtime(true)), 0, 6);
        }
        if (! in_array($origem, ['catalogo', 'sem_saldo', 'fora_catalogo'], true)) {
            $origem = 'catalogo';
        }

        /** @var list<array{sku:string,titulo:string,quantidade:int,origem?:string,alerta_id?:?int,foto?:?string}> $itens */
        $itens = $p['itens'];
        $achou = false;
        foreach ($itens as $i => $item) {
            if (strtoupper($item['sku']) === $sku && ($item['origem'] ?? 'catalogo') === $origem) {
                $itens[$i]['quantidade'] = (int) $item['quantidade'] + $quantidade;
                $itens[$i]['titulo'] = $titulo;
                if ($foto !== null) {
                    $itens[$i]['foto'] = $foto;
                }
                $achou = true;
                break;
            }
        }
        if (! $achou) {
            $linha = [
                'sku' => $sku,
                'titulo' => $titulo,
                'quantidade' => $quantidade,
                'origem' => $origem,
                'alerta_id' => $alertaId,
            ];
            if ($foto !== null) {
                $linha['foto'] = $foto;
            }
            $itens[] = $linha;
        }

        $p['itens'] = $itens;
        $rotulo = ($p['escola'] ?? '').(! empty($p['setor']) ? ' / '.$p['setor'] : '');
        PocRegistro::atualizar($pedidoId, $p, $rotulo !== '' ? $rotulo : $p['escola'], self::STATUS_CARRINHO.' · '.count($itens).' item(ns)');
    }

    public static function removerItem(int $pedidoId, string $sku): void
    {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        if (($p['status'] ?? '') !== self::STATUS_CARRINHO) {
            throw new RuntimeException('Só é possível remover itens do carrinho aberto.');
        }

        $sku = strtoupper(trim($sku));
        $p['itens'] = array_values(array_filter(
            $p['itens'],
            static fn (array $i): bool => strtoupper($i['sku']) !== $sku
        ));
        PocRegistro::atualizar($pedidoId, $p, $p['escola'], self::STATUS_CARRINHO.' · '.count($p['itens']).' item(ns)');
    }

    public static function fechar(int $pedidoId): void
    {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        if (($p['status'] ?? '') !== self::STATUS_CARRINHO) {
            throw new RuntimeException('Pedido já foi solicitado.');
        }
        if ($p['itens'] === []) {
            throw new RuntimeException('Inclua ao menos um item no carrinho antes de fechar.');
        }

        $p['status'] = self::STATUS_SOLICITADO;
        $p['fechado_em'] = now()->toDateTimeString();
        $p['solicitado_em'] = $p['fechado_em'];
        PocRegistro::atualizar($pedidoId, $p, $p['escola'], self::STATUS_SOLICITADO.' · '.count($p['itens']).' item(ns)');

        self::notificarEtapaPendente(
            $pedidoId,
            'aceite',
            $p,
            'Almoxarifado: pedido #'.$pedidoId.' solicitado — aguardando Aceite — '.$p['escola'].' ('.count($p['itens']).' item(ns)).',
        );
    }

    /**
     * @param  'aceite'|'entrega'|'conferencia'  $etapa
     */
    public static function assinar(
        int $pedidoId,
        string $etapa,
        string $responsavel,
        ?string $observacao = null,
        ?int $responsavelId = null,
        ?string $login = null,
    ): void {
        if (! in_array($etapa, self::ETAPAS_ASSINATURA, true)) {
            throw new RuntimeException('Etapa de assinatura inválida.');
        }

        $responsavel = trim($responsavel);
        if ($responsavel === '') {
            throw new RuntimeException('Responsável da assinatura não identificado.');
        }

        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        $status = self::normalizarStatus((string) ($p['status'] ?? ''));

        if ($status === self::STATUS_CARRINHO) {
            throw new RuntimeException('Feche o pedido antes de coletar assinaturas.');
        }
        if (self::finalizado($p)) {
            throw new RuntimeException('Pedido já conferido e finalizado — não é possível alterar assinaturas ou emitir novas confirmações.');
        }

        $ordem = ['aceite' => 0, 'entrega' => 1, 'conferencia' => 2];
        $idx = $ordem[$etapa] ?? 0;
        foreach (self::ETAPAS_ASSINATURA as $prev) {
            if (($ordem[$prev] ?? 99) >= $idx) {
                break;
            }
            if (empty($p['assinaturas'][$prev])) {
                throw new RuntimeException('Conclua a etapa anterior ('.self::rotuloEtapa($prev).') antes.');
            }
        }

        // Entrega / conferência bloqueadas enquanto houver itens faltosos no pedido.
        if (in_array($etapa, ['entrega', 'conferencia'], true) && self::bloqueiaEntrega($p)) {
            $n = count(self::itensFaltosos($p));
            throw new RuntimeException(
                "Pedido parcial: {$n} item(ns) sem disponibilidade. "
                .'O almoxarifado precisa retirar os faltosos (viram «não atendidos») antes da entrega/conferência.'
            );
        }

        // Entrega só após Em trânsito (separação operacional + remessa/QR).
        if ($etapa === 'entrega' && ! self::emTransito($p)) {
            throw new RuntimeException(
                'Registre a separação e marque Em trânsito (remessa) antes da assinatura de entrega.'
            );
        }

        $agora = now();
        $p['assinaturas'][$etapa] = [
            'responsavel' => $responsavel,
            'responsavel_id' => $responsavelId,
            'login' => $login,
            'observacao' => $observacao,
            'quando' => $agora->format('d/m/Y H:i:s'),
            'quando_iso' => $agora->toIso8601String(),
            'quando_raw' => $agora->toDateTimeString(),
        ];

        // Aceite com faltas: marca parcial e bloqueia etapas finais até o almox tratar.
        if ($etapa === 'aceite') {
            $faltosos = self::itensFaltosos($p);
            if ($faltosos !== []) {
                $p['parcial'] = true;
                $p['bloqueio_entrega'] = true;
                $p['nota_parcial'] = [
                    'motivo' => 'Itens sem disponibilidade no aceite',
                    'faltosos' => count($faltosos),
                    'quando' => $agora->toDateTimeString(),
                    'por' => $responsavel,
                ];
                $p['status'] = self::STATUS_ACEITO;
            } else {
                $p['parcial'] = (bool) ($p['parcial'] ?? false);
                $p['bloqueio_entrega'] = false;
                // Aceite ok → entra na fase operacional de separação (sem assinatura).
                $p['status'] = self::STATUS_SEPARACAO;
            }
        } else {
            $p['status'] = match ($etapa) {
                'entrega' => self::STATUS_ENTREGUE,
                'conferencia' => self::STATUS_CONFERIDO,
                default => $status,
            };
        }

        if ($etapa === 'conferencia') {
            $p['finalizado_em'] = $agora->toDateTimeString();
            $p['finalizado'] = true;
            $p['status'] = self::STATUS_CONFERIDO;
        }

        if ($etapa === 'entrega' && empty($p['baixa_estoque'])) {
            $semCobertura = self::itensSemCobertura($p);
            if ($semCobertura !== []) {
                $partes = array_map(
                    static fn (array $i): string => $i['sku'].': disponível '.$i['saldo'].', pedido '.$i['quantidade'],
                    $semCobertura
                );
                throw new RuntimeException(
                    'Saldo insuficiente para a entrega ('.implode('; ', $partes).'). '
                    .'Entregue só o disponível e gere a compra da falta, ou retire o item para seguir com os demais.'
                );
            }

            foreach ($p['itens'] as $item) {
                // Só baixa itens atendidos do catálogo (faltosos já saíram da lista).
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
                    'Baixa automática na entrega do pedido #'.$pedidoId,
                    $responsavel,
                    $responsavelId,
                );
            }
            $p['baixa_estoque'] = true;
            $p['baixa_em'] = now()->toDateTimeString();
        }

        // Autenticidade do documento + da etapa; LibreSign quando módulo ativo.
        try {
            $p = (new NotaAssinaturaDigital())->aposAssinarEtapa(
                $pedidoId,
                $etapa,
                $p,
                $responsavel,
                $login,
            );
        } catch (Throwable $e) {
            report($e);
        }

        $metaExtra = ! empty($p['bloqueio_entrega']) ? ' · parcial/bloqueado' : '';
        if (! empty($p['itens_nao_atendidos'])) {
            $metaExtra .= ' · '.count($p['itens_nao_atendidos']).' não atendido(s)';
        }
        PocRegistro::atualizar(
            $pedidoId,
            $p,
            $p['escola'],
            $p['status'].' · '.count($p['itens']).' item(ns)'.$metaExtra
        );

        $proxima = self::proximaEtapaPendente($p);
        if ($proxima !== null) {
            self::notificarEtapaPendente(
                $pedidoId,
                $proxima,
                $p,
                'Almoxarifado: pedido #'.$pedidoId.' aguardando '
                    .self::rotuloEtapa($proxima).' — '.$p['escola'].'.',
            );
        } elseif ($etapa === 'aceite' && ($p['status'] ?? '') === self::STATUS_SEPARACAO
            && class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)
            && class_exists(\iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::class)
        ) {
            // Separação é operacional (sem assinatura): avisa quem opera o depósito.
            $niveis = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::niveisAssinatura('separacao');
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraNiveis(
                $niveis,
                'Almoxarifado: pedido #'.$pedidoId.' pronto para separação — '.$p['escola'].'.',
                '/almoxarifado-escolar/pedidos/'.$pedidoId.'/nota',
            );
        }
    }

    /**
     * Itens que impedem entrega/conferência (sem saldo ou fora do catálogo).
     *
     * @param  array<string,mixed>  $payload
     * @return list<array{sku:string,titulo:string,quantidade:int,origem:string,alerta_id?:?int}>
     */
    public static function itensFaltosos(array $payload): array
    {
        $out = [];
        foreach ($payload['itens'] ?? [] as $item) {
            $origem = (string) ($item['origem'] ?? 'catalogo');
            if (in_array($origem, ['sem_saldo', 'fora_catalogo'], true)) {
                $out[] = [
                    'sku' => (string) ($item['sku'] ?? ''),
                    'titulo' => (string) ($item['titulo'] ?? ''),
                    'quantidade' => (int) ($item['quantidade'] ?? 1),
                    'origem' => $origem,
                    'alerta_id' => isset($item['alerta_id']) ? (int) $item['alerta_id'] : null,
                ];
            }
        }

        return $out;
    }

    /** @param  array<string,mixed>  $payload */
    public static function bloqueiaEntrega(array $payload): bool
    {
        if (! empty($payload['bloqueio_entrega'])) {
            return self::itensFaltosos($payload) !== [];
        }

        // Aceito/separado com faltas ainda na lista → bloqueia mesmo sem flag legado.
        $status = (string) ($payload['status'] ?? '');
        if (in_array(self::normalizarStatus($status), [
            self::STATUS_SOLICITADO,
            self::STATUS_ACEITO,
            self::STATUS_SEPARACAO,
            self::STATUS_SEPARADO,
            self::STATUS_EM_TRANSITO,
        ], true)) {
            return self::itensFaltosos($payload) !== [];
        }

        return false;
    }

    /**
     * Almoxarifado retira um item faltoso: sai do pedido e vai para «não atendidos»
     * (nota parcial / providência futura). Libera entrega se não restar falta.
     */
    public static function retirarFaltoso(
        int $pedidoId,
        string $sku,
        string $responsavel,
        ?string $observacao = null,
        ?int $responsavelId = null,
    ): void {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        $status = (string) ($p['status'] ?? '');

        if ($status === self::STATUS_CARRINHO) {
            throw new RuntimeException('No carrinho, remova o item pela lista do pedido.');
        }
        if (in_array($status, [self::STATUS_ENTREGUE, self::STATUS_CONFERIDO], true)) {
            throw new RuntimeException('Pedido já entregue/conferido — não é possível alterar itens.');
        }

        $sku = strtoupper(trim($sku));
        $faltoso = null;
        $restantes = [];
        foreach ($p['itens'] as $item) {
            $origem = (string) ($item['origem'] ?? 'catalogo');
            if (strtoupper((string) $item['sku']) === $sku && in_array($origem, ['sem_saldo', 'fora_catalogo'], true)) {
                $faltoso = $item;
                continue;
            }
            $restantes[] = $item;
        }

        if ($faltoso === null) {
            throw new RuntimeException('Item faltoso não encontrado neste pedido (SKU '.$sku.').');
        }

        if ($restantes === []) {
            throw new RuntimeException(
                'Não é possível retirar o único item do pedido. Cancele o pedido ou aguarde atendimento do alerta de compra.'
            );
        }

        $naoAtendidos = is_array($p['itens_nao_atendidos'] ?? null) ? $p['itens_nao_atendidos'] : [];
        $naoAtendidos[] = [
            'sku' => (string) $faltoso['sku'],
            'titulo' => (string) $faltoso['titulo'],
            'quantidade' => (int) ($faltoso['quantidade'] ?? 1),
            'origem' => (string) ($faltoso['origem'] ?? 'sem_saldo'),
            'alerta_id' => $faltoso['alerta_id'] ?? null,
            'retirado_em' => now()->toDateTimeString(),
            'retirado_por' => $responsavel,
            'retirado_por_id' => $responsavelId,
            'observacao' => $observacao,
            'providencia' => 'nota_parcial',
        ];

        $p['itens'] = $restantes;
        $p['itens_nao_atendidos'] = $naoAtendidos;
        $p['parcial'] = true;

        $aindaFalta = self::itensFaltosos($p) !== [];
        $p['bloqueio_entrega'] = $aindaFalta;
        $p['nota_parcial'] = [
            'motivo' => $aindaFalta
                ? 'Ainda há itens faltosos — entrega bloqueada'
                : 'Faltosos retirados; pedido parcial liberado para entrega',
            'nao_atendidos' => count($naoAtendidos),
            'atendidos' => count($restantes),
            'quando' => now()->toDateTimeString(),
            'por' => $responsavel,
            'ultima_retirada' => (string) $faltoso['titulo'],
        ];

        // Aceite já feito e liberado → avança para a fase de separação.
        if (! $aindaFalta
            && ! empty($p['assinaturas']['aceite'])
            && empty($p['assinaturas']['separacao'])
            && self::normalizarStatus((string) ($p['status'] ?? '')) === self::STATUS_ACEITO) {
            $p['status'] = self::STATUS_SEPARACAO;
        }

        PocRegistro::atualizar(
            $pedidoId,
            $p,
            $p['escola'],
            $p['status'].' · '.count($restantes).' atendido(s) · '.count($naoAtendidos).' não atendido(s)'
            .($aindaFalta ? ' · bloqueado' : ' · liberado')
        );

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
                \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
                'Almoxarifado: pedido #'.$pedidoId.' — item «'.$faltoso['titulo'].'» marcado como não atendido (nota parcial).',
                '/almoxarifado-escolar/pedidos/'.$pedidoId,
            );
        }
    }

    /**
     * Itens de catálogo cuja quantidade pedida passa do saldo atual.
     * Não consulta saldo depois da baixa nem no carrinho (lá a inclusão já limita).
     *
     * @param  array<string,mixed>  $payload
     * @return list<array{sku:string,titulo:string,quantidade:int,saldo:int,falta:int,atende:int}>
     */
    public static function itensSemCobertura(array $payload): array
    {
        if (! empty($payload['baixa_estoque']) || self::finalizado($payload)) {
            return [];
        }
        if ((string) ($payload['status'] ?? '') === self::STATUS_CARRINHO) {
            return [];
        }

        $out = [];
        foreach ($payload['itens'] ?? [] as $item) {
            if ((string) ($item['origem'] ?? 'catalogo') !== 'catalogo') {
                continue;
            }
            $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $qtd = max(1, (int) ($item['quantidade'] ?? 1));
            $saldo = MovimentoEstoque::saldoAtual($sku) ?? 0;
            if ($qtd <= $saldo) {
                continue;
            }
            $out[] = [
                'sku' => $sku,
                'titulo' => (string) ($item['titulo'] ?? $sku),
                'quantidade' => $qtd,
                'saldo' => $saldo,
                'falta' => $qtd - $saldo,
                'atende' => $saldo,
            ];
        }

        return $out;
    }

    /**
     * Ajusta um item de catálogo sem saldo bastante para a entrega:
     * atende o disponível e manda a falta para compra, ou retira o item inteiro.
     * Os demais itens permanecem para a entrega parcial.
     *
     * @param  'atender_disponivel'|'retirar'  $modo
     * @return array{alerta_id:int,mensagem:string,liberado:bool}
     */
    public static function resolverCobertura(
        int $pedidoId,
        string $sku,
        string $modo,
        string $responsavel,
        ?string $observacao = null,
        ?int $responsavelId = null,
    ): array {
        if (! in_array($modo, ['atender_disponivel', 'retirar'], true)) {
            throw new RuntimeException('Modo de cobertura inválido.');
        }

        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        $status = (string) ($p['status'] ?? '');
        if ($status === self::STATUS_CARRINHO) {
            throw new RuntimeException('No carrinho, ajuste a quantidade ou remova o item antes de fechar.');
        }
        if (in_array($status, [self::STATUS_ENTREGUE, self::STATUS_CONFERIDO], true) || ! empty($p['baixa_estoque'])) {
            throw new RuntimeException('Pedido já teve baixa de estoque — a cobertura não pode mais ser alterada.');
        }

        $sku = strtoupper(trim($sku));
        $idx = null;
        $alvo = null;
        foreach ($p['itens'] as $i => $item) {
            if (strtoupper((string) ($item['sku'] ?? '')) === $sku
                && (string) ($item['origem'] ?? 'catalogo') === 'catalogo') {
                $idx = $i;
                $alvo = $item;
                break;
            }
        }
        if ($alvo === null || $idx === null) {
            throw new RuntimeException('Item de catálogo '.$sku.' não está neste pedido.');
        }

        $qtd = max(1, (int) ($alvo['quantidade'] ?? 1));
        $saldo = MovimentoEstoque::saldoAtual($sku) ?? 0;
        if ($qtd <= $saldo) {
            throw new RuntimeException("{$sku} já cabe no saldo ({$saldo} disponível, {$qtd} no pedido).");
        }

        $titulo = (string) ($alvo['titulo'] ?? $sku);
        $falta = $qtd - $saldo;
        if ($modo === 'atender_disponivel' && $saldo < 1) {
            $modo = 'retirar';
        }

        if ($modo === 'retirar') {
            $restantes = $p['itens'];
            unset($restantes[$idx]);
            $restantes = array_values($restantes);
            if ($restantes === []) {
                throw new RuntimeException('Este é o único item do pedido. Entregue o disponível, se houver saldo, ou cancele o pedido.');
            }
            $p['itens'] = $restantes;
            $qtdAlerta = $qtd;
            $mensagem = "{$sku} retirado do pedido ({$qtd} un.). Compra gerada. Os demais itens seguem para entrega parcial.";
        } else {
            $p['itens'][$idx]['quantidade'] = $saldo;
            $qtdAlerta = $falta;
            $mensagem = "{$sku}: a entrega fica com {$saldo} un. e a compra cobre as {$falta} que faltam. Os demais itens podem ser entregues.";
        }

        $alertaId = AlertaCompra::registrar([
            'sku' => $sku,
            'titulo' => $titulo,
            'quantidade' => $qtdAlerta,
            'motivo' => 'cobertura',
            'escola' => (string) ($p['escola'] ?? ''),
            'setor' => $p['setor'] ?? null,
            'pedido_id' => $pedidoId,
            'solicitante' => $p['solicitante'] ?? $responsavel,
            'solicitante_id' => $p['solicitante_id'] ?? $responsavelId,
        ]);

        $naoAtendidos = is_array($p['itens_nao_atendidos'] ?? null) ? $p['itens_nao_atendidos'] : [];
        $naoAtendidos[] = [
            'sku' => $sku,
            'titulo' => $titulo,
            'quantidade' => $qtdAlerta,
            'origem' => 'sem_saldo',
            'alerta_id' => $alertaId,
            'retirado_em' => now()->toDateTimeString(),
            'retirado_por' => $responsavel,
            'retirado_por_id' => $responsavelId,
            'observacao' => $observacao,
            'providencia' => 'compra',
            'motivo' => $modo === 'retirar' ? 'item_retirado_sem_cobertura' : 'cobertura_parcial',
            'saldo_no_momento' => $saldo,
            'quantidade_pedida' => $qtd,
        ];

        $p['itens_nao_atendidos'] = $naoAtendidos;
        $p['parcial'] = true;
        $ainda = self::itensFaltosos($p) !== [] || self::itensSemCobertura($p) !== [];
        $p['bloqueio_entrega'] = $ainda;
        $p['nota_parcial'] = [
            'motivo' => $ainda
                ? 'Ainda há itens sem cobertura ou faltosos — entrega bloqueada'
                : 'Cobertura ajustada; pedido parcial liberado para entrega dos itens atendidos',
            'nao_atendidos' => count($naoAtendidos),
            'quando' => now()->toDateTimeString(),
            'por' => $responsavel,
            'alerta_id' => $alertaId,
        ];

        PocRegistro::atualizar(
            $pedidoId,
            $p,
            (string) ($p['escola'] ?? ''),
            $p['status'].' · '.count($p['itens']).' no pedido · '.count($naoAtendidos).' p/ compra'
            .($ainda ? ' · bloqueado' : ' · liberado')
        );

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
                \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
                'Almoxarifado: pedido #'.$pedidoId.' — '.$mensagem,
                '/almoxarifado-escolar/pedidos/'.$pedidoId.'/nota',
            );
        }

        return ['alerta_id' => $alertaId, 'mensagem' => $mensagem, 'liberado' => ! $ainda];
    }


    /**
     * Próxima etapa de assinatura ainda vazia (respeita bloqueio de entrega).
     *
     * @param  array<string,mixed>  $payload
     */
    public static function proximaEtapaPendente(array $payload): ?string
    {
        if (self::finalizado($payload)) {
            return null;
        }
        foreach (self::ETAPAS_ASSINATURA as $etapa) {
            if (! empty($payload['assinaturas'][$etapa])) {
                continue;
            }
            if (in_array($etapa, ['entrega', 'conferencia'], true) && self::bloqueiaEntrega($payload)) {
                return null;
            }
            // Entrega aguarda Em trânsito (separação / remessa são operacionais).
            if ($etapa === 'entrega' && ! self::emTransito($payload)) {
                return null;
            }

            return $etapa;
        }

        return null;
    }

    /**
     * Avisa todos os usuários com permissão na etapa (várias pessoas podem separar/entregar).
     *
     * @param  array<string,mixed>  $payload
     */
    public static function notificarEtapaPendente(
        int $pedidoId,
        string $etapa,
        array $payload,
        ?string $texto = null,
    ): int {
        if (! in_array($etapa, self::ETAPAS_ASSINATURA, true)) {
            return 0;
        }
        if (! class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            return 0;
        }
        if (! class_exists(\iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::class)) {
            return 0;
        }

        $escola = (string) ($payload['escola'] ?? '');
        $texto = $texto ?? ('Almoxarifado: documento aguardando '
            .self::rotuloEtapa($etapa).' no pedido #'.$pedidoId
            .($escola !== '' ? ' — '.$escola : '').'.');
        $link = '/almoxarifado-escolar/pedidos/'.$pedidoId.'/nota';
        $niveis = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado::niveisAssinatura($etapa);

        return \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraNiveis($niveis, $texto, $link);
    }

    public static function rotuloEtapa(string $etapa): string
    {
        return match ($etapa) {
            'aceite' => 'Aceite',
            'separacao' => 'Separação',
            'entrega' => 'Entrega',
            'conferencia' => 'Conferência',
            default => $etapa,
        };
    }

    /** Normaliza status legado (fechado → solicitado). */
    public static function normalizarStatus(string $status): string
    {
        return $status === self::STATUS_FECHADO ? self::STATUS_SOLICITADO : $status;
    }

    /**
     * Separação operacional (sem assinatura formal): material separado no depósito.
     */
    public static function marcarSeparado(int $pedidoId, ?string $responsavel = null): void
    {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        $status = self::normalizarStatus((string) ($p['status'] ?? ''));
        if (self::finalizado($p) || $status === self::STATUS_CARRINHO) {
            return;
        }
        if (in_array($status, [
            self::STATUS_SEPARADO,
            self::STATUS_EM_TRANSITO,
            self::STATUS_ENTREGUE,
            self::STATUS_CONFERIDO,
        ], true)) {
            return;
        }
        if (empty($p['assinaturas']['aceite']) && ! in_array($status, [self::STATUS_ACEITO, self::STATUS_SEPARACAO], true)) {
            throw new RuntimeException('Conclua o aceite antes de marcar a separação.');
        }
        if (self::bloqueiaEntrega($p)) {
            throw new RuntimeException('Retire os itens faltosos antes de concluir a separação.');
        }

        $agora = now();
        $p['status'] = self::STATUS_SEPARADO;
        $p['separado_em'] = $agora->toDateTimeString();
        if ($responsavel !== null && trim($responsavel) !== '') {
            $p['separado_por'] = trim($responsavel);
        }
        // Marca operacional legada (não é assinatura do PDF).
        if (empty($p['assinaturas']['separacao'])) {
            $p['assinaturas']['separacao'] = [
                'responsavel' => $p['separado_por'] ?? 'sistema',
                'quando' => $agora->format('d/m/Y H:i:s'),
                'quando_raw' => $agora->toDateTimeString(),
                'via' => 'operacional',
            ];
        }
        PocRegistro::atualizar(
            $pedidoId,
            $p,
            $p['escola'] ?? null,
            self::STATUS_SEPARADO.' · '.count($p['itens'] ?? []).' item(ns)'
        );
    }

    /**
     * Marca o pedido como em trânsito (após separado, na emissão de remessa/QR)
     * e notifica quem solicitou os itens.
     */
    public static function marcarEmTransito(int $pedidoId): void
    {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        $status = self::normalizarStatus((string) ($p['status'] ?? ''));
        if (self::finalizado($p) || $status === self::STATUS_CARRINHO) {
            return;
        }
        if (in_array($status, [self::STATUS_ENTREGUE, self::STATUS_CONFERIDO, self::STATUS_EM_TRANSITO], true)) {
            return;
        }
        if (! in_array($status, [self::STATUS_SEPARADO, self::STATUS_SEPARACAO, self::STATUS_ACEITO], true)
            && empty($p['assinaturas']['aceite'])) {
            return;
        }

        // Se ainda está em separação, fecha o passo operacional ao emitir a remessa.
        if ($status === self::STATUS_SEPARACAO || $status === self::STATUS_ACEITO) {
            $p['separado_em'] = $p['separado_em'] ?? now()->toDateTimeString();
        }

        $jaEmTransito = ! empty($p['em_transito_em']);
        $p['status'] = self::STATUS_EM_TRANSITO;
        $p['em_transito_em'] = $p['em_transito_em'] ?? now()->toDateTimeString();
        PocRegistro::atualizar(
            $pedidoId,
            $p,
            $p['escola'] ?? null,
            self::STATUS_EM_TRANSITO.' · '.count($p['itens'] ?? []).' item(ns)'
        );

        if (! $jaEmTransito) {
            self::notificarSolicitanteEmTransito($pedidoId, $p);
        }
    }

    /**
     * Avisa o solicitante de que o material saiu (Em trânsito).
     *
     * @param  array<string,mixed>  $payload
     */
    public static function notificarSolicitanteEmTransito(int $pedidoId, array $payload): void
    {
        if (! class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            return;
        }
        $solicitanteId = (int) ($payload['solicitante_id'] ?? 0);
        $escola = (string) ($payload['escola'] ?? '');
        $texto = 'Almoxarifado: pedido #'.$pedidoId.' está em trânsito'
            .($escola !== '' ? ' — '.$escola : '')
            .'. Acompanhe a entrega e a conferência.';
        $link = '/almoxarifado-escolar/pedidos/'.$pedidoId;

        if ($solicitanteId > 0) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraUsuario($solicitanteId, $texto, $link);

            return;
        }

        \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
            \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
            $texto,
            $link,
        );
    }

    public static function rotuloStatus(string $status, ?array $payload = null): string
    {
        $status = self::normalizarStatus($status);
        $base = match ($status) {
            self::STATUS_CARRINHO => 'Carrinho',
            self::STATUS_SOLICITADO => 'Solicitado',
            self::STATUS_ACEITO => 'Aceite',
            self::STATUS_SEPARACAO => 'Separação',
            self::STATUS_SEPARADO => 'Separado',
            self::STATUS_EM_TRANSITO => 'Em trânsito',
            self::STATUS_ENTREGUE => 'Entregue',
            self::STATUS_CONFERIDO => 'Conferido',
            default => $status,
        };

        if ($payload !== null && self::bloqueiaEntrega($payload)) {
            return $base.' (parcial · bloqueado)';
        }
        if ($payload !== null && ! empty($payload['itens_nao_atendidos']) && $status !== self::STATUS_CONFERIDO) {
            return $base.' (parcial)';
        }
        if ($payload !== null && $status === self::STATUS_CONFERIDO && ! empty($payload['itens_nao_atendidos'])) {
            $pendentes = count(array_filter(
                $payload['itens_nao_atendidos'],
                static fn ($i): bool => empty($i['repedido_id'])
            ));
            if ($pendentes > 0) {
                return $base.' · '.$pendentes.' p/ reposição';
            }
        }

        return $base;
    }

    /**
     * Identidade visual do status (cor, ícone, classe CSS).
     *
     * @param  array<string,mixed>|null  $payload
     * @return array{status:string,rotulo:string,icone:string,cor:string,classe:string,tom:string,finalizado:bool}
     */
    public static function visualStatus(string $status, ?array $payload = null): array
    {
        $status = self::normalizarStatus($status);
        $bloqueado = $payload !== null && self::bloqueiaEntrega($payload);
        $mapa = match (true) {
            $bloqueado => [
                'icone' => 'fa-ban',
                'cor' => '#c0392b',
                'classe' => 'st-bloqueado',
                'tom' => 'fail',
            ],
            $status === self::STATUS_CARRINHO => [
                'icone' => 'fa-shopping-cart',
                'cor' => '#5b6b76',
                'classe' => 'st-carrinho',
                'tom' => 'warn',
            ],
            $status === self::STATUS_SOLICITADO => [
                'icone' => 'fa-inbox',
                'cor' => '#3d5a80',
                'classe' => 'st-solicitado',
                'tom' => 'warn',
            ],
            $status === self::STATUS_ACEITO => [
                'icone' => 'fa-thumbs-o-up',
                'cor' => '#1a7a4c',
                'classe' => 'st-aceito',
                'tom' => 'ok',
            ],
            $status === self::STATUS_SEPARACAO => [
                'icone' => 'fa-cube',
                'cor' => '#2a6f97',
                'classe' => 'st-separacao',
                'tom' => 'warn',
            ],
            $status === self::STATUS_SEPARADO => [
                'icone' => 'fa-cubes',
                'cor' => '#1b6ca8',
                'classe' => 'st-separado',
                'tom' => 'ok',
            ],
            $status === self::STATUS_EM_TRANSITO => [
                'icone' => 'fa-exchange',
                'cor' => '#0b6e4f',
                'classe' => 'st-transito',
                'tom' => 'ok',
            ],
            $status === self::STATUS_ENTREGUE => [
                'icone' => 'fa-truck',
                'cor' => '#0d7377',
                'classe' => 'st-entregue',
                'tom' => 'ok',
            ],
            $status === self::STATUS_CONFERIDO => [
                'icone' => 'fa-check-circle',
                'cor' => '#17663f',
                'classe' => 'st-conferido',
                'tom' => 'ok',
            ],
            default => [
                'icone' => 'fa-circle-o',
                'cor' => '#5b6b76',
                'classe' => 'st-outro',
                'tom' => 'warn',
            ],
        };

        return [
            'status' => $status,
            'rotulo' => self::rotuloStatus($status, $payload),
            'icone' => $mapa['icone'],
            'cor' => $mapa['cor'],
            'classe' => $mapa['classe'],
            'tom' => $mapa['tom'],
            'finalizado' => self::finalizado($payload ?? ['status' => $status]),
        ];
    }

    /** Pedido conferido = ciclo encerrado (sem novas etiquetas/confirmações). */
    public static function finalizado(array $payload): bool
    {
        return (string) ($payload['status'] ?? '') === self::STATUS_CONFERIDO
            || ! empty($payload['finalizado']);
    }

    /** Etiquetas / QR de remessa — só no ciclo ativo (antes da conferência). */
    public static function podeEmitirRemessa(array $payload): bool
    {
        $status = (string) ($payload['status'] ?? '');
        if ($status === self::STATUS_CARRINHO || self::finalizado($payload)) {
            return false;
        }

        return true;
    }

    /**
     * Já registrou saída do depósito (Em trânsito).
     *
     * @param  array<string,mixed>  $payload
     */
    public static function emTransito(array $payload): bool
    {
        $status = self::normalizarStatus((string) ($payload['status'] ?? ''));

        return $status === self::STATUS_EM_TRANSITO
            || ! empty($payload['em_transito_em'])
            || in_array($status, [self::STATUS_ENTREGUE, self::STATUS_CONFERIDO], true);
    }

    /**
     * Próxima ação operacional do depósito (sem assinatura formal).
     *
     * @param  array<string,mixed>  $payload
     * @return 'marcar_separado'|'emitir_remessa'|null
     */
    public static function proximaAcaoOperacional(array $payload): ?string
    {
        if (self::finalizado($payload) || self::bloqueiaEntrega($payload)) {
            return null;
        }

        $status = self::normalizarStatus((string) ($payload['status'] ?? ''));
        if ($status === self::STATUS_CARRINHO || $status === self::STATUS_SOLICITADO) {
            return null;
        }

        if (self::emTransito($payload)) {
            return null;
        }

        $temAceite = ! empty($payload['assinaturas']['aceite'])
            || in_array($status, [self::STATUS_ACEITO, self::STATUS_SEPARACAO, self::STATUS_SEPARADO], true);

        if (! $temAceite) {
            return null;
        }

        $separado = $status === self::STATUS_SEPARADO
            || ! empty($payload['separado_em']);

        if (! $separado) {
            return 'marcar_separado';
        }

        return 'emitir_remessa';
    }

    /** Confirmação de recebimento (QR) — até entrega; nunca após conferido. */
    public static function podeConfirmarRecebimento(array $payload): bool
    {
        if (self::finalizado($payload) || self::bloqueiaEntrega($payload)) {
            return false;
        }
        $status = (string) ($payload['status'] ?? '');

        $status = self::normalizarStatus($status);

        return in_array($status, [
            self::STATUS_SOLICITADO,
            self::STATUS_ACEITO,
            self::STATUS_SEPARACAO,
            self::STATUS_SEPARADO,
            self::STATUS_EM_TRANSITO,
            self::STATUS_ENTREGUE,
        ], true);
    }

    /**
     * Itens não atendidos ainda sem segundo pedido de reposição.
     *
     * @param  array<string,mixed>  $payload
     * @return list<array<string,mixed>>
     */
    public static function itensAguardandoReposicao(array $payload): array
    {
        $out = [];
        foreach ($payload['itens_nao_atendidos'] ?? [] as $item) {
            if (empty($item['repedido_id'])) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Após compra: gera 2º pedido (carrinho) só com itens não atendidos pendentes.
     * O pedido original permanece conferido/fechado — tratativa é o novo ciclo.
     *
     * @return array{id:int,payload:array<string,mixed>,itens:int}
     */
    public static function gerarPedidoReposicao(
        int $pedidoOrigemId,
        string $responsavel,
        ?int $responsavelId = null,
    ): array {
        $origem = self::obter($pedidoOrigemId);
        $p = $origem['payload'];

        if (! self::finalizado($p)) {
            throw new RuntimeException(
                'Conclua a conferência do pedido original antes de gerar o pedido de reposição dos itens não atendidos.'
            );
        }

        $pendentes = self::itensAguardandoReposicao($p);
        if ($pendentes === []) {
            throw new RuntimeException('Não há itens não atendidos aguardando reposição neste pedido.');
        }

        $aberto = self::abrir(
            (string) ($p['escola'] ?? ''),
            isset($p['solicitante']) ? (string) $p['solicitante'] : $responsavel,
            isset($p['solicitante_id']) ? (int) $p['solicitante_id'] : $responsavelId,
            isset($p['setor']) ? (string) $p['setor'] : null,
        );
        $novoId = (int) $aberto['id'];
        $novo = self::obter($novoId);
        $np = $novo['payload'];
        $np['origem_pedido_id'] = $pedidoOrigemId;
        $np['tipo_pedido'] = 'reposicao';
        $np['observacao_reposicao'] = 'Reposição dos itens não atendidos do pedido #'.$pedidoOrigemId
            .' (após compra / providência).';

        foreach ($pendentes as $item) {
            $np['itens'][] = [
                'sku' => (string) ($item['sku'] ?? 'REPO'),
                'titulo' => (string) ($item['titulo'] ?? 'Item reposição'),
                'quantidade' => max(1, (int) ($item['quantidade'] ?? 1)),
                'origem' => 'catalogo',
                'alerta_id' => $item['alerta_id'] ?? null,
                'de_pedido_origem' => $pedidoOrigemId,
                'reposicao' => true,
            ];
        }

        PocRegistro::atualizar(
            $novoId,
            $np,
            (string) ($np['escola'] ?? ''),
            'carrinho · reposição de #'.$pedidoOrigemId.' · '.count($pendentes).' item(ns)'
        );

        // Marca itens no pedido original como vinculados ao 2º pedido.
        foreach ($p['itens_nao_atendidos'] as $i => $item) {
            if (! empty($item['repedido_id'])) {
                continue;
            }
            $p['itens_nao_atendidos'][$i]['repedido_id'] = $novoId;
            $p['itens_nao_atendidos'][$i]['repedido_em'] = now()->toDateTimeString();
            $p['itens_nao_atendidos'][$i]['repedido_por'] = $responsavel;
        }
        $p['pedido_reposicao_id'] = $novoId;
        $p['nota_parcial'] = array_merge(is_array($p['nota_parcial'] ?? null) ? $p['nota_parcial'] : [], [
            'reposicao_pedido_id' => $novoId,
            'reposicao_em' => now()->toDateTimeString(),
            'reposicao_por' => $responsavel,
        ]);

        PocRegistro::atualizar(
            $pedidoOrigemId,
            $p,
            (string) ($p['escola'] ?? ''),
            ($p['status'] ?? '').' · reposição #'.$novoId
        );

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
                \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
                'Almoxarifado: pedido de reposição #'.$novoId.' gerado a partir dos não atendidos do #'.$pedidoOrigemId.'.',
                '/almoxarifado-escolar/pedidos/'.$novoId,
            );
        }

        return ['id' => $novoId, 'payload' => $np, 'itens' => count($pendentes)];
    }

    /**
     * Depois da entrada conferida: abre um pedido fechado só com o item comprado.
     * Ele entra no ciclo completo (aceite → separação → em trânsito → entrega → conferência).
     *
     * @param  array<string,mixed>  $alerta
     * @return array{id:int,payload:array<string,mixed>,itens:int}
     */
    public static function abrirPedidoDaCompra(array $alerta, string $responsavel, ?int $responsavelId = null): array
    {
        if (! empty($alerta['pedido_gerado_id'])) {
            $existente = self::obter((int) $alerta['pedido_gerado_id']);

            return ['id' => (int) $existente['id'], 'payload' => $existente['payload'], 'itens' => count($existente['payload']['itens'] ?? [])];
        }

        $escola = trim((string) ($alerta['escola'] ?? ''));
        if ($escola === '') {
            throw new RuntimeException('Alerta sem escola — não é possível abrir o pedido da compra.');
        }

        $sku = strtoupper(trim((string) ($alerta['sku'] ?? '')));
        $titulo = trim((string) ($alerta['titulo'] ?? $sku));
        $qtd = max(1, (int) ($alerta['quantidade'] ?? 1));
        if ($sku === '' || $titulo === '') {
            throw new RuntimeException('Alerta sem item para gerar o pedido.');
        }

        $origemId = (int) ($alerta['pedido_id'] ?? 0);
        $alertaId = isset($alerta['id']) ? (int) $alerta['id'] : null;
        $aberto = self::abrir(
            $escola,
            isset($alerta['solicitante']) ? (string) $alerta['solicitante'] : $responsavel,
            isset($alerta['solicitante_id']) ? (int) $alerta['solicitante_id'] : $responsavelId,
            isset($alerta['setor']) ? (string) $alerta['setor'] : null,
        );
        $novoId = (int) $aberto['id'];
        self::adicionarItem($novoId, $sku, $titulo, $qtd, 'catalogo', $alertaId);
        self::fechar($novoId);

        $novo = self::obter($novoId);
        $np = $novo['payload'];
        $np['tipo_pedido'] = 'reposicao_compra';
        $np['alerta_id'] = $alertaId;
        if ($origemId > 0) {
            $np['origem_pedido_id'] = $origemId;
        }
        $np['observacao_reposicao'] = 'Gerado após a conferência da entrada da compra'
            .($origemId > 0 ? ' do pedido #'.$origemId : '')
            .'. Segue o fluxo completo: aceite → separação → em trânsito → entrega → conferência.';
        PocRegistro::atualizar(
            $novoId,
            $np,
            $escola,
            self::STATUS_SOLICITADO.' · compra conferida · '.$qtd.' un.'
        );

        if ($origemId > 0) {
            self::vincularReposicaoNoOrigem($origemId, $novoId, $sku, $alertaId, $responsavel);
        }

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
                \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
                'Almoxarifado: pedido #'.$novoId.' gerado após a entrada da compra de «'.$titulo.'».',
                '/almoxarifado-escolar/pedidos/'.$novoId.'/nota',
            );
        }

        return ['id' => $novoId, 'payload' => $np, 'itens' => 1];
    }

    private static function vincularReposicaoNoOrigem(
        int $origemId,
        int $novoId,
        string $sku,
        ?int $alertaId,
        string $responsavel,
    ): void {
        try {
            $origem = self::obter($origemId);
        } catch (\Throwable) {
            return;
        }

        $p = $origem['payload'];
        $escolhido = null;
        foreach ($p['itens_nao_atendidos'] ?? [] as $i => $item) {
            if (! empty($item['repedido_id'])) {
                continue;
            }
            $mesmoAlerta = $alertaId !== null && (int) ($item['alerta_id'] ?? 0) === $alertaId;
            $mesmoSku = strtoupper((string) ($item['sku'] ?? '')) === $sku;
            if ($mesmoAlerta) {
                $escolhido = $i;
                break;
            }
            if ($mesmoSku && $escolhido === null) {
                $escolhido = $i;
            }
        }
        if ($escolhido === null) {
            return;
        }
        $p['itens_nao_atendidos'][$escolhido]['repedido_id'] = $novoId;
        $p['itens_nao_atendidos'][$escolhido]['repedido_em'] = now()->toDateTimeString();
        $p['itens_nao_atendidos'][$escolhido]['repedido_por'] = $responsavel;
        $p['pedido_reposicao_id'] = $novoId;
        PocRegistro::atualizar(
            $origemId,
            $p,
            (string) ($p['escola'] ?? ''),
            ($p['status'] ?? '').' · reposição #'.$novoId
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private static function normalizarPayload(array $payload): array
    {
        // Pedidos legados (1 item flat) → formato carrinho
        if (! isset($payload['itens']) || ! is_array($payload['itens'])) {
            $item = trim((string) ($payload['item'] ?? ''));
            $qtd = max(1, (int) ($payload['quantidade'] ?? 1));
            $payload['itens'] = $item !== '' ? [[
                'sku' => $item,
                'titulo' => $item,
                'quantidade' => $qtd,
            ]] : [];
        }

        $statusLegado = (string) ($payload['status'] ?? self::STATUS_CARRINHO);
        if ($statusLegado === 'Em separação') {
            $payload['status'] = self::STATUS_SEPARACAO;
        } elseif ($statusLegado === 'Entregue') {
            $payload['status'] = self::STATUS_ENTREGUE;
        } elseif ($statusLegado === self::STATUS_FECHADO) {
            $payload['status'] = self::STATUS_SOLICITADO;
        } elseif (! isset($payload['status'])) {
            $payload['status'] = self::STATUS_CARRINHO;
        }

        $payload['assinaturas'] = array_merge([
            'aceite' => null,
            'separacao' => null,
            'entrega' => null,
            'conferencia' => null,
        ], is_array($payload['assinaturas'] ?? null) ? $payload['assinaturas'] : []);

        $payload['baixa_estoque'] = (bool) ($payload['baixa_estoque'] ?? false);
        $payload['parcial'] = (bool) ($payload['parcial'] ?? false);
        $payload['bloqueio_entrega'] = (bool) ($payload['bloqueio_entrega'] ?? false);
        $payload['finalizado'] = (bool) ($payload['finalizado'] ?? false)
            || (string) ($payload['status'] ?? '') === self::STATUS_CONFERIDO;
        $payload['itens_nao_atendidos'] = is_array($payload['itens_nao_atendidos'] ?? null)
            ? $payload['itens_nao_atendidos']
            : [];
        $payload['escola'] = (string) ($payload['escola'] ?? '');
        $payload['setor'] = isset($payload['setor']) && trim((string) $payload['setor']) !== ''
            ? trim((string) $payload['setor'])
            : null;

        foreach ($payload['itens'] as $i => $item) {
            if (! is_array($item)) {
                $payload['itens'][$i] = [
                    'sku' => (string) $item,
                    'titulo' => (string) $item,
                    'quantidade' => 1,
                    'origem' => 'catalogo',
                ];
                continue;
            }
            $sku = strtoupper(trim((string) ($item['sku'] ?? $item['item'] ?? '')));
            $titulo = trim((string) ($item['titulo'] ?? $item['nome'] ?? $item['item'] ?? $sku));
            $payload['itens'][$i]['sku'] = $sku !== '' ? $sku : 'ITEM';
            $payload['itens'][$i]['titulo'] = $titulo !== '' ? $titulo : ($sku !== '' ? $sku : 'Item');
            $payload['itens'][$i]['quantidade'] = max(1, (int) ($item['quantidade'] ?? $item['qtd'] ?? 1));
            $payload['itens'][$i]['origem'] = (string) ($item['origem'] ?? 'catalogo');
        }

        // Mantém bloqueio coerente com faltas atuais.
        if (self::itensFaltosos($payload) !== []
            && in_array(self::normalizarStatus((string) ($payload['status'] ?? '')), [
                self::STATUS_SOLICITADO,
                self::STATUS_ACEITO,
                self::STATUS_SEPARACAO,
                self::STATUS_SEPARADO,
                self::STATUS_EM_TRANSITO,
            ], true)) {
            $payload['bloqueio_entrega'] = true;
            $payload['parcial'] = true;
        } elseif (self::itensFaltosos($payload) === [] && ! empty($payload['itens_nao_atendidos'])) {
            $payload['bloqueio_entrega'] = false;
            $payload['parcial'] = true;
        }

        // Reenquadra status ao ciclo atual (assinatura só em aceite/entrega/conferência).
        $payload = self::reenquadrarStatusCiclo($payload);

        return $payload;
    }

    /**
     * Alinha status legado ao ciclo: solicitado → aceite → separação → separado → em trânsito → entregue → conferido.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private static function reenquadrarStatusCiclo(array $payload): array
    {
        if (self::finalizado($payload)) {
            $payload['status'] = self::STATUS_CONFERIDO;

            return $payload;
        }

        $assin = is_array($payload['assinaturas'] ?? null) ? $payload['assinaturas'] : [];
        $tem = static fn (string $e): bool => is_array($assin[$e] ?? null)
            && trim((string) ($assin[$e]['responsavel'] ?? '')) !== '';

        $status = self::normalizarStatus((string) ($payload['status'] ?? self::STATUS_CARRINHO));
        if ($status === self::STATUS_CARRINHO) {
            return $payload;
        }

        // Prioridade: assinaturas formais e marcas operacionais.
        if ($tem('conferencia')) {
            $payload['status'] = self::STATUS_CONFERIDO;
            $payload['finalizado'] = true;
        } elseif ($tem('entrega')) {
            $payload['status'] = self::STATUS_ENTREGUE;
        } elseif (! empty($payload['em_transito_em']) || $status === self::STATUS_EM_TRANSITO) {
            $payload['status'] = self::STATUS_EM_TRANSITO;
        } elseif ($tem('separacao') || ! empty($payload['separado_em']) || $status === self::STATUS_SEPARADO) {
            $payload['status'] = self::STATUS_SEPARADO;
        } elseif ($tem('aceite')) {
            // Aceite feito → fase operacional de separação (salvo se já avançou).
            if (! in_array($status, [
                self::STATUS_SEPARACAO,
                self::STATUS_SEPARADO,
                self::STATUS_EM_TRANSITO,
                self::STATUS_ENTREGUE,
            ], true)) {
                $payload['status'] = ! empty($payload['bloqueio_entrega'])
                    ? self::STATUS_ACEITO
                    : self::STATUS_SEPARACAO;
            }
        } elseif ($status === self::STATUS_FECHADO) {
            $payload['status'] = self::STATUS_SOLICITADO;
        }

        return $payload;
    }
}
