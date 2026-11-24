<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Providers;

use Illuminate\Support\ServiceProvider;

class AlmoxarifadoEscolarServiceProvider extends ServiceProvider
{
    public const string PACKAGE = 'buriti/i-educar-almoxarifado-escolar-package';

    public function register(): void
    {
        // Services são value objects — instanciar com `new` nos callers (não bind no container).
    }

    public function boot(): void
    {
    }
}
