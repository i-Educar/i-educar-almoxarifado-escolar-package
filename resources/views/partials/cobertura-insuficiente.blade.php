@if(!empty($semCobertura))
    <div class="bloco" style="border-top-color:#c0392b;background:linear-gradient(135deg,#fff5f5 0%,#fff 55%)">
        <h2 style="color:#c0392b"><i class="fa fa-balance-scale"></i> Saldo insuficiente para a entrega</h2>
        <p style="margin:0 0 12px;color:var(--suave);font-size:.9rem">
            O depósito não cobre a quantidade pedida. Ajuste cada item para liberar a entrega parcial dos demais:
            entrega o que há agora e a falta segue para compra, ou o item sai inteiro deste pedido.
        </p>
        @foreach($semCobertura as $c)
            <div style="margin:0 0 12px;padding:12px;border:1px dashed #f5c6cb;border-radius:10px;background:#fff">
                <strong>{{ $c['sku'] }} — {{ $c['titulo'] }}</strong>
                <p style="margin:4px 0 10px;color:var(--suave);font-size:.85rem">
                    Pedido {{ $c['quantidade'] }} · disponível {{ $c['saldo'] }} · falta {{ $c['falta'] }}
                </p>
                @if(!empty($podeResolverCobertura))
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        @if((int) $c['saldo'] > 0)
                            <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/cobertura') }}" style="margin:0">
                                @csrf
                                <input type="hidden" name="sku" value="{{ $c['sku'] }}">
                                <input type="hidden" name="modo" value="atender_disponivel">
                                <button class="botao" type="submit" style="background:var(--ambar)">
                                    <i class="fa fa-truck"></i>
                                    Entregar {{ $c['saldo'] }} e comprar {{ $c['falta'] }}
                                </button>
                            </form>
                        @endif
                        <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/cobertura') }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="sku" value="{{ $c['sku'] }}">
                            <input type="hidden" name="modo" value="retirar">
                            <button class="botao" type="submit" style="background:#c0392b">
                                <i class="fa fa-minus-circle"></i>
                                Retirar o item e comprar {{ $c['quantidade'] }}
                            </button>
                        </form>
                    </div>
                @else
                    <p style="margin:0;font-size:.85rem;color:var(--suave)">
                        <i class="fa fa-lock"></i>
                        Quem assina a entrega ou tem «Retirar itens faltosos» pode ajustar esta cobertura.
                    </p>
                @endif
            </div>
        @endforeach
    </div>
@endif
