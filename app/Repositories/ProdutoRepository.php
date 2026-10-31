<?php

namespace App\Repositories;

use App\Contracts\ProdutoRepositoryInterface;
use App\Models\Produto;
use Illuminate\Support\Collection;

class ProdutoRepository implements ProdutoRepositoryInterface
{
    /** @return Collection<int, Produto> */
    public function search(array $filtros = []): Collection
    {
        $query = Produto::query();

        if (isset($filtros['preco_min'])) {
            $query->where('preco', '>=', $filtros['preco_min']);
        }

        if (isset($filtros['preco_max'])) {
            $query->where('preco', '<=', $filtros['preco_max']);
        }

        return $query->orderBy('id')->get();
    }

    public function find(string $id): ?Produto
    {
        return Produto::query()->find($id);
    }

    public function create(array $dados): Produto
    {
        return Produto::query()->create($dados);
    }

    public function update(string $id, array $dados): ?Produto
    {
        $produto = $this->find($id);

        if ($produto === null) {
            return null;
        }

        $produto->update($dados);

        return $produto->refresh();
    }

    public function delete(string $id): bool
    {
        $produto = $this->find($id);

        if ($produto === null) {
            return false;
        }

        return (bool) $produto->delete();
    }
}
