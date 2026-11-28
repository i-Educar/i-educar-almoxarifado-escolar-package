@extends('almoxarifado::layout')
@php
    $panorama = $panorama ?? ['deposito' => [], 'escolas' => [], 'resumo' => []];
    $r = $panorama['resumo'] ?? [];
    $escolaFiltro = $escolaFiltro ?? '';
    $busca = $busca ?? '';
    $situacao = $situacao ?? 'todos';
    $totaisDep = $totaisDep ?? ['todos' => 0, 'criticos' => 0, 'atencao' => 0, 'ok' => 0];
    $escolasOpcoes = $escolasOpcoes ?? [];
    $periodo = $periodo ?? 'mes';
    $de = $de ?? '';
    $ate = $ate ?? '';
    $periodoRotulo = $periodoRotulo ?? '';

    $fmtQuando = static function (?string $raw): string {
        if ($raw === null || $raw === '') {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($raw)->timezone(config('app.timezone', 'America/Sao_Paulo'))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $raw;
        }
    };

    $baseUrl = url('/almoxarifado-escolar/saldos');
    $queryBase = array_filter([
        'q' => $busca !== '' ? $busca : null,
        'escola' => $escolaFiltro !== '' ? $escolaFiltro : null,
        'periodo' => $periodo !== '' ? $periodo : null,
        'de' => $de !== '' ? $de : null,
        'ate' => $ate !== '' ? $ate : null,
    ], static fn ($v) => $v !== null && $v !== '');
    $urlSituacao = static function (string $s) use ($baseUrl, $queryBase): string {
        return $baseUrl.'?'.http_build_query(array_merge($queryBase, ['situacao' => $s]));
    };
    $queryBalanco = http_build_query(array_filter([
        'periodo' => $periodo,
        'de' => $de !== '' ? $de : null,
        'ate' => $ate !== '' ? $ate : null,
        'escola' => $escolaFiltro !== '' ? $escolaFiltro : null,
        'q' => $busca !== '' ? $busca : null,
    ], static fn ($v) => $v !== null && $v !== ''));
    $temFiltroExtra = $busca !== '' || $escolaFiltro !== '' || $situacao !== 'todos' || $periodo !== 'mes';
@endphp
@section('titulo', 'Saldos por escola')
@section('migalha', 'Saldos')
@section('icone-tela', 'fa-bar-chart')
@section('titulo-tela', 'Saldos da rede')
@section('lead')
    Depósito central e material nas escolas. Filtre o estoque e emita o balanço operacional do período.
@endsection
@section('acoes-topo')
    <a class="botao icone" href="{{ url('/almoxarifado-escolar/saldos/balanco?'.$queryBalanco) }}" title="Balanço" aria-label="Balanço"><i aria-hidden="true" class="fa fa-pie-chart"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/saldos/balanco/pdf?'.$queryBalanco) }}" title="Emitir balanço" aria-label="Emitir balanço"><i aria-hidden="true" class="fa fa-file-pdf-o"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/movimentos') }}" title="Movimentos" aria-label="Movimentos"><i aria-hidden="true" class="fa fa-exchange"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/pedidos') }}" title="Pedidos" aria-label="Pedidos"><i aria-hidden="true" class="fa fa-shopping-cart"></i></a>
@endsection

@section('foco')
<section class="buriti-foco">
    <p class="buriti-foco-kicker">Rede</p>
    <h2 class="buriti-foco-titulo">
        {{ number_format((int) ($r['deposito_unidades'] ?? 0), 0, ',', '.') }} un. no depósito
        @if(($r['criticos'] ?? 0) > 0)
            · {{ (int) $r['criticos'] }} crítico{{ (int) $r['criticos'] === 1 ? '' : 's' }}
        @endif
    </h2>
    <p class="buriti-foco-texto">
        {{ (int) ($r['escolas_com_material'] ?? 0) }} escola(s) com material ·
        {{ (int) ($r['pedidos_transito'] ?? 0) }} pedido(s) em trânsito ·
        balanço: {{ $periodoRotulo }}
    </p>
</section>
@endsection

@section('conteudo')
<style>
    .saldos-barra { margin: 0 0 14px; display: grid; gap: 10px; }
    .saldos-chips { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .saldos-chip {
        display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 12px;
        text-decoration: none; color: var(--tinta); background: var(--superficie, #fff);
        border: 1px solid color-mix(in srgb, var(--chip, var(--cor-tela)) 28%, var(--linha));
        border-top: 3px solid var(--chip, var(--cor-tela));
        box-shadow: 0 6px 14px rgba(21,34,43,.04);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .saldos-chip i { color: var(--chip, var(--cor-tela)); width: 16px; text-align: center; }
    .saldos-chip span { flex: 1; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--suave); }
    .saldos-chip strong { font-size: 1.1rem; }
    .saldos-chip:hover { transform: translateY(-1px); }
    .saldos-chip.is-ativo {
        background: color-mix(in srgb, var(--chip, var(--cor-tela)) 10%, #fff);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--chip, var(--cor-tela)) 18%, transparent);
    }
    .saldos-filtro { margin: 0; }
    .saldos-busca { flex: 1.3 1 200px; max-width: none !important; }
    .saldos-busca-campo { position: relative; display: block; }
    .saldos-busca-campo > i {
        position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
        color: var(--suave); pointer-events: none;
    }
    .saldos-busca-campo input { padding-left: 32px !important; }
    .barra-cobertura { height: 8px; border-radius: 99px; background: #e8eef2; overflow: hidden; margin-top: 6px; }
    .barra-cobertura i {
        display: block; height: 100%; border-radius: 99px;
        background: linear-gradient(90deg, var(--tom, var(--ok)), color-mix(in srgb, var(--tom, var(--ok)) 70%, #fff));
    }
    .escola-card {
        border: 1px solid var(--linha); border-radius: 14px; padding: 0; overflow: hidden; margin: 0 0 12px;
        background: #fff; box-shadow: 0 8px 18px rgba(21,34,43,.04);
    }
    .escola-card > summary {
        list-style: none; cursor: pointer; display: grid;
        grid-template-columns: 44px 1fr auto; gap: 12px; align-items: center;
        padding: 14px 16px; background: linear-gradient(120deg, color-mix(in srgb, var(--clara) 55%, #fff), #fff);
    }
    .escola-card > summary::-webkit-details-marker { display: none; }
    .escola-card .esc-ico {
        width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; color: #fff;
        background: linear-gradient(145deg, var(--destaque), var(--cor));
    }
    .escola-card h3 { margin: 0; font-size: 1rem; }
    .escola-card .esc-meta { margin: 2px 0 0; color: var(--suave); font-size: .8rem; }
    .escola-card .esc-kpis { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
    .escola-card .corpo { padding: 0 16px 16px; border-top: 1px dashed var(--linha); }
    .escola-card table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .escola-card th, .escola-card td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--linha); font-size: .86rem; }
    .escola-card th { color: var(--suave); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }
    .deposito-table { width: 100%; border-collapse: collapse; }
    .deposito-table th, .deposito-table td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--linha); vertical-align: top; }
    .deposito-table th { color: var(--suave); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }
    .deposito-table tr.is-critico { background: color-mix(in srgb, var(--erro-fundo) 55%, #fff); }
    .sku-chip {
        display: inline-block; font-family: ui-monospace, monospace; font-size: .72rem; font-weight: 700;
        padding: 2px 6px; border-radius: 6px; background: #eef2f5; color: #475569;
    }
    .saldos-balanco-cta {
        display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between;
        padding: 12px 14px; border-radius: 12px; margin: 0 0 14px;
        border: 1px solid color-mix(in srgb, var(--cor-tela) 30%, var(--linha));
        background: linear-gradient(120deg, color-mix(in srgb, var(--clara) 60%, #fff), #fff);
    }
    .saldos-balanco-cta strong { display: block; font-size: .95rem; }
    .saldos-balanco-cta span { color: var(--suave); font-size: .82rem; }
    .saldos-balanco-cta .acoes { display: flex; flex-wrap: wrap; gap: 8px; }
    @media (max-width: 860px) {
        .saldos-chips { grid-template-columns: 1fr 1fr; }
        .escola-card > summary { grid-template-columns: 44px 1fr; }
        .escola-card .esc-kpis { grid-column: 1 / -1; justify-content: flex-start; }
    }
</style>

<div class="numeros">
    <article style="--tom: var(--destaque)">
        <div class="kpi-ico"><i class="fa fa-database"></i></div>
        <span>No depósito</span>
        <strong>{{ number_format((int) ($r['deposito_unidades'] ?? 0), 0, ',', '.') }}</strong>
    </article>
    <article style="--tom: {{ ($r['criticos'] ?? 0) > 0 ? 'var(--erro)' : 'var(--ok)' }}">
        <div class="kpi-ico"><i class="fa fa-exclamation-triangle"></i></div>
        <span>Itens críticos</span>
        <strong>{{ (int) ($r['criticos'] ?? 0) }}</strong>
    </article>
    <article style="--tom: var(--ambar)">
        <div class="kpi-ico"><i class="fa fa-exchange"></i></div>
        <span>Pedidos em trânsito</span>
        <strong>{{ (int) ($r['pedidos_transito'] ?? 0) }}</strong>
    </article>
    <article style="--tom: var(--ok)">
        <div class="kpi-ico"><i class="fa fa-university"></i></div>
        <span>Escolas com material</span>
        <strong>{{ (int) ($r['escolas_com_material'] ?? 0) }}</strong>
    </article>
    <article style="--tom: var(--cor)">
        <div class="kpi-ico"><i class="fa fa-cubes"></i></div>
        <span>Unidades entregues</span>
        <strong>{{ number_format((int) ($r['unidades_entregues'] ?? 0), 0, ',', '.') }}</strong>
    </article>
</div>

<div class="saldos-barra">
    <div class="saldos-chips" role="tablist" aria-label="Situação do depósito">
        <a href="{{ $urlSituacao('todos') }}" class="saldos-chip{{ $situacao === 'todos' ? ' is-ativo' : '' }}" style="--chip:var(--cor-tela,#4a5a6a)">
            <i class="fa fa-list"></i><span>Todos</span><strong>{{ (int) $totaisDep['todos'] }}</strong>
        </a>
        <a href="{{ $urlSituacao('criticos') }}" class="saldos-chip{{ $situacao === 'criticos' ? ' is-ativo' : '' }}" style="--chip:#9b2c2c">
            <i class="fa fa-exclamation-triangle"></i><span>Críticos</span><strong>{{ (int) $totaisDep['criticos'] }}</strong>
        </a>
        <a href="{{ $urlSituacao('atencao') }}" class="saldos-chip{{ $situacao === 'atencao' ? ' is-ativo' : '' }}" style="--chip:#b86e00">
            <i class="fa fa-clock-o"></i><span>Atenção</span><strong>{{ (int) $totaisDep['atencao'] }}</strong>
        </a>
        <a href="{{ $urlSituacao('ok') }}" class="saldos-chip{{ $situacao === 'ok' ? ' is-ativo' : '' }}" style="--chip:#17663f">
            <i class="fa fa-check-circle"></i><span>OK</span><strong>{{ (int) $totaisDep['ok'] }}</strong>
        </a>
    </div>

    <form class="buriti-filtro saldos-filtro" method="get" action="{{ $baseUrl }}" role="search">
        <input type="hidden" name="situacao" value="{{ $situacao }}">
        <label class="saldos-busca">
            Buscar item
            <span class="saldos-busca-campo">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $busca }}" placeholder="SKU ou nome do item" autocomplete="off">
            </span>
        </label>
        <label>
            Escola
            <select name="escola">
                <option value="">Todas</option>
                @foreach($escolasOpcoes as $esc)
                    <option value="{{ $esc }}" @selected($escolaFiltro === $esc)>{{ $esc }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Período do balanço
            <select name="periodo" id="saldo-periodo">
                <option value="7d" @selected($periodo === '7d')>Últimos 7 dias</option>
                <option value="30d" @selected($periodo === '30d')>Últimos 30 dias</option>
                <option value="mes" @selected($periodo === 'mes')>Mês atual</option>
                <option value="trimestre" @selected($periodo === 'trimestre')>Trimestre</option>
                <option value="ano" @selected($periodo === 'ano')>Ano atual</option>
                <option value="custom" @selected($periodo === 'custom')>Personalizado</option>
            </select>
        </label>
        <label>
            De
            <input type="date" name="de" value="{{ $de }}" id="saldo-de">
        </label>
        <label>
            Até
            <input type="date" name="ate" value="{{ $ate }}" id="saldo-ate">
        </label>
        <div class="acoes">
            <button class="botao" type="submit"><i class="fa fa-filter"></i> Filtrar</button>
            @if($temFiltroExtra)
                <a class="botao claro" href="{{ $baseUrl }}"><i class="fa fa-times"></i> Limpar</a>
            @endif
        </div>
    </form>
</div>

<div class="saldos-balanco-cta">
    <div>
        <strong><i class="fa fa-pie-chart" style="color:var(--cor-tela)"></i> Balanço operacional</strong>
        <span>Documento com KPIs, gráficos de movimentação e ranking de escolas/itens · {{ $periodoRotulo }}</span>
    </div>
    <div class="acoes">
        <a class="botao claro" href="{{ url('/almoxarifado-escolar/saldos/balanco?'.$queryBalanco) }}">
            <i class="fa fa-eye"></i> Ver balanço
        </a>
        <a class="botao" href="{{ url('/almoxarifado-escolar/saldos/balanco/pdf?'.$queryBalanco) }}">
            <i class="fa fa-file-pdf-o"></i> Emitir PDF
        </a>
    </div>
</div>

<div class="bloco">
    <h2>
        <i class="fa fa-building"></i> Depósito central
        <span class="texto-suave" style="font-weight:500;font-size:.85rem;margin-left:6px">({{ count($panorama['deposito'] ?? []) }})</span>
    </h2>
    @if(empty($panorama['deposito']))
        <p style="margin:0;color:var(--suave)">
            @if($temFiltroExtra)
                Nenhum item neste filtro.
            @else
                Nenhum item no catálogo. Cadastre no catálogo ou registre uma entrada.
            @endif
        </p>
    @else
        <table class="deposito-table">
            <thead>
            <tr>
                <th>Item</th>
                <th>Saldo</th>
                <th>Mínimo</th>
                <th>Cobertura</th>
                <th>Situação</th>
            </tr>
            </thead>
            <tbody>
            @foreach($panorama['deposito'] as $item)
                @php
                    $pct = min(100, (int) round(min(2, max(0, $item['cobertura'])) / 2 * 100));
                    $tom = $item['critico'] ? 'var(--erro)' : ($item['cobertura'] < 1.2 ? 'var(--ambar)' : 'var(--ok)');
                @endphp
                <tr class="{{ $item['critico'] ? 'is-critico' : '' }}">
                    <td>
                        <strong>{{ $item['titulo'] }}</strong><br>
                        <span class="sku-chip">{{ $item['sku'] }}</span>
                    </td>
                    <td><strong>{{ number_format($item['saldo'], 0, ',', '.') }}</strong> un.</td>
                    <td>{{ number_format($item['minimo'], 0, ',', '.') }} un.</td>
                    <td style="min-width:140px">
                        {{ number_format($item['cobertura'], 2, ',', '.') }}× o mínimo
                        <div class="barra-cobertura" style="--tom: {{ $tom }}"><i style="width:{{ $pct }}%"></i></div>
                    </td>
                    <td>
                        @if($item['critico'])
                            <span class="badge fail"><i class="fa fa-exclamation-triangle"></i> Crítico</span>
                        @elseif($item['cobertura'] < 1.2)
                            <span class="badge warn"><i class="fa fa-clock-o"></i> Atenção</span>
                        @else
                            <span class="badge ok"><i class="fa fa-check"></i> OK</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="bloco">
    <h2><i class="fa fa-university"></i> Material por escola</h2>
    <p style="margin:0 0 12px;color:var(--suave);font-size:.86rem">
        Quantidades acumuladas dos pedidos já entregues/conferidos. Expanda a escola para ver o detalhe.
    </p>

    @forelse($panorama['escolas'] as $esc)
        <details class="escola-card" @if($escolaFiltro !== '' || count($panorama['escolas']) === 1) open @endif>
            <summary>
                <span class="esc-ico"><i class="fa fa-university"></i></span>
                <div>
                    <h3>{{ $esc['escola'] }}</h3>
                    <p class="esc-meta">
                        Última entrega: {{ $fmtQuando($esc['ultima_entrega'] ?? null) }}
                        · {{ (int) $esc['pedidos_entregues'] }} pedido(s) entregue(s)
                    </p>
                </div>
                <div class="esc-kpis">
                    <span class="badge ok">{{ number_format((int) $esc['unidades'], 0, ',', '.') }} un. na escola</span>
                    <span class="badge warn">{{ (int) $esc['skus'] }} SKU(s)</span>
                    @if(($esc['pedidos_transito'] ?? 0) > 0)
                        <span class="badge warn">{{ (int) $esc['pedidos_transito'] }} em trânsito</span>
                    @endif
                </div>
            </summary>
            <div class="corpo">
                @if(empty($esc['itens']))
                    <p style="margin:12px 0 0;color:var(--suave)">Ainda sem material entregue nesta unidade{{ $busca !== '' ? ' para o filtro atual' : '' }}.</p>
                @else
                    <table>
                        <thead>
                        <tr><th>Item</th><th>SKU</th><th>Qtd na escola</th></tr>
                        </thead>
                        <tbody>
                        @foreach($esc['itens'] as $it)
                            <tr>
                                <td>{{ $it['titulo'] }}</td>
                                <td><span class="sku-chip">{{ $it['sku'] }}</span></td>
                                <td><strong>{{ number_format((int) $it['quantidade'], 0, ',', '.') }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($esc['transito']))
                    <h3 style="margin:16px 0 8px;font-size:.9rem"><i class="fa fa-truck"></i> Em trânsito</h3>
                    <ul style="margin:0;padding-left:18px;color:var(--suave)">
                        @foreach($esc['transito'] as $t)
                            <li>
                                <a href="{{ url('/almoxarifado-escolar/pedidos/'.$t['pedido_id']) }}">Pedido #{{ $t['pedido_id'] }}</a>
                                — {{ $t['status'] }} · {{ $t['itens'] }} item(ns) · {{ $t['unidades'] }} un.
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p style="margin:14px 0 0">
                    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos?escola='.urlencode($esc['escola'])) }}">
                        <i class="fa fa-shopping-cart"></i> Pedidos desta escola
                    </a>
                </p>
            </div>
        </details>
    @empty
        <p style="margin:0;color:var(--suave)">
            @if($escolaFiltro !== '')
                Nenhuma movimentação encontrada para «{{ $escolaFiltro }}».
            @else
                Ainda não há pedidos com entrega registrada.
            @endif
        </p>
    @endforelse
</div>

<script>
(function () {
    var sel = document.getElementById('saldo-periodo');
    var de = document.getElementById('saldo-de');
    var ate = document.getElementById('saldo-ate');
    if (!sel || !de || !ate) return;
    function sync() {
        var custom = sel.value === 'custom';
        de.disabled = !custom;
        ate.disabled = !custom;
        de.parentElement.style.opacity = custom ? '1' : '.55';
        ate.parentElement.style.opacity = custom ? '1' : '.55';
    }
    sel.addEventListener('change', function () {
        if (sel.value === 'custom') {
            // marca datas atuais se vazias
        }
        sync();
    });
    sync();
})();
</script>
@endsection
