<?php

namespace maestroerror\StatamicMagicImport;

use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $scripts = [__DIR__ . '/../dist/js/addon.js'];

    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
    ];

    public function boot()
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../config/statamic-magic-import.php' => config_path('statamic-magic-import.php'),
        ]);

        Nav::extend(function ($nav) {
            $nav->tools('Magic Import')
                ->route('json-import.index')
                ->icon('collections');
        });
    }
}
