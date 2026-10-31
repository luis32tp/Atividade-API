<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'preco', 'estoque'])]
class Produto extends Model
{
    protected $table = 'produtos';

    protected function casts(): array
    {
        return [
            'preco' => 'float',
            'estoque' => 'integer',
        ];
    }
}
