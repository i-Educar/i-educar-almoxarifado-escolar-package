@extends('almoxarifado::layout')
@php
    $p = $pedido['payload'];
    $status = $p['status'] ?? 'carrinho';
    $id = (int) $pedido['id'];
@endphp
@section('titulo', 'Pedido #'.$id)
@section('migalha', 'Pedidos / #'.$id)
@section('icone-tela', 'fa-shopping-cart')
@section('titulo-tela', 'Pedido #'.$id.' — '.$p['escola'])
@section('lead')
    Status: {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status) }}
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-arrow-left"></i> Pedidos</a>
    @if($status !== 'carrinho')
        <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}"><i class="fa fa-file-text-o"></i> Nota</a>
    @endif
@endsection

@section('conteudo')

    @if($status === 'carrinho')
        <div class="bloco">
            <h2><i class="fa fa-plus"></i> Adicionar ao carrinho</h2>
            <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/itens') }}">
                @csrf
                <label class="col-wide">Item
                    <input name="item" required list="almox-itens" placeholder="SKU ou nome">
                    <datalist id="almox-itens">
                        @foreach(($itensCatalogo ?? []) as $i)
                            <option value="{{ $i['sku'] }} · {{ $i['titulo'] }}">saldo {{ $i['saldo'] }}</option>
                        @endforeach
                    </datalist>
                </label>
                <label>Quantidade <input type="number" name="quantidade" min="1" required value="1"></label>
                <div class="acoes"><button class="botao" type="submit"><i class="fa fa-cart-plus"></i> Incluir</button></div>
            </form>
        </div>
    @endif

    <div class="bloco">
        <h2><i class="fa fa-list"></i> Itens do pedido</h2>
        @if(empty($p['itens']))
            <p style="color:var(--suave);margin:0">Carrinho vazio — inclua itens acima.</p>
        @else
            <table>
                <thead>
                    <tr><th>SKU</th><th>Item</th><th>Qtd</th>@if($status === 'carrinho')<th></th>@endif</tr>
                </thead>
                <tbody>
                    @foreach($p['itens'] as $item)
                        <tr>
                            <td>{{ $item['sku'] }}</td>
                            <td>{{ $item['titulo'] }}</td>
                            <td>{{ $item['quantidade'] }}</td>
                            @if($status === 'carrinho')
                                <td>
                                    <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/itens/remover') }}" style="margin:0">
                                        @csrf
                                        <input type="hidden" name="sku" value="{{ $item['sku'] }}">
                                        <button class="botao claro" type="submit" title="Remover"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($status === 'carrinho' && !empty($p['itens']))
            <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/fechar') }}" style="margin-top:14px;display:flex;justify-content:flex-end">
                @csrf
                <button class="botao" type="submit"><i class="fa fa-lock"></i> Fechar pedido</button>
            </form>
        @endif
    </div>

    @if($status !== 'carrinho')
        <div class="bloco">
            <h2><i class="fa fa-check-square-o"></i> Assinaturas</h2>
            <ul style="margin:0;padding-left:18px;color:var(--suave)">
                @foreach($etapas as $etapa)
                    @php $a = $p['assinaturas'][$etapa] ?? null; @endphp
                    <li style="margin:4px 0">
                        <strong>{{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($etapa) }}:</strong>
                        @if($a)
                            {{ $a['responsavel'] }}@if(!empty($a['login'])) ({{ $a['login'] }})@endif · {{ $a['quando'] }}
                        @else
                            pendente
                        @endif
                    </li>
                @endforeach
            </ul>
            <p style="margin:12px 0 0">
                <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}"><i class="fa fa-pencil-square-o"></i> Emitir / assinar nota</a>
            </p>
        </div>
    @endif
@endsection
