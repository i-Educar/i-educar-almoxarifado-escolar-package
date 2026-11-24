<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Providers;

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\SaldoEstoque;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Services\DistribuicaoEscola;
use Illuminate\Support\ServiceProvider;

class AlmoxarifadoEscolarServiceProvider extends ServiceProvider
{
    public const string PACKAGE = 'buriti/i-educar-almoxarifado-escolar-package';

    public function register(): void
    {
        $this->app->singleton(SaldoEstoque::class);
        $this->app->singleton(DistribuicaoEscola::class);
    }

    public function boot(): void
    {
    }
}
