# API de produtos

A API continua usando `storage/app/produtos.json` por padrão. O controller usa o
`ProdutoService`, que mantém as validações e delega a persistência ao contrato
`ProdutoRepositoryInterface`. O container escolhe `JsonProdutoRepository` ou
`ProdutoRepository`, conforme `PRODUTOS_DRIVER` (`json` ou `eloquent`).

Não havia filtros de busca implementados anteriormente. A listagem sem parâmetros,
as rotas, o resource, as mensagens de validação e as respostas do CRUD foram
preservados. A configuração de autenticação das rotas permanece a existente.

## Testar no Postman

Inicie o servidor com `php artisan serve` e use a URL base
`http://127.0.0.1:8000/api`. Envie `Accept: application/json` em todas as
requisições e `Content-Type: application/json` nas requisições com corpo.
A coleção existente em `postman/Produtos.postman_collection.json` continua válida.

1. Faça `POST /produtos` com o corpo abaixo. A resposta deve ser **201**;
   anote `data.id` para usar nas próximas requisições.

   ```json
   { "nome": "Teclado", "preco": 100.50, "estoque": 4 }
   ```

2. Consulte a listagem com **GET**. Cada resposta deve ser **200**, com um array
   em `data`:

   | Caminho | Resultado |
   | --- | --- |
   | `/produtos` | Todos os produtos |
   | `/produtos?preco_min=100.50` | Preço maior ou igual a 100,50 |
   | `/produtos?preco_max=100.50` | Preço menor ou igual a 100,50 |
   | `/produtos?preco_min=100.50&preco_max=100.50` | Preço exatamente 100,50 |
   | `/produtos?preco_min=50&preco_max=150` | Preço entre 50 e 150, inclusive |
   | `/produtos?preco_max=0` | Produtos com preço zero |

   Limites não numéricos, negativos, vazios ou com `preco_min > preco_max`
   retornam **422**. A ausência de resultados retorna `{"data":[]}`.
   Nos parâmetros e no JSON, use ponto como separador decimal.

3. Faça `PUT /produtos/{id}` ou `PATCH /produtos/{id}` com o corpo abaixo.
   A resposta deve ser **200**, com o produto atualizado em `data`. Os campos
   omitidos permanecem iguais. Confirme com `GET /produtos/{id}`.

   ```json
   { "preco": 90, "estoque": 0 }
   ```

   Preço negativo, estoque negativo ou nome vazio retornam **422**. Campos que
   não pertencem às regras de validação são ignorados.

4. Faça `DELETE /produtos/{id}`. A resposta deve ser **204**, sem corpo.
   Consultar, atualizar ou excluir novamente esse ID retorna **404**:
   `{"mensagem":"Produto não encontrado"}`.

## Alternativa Eloquent

`ProdutoRepository` usa o Model `Produto` e a tabela `produtos`, com as colunas
`id`, `nome`, `preco`, `estoque`, `created_at` e `updated_at`.
`search` combina os dois limites usando `>=` e `<=`.
`update` retorna o model atualizado, ou `null` se o ID não existir.
`delete` retorna `true` quando exclui e `false` se o ID não existir. O controller
mantém as respostas HTTP 404 para os dois casos de produto ausente.

A migration `2026_10_31_000000_create_produtos_table.php` foi preparada, mas não
foi executada. Para usar Eloquent posteriormente, primeiro disponibilize essa
tabela no banco configurado e depois defina `PRODUTOS_DRIVER=eloquent` no `.env`.
Se houver cache de configuração, atualize-o antes de reiniciar o servidor.
O driver padrão continua sendo `json`; não ocorre importação automática dos dados
do arquivo para o banco.

## Testes automatizados

Execute `php artisan test`. Os testes existentes usam arquivos JSON temporários.
Os testes de Eloquent criam somente uma tabela em uma conexão SQLite `:memory:`
e descartam a conexão ao terminar; não executam migrations e não usam o banco
configurado no `.env`.

Os testes de Eloquent exigem `pdo_sqlite` e são sinalizados como ignorados quando
a extensão está desativada. Se a DLL estiver instalada, é possível carregar a
extensão somente para o processo de testes no Windows:

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```
