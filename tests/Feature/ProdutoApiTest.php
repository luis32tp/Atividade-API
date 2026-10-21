<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProdutoApiTest extends TestCase
{
    private string $temporaryStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryStoragePath = storage_path('framework/testing/produto-api-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($this->temporaryStoragePath.'/app');
        $this->app->useStoragePath($this->temporaryStoragePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStoragePath);

        parent::tearDown();
    }

    public function test_lists_products_with_their_resource_fields(): void
    {
        $produtos = [['id' => 1, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4]];
        $this->writeProducts($produtos);

        $response = $this->getJson('/api/produtos');

        $response->assertOk()->assertExactJson(['data' => $produtos]);
    }

    public function test_creates_a_product_returns_201_and_persists_it(): void
    {
        $this->writeProducts([['id' => 7, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2]]);
        $dados = ['nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4];

        $response = $this->postJson('/api/produtos', $dados);

        $response->assertCreated()->assertExactJson(['data' => ['id' => 8, ...$dados]]);
        $this->assertSame([
            ['id' => 7, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2],
            ['id' => 8, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4],
        ], $this->readProducts());
    }

    public function test_returns_the_product_matching_the_requested_id(): void
    {
        $produto = ['id' => 2, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2];
        $this->writeProducts([
            ['id' => 1, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4],
            $produto,
        ]);

        $response = $this->getJson('/api/produtos/2');

        $response->assertOk()->assertExactJson(['data' => $produto]);
    }

    #[TestWith(['PUT'])]
    #[TestWith(['PATCH'])]
    public function test_updates_the_product_and_persists_its_unchanged_fields(string $method): void
    {
        $this->writeProducts([['id' => 1, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4]]);
        $produtoAtualizado = ['id' => 1, 'nome' => 'Teclado', 'preco' => 99.5, 'estoque' => 4];

        $response = $this->json($method, '/api/produtos/1', ['preco' => 99.5]);

        $response->assertOk()->assertExactJson(['data' => $produtoAtualizado]);
        $this->assertSame([$produtoAtualizado], $this->readProducts());
    }

    public function test_deletes_the_requested_product_and_preserves_the_remaining_products(): void
    {
        $produtoRestante = ['id' => 2, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2];
        $this->writeProducts([
            ['id' => 1, 'nome' => 'Teclado', 'preco' => 120.5, 'estoque' => 4],
            $produtoRestante,
        ]);

        $response = $this->deleteJson('/api/produtos/1');

        $response->assertNoContent();
        $this->assertSame([$produtoRestante], $this->readProducts());
    }

    public function test_returns_422_for_missing_required_fields_without_changing_products(): void
    {
        $this->writeProducts([]);

        $response = $this->postJson('/api/produtos', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['nome', 'preco', 'estoque']);
        $response->assertJsonPath('errors.nome.0', 'Informe o nome do produto.')
            ->assertJsonPath('errors.preco.0', 'Informe o preço do produto.')
            ->assertJsonPath('errors.estoque.0', 'Informe o estoque do produto.');
        $this->assertSame([], $this->readProducts());
    }

    public function test_returns_404_for_an_unknown_product(): void
    {
        $this->writeProducts([]);

        $response = $this->getJson('/api/produtos/99');

        $response->assertNotFound()->assertExactJson(['mensagem' => 'Produto não encontrado']);
    }

    #[DataProvider('invalidProductFields')]
    public function test_returns_422_for_invalid_product_fields_without_changing_products(string $field, mixed $value, string $message): void
    {
        $produtos = [['id' => 1, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2]];
        $this->writeProducts($produtos);
        $dados = ['nome' => 'Notebook Acer Nitro V15', 'preco' => 4500, 'estoque' => 5];
        $dados[$field] = $value;

        $response = $this->postJson('/api/produtos', $dados);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([$field])
            ->assertJsonPath('errors.'.$field.'.0', $message);
        $this->assertSame($produtos, $this->readProducts());
    }

    public static function invalidProductFields(): array
    {
        return [
            'nome deve ser texto' => ['nome', 42, 'O nome deve ser um texto.'],
            'nome com mais de 255 caracteres' => ['nome', str_repeat('a', 256), 'O nome deve ter no máximo 255 caracteres.'],
            'preco deve ser numerico' => ['preco', 'caro', 'O preço deve ser um número.'],
            'preco negativo' => ['preco', -1, 'O preço não pode ser negativo.'],
            'estoque deve ser inteiro' => ['estoque', 1.5, 'O estoque deve ser um número inteiro.'],
            'estoque negativo' => ['estoque', -1, 'O estoque não pode ser negativo.'],
        ];
    }

    #[TestWith(['PUT'])]
    #[TestWith(['PATCH'])]
    public function test_returns_422_for_invalid_updates_without_changing_the_product(string $method): void
    {
        $produtos = [['id' => 1, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2]];
        $this->writeProducts($produtos);

        $response = $this->json($method, '/api/produtos/1', ['nome' => '', 'preco' => -1, 'estoque' => -1]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'preco', 'estoque'])
            ->assertJsonPath('errors.nome.0', 'Informe o nome do produto.')
            ->assertJsonPath('errors.preco.0', 'O preço não pode ser negativo.')
            ->assertJsonPath('errors.estoque.0', 'O estoque não pode ser negativo.');
        $this->assertSame($produtos, $this->readProducts());
    }

    public function test_accepts_zero_price_and_zero_stock(): void
    {
        $this->writeProducts([]);
        $dados = ['nome' => 'Brinde', 'preco' => 0, 'estoque' => 0];

        $response = $this->postJson('/api/produtos', $dados);

        $response->assertCreated()->assertExactJson(['data' => ['id' => 1, ...$dados]]);
        $this->assertSame([['id' => 1, ...$dados]], $this->readProducts());
    }

    #[TestWith(['PUT'])]
    #[TestWith(['PATCH'])]
    #[TestWith(['DELETE'])]
    public function test_returns_404_for_writes_to_an_unknown_product_without_changing_products(string $method): void
    {
        $produtos = [['id' => 1, 'nome' => 'Mouse', 'preco' => 40.5, 'estoque' => 2]];
        $this->writeProducts($produtos);

        $response = $this->json($method, '/api/produtos/99', ['preco' => 50]);

        $response->assertNotFound()->assertExactJson(['mensagem' => 'Produto não encontrado']);
        $this->assertSame($produtos, $this->readProducts());
    }

    #[TestWith(['JSON invalido'])]
    #[TestWith(['null'])]
    #[TestWith(['{"produto":"objeto em vez de lista"}'])]
    public function test_returns_500_for_corrupted_storage_without_overwriting_it(string $conteudo): void
    {
        File::put(storage_path('app/produtos.json'), $conteudo);

        $response = $this->postJson('/api/produtos', ['nome' => 'Mouse', 'preco' => 50, 'estoque' => 2]);

        $response->assertInternalServerError()->assertJsonStructure(['message']);
        $this->assertSame($conteudo, File::get(storage_path('app/produtos.json')));
    }

    public function test_lists_an_empty_collection_when_the_storage_file_does_not_exist(): void
    {
        $response = $this->getJson('/api/produtos');

        $response->assertOk()->assertExactJson(['data' => []]);
        $this->assertSame([], $this->readProducts());
    }

    #[TestWith(['http://localhost:5173'])]
    #[TestWith(['http://127.0.0.1:5173'])]
    public function test_allows_cors_preflight_from_the_vue_development_server(string $origin): void
    {
        $response = $this->options('/api/produtos', [], [
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeaderContains('Access-Control-Allow-Methods', 'POST')
            ->assertHeaderContains('Access-Control-Allow-Headers', 'content-type');
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
