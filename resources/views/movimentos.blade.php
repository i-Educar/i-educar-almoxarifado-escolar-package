@extends('almoxarifado::layout')
@php
    $itens = $itens ?? [];
    $linhas = $linhas ?? [];
    $qtdHist = count($linhas);
@endphp
@section('titulo', 'Entrada e saída')
@section('migalha', 'Movimentos')
@section('icone-tela', 'fa-exchange')
@section('titulo-tela', 'Entrada e saída de itens')
@section('lead')
    Catálogo → movimento de estoque → pedidos. Ajuste o saldo do depósito aqui.
@endsection
@section('acoes-topo')
    @if(!empty($podeExportar) && ($qtdHist ?? 0) > 0)
        @include('exportacao::partials.botoes-exportar', [
            'action' => url('/almoxarifado-escolar/movimentos/exportar'),
            'formatos' => ['pdf', 'xlsx', 'csv', 'html'],
            'opcoes' => [
                ['value' => 'historico', 'label' => 'Histórico de movimentos'],
            ],
            'tituloModal' => 'Download — Movimentos',
        ])
    @endif
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/itens') }}"><i class="fa fa-cube"></i> Catálogo</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-shopping-cart"></i> Pedidos</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/saldos') }}"><i class="fa fa-bar-chart"></i> Saldos</a>
@endsection

@section('foco')
<section class="buriti-foco">
    <p class="buriti-foco-kicker">Fluxo</p>
    <h2 class="buriti-foco-titulo">Registrar movimento do depósito</h2>
    <p class="buriti-foco-texto">
        1. Sentido · 2. Item e quantidade · 3. Motivo · 4. Confirmação (responsável) · Registrar.
        Entrada fica com quem está logado; saída exige quem recebe o material.
    </p>
</section>
@endsection

@section('conteudo')
<style>
    .mov-passos { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 14px; padding:0; list-style:none; }
    .mov-passos li {
        display:inline-flex; align-items:center; gap:6px;
        padding:6px 10px; border-radius:999px; font-size:.78rem; font-weight:700;
        background:color-mix(in srgb, var(--clara) 55%, #fff); color:var(--suave);
        border:1px solid var(--linha);
    }
    .mov-passos li i { opacity:.7; }
    .mov-sentido {
        display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:0 0 4px;
    }
    .mov-sentido label {
        display:flex; align-items:center; gap:10px; margin:0; padding:12px 14px;
        border:2px solid var(--linha); border-radius:12px; cursor:pointer;
        background:#fff; transition:border-color .15s, background .15s;
    }
    .mov-sentido label:has(input:checked) {
        border-color:var(--cor-tela);
        background:color-mix(in srgb, var(--clara) 45%, #fff);
        box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--cor-tela) 25%, transparent);
    }
    .mov-sentido label.saida:has(input:checked) {
        border-color:var(--ambar);
        background:color-mix(in srgb, #fffaf0 80%, #fff);
    }
    .mov-sentido input { accent-color:var(--cor-tela); width:auto; }
    .mov-sentido strong { display:block; font-size:.95rem; }
    .mov-sentido small { display:block; color:var(--suave); font-weight:500; margin-top:2px; }
    .mov-saldo-atual {
        margin:4px 0 0; font-size:.82rem; color:var(--suave);
        min-height:1.2em;
    }
    .mov-saldo-atual strong { color:var(--tinta); }
    .mov-confirma {
        margin-top:6px; padding:14px; border-radius:12px;
        border:1px dashed color-mix(in srgb, var(--cor-tela) 40%, var(--linha));
        background:linear-gradient(135deg, color-mix(in srgb, var(--clara) 50%, #fff), #fff);
    }
    .mov-confirma h3 {
        margin:0 0 8px; font-size:.95rem; display:flex; align-items:center; gap:8px; color:var(--cor-tela);
    }
    .mov-confirma .campo-fixado { margin:0; }
    .hist-tabela { width:100%; border-collapse:collapse; font-size:.88rem; }
    .hist-tabela th, .hist-tabela td { padding:8px 10px; text-align:left; border-bottom:1px solid var(--linha); vertical-align:top; }
    .hist-tabela th { color:var(--suave); font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; }
    .hist-tabela tr:hover td { background:color-mix(in srgb, var(--clara) 30%, #fff); }
    @media (max-width:640px) {
        .mov-sentido { grid-template-columns:1fr; }
        .hist-tabela .col-saldo { display:none; }
    }
</style>

    <div class="bloco">
        <h2><i class="fa fa-plus-circle"></i> Novo movimento</h2>
        <ol class="mov-passos" aria-hidden="true">
            <li><i class="fa fa-exchange"></i> Sentido</li>
            <li><i class="fa fa-cube"></i> Item</li>
            <li><i class="fa fa-comment-o"></i> Motivo</li>
            <li><i class="fa fa-pencil"></i> Confirmação</li>
        </ol>

        @php
            $alertaEntrada = $alertaEntrada ?? null;
            $skuAlerta = strtoupper((string) ($alertaEntrada['sku'] ?? ''));
            $qtdAlerta = max(1, (int) ($alertaEntrada['quantidade'] ?? 1));
            $itemNoCatalogo = $skuAlerta === '';
            if ($skuAlerta !== '') {
                foreach ($itens as $candidato) {
                    if (strtoupper((string) ($candidato['sku'] ?? '')) === $skuAlerta) {
                        $itemNoCatalogo = true;
                        break;
                    }
                }
            }
        @endphp
        @if($alertaEntrada)
            <div class="faixa-parcial info" style="margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#fffaf0;border:1px solid #f0e0c0;color:#8a6d3b">
                <strong>Compra do alerta #{{ $alertaEntrada['id'] }}.</strong>
                Confira a entrada de {{ $qtdAlerta }} un. de {{ $alertaEntrada['titulo'] ?? $skuAlerta }}.
                Ao registrar, o saldo sobe e abre um pedido novo no fluxo de separação até a conferência.
                @if(!$itemNoCatalogo)
                    <span style="display:block;margin-top:6px;color:#9b2c2c">
                        Este SKU ainda não está no catálogo.
                        <a href="{{ url('/almoxarifado-escolar/itens') }}">Cadastre o item</a> antes de conferir a entrada.
                    </span>
                @endif
            </div>
        @endif

        <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/movimentos') }}" id="form-movimento">
            @csrf
            @if($alertaEntrada)
                <input type="hidden" name="alerta_id" value="{{ $alertaEntrada['id'] }}">
            @endif

            {{-- 1. Sentido --}}
            <div class="col-wide">
                <span style="display:block;font-size:.78rem;font-weight:700;color:var(--suave);margin:0 0 6px;text-transform:uppercase;letter-spacing:.04em">1 · Sentido</span>
                <div class="mov-sentido">
                    <label>
                        <input type="radio" name="sentido" value="entrada" id="sentido-entrada" checked>
                        <span>
                            <strong><i class="fa fa-arrow-down" style="color:var(--ok)"></i> Entrada (+)</strong>
                            <small>Compra, doação ou reposição no depósito</small>
                        </span>
                    </label>
                    <label class="saida" @if($alertaEntrada) hidden @endif>
                        <input type="radio" name="sentido" value="saida" id="sentido-saida" @disabled($alertaEntrada)>
                        <span>
                            <strong><i class="fa fa-arrow-up" style="color:var(--ambar)"></i> Saída (−)</strong>
                            <small>Baixa avulsa (não use para pedidos — use Pedidos)</small>
                        </span>
                    </label>
                </div>
            </div>

            {{-- 2. Item + quantidade --}}
            <label class="col-wide">2 · Item do catálogo
                <select name="item" id="item-movimento" required>
                    <option value="">— selecionar item —</option>
                    @foreach($itens as $i)
                        <option
                            value="{{ $i['sku'] }} · {{ $i['titulo'] }}"
                            data-saldo="{{ (int) ($i['saldo'] ?? 0) }}"
                            data-minimo="{{ (int) ($i['minimo'] ?? 0) }}"
                            @selected($skuAlerta !== '' && strtoupper((string) $i['sku']) === $skuAlerta)
                        >
                            {{ $i['sku'] }} — {{ $i['titulo'] }} (saldo {{ (int) ($i['saldo'] ?? 0) }})
                        </option>
                    @endforeach
                </select>
                <p class="mov-saldo-atual" id="saldo-hint">Selecione um item para ver o saldo atual.</p>
            </label>
            <label>Quantidade
                <input type="number" name="quantidade" id="qtd-movimento" min="{{ $alertaEntrada ? $qtdAlerta : 1 }}" required value="{{ $alertaEntrada ? $qtdAlerta : 1 }}">
            </label>

            {{-- 3. Motivo --}}
            <label class="col-wide">3 · Motivo / observação
                <input name="observacao" value="{{ $alertaEntrada ? 'Entrada da compra — alerta #'.$alertaEntrada['id'] : '' }}" placeholder="Ex.: NF 1234, doação, ajuste de inventário, perda…">
            </label>

            {{-- 4. Confirmação / assinatura — sempre no fim --}}
            <div class="col-wide mov-confirma" id="bloco-confirma">
                <h3><i class="fa fa-pencil-square-o"></i> 4 · Confirmação</h3>

                <div id="resp-entrada">
                    <div class="campo-fixado">
                        <span>Responsável pela entrada</span>
                        <strong>{{ $usuarioLogado ?? 'Usuário logado' }}</strong>
                        <small>Assinatura automática de quem está autenticado — não é possível indicar outra pessoa.</small>
                    </div>
                </div>

                <div id="resp-saida" hidden>
                    @include('buriti-ui::select-usuario', [
                        'campo' => 'responsavel_id',
                        'rotulo' => 'Quem recebe / responde pela saída',
                        'usuarios' => $usuarios ?? [],
                        'ajuda' => 'Obrigatório na saída. Quem retira o material do depósito.',
                    ])
                </div>
            </div>

            <div class="acoes col-wide" style="justify-content:flex-end;margin-top:4px">
                <button class="botao" type="submit" id="btn-registrar">
                    <i class="fa fa-check"></i> <span id="btn-rotulo" @if($alertaEntrada) data-fixo="1" @endif>{{ $alertaEntrada ? 'Conferir entrada e gerar pedido' : 'Registrar entrada' }}</span>
                </button>
            </div>
        </form>
    </div>

    <div class="bloco">
        <h2><i class="fa fa-history"></i> Histórico recente @if($qtdHist > 0)<span style="font-weight:500;color:var(--suave);font-size:.85rem">· {{ $qtdHist }}</span>@endif</h2>
        @if($qtdHist > 0)
            <div style="overflow-x:auto">
                <table class="hist-tabela">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Item</th>
                            <th>Qtd</th>
                            <th class="col-saldo">Saldo</th>
                            <th>Responsável</th>
                            <th>Origem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($linhas as $l)
                            <tr>
                                <td style="white-space:nowrap;color:var(--suave);font-size:.82rem">{{ $l['quando'] ?? '—' }}</td>
                                <td>
                                    <strong>{{ $l['titulo'] ?? '—' }}</strong>
                                    @if(!empty($l['obs']))
                                        <div style="font-size:.78rem;color:var(--suave);margin-top:2px">{{ $l['obs'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $l['badge_tom'] ?? 'ok' }}">{{ $l['badge'] ?? '' }}</span>
                                </td>
                                <td class="col-saldo" style="font-size:.82rem;color:var(--suave);white-space:nowrap">
                                    {{ $l['saldo_txt'] ?? '—' }}
                                </td>
                                <td style="font-size:.86rem">{{ $l['responsavel'] ?? '—' }}</td>
                                <td style="font-size:.82rem;color:var(--suave)">
                                    {{ $l['origem'] ?? '—' }}
                                    @if(!empty($l['pedido_id'])) · #{{ $l['pedido_id'] }} @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="color:var(--suave);margin:0">Nenhum movimento ainda. Cadastre itens no catálogo e registre a primeira entrada acima.</p>
        @endif
    </div>

    <script>
    (function () {
        var entRadio = document.getElementById('sentido-entrada');
        var saiRadio = document.getElementById('sentido-saida');
        var ent = document.getElementById('resp-entrada');
        var sai = document.getElementById('resp-saida');
        var lista = sai ? sai.querySelector('select') : null;
        var itemSel = document.getElementById('item-movimento');
        var hint = document.getElementById('saldo-hint');
        var qtd = document.getElementById('qtd-movimento');
        var btnRotulo = document.getElementById('btn-rotulo');

        function isEntrada() {
            return !saiRadio || saiRadio.checked === false;
        }

        function syncSentido() {
            var entrada = isEntrada();
            if (ent) ent.hidden = !entrada;
            if (sai) sai.hidden = entrada;
            if (lista) {
                if (entrada) {
                    lista.removeAttribute('required');
                    lista.value = '';
                } else {
                    lista.setAttribute('required', 'required');
                }
            }
            if (btnRotulo && !btnRotulo.getAttribute('data-fixo')) {
                btnRotulo.textContent = entrada ? 'Registrar entrada' : 'Registrar saída';
            }
            syncSaldo();
        }

        function syncSaldo() {
            if (!hint || !itemSel) return;
            var opt = itemSel.options[itemSel.selectedIndex];
            if (!opt || !opt.value) {
                hint.innerHTML = 'Selecione um item para ver o saldo atual.';
                return;
            }
            var saldo = parseInt(opt.getAttribute('data-saldo') || '0', 10);
            var minimo = parseInt(opt.getAttribute('data-minimo') || '0', 10);
            var q = parseInt((qtd && qtd.value) || '1', 10) || 1;
            var entrada = isEntrada();
            var depois = entrada ? saldo + q : saldo - q;
            var aviso = '';
            if (!entrada && depois < 0) {
                aviso = ' <span style="color:var(--erro)">· saldo insuficiente</span>';
            } else if (!entrada && minimo > 0 && depois < minimo) {
                aviso = ' <span style="color:var(--ambar)">· ficará abaixo do mínimo (' + minimo + ')</span>';
            }
            hint.innerHTML = 'Saldo atual: <strong>' + saldo + '</strong> → após o movimento: <strong>' + depois + '</strong>' + aviso;
        }

        if (entRadio) entRadio.addEventListener('change', syncSentido);
        if (saiRadio) saiRadio.addEventListener('change', syncSentido);
        if (itemSel) itemSel.addEventListener('change', syncSaldo);
        if (qtd) qtd.addEventListener('input', syncSaldo);
        syncSentido();
    })();
    </script>
@endsection
