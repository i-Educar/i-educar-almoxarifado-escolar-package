<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Console;

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\AlmoxarifadoDemoService;
use Illuminate\Console\Command;

class AlmoxarifadoDemoCommand extends Command
{
    protected $signature = 'almoxarifado:demo {--force : Recria itens/movimentos demo}';

    protected $description = 'PoC — povoa catálogo, movimentos e pedidos do almoxarifado';

    public function handle(AlmoxarifadoDemoService $demo): int
    {
        $this->info('Almoxarifado escolar · carga demo');
        $r = $demo->executar((bool) $this->option('force'));
        if ($r['pulado']) {
            $this->warn('  '.$r['mensagem']);

            return self::SUCCESS;
        }
        if (str_contains($r['mensagem'], 'ausente')) {
            $this->error('  '.$r['mensagem']);

            return self::FAILURE;
        }
        $this->line('  '.$r['mensagem']);

        return self::SUCCESS;
    }
}
