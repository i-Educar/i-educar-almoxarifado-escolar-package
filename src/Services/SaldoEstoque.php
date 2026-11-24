<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Services;

final class SaldoEstoque
{
    /** @param list<array{item:string,sentido:string,qtd:int}> $movs */
    public static function calcular(array $movs): array {
        $s=[]; foreach($movs as $m){ $i=$m['item']; $s[$i]=($s[$i]??0)+(($m['sentido']==='entrada')?(int)$m['qtd']:-(int)$m['qtd']); }
        return $s;
    }
}
