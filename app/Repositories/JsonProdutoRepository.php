<?php

namespace App\Repositories;

use App\Contracts\ProdutoRepositoryInterface;
use Illuminate\Support\Collection;
use JsonException;

class JsonProdutoRepository implements ProdutoRepositoryInterface
{
    public function search(array $filtros = []): Collection
    {
        return collect($this->lerProdutos())
            ->filter(function (array $produto) use ($filtros): bool {
                return (! isset($filtros['preco_min']) || $produto['preco'] >= $filtros['preco_min'])
                    && (! isset($filtros['preco_max']) || $produto['preco'] <= $filtros['preco_max']);
            })
            ->values();
    }

    public function find(string $id): ?array
    {
        foreach ($this->lerProdutos() as $produto) {
            if ($produto['id'] == $id) {
                return $produto;
            }
        }

        return null;
    }

    public function create(array $dados): array
    {
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

        return $novoProduto;
    }

    public function update(string $id, array $dados): ?array
    {
        $produtos = $this->lerProdutos();

        foreach ($produtos as $indice => $produto) {
            if ($produto['id'] == $id) {
                $produtos[$indice] = array_merge($produto, $dados);
                $this->salvarProdutos($produtos);

                return $produtos[$indice];
            }
        }

        return null;
    }

    public function delete(string $id): bool
    {
        $produtos = $this->lerProdutos();

        foreach ($produtos as $indice => $produto) {
            if ($produto['id'] == $id) {
                unset($produtos[$indice]);
                $this->salvarProdutos(array_values($produtos));

                return true;
            }
        }

        return false;
    }

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

    private function salvarProdutos(array $produtos): void
    {
        $resultado = file_put_contents(
            storage_path('app/produtos.json'),
            json_encode($produtos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            LOCK_EX
        );

        abort_if($resultado === false, 500, 'Não foi possível salvar o arquivo de produtos.');
    }
}
