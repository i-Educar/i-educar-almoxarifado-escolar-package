<?php

namespace iEducar\Packages\BuritiAlmoxarifadoEscolar\Providers;

use iEducar\Packages\BuritiAlmoxarifadoEscolar\Console\AlmoxarifadoDemoCommand;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\Identidade;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\MenuFluxo;
use iEducar\Packages\BuritiAlmoxarifadoEscolar\Support\PermissoesAlmoxarifado;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AlmoxarifadoEscolarServiceProvider extends ServiceProvider
{
    public const string PACKAGE = 'buriti/i-educar-almoxarifado-escolar-package';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/almoxarifado.php', 'almoxarifado');
        // Fora de runningInConsole(): o check do Ambiente Laguna (HTTP) usa Artisan::all().
        $this->commands([AlmoxarifadoDemoCommand::class]);
    }

    public function boot(): void
    {
        if (class_exists(\iEducar\Packages\BuritiLagunaAmbiente\Support\BuritiUi::class)) {
            \iEducar\Packages\BuritiLagunaAmbiente\Support\BuritiUi::registrar();
        }
        $root = dirname(__DIR__, 2);
        $this->loadViewsFrom($root.'/resources/views', 'almoxarifado');

        if ($this->app->runningInConsole()) {
            if (\App\Support\Tenancy\PackageTenant::allows(self::PACKAGE)) {
                $this->loadMigrationsFrom($root.'/database/migrations');
            }
        }

        Route::middleware([
            'web',
            'ieducar.navigation',
            'ieducar.footer',
            'ieducar.xssbypass',
            'ieducar.suspended',
            'auth',
            'ieducar.checkresetpassword',
        ])->group($root.'/routes/web.php');

        View::composer('almoxarifado::*', function ($view): void {
            $view->with('identidade', Identidade::tokens());
            $view->with('menuGrupos', MenuFluxo::grupos(PermissoesAlmoxarifado::menu()));
        });
    }
}
