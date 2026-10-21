<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UsuariosExternosApiTest extends TestCase
{
    public function test_returns_the_users_received_from_the_external_api(): void
    {
        Http::preventStrayRequests();
        $usuarios = [['id' => 1, 'name' => 'Maria', 'email' => 'maria@example.com']];
        Http::fake([
            'https://jsonplaceholder.typicode.com/users' => Http::response($usuarios, 200),
        ]);

        $response = $this->getJson('/api/usuarios-externos');

        $response->assertOk()->assertExactJson($usuarios);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://jsonplaceholder.typicode.com/users');
    }

    #[TestWith([404])]
    #[TestWith([500])]
    public function test_returns_502_when_the_external_api_returns_an_error(int $status): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://jsonplaceholder.typicode.com/users' => Http::response([], $status),
        ]);

        $response = $this->getJson('/api/usuarios-externos');

        $response->assertStatus(502)->assertExactJson([
            'mensagem' => 'A API externa não retornou uma resposta válida. Tente novamente mais tarde.',
        ]);
        Http::assertSentCount(1);
    }

    #[TestWith(['resposta sem JSON'])]
    #[TestWith(['{"erro":"resposta inesperada"}'])]
    public function test_returns_502_when_the_external_api_does_not_return_a_users_list(string $body): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://jsonplaceholder.typicode.com/users' => Http::response($body, 200),
        ]);

        $response = $this->getJson('/api/usuarios-externos');

        $response->assertStatus(502)->assertExactJson([
            'mensagem' => 'A API externa não retornou uma resposta válida. Tente novamente mais tarde.',
        ]);
        Http::assertSentCount(1);
    }

    public function test_returns_502_when_the_external_api_connection_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://jsonplaceholder.typicode.com/users' => Http::failedConnection(),
        ]);

        $response = $this->getJson('/api/usuarios-externos');

        $response->assertStatus(502)->assertExactJson([
            'mensagem' => 'Não foi possível conectar à API externa. Tente novamente mais tarde.',
        ]);
        Http::assertSentCount(1);
    }
}
