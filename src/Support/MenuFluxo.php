<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Support;

final class MenuFluxo
{
    /**
     * @param  array<string, bool>  $permite
     * @return list<array{nome:string,itens:list<array{rotulo:string,url:string,icone:string,chave:string}>}>
     */
    public static function grupos(array $permite): array
    {
        $item = static function (string $chave, string $rotulo, string $url, string $icone) use ($permite): ?array {
            if (! ($permite[$chave] ?? true)) {
                return null;
            }

            return compact('chave', 'rotulo', 'url', 'icone');
        };
        $filtrar = static fn (array $itens): array => array_values(array_filter($itens));

        return array_values(array_filter([
            [
                'nome' => 'Estoque da rede',
                'itens' => $filtrar([
                    $item('painel', 'Início', '/almoxarifado-escolar', 'fa-th-large'),
                    $item('itens', 'Catálogo de itens', '/almoxarifado-escolar/itens', 'fa-cube'),
                    $item('movimentos', 'Entrada e saída', '/almoxarifado-escolar/movimentos', 'fa-exchange'),
                    $item('pedidos', 'Pedidos / setores', '/almoxarifado-escolar/pedidos', 'fa-shopping-cart'),
                ]),
            ],
            [
                'nome' => 'Consultas',
                'itens' => $filtrar([
                    $item('saldos', 'Saldos por escola', '/almoxarifado-escolar/saldos', 'fa-bar-chart'),
                    $item('alertas', 'Alertas de compra', '/almoxarifado-escolar/alertas', 'fa-bell'),
                ]),
            ],
        ], static fn (array $g): bool => $g['itens'] !== []));
    }
}
