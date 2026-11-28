@extends('almoxarifado::layout')
@php
    $p = $pedido['payload'];
    $status = $p['status'] ?? 'carrinho';
    $id = (int) $pedido['id'];
    $setor = $p['setor'] ?? null;
    $faltosos = $faltosos ?? [];
    $bloqueioEntrega = ! empty($bloqueioEntrega);
    $podeRetirarFaltosos = ! empty($podeRetirarFaltosos);
    $naoAtendidos = $p['itens_nao_atendidos'] ?? [];
    $finalizado = ! empty($finalizado);
    $podeEmitirRemessa = ! empty($podeEmitirRemessa);
    $aguardandoReposicao = $aguardandoReposicao ?? [];
    $podeGerarReposicao = ! empty($podeGerarReposicao);
    $visual = $visual ?? \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::visualStatus($status, $p);
    $statusNorm = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::normalizarStatus($status);
    $ordemStatus = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::ORDEM_CICLO;
    $assinaturas = is_array($p['assinaturas'] ?? null) ? $p['assinaturas'] : [];
    $idxStatus = array_search($statusNorm, $ordemStatus, true);
    if ($idxStatus === false) { $idxStatus = 0; }
    // Trilho baseado em assinaturas + status (aceite antes da separação).
    $trilhoFeito = [
        'solicitado' => $statusNorm !== 'carrinho',
        'aceito' => ! empty($assinaturas['aceite']) || in_array($statusNorm, ['aceito','separacao','separado','em_transito','entregue','conferido'], true),
        'separacao' => (! empty($assinaturas['aceite']) && $statusNorm === 'separacao') || $statusNorm === 'separacao' || in_array($statusNorm, ['separado','em_transito','entregue','conferido'], true),
        'separado' => ! empty($p['separado_em']) || (! empty($assinaturas['separacao'])) || in_array($statusNorm, ['separado','em_transito','entregue','conferido'], true),
        'em_transito' => $statusNorm === 'em_transito' || ! empty($p['em_transito_em']) || in_array($statusNorm, ['entregue','conferido'], true),
        'entregue' => ! empty($assinaturas['entrega']) || in_array($statusNorm, ['entregue','conferido'], true),
        'conferido' => $statusNorm === 'conferido' || ! empty($p['finalizado']),
    ];
    $trilhoAtual = match (true) {
        $statusNorm === 'conferido' || ! empty($p['finalizado']) => 'conferido',
        ! empty($assinaturas['entrega']) || $statusNorm === 'entregue' => 'entregue',
        $statusNorm === 'em_transito' || ! empty($p['em_transito_em']) => 'em_transito',
        ! empty($assinaturas['separacao']) || $statusNorm === 'separado' => 'separado',
        $statusNorm === 'separacao' || (! empty($assinaturas['aceite']) && empty($assinaturas['separacao'])) => 'separacao',
        ! empty($assinaturas['aceite']) || $statusNorm === 'aceito' => 'aceito',
        default => 'solicitado',
    };
@endphp
@section('titulo', 'Pedido #'.$id)
@section('migalha', 'Pedidos / #'.$id)
@section('icone-tela', $visual['icone'])
@section('titulo-tela', 'Pedido #'.$id.' — '.$p['escola'].($setor ? ' / '.$setor : ''))
@section('lead')
    @if($setor) Setor {{ $setor }} · @endif
    Controle de materiais e qualidade do ciclo de atendimento
@endsection
@section('acoes-topo')
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/pedidos') }}" title="Pedidos" aria-label="Pedidos"><i aria-hidden="true" class="fa fa-arrow-left"></i></a>
    <a class="botao claro icone alerta-semaforo" href="{{ url('/almoxarifado-escolar/alertas') }}" title="Alertas de compra" aria-label="Alertas de compra"><span class="icone-semaforo" aria-hidden="true"><i></i><i></i><i></i></span></a>
    @if($status !== 'carrinho')
        <a class="botao icone" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}" title="Nota" aria-label="Nota"><i aria-hidden="true" class="fa fa-file-text-o"></i></a>
        @if($podeEmitirRemessa)
            <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/qr') }}" title="QR" aria-label="QR"><i aria-hidden="true" class="fa fa-qrcode"></i></a>
            <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/etiquetas') }}" title="Etiquetas" aria-label="Etiquetas"><i aria-hidden="true" class="fa fa-tags"></i></a>
        @endif
    @endif
@endsection

@section('conteudo')
<style>
    .pedido-cabeca-status { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:0 0 14px; }
    .carrinho-catalogo {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 14px;
    }
    .cat-card {
        display: flex;
        flex-direction: column;
        padding: 0;
        overflow: hidden;
        border-radius: 16px;
        border: 1px solid var(--linha);
        border-top: 1px solid var(--linha);
        background: var(--superficie, #fff);
        box-shadow: 0 10px 28px rgba(21, 34, 43, .05);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .cat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(21, 34, 43, .08);
        border-color: color-mix(in srgb, var(--cor-tela) 32%, var(--linha));
    }
    .cat-card__media {
        position: relative;
        aspect-ratio: 4 / 3;
        background: linear-gradient(145deg, color-mix(in srgb, var(--clara) 70%, #fff), #f4f7f9);
        overflow: hidden;
    }
    .cat-card__media img {
        width: 100%; height: 100%; object-fit: cover; display: block; cursor: zoom-in;
        transition: transform .35s ease;
    }
    .cat-card:hover .cat-card__media img { transform: scale(1.03); }
    .cat-card__media-vazio {
        width: 100%; height: 100%; display: grid; place-items: center;
        color: var(--suave); font-size: 1.4rem;
    }
    .cat-card__sku-chip {
        position: absolute; top: 10px; left: 10px;
        padding: 4px 9px; border-radius: 999px;
        font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
        background: color-mix(in srgb, #fff 88%, transparent); color: var(--tinta);
        border: 1px solid color-mix(in srgb, var(--linha) 70%, transparent);
        backdrop-filter: blur(6px);
    }
    .cat-card__corpo { padding: 12px 14px 8px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .cat-card__titulo {
        margin: 0; font-size: .95rem; font-weight: 700; line-height: 1.3;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        min-height: 2.5em;
    }
    .cat-card__meta {
        margin: 0; color: var(--suave); font-size: .78rem; font-weight: 600;
    }
    .cat-card__meta strong { color: var(--ok); font-weight: 800; }
    .cat-card__form {
        padding: 0 12px 12px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: end;
    }
    .cat-card__form label {
        display: grid; gap: 4px; font-size: .72rem; font-weight: 700; color: var(--suave); margin: 0;
    }
    .cat-card__form input[type=number] {
        min-height: 36px; padding: 6px 8px; border: 1px solid var(--linha);
        border-radius: 8px; font: inherit; width: 100%; background: #fff;
    }
    .cat-card__form .botao { white-space: nowrap; }
</style>

    <div class="pedido-cabeca-status" style="--st-cor: {{ $visual['cor'] }}">
        <span class="status-pill"><i class="fa {{ $visual['icone'] }}"></i> {{ $visual['rotulo'] }}</span>
        @if(!empty($p['origem_pedido_id']))
            <a href="{{ url('/almoxarifado-escolar/pedidos/'.$p['origem_pedido_id']) }}" style="font-size:.85rem">
                Reposição do pedido #{{ $p['origem_pedido_id'] }}
            </a>
        @endif
        @if(!empty($p['pedido_reposicao_id']))
            <a href="{{ url('/almoxarifado-escolar/pedidos/'.$p['pedido_reposicao_id']) }}" style="font-size:.85rem">
                Pedido de reposição #{{ $p['pedido_reposicao_id'] }}
            </a>
        @endif
    </div>

    @if($status !== 'carrinho')
        <ul class="trilho-status" style="--st-cor: {{ $visual['cor'] }}" aria-label="Ciclo do pedido">
            @foreach([
                'solicitado' => ['fa-inbox','Solicitado'],
                'aceito' => ['fa-thumbs-o-up','Aceite'],
                'separacao' => ['fa-cube','Separação'],
                'separado' => ['fa-cubes','Separado'],
                'em_transito' => ['fa-exchange','Em trânsito'],
                'entregue' => ['fa-truck','Entregue'],
                'conferido' => ['fa-check-circle','Conferido'],
            ] as $st => [$ico, $lab])
                @php
                    $i = array_search($st, $ordemStatus, true);
                    $classe = '';
                    if ($bloqueioEntrega && in_array($st, ['em_transito','entregue','conferido'], true) && $trilhoAtual !== 'conferido' && ! ($trilhoFeito[$st] ?? false)) {
                        $classe = 'bloqueado';
                    } elseif ($st === $trilhoAtual) {
                        $classe = 'atual';
                    } elseif (! empty($trilhoFeito[$st]) && $i !== false && $i < array_search($trilhoAtual, $ordemStatus, true)) {
                        $classe = 'feito';
                    } elseif (! empty($trilhoFeito[$st]) && $st !== $trilhoAtual) {
                        $classe = 'feito';
                    }
                @endphp
                <li class="{{ $classe }}"><i class="fa {{ $ico }}"></i>{{ $lab }}</li>
            @endforeach
        </ul>
    @endif

    @if($finalizado)
        <div class="bloco" style="border-top-color:#17663f;background:linear-gradient(135deg,#e8f6ee 0%,#fff 60%)">
            <h2 style="color:#17663f"><i class="fa fa-check-circle"></i> Pedido finalizado (conferido)</h2>
            <p style="margin:0;color:var(--suave);font-size:.9rem">
                Ciclo encerrado
                @if(!empty($p['finalizado_em']))
                    em {{ $p['finalizado_em'] }}
                @endif.
                Etiquetas, QR e novas confirmações ficam bloqueados.
                @if(count($aguardandoReposicao) > 0)
                    Itens não atendidos seguem para <strong>pedido de reposição</strong> após a compra entrar no estoque.
                @endif
            </p>
        </div>
    @endif

    @if($bloqueioEntrega)
        <div class="bloco pedido-parcial-alerta" style="border-top-color:#c0392b;background:linear-gradient(135deg,#fff5f5 0%,#fff 60%)">
            <h2 style="color:#c0392b"><i class="fa fa-ban"></i> Pedido parcial — entrega e conferência bloqueadas</h2>
            <p style="margin:0 0 10px;color:var(--suave);font-size:.9rem">
                Há {{ count($faltosos) }} item(ns) sem disponibilidade. O aceite pode seguir, mas a entrega/conferência
                só liberam depois que o almoxarifado retirar os faltosos (viram «não atendidos» / nota parcial para providência).
            </p>
            @if(!empty($p['nota_parcial']))
                <p style="margin:0 0 10px;font-size:.82rem;color:var(--suave)">
                    Nota parcial: {{ $p['nota_parcial']['motivo'] ?? '—' }}
                    @if(!empty($p['nota_parcial']['quando'])) · @data($p['nota_parcial']['quando']) @endif
                </p>
            @endif
        </div>
    @elseif(!empty($naoAtendidos) && !empty($p['parcial']))
        <div class="bloco" style="border-top-color:var(--ambar);background:linear-gradient(135deg,#fffaf0 0%,#fff 60%)">
            <h2 style="color:var(--ambar)"><i class="fa fa-adjust"></i> Pedido parcial liberado</h2>
            <p style="margin:0;color:var(--suave);font-size:.9rem">
                Faltosos retirados. Entrega/conferência liberadas só com os itens atendidos.
                {{ count($naoAtendidos) }} item(ns) ficaram em «não atendidos» para providência futura.
            </p>
        </div>
    @endif

    @if($status === 'carrinho')
        <div class="bloco">
            <h2><i class="fa fa-plus"></i> Itens disponíveis (com saldo)</h2>
            <p style="margin:0 0 12px;color:var(--suave);font-size:.85rem">
                Só entram itens do catálogo com saldo &gt; 0. Clique na foto para ampliar.
            </p>
            @if(empty($itensCatalogo))
                <p style="margin:0;color:var(--ambar)">Nenhum item com saldo agora.</p>
            @else
                <div class="carrinho-catalogo">
                    @foreach($itensCatalogo as $i)
                        @php
                            $legenda = trim(($i['sku'] ?? '').' — '.($i['titulo'] ?? ''), ' —');
                        @endphp
                        <article class="card cat-card">
                            <div class="cat-card__media">
                                @if(!empty($i['sku']))
                                    <span class="cat-card__sku-chip">{{ $i['sku'] }}</span>
                                @endif
                                @if(!empty($i['foto_url']))
                                    <img src="{{ $i['foto_url'] }}"
                                         alt="{{ $i['titulo'] }}"
                                         data-almox-foto="{{ $i['foto_url'] }}"
                                         data-almox-titulo="{{ $legenda }}">
                                @else
                                    <div class="cat-card__media-vazio" aria-hidden="true"><i class="fa fa-cube"></i></div>
                                @endif
                            </div>
                            <div class="cat-card__corpo">
                                <h3 class="cat-card__titulo" title="{{ $i['titulo'] }}">{{ $i['titulo'] }}</h3>
                                <p class="cat-card__meta">Disponível · <strong>{{ (int) $i['saldo'] }}</strong> un.</p>
                            </div>
                            <form class="cat-card__form" method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/itens') }}">
                                @csrf
                                <input type="hidden" name="modo" value="catalogo">
                                <input type="hidden" name="item" value="{{ $i['sku'] }} · {{ $i['titulo'] }}">
                                <label>Qtd
                                    <input type="number" name="quantidade" min="1" max="{{ max(1, (int) $i['saldo']) }}" required value="1">
                                </label>
                                <button class="botao" type="submit" title="Incluir no pedido">
                                    <i class="fa fa-cart-plus"></i> Incluir
                                </button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        @if(!empty($podeForaCatalogo))
            <div class="bloco" style="border-top-color:var(--ambar)">
                <h2><i class="fa fa-exclamation-triangle" style="color:var(--ambar)"></i> Item não cadastrado / sem saldo</h2>
                <p style="margin:0 0 12px;color:var(--suave);font-size:.85rem">
                    Permissão especial. Gera <strong>alerta de compra</strong> no almoxarifado.
                    Se o item já existir no catálogo, o sistema bloqueia a digitação livre.
                </p>
                <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/itens') }}">
                    @csrf
                    <input type="hidden" name="modo" value="fora_catalogo">
                    <label class="col-wide">Descrição do item
                        <input name="titulo_livre" required placeholder="Ex.: Toner HP 85A (não cadastrado)">
                    </label>
                    <label>SKU sugerido (opcional) <input name="sku_livre" placeholder="TON-85A"></label>
                    <label>Quantidade <input type="number" name="quantidade" min="1" required value="1"></label>
                    <div class="acoes">
                        <button class="botao" type="submit" style="background:var(--ambar)">
                            <i class="fa fa-bell"></i> Pedir e gerar alerta
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="bloco">
                <p style="margin:0;color:var(--suave);font-size:.85rem">
                    <i class="fa fa-lock"></i>
                    Pedido fora do catálogo desabilitado (padrão). Libere o processo
                    «Pedido fora do catálogo» ou <code>ALMOXARIFADO_PEDIDO_FORA_CATALOGO=true</code>.
                </p>
            </div>
        @endif
    @endif

    <div class="bloco">
        <h2><i class="fa fa-list"></i> Itens do pedido</h2>
        @if(empty($p['itens']))
            <p style="color:var(--suave);margin:0">Carrinho vazio — inclua itens acima.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:64px">Foto</th>
                        <th>SKU</th>
                        <th>Item</th>
                        <th>Qtd</th>
                        <th>Origem</th>
                        @if($status === 'carrinho' || ($bloqueioEntrega && $podeRetirarFaltosos))
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($p['itens'] as $item)
                        @php
                            $origem = $item['origem'] ?? 'catalogo';
                            $ehFalta = in_array($origem, ['sem_saldo', 'fora_catalogo'], true);
                            $cobertura = null;
                            foreach (($semCobertura ?? []) as $cob) {
                                if (strtoupper((string) $cob['sku']) === strtoupper((string) $item['sku'])) {
                                    $cobertura = $cob;
                                    break;
                                }
                            }
                            $rotuloOrigem = match ($origem) {
                                'fora_catalogo' => 'Fora do catálogo',
                                'sem_saldo' => 'Sem saldo',
                                default => 'Catálogo',
                            };
                            $fotoUrl = $item['foto_url']
                                ?? \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\ItemFoto::url($item['foto'] ?? null);
                            $tituloItem = $item['titulo'] ?? ($item['sku'] ?? 'Item');
                        @endphp
                        <tr @if($ehFalta || $cobertura) style="background:#fff5f5" @endif>
                            <td>
                                @if(!empty($fotoUrl))
                                    <img class="almox-foto-thumb"
                                         src="{{ $fotoUrl }}"
                                         alt="{{ $tituloItem }}"
                                         data-almox-foto="{{ $fotoUrl }}"
                                         data-almox-titulo="{{ ($item['sku'] ?? '').' — '.$tituloItem }}">
                                @else
                                    <span class="almox-foto-placeholder"><i class="fa fa-cube"></i></span>
                                @endif
                            </td>
                            <td>{{ $item['sku'] ?? '—' }}</td>
                            <td>
                                {{ $tituloItem }}
                                @if($ehFalta)
                                    <span style="display:block;font-size:.75rem;color:#c0392b;margin-top:2px">
                                        <i class="fa fa-exclamation-circle"></i> Impede entrega até retirada
                                    </span>
                                @elseif($cobertura)
                                    <span style="display:block;font-size:.75rem;color:#c0392b;margin-top:2px">
                                        <i class="fa fa-exclamation-circle"></i>
                                        Saldo {{ $cobertura['saldo'] }} · faltam {{ $cobertura['falta'] }} para a entrega
                                    </span>
                                @endif
                            </td>
                            <td>{{ $item['quantidade'] }}</td>
                            <td>
                                <span class="badge {{ $origem === 'catalogo' ? 'ok' : 'fail' }}">{{ $rotuloOrigem }}</span>
                            </td>
                            @if($status === 'carrinho')
                                <td>
                                    <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/itens/remover') }}" style="margin:0">
                                        @csrf
                                        <input type="hidden" name="sku" value="{{ $item['sku'] }}">
                                        <button class="botao claro" type="submit" title="Remover"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            @elseif($bloqueioEntrega && $podeRetirarFaltosos && $ehFalta)
                                <td>
                                    <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/retirar-faltoso') }}" style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                        @csrf
                                        <input type="hidden" name="sku" value="{{ $item['sku'] }}">
                                        <input name="observacao" placeholder="Obs. (opcional)" style="max-width:140px;font-size:.8rem">
                                        <button class="botao" type="submit" style="background:#c0392b" title="Marcar como não atendido">
                                            <i class="fa fa-minus-circle"></i> Retirar faltoso
                                        </button>
                                    </form>
                                </td>
                            @elseif($bloqueioEntrega && $podeRetirarFaltosos)
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($bloqueioEntrega && ! $podeRetirarFaltosos)
            <p style="margin:12px 0 0;color:var(--suave);font-size:.85rem">
                <i class="fa fa-lock"></i>
                Só o almoxarifado com permissão pode retirar faltosos
                (processo «Retirar itens faltosos» ou <code>ALMOXARIFADO_RETIRAR_FALTOSOS</code>).
            </p>
        @endif

        @if($status === 'carrinho' && !empty($p['itens']))
            <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/fechar') }}" style="margin-top:14px;display:flex;justify-content:flex-end">
                @csrf
                <button class="botao" type="submit"><i class="fa fa-lock"></i> Fechar pedido (enviar à separação)</button>
            </form>
        @endif
    </div>

    @include('almoxarifado::partials.cobertura-insuficiente', [
        'id' => $id,
        'semCobertura' => $semCobertura ?? [],
        'podeResolverCobertura' => $podeResolverCobertura ?? false,
    ])

    @if(!empty($naoAtendidos))
        <div class="bloco" style="border-top-color:var(--ambar)">
            <h2><i class="fa fa-hourglass-half" style="color:var(--ambar)"></i> Itens não atendidos (controle de materiais)</h2>
            <p style="margin:0 0 12px;color:var(--suave);font-size:.85rem">
                Não entraram na entrega nem na baixa. Após a compra/cadastro e a conferência deste pedido,
                gere um <strong>segundo pedido de reposição</strong> — o original permanece finalizado.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item</th>
                        <th>Qtd</th>
                        <th>Motivo</th>
                        <th>Retirado</th>
                        <th>Reposição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($naoAtendidos as $item)
                        <tr>
                            <td>{{ $item['sku'] ?? '—' }}</td>
                            <td>{{ $item['titulo'] ?? '—' }}</td>
                            <td>{{ $item['quantidade'] ?? 1 }}</td>
                            <td>
                                <span class="badge warn">
                                    {{ ($item['origem'] ?? '') === 'fora_catalogo' ? 'Fora do catálogo' : 'Sem saldo' }}
                                </span>
                            </td>
                            <td style="font-size:.82rem;color:var(--suave)">
                                {{ $item['retirado_por'] ?? '—' }}
                                @if(!empty($item['retirado_em'])) · {{ $item['retirado_em'] }} @endif
                                @if(!empty($item['observacao']))
                                    <br>{{ $item['observacao'] }}
                                @endif
                            </td>
                            <td style="font-size:.82rem">
                                @if(!empty($item['repedido_id']))
                                    <a href="{{ url('/almoxarifado-escolar/pedidos/'.$item['repedido_id']) }}">#{{ $item['repedido_id'] }}</a>
                                @else
                                    <span class="badge warn">Aguardando</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($podeGerarReposicao)
                <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/pedido-reposicao') }}" style="margin-top:14px;display:flex;justify-content:flex-end;gap:8px;align-items:center;flex-wrap:wrap">
                    @csrf
                    <span style="font-size:.82rem;color:var(--suave)">{{ count($aguardandoReposicao) }} item(ns) sem reposição</span>
                    <button class="botao" type="submit" style="background:var(--ambar)">
                        <i class="fa fa-refresh"></i> Gerar pedido de reposição
                    </button>
                </form>
            @elseif($finalizado && count($aguardandoReposicao) > 0)
                <p style="margin:12px 0 0;color:var(--suave);font-size:.85rem">
                    <i class="fa fa-lock"></i> Só o almoxarifado com permissão pode gerar o pedido de reposição.
                </p>
            @elseif(! $finalizado && count($aguardandoReposicao) > 0)
                <p style="margin:12px 0 0;color:var(--suave);font-size:.85rem">
                    Conclua a <strong>conferência</strong> deste pedido; depois gere a reposição (com o material já comprado/cadastrado).
                </p>
            @endif
        </div>
    @endif

    @if($status !== 'carrinho')
        <div class="bloco">
            <h2><i class="fa fa-check-square-o"></i> Assinaturas formais</h2>
            <p style="margin:0 0 8px;font-size:.8rem;color:var(--suave)">Aceite, Entrega e Conferência. Separação e Em trânsito são só operacionais.</p>
            <ul style="margin:0;padding-left:18px;color:var(--suave)">
                @foreach($etapas as $etapa)
                    @php $a = $p['assinaturas'][$etapa] ?? null; @endphp
                    <li style="margin:4px 0">
                        <strong>{{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($etapa) }}:</strong>
                        @if($a)
                            {{ $a['responsavel'] }}@if(!empty($a['login'])) ({{ $a['login'] }})@endif · {{ $a['quando'] }}
                        @elseif($finalizado)
                            —
                        @elseif($bloqueioEntrega && in_array($etapa, ['entrega', 'conferencia'], true))
                            <span style="color:#c0392b">bloqueada (faltosos pendentes)</span>
                        @else
                            pendente
                        @endif
                    </li>
                @endforeach
            </ul>
            <p style="margin:12px 0 0">
                @if($finalizado)
                    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}"><i class="fa fa-eye"></i> Consultar nota</a>
                @else
                    <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}"><i class="fa fa-pencil-square-o"></i> Emitir / assinar nota</a>
                @endif
            </p>
        </div>
    @endif

    @include('almoxarifado::partials.foto-lightbox')
@endsection
