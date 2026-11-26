<form class="form-grid" id="form-abrir-carrinho" method="post" action="{{ url('/almoxarifado-escolar/pedidos') }}">
    @csrf
    @include('buriti-ui::select-busca', [
        'campo' => 'escola',
        'rotulo' => 'Escola',
        'icone' => 'fa-building',
        'opcoes' => collect($escolas ?? [])->map(fn ($e) => [
            'id' => $e['nome'],
            'nome' => $e['nome'],
            'data_id' => $e['cod'] ?? '',
            'data' => ['cod' => (string) ($e['cod'] ?? '')],
        ])->all(),
        'selecionado' => null,
        'placeholder' => 'Digite o nome da escola…',
        'obrigatorio' => true,
        'ajuda' => 'Escolha a unidade. O solicitante só lista pessoas vinculadas a essa escola.',
        'compacto' => true,
    ])
    <label>Setor solicitante
        <select name="setor" required>
            <option value="">— selecionar —</option>
            @foreach(($setores ?? []) as $setor)
                <option value="{{ $setor }}">{{ $setor }}</option>
            @endforeach
        </select>
    </label>
    @include('buriti-ui::select-usuario', [
        'campo' => 'solicitante_id',
        'rotulo' => 'Solicitante da unidade',
        'usuarios' => $usuarios ?? [],
        'compacto' => true,
        'ajuda' => 'Só pessoas da escola selecionada com permissão para solicitar. Em branco, usa o usuário logado (se for da unidade).',
    ])
    <p id="aviso-solicitantes-escola" style="margin:0;grid-column:1/-1;font-size:.82rem;color:var(--suave)">
        Selecione a escola para carregar os solicitantes da unidade.
    </p>
    <div class="acoes"><button class="botao" type="submit"><i class="fa fa-plus"></i> Abrir carrinho</button></div>
</form>
<script>
(function () {
    var form = document.getElementById('form-abrir-carrinho');
    if (!form || form.dataset.filtroEscolaBound === '1') return;
    form.dataset.filtroEscolaBound = '1';

    var escola = form.querySelector('select[name="escola"]');
    var pessoas = form.querySelector('select[name="solicitante_id"]');
    var aviso = document.getElementById('aviso-solicitantes-escola');
    if (!escola || !pessoas) return;

    function codEscola() {
        var opt = escola.options[escola.selectedIndex];
        if (!opt || !opt.value) return '';
        return String(opt.getAttribute('data-id') || opt.getAttribute('data-cod') || '').trim();
    }

    function aplicar() {
        var cod = codEscola();
        var visiveis = 0;

        Array.prototype.forEach.call(pessoas.options, function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                opt.disabled = false;
                opt.removeAttribute('data-fora-escola');
                return;
            }

            var lista = (opt.getAttribute('data-escolas') || '')
                .split(',')
                .map(function (s) { return String(s).trim(); })
                .filter(Boolean);

            // Sem escola: ninguém (exceto opção vazia).
            // Com escola: só quem tem o código em data-escolas.
            var fora = cod === '' || lista.indexOf(cod) === -1;
            if (fora) {
                opt.setAttribute('data-fora-escola', '1');
                opt.hidden = true;
                opt.disabled = true;
                if (opt.selected) pessoas.value = '';
            } else {
                opt.removeAttribute('data-fora-escola');
                opt.hidden = false;
                opt.disabled = false;
                visiveis++;
            }
        });

        if (aviso) {
            if (cod === '') {
                aviso.textContent = 'Selecione a escola para carregar os solicitantes da unidade.';
                aviso.style.color = 'var(--suave)';
            } else if (visiveis === 0) {
                aviso.textContent = 'Nenhuma pessoa vinculada a esta escola com permissão para solicitar. O pedido usará o usuário logado se ele pertencer à unidade.';
                aviso.style.color = 'var(--ambar, #b86e00)';
            } else {
                aviso.textContent = visiveis + ' solicitante(s) da unidade. Em branco, usa o usuário logado.';
                aviso.style.color = 'var(--suave)';
            }
        }

        // Reaplica busca respeitando data-fora-escola
        var busca = form.querySelector('.buriti-select-busca[data-for="' + pessoas.id + '"]')
            || form.querySelector('[data-for="' + pessoas.id + '"]');
        if (busca) busca.dispatchEvent(new Event('input', { bubbles: true }));
    }

    escola.addEventListener('change', aplicar);
    // select-busca compacto também dispara input na lista
    escola.addEventListener('input', aplicar);
    aplicar();
})();
</script>
