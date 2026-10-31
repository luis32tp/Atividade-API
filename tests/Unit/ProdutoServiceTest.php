<?php

namespace Tests\Unit;

use App\Services\ProdutoService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProdutoServiceTest extends TestCase
{
    private string $temporaryStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['produtos.driver' => 'json']);

        $this->temporaryStoragePath = storage_path('framework/testing/produto-service-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($this->temporaryStoragePath.'/app');
        $this->app->useStoragePath($this->temporaryStoragePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStoragePath);

        parent::tearDown();
    }

    public function test_updates_a_product_and_persists_the_changes(): void
    {
        $produtoOriginal = ['id' => 1, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4];
        $this->writeProducts([$produtoOriginal]);

        $produtoAtualizado = app(ProdutoService::class)->update(1, [
            'nome' => 'Teclado mecânico',
            'preco' => 150,
        ]);

        $esperado = ['id' => 1, 'nome' => 'Teclado mecânico', 'preco' => 150, 'estoque' => 4];
        $this->assertSame($esperado, $produtoAtualizado);
        $this->assertSame([$esperado], $this->readProducts());
    }

    public function test_rejects_an_invalid_price_and_preserves_the_original_product(): void
    {
        $produtoOriginal = ['id' => 1, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4];
        $this->writeProducts([$produtoOriginal]);

        try {
            app(ProdutoService::class)->update(1, ['preco' => -1]);
            $this->fail('A atualização de um preço negativo deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preco', $exception->errors());
        }

        $this->assertSame([$produtoOriginal], $this->readProducts());
    }

    private function writeProducts(array $produtos): void
    {
        File::put(storage_path('app/produtos.json'), json_encode($produtos, JSON_THROW_ON_ERROR));
    }

    private function readProducts(): array
    {
        return File::json(storage_path('app/produtos.json'), JSON_THROW_ON_ERROR);
    }
}
