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
    $ordemStatus = ['carrinho','fechado','separado','aceito','entregue','conferido'];
    $idxStatus = array_search($status, $ordemStatus, true);
    if ($idxStatus === false) { $idxStatus = 0; }
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
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-arrow-left"></i> Pedidos</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/alertas') }}"><i class="fa fa-bell"></i> Alertas</a>
    @if($status !== 'carrinho')
        <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/nota') }}"><i class="fa fa-file-text-o"></i> Nota</a>
        @if($podeEmitirRemessa)
            <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/qr') }}"><i class="fa fa-qrcode"></i> QR</a>
            <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/etiquetas') }}"><i class="fa fa-tags"></i> Etiquetas</a>
        @endif
    @endif
@endsection

@section('conteudo')
<style>
    .pedido-cabeca-status { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:0 0 14px; }
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
                'fechado' => ['fa-lock','Fechado'],
                'separado' => ['fa-cubes','Separado'],
                'aceito' => ['fa-thumbs-o-up','Aceito'],
                'entregue' => ['fa-truck','Entregue'],
                'conferido' => ['fa-check-circle','Conferido'],
            ] as $st => [$ico, $lab])
                @php
                    $i = array_search($st, $ordemStatus, true);
                    $classe = '';
                    if ($bloqueioEntrega && in_array($st, ['entregue','conferido'], true) && $idxStatus < $i) {
                        $classe = 'bloqueado';
                    } elseif ($i !== false && $i < $idxStatus) {
                        $classe = 'feito';
                    } elseif ($st === $status) {
                        $classe = 'atual';
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
                Só entram itens do catálogo com saldo &gt; 0 — o setor separa e entrega.
            </p>
            <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/itens') }}">
                @csrf
                <input type="hidden" name="modo" value="catalogo">
                <label class="col-wide">Item disponível
                    <select name="item" required>
                        <option value="">— selecionar —</option>
                        @foreach(($itensCatalogo ?? []) as $i)
                            <option value="{{ $i['sku'] }} · {{ $i['titulo'] }}">
                                {{ $i['sku'] }} — {{ $i['titulo'] }} (saldo {{ $i['saldo'] }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>Quantidade <input type="number" name="quantidade" min="1" required value="1"></label>
                <div class="acoes"><button class="botao" type="submit"><i class="fa fa-cart-plus"></i> Incluir</button></div>
            </form>
            @if(empty($itensCatalogo))
                <p style="margin:10px 0 0;color:var(--ambar)">Nenhum item com saldo agora.</p>
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
                        @endphp
                        <tr @if($ehFalta || $cobertura) style="background:#fff5f5" @endif>
                            <td>{{ $item['sku'] }}</td>
                            <td>
                                {{ $item['titulo'] }}
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
            <h2><i class="fa fa-check-square-o"></i> Assinaturas / qualidade</h2>
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
@endsection
