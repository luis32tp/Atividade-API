<?php

namespace Tests\Feature;

use App\Contracts\ProdutoRepositoryInterface;
use App\Models\Produto;
use App\Repositories\ProdutoRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProdutoRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Ative pdo_sqlite para testar Eloquent em um banco isolado em memória.');
        }

        config([
            'produtos.driver' => 'eloquent',
            'database.default' => 'produtos_test',
            'database.connections.produtos_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        // Somente a conexão em memória recebe uma tabela; nenhuma migration é executada.
        Schema::connection('produtos_test')->create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->decimal('preco', 12, 2);
            $table->unsignedInteger('estoque');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('produtos_test');

        parent::tearDown();
    }

    #[DataProvider('priceFilters')]
    public function test_search_preserves_the_full_listing_and_filters_inclusive_price_limits(array $filters, array $expectedIds): void
    {
        $this->createProducts();

        $produtos = app(ProdutoRepository::class)->search($filters);

        $this->assertSame($expectedIds, $produtos->pluck('id')->all());
        $this->assertContainsOnlyInstancesOf(Produto::class, $produtos);
        $this->assertDatabaseCount('produtos', 4);
    }

    #[DataProvider('priceFilters')]
    public function test_the_eloquent_driver_uses_the_same_filters_through_the_api(array $filters, array $expectedIds): void
    {
        $this->createProducts();

        $response = $this->getJson('/api/produtos?'.http_build_query($filters));

        $response->assertOk();
        $this->assertSame($expectedIds, array_column($response->json('data'), 'id'));
        $this->assertDatabaseCount('produtos', 4);
    }

    public static function priceFilters(): array
    {
        return [
            'sem filtros' => [[], [1, 2, 3, 4]],
            'preço mínimo' => [['preco_min' => '50.5'], [2, 3, 4]],
            'preço máximo' => [['preco_max' => '100.75'], [1, 2, 3]],
            'intervalo inclusivo' => [['preco_min' => '50.5', 'preco_max' => '100.75'], [2, 3]],
            'mínimo zero' => [['preco_min' => '0'], [1, 2, 3, 4]],
            'máximo zero' => [['preco_max' => '0'], [1]],
            'limites iguais' => [['preco_min' => '50.5', 'preco_max' => '50.5'], [2]],
            'nenhum resultado' => [['preco_min' => '300'], []],
        ];
    }

    public function test_creates_and_finds_a_product_using_eloquent(): void
    {
        $repository = app(ProdutoRepository::class);
        $dados = ['nome' => 'Mouse', 'preco' => 50.5, 'estoque' => 2];

        $produto = $repository->create($dados);

        $this->assertInstanceOf(Produto::class, $produto);
        $this->assertTrue($produto->exists);
        $this->assertSame($dados, $repository->find((string) $produto->id)->only(array_keys($dados)));
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, ...$dados]);
    }

    public function test_update_returns_the_updated_model_and_preserves_other_fields_and_products(): void
    {
        $this->createProducts();

        $produto = app(ProdutoRepository::class)->update('2', ['preco' => 0, 'estoque' => 0]);

        $this->assertInstanceOf(Produto::class, $produto);
        $this->assertSame(['id' => 2, 'nome' => 'Mouse', 'preco' => 0.0, 'estoque' => 0], $produto->only(['id', 'nome', 'preco', 'estoque']));
        $this->assertDatabaseHas('produtos', ['id' => 2, 'nome' => 'Mouse', 'preco' => 0, 'estoque' => 0]);
        $this->assertDatabaseHas('produtos', ['id' => 3, 'nome' => 'Teclado', 'preco' => 100.75, 'estoque' => 4]);
        $this->assertDatabaseCount('produtos', 4);
    }

    public function test_delete_removes_only_the_requested_product(): void
    {
        $this->createProducts();
        $repository = app(ProdutoRepository::class);

        $this->assertTrue($repository->delete('2'));

        $this->assertNull($repository->find('2'));
        $this->assertDatabaseMissing('produtos', ['id' => 2]);
        $this->assertSame([1, 3, 4], $repository->search()->pluck('id')->all());
    }

    public function test_missing_products_return_null_or_false_without_changing_the_database(): void
    {
        $this->createProducts();
        $repository = app(ProdutoRepository::class);

        $this->assertNull($repository->find('99'));
        $this->assertNull($repository->update('99', ['preco' => 10]));
        $this->assertFalse($repository->delete('99'));
        $this->assertSame([1, 2, 3, 4], $repository->search()->pluck('id')->all());
    }

    public function test_the_eloquent_driver_preserves_crud_responses_and_validation(): void
    {
        $this->assertInstanceOf(ProdutoRepository::class, app(ProdutoRepositoryInterface::class));
        $dados = ['nome' => 'Mouse', 'preco' => 50.5, 'estoque' => 2];

        $response = $this->postJson('/api/produtos', $dados)->assertCreated();
        $id = $response->json('data.id');
        $response->assertExactJson(['data' => ['id' => $id, ...$dados]]);
        $this->getJson('/api/produtos/'.$id)->assertOk()->assertExactJson(['data' => ['id' => $id, ...$dados]]);

        $this->putJson('/api/produtos/'.$id, ['preco' => 80])
            ->assertOk()->assertExactJson(['data' => ['id' => $id, ...$dados, 'preco' => 80]]);
        $this->patchJson('/api/produtos/'.$id, ['estoque' => 0])
            ->assertOk()->assertJsonPath('data.estoque', 0)->assertJsonPath('data.preco', 80);

        $this->patchJson('/api/produtos/'.$id, ['preco' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['preco']);
        $this->getJson('/api/produtos?preco_min=100&preco_max=50')
            ->assertUnprocessable()->assertJsonValidationErrors(['preco_max']);
        $this->assertDatabaseHas('produtos', ['id' => $id, 'preco' => 80, 'estoque' => 0]);

        $this->deleteJson('/api/produtos/'.$id)->assertNoContent();
        $this->getJson('/api/produtos/'.$id)->assertNotFound()->assertExactJson(['mensagem' => 'Produto não encontrado']);
        $this->putJson('/api/produtos/'.$id, ['preco' => 20])->assertNotFound()->assertExactJson(['mensagem' => 'Produto não encontrado']);
        $this->deleteJson('/api/produtos/'.$id)->assertNotFound()->assertExactJson(['mensagem' => 'Produto não encontrado']);
        $this->assertDatabaseCount('produtos', 0);
    }

    private function createProducts(): void
    {
        Produto::query()->insert([
            ['id' => 1, 'nome' => 'Brinde', 'preco' => 0, 'estoque' => 0],
            ['id' => 2, 'nome' => 'Mouse', 'preco' => 50.5, 'estoque' => 2],
            ['id' => 3, 'nome' => 'Teclado', 'preco' => 100.75, 'estoque' => 4],
            ['id' => 4, 'nome' => 'Monitor', 'preco' => 200, 'estoque' => 1],
        ]);
    }
}
