<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProdutoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use JsonException;

class ProdutoController extends Controller
{
    /**
     * @return list<array{id: int, nome: string, preco: int|float, estoque: int}>
     */
    private function lerProdutos(): array
    {
        $caminho = storage_path('app/produtos.json');

        if (! file_exists($caminho)) {
            abort_if(file_put_contents($caminho, '[]', LOCK_EX) === false, 500, 'Não foi possível criar o arquivo de produtos.');
        }

        $conteudo = file_get_contents($caminho);

        abort_if($conteudo === false, 500, 'Não foi possível ler o arquivo de produtos.');

        try {
            $produtos = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(500, 'O arquivo de produtos contém JSON inválido. Os dados não foram alterados.');
        }

        abort_unless(is_array($produtos) && array_is_list($produtos), 500, 'O arquivo de produtos deve conter uma lista válida.');

        return $produtos;
    }

    /**
     * @param  list<array{id: int, nome: string, preco: int|float, estoque: int}>  $produtos
     */
    private function salvarProdutos(array $produtos): void
    {
        $caminho = storage_path('app/produtos.json');

        $resultado = file_put_contents(
            $caminho,
            json_encode($produtos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            LOCK_EX
        );

        abort_if($resultado === false, 500, 'Não foi possível salvar o arquivo de produtos.');
    }

    public function index(): AnonymousResourceCollection
    {
        $produtos = $this->lerProdutos();

        return ProdutoResource::collection($produtos);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:255',
            'preco' => 'required|numeric|min:0',
            'estoque' => 'required|integer|min:0',
        ], $this->mensagensValidacao());

        $produtos = $this->lerProdutos();

        $ids = array_column($produtos, 'id');

        $novoProduto = [
            'id' => empty($ids) ? 1 : max($ids) + 1,
            'nome' => $dados['nome'],
            'preco' => $dados['preco'],
            'estoque' => $dados['estoque'],
        ];

        $produtos[] = $novoProduto;

        $this->salvarProdutos($produtos);

        return (new ProdutoResource($novoProduto))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): ProdutoResource|JsonResponse
    {
        $produtos = $this->lerProdutos();

        foreach ($produtos as $produto) {
            if ($produto['id'] == $id) {
                return new ProdutoResource($produto);
            }
        }

        return response()->json([
            'mensagem' => 'Produto não encontrado',
        ], 404);
    }

    public function update(Request $request, string $id): ProdutoResource|JsonResponse
    {
        $dados = $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'preco' => 'sometimes|required|numeric|min:0',
            'estoque' => 'sometimes|required|integer|min:0',
        ], $this->mensagensValidacao());

        $produtos = $this->lerProdutos();

        foreach ($produtos as $indice => $produto) {
            if ($produto['id'] == $id) {
                $produtos[$indice] = array_merge(
                    $produto,
                    $dados
                );

                $this->salvarProdutos($produtos);

                return new ProdutoResource(
                    $produtos[$indice]
                );
            }
        }

        return response()->json([
            'mensagem' => 'Produto não encontrado',
        ], 404);
    }

    public function destroy(string $id): Response|JsonResponse
    {
        $produtos = $this->lerProdutos();

        foreach ($produtos as $indice => $produto) {
            if ($produto['id'] == $id) {
                unset($produtos[$indice]);

                $this->salvarProdutos(
                    array_values($produtos)
                );

                return response()->noContent();
            }
        }

        return response()->json([
            'mensagem' => 'Produto não encontrado',
        ], 404);
    }

    /**
     * @return array<string, string>
     */
    private function mensagensValidacao(): array
    {
        return [
            'nome.required' => 'Informe o nome do produto.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 255 caracteres.',
            'preco.required' => 'Informe o preço do produto.',
            'preco.numeric' => 'O preço deve ser um número.',
            'preco.min' => 'O preço não pode ser negativo.',
            'estoque.required' => 'Informe o estoque do produto.',
            'estoque.integer' => 'O estoque deve ser um número inteiro.',
            'estoque.min' => 'O estoque não pode ser negativo.',
        ];
    }
}
