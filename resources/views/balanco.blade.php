@extends('almoxarifado::layout')
@php
    $balanco = $balanco ?? [];
    $k = $balanco['kpis'] ?? [];
    $periodo = $balanco['periodo'] ?? [];
    $serie = $balanco['serie_movimentos'] ?? [];
    $topItens = $balanco['top_itens'] ?? [];
    $topEscolas = $balanco['top_escolas'] ?? [];
    $statusPedidos = $balanco['status_pedidos'] ?? [];
    $criticos = $balanco['criticos'] ?? [];
    $queryPdf = http_build_query(array_filter([
        'periodo' => $periodo['preset'] ?? 'mes',
        'de' => $periodo['de_iso'] ?? null,
        'ate' => $periodo['ate_iso'] ?? null,
        'escola' => $balanco['filtros']['escola'] ?? null,
        'q' => $balanco['filtros']['q'] ?? null,
    ], static fn ($v) => $v !== null && $v !== ''));
    $maxSerie = 1;
    foreach ($serie as $p) {
        $maxSerie = max($maxSerie, (int) ($p['entradas'] ?? 0), (int) ($p['saidas'] ?? 0));
    }
@endphp
@section('titulo', 'Balanço do almoxarifado')
@section('migalha', 'Balanço')
@section('icone-tela', 'fa-pie-chart')
@section('titulo-tela', 'Balanço operacional')
@section('lead')
    KPIs, movimentos e entregas no período selecionado — emita o PDF institucional quando precisar registrar.
@endsection
@section('acoes-topo')
    <a class="botao icone" href="{{ url('/almoxarifado-escolar/saldos/balanco/pdf?'.$queryPdf) }}" title="Emitir PDF" aria-label="Emitir PDF"><i aria-hidden="true" class="fa fa-file-pdf-o"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/saldos') }}" title="Saldos" aria-label="Saldos"><i aria-hidden="true" class="fa fa-bar-chart"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/movimentos') }}" title="Movimentos" aria-label="Movimentos"><i aria-hidden="true" class="fa fa-exchange"></i></a>
@endsection

@section('conteudo')
<style>
    .balanco-kpis { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; margin:0 0 14px; }
    .balanco-kpis article {
        background:#fff; border:1px solid var(--linha); border-top:3px solid var(--tom,var(--cor-tela));
        border-radius:12px; padding:12px; box-shadow:0 6px 14px rgba(21,34,43,.04);
    }
    .balanco-kpis span { display:block; color:var(--suave); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
    .balanco-kpis strong { font-size:1.25rem; letter-spacing:-.02em; }
    .balanco-chart {
        display:flex; align-items:flex-end; gap:6px; min-height:140px; padding:12px 8px 4px;
        border:1px solid var(--linha); border-radius:12px; background:linear-gradient(180deg,#f7fafb,#fff);
        overflow-x:auto;
    }
    .balanco-bar { flex:1 0 28px; max-width:48px; display:flex; flex-direction:column; align-items:center; gap:2px; }
    .balanco-bar .pares { display:flex; align-items:flex-end; gap:2px; height:110px; width:100%; justify-content:center; }
    .balanco-bar i { display:block; width:10px; border-radius:4px 4px 0 0; min-height:2px; }
    .balanco-bar i.e { background:#17663f; }
    .balanco-bar i.s { background:#b86e00; }
    .balanco-bar small { font-size:.65rem; color:var(--suave); white-space:nowrap; }
    .rank-row { margin:0 0 8px; }
    .rank-row .lbl { display:flex; justify-content:space-between; gap:8px; font-size:.82rem; margin:0 0 3px; }
    .rank-bar { height:8px; border-radius:99px; background:#e8eef2; overflow:hidden; }
    .rank-bar i { display:block; height:100%; border-radius:99px; background:var(--tom,var(--cor-tela)); }
</style>

<p class="texto-suave" style="margin:0 0 12px">
    <i class="fa fa-calendar"></i> {{ $periodo['rotulo'] ?? '—' }}
    @if(!empty($balanco['filtros']['escola'])) · Escola: {{ $balanco['filtros']['escola'] }} @endif
    @if(!empty($balanco['filtros']['q'])) · Busca: {{ $balanco['filtros']['q'] }} @endif
</p>

<div class="balanco-kpis">
    <article style="--tom:var(--destaque)"><span>No depósito</span><strong>{{ number_format((int) ($k['deposito_unidades'] ?? 0), 0, ',', '.') }}</strong></article>
    <article style="--tom:{{ ($k['criticos'] ?? 0) > 0 ? 'var(--erro)' : 'var(--ok)' }}"><span>Críticos</span><strong>{{ (int) ($k['criticos'] ?? 0) }}</strong></article>
    <article style="--tom:var(--ok)"><span>Entradas</span><strong>{{ number_format((int) ($k['entradas_periodo'] ?? 0), 0, ',', '.') }}</strong></article>
    <article style="--tom:var(--ambar)"><span>Saídas</span><strong>{{ number_format((int) ($k['saidas_periodo'] ?? 0), 0, ',', '.') }}</strong></article>
    <article style="--tom:var(--cor)"><span>Pedidos</span><strong>{{ (int) ($k['pedidos_periodo'] ?? 0) }}</strong></article>
    <article style="--tom:var(--ok)"><span>Entregues</span><strong>{{ (int) ($k['pedidos_entregues_periodo'] ?? 0) }}</strong></article>
    <article style="--tom:var(--ambar)"><span>Em trânsito</span><strong>{{ (int) ($k['pedidos_transito'] ?? 0) }}</strong></article>
    <article style="--tom:var(--destaque)"><span>Escolas no período</span><strong>{{ (int) ($k['escolas_atendidas_periodo'] ?? 0) }}</strong></article>
</div>

<div class="bloco">
    <h2><i class="fa fa-line-chart"></i> Movimentação</h2>
    @if(empty($serie))
        <p style="margin:0;color:var(--suave)">Sem movimentos neste período.</p>
    @else
        <div class="balanco-chart">
            @foreach($serie as $ponto)
                @php
                    $he = max(2, (int) round(((int) $ponto['entradas'] / $maxSerie) * 100));
                    $hs = max(2, (int) round(((int) $ponto['saidas'] / $maxSerie) * 100));
                @endphp
                <div class="balanco-bar" title="E {{ $ponto['entradas'] }} / S {{ $ponto['saidas'] }}">
                    <div class="pares">
                        <i class="e" style="height:{{ $he }}%"></i>
                        <i class="s" style="height:{{ $hs }}%"></i>
                    </div>
                    <small>{{ $ponto['rotulo'] }}</small>
                </div>
            @endforeach
        </div>
        <p style="margin:8px 0 0;color:var(--suave);font-size:.8rem">
            <span style="color:#17663f"><i class="fa fa-square"></i> Entradas</span>
            · <span style="color:#b86e00"><i class="fa fa-square"></i> Saídas</span>
        </p>
    @endif
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
    <div class="bloco">
        <h2><i class="fa fa-university"></i> Escolas</h2>
        @forelse($topEscolas as $esc)
            @php $pct = max(4, (int) round(((int) $esc['unidades'] / max(1, (int) ($topEscolas[0]['unidades'] ?? 1))) * 100)); @endphp
            <div class="rank-row">
                <div class="lbl"><span>{{ $esc['escola'] }}</span><strong>{{ number_format((int) $esc['unidades'], 0, ',', '.') }} un.</strong></div>
                <div class="rank-bar" style="--tom:var(--cor)"><i style="width:{{ $pct }}%"></i></div>
            </div>
        @empty
            <p style="margin:0;color:var(--suave)">Nenhuma entrega no período.</p>
        @endforelse
    </div>
    <div class="bloco">
        <h2><i class="fa fa-cubes"></i> Itens</h2>
        @forelse($topItens as $item)
            @php
                $tot = (int) $item['entradas'] + (int) $item['saidas'];
                $base = max(1, (int) ($topItens[0]['entradas'] ?? 0) + (int) ($topItens[0]['saidas'] ?? 0));
                $pct = max(4, (int) round(($tot / $base) * 100));
            @endphp
            <div class="rank-row">
                <div class="lbl">
                    <span>{{ $item['titulo'] }} <small style="color:var(--suave)">{{ $item['sku'] }}</small></span>
                    <strong>E{{ (int) $item['entradas'] }}/S{{ (int) $item['saidas'] }}</strong>
                </div>
                <div class="rank-bar" style="--tom:#0d7377"><i style="width:{{ $pct }}%"></i></div>
            </div>
        @empty
            <p style="margin:0;color:var(--suave)">Sem itens movimentados.</p>
        @endforelse
    </div>
</div>

<div class="bloco">
    <h2><i class="fa fa-list"></i> Pedidos por status</h2>
    @if(empty($statusPedidos))
        <p style="margin:0;color:var(--suave)">Nenhum pedido no período.</p>
    @else
        <table>
            <thead><tr><th>Status</th><th>Qtd</th></tr></thead>
            <tbody>
            @foreach($statusPedidos as $st)
                <tr><td>{{ $st['rotulo'] }}</td><td>{{ (int) $st['qtd'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

@if(!empty($criticos))
<div class="bloco">
    <h2><i class="fa fa-exclamation-triangle"></i> Críticos no depósito</h2>
    <table>
        <thead><tr><th>SKU</th><th>Item</th><th>Saldo</th><th>Mínimo</th></tr></thead>
        <tbody>
        @foreach($criticos as $c)
            <tr>
                <td><span class="sku-chip" style="font-family:monospace;font-size:.75rem">{{ $c['sku'] }}</span></td>
                <td>{{ $c['titulo'] }}</td>
                <td>{{ number_format((int) $c['saldo'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $c['minimo'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
