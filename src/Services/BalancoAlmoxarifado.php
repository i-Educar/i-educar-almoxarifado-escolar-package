<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

use Carbon\Carbon;
use Throwable;

/**
 * Balanço operacional do almoxarifado: KPIs, séries e rankings por período.
 */
final class BalancoAlmoxarifado
{
    /**
     * @param  list<array{sku?:string,titulo?:string,saldo?:int,minimo?:int}>  $catalogo
     * @return array{
     *   periodo:array{inicio:string,fim:string,rotulo:string,preset:string,de_iso:string,ate_iso:string},
     *   filtros:array{escola:?string,q:string},
     *   kpis:array<string,int|float>,
     *   serie_movimentos:list<array{rotulo:string,entradas:int,saidas:int}>,
     *   top_itens:list<array{sku:string,titulo:string,entradas:int,saidas:int,liquido:int}>,
     *   top_escolas:list<array{escola:string,unidades:int,pedidos:int}>,
     *   status_pedidos:list<array{status:string,rotulo:string,qtd:int}>,
     *   criticos:list<array{sku:string,titulo:string,saldo:int,minimo:int,cobertura:float}>,
     *   panorama:array<string,mixed>
     * }
     */
    public static function gerar(
        array $catalogo,
        string $preset = 'mes',
        ?string $de = null,
        ?string $ate = null,
        ?string $escola = null,
        string $q = '',
        float $fatorCritico = 1.0,
    ): array {
        $periodo = self::resolverPeriodo($preset, $de, $ate);
        $inicio = Carbon::parse($periodo['de_iso'])->startOfDay();
        $fim = Carbon::parse($periodo['ate_iso'])->endOfDay();
        $escola = $escola !== null && trim($escola) !== '' ? trim($escola) : null;
        $q = trim($q);
        $needle = $q !== '' ? mb_strtolower($q) : '';

        $panorama = SaldoRede::panorama($catalogo, $escola, $fatorCritico);
        if ($needle !== '') {
            $panorama['deposito'] = array_values(array_filter(
                $panorama['deposito'],
                static fn (array $i): bool => str_contains(mb_strtolower($i['sku'].' '.$i['titulo']), $needle)
            ));
        }

        $criticos = [];
        foreach ($panorama['deposito'] as $item) {
            if (! empty($item['critico'])) {
                $criticos[] = [
                    'sku' => (string) $item['sku'],
                    'titulo' => (string) $item['titulo'],
                    'saldo' => (int) $item['saldo'],
                    'minimo' => (int) $item['minimo'],
                    'cobertura' => (float) $item['cobertura'],
                ];
            }
        }

        $entradas = 0;
        $saidas = 0;
        $ops = 0;
        $porDia = [];
        /** @var array<string, array{sku:string,titulo:string,entradas:int,saidas:int}> $porSku */
        $porSku = [];

        foreach (MovimentoEstoque::listar(800) as $r) {
            $p = is_array($r['payload'] ?? null) ? $r['payload'] : [];
            $quandoRaw = (string) ($p['quando'] ?? $r['created_at'] ?? '');
            if ($quandoRaw === '' || ! self::noPeriodo($quandoRaw, $inicio, $fim)) {
                continue;
            }
            $sku = strtoupper(trim((string) ($p['sku'] ?? '')));
            $titulo = (string) ($p['titulo'] ?? $sku);
            if ($needle !== '' && ! str_contains(mb_strtolower($sku.' '.$titulo), $needle)) {
                continue;
            }
            $sentido = (string) ($p['sentido'] ?? '');
            $qtd = max(0, (int) ($p['quantidade'] ?? 0));
            if ($qtd < 1) {
                continue;
            }
            $ops++;
            $dia = self::chaveDia($quandoRaw);
            if (! isset($porDia[$dia])) {
                $porDia[$dia] = ['rotulo' => $dia, 'entradas' => 0, 'saidas' => 0];
            }
            if (! isset($porSku[$sku])) {
                $porSku[$sku] = ['sku' => $sku, 'titulo' => $titulo, 'entradas' => 0, 'saidas' => 0];
            }
            if ($sentido === MovimentoEstoque::ENTRADA) {
                $entradas += $qtd;
                $porDia[$dia]['entradas'] += $qtd;
                $porSku[$sku]['entradas'] += $qtd;
            } else {
                $saidas += $qtd;
                $porDia[$dia]['saidas'] += $qtd;
                $porSku[$sku]['saidas'] += $qtd;
            }
        }

        ksort($porDia);
        $serie = self::agruparSerie(array_values($porDia), $inicio, $fim);

        $topItens = [];
        foreach ($porSku as $row) {
            $topItens[] = [
                'sku' => $row['sku'],
                'titulo' => $row['titulo'],
                'entradas' => $row['entradas'],
                'saidas' => $row['saidas'],
                'liquido' => $row['entradas'] - $row['saidas'],
            ];
        }
        usort($topItens, static fn (array $a, array $b): int => ($b['saidas'] + $b['entradas']) <=> ($a['saidas'] + $a['entradas']));
        $topItens = array_slice($topItens, 0, 12);

        $statusContagem = [];
        /** @var array<string, array{escola:string,unidades:int,pedidos:int}> $escolasPeriodo */
        $escolasPeriodo = [];
        $pedidosPeriodo = 0;
        $pedidosEntreguesPeriodo = 0;
        $pedidosFinalizadosPeriodo = 0;

        foreach (PocRegistro::listar(PedidoCarrinho::TIPO, 500) as $r) {
            $p = is_array($r['payload'] ?? null) ? $r['payload'] : [];
            $esc = trim((string) ($p['escola'] ?? ''));
            if ($escola !== null && strcasecmp($esc, $escola) !== 0) {
                continue;
            }
            $quandoRaw = (string) (
                $p['finalizado_em']
                ?? $p['fechado_em']
                ?? $p['assinaturas']['entrega']['quando_iso']
                ?? $p['assinaturas']['entrega']['quando_raw']
                ?? $r['created_at']
                ?? ''
            );
            if ($quandoRaw === '' || ! self::noPeriodo($quandoRaw, $inicio, $fim)) {
                continue;
            }
            $pedidosPeriodo++;
            $status = (string) ($p['status'] ?? PedidoCarrinho::STATUS_CARRINHO);
            $statusContagem[$status] = ($statusContagem[$status] ?? 0) + 1;

            $entregue = in_array($status, [PedidoCarrinho::STATUS_ENTREGUE, PedidoCarrinho::STATUS_CONFERIDO], true)
                || ! empty($p['baixa_estoque']);
            if ($entregue) {
                $pedidosEntreguesPeriodo++;
            }
            if (PedidoCarrinho::finalizado($p)) {
                $pedidosFinalizadosPeriodo++;
            }

            if ($esc === '' || ! $entregue) {
                continue;
            }
            if (! isset($escolasPeriodo[$esc])) {
                $escolasPeriodo[$esc] = ['escola' => $esc, 'unidades' => 0, 'pedidos' => 0];
            }
            $escolasPeriodo[$esc]['pedidos']++;
            foreach (is_array($p['itens'] ?? null) ? $p['itens'] : [] as $item) {
                $origem = (string) ($item['origem'] ?? 'catalogo');
                if (in_array($origem, ['sem_saldo', 'fora_catalogo'], true)) {
                    continue;
                }
                $skuItem = strtoupper(trim((string) ($item['sku'] ?? '')));
                $tituloItem = (string) ($item['titulo'] ?? $skuItem);
                if ($needle !== '' && ! str_contains(mb_strtolower($skuItem.' '.$tituloItem), $needle)) {
                    continue;
                }
                $escolasPeriodo[$esc]['unidades'] += max(0, (int) ($item['quantidade'] ?? 0));
            }
        }

        $topEscolas = array_values($escolasPeriodo);
        usort($topEscolas, static function (array $a, array $b): int {
            if ($a['unidades'] !== $b['unidades']) {
                return $b['unidades'] <=> $a['unidades'];
            }

            return strcasecmp($a['escola'], $b['escola']);
        });
        $topEscolas = array_slice($topEscolas, 0, 12);

        $statusPedidos = [];
        foreach ($statusContagem as $st => $qtd) {
            $statusPedidos[] = [
                'status' => $st,
                'rotulo' => PedidoCarrinho::rotuloStatus($st),
                'qtd' => $qtd,
            ];
        }
        usort($statusPedidos, static fn (array $a, array $b): int => $b['qtd'] <=> $a['qtd']);

        $resumo = $panorama['resumo'] ?? [];

        return [
            'periodo' => $periodo,
            'filtros' => [
                'escola' => $escola,
                'q' => $q,
            ],
            'kpis' => [
                'deposito_unidades' => (int) ($resumo['deposito_unidades'] ?? 0),
                'criticos' => count($criticos),
                'entradas_periodo' => $entradas,
                'saidas_periodo' => $saidas,
                'liquido_periodo' => $entradas - $saidas,
                'ops_periodo' => $ops,
                'pedidos_periodo' => $pedidosPeriodo,
                'pedidos_entregues_periodo' => $pedidosEntreguesPeriodo,
                'pedidos_finalizados_periodo' => $pedidosFinalizadosPeriodo,
                'pedidos_transito' => (int) ($resumo['pedidos_transito'] ?? 0),
                'escolas_atendidas_periodo' => count($topEscolas),
                'escolas_com_material' => (int) ($resumo['escolas_com_material'] ?? 0),
                'unidades_entregues_acumulado' => (int) ($resumo['unidades_entregues'] ?? 0),
            ],
            'serie_movimentos' => $serie,
            'top_itens' => $topItens,
            'top_escolas' => $topEscolas,
            'status_pedidos' => $statusPedidos,
            'criticos' => $criticos,
            'panorama' => $panorama,
        ];
    }

    /**
     * @return array{inicio:string,fim:string,rotulo:string,preset:string,de_iso:string,ate_iso:string}
     */
    public static function resolverPeriodo(string $preset, ?string $de = null, ?string $ate = null): array
    {
        $preset = mb_strtolower(trim($preset));
        $tz = config('app.timezone', 'America/Sao_Paulo');
        $hoje = Carbon::now($tz)->startOfDay();

        try {
            $inicioCustom = $de !== null && trim($de) !== '' ? Carbon::parse($de, $tz)->startOfDay() : null;
            $fimCustom = $ate !== null && trim($ate) !== '' ? Carbon::parse($ate, $tz)->endOfDay() : null;
        } catch (Throwable) {
            $inicioCustom = null;
            $fimCustom = null;
        }

        if ($preset === 'custom' || ($inicioCustom !== null && $fimCustom !== null && $preset === '')) {
            $inicio = $inicioCustom ?? $hoje->copy()->startOfMonth();
            $fim = $fimCustom ?? $hoje->copy()->endOfDay();
            $preset = 'custom';
        } else {
            [$inicio, $fim, $preset] = match ($preset) {
                '7d', '7dias' => [$hoje->copy()->subDays(6), $hoje->copy()->endOfDay(), '7d'],
                '30d', '30dias' => [$hoje->copy()->subDays(29), $hoje->copy()->endOfDay(), '30d'],
                'trimestre' => [$hoje->copy()->firstOfQuarter(), $hoje->copy()->endOfDay(), 'trimestre'],
                'ano' => [$hoje->copy()->startOfYear(), $hoje->copy()->endOfDay(), 'ano'],
                'custom' => [
                    $inicioCustom ?? $hoje->copy()->startOfMonth(),
                    $fimCustom ?? $hoje->copy()->endOfDay(),
                    'custom',
                ],
                default => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfDay(), 'mes'],
            };
        }

        if ($fim->lt($inicio)) {
            [$inicio, $fim] = [$fim->copy()->startOfDay(), $inicio->copy()->endOfDay()];
        }

        $rotulo = $inicio->format('d/m/Y').' — '.$fim->format('d/m/Y');
        $rotuloPreset = match ($preset) {
            '7d' => 'Últimos 7 dias',
            '30d' => 'Últimos 30 dias',
            'mes' => 'Mês atual',
            'trimestre' => 'Trimestre atual',
            'ano' => 'Ano atual',
            default => 'Período personalizado',
        };

        return [
            'inicio' => $inicio->format('d/m/Y'),
            'fim' => $fim->format('d/m/Y'),
            'rotulo' => $rotuloPreset.' · '.$rotulo,
            'preset' => $preset,
            'de_iso' => $inicio->toDateString(),
            'ate_iso' => $fim->toDateString(),
        ];
    }

    private static function noPeriodo(string $quandoRaw, Carbon $inicio, Carbon $fim): bool
    {
        try {
            $c = Carbon::parse($quandoRaw);

            return $c->betweenIncluded($inicio, $fim);
        } catch (Throwable) {
            return false;
        }
    }

    private static function chaveDia(string $quandoRaw): string
    {
        try {
            return Carbon::parse($quandoRaw)->format('d/m');
        } catch (Throwable) {
            return substr($quandoRaw, 0, 10);
        }
    }

    /**
     * @param  list<array{rotulo:string,entradas:int,saidas:int}>  $dias
     * @return list<array{rotulo:string,entradas:int,saidas:int}>
     */
    private static function agruparSerie(array $dias, Carbon $inicio, Carbon $fim): array
    {
        $span = max(1, $inicio->diffInDays($fim) + 1);
        if ($span <= 14 || count($dias) <= 14) {
            return $dias;
        }

        // Agrupa por semana (rótulo = início da semana).
        $semanas = [];
        foreach ($dias as $d) {
            try {
                $c = Carbon::createFromFormat('d/m', $d['rotulo'])->year($inicio->year);
                if ($c->lt($inicio)) {
                    $c->year($fim->year);
                }
                $chave = $c->startOfWeek(Carbon::MONDAY)->format('d/m');
            } catch (Throwable) {
                $chave = $d['rotulo'];
            }
            if (! isset($semanas[$chave])) {
                $semanas[$chave] = ['rotulo' => 'Sem '.$chave, 'entradas' => 0, 'saidas' => 0];
            }
            $semanas[$chave]['entradas'] += $d['entradas'];
            $semanas[$chave]['saidas'] += $d['saidas'];
        }

        return array_values($semanas);
    }
}
