<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProdutoResource;
use App\Services\ProdutoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProdutoController extends Controller
{
    public function index(Request $request, ProdutoService $produtoService): AnonymousResourceCollection
    {
        return ProdutoResource::collection($produtoService->index($request->query()));
    }

    public function store(Request $request, ProdutoService $produtoService): JsonResponse
    {
        $novoProduto = $produtoService->create($request->all());

        return (new ProdutoResource($novoProduto))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, ProdutoService $produtoService): ProdutoResource|JsonResponse
    {
        $produto = $produtoService->find($id);

        if ($produto !== null) {
            return new ProdutoResource($produto);
        }

        return response()->json([
            'mensagem' => 'Produto não encontrado',
        ], 404);
    }

    public function update(Request $request, string $id, ProdutoService $produtoService): ProdutoResource|JsonResponse
    {
        $produto = $produtoService->update($id, $request->all());

        if ($produto !== null) {
            return new ProdutoResource($produto);
        }

        return response()->json([
            'mensagem' => 'Produto não encontrado',
        ], 404);
    }

    public function destroy(string $id, ProdutoService $produtoService): Response|JsonResponse
    {
        if ($produtoService->delete($id)) {
            return response()->noContent();
        }

        return response()->json([
            'mensagem' => 'Produto não encontrado',
        ], 404);
    }
}
