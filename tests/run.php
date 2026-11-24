#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__.'/../src/Services/SaldoEstoque.php';
require __DIR__.'/../src/Services/DistribuicaoEscola.php';

$ok=0; $fail=0;
$assert = static function(bool $c, string $m) use (&$ok,&$fail): void {
  echo ($c? '  OK  ':' FAIL ').$m."\n";
  $c ? $ok++ : $fail++;
};

$s = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\SaldoEstoque::calcular([['item'=>'papel','sentido'=>'entrada','qtd'=>100],['item'=>'papel','sentido'=>'saida','qtd'=>30]]);
$assert(($s['papel']??0)===70, 'saldo estoque');
$assert(\iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\DistribuicaoEscola::validar(20,70)===true, 'distribuição válida');
$assert(\iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\DistribuicaoEscola::validar(80,70)===false, 'distribuição inválida');

echo "\nResultado: $ok ok, $fail falhas\n";
exit($fail>0?1:0);
