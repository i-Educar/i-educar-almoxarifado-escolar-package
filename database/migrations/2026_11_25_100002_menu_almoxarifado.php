<?php

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $raiz = Menu::query()->updateOrCreate(['process' => Processos::RAIZ], [
            'parent_id' => null,
            'title' => 'Almoxarifado escolar',
            'description' => 'Material pedagógico e administrativo — saldo, pedidos e entrega às escolas',
            'link' => '/almoxarifado-escolar',
            'icon' => 'fa-cubes',
            'order' => 10,
            'type' => 1,
            'active' => true,
        ]);

        $operacao = Menu::query()->updateOrCreate(
            ['parent_id' => $raiz->getKey(), 'title' => 'Operação'],
            ['order' => 1, 'type' => 2, 'process' => null, 'active' => true, 'link' => null],
        );

        $links = [
            [Processos::PAINEL, $operacao->getKey(), 'Painel', '/almoxarifado-escolar', 'Estoque e distribuição'],
            [Processos::ITENS, $operacao->getKey(), 'Itens', '/almoxarifado-escolar/itens', 'Catálogo do depósito'],
            [Processos::SALDOS, $operacao->getKey(), 'Saldos', '/almoxarifado-escolar/saldos', 'Saldo por escola'],
            [Processos::PEDIDOS, $operacao->getKey(), 'Pedidos', '/almoxarifado-escolar/pedidos', 'Distribuição para escolas'],
        ];

        foreach ($links as [$processo, $pai, $titulo, $link, $descricao]) {
            Menu::query()->updateOrCreate(['process' => $processo], [
                'parent_id' => $pai,
                'title' => $titulo,
                'description' => $descricao,
                'link' => $link,
                'order' => 0,
                'type' => 3,
                'active' => true,
            ]);
        }

        $processos = [Processos::RAIZ, Processos::PAINEL, Processos::ITENS, Processos::SALDOS, Processos::PEDIDOS];
        $tipos = DB::table('pmieducar.tipo_usuario')->where('ativo', 1)->get(['cod_tipo_usuario']);
        foreach ($tipos as $tipo) {
            foreach ($processos as $processo) {
                $menuId = Menu::query()->where('process', $processo)->value('id');
                if ($menuId === null) continue;
                DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                    ['ref_cod_tipo_usuario' => $tipo->cod_tipo_usuario, 'menu_id' => $menuId],
                    ['cadastra' => 1, 'visualiza' => 1, 'exclui' => 0],
                );
            }
        }

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        $raiz = Menu::query()->where('process', Processos::RAIZ)->first();
        if ($raiz === null) return;
        $grupos = Menu::query()->where('parent_id', $raiz->getKey())->pluck('id');
        $folhas = Menu::query()->whereIn('parent_id', $grupos)->pluck('id');
        $ids = $grupos->merge($folhas)->push($raiz->getKey());
        DB::table('pmieducar.menu_tipo_usuario')->whereIn('menu_id', $ids)->delete();
        Menu::query()->whereIn('id', $folhas)->delete();
        Menu::query()->whereIn('id', $grupos)->delete();
        $raiz->delete();
        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }
};
