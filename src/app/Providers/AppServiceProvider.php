<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Phar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (Phar::running()) {
            $this->hideDevCommands();
        }
    }

    /**
     * Hide development commands in production PHAR via config.
     */
    private function hideDevCommands(): void
    {
        $devCommands = [
            \App\Commands\UpdateCommand::class,
            \App\Commands\BuildCommand::class,
            \NunoMaduro\Collision\Adapters\Laravel\Commands\TestCommand::class,
            \LaravelZero\Framework\Commands\BuildCommand::class,
            \LaravelZero\Framework\Commands\InstallCommand::class,
            \LaravelZero\Framework\Commands\RenameCommand::class,
            \LaravelZero\Framework\Commands\MakeCommand::class,
            \LaravelZero\Framework\Commands\TestMakeCommand::class,
        ];

        $hidden = config('commands.hidden', []);
        config(['commands.hidden' => array_merge($hidden, $devCommands)]);
    }
}
