@extends('almoxarifado::layout')
@section('titulo', 'Pedidos')
@section('migalha', 'Pedidos')
@section('icone-tela', 'fa-truck')
@section('titulo-tela', 'Pedidos por setor')
@section('lead')
    Finalizados ocultos até você pedi-los · assinaturas: Aceite, Entrega e Conferência
@endsection
@section('acoes-topo')
    <button type="button"
            class="botao icone primaria {{ empty($podeCarrinhoSetor) ? 'buriti-acao-bloqueada' : '' }}"
            data-buriti-modal="modal-novo-pedido"
            title="{{ empty($podeCarrinhoSetor) ? 'Sem permissão para abrir carrinho' : 'Abrir carrinho' }}"
            aria-label="{{ empty($podeCarrinhoSetor) ? 'Sem permissão para abrir carrinho' : 'Abrir carrinho' }}">
        <i class="fa {{ empty($podeCarrinhoSetor) ? 'fa-lock' : 'fa-shopping-cart' }}" aria-hidden="true"></i>
    </button>
    <a class="botao claro icone alerta-semaforo"
       href="{{ url('/almoxarifado-escolar/alertas') }}"
       title="Alertas de compra"
       aria-label="Alertas de compra"><span class="icone-semaforo" aria-hidden="true"><i></i><i></i><i></i></span></a>
    <a class="botao claro icone"
       href="{{ url('/almoxarifado-escolar/movimentos') }}"
       title="Entrada/saída"
       aria-label="Entrada/saída"><i class="fa fa-exchange" aria-hidden="true"></i></a>
@endsection

@section('foco')
@php
    $totais = $totais ?? ['abertos' => 0, 'finalizados' => 0, 'todos' => 0];
    $situacao = $situacao ?? 'abertos';
    $qAbertos = (int) ($totais['abertos'] ?? 0);
    $qCarrinhos = count($carrinhos ?? []);
@endphp
<section class="buriti-foco">
    <p class="buriti-foco-kicker">Agora</p>
    <h2 class="buriti-foco-titulo">
        @if($qCarrinhos > 0)
            {{ $qCarrinhos }} carrinho{{ $qCarrinhos === 1 ? '' : 's' }} em montagem
        @elseif($qAbertos > 0)
            {{ $qAbertos }} pedido{{ $qAbertos === 1 ? '' : 's' }} em andamento
        @else
            Nenhum pedido em aberto
        @endif
    </h2>
    <p class="buriti-foco-texto">
        @if(empty($podeCarrinhoSetor))
            Carrinho por setor bloqueado — peça a permissão ou use Entrada/saída.
        @elseif($qAbertos > 0)
            Foque no que ainda precisa de ação. Finalizados: {{ (int) ($totais['finalizados'] ?? 0) }} (ocultos por padrão).
        @else
            Use o ícone do carrinho para solicitar itens disponíveis ao setor.
        @endif
    </p>
</section>
@endsection

@section('conteudo')
@php
    $baseUrl = url('/almoxarifado-escolar/pedidos');
    $queryBase = array_filter([
        'q' => $filtroQ ?? null,
        'escola' => $filtroEscola ?? null,
        'status' => $filtroStatus ?? null,
        'solicitante_id' => $filtroSolicitanteId ?? null,
    ], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
    $urlSituacao = static function (string $s) use ($baseUrl, $queryBase): string {
        return $baseUrl.'?'.http_build_query(array_merge($queryBase, ['situacao' => $s]));
    };
    $temFiltroExtra = ($filtroQ ?? '') !== ''
        || ($filtroEscola ?? '') !== ''
        || ($filtroStatus ?? '') !== ''
        || ! empty($filtroSolicitanteId);
@endphp

<div class="pedidos-barra">
    <div class="pedidos-chips" role="tablist" aria-label="Situação dos pedidos">
        <a href="{{ $urlSituacao('abertos') }}"
           class="pedidos-chip{{ $situacao === 'abertos' ? ' is-ativo' : '' }}"
           style="--chip:#b86e00"
           aria-current="{{ $situacao === 'abertos' ? 'page' : 'false' }}">
            <i class="fa fa-hourglass-half" aria-hidden="true"></i>
            <span>Em andamento</span>
            <strong>{{ (int) ($totais['abertos'] ?? 0) }}</strong>
        </a>
        <a href="{{ $urlSituacao('finalizados') }}"
           class="pedidos-chip{{ $situacao === 'finalizados' ? ' is-ativo' : '' }}"
           style="--chip:#17663f"
           aria-current="{{ $situacao === 'finalizados' ? 'page' : 'false' }}">
            <i class="fa fa-check-circle" aria-hidden="true"></i>
            <span>Finalizados</span>
            <strong>{{ (int) ($totais['finalizados'] ?? 0) }}</strong>
        </a>
        <a href="{{ $urlSituacao('todos') }}"
           class="pedidos-chip{{ $situacao === 'todos' ? ' is-ativo' : '' }}"
           style="--chip:var(--cor-tela,#4a5a6a)"
           aria-current="{{ $situacao === 'todos' ? 'page' : 'false' }}">
            <i class="fa fa-list" aria-hidden="true"></i>
            <span>Todos</span>
            <strong>{{ (int) ($totais['todos'] ?? 0) }}</strong>
        </a>
    </div>

    <form class="buriti-filtro pedidos-filtro" method="get" action="{{ $baseUrl }}" role="search">
        <input type="hidden" name="situacao" value="{{ $situacao }}">
        <label class="pedidos-busca">
            Buscar
            <span class="pedidos-busca-campo">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $filtroQ ?? '' }}"
                       placeholder="Nº, escola, setor ou solicitante"
                       autocomplete="off">
            </span>
        </label>
        <label>
            Escola
            <select name="escola">
                <option value="">Todas</option>
                @foreach(($escolas ?? []) as $escolaOpt)
                    @php $nomeEscola = is_array($escolaOpt) ? (string) ($escolaOpt['nome'] ?? $escolaOpt['titulo'] ?? '') : (string) $escolaOpt; @endphp
                    @if($nomeEscola !== '')
                        <option value="{{ $nomeEscola }}" @selected(($filtroEscola ?? '') === $nomeEscola)>{{ $nomeEscola }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label>
            Status
            <select name="status">
                <option value="">Qualquer etapa</option>
                @foreach(($statusOpcoes ?? []) as $stValor => $stRotulo)
                    <option value="{{ $stValor }}" @selected(($filtroStatus ?? '') === $stValor)>{{ $stRotulo }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Solicitante
            <select name="solicitante_id">
                <option value="">Todos</option>
                @foreach(($usuarios ?? []) as $u)
                    @php
                        $uid = (int) ($u['id'] ?? $u['cod_usuario'] ?? 0);
                        $unome = (string) ($u['nome'] ?? $u['name'] ?? ('#'.$uid));
                    @endphp
                    @if($uid > 0)
                        <option value="{{ $uid }}" @selected((int) ($filtroSolicitanteId ?? 0) === $uid)>{{ $unome }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <div class="acoes">
            <button class="botao" type="submit"><i class="fa fa-filter"></i> Filtrar</button>
            @if($temFiltroExtra || $situacao !== 'abertos')
                <a class="botao claro" href="{{ $baseUrl }}"><i class="fa fa-times"></i> Limpar</a>
            @endif
        </div>
    </form>
</div>

@if(!empty($carrinhos) && $situacao !== 'finalizados')
    <div class="bloco">
        <h2><i class="fa fa-shopping-basket"></i> Carrinhos abertos</h2>
        <div class="card-grid">
            @foreach($carrinhos as $i => $l)
                <article class="card pedido-st st-carrinho" style="--st-cor:#5b6b76;animation-delay: {{ $i * 0.04 }}s">
                    <header>
                        <span class="ico" style="background:linear-gradient(145deg,#7a8a96,#5b6b76)"><i class="fa fa-shopping-cart"></i></span>
                        <div>
                            <h3>#{{ $l['id'] }} · {{ $l['titulo'] }}</h3>
                            <p class="meta">{{ $l['subtitulo'] ?? $l['meta'] }}</p>
                        </div>
                    </header>
                    <span class="badge st-carrinho"><i class="fa fa-shopping-cart"></i> {{ $l['badge'] }}</span>
                    <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] }}</p>
                    <p style="margin:10px 0 0">
                        <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos/'.$l['id']) }}"><i class="fa fa-pencil"></i> Montar itens</a>
                    </p>
                </article>
            @endforeach
        </div>
    </div>
@endif

<div class="bloco">
    <h2>
        <i class="fa fa-list"></i>
        @if($situacao === 'finalizados')
            Pedidos finalizados
        @elseif($situacao === 'todos')
            Todos os pedidos
        @else
            Pedidos em andamento
        @endif
        <span class="texto-suave" style="font-weight:500;font-size:.85rem;margin-left:6px">
            ({{ count($linhas ?? []) }})
        </span>
    </h2>

    @if(!empty($linhas))
        <div class="card-grid">
            @foreach($linhas as $i => $l)
                @php
                    $stClasse = $l['status_classe'] ?? 'st-outro';
                    $stCor = $l['status_cor'] ?? '#5b6b76';
                    $stIco = $l['status_icone'] ?? 'fa-truck';
                @endphp
                <article class="card pedido-st {{ $stClasse }}" style="--st-cor:{{ $stCor }};animation-delay: {{ $i * 0.04 }}s">
                    <header>
                        <span class="ico" style="background:linear-gradient(145deg, color-mix(in srgb, {{ $stCor }} 70%, #fff), {{ $stCor }})">
                            <i class="fa {{ $stIco }}"></i>
                        </span>
                        <div>
                            <h3>#{{ $l['id'] }} · {{ $l['titulo'] }}</h3>
                            <p class="meta">{{ $l['subtitulo'] ?? '' }}@if(!empty($l['meta'])) · {{ $l['meta'] }}@endif</p>
                        </div>
                    </header>
                    <span class="badge {{ $stClasse }}"><i class="fa {{ $stIco }}"></i> {{ $l['badge'] ?? '' }}</span>
                    <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] ?? '' }}</p>
                    <p style="margin:10px 0 0">
                        <a class="botao {{ !empty($l['finalizado']) ? 'claro' : '' }}"
                           href="{{ url('/almoxarifado-escolar/pedidos/'.$l['id']) }}">
                            <i class="fa {{ !empty($l['finalizado']) ? 'fa-archive' : 'fa-eye' }}"></i>
                            {{ !empty($l['finalizado']) ? 'Consultar' : 'Abrir' }}
                        </a>
                    </p>
                </article>
            @endforeach
        </div>
    @else
        <p style="color:var(--suave);margin:0">
            @if($temFiltroExtra)
                Nenhum pedido corresponde aos filtros.
            @elseif($situacao === 'finalizados')
                Ainda não há pedidos finalizados.
            @elseif($situacao === 'abertos')
                Nenhum pedido em andamento. Veja os finalizados na barra acima, se precisar.
            @else
                Ainda não há pedidos registrados.
            @endif
        </p>
    @endif
</div>

@include('buriti-ui::operacao-modal', [
    'id' => 'modal-novo-pedido',
    'titulo' => !empty($podeCarrinhoSetor) ? 'Abrir carrinho do setor' : 'Carrinho bloqueado',
    'lead' => !empty($podeCarrinhoSetor)
        ? 'Pedido de itens disponíveis: separação → entrega.'
        : 'Sem permissão de carrinho por setor.',
    'corpo' => !empty($podeCarrinhoSetor)
        ? view('almoxarifado::partials.pedidos-novo', [
            'escolas' => $escolas ?? [],
            'setores' => $setores ?? [],
            'usuarios' => $usuarios ?? [],
        ])->render()
        : '<p style="margin:0;color:var(--suave)">A permissão <strong>Carrinho por setor</strong> está desligada (padrão). Um gestor precisa marcar <em>cadastra</em> no processo do menu do tipo de usuário ou habilitar <code>ALMOXARIFADO_CARRINHO_SETOR=true</code>.</p>',
])

<style>
    .pedidos-barra { margin: 0 0 14px; display: grid; gap: 10px; }
    .pedidos-chips {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .pedidos-chip {
        display: flex; align-items: center; gap: 8px;
        padding: 10px 12px; border-radius: 12px; text-decoration: none;
        color: var(--tinta); background: var(--superficie, #fff);
        border: 1px solid color-mix(in srgb, var(--chip, var(--cor-tela)) 28%, var(--linha));
        border-top: 3px solid var(--chip, var(--cor-tela));
        box-shadow: 0 6px 14px rgba(21,34,43,.04);
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .pedidos-chip i { color: var(--chip, var(--cor-tela)); width: 16px; text-align: center; }
    .pedidos-chip span {
        flex: 1; font-size: .72rem; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; color: var(--suave);
    }
    .pedidos-chip strong { font-size: 1.15rem; letter-spacing: -.02em; }
    .pedidos-chip:hover { transform: translateY(-1px); box-shadow: 0 10px 18px rgba(21,34,43,.07); }
    .pedidos-chip.is-ativo {
        background: color-mix(in srgb, var(--chip, var(--cor-tela)) 10%, #fff);
        border-color: color-mix(in srgb, var(--chip, var(--cor-tela)) 45%, var(--linha));
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--chip, var(--cor-tela)) 18%, transparent);
    }
    .pedidos-filtro { margin: 0; }
    .pedidos-busca { flex: 1.4 1 220px; max-width: none !important; }
    .pedidos-busca-campo { position: relative; display: block; }
    .pedidos-busca-campo > i {
        position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
        color: var(--suave); pointer-events: none;
    }
    .pedidos-busca-campo input { padding-left: 32px !important; }
    @media (max-width: 760px) {
        .pedidos-chips { grid-template-columns: 1fr; }
    }
</style>
@endsection
