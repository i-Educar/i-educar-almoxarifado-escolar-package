<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

/**
 * Panorama de saldos da rede: depósito central + material já entregue por escola.
 */
final class SaldoRede
{
    /**
     * @param  list<array{sku:string,titulo:string,saldo:int,minimo:int}>  $catalogo
     * @return array{
     *   deposito:list<array{sku:string,titulo:string,saldo:int,minimo:int,critico:bool,cobertura:float}>,
     *   escolas:list<array{
     *     escola:string,
     *     unidades:int,
     *     skus:int,
     *     pedidos_entregues:int,
     *     pedidos_transito:int,
     *     ultima_entrega:?string,
     *     itens:list<array{sku:string,titulo:string,quantidade:int}>,
     *     transito:list<array{pedido_id:int,status:string,itens:int,unidades:int}>
     *   }>,
     *   resumo:array{
     *     deposito_itens:int,
     *     deposito_unidades:int,
     *     criticos:int,
     *     escolas_com_material:int,
     *     unidades_entregues:int,
     *     pedidos_transito:int
     *   }
     * }
     */
    /**
     * Avalia só o depósito (puro — sem PoC/DB). Usado por panorama e testes smoke.
     *
     * @param  list<array{sku?:string,titulo?:string,saldo?:int,minimo?:int}>  $catalogo
     * @return array{deposito:list<array{sku:string,titulo:string,saldo:int,minimo:int,critico:bool,cobertura:float}>,criticos:int,deposito_unidades:int}
     */
    public static function avaliarDeposito(array $catalogo, float $fatorCritico = 1.0): array
    {
        $deposito = [];
        $criticos = 0;
        $depositoUnidades = 0;
        foreach ($catalogo as $item) {
            $saldo = (int) ($item['saldo'] ?? 0);
            $minimo = max(0, (int) ($item['minimo'] ?? 0));
            $critico = $minimo > 0 && $saldo < ($minimo * $fatorCritico);
            if ($critico) {
                $criticos++;
            }
            $depositoUnidades += $saldo;
            $deposito[] = [
                'sku' => (string) ($item['sku'] ?? ''),
                'titulo' => (string) ($item['titulo'] ?? ''),
                'saldo' => $saldo,
                'minimo' => $minimo,
                'critico' => $critico,
                'cobertura' => $minimo > 0 ? round($saldo / $minimo, 2) : ($saldo > 0 ? 99.0 : 0.0),
            ];
        }

        usort($deposito, static function (array $a, array $b): int {
            if ($a['critico'] !== $b['critico']) {
                return $a['critico'] ? -1 : 1;
            }

            return $a['saldo'] <=> $b['saldo'];
        });

        return [
            'deposito' => $deposito,
            'criticos' => $criticos,
            'deposito_unidades' => $depositoUnidades,
        ];
    }

    public static function panorama(array $catalogo, ?string $escolaFiltro = null, float $fatorCritico = 1.0): array
    {
        $escolaFiltro = $escolaFiltro !== null ? trim($escolaFiltro) : null;
        if ($escolaFiltro === '') {
            $escolaFiltro = null;
        }

        $avaliacao = self::avaliarDeposito($catalogo, $fatorCritico);
        $deposito = $avaliacao['deposito'];
        $criticos = $avaliacao['criticos'];
        $depositoUnidades = $avaliacao['deposito_unidades'];

        /** @var array<string, array{escola:string,unidades:int,skus:int,pedidos_entregues:int,pedidos_transito:int,ultima_entrega:?string,itens:array<string,array{sku:string,titulo:string,quantidade:int}>,transito:list<array{pedido_id:int,status:string,itens:int,unidades:int}>}> $mapa */
        $mapa = [];
        $unidadesEntregues = 0;
        $pedidosTransitoTotal = 0;

        foreach (PocRegistro::listar(PedidoCarrinho::TIPO, 500) as $r) {
            $p = $r['payload'];
            $escola = trim((string) ($p['escola'] ?? ''));
            if ($escola === '') {
                continue;
            }
            if ($escolaFiltro !== null && strcasecmp($escola, $escolaFiltro) !== 0) {
                continue;
            }

            if (! isset($mapa[$escola])) {
                $mapa[$escola] = [
                    'escola' => $escola,
                    'unidades' => 0,
                    'skus' => 0,
                    'pedidos_entregues' => 0,
                    'pedidos_transito' => 0,
                    'ultima_entrega' => null,
                    'itens' => [],
                    'transito' => [],
                ];
            }

            $status = (string) ($p['status'] ?? '');
            $itens = is_array($p['itens'] ?? null) ? $p['itens'] : [];
            $qtdItens = count($itens);
            $unidadesPedido = 0;
            foreach ($itens as $item) {
                $unidadesPedido += max(0, (int) ($item['quantidade'] ?? 0));
            }

            $entregue = in_array($status, [PedidoCarrinho::STATUS_ENTREGUE, PedidoCarrinho::STATUS_CONFERIDO], true)
                || ! empty($p['baixa_estoque']);

            if ($entregue) {
                $mapa[$escola]['pedidos_entregues']++;
                $quando = (string) (
                    $p['assinaturas']['conferencia']['quando_iso']
                    ?? $p['assinaturas']['conferencia']['quando_raw']
                    ?? $p['assinaturas']['entrega']['quando_iso']
                    ?? $p['assinaturas']['entrega']['quando_raw']
                    ?? $p['baixa_em']
                    ?? $p['finalizado_em']
                    ?? $r['created_at']
                    ?? ''
                );
                if ($quando !== '' && ($mapa[$escola]['ultima_entrega'] === null || $quando > $mapa[$escola]['ultima_entrega'])) {
                    $mapa[$escola]['ultima_entrega'] = $quando;
                }

                foreach ($itens as $item) {
                    $origem = (string) ($item['origem'] ?? 'catalogo');
                    if (in_array($origem, ['sem_saldo', 'fora_catalogo'], true)) {
                        continue;
                    }
                    $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
                    if ($sku === '') {
                        continue;
                    }
                    $qtd = max(0, (int) ($item['quantidade'] ?? 0));
                    if ($qtd < 1) {
                        continue;
                    }
                    $titulo = (string) ($item['titulo'] ?? $sku);
                    if (! isset($mapa[$escola]['itens'][$sku])) {
                        $mapa[$escola]['itens'][$sku] = [
                            'sku' => $sku,
                            'titulo' => $titulo,
                            'quantidade' => 0,
                        ];
                    }
                    $mapa[$escola]['itens'][$sku]['quantidade'] += $qtd;
                    $mapa[$escola]['unidades'] += $qtd;
                    $unidadesEntregues += $qtd;
                }
            } elseif (in_array(PedidoCarrinho::normalizarStatus($status), [
                PedidoCarrinho::STATUS_SOLICITADO,
                PedidoCarrinho::STATUS_ACEITO,
                PedidoCarrinho::STATUS_SEPARACAO,
                PedidoCarrinho::STATUS_SEPARADO,
                PedidoCarrinho::STATUS_EM_TRANSITO,
            ], true)) {
                $mapa[$escola]['pedidos_transito']++;
                $pedidosTransitoTotal++;
                $mapa[$escola]['transito'][] = [
                    'pedido_id' => (int) $r['id'],
                    'status' => $status,
                    'itens' => $qtdItens,
                    'unidades' => $unidadesPedido,
                ];
            }
        }

        $escolas = [];
        foreach ($mapa as $row) {
            $itens = array_values($row['itens']);
            usort($itens, static fn (array $a, array $b): int => $b['quantidade'] <=> $a['quantidade']);
            $row['itens'] = $itens;
            $row['skus'] = count($itens);
            $escolas[] = $row;
        }

        usort($escolas, static function (array $a, array $b): int {
            if ($a['unidades'] !== $b['unidades']) {
                return $b['unidades'] <=> $a['unidades'];
            }

            return strcasecmp($a['escola'], $b['escola']);
        });

        return [
            'deposito' => $deposito,
            'escolas' => $escolas,
            'resumo' => [
                'deposito_itens' => count($deposito),
                'deposito_unidades' => $depositoUnidades,
                'criticos' => $criticos,
                'escolas_com_material' => count(array_filter($escolas, static fn (array $e): bool => $e['unidades'] > 0)),
                'unidades_entregues' => $unidadesEntregues,
                'pedidos_transito' => $pedidosTransitoTotal,
            ],
        ];
    }
}
