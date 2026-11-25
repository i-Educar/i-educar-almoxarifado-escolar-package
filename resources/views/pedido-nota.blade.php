@extends('almoxarifado::layout')
@php
    $p = $pedido['payload'];
    $id = (int) $pedido['id'];
    $status = $p['status'] ?? 'fechado';
    $proxima = $proxima ?? null;
    $podeAssinar = ! empty($podeAssinar);
    $usuarioLogado = $usuarioLogado ?? ['nome' => '—', 'login' => null];
@endphp
@section('titulo', 'Nota do pedido #'.$id)
@section('migalha', 'Pedidos / Nota #'.$id)
@section('icone-tela', 'fa-file-text-o')
@section('titulo-tela', 'Nota de entrega #'.$id)
@section('lead')
    {{ $p['escola'] ?? '—' }} · {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status) }}
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id) }}"><i class="fa fa-arrow-left"></i> Pedido</a>
    <button class="botao claro" type="button" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
@endsection

@section('conteudo')
<style>
    .nota { background:#fff; border:1px solid var(--linha); border-radius:14px; padding:20px; }
    .nota-cabeca { display:flex; justify-content:space-between; gap:16px; border-bottom:2px solid var(--cor); padding-bottom:12px; margin-bottom:14px; }
    .nota-cabeca h2 { margin:0 0 4px; font-size:1.2rem; }
    .nota-cabeca p { margin:0; color:var(--suave); font-size:.86rem; }
    .assinaturas { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-top:18px; }
    .assinatura { border:1px dashed var(--linha); border-radius:12px; padding:12px; min-height:110px; }
    .assinatura strong { display:block; margin-bottom:8px; color:var(--cor); }
    .assinatura .linha { border-top:1px solid #333; margin-top:36px; padding-top:4px; font-size:.78rem; color:var(--suave); }
    .assinatura .feito { color:var(--ok); font-size:.86rem; margin:0 0 4px; }
    .assinatura .quando { margin:0; font-size:.78rem; color:var(--suave); }
    @media print {
        .lateral, .topo, .cabeca-tela, .acoes-tela, .flash, .bloco-assinar, footer { display:none !important; }
        .app { display:block; } .miolo { padding:0; } main { padding:0; }
        .nota { border:0; box-shadow:none; }
    }
</style>

<div class="nota">
    <div class="nota-cabeca">
        <div>
            <h2>Nota de material — Almoxarifado</h2>
            <p>{{ $municipio ?? '' }}</p>
            <p>Pedido #{{ $id }} · Escola: <strong>{{ $p['escola'] ?? '—' }}</strong></p>
            @if(!empty($p['solicitante']))
                <p>Solicitante: {{ $p['solicitante'] }}</p>
            @endif
        </div>
        <div style="text-align:right">
            <p>Fechado em: {{ $p['fechado_em'] ?? ($pedido['created_at'] ?? '—') }}</p>
            <p>Status: {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status) }}</p>
            @if(!empty($p['baixa_estoque']))
                <p style="color:var(--ok)">Baixa de estoque: sim ({{ $p['baixa_em'] ?? '—' }})</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>SKU</th><th>Item</th><th>Qtd</th></tr>
        </thead>
        <tbody>
            @foreach(($p['itens'] ?? []) as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td>{{ $item['titulo'] }}</td>
                    <td>{{ $item['quantidade'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="assinaturas">
        @foreach($etapas as $etapa)
            @php $a = $p['assinaturas'][$etapa] ?? null; @endphp
            <div class="assinatura">
                <strong>{{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($etapa) }}</strong>
                @if($a)
                    <p class="feito"><i class="fa fa-check"></i> {{ $a['responsavel'] }}@if(!empty($a['login'])) <span style="font-weight:500;color:var(--suave)">({{ $a['login'] }})</span>@endif</p>
                    <p class="quando"><i class="fa fa-clock-o"></i> {{ $a['quando'] ?? '—' }}</p>
                    @if(!empty($a['observacao']))
                        <p style="margin:4px 0 0;font-size:.78rem">{{ $a['observacao'] }}</p>
                    @endif
                @else
                    <div class="linha">Aguardando assinatura digital</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

@if($proxima && $status !== 'carrinho')
    <div class="bloco bloco-assinar" style="margin-top:14px">
        <h2><i class="fa fa-pencil"></i> Registrar {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($proxima) }}</h2>
        @if($proxima === 'entrega')
            <p style="color:var(--suave);margin:0 0 10px">Ao assinar a entrega, o sistema dá baixa automática no estoque dos itens desta nota.</p>
        @endif

        @if($podeAssinar)
            <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/assinar') }}">
                @csrf
                <input type="hidden" name="etapa" value="{{ $proxima }}">
                <div class="campo-fixado">
                    <span>Responsável</span>
                    <strong>{{ $usuarioLogado['nome'] }}</strong>
                    <small>
                        Usuário logado
                        @if(!empty($usuarioLogado['login'])) · matrícula {{ $usuarioLogado['login'] }} @endif
                        · data/hora serão gravadas no momento da assinatura
                    </small>
                </div>
                <label class="col-wide">Observação <input name="observacao" placeholder="Opcional"></label>
                <div class="acoes">
                    <button class="botao" type="submit"><i class="fa fa-check"></i> Assinar {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($proxima) }}</button>
                </div>
            </form>
        @else
            <p class="flash erro" style="margin:0">
                <i class="fa fa-lock"></i>
                Seu perfil não pode assinar «{{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($proxima) }}».
                Permitidos: <code>{{ implode(', ', $niveisEtapa ?? []) }}</code>
                (config <code>almoxarifado.permissoes.assinaturas.{{ $proxima }}</code>).
            </p>
        @endif
    </div>
@endif
@endsection
