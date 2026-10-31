<?php

namespace App\Services;

use App\Contracts\ProdutoRepositoryInterface;
use App\Models\Produto;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProdutoService
{
    public function __construct(private ProdutoRepositoryInterface $produtoRepository) {}

    /**
     * @return list<array{id: int, nome: string, preco: int|float, estoque: int}>
     */
    public function index(array $filtros = []): array
    {
        $regras = [
            'preco_min' => 'sometimes|required|numeric|min:0',
            'preco_max' => 'sometimes|required|numeric|min:0',
        ];

        if (isset($filtros['preco_min'], $filtros['preco_max'])) {
            $regras['preco_max'] .= '|gte:preco_min';
        }

        $filtros = Validator::make($filtros, $regras)->validate();

        return $this->produtoRepository->search($filtros)
            ->map(fn (Produto|array $produto): array => $this->paraArray($produto))
            ->all();
    }

    /**
     * @return array{id: int, nome: string, preco: int|float, estoque: int}|null
     */
    public function find(string $id): ?array
    {
        $produto = $this->produtoRepository->find($id);

        return $produto === null ? null : $this->paraArray($produto);
    }

    /**
     * @return array{id: int, nome: string, preco: int|float, estoque: int}
     *
     * @throws ValidationException
     */
    public function create(array $dados): array
    {
        return $this->paraArray($this->produtoRepository->create($this->validarDados($dados)));
    }

    /**
     * @return array{id: int, nome: string, preco: int|float, estoque: int}|null
     *
     * @throws ValidationException
     */
    public function update(string $id, array $dados): ?array
    {
        $dados = $this->validarDados($dados, parcial: true);
        $produto = $this->produtoRepository->update($id, $dados);

        return $produto === null ? null : $this->paraArray($produto);
    }

    public function delete(string $id): bool
    {
        return $this->produtoRepository->delete($id);
    }

    /**
     * @return array{id: int, nome: string, preco: int|float, estoque: int}
     */
    private function paraArray(Produto|array $produto): array
    {
        return $produto instanceof Produto ? $produto->only(['id', 'nome', 'preco', 'estoque']) : $produto;
    }

    /**
     * @throws ValidationException
     */
    private function validarDados(array $dados, bool $parcial = false): array
    {
        $regras = [
            'nome' => ($parcial ? 'sometimes|' : '').'required|string|max:255',
            'preco' => ($parcial ? 'sometimes|' : '').'required|numeric|min:0',
            'estoque' => ($parcial ? 'sometimes|' : '').'required|integer|min:0',
        ];

        return Validator::make($dados, $regras, [
            'nome.required' => 'Informe o nome do produto.',
            'nome.string' => 'O nome deve ser um texto.',
            'nome.max' => 'O nome deve ter no máximo 255 caracteres.',
            'preco.required' => 'Informe o preço do produto.',
            'preco.numeric' => 'O preço deve ser um número.',
            'preco.min' => 'O preço não pode ser negativo.',
            'estoque.required' => 'Informe o estoque do produto.',
            'estoque.integer' => 'O estoque deve ser um número inteiro.',
            'estoque.min' => 'O estoque não pode ser negativo.',
        ])->validate();
    }
}
