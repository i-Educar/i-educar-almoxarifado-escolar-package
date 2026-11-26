<?php

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Processos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Processos especiais do almoxarifado com cadastra=0 por padrão:
 * - Carrinho por setor
 * - Pedido fora do catálogo
 * - Alertas de compra (visualiza para quem opera o depósito)
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

        $links = [
            [Processos::CARRINHO_SETOR, 'Carrinho por setor', '/almoxarifado-escolar/pedidos', 'Abrir solicitação de itens disponíveis pelo setor'],
            [Processos::PEDIDO_FORA_CATALOGO, 'Pedido fora do catálogo', '/almoxarifado-escolar/pedidos', 'Solicitar item não cadastrado / sem saldo (alerta de compra)'],
            [Processos::ALERTAS_COMPRA, 'Alertas de compra', '/almoxarifado-escolar/alertas', 'Itens sem saldo ou fora do catálogo'],
        ];

        foreach ($links as [$processo, $titulo, $link, $descricao]) {
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

        $tipos = DB::table('pmieducar.tipo_usuario')->where('ativo', 1)->get(['cod_tipo_usuario', 'nivel']);
        foreach ($tipos as $tipo) {
            foreach ([Processos::CARRINHO_SETOR, Processos::PEDIDO_FORA_CATALOGO, Processos::ALERTAS_COMPRA] as $processo) {
                $menuId = Menu::query()->where('process', $processo)->value('id');
                if ($menuId === null) {
                    continue;
                }
                // Default false: ninguém cadastra. Alertas: admin/institucional visualizam.
                $nivel = (int) ($tipo->nivel ?? 0);
                $visualizaAlerta = $processo === Processos::ALERTAS_COMPRA && in_array($nivel, [1, 2], true) ? 1 : 0;
                DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                    ['ref_cod_tipo_usuario' => $tipo->cod_tipo_usuario, 'menu_id' => $menuId],
                    [
                        'cadastra' => 0,
                        'visualiza' => $visualizaAlerta,
                        'exclui' => 0,
                    ],
                );
            }
        }

        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        foreach ([Processos::CARRINHO_SETOR, Processos::PEDIDO_FORA_CATALOGO, Processos::ALERTAS_COMPRA] as $processo) {
            $menu = Menu::query()->where('process', $processo)->first();
            if ($menu === null) {
                continue;
            }
            DB::table('pmieducar.menu_tipo_usuario')->where('menu_id', $menu->getKey())->delete();
            $menu->delete();
        }
        if (class_exists(MenuCacheService::class)) {
            app(MenuCacheService::class)->flushAll();
        }
    }
};
