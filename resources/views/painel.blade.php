@extends('almoxarifado::layout')
@section('titulo', 'Início — Almoxarifado')
@section('migalha', 'Início')
@section('icone-tela', 'fa-cubes')
@section('titulo-tela', 'Almoxarifado da rede')
@section('lead')
    Peça materiais, registre entradas e saídas e confira o saldo de cada escola.
@endsection
@section('acoes-topo')
    <a class="botao" href="{{ url('/almoxarifado-escolar/pedidos') }}"><i class="fa fa-shopping-cart"></i> Pedidos</a>
    <a class="botao claro" href="{{ url('/almoxarifado-escolar/saldos') }}"><i class="fa fa-bar-chart"></i> Saldos</a>
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
