<?php

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Processo «Retirar itens faltosos» (cadastra=0 por padrão).
 * Libera via tipo_usuario.cadastra=1 ou ALMOXARIFADO_RETIRAR_FALTOSOS_NIVEIS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $raiz = Menu::query()->where('process', Processos::RAIZ)->first();
        if ($raiz === null) {
            return;
        }

        $operacao = Menu::query()
            ->where('parent_id', $raiz->getKey())
            ->where('title', 'Operação')
            ->first();
        $pai = $operacao?->getKey() ?? $raiz->getKey();

        Menu::query()->updateOrCreate(['process' => Processos::RETIRAR_FALTOSOS], [
            'parent_id' => $pai,
            'title' => 'Retirar itens faltosos',
            'description' => 'Retira faltosos do pedido (nota parcial / não atendidos) após o aceite',
            'link' => '/almoxarifado-escolar/pedidos',
            'order' => 0,
            'type' => 3,
            'active' => true,
        ]);

        // Atualiza descrição do fluxo de pedidos (novo ciclo).
        Menu::query()->where('process', Processos::PEDIDOS)->update([
            'description' => 'Solicitado → aceite → separação → trânsito → entrega → conferido',
        ]);

        $menuId = Menu::query()->where('process', Processos::RETIRAR_FALTOSOS)->value('id');
        if ($menuId === null) {
            return;
        }

        $tipos = DB::table('pmieducar.tipo_usuario')->where('ativo', 1)->get(['cod_tipo_usuario', 'nivel']);
        foreach ($tipos as $tipo) {
            $nivel = (int) ($tipo->nivel ?? 0);
            // Admin/institucional visualizam o processo; cadastra continua 0 (usa níveis do .env).
            $visualiza = in_array($nivel, [1, 2], true) ? 1 : 0;
            DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                ['ref_cod_tipo_usuario' => $tipo->cod_tipo_usuario, 'menu_id' => $menuId],
                [
                    'cadastra' => 0,
                    'visualiza' => $visualiza,
                    'exclui' => 0,
                ],
            );
        }

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        $menu = Menu::query()->where('process', Processos::RETIRAR_FALTOSOS)->first();
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
