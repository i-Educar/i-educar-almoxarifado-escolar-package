<form class="buriti-filtro" method="get" action="{{ url('/almoxarifado-escolar/pedidos') }}">
    @include('buriti-ui::select-usuario', [
        'campo' => 'solicitante_id',
        'rotulo' => 'Quem pediu',
        'usuarios' => $usuarios ?? [],
        'selecionado' => $filtroSolicitanteId ?? null,
        'compacto' => true,
        'colWide' => false,
        'vazio' => 'Todos',
    ])
    <div class="acoes">
        <button class="botao" type="submit"><i class="fa fa-search"></i> Buscar</button>
        <a class="botao claro" href="{{ url('/almoxarifado-escolar/pedidos') }}">Limpar</a>
    </div>
</form>
