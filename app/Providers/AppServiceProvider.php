<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {Schema::defaultStringLength(100);

        // Dans les vues : @droit('eleves', 'supprimer') ... @enddroit
        // n'affiche le contenu que si le compte connecté a ce droit.
        Blade::if('droit', fn (string $module, string $niveau = 'lecture') => (bool) auth()->user()?->peut($module, $niveau));
    }
}
