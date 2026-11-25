@extends('almoxarifado::layout')
@section('titulo', 'Pedidos')
@section('migalha', 'Pedidos')
@section('icone-tela', 'fa-truck')
@section('titulo-tela', 'Pedidos')
@section('lead')
    Consultar pedidos já feitos é um fluxo; abrir um carrinho novo é outro — escolha abaixo.
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/movimentos') }}"><i class="fa fa-exchange"></i> Entrada/saída</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar') }}"><i class="fa fa-dashboard"></i> Painel</a>
@endsection

@section('conteudo')

    <div class="fluxo-sep">Consulta</div>
    <section class="fluxo-bloco consulta">
        <header class="fluxo-cabeca">
            <div>
                <h2><i class="fa fa-search"></i> Histórico por solicitante</h2>
                <p>Só para localizar pedidos já registrados — não abre carrinho novo.</p>
            </div>
            <span class="selo-fluxo">Consultar</span>
        </header>
        <form class="form-grid" method="get" action="{{ url('/almoxarifado-escolar/pedidos') }}">
            @include('buriti-ui::select-usuario', [
                'campo' => 'solicitante_id',
                'rotulo' => 'Quem pediu',
                'usuarios' => $usuarios ?? [],
                'selecionado' => $filtroSolicitanteId ?? null,
                'ajuda' => 'Pessoas com acesso ao almoxarifado no seu escopo (escola/rede).',
            ])
            <div class="acoes">
                <button class="botao" type="submit"><i class="fa fa-search"></i> Buscar</button>
                <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}">Limpar</a>
            </div>
        </form>
    </section>

    <div class="fluxo-sep">Novo pedido</div>
    <section class="fluxo-bloco acao">
        <header class="fluxo-cabeca">
            <div>
                <h2><i class="fa fa-shopping-cart"></i> Abrir carrinho</h2>
                <p>Começa um pedido novo: escolha a escola e, se quiser, quem está solicitando.</p>
            </div>
            <span class="selo-fluxo">Criar</span>
        </header>
        <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/pedidos') }}">
            @csrf
            @include('buriti-ui::select-busca', [
                'campo' => 'escola',
                'rotulo' => 'Escola',
                'icone' => 'fa-building',
                'opcoes' => collect($escolas ?? [])->map(fn ($e) => ['id' => $e['nome'], 'nome' => $e['nome']])->all(),
                'selecionado' => null,
                'placeholder' => 'Digite o nome da escola…',
                'obrigatorio' => true,
                'ajuda' => 'Escola que está pedindo o material.',
            ])
            @include('buriti-ui::select-usuario', [
                'campo' => 'solicitante_id',
                'rotulo' => 'Solicitante (opcional)',
                'usuarios' => $usuarios ?? [],
                'ajuda' => 'Quem pede em nome da escola — lista filtrada por permissão do almoxarifado.',
            ])
            <div class="acoes"><button class="botao" type="submit"><i class="fa fa-plus"></i> Abrir carrinho</button></div>
        </form>
    </section>

    @if(!empty($carrinhos))
        <div class="fluxo-sep">Em andamento</div>
        <div class="bloco">
            <h2><i class="fa fa-shopping-basket"></i> Carrinhos abertos</h2>
            <div class="card-grid">
                @foreach($carrinhos as $i => $l)
                    <article class="card" style="animation-delay: {{ $i * 0.04 }}s">
                        <header>
                            <span class="ico"><i class="fa fa-shopping-cart"></i></span>
                            <div>
                                <h3>{{ $l['titulo'] }}</h3>
                                <p class="meta">{{ $l['meta'] }}</p>
                            </div>
                        </header>
                        <span class="badge warn">{{ $l['badge'] }}</span>
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
                    <article class="card" style="animation-delay: {{ $i * 0.04 }}s">
                        <header>
                            <span class="ico"><i class="fa fa-truck"></i></span>
                            <div>
                                <h3>{{ $l['titulo'] ?? ('Pedido '.($i+1)) }}</h3>
                                <p class="meta">{{ $l['meta'] ?? '' }}</p>
                            </div>
                        </header>
                        <span class="badge {{ $l['badge_tom'] ?? 'ok' }}">{{ $l['badge'] ?? '' }}</span>
                        <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] ?? '' }}</p>
                        <p style="margin:10px 0 0">
                            <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos/'.$l['id']) }}"><i class="fa fa-eye"></i> Abrir</a>
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
@endsection
