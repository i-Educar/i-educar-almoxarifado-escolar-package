{{-- PDF do balanço do almoxarifado — KPIs + gráficos SVG (Dompdf). --}}
@php
    $balanco = $balanco ?? [];
    $k = $balanco['kpis'] ?? [];
    $periodo = $balanco['periodo'] ?? [];
    $filtros = $balanco['filtros'] ?? [];
    $serie = $balanco['serie_movimentos'] ?? [];
    $topItens = $balanco['top_itens'] ?? [];
    $topEscolas = $balanco['top_escolas'] ?? [];
    $statusPedidos = $balanco['status_pedidos'] ?? [];
    $criticos = $balanco['criticos'] ?? [];
    $cor = $cor ?? '#4a5a6a';
    $titulo = $titulo ?? 'Balanço do almoxarifado escolar';
    $emitidoEm = $emitidoEm ?? now()->format('d/m/Y H:i');
    $emitidoPor = $emitidoPor ?? 'sistema';

    $maxSerie = 1;
    foreach ($serie as $p) {
        $maxSerie = max($maxSerie, (int) ($p['entradas'] ?? 0), (int) ($p['saidas'] ?? 0));
    }
    $maxEscola = 1;
    foreach ($topEscolas as $e) {
        $maxEscola = max($maxEscola, (int) ($e['unidades'] ?? 0));
    }
    $maxItem = 1;
    foreach ($topItens as $i) {
        $maxItem = max($maxItem, (int) ($i['saidas'] ?? 0) + (int) ($i['entradas'] ?? 0));
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 26px 36px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a242b; }
        h1 { margin: 0 0 4px; font-size: 16px; color: {{ $cor }}; text-transform: uppercase; letter-spacing: .04em; }
        h2 { margin: 14px 0 6px; font-size: 11px; color: {{ $cor }}; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #cfd8df; padding-bottom: 3px; }
        .meta { color: #5b6b76; margin: 0 0 10px; font-size: 9px; }
        .selo {
            display: inline-block; padding: 2px 7px; border: 1px solid {{ $cor }}; color: {{ $cor }};
            border-radius: 3px; font-size: 8px; font-weight: 700; text-transform: uppercase; margin: 0 0 8px;
        }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 0 8px; }
        .kpis td {
            width: 25%; background: #f3f6f8; border: 1px solid #d7e0e7; border-top: 3px solid {{ $cor }};
            border-radius: 6px; padding: 7px 8px; vertical-align: top;
        }
        .kpis .lbl { display: block; color: #5b6b76; font-size: 7.5px; text-transform: uppercase; font-weight: 700; letter-spacing: .03em; }
        .kpis .val { display: block; font-size: 14px; font-weight: 800; margin-top: 2px; letter-spacing: -.02em; }
        table.lista { width: 100%; border-collapse: collapse; margin: 0 0 6px; }
        table.lista th {
            background: {{ $cor }}; color: #fff; font-size: 8px; text-transform: uppercase;
            text-align: left; padding: 5px 6px;
        }
        table.lista td { border-bottom: 1px solid #e2e8ee; padding: 4px 6px; font-size: 9px; }
        table.lista tr:nth-child(even) td { background: #f7fafb; }
        .chart-box { border: 1px solid #d7e0e7; border-radius: 6px; padding: 8px; margin: 0 0 8px; }
        .col2 { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .col2 td { width: 50%; vertical-align: top; }
        .legenda { font-size: 8px; color: #5b6b76; margin: 4px 0 0; }
        .legenda span { display: inline-block; margin-right: 10px; }
        .swatch { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 3px; vertical-align: middle; }
        .rodape {
            position: fixed; bottom: -18px; left: 0; right: 0; font-size: 8px; color: #5b6b76;
            border-top: 1px solid #cfd8df; padding-top: 4px;
        }
        .faixa { height: 4px; background: {{ $cor }}; margin: 0 0 10px; }
    </style>
</head>
<body>
    <div class="faixa"></div>
    <div class="selo">Documento oficial · balanço operacional</div>
    <h1>{{ $titulo }}</h1>
    <p class="meta">
        Período: {{ $periodo['rotulo'] ?? '—' }}
        @if(!empty($filtros['escola'])) · Escola: {{ $filtros['escola'] }} @endif
        @if(!empty($filtros['q'])) · Busca: {{ $filtros['q'] }} @endif
        · Emitido em {{ $emitidoEm }} por {{ $emitidoPor }}
    </p>

    <table class="kpis">
        <tr>
            <td><span class="lbl">No depósito</span><span class="val">{{ number_format((int) ($k['deposito_unidades'] ?? 0), 0, ',', '.') }}</span></td>
            <td><span class="lbl">Críticos</span><span class="val">{{ (int) ($k['criticos'] ?? 0) }}</span></td>
            <td><span class="lbl">Entradas no período</span><span class="val">{{ number_format((int) ($k['entradas_periodo'] ?? 0), 0, ',', '.') }}</span></td>
            <td><span class="lbl">Saídas no período</span><span class="val">{{ number_format((int) ($k['saidas_periodo'] ?? 0), 0, ',', '.') }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Líquido (E−S)</span><span class="val">{{ number_format((int) ($k['liquido_periodo'] ?? 0), 0, ',', '.') }}</span></td>
            <td><span class="lbl">Pedidos no período</span><span class="val">{{ (int) ($k['pedidos_periodo'] ?? 0) }}</span></td>
            <td><span class="lbl">Entregues no período</span><span class="val">{{ (int) ($k['pedidos_entregues_periodo'] ?? 0) }}</span></td>
            <td><span class="lbl">Em trânsito agora</span><span class="val">{{ (int) ($k['pedidos_transito'] ?? 0) }}</span></td>
        </tr>
    </table>

    <h2>Movimentação no período</h2>
    <div class="chart-box">
        @if(empty($serie))
            <p class="meta" style="margin:0">Sem movimentos de estoque neste período.</p>
        @else
            @php
                $n = max(1, count($serie));
                $w = 520; $h = 120; $padL = 28; $padB = 28; $padT = 8; $padR = 8;
                $plotW = $w - $padL - $padR; $plotH = $h - $padT - $padB;
                $grupo = $plotW / $n;
                $barW = max(3, min(14, $grupo * 0.32));
            @endphp
            <svg width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" xmlns="http://www.w3.org/2000/svg">
                <line x1="{{ $padL }}" y1="{{ $padT }}" x2="{{ $padL }}" y2="{{ $padT + $plotH }}" stroke="#cfd8df" stroke-width="1"/>
                <line x1="{{ $padL }}" y1="{{ $padT + $plotH }}" x2="{{ $padL + $plotW }}" y2="{{ $padT + $plotH }}" stroke="#cfd8df" stroke-width="1"/>
                @foreach($serie as $i => $ponto)
                    @php
                        $x0 = $padL + ($i * $grupo) + ($grupo - 2 * $barW) / 2;
                        $he = (int) round(((int) $ponto['entradas'] / $maxSerie) * $plotH);
                        $hs = (int) round(((int) $ponto['saidas'] / $maxSerie) * $plotH);
                        $ye = $padT + $plotH - $he;
                        $ys = $padT + $plotH - $hs;
                    @endphp
                    <rect x="{{ $x0 }}" y="{{ $ye }}" width="{{ $barW }}" height="{{ max(0, $he) }}" fill="#17663f"/>
                    <rect x="{{ $x0 + $barW + 1 }}" y="{{ $ys }}" width="{{ $barW }}" height="{{ max(0, $hs) }}" fill="#b86e00"/>
                    @if($n <= 16 || $i % 2 === 0)
                        <text x="{{ $x0 + $barW }}" y="{{ $h - 8 }}" font-size="7" text-anchor="middle" fill="#5b6b76">{{ $ponto['rotulo'] }}</text>
                    @endif
                @endforeach
            </svg>
            <p class="legenda">
                <span><i class="swatch" style="background:#17663f"></i>Entradas</span>
                <span><i class="swatch" style="background:#b86e00"></i>Saídas</span>
                · pico {{ number_format($maxSerie, 0, ',', '.') }} un.
            </p>
        @endif
    </div>

    <table class="col2">
        <tr>
            <td>
                <h2>Escolas atendidas</h2>
                <div class="chart-box">
                    @forelse(array_slice($topEscolas, 0, 8) as $esc)
                        @php $pct = max(2, (int) round(((int) $esc['unidades'] / $maxEscola) * 100)); @endphp
                        <div style="margin:0 0 6px">
                            <div style="font-size:8px;margin:0 0 2px">
                                <strong>{{ \Illuminate\Support\Str::limit($esc['escola'], 36) }}</strong>
                                · {{ number_format((int) $esc['unidades'], 0, ',', '.') }} un. · {{ (int) $esc['pedidos'] }} ped.
                            </div>
                            <div style="height:7px;background:#e8eef2;border-radius:4px;overflow:hidden">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $cor }};"></div>
                            </div>
                        </div>
                    @empty
                        <p class="meta" style="margin:0">Nenhuma entrega no período.</p>
                    @endforelse
                </div>
            </td>
            <td>
                <h2>Itens mais movimentados</h2>
                <div class="chart-box">
                    @forelse(array_slice($topItens, 0, 8) as $item)
                        @php
                            $tot = (int) $item['entradas'] + (int) $item['saidas'];
                            $pct = max(2, (int) round(($tot / $maxItem) * 100));
                        @endphp
                        <div style="margin:0 0 6px">
                            <div style="font-size:8px;margin:0 0 2px">
                                <strong>{{ \Illuminate\Support\Str::limit($item['titulo'], 28) }}</strong>
                                <span style="color:#5b6b76">({{ $item['sku'] }})</span>
                                · E {{ (int) $item['entradas'] }} / S {{ (int) $item['saidas'] }}
                            </div>
                            <div style="height:7px;background:#e8eef2;border-radius:4px;overflow:hidden">
                                <div style="height:100%;width:{{ $pct }}%;background:#0d7377;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="meta" style="margin:0">Sem movimentação de itens no período.</p>
                    @endforelse
                </div>
            </td>
        </tr>
    </table>

    <h2>Pedidos por status (no período)</h2>
    @if(empty($statusPedidos))
        <p class="meta">Nenhum pedido no período.</p>
    @else
        <table class="lista">
            <thead><tr><th>Status</th><th>Quantidade</th></tr></thead>
            <tbody>
            @foreach($statusPedidos as $st)
                <tr>
                    <td>{{ $st['rotulo'] }}</td>
                    <td>{{ (int) $st['qtd'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Itens críticos no depósito (agora)</h2>
    @if(empty($criticos))
        <p class="meta">Nenhum item abaixo do mínimo.</p>
    @else
        <table class="lista">
            <thead><tr><th>SKU</th><th>Item</th><th>Saldo</th><th>Mínimo</th><th>Cobertura</th></tr></thead>
            <tbody>
            @foreach($criticos as $c)
                <tr>
                    <td>{{ $c['sku'] }}</td>
                    <td>{{ $c['titulo'] }}</td>
                    <td>{{ number_format((int) $c['saldo'], 0, ',', '.') }}</td>
                    <td>{{ number_format((int) $c['minimo'], 0, ',', '.') }}</td>
                    <td>{{ number_format((float) $c['cobertura'], 2, ',', '.') }}×</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="rodape">
        Almoxarifado escolar · balanço operacional · {{ $periodo['rotulo'] ?? '' }}
        @if(!empty($codigoValidacao)) · Validação: {{ $codigoValidacao }} @endif
    </div>
</body>
</html>
