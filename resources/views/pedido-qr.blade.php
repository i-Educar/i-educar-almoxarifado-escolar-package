@extends('almoxarifado::layout')
@php
    $id = (int) $emit['pedido_id'];
    $status = $emit['status'] ?? '';
@endphp
@section('titulo', 'QR Pedido #'.$id)
@section('migalha', 'Pedidos / QR #'.$id)
@section('icone-tela', 'fa-qrcode')
@section('titulo-tela', 'QR e etiquetas — Pedido #'.$id)
@section('lead')
    {{ $emit['escola'] }} · {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status) }}
    · código {{ $emit['codigo'] }}
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id) }}"><i class="fa fa-arrow-left"></i> Pedido</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}"><i class="fa fa-file-text-o"></i> Nota</a>
    <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/etiquetas') }}"><i class="fa fa-tags"></i> Emitir etiquetas</a>
    <button class="botao claro" type="button" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
@endsection

@section('conteudo')
<style>
    .almox-qr-painel {
        display: grid; gap: 16px; grid-template-columns: minmax(240px, 280px) 1fr;
        align-items: start;
    }
    @media (max-width: 720px) { .almox-qr-painel { grid-template-columns: 1fr; } }
    .almox-qr-card {
        background: #fff; border: 1px solid var(--linha); border-radius: 14px;
        padding: 16px; text-align: center; border-top: 3px solid var(--cor-tela, var(--cor));
    }
    .almox-qr-meta { display: grid; gap: 10px; }
    .almox-qr-meta dl {
        display: grid; grid-template-columns: 110px 1fr; gap: 6px 10px; margin: 0;
        font-size: .9rem;
    }
    .almox-qr-meta dt { color: var(--suave); font-weight: 700; }
    .almox-qr-meta dd { margin: 0; font-weight: 600; word-break: break-word; }
    .almox-qr-codigo {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 1.15rem; letter-spacing: .12em; font-weight: 800;
        padding: 10px 12px; border-radius: 10px; background: var(--clara);
        border: 1px dashed var(--linha); text-align: center;
    }
    .almox-qr-url {
        font-size: .72rem; color: var(--suave); word-break: break-all;
        padding: 8px 10px; background: #f7f9fb; border-radius: 8px; border: 1px solid var(--linha);
    }
    @media print {
        .lateral, .topo, .cabeca-tela, .acoes-tela, .flash, footer, .no-print { display: none !important; }
        .app { display: block; } .miolo, main { padding: 0 !important; }
        .almox-qr-painel { grid-template-columns: 240px 1fr; }
    }
</style>

<div class="bloco">
    <div class="almox-qr-painel">
        <div class="almox-qr-card">
            <p style="margin:0 0 8px;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--cor-tela)">
                Escaneie para confirmar
            </p>
            @include('almoxarifado::partials.qr-canvas', [
                'qrUrl' => $emit['url'],
                'qrId' => 'qr-pedido-'.$id,
                'qrSize' => 240,
            ])
            <p class="almox-qr-codigo" style="margin:12px 0 0">{{ $emit['codigo'] }}</p>
        </div>
        <div class="almox-qr-meta">
            <h2 style="margin:0"><i class="fa fa-info-circle"></i> Detalhes do pedido</h2>
            <dl>
                <dt>Pedido</dt><dd>#{{ $id }}</dd>
                <dt>Escola</dt><dd>{{ $emit['escola'] }}</dd>
                @if(!empty($emit['setor']))
                    <dt>Setor</dt><dd>{{ $emit['setor'] }}</dd>
                @endif
                <dt>Situação</dt>
                <dd>{{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status) }}</dd>
                <dt>Itens</dt><dd>{{ $emit['itens'] }}</dd>
                <dt>Código</dt><dd><code>{{ $emit['codigo'] }}</code></dd>
            </dl>
            <div class="almox-qr-url">{{ $emit['url'] }}</div>
            <p style="margin:0;color:var(--suave);font-size:.85rem">
                O código permanece o mesmo em reimpressões. Use <strong>Emitir etiquetas</strong>
                para colar no volume/caixa com QR + dados do pedido.
            </p>
            <div class="no-print" style="display:flex;flex-wrap:wrap;gap:8px">
                <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/etiquetas') }}">
                    <i class="fa fa-tags"></i> Emitir etiquetas
                </a>
                <button class="botao claro" type="button" onclick="window.print()">
                    <i class="fa fa-print"></i> Imprimir esta página
                </button>
            </div>
        </div>
    </div>
</div>

@include('almoxarifado::partials.qr-script')
@endsection
