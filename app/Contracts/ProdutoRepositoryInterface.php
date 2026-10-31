<?php

namespace App\Contracts;

use App\Models\Produto;
use Illuminate\Support\Collection;

interface ProdutoRepositoryInterface
{
    public function search(array $filtros = []): Collection;

    public function find(string $id): Produto|array|null;

    public function create(array $dados): Produto|array;

    public function update(string $id, array $dados): Produto|array|null;

    public function delete(string $id): bool;
}
