@extends('almoxarifado::layout')
@php
    $p = $pedido['payload'];
    $id = (int) $pedido['id'];
    $status = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::normalizarStatus((string) ($p['status'] ?? 'solicitado'));
    $proxima = $proxima ?? null;
    $podeAssinar = ! empty($podeAssinar);
    $usuarioLogado = $usuarioLogado ?? ['nome' => '—', 'login' => null];
    $bloqueioEntrega = ! empty($bloqueioEntrega);
    $etapaBloqueada = ! empty($etapaBloqueada);
    $faltosos = $faltosos ?? [];
    $podeRetirarFaltosos = ! empty($podeRetirarFaltosos);
    $naoAtendidos = $p['itens_nao_atendidos'] ?? [];
    $finalizado = ! empty($finalizado) || \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::finalizado($p);
    $podeEmitirRemessa = isset($podeEmitirRemessa)
        ? ! empty($podeEmitirRemessa)
        : \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::podeEmitirRemessa($p);
    $visual = $visual ?? \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::visualStatus($status, $p);
@endphp
@section('titulo', 'Nota do pedido #'.$id)
@section('migalha', 'Pedidos / Nota #'.$id)
@section('icone-tela', $visual['icone'])
@section('titulo-tela', 'Nota de entrega #'.$id)
@section('lead')
    {{ $p['escola'] ?? '—' }} · {{ $visual['rotulo'] }}
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id) }}"><i class="fa fa-arrow-left"></i> Pedido</a>
    @if($podeEmitirRemessa)
        <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/qr') }}"><i class="fa fa-qrcode"></i> QR</a>
        <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/etiquetas') }}"><i class="fa fa-tags"></i> Etiquetas</a>
    @endif
    <button class="botao claro" type="button" onclick="window.print()"><i class="fa fa-print"></i> Imprimir</button>
@endsection

@section('conteudo')
@php
    $corNota = (string) ($identidade['cor'] ?? '#4a5a6a');
    $entidadeNome = (string) ($municipio ?? config('app.name', 'i-Educar'));
@endphp
<style>
    .nota {
        --nota-cor: {{ $corNota }};
        background:#fff; border:1px solid var(--linha); border-radius:14px; padding:20px 22px 24px;
        border-top: 4px solid var(--nota-cor);
    }
    .nota.parcial-bloqueada { border-color:#c0392b; border-top-color:#c0392b; box-shadow:0 0 0 3px rgba(192,57,43,.12); }
    .nota.parcial-ok { border-color:var(--ambar); border-top-color:var(--ambar); }
    .nota-identidade {
        display:flex; align-items:center; gap:12px;
        margin:-4px 0 14px; padding-bottom:12px; border-bottom:1px solid var(--linha);
    }
    .nota-identidade .marca-mini {
        width:44px; height:44px; border-radius:12px; flex:none;
        display:grid; place-items:center; color:#fff; font-size:18px;
        background:linear-gradient(145deg, var(--destaque), var(--nota-cor));
    }
    .nota-identidade .titulo-doc { margin:0; font-size:1.05rem; font-weight:800; color:var(--tinta); }
    .nota-identidade .sub-doc { margin:2px 0 0; font-size:.8rem; color:var(--suave); }
    .nota-cabeca { display:flex; justify-content:space-between; gap:16px; margin-bottom:14px; }
    .nota-cabeca h2 { margin:0 0 4px; font-size:1.15rem; color:var(--nota-cor); }
    .nota-cabeca p { margin:0; color:var(--suave); font-size:.86rem; }
    .faixa-parcial { margin:0 0 14px; padding:10px 12px; border-radius:10px; font-size:.88rem; }
    .faixa-parcial.bloqueio { background:#fff5f5; color:#922b21; border:1px solid #f5c6cb; }
    .faixa-parcial.info { background:#fffaf0; color:#8a6d3b; border:1px solid #f0e0c0; }
    .assinaturas {
        margin-top:24px; padding-top:14px; border-top:1px solid var(--linha);
        page-break-inside: avoid;
    }
    .assinaturas > h3 {
        margin:0 0 10px; font-size:.78rem; letter-spacing:.06em;
        text-transform:uppercase; color:var(--nota-cor);
    }
    .assinaturas-linha {
        display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px 14px;
        align-items:stretch;
    }
    .assinatura {
        min-width:0;
        text-align:center; padding:38px 6px 0; min-height:0;
        border:0; background:transparent; border-radius:0;
    }
    .assinatura.bloqueada { opacity:.7; }
    .assinatura .linha-assina {
        border-top:1px solid #1a242b; margin:0 18px 8px; height:0;
    }
    .assinatura .nome-assina {
        display:block; margin:0; font-size:.82rem; font-weight:700; color:var(--tinta);
        line-height:1.25; word-break:break-word;
    }
    .assinatura .cargo-assina {
        display:block; margin:2px 0 0; font-size:.72rem; font-weight:650; color:var(--nota-cor);
        line-height:1.2;
    }
    .assinatura .quando { margin:2px 0 0; font-size:.68rem; color:var(--suave); line-height:1.25; }
    .assinatura .pendente { margin:0; font-size:.78rem; color:var(--suave); font-style:italic; }
    .assinatura .bloqueio-txt { margin:0; color:#c0392b; font-size:.82rem; }
    .tr-falta { background:#fff5f5; }
    .tr-nao-atendido { background:#fffaf0; }
    .nota-autenticidade {
        display:grid; grid-template-columns:minmax(130px, 160px) 1fr; gap:16px 20px;
        align-items:center; margin-top:22px; padding:14px 16px;
        border:1px solid color-mix(in srgb, var(--nota-cor) 28%, var(--linha));
        border-radius:12px; background:linear-gradient(180deg, #f8fbfc, #fff);
        page-break-inside: avoid;
    }
    .nota-autenticidade h4 {
        margin:0 0 6px; font-size:.74rem; letter-spacing:.06em; text-transform:uppercase; color:var(--nota-cor);
    }
    .nota-autenticidade .codigo-doc {
        font-family:ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size:1rem; font-weight:800; letter-spacing:.08em; margin:0 0 6px;
    }
    .nota-autenticidade .qr-wrap { text-align:center; }
    .nota-autenticidade .qr-wrap canvas,
    .nota-autenticidade .qr-wrap img { max-width:100%; height:auto !important; }
    .nota-rodape-inst {
        display:none; margin-top:14px; padding-top:8px; border-top:1px solid var(--linha);
        color:var(--suave); font-size:.72rem; justify-content:space-between; gap:12px; flex-wrap:wrap;
    }
    @media (max-width: 900px) {
        .assinaturas-linha { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 520px) {
        .assinaturas-linha { grid-template-columns:1fr; }
        .nota-autenticidade { grid-template-columns:1fr; }
    }
    @media print {
        .lateral, .topo, .cabeca-tela, .acoes-tela, .flash, .bloco-assinar, .bloco-retirar,
        .bloco, footer, .no-print { display:none !important; }
        .app { display:block; } .miolo { padding:0; } main { padding:0 !important; }
        .nota {
            border:2.5px solid var(--nota-cor); border-radius:0; box-shadow:none;
            padding:18px 20px 22px; margin:0;
        }
        .faixa-parcial { border:1px solid #999; }
        .nota-rodape-inst { display:flex; }
        .assinaturas { break-inside: avoid; }
        .assinaturas-linha { grid-template-columns:repeat(4, minmax(0, 1fr)); }
        .nota-autenticidade { break-inside: avoid; }
    }
</style>

@if($finalizado)
    <div class="faixa-parcial info" style="background:#e8f6ee;color:#17663f;border-color:#b7e0c5">
        <strong><i class="fa fa-check-circle"></i> Pedido finalizado.</strong>
        Nota somente para consulta — etiquetas, QR e novas assinaturas bloqueados.
        @if(!empty($naoAtendidos))
            Não atendidos: tratativa via pedido de reposição no detalhe do pedido.
        @endif
    </div>
@elseif($bloqueioEntrega)
    <div class="faixa-parcial bloqueio no-print">
        <strong><i class="fa fa-ban"></i> Nota parcial — entrega/conferência bloqueadas.</strong>
        Retire os {{ count($faltosos) }} item(ns) faltoso(s) no pedido para liberar o fluxo.
        @if($podeRetirarFaltosos)
            <a href="{{ url('/almoxarifado-escolar/pedidos/'.$id) }}" style="margin-left:8px">Ir ao pedido →</a>
        @endif
    </div>
@elseif(!empty($naoAtendidos))
    <div class="faixa-parcial info">
        <strong><i class="fa fa-adjust"></i> Nota parcial.</strong>
        {{ count($naoAtendidos) }} item(ns) não atendido(s) para providência futura (2º pedido após compra).
        Entrega cobre apenas os itens da tabela abaixo.
    </div>
@endif

<div class="nota {{ $bloqueioEntrega ? 'parcial-bloqueada' : (!empty($naoAtendidos) ? 'parcial-ok' : '') }}">
    <header class="nota-identidade">
        <div class="marca-mini" aria-hidden="true"><i class="fa fa-file-text-o"></i></div>
        <div>
            <p class="titulo-doc">Nota de material — Almoxarifado escolar</p>
            <p class="sub-doc">{{ $entidadeNome }} · Pedido #{{ $id }}</p>
        </div>
    </header>

    <div class="nota-cabeca">
        <div>
            <h2>Nota de material @if(!empty($p['parcial']) || $bloqueioEntrega)<span style="color:#c0392b;font-size:.85rem">(parcial)</span>@endif</h2>
            <p>Pedido #{{ $id }} · Escola: <strong>{{ $p['escola'] ?? '—' }}</strong></p>
            @if(!empty($p['solicitante']))
                <p style="margin:8px 0 0;padding:8px 10px;border-radius:8px;background:color-mix(in srgb, var(--nota-cor) 8%, #fff);border:1px solid color-mix(in srgb, var(--nota-cor) 22%, var(--linha));">
                    <span style="display:block;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;color:var(--nota-cor);font-weight:700;">Solicitado por</span>
                    <strong style="font-size:.95rem;">{{ $p['solicitante'] }}</strong>
                    <span style="display:block;margin-top:2px;font-size:.82rem;color:var(--suave);">
                        @dataAbs($p['solicitado_em'] ?? $p['fechado_em'] ?? ($p['assinaturas']['aceite']['quando'] ?? null) ?? ($pedido['created_at'] ?? null))
                    </span>
                </p>
            @endif
        </div>
        <div style="text-align:right">
            <p>Status: {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus($status, $p) }}</p>
            @if($bloqueioEntrega)
                <p style="color:#c0392b;font-weight:600"><i class="fa fa-lock"></i> Entrega bloqueada</p>
            @endif
            @if(!empty($p['baixa_estoque']))
                <p style="color:var(--ok)">Baixa de estoque: sim (@dataAbs($p['baixa_em'] ?? null))</p>
            @endif
        </div>
    </div>

    <p style="margin:0 0 8px;font-size:.82rem;color:var(--suave);font-weight:600">Itens atendidos / em curso</p>
    <table>
        <thead>
            <tr><th>#</th><th>SKU</th><th>Item</th><th>Qtd</th><th>Situação</th></tr>
        </thead>
        <tbody>
            @foreach(($p['itens'] ?? []) as $i => $item)
                @php
                    $origem = $item['origem'] ?? 'catalogo';
                    $ehFalta = in_array($origem, ['sem_saldo', 'fora_catalogo'], true);
                    $cobertura = null;
                    foreach (($semCobertura ?? []) as $cob) {
                        if (strtoupper((string) $cob['sku']) === strtoupper((string) ($item['sku'] ?? ''))) {
                            $cobertura = $cob;
                            break;
                        }
                    }
                @endphp
                <tr class="{{ $ehFalta || $cobertura ? 'tr-falta' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item['sku'] }}</td>
                    <td>{{ $item['titulo'] }}</td>
                    <td>{{ $item['quantidade'] }}</td>
                    <td>
                        @if($ehFalta)
                            <span class="badge fail">Faltoso</span>
                        @elseif($cobertura)
                            <span class="badge fail">Saldo {{ $cobertura['saldo'] }} · falta {{ $cobertura['falta'] }}</span>
                        @else
                            <span class="badge ok">Atendido</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($naoAtendidos))
        <p style="margin:16px 0 8px;font-size:.82rem;color:var(--ambar);font-weight:600">Itens não atendidos (providência futura)</p>
        <table>
            <thead>
                <tr><th>#</th><th>SKU</th><th>Item</th><th>Qtd</th><th>Retirado por</th></tr>
            </thead>
            <tbody>
                @foreach($naoAtendidos as $i => $item)
                    <tr class="tr-nao-atendido">
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item['sku'] ?? '—' }}</td>
                        <td>{{ $item['titulo'] ?? '—' }}</td>
                        <td>{{ $item['quantidade'] ?? 1 }}</td>
                        <td style="font-size:.8rem">
                            {{ $item['retirado_por'] ?? '—' }}
                            @if(!empty($item['retirado_em'])) · @dataAbs($item['retirado_em']) @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="assinaturas">
        <h3>Assinaturas</h3>
        <div class="assinaturas-linha">
        @foreach($etapas as $etapa)
            @php
                $a = $p['assinaturas'][$etapa] ?? null;
                $bloqEtapa = $bloqueioEntrega && in_array($etapa, ['entrega', 'conferencia'], true) && empty($a);
                $rotuloEtapa = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($etapa);
                $codAssina = trim((string) ($a['codigo_validacao'] ?? ''));
                $urlAssina = trim((string) ($a['url_validacao'] ?? ''));
                // Nunca expor host.docker.internal / validador LS cru sob a assinatura.
                if ($codAssina !== '' && (
                    $urlAssina === ''
                    || str_contains($urlAssina, 'host.docker.internal')
                    || str_contains($urlAssina, '/apps/libresign/')
                )) {
                    $urlAssina = url('/assinatura-digital/verificar/'.$codAssina);
                }
            @endphp
            <div class="assinatura {{ $bloqEtapa ? 'bloqueada' : '' }}">
                <div class="linha-assina" aria-hidden="true"></div>
                @if($a)
                    <span class="nome-assina">{{ $a['responsavel'] }}</span>
                    <span class="cargo-assina">{{ $rotuloEtapa }}@if(!empty($a['login'])) · {{ $a['login'] }}@endif</span>
                    <p class="quando">@data($a['quando'] ?? null)</p>
                    @if(!empty($a['observacao']))
                        <p class="quando">{{ $a['observacao'] }}</p>
                    @endif
                    @if(!empty($a['pedido_assinatura_id']))
                        <p class="quando" style="color:var(--nota-cor);">Assinatura digital · LibreSign #{{ $a['pedido_assinatura_id'] }}</p>
                    @endif
                    @if($codAssina !== '' || $urlAssina !== '')
                        <p class="quando" style="margin-top:6px;">
                            @if($urlAssina !== '')
                                <a href="{{ $urlAssina }}" target="_blank" rel="noopener" style="color:var(--nota-cor);font-size:.78rem;border-bottom:1px dotted var(--nota-cor);text-decoration:none;">
                                    Conferir assinatura{{ $codAssina !== '' ? ' · '.$codAssina : '' }}
                                </a>
                            @else
                                <span style="color:var(--nota-cor);font-size:.78rem;">Conferir assinatura · {{ $codAssina }}</span>
                            @endif
                        </p>
                    @endif
                @elseif($bloqEtapa)
                    <span class="nome-assina">________________</span>
                    <span class="cargo-assina">{{ $rotuloEtapa }}</span>
                    <p class="bloqueio-txt"><i class="fa fa-lock"></i> Bloqueada até retirar faltosos</p>
                @else
                    <span class="nome-assina">________________</span>
                    <span class="cargo-assina">{{ $rotuloEtapa }}</span>
                    <p class="pendente">Aguardando assinatura</p>
                @endif
            </div>
        @endforeach
        </div>
    </div>

    @php
        $validacaoDoc = is_array($p['validacao'] ?? null) ? $p['validacao'] : [];
        $codigoDoc = trim((string) ($validacaoDoc['codigo'] ?? ''));
        $urlDoc = trim((string) ($validacaoDoc['url'] ?? ''));
        if ($urlDoc === '' && $codigoDoc !== '') {
            $urlDoc = class_exists(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)
                ? app(\iEducar\Packages\BuritiAssinaturaDigital\Services\ValidacaoPublicaService::class)->urlPublica($codigoDoc)
                : url('/exportacao/validar/'.$codigoDoc);
        }
    @endphp
    @if($codigoDoc !== '' || $urlDoc !== '')
        <section class="nota-autenticidade" aria-labelledby="nota-aut-{{ $id }}">
            <div class="qr-wrap">
                @if($urlDoc !== '')
                    @include('almoxarifado::partials.qr-canvas', [
                        'qrUrl' => $urlDoc,
                        'qrId' => 'qr-nota-doc-'.$id,
                        'qrSize' => 148,
                    ])
                @elseif(!empty($validacaoDoc['qr_data_uri']))
                    <img src="{{ $validacaoDoc['qr_data_uri'] }}" alt="QR autenticidade" width="148" height="148"
                         style="display:block;margin:0 auto;background:#fff;">
                @endif
            </div>
            <div>
                <h4 id="nota-aut-{{ $id }}"><i class="fa fa-shield"></i> Autenticidade do documento</h4>
                @if($codigoDoc !== '')
                    <p class="codigo-doc">{{ $codigoDoc }}</p>
                @endif
                <p style="margin:0 0 8px;font-size:.85rem;color:var(--tinta);">
                    Escaneie o QR ou use o código para conferir se esta nota foi emitida neste ambiente.
                </p>
                @if($urlDoc !== '')
                    <a href="{{ $urlDoc }}" target="_blank" rel="noopener" class="botao sm claro no-print" style="display:inline-flex">
                        <i class="fa fa-external-link"></i> Conferir autenticidade
                    </a>
                    <p class="no-print" style="margin:8px 0 0;font-size:.72rem;color:var(--suave);word-break:break-all">{{ $urlDoc }}</p>
                @endif
                <p style="margin:8px 0 0;font-size:.72rem;color:var(--suave);">
                    Cada assinatura acima possui código próprio de verificação (link «Conferir assinatura»).
                </p>
            </div>
        </section>
    @endif

    <footer class="nota-rodape-inst">
        <span>Documento gerado eletronicamente</span>
        <span>Almoxarifado escolar · @dataAbs(now())</span>
    </footer>
</div>

@if(!empty($urlDoc))
    @include('almoxarifado::partials.qr-script')
@endif

@if($bloqueioEntrega && $podeRetirarFaltosos && $faltosos !== [])
    <div class="bloco bloco-retirar" style="margin-top:14px;border-top-color:#c0392b">
        <h2><i class="fa fa-minus-circle"></i> Retirar itens faltosos</h2>
        <p style="margin:0 0 12px;color:var(--suave);font-size:.85rem">
            Cada retirada grava o item em «não atendidos» / nota parcial. Quando não restar falta, a entrega é liberada.
        </p>
        @foreach($faltosos as $f)
            <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/retirar-faltoso') }}" style="margin-bottom:10px;padding:10px;border:1px dashed #f5c6cb;border-radius:10px;background:#fff8f8">
                @csrf
                <input type="hidden" name="sku" value="{{ $f['sku'] }}">
                <div class="campo-fixado col-wide">
                    <span>{{ $f['sku'] }} — {{ $f['titulo'] }} ({{ $f['quantidade'] }} un.)</span>
                    <small>{{ ($f['origem'] ?? '') === 'fora_catalogo' ? 'Fora do catálogo' : 'Sem saldo' }}</small>
                </div>
                <label class="col-wide">Observação <input name="observacao" placeholder="Opcional — motivo / providência"></label>
                <div class="acoes">
                    <button class="botao" type="submit" style="background:#c0392b">
                        <i class="fa fa-minus-circle"></i> Marcar como não atendido
                    </button>
                </div>
            </form>
        @endforeach
    </div>
@endif

@include('almoxarifado::partials.cobertura-insuficiente', [
    'id' => $id,
    'semCobertura' => $semCobertura ?? [],
    'podeResolverCobertura' => $podeResolverCobertura ?? false,
])

@php
    $statusOp = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::normalizarStatus((string) $status);
    $podeMarcarSeparado = ! $finalizado
        && in_array($statusOp, ['aceito', 'separacao'], true)
        && empty($bloqueioEntrega)
        && ! empty($podeAssinarSeparacao ?? $podeOperarDeposito ?? false);
@endphp
@if($podeMarcarSeparado)
    <div class="bloco" style="margin-top:14px;border-top-color:#2a6f97">
        <h2><i class="fa fa-cubes"></i> Separação (operacional)</h2>
        <p style="margin:0 0 10px;color:var(--suave);font-size:.85rem">
            Sem assinatura no PDF. Marque quando o material estiver separado; em seguida emita a remessa (Em trânsito) — o solicitante será notificado.
        </p>
        <form method="post" action="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/marcar-separado') }}" style="display:inline-flex;gap:8px;flex-wrap:wrap">
            @csrf
            <button class="botao" type="submit"><i class="fa fa-check"></i> Marcar separado</button>
            <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/qr') }}"><i class="fa fa-qrcode"></i> Emitir remessa / QR</a>
        </form>
    </div>
@elseif(! $finalizado && in_array($statusOp, ['separado'], true))
    <div class="bloco" style="margin-top:14px;border-top-color:#0b6e4f">
        <h2><i class="fa fa-exchange"></i> Em trânsito</h2>
        <p style="margin:0 0 10px;color:var(--suave);font-size:.85rem">
            Material separado. Emita a remessa para registrar Em trânsito e avisar quem solicitou os itens.
        </p>
        <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$id.'/qr') }}"><i class="fa fa-qrcode"></i> Emitir remessa / QR</a>
    </div>
@endif

@if($proxima && $status !== 'carrinho' && ! $finalizado)
    <div class="bloco bloco-assinar" style="margin-top:14px">
        <h2><i class="fa fa-pencil"></i> Registrar {{ \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa($proxima) }}</h2>
        @if($proxima === 'entrega')
            <p style="color:var(--suave);margin:0 0 10px">Ao assinar a entrega, o sistema dá baixa automática no estoque dos itens atendidos desta nota.</p>
        @endif
        @if($proxima === 'conferencia')
            <p style="color:var(--suave);margin:0 0 10px">A conferência encerra o ciclo: qualidade e materiais conferidos. Depois disso não há etiquetas nem novas confirmações.</p>
        @endif

        @if(!empty($semCobertura))
            <p class="flash erro" style="margin:0">
                <i class="fa fa-ban"></i>
                A assinatura da entrega não baixa o estoque enquanto a quantidade pedida for maior que o saldo.
                Use o ajuste acima: entregar o disponível e comprar a falta, ou retirar o item e seguir com os demais.
            </p>
        @elseif($etapaBloqueada)
            <p class="flash erro" style="margin:0">
                <i class="fa fa-ban"></i>
                Entrega e conferência bloqueadas enquanto houver itens faltosos.
                @if($podeRetirarFaltosos)
                    Use a área acima ou o <a href="{{ url('/almoxarifado-escolar/pedidos/'.$id) }}">pedido</a> para retirar.
                @else
                    Solicite ao almoxarifado com permissão «Retirar itens faltosos».
                @endif
            </p>
        @elseif($podeAssinar)
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
