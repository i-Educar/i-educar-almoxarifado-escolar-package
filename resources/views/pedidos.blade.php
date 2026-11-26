@extends('almoxarifado::layout')
@section('titulo', 'Pedidos')
@section('migalha', 'Pedidos')
@section('icone-tela', 'fa-truck')
@section('titulo-tela', 'Pedidos por setor')
@section('lead')
    Ciclo: carrinho → separação → aceite → entrega → conferência. Cada status tem cor e ícone próprios.
@endsection
@section('acoes-topo')
    <button type="button" class="botao primaria {{ empty($podeCarrinhoSetor) ? 'buriti-acao-bloqueada' : '' }}"
            data-buriti-modal="modal-novo-pedido"
            @if(empty($podeCarrinhoSetor)) title="Sem permissão — veja o aviso" @endif>
        <i class="fa {{ empty($podeCarrinhoSetor) ? 'fa-lock' : 'fa-plus' }}"></i> Abrir carrinho
    </button>
    <button type="button" class="botao consulta" data-buriti-modal="modal-buscar-pedidos">
        <i class="fa fa-search"></i> Buscar
    </button>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/alertas') }}"><i class="fa fa-bell"></i> Alertas</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/movimentos') }}"><i class="fa fa-exchange"></i> Entrada/saída</a>
@endsection

@section('foco')
@php
    $qAbertos = count($carrinhos ?? []);
    $qTotal = count($linhas ?? []);
@endphp
<section class="buriti-foco">
    <p class="buriti-foco-kicker">Agora</p>
    <h2 class="buriti-foco-titulo">
        @if($qAbertos > 0)
            {{ $qAbertos }} carrinho{{ $qAbertos === 1 ? '' : 's' }} em andamento
        @else
            Nenhum carrinho aberto
        @endif
    </h2>
    <p class="buriti-foco-texto">
        @if(empty($podeCarrinhoSetor))
            Carrinho por setor bloqueado — peça a permissão ou use Entrada/saída.
        @elseif($qAbertos > 0)
            Monte os itens e avance separação → entrega. Histórico: {{ $qTotal }} pedido(s).
        @else
            Use <strong>Abrir carrinho</strong> para solicitar itens disponíveis ao setor.
        @endif
    </p>
</section>
@endsection

@section('conteudo')

    @if(!empty($carrinhos))
        <div class="bloco">
            <h2><i class="fa fa-shopping-basket"></i> Carrinhos abertos</h2>
            <div class="card-grid">
                @foreach($carrinhos as $i => $l)
                    <article class="card pedido-st st-carrinho" style="--st-cor:#5b6b76;animation-delay: {{ $i * 0.04 }}s">
                        <header>
                            <span class="ico" style="background:linear-gradient(145deg,#7a8a96,#5b6b76)"><i class="fa fa-shopping-cart"></i></span>
                            <div>
                                <h3>{{ $l['titulo'] }}</h3>
                                <p class="meta">{{ $l['meta'] }}</p>
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
        <h2><i class="fa fa-list"></i> Todos os pedidos</h2>
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
                                <h3>{{ $l['titulo'] ?? ('Pedido '.($i+1)) }}</h3>
                                <p class="meta">{{ $l['meta'] ?? '' }}</p>
                            </div>
                        </header>
                        <span class="badge {{ $stClasse }}"><i class="fa {{ $stIco }}"></i> {{ $l['badge'] ?? '' }}</span>
                        <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] ?? '' }}</p>
                        <p style="margin:10px 0 0">
                            <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$l['id']) }}">
                                <i class="fa {{ !empty($l['finalizado']) ? 'fa-archive' : 'fa-eye' }}"></i>
                                {{ !empty($l['finalizado']) ? 'Consultar' : 'Abrir' }}
                            </a>
                        </p>
                    </article>
                @endforeach
            </div>
        @else
            <p style="color:var(--suave);margin:0">
                @if(request()->filled('solicitante_id'))
                    Nenhum pedido para este solicitante.
                @else
                    Ainda não há pedidos registrados.
                @endif
            </p>
        @endif
    </div>

    @include('buriti-ui::operacao-modal', [
        'id' => 'modal-buscar-pedidos',
        'titulo' => 'Buscar pedidos',
        'lead' => 'Filtra o histórico por quem pediu — não abre carrinho novo.',
        'corpo' => view('almoxarifado::partials.pedidos-buscar', [
            'usuarios' => $usuarios ?? [],
            'filtroSolicitanteId' => $filtroSolicitanteId ?? null,
        ])->render(),
    ])

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
@endsection
