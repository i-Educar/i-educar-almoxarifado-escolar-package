#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__.'/../src/Services/SaldoEstoque.php';
require __DIR__.'/../src/Services/DistribuicaoEscola.php';
require __DIR__.'/../src/Services/AlertaCompra.php';
require __DIR__.'/../src/Services/SaldoRede.php';
require __DIR__.'/../src/Support/Processos.php';

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\AlertaCompra;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\DistribuicaoEscola;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\SaldoEstoque;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\SaldoRede;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos;

$ok=0; $fail=0;
$assert = static function(bool $c, string $m) use (&$ok,&$fail): void {
  echo ($c? '  OK  ':' FAIL ').$m."\n";
  $c ? $ok++ : $fail++;
};

$s = SaldoEstoque::calcular([['item'=>'papel','sentido'=>'entrada','qtd'=>100],['item'=>'papel','sentido'=>'saida','qtd'=>30]]);
$assert(($s['papel']??0)===70, 'saldo estoque');
$assert(DistribuicaoEscola::validar(20,70)===true, 'distribuição válida');
$assert(DistribuicaoEscola::validar(80,70)===false, 'distribuição inválida');
$assert(AlertaCompra::rotuloMotivo('fora_catalogo')==='Não cadastrado', 'alerta fora catálogo');
$assert(AlertaCompra::rotuloMotivo('sem_saldo')==='Sem saldo', 'alerta sem saldo');
$assert(AlertaCompra::rotuloMotivo('cobertura')==='Falta de cobertura', 'alerta cobertura');
$assert(Processos::CARRINHO_SETOR===881406, 'processo carrinho setor');
$assert(Processos::PEDIDO_FORA_CATALOGO===881407, 'processo fora catálogo');

/* —— Saldos por escola / depósito —— */
$dep = SaldoRede::avaliarDeposito([
    ['sku' => 'PAPEL', 'titulo' => 'Papel A4', 'saldo' => 10, 'minimo' => 50],
    ['sku' => 'CANETA', 'titulo' => 'Caneta', 'saldo' => 80, 'minimo' => 20],
    ['sku' => 'GIZ', 'titulo' => 'Giz', 'saldo' => 0, 'minimo' => 0],
]);
$assert($dep['criticos'] === 1, '1 item crítico no depósito');
$assert($dep['deposito_unidades'] === 90, 'soma unidades depósito');
$assert(($dep['deposito'][0]['sku'] ?? '') === 'PAPEL', 'críticos ordenados primeiro');
$assert(($dep['deposito'][0]['cobertura'] ?? 0) === 0.2, 'cobertura papel 10/50');
$assert(($dep['deposito'][1]['critico'] ?? true) === false, 'caneta não crítica');
$depFator = SaldoRede::avaliarDeposito([
    ['sku' => 'X', 'titulo' => 'X', 'saldo' => 40, 'minimo' => 50],
], 0.5);
$assert($depFator['criticos'] === 0, 'fator crítico 0.5: 40 >= 25');

echo "\nResultado: $ok ok, $fail falhas\n";
exit($fail>0?1:0);
