@extends('almoxarifado::layout')
@section('titulo', 'Itens')
@section('migalha', 'Itens')
@section('icone-tela', 'fa-cubes')
@section('titulo-tela', 'Itens')
@section('lead')
    Catálogo do depósito
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar') }}"><i class="fa fa-dashboard"></i> Painel</a>
@endsection

@section('conteudo')

    <div class="bloco">
        <h2><i class="fa fa-plus"></i> Novo item</h2>
        <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/itens') }}">
            @csrf
            <label>SKU <input name="sku" required placeholder="Ex.: CAD-A4"></label>
            <label class="col-wide">Título <input name="titulo" required placeholder="Ex.: Caderno A4 96 folhas"></label>
            <label>Saldo <input type="number" name="saldo" min="0" required value="0"></label>
            <label>Mínimo operacional <input type="number" name="minimo" min="0" required value="0"></label>
            <div class="acoes"><button class="botao" type="submit"><i class="fa fa-save"></i> Cadastrar</button></div>
        </form>
    </div>

    <div class="bloco">
        @yield('extra')
        @if(!empty($linhas))
            <div class="card-grid">
                @foreach($linhas as $i => $l)
                    <article class="card" style="animation-delay: {{ $i * 0.04 }}s">
                        <header>
                            <span class="ico"><i class="fa fa-cubes"></i></span>
                            <div>
                                <h3>{{ $l['titulo'] ?? ('Item '.($i+1)) }}</h3>
                                <p class="meta">{{ $l['meta'] ?? '' }}</p>
                            </div>
                        </header>
                        @if(!empty($l['badge']))
                            <span class="badge {{ $l['badge_tom'] ?? 'ok' }}">{{ $l['badge'] }}</span>
                        @endif
                        <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <p style="color:var(--suave);margin:0">Sem registros nesta PoC — os dados de demonstração aparecem após o povoamento do módulo.</p>
        @endif
    </div>
@endsection
