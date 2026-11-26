<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Support;

final class Processos
{
    public const int RAIZ = 881400;
    public const int PAINEL = 881401;
    public const int ITENS = 881402;
    public const int SALDOS = 881403;
    public const int PEDIDOS = 881404;

    public const int MOVIMENTOS = 881405;

    /** Carrinho de pedidos pelo setor (default sem cadastro). */
    public const int CARRINHO_SETOR = 881406;

    /** Pedir item não cadastrado / sem saldo (alerta de compra). */
    public const int PEDIDO_FORA_CATALOGO = 881407;

    public const int ALERTAS_COMPRA = 881408;

    /** Retirar itens faltosos do pedido (nota parcial / não atendidos). */
    public const int RETIRAR_FALTOSOS = 881409;

    /** @return array<int, string> */
    public static function titulos(): array
    {
        return [
            self::RAIZ => 'Almoxarifado',
            self::PAINEL => 'Painel',
            self::ITENS => 'Itens',
            self::SALDOS => 'Saldos',
            self::PEDIDOS => 'Pedidos',
            self::MOVIMENTOS => 'Entrada e saída',
            self::CARRINHO_SETOR => 'Carrinho por setor',
            self::PEDIDO_FORA_CATALOGO => 'Pedido fora do catálogo',
            self::ALERTAS_COMPRA => 'Alertas de compra',
            self::RETIRAR_FALTOSOS => 'Retirar itens faltosos',
        ];
    }
}
