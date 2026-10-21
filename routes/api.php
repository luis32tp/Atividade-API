<?php

use App\Http\Controllers\Api\ProdutoController;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/usuarios-externos', function (): JsonResponse {
    try {
        $resposta = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->get('https://jsonplaceholder.typicode.com/users');
    } catch (ConnectionException) {
        return response()->json([
            'mensagem' => 'Não foi possível conectar à API externa. Tente novamente mais tarde.',
        ], 502);
    }

    $usuarios = $resposta->json();

    if (! $resposta->successful() || ! is_array($usuarios) || ! array_is_list($usuarios)) {
        return response()->json([
            'mensagem' => 'A API externa não retornou uma resposta válida. Tente novamente mais tarde.',
        ], 502);
    }

    return response()->json($usuarios);
});

Route::apiResource('produtos', ProdutoController::class);
