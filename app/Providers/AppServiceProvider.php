<?php

namespace App\Providers;

use App\Contracts\ProdutoRepositoryInterface;
use App\Repositories\JsonProdutoRepository;
use App\Repositories\ProdutoRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProdutoRepositoryInterface::class, function (Application $app): ProdutoRepositoryInterface {
            return match ($app['config']['produtos.driver']) {
                'json' => $app->make(JsonProdutoRepository::class),
                'eloquent' => $app->make(ProdutoRepository::class),
                default => throw new InvalidArgumentException('PRODUTOS_DRIVER deve ser json ou eloquent.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
