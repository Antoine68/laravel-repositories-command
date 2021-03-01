<?php


namespace Antoiner\LaravelRepositoriesCommand;


use Antoiner\LaravelRepositoriesCommand\Commands\MakeRepositoriesCommand;
use Illuminate\Support\ServiceProvider;

class LaravelRepositoriesCommandProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            MakeRepositoriesCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
