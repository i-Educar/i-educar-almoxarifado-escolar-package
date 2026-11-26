<?php

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Http\Controllers\AlmoxarifadoController;
use Illuminate\Support\Facades\Route;

Route::prefix('almoxarifado-escolar')->group(function (): void {
    Route::get('/', [AlmoxarifadoController::class, 'index']);
    Route::get('/itens', [AlmoxarifadoController::class, 'itens']);
    Route::post('/itens', [AlmoxarifadoController::class, 'salvarItem']);
    Route::get('/saldos', [AlmoxarifadoController::class, 'saldos']);

    Route::get('/pedidos', [AlmoxarifadoController::class, 'pedidos']);
    Route::post('/pedidos', [AlmoxarifadoController::class, 'abrirPedido']);
    Route::post('/pedidos/legado', [AlmoxarifadoController::class, 'salvarPedido']);
    Route::get('/pedidos/{id}', [AlmoxarifadoController::class, 'verPedido'])->whereNumber('id');
    Route::post('/pedidos/{id}/itens', [AlmoxarifadoController::class, 'adicionarItemPedido'])->whereNumber('id');
    Route::post('/pedidos/{id}/itens/remover', [AlmoxarifadoController::class, 'removerItemPedido'])->whereNumber('id');
    Route::post('/pedidos/{id}/fechar', [AlmoxarifadoController::class, 'fecharPedido'])->whereNumber('id');
    Route::get('/pedidos/{id}/nota', [AlmoxarifadoController::class, 'notaPedido'])->whereNumber('id');
    Route::get('/pedidos/{id}/nota/pdf', [AlmoxarifadoController::class, 'exportarNotaPedido'])->whereNumber('id');
    Route::get('/pedidos/{id}/qr', [AlmoxarifadoController::class, 'qrPedido'])->whereNumber('id');
    Route::get('/pedidos/{id}/etiquetas', [AlmoxarifadoController::class, 'etiquetasPedido'])->whereNumber('id');
    Route::post('/pedidos/{id}/assinar', [AlmoxarifadoController::class, 'assinarPedido'])->whereNumber('id');
    Route::post('/pedidos/{id}/retirar-faltoso', [AlmoxarifadoController::class, 'retirarFaltoso'])->whereNumber('id');
    Route::post('/pedidos/{id}/cobertura', [AlmoxarifadoController::class, 'resolverCobertura'])->whereNumber('id');
    Route::post('/pedidos/{id}/pedido-reposicao', [AlmoxarifadoController::class, 'gerarPedidoReposicao'])->whereNumber('id');

    Route::get('/confirmar-recebimento/{token}', [AlmoxarifadoController::class, 'confirmarRecebimento']);

    Route::get('/alertas', [AlmoxarifadoController::class, 'alertas']);
    Route::post('/alertas/{id}', [AlmoxarifadoController::class, 'atualizarAlerta'])->whereNumber('id');

    Route::get('/movimentos', [AlmoxarifadoController::class, 'movimentos']);
    Route::post('/movimentos', [AlmoxarifadoController::class, 'salvarMovimento']);
    Route::post('/movimentos/exportar', [AlmoxarifadoController::class, 'exportarMovimentos']);
});
