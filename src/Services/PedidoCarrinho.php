<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use RuntimeException;

/**
 * Pedido com carrinho multi-item, fechamento e nota com assinaturas.
 */
final class PedidoCarrinho
{
    public const string TIPO = 'pedido';

    public const string STATUS_CARRINHO = 'carrinho';

    public const string STATUS_FECHADO = 'fechado';

    public const string STATUS_SEPARADO = 'separado';

    public const string STATUS_ACEITO = 'aceito';

    public const string STATUS_ENTREGUE = 'entregue';

    public const string STATUS_CONFERIDO = 'conferido';

    /** @var list<string> */
    public const array ETAPAS_ASSINATURA = ['separacao', 'aceite', 'entrega', 'conferencia'];

    /**
     * @return array{id:int,payload:array<string,mixed>}
     */
    public static function abrir(string $escola, ?string $solicitante = null, ?int $solicitanteId = null): array
    {
        $escola = trim($escola);
        if ($escola === '') {
            throw new RuntimeException('Escola é obrigatória.');
        }

        $payload = [
            'escola' => $escola,
            'solicitante' => $solicitante,
            'solicitante_id' => $solicitanteId,
            'status' => self::STATUS_CARRINHO,
            'itens' => [],
            'assinaturas' => [
                'separacao' => null,
                'aceite' => null,
                'entrega' => null,
                'conferencia' => null,
            ],
            'baixa_estoque' => false,
            'fechado_em' => null,
        ];

        $id = PocRegistro::gravar(self::TIPO, $escola, self::STATUS_CARRINHO, $payload);

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

    public static function adicionarItem(int $pedidoId, string $sku, string $titulo, int $quantidade): void
    {
        $pedido = self::obter($pedidoId);
        $p = $pedido['payload'];
        if (($p['status'] ?? '') !== self::STATUS_CARRINHO) {
            throw new RuntimeException('Só é possível incluir itens em pedidos em aberto (carrinho).');
        }

        $sku = strtoupper(trim($sku));
        $titulo = trim($titulo);
        $quantidade = max(1, $quantidade);
        if ($sku === '' || $titulo === '') {
            throw new RuntimeException('Informe o item (SKU/título).');
        }

        /** @var list<array{sku:string,titulo:string,quantidade:int}> $itens */
        $itens = $p['itens'];
        $achou = false;
        foreach ($itens as $i => $item) {
            if (strtoupper($item['sku']) === $sku) {
                $itens[$i]['quantidade'] = (int) $item['quantidade'] + $quantidade;
                $itens[$i]['titulo'] = $titulo;
                $achou = true;
                break;
            }
        }
        if (! $achou) {
            $itens[] = [
                'sku' => $sku,
                'titulo' => $titulo,
                'quantidade' => $quantidade,
            ];
        }

        $p['itens'] = $itens;
        PocRegistro::atualizar($pedidoId, $p, $p['escola'], self::STATUS_CARRINHO.' · '.count($itens).' item(ns)');
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
            throw new RuntimeException('Pedido já foi fechado.');
        }
        if ($p['itens'] === []) {
            throw new RuntimeException('Inclua ao menos um item no carrinho antes de fechar.');
        }

        $p['status'] = self::STATUS_FECHADO;
        $p['fechado_em'] = now()->toDateTimeString();
        PocRegistro::atualizar($pedidoId, $p, $p['escola'], self::STATUS_FECHADO.' · '.count($p['itens']).' item(ns)');

        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\Notificador::paraProcesso(
                \iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos::PEDIDOS,
                'Almoxarifado: pedido #'.$pedidoId.' fechado — '.$p['escola'].' ('.count($p['itens']).' item(ns)).',
                '/almoxarifado-escolar/pedidos/'.$pedidoId,
            );
        }
    }

    /**
     * @param  'separacao'|'aceite'|'entrega'|'conferencia'  $etapa
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
        $status = (string) ($p['status'] ?? '');

        if ($status === self::STATUS_CARRINHO) {
            throw new RuntimeException('Feche o pedido antes de coletar assinaturas.');
        }

        $ordem = ['separacao' => 0, 'aceite' => 1, 'entrega' => 2, 'conferencia' => 3];
        $idx = $ordem[$etapa];
        foreach (self::ETAPAS_ASSINATURA as $prev) {
            if ($ordem[$prev] >= $idx) {
                break;
            }
            if (empty($p['assinaturas'][$prev])) {
                throw new RuntimeException('Conclua a etapa anterior ('.self::rotuloEtapa($prev).') antes.');
            }
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

        $p['status'] = match ($etapa) {
            'separacao' => self::STATUS_SEPARADO,
            'aceite' => self::STATUS_ACEITO,
            'entrega' => self::STATUS_ENTREGUE,
            'conferencia' => self::STATUS_CONFERIDO,
            default => $status,
        };

        if ($etapa === 'entrega' && empty($p['baixa_estoque'])) {
            foreach ($p['itens'] as $item) {
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

        PocRegistro::atualizar($pedidoId, $p, $p['escola'], $p['status'].' · '.count($p['itens']).' item(ns)');
    }

    public static function rotuloEtapa(string $etapa): string
    {
        return match ($etapa) {
            'separacao' => 'Separação',
            'aceite' => 'Aceite',
            'entrega' => 'Entrega',
            'conferencia' => 'Conferência',
            default => $etapa,
        };
    }

    public static function rotuloStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_CARRINHO => 'Carrinho',
            self::STATUS_FECHADO => 'Fechado',
            self::STATUS_SEPARADO => 'Separado',
            self::STATUS_ACEITO => 'Aceito',
            self::STATUS_ENTREGUE => 'Entregue',
            self::STATUS_CONFERIDO => 'Conferido',
            default => $status,
        };
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
            $payload['status'] = self::STATUS_FECHADO;
        } elseif ($statusLegado === 'Entregue') {
            $payload['status'] = self::STATUS_ENTREGUE;
        } elseif (! isset($payload['status'])) {
            $payload['status'] = self::STATUS_CARRINHO;
        }

        $payload['assinaturas'] = array_merge([
            'separacao' => null,
            'aceite' => null,
            'entrega' => null,
            'conferencia' => null,
        ], is_array($payload['assinaturas'] ?? null) ? $payload['assinaturas'] : []);

        $payload['baixa_estoque'] = (bool) ($payload['baixa_estoque'] ?? false);
        $payload['escola'] = (string) ($payload['escola'] ?? '');

        return $payload;
    }
}
