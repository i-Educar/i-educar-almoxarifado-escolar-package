<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

final class DistribuicaoEscola
{
    public static function validar(int $pedido, int $disponivel): bool { return $pedido > 0 && $pedido <= $disponivel; }
}
