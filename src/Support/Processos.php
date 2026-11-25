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
        ];
    }
}
