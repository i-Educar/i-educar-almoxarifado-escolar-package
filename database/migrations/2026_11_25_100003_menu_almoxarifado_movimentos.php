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
        $operacao = Menu::query()
            ->where('title', 'Operação')
            ->whereHas('parent', fn ($q) => $q->where('process', Processos::RAIZ))
            ->first();

        if ($operacao === null) {
            $raiz = Menu::query()->where('process', Processos::RAIZ)->first();
            if ($raiz === null) {
                return;
            }
            $operacao = Menu::query()->updateOrCreate(
                ['parent_id' => $raiz->getKey(), 'title' => 'Operação'],
                ['order' => 1, 'type' => 2, 'process' => null, 'active' => true, 'link' => null],
            );
        }

        Menu::query()->updateOrCreate(['process' => Processos::MOVIMENTOS], [
            'parent_id' => $operacao->getKey(),
            'title' => 'Entrada e saída',
            'description' => 'Movimentos manuais de estoque',
            'link' => '/almoxarifado-escolar/movimentos',
            'order' => 0,
            'type' => 3,
            'active' => true,
        ]);

        // Atualiza descrição dos pedidos
        Menu::query()->where('process', Processos::PEDIDOS)->update([
            'description' => 'Carrinho multi-item, nota e assinaturas',
        ]);

        $menuId = Menu::query()->where('process', Processos::MOVIMENTOS)->value('id');
        if ($menuId === null) {
            return;
        }

        $tipos = DB::table('pmieducar.tipo_usuario')->where('ativo', 1)->get(['cod_tipo_usuario']);
        foreach ($tipos as $tipo) {
            DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                ['ref_cod_tipo_usuario' => $tipo->cod_tipo_usuario, 'menu_id' => $menuId],
                ['cadastra' => 1, 'visualiza' => 1, 'exclui' => 0],
            );
        }

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        $menu = Menu::query()->where('process', Processos::MOVIMENTOS)->first();
        if ($menu === null) {
            return;
        }
        DB::table('pmieducar.menu_tipo_usuario')->where('menu_id', $menu->getKey())->delete();
        $menu->delete();
        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }
};
