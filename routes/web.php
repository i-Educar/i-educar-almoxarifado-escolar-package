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
    Route::post('/pedidos/{id}/assinar', [AlmoxarifadoController::class, 'assinarPedido'])->whereNumber('id');

    Route::get('/movimentos', [AlmoxarifadoController::class, 'movimentos']);
    Route::post('/movimentos', [AlmoxarifadoController::class, 'salvarMovimento']);
});
