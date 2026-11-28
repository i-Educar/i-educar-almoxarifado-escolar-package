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

$ok = 0;
$fail = 0;
$assert = static function (bool $c, string $m) use (&$ok, &$fail): void {
    echo ($c ? '  OK  ' : ' FAIL ').$m."\n";
    $c ? $ok++ : $fail++;
};

$s = SaldoEstoque::calcular([['item' => 'papel', 'sentido' => 'entrada', 'qtd' => 100], ['item' => 'papel', 'sentido' => 'saida', 'qtd' => 30]]);
$assert(($s['papel'] ?? 0) === 70, 'saldo estoque');
$assert(DistribuicaoEscola::validar(20, 70) === true, 'distribuição válida');
$assert(DistribuicaoEscola::validar(80, 70) === false, 'distribuição inválida');
$assert(AlertaCompra::rotuloMotivo('fora_catalogo') === 'Não cadastrado', 'alerta fora catálogo');
$assert(AlertaCompra::rotuloMotivo('sem_saldo') === 'Sem saldo', 'alerta sem saldo');
$assert(AlertaCompra::rotuloMotivo('cobertura') === 'Falta de cobertura', 'alerta cobertura');
$assert(Processos::CARRINHO_SETOR === 881406, 'processo carrinho setor');
$assert(Processos::PEDIDO_FORA_CATALOGO === 881407, 'processo fora catálogo');
$assert(Processos::ALERTAS_COMPRA === 881408, 'processo alertas compra');
$assert(Processos::RETIRAR_FALTOSOS === 881409, 'processo retirar faltosos');
$assert(Processos::titulos()[Processos::RETIRAR_FALTOSOS] === 'Retirar itens faltosos', 'título retirar faltosos');

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
$assert($depFator['criticos'] === 0, 'fator critico 0.5: 40 >= 25');

/* —— Ciclo / assinaturas formais (contrato de código) —— */
$carrinhoSrc = file_get_contents(__DIR__.'/../src/Services/PedidoCarrinho.php');
$assert(is_string($carrinhoSrc) && $carrinhoSrc !== '', 'PedidoCarrinho.php legível');
$assert(str_contains((string) $carrinhoSrc, "ETAPAS_ASSINATURA = ['aceite', 'entrega', 'conferencia']"), 'ETAPAS formais sem separação');
$assert(str_contains((string) $carrinhoSrc, 'STATUS_EM_TRANSITO'), 'status em trânsito');
$assert(str_contains((string) $carrinhoSrc, 'function marcarSeparado'), 'marcarSeparado operacional');
$assert(str_contains((string) $carrinhoSrc, 'notificarSolicitanteEmTransito'), 'notify solicitante em trânsito');
$assert(str_contains((string) $carrinhoSrc, 'function reenquadrarStatusCiclo'), 'reenquadramento de status');

$notaSrc = file_get_contents(__DIR__.'/../src/Services/NotaAssinaturaDigital.php');
$assert(is_string($notaSrc) && str_contains((string) $notaSrc, "ETAPAS = ['aceite', 'entrega', 'conferencia']"), 'NotaAssinaturaDigital ETAPAS alinhadas');
$assert(str_contains((string) $notaSrc, 'Solicitado por'), 'PDF inclui solicitante');

/* —— Cenário com Laravel (se monorepo disponível) —— */
$root = dirname(__DIR__, 4);
if (is_file($root.'/artisan') && is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
    /** @var \Illuminate\Foundation\Application $app */
    $app = require $root.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $assert(
        \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::ETAPAS_ASSINATURA
            === ['aceite', 'entrega', 'conferencia'],
        'runtime ETAPAS_ASSINATURA'
    );
    $assert(
        \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::ORDEM_CICLO
            === [
                'solicitado', 'aceito', 'separacao', 'separado', 'em_transito', 'entregue', 'conferido',
            ],
        'runtime ORDEM_CICLO'
    );
    $assert(
        \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloEtapa('aceite') === 'Aceite',
        'rotulo Aceite'
    );
    $assert(
        \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::rotuloStatus('em_transito') === 'Em trânsito',
        'rotulo Em trânsito'
    );
    $proxVazio = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::proximaEtapaPendente([
        'status' => 'separacao',
        'assinaturas' => [
            'aceite' => ['responsavel' => 'A', 'quando' => '01/01/2026'],
            'entrega' => null,
            'conferencia' => null,
        ],
    ]);
    $assert($proxVazio === null, 'após aceite, sem em trânsito → não libera entrega');
    $proxEntrega = \iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\PedidoCarrinho::proximaEtapaPendente([
        'status' => 'em_transito',
        'em_transito_em' => '2026-01-01 10:00:00',
        'assinaturas' => [
            'aceite' => ['responsavel' => 'A', 'quando' => '01/01/2026'],
            'entrega' => null,
            'conferencia' => null,
        ],
    ]);
    $assert($proxEntrega === 'entrega', 'em trânsito libera assinatura de entrega');
} else {
    echo "  SKIP cenário Laravel (artisan/vendor ausentes)\n";
}

echo "\nResultado: $ok ok, $fail falhas\n";
exit($fail > 0 ? 1 : 0);
