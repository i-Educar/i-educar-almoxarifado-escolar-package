@extends('almoxarifado::layout')
@section('titulo', 'Entrada e saída')
@section('migalha', 'Movimentos')
@section('icone-tela', 'fa-exchange')
@section('titulo-tela', 'Entrada e saída de itens')
@section('lead')
    Ajuste o saldo do depósito com entradas (compra/doação) ou saídas avulsas
@endsection
@section('acoes-topo')
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-truck"></i> Pedidos</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar') }}"><i class="fa fa-dashboard"></i> Painel</a>
@endsection

@section('conteudo')

    <div class="bloco">
        <h2><i class="fa fa-exchange"></i> Novo movimento</h2>
        <form class="form-grid" method="post" action="{{ url('/almoxarifado-escolar/movimentos') }}" id="form-movimento">
            @csrf
            <label>Sentido
                <select name="sentido" id="sentido-movimento" required>
                    <option value="entrada" selected>Entrada (+)</option>
                    <option value="saida">Saída (−)</option>
                </select>
            </label>
            <label class="col-wide">Item
                <input name="item" required list="mov-itens" placeholder="SKU · título">
                <datalist id="mov-itens">
                    @foreach(($itens ?? []) as $i)
                        <option value="{{ $i['sku'] }} · {{ $i['titulo'] }}">saldo {{ $i['saldo'] }}</option>
                    @endforeach
                </datalist>
            </label>
            <label>Quantidade <input type="number" name="quantidade" min="1" required value="1"></label>

            <div class="campo-fixado" id="resp-entrada">
                <span>Responsável (entrada)</span>
                <strong>{{ $usuarioLogado ?? 'Usuário logado' }}</strong>
                <small>Preenchido automaticamente com quem está autenticado — não é possível indicar outra pessoa.</small>
            </div>

            <div id="resp-saida" hidden>
                @include('buriti-ui::select-usuario', [
                    'campo' => 'responsavel_id',
                    'rotulo' => 'Quem recebe / responde pela saída',
                    'usuarios' => $usuarios ?? [],
                    'ajuda' => 'Pessoas com acesso a pedidos/movimentos no seu escopo. Entrada fica com quem está logado.',
                ])
            </div>

            <label class="col-wide">Observação <input name="observacao" placeholder="NF, doação, perda…"></label>
            <div class="acoes"><button class="botao" type="submit"><i class="fa fa-save"></i> Registrar</button></div>
        </form>
    </div>

    <div class="bloco">
        <h2><i class="fa fa-history"></i> Histórico</h2>
        @if(!empty($linhas))
            <div class="card-grid">
                @foreach($linhas as $i => $l)
                    <article class="card" style="animation-delay: {{ $i * 0.03 }}s">
                        <header>
                            <span class="ico"><i class="fa fa-exchange"></i></span>
                            <div>
                                <h3>{{ $l['titulo'] }}</h3>
                                <p class="meta">{{ $l['meta'] }}</p>
                            </div>
                        </header>
                        <span class="badge {{ $l['badge_tom'] ?? 'ok' }}">{{ $l['badge'] }}</span>
                        <p style="margin:8px 0 0;color:var(--suave)">{{ $l['corpo'] }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <p style="color:var(--suave);margin:0">Nenhum movimento ainda. Registre uma entrada ou saída acima.</p>
        @endif
    </div>

    <script>
    (function () {
        var sel = document.getElementById('sentido-movimento');
        var ent = document.getElementById('resp-entrada');
        var sai = document.getElementById('resp-saida');
        var lista = sai ? sai.querySelector('select') : null;
        function sync() {
            var entrada = !sel || sel.value === 'entrada';
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
        }
        if (sel) {
            sel.addEventListener('change', sync);
            sync();
        }
    })();
    </script>
@endsection
