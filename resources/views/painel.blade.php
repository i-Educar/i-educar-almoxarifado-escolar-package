@extends('almoxarifado::layout')
@section('titulo', 'Início — Almoxarifado')
@section('migalha', 'Início')
@section('icone-tela', 'fa-cubes')
@section('titulo-tela', 'Almoxarifado da rede')
@section('lead')
    Peça materiais, registre entradas e saídas e confira o saldo de cada escola.
@endsection
@section('acoes-topo')
    <a class="botao icone" href="{{ url('/almoxarifado-escolar/pedidos') }}" title="Pedidos" aria-label="Pedidos"><i aria-hidden="true" class="fa fa-shopping-cart"></i></a>
    <a class="botao claro icone" href="{{ url('/almoxarifado-escolar/saldos') }}" title="Saldos" aria-label="Saldos"><i aria-hidden="true" class="fa fa-bar-chart"></i></a>
@endsection

@section('conteudo')
    @include('buriti-ui::painel-corpo', [
        'kpis' => $kpis ?? [],
        'atalhos' => $atalhos ?? [],
        'destaques' => $destaques ?? [],
        'analise' => $analise ?? [],
        'analiseTitulo' => $analiseTitulo ?? 'Análise',
        'resumo' => $resumo ?? null,
        'fluxoTitulo' => $fluxoTitulo ?? 'Fluxo do módulo',
    ])
@endsection
