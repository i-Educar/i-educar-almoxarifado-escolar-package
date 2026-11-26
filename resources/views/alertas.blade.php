@extends('almoxarifado::layout')
@section('titulo', 'Alertas de compra')
@section('migalha', 'Alertas')
@section('icone-tela', 'fa-bell')
@section('titulo-tela', 'Alertas de compra')
@section('lead', 'Registre a compra e confira a entrada no depósito. O pedido novo nasce depois dessa conferência — não dá para marcar como entregue daqui.')

@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-shopping-cart"></i> Pedidos</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/itens') }}"><i class="fa fa-cube"></i> Catálogo</a>
@endsection

@section('conteudo')
<form class="buriti-filtro" method="get" action="{{ url('/almoxarifado-escolar/alertas') }}">
    <label>Status
        <select name="status">
            <option value="aberto" @selected(($status ?? '') === 'aberto')>Abertos</option>
            <option value="em_compra" @selected(($status ?? '') === 'em_compra')>Em compra</option>
            <option value="atendido" @selected(($status ?? '') === 'atendido')>Entrada conferida</option>
            <option value="descartado" @selected(($status ?? '') === 'descartado')>Descartados</option>
            <option value="todos" @selected(($status ?? '') === 'todos')>Todos</option>
        </select>
    </label>
    <div class="acoes"><button class="botao" type="submit"><i class="fa fa-filter"></i> Filtrar</button></div>
</form>

<div class="bloco">
    <h2><i class="fa fa-bell"></i> Alertas ({{ count($alertas ?? []) }})</h2>
    @if(empty($alertas))
        <p style="margin:0;color:var(--suave)">Nenhum alerta neste filtro.</p>
    @else
        <div style="overflow:auto">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Motivo</th>
                        <th>Qtd</th>
                        <th>Escola / setor</th>
                        <th>Pedido</th>
                        <th>Status</th>
                        @if(!empty($podeGerir))<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($alertas as $a)
                        @php $p = $a['payload'] ?? []; @endphp
                        <tr>
                            <td>{{ $a['id'] }}</td>
                            <td>
                                <strong>{{ $p['titulo'] ?? $a['titulo'] }}</strong>
                                <div style="color:var(--suave);font-size:.78rem">{{ $p['sku'] ?? '' }}</div>
                            </td>
                            <td>
                                <span class="badge warn">
                                    {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\AlertaCompra::rotuloMotivo((string) ($p['motivo'] ?? '')) }}
                                </span>
                            </td>
                            <td>{{ $p['quantidade'] ?? 0 }}</td>
                            <td>{{ $p['escola'] ?? '—' }}{{ !empty($p['setor']) ? ' / '.$p['setor'] : '' }}</td>
                            <td>
                                @if(!empty($p['pedido_id']))
                                    <a href="{{ url('/almoxarifado-escolar/pedidos/'.$p['pedido_id']) }}">#{{ $p['pedido_id'] }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @php
                                    $stAlerta = (string) ($p['status'] ?? 'aberto');
                                    $rotuloStatus = match ($stAlerta) {
                                        'em_compra' => 'Em compra',
                                        'atendido' => 'Entrada conferida',
                                        'descartado' => 'Descartado',
                                        default => 'Aberto',
                                    };
                                @endphp
                                {{ $rotuloStatus }}
                                @if(!empty($p['pedido_gerado_id']))
                                    <div style="margin-top:4px">
                                        <a href="{{ url('/almoxarifado-escolar/pedidos/'.$p['pedido_gerado_id'].'/nota') }}">Pedido #{{ $p['pedido_gerado_id'] }}</a>
                                    </div>
                                @endif
                            </td>
                            @if(!empty($podeGerir))
                                <td>
                                    @if($stAlerta === 'atendido')
                                        <span style="color:var(--suave);font-size:.8rem">Pedido no fluxo</span>
                                    @else
                                        <div style="display:flex;gap:4px;flex-wrap:wrap">
                                            @if($stAlerta !== 'descartado')
                                                <form method="post" action="{{ url('/almoxarifado-escolar/alertas/'.$a['id']) }}" style="margin:0">
                                                    @csrf
                                                    <input type="hidden" name="status" value="em_compra">
                                                    <button class="botao" type="submit" style="padding:6px 8px">
                                                        <i class="fa fa-shopping-cart"></i>
                                                        {{ $stAlerta === 'em_compra' ? 'Conferir entrada' : 'Registrar compra' }}
                                                    </button>
                                                </form>
                                            @endif
                                            @if($stAlerta !== 'descartado')
                                                <form method="post" action="{{ url('/almoxarifado-escolar/alertas/'.$a['id']) }}" style="margin:0">
                                                    @csrf
                                                    <input type="hidden" name="status" value="descartado">
                                                    <button class="botao claro" type="submit" style="padding:6px 8px">Descartar</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ url('/almoxarifado-escolar/alertas/'.$a['id']) }}" style="margin:0">
                                                    @csrf
                                                    <input type="hidden" name="status" value="aberto">
                                                    <button class="botao claro" type="submit" style="padding:6px 8px">Reabrir</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
