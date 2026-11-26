<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Support;

final class Identidade
{
    public const COR = '#4a5a6a';
    public const DESTAQUE = '#6b7c8f';
    public const COR_CLARA = '#eef1f5';
    public const ICONE = 'fa-cubes';
    public const NOME = 'Almoxarifado';

    public const DESCRICAO = 'Pedidos, estoque e movimentação de materiais';

    /** @return array{cor:string,destaque:string,clara:string,icone:string,nome:string,descricao:string} */
    public static function tokens(): array
    {
        return [
            'cor' => self::COR,
            'destaque' => self::DESTAQUE,
            'clara' => self::COR_CLARA,
            'icone' => self::ICONE,
            'nome' => self::NOME,
            'descricao' => self::DESCRICAO,
        ];
    }
}
