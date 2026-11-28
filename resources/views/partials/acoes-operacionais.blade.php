{{-- Ações do depósito: separado → em trânsito (notifica solicitante) → libera assinatura de entrega --}}
@php
    $idOp = (int) ($id ?? 0);
    $acaoOp = $proximaAcaoOperacional ?? null;
    $podeOp = ! empty($podeOperarDeposito);
    $finalizadoOp = ! empty($finalizado);
    $bloqueioOp = ! empty($bloqueioEntrega);
@endphp
@if($idOp > 0 && $podeOp && ! $finalizadoOp && ! $bloqueioOp && in_array($acaoOp, ['marcar_separado', 'emitir_remessa'], true))
    <div class="bloco pedido-acoes-operacionais" style="border-top-color:{{ $acaoOp === 'emitir_remessa' ? '#0b6e4f' : '#2a6f97' }};background:linear-gradient(135deg,{{ $acaoOp === 'emitir_remessa' ? '#e8f6f1' : '#eef6fb' }} 0%,#fff 55%)">
        @if($acaoOp === 'marcar_separado')
            <h2 style="color:#2a6f97"><i class="fa fa-cubes"></i> Próximo passo: separar o material</h2>
            <p style="margin:0 0 12px;color:var(--suave);font-size:.9rem">
                Operação do depósito (sem assinatura no PDF). Ao concluir, registre <strong>Em trânsito</strong> para avisar quem pediu os itens.
            </p>
            <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$idOp.'/marcar-separado') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                @csrf
                <button class="botao" type="submit"><i class="fa fa-check"></i> Marcar separado</button>
                <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$idOp.'/nota') }}"><i class="fa fa-file-text-o"></i> Abrir nota</a>
            </form>
        @else
            <h2 style="color:#0b6e4f"><i class="fa fa-truck"></i> Próximo passo: remessa / Em trânsito</h2>
            <p style="margin:0 0 12px;color:var(--suave);font-size:.9rem">
                Material separado. Marque <strong>Em trânsito</strong> para notificar o solicitante.
                A assinatura de <strong>entrega</strong> só libera depois deste passo.
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$idOp.'/em-transito') }}" style="margin:0">
                    @csrf
                    <button class="botao" type="submit" style="background:#0b6e4f">
                        <i class="fa fa-exchange"></i> Marcar em trânsito e notificar
                    </button>
                </form>
                <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$idOp.'/qr') }}"><i class="fa fa-qrcode"></i> Remessa / QR</a>
                <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$idOp.'/etiquetas') }}"><i class="fa fa-tags"></i> Etiquetas</a>
            </div>
        @endif
    </div>
@elseif($idOp > 0 && ! $finalizadoOp && ! empty($aguardandoRemessa) && ! $podeOp)
    <div class="bloco" style="border-top-color:var(--ambar)">
        <h2 style="color:var(--ambar)"><i class="fa fa-hourglass-half"></i> Aguardando remessa do depósito</h2>
        <p style="margin:0;color:var(--suave);font-size:.9rem">
            A entrega só pode ser assinada depois que o almoxarifado marcar <strong>Em trânsito</strong> (e notificar quem solicitou).
        </p>
    </div>
@endif
