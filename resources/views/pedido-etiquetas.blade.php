@extends('almoxarifado::layout')
@php
    $id = (int) $emit['pedido_id'];
    $itens = $emit['payload']['itens'] ?? [];
    $status = $emit['status'] ?? '';
@endphp
@section('titulo', 'Etiquetas Pedido #'.$id)
@section('migalha', 'Pedidos / Etiquetas #'.$id)
@section('icone-tela', 'fa-tags')
@section('titulo-tela', 'Etiquetas do pedido #'.$id)
@section('lead')
    Imprima e cole no volume. Cada etiqueta traz QR, código e dados do pedido.
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/qr') }}" title="QR" aria-label="QR"><i aria-hidden="true" class="fa fa-qrcode"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/pedidos/'.$id) }}" title="Pedido" aria-label="Pedido"><i aria-hidden="true" class="fa fa-arrow-left"></i></a>
    <button class="botao icone" type="button" onclick="window.print()" title="Imprimir etiquetas" aria-label="Imprimir etiquetas"><i aria-hidden="true" class="fa fa-print"></i></button>
@endsection

@section('conteudo')
    <p style="font-size:.85rem;color:var(--suave,#5b6b76);max-width:42rem;margin:0 0 14px;">
        Etiquetas/QR de remessa só alteram status (entrega) via usuário autenticado com permissão.
        APP: <code>POST /api/apps/v1/equipe/almox/confirmar</code>.
    </p>
<style>
    .etiq-grade {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
    }
    .etiq {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 2px solid #111;
        border-radius: 10px;
        padding: 12px;
        background: #fff;
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 10px;
        align-items: center;
        min-height: 148px;
    }
    .etiq-qr { text-align: center; }
    .etiq-qr canvas, .etiq-qr img { width: 112px !important; height: 112px !important; }
    .etiq-corpo { display: grid; gap: 4px; min-width: 0; }
    .etiq-titulo {
        margin: 0; font-size: .95rem; font-weight: 800; line-height: 1.2;
        color: #111;
    }
    .etiq-linha { margin: 0; font-size: .78rem; color: #222; }
    .etiq-codigo {
        margin: 4px 0 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .85rem; font-weight: 800; letter-spacing: .08em;
        padding: 4px 6px; border: 1px dashed #333; border-radius: 6px; display: inline-block;
    }
    .etiq-selo {
        font-size: .65rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
        color: #555;
    }
    .etiq--item { border-style: dashed; }
    .no-print-hint { margin: 0 0 12px; color: var(--suave); font-size: .85rem; }
    @media print {
        .lateral, .topo, .cabeca-tela, .acoes-tela, .flash, footer, .no-print-hint { display: none !important; }
        .app { display: block; } .miolo, main { padding: 8px !important; }
        .bloco { border: 0; box-shadow: none; padding: 0; margin: 0; }
        .etiq-grade { gap: 8px; grid-template-columns: repeat(2, 1fr); }
        .etiq { border-width: 1.5px; }
    }
</style>

<p class="no-print-hint">
    Etiqueta principal do pedido + uma por item (opcional para volumes separados).
    Código estável: <strong>{{ $emit['codigo'] }}</strong>.
</p>

<div class="bloco">
    <div class="etiq-grade">
        {{-- Etiqueta mestra do pedido --}}
        <article class="etiq" aria-label="Etiqueta do pedido">
            <div class="etiq-qr">
                @include('almoxarifado::partials.qr-canvas', [
                    'qrUrl' => $emit['url'],
                    'qrId' => 'qr-etiq-pedido-'.$id,
                    'qrSize' => 112,
                ])
            </div>
            <div class="etiq-corpo">
                <p class="etiq-selo">Almoxarifado · recebimento</p>
                <h3 class="etiq-titulo">Pedido #{{ $id }}</h3>
                <p class="etiq-linha"><strong>{{ $emit['escola'] }}</strong></p>
                @if(!empty($emit['setor']))
                    <p class="etiq-linha">Setor: {{ $emit['setor'] }}</p>
                @endif
                <p class="etiq-linha">
                    {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status) }}
                    · {{ $emit['itens'] }} item(ns)
                </p>
                <p class="etiq-codigo">{{ $emit['codigo'] }}</p>
            </div>
        </article>

        {{-- Etiquetas por item --}}
        @foreach($itens as $idx => $item)
            <article class="etiq etiq--item" aria-label="Etiqueta item {{ $idx + 1 }}">
                <div class="etiq-qr">
                    @include('almoxarifado::partials.qr-canvas', [
                        'qrUrl' => $emit['url'],
                        'qrId' => 'qr-etiq-item-'.$id.'-'.$idx,
                        'qrSize' => 112,
                    ])
                </div>
                <div class="etiq-corpo">
                    <p class="etiq-selo">Item {{ $idx + 1 }} / {{ count($itens) }} · Pedido #{{ $id }}</p>
                    <h3 class="etiq-titulo">{{ $item['titulo'] ?? $item['sku'] ?? 'Item' }}</h3>
                    <p class="etiq-linha">
                        @if(!empty($item['sku']))SKU {{ $item['sku'] }} · @endif
                        Qtde: <strong>{{ $item['quantidade'] ?? 1 }}</strong>
                    </p>
                    <p class="etiq-linha">{{ $emit['escola'] }}</p>
                    <p class="etiq-codigo">{{ $emit['codigo'] }}</p>
                </div>
            </article>
        @endforeach
    </div>
</div>

@include('almoxarifado::partials.qr-script')
@endsection
