@extends('almoxarifado::layout')
@php
    $panorama = $panorama ?? ['deposito' => [], 'escolas' => [], 'resumo' => []];
    $r = $panorama['resumo'] ?? [];
    $escolaFiltro = $escolaFiltro ?? '';
    $busca = $busca ?? '';
    $escolasOpcoes = $escolasOpcoes ?? [];

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
@endphp
@section('titulo', 'Saldos por escola')
@section('migalha', 'Saldos')
@section('icone-tela', 'fa-bar-chart')
@section('titulo-tela', 'Saldos da rede')
@section('lead')
    Depósito central da SME e material já entregue em cada escola — o que ainda pode sair e o que já está na ponta.
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/movimentos') }}"><i class="fa fa-exchange"></i> Movimentos</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-shopping-cart"></i> Pedidos</a>
    <a class="botao" href="{{ url('/almoxarifado-escolar') }}"><i class="fa fa-dashboard"></i> Painel</a>
@endsection

@section('conteudo')
<style>
    .saldo-modelo {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;
        margin: 0 0 16px;
    }
    .saldo-modelo article {
        padding: 12px 14px; border-radius: 12px; border: 1px solid var(--linha);
        background: linear-gradient(165deg, color-mix(in srgb, var(--clara) 45%, #fff), #fff);
    }
    .saldo-modelo strong { display: block; font-size: .9rem; margin: 0 0 4px; }
    .saldo-modelo span { display: block; color: var(--suave); font-size: .8rem; line-height: 1.4; }
    .saldo-filtros {
        display: grid; grid-template-columns: minmax(180px, 1.2fr) minmax(160px, 1fr) auto;
        gap: 10px; align-items: end; margin: 0 0 4px;
    }
    .saldo-filtros label { display: grid; gap: 4px; font-size: .78rem; font-weight: 700; color: var(--suave); }
    .saldo-filtros input, .saldo-filtros select {
        min-height: 38px; padding: 8px 10px; border: 1px solid var(--linha); border-radius: 10px; font: inherit; background: #fff;
    }
    .saldo-filtros .acoes { display: flex; gap: 8px; flex-wrap: wrap; }
    .barra-cobertura {
        height: 8px; border-radius: 99px; background: #e8eef2; overflow: hidden; margin-top: 6px;
    }
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
    @media (max-width: 720px) {
        .saldo-filtros { grid-template-columns: 1fr; }
        .escola-card > summary { grid-template-columns: 44px 1fr; }
        .escola-card .esc-kpis { grid-column: 1 / -1; justify-content: flex-start; }
    }
</style>

    <div class="saldo-modelo">
        <article>
            <strong><i class="fa fa-building" style="color:var(--destaque)"></i> Depósito central</strong>
            <span>Estoque da SME disponível para pedidos das escolas. Crítico = abaixo do mínimo.</span>
        </article>
        <article>
            <strong><i class="fa fa-truck" style="color:var(--destaque)"></i> Em trânsito</strong>
            <span>Pedidos fechados/separados/aceitos que ainda não baixaram estoque na entrega.</span>
        </article>
        <article>
            <strong><i class="fa fa-university" style="color:var(--destaque)"></i> Na escola</strong>
            <span>Soma do que já foi entregue/conferido — material que saiu do depósito para a unidade.</span>
        </article>
    </div>

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

    <div class="bloco">
        <h2><i class="fa fa-filter" style="color:var(--destaque)"></i> Filtrar</h2>
        <form class="saldo-filtros" method="get" action="{{ url('/almoxarifado-escolar/saldos') }}">
            <label>Escola
                <select name="escola">
                    <option value="">Todas as escolas</option>
                    @foreach($escolasOpcoes as $esc)
                        <option value="{{ $esc }}" @selected($escolaFiltro === $esc)>{{ $esc }}</option>
                    @endforeach
                </select>
            </label>
            <label>Item / SKU
                <input type="search" name="q" value="{{ $busca }}" placeholder="Ex.: CAD-A4 ou caderno">
            </label>
            <div class="acoes">
                <button class="botao" type="submit"><i class="fa fa-search"></i> Aplicar</button>
                @if($escolaFiltro !== '' || $busca !== '')
                    <a class="botao claro" href="{{ url('/almoxarifado-escolar/saldos') }}"><i class="fa fa-times"></i> Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="bloco">
        <h2><i class="fa fa-building" style="color:var(--destaque)"></i> Depósito central</h2>
        @if(empty($panorama['deposito']))
            <p style="margin:0;color:var(--suave)">Nenhum item no catálogo{{ $busca !== '' ? ' com este filtro' : '' }}. Cadastre no catálogo ou registre uma entrada.</p>
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
        <h2><i class="fa fa-university" style="color:var(--destaque)"></i> Material por escola</h2>
        <p style="margin:0 0 12px;color:var(--suave);font-size:.86rem">
            Quantidades acumuladas dos pedidos já entregues/conferidos (baixa no depósito).
            Expandir a escola para ver o detalhe por SKU e o que ainda está em trânsito.
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
                    Ainda não há pedidos com entrega registrada. Quando um pedido for entregue, o material aparece aqui por escola.
                @endif
            </p>
        @endforelse
    </div>
@endsection
