<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdutoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'nome' => $this->resource['nome'],
            'preco' => (float) $this->resource['preco'],
            'estoque' => (int) $this->resource['estoque'],
        ];
    }
}
