# SoftSeven Faturação — Backend

Laravel 12 / PHP 8.2 — API de faturação, stock e gestão empresarial.

## Setup e comandos

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
```

| Comando | O que faz |
|---|---|
| `composer dev` | Inicia servidor + queue listener + logs + Vite em simultâneo |
| `composer test` | Executa `config:clear` e depois `php artisan test` |
| `php artisan test --testsuite=Feature` | Apenas testes de funcionalidade |
| `php artisan test --testsuite=Unit` | Apenas testes unitários |
| `php artisan migrate --seed` | Migrações + seeds |
| `php artisan stock:recalcular-stock-atual` | Recalcula stock a partir de movimentações |
| `php artisan stock:verificar-stock-minimo` | Verifica stock baixo e envia SMS |

**Agendado:** `stock:verificar-minimo` (nota: o comando real chama-se `stock:verificar-stock-minimo`) executa a cada 5min em `routes/console.php`.

**Testes:** MySQL com base `softseven_test` (configurado em `phpunit.xml`). `phpunit.xml` alterado de SQLite para MySQL porque o driver `pdo_sqlite` não está disponível. Migrations têm FK problemático (`documentos` → `info_guias`) que impede uso de `RefreshDatabase`; usar `DatabaseTransactions` ou criar tabelas manualmente nos testes de funcionalidade.

## Arquitetura

- **Auth:** Middleware personalizado `AuthenticateWithRememberToken` verifica `Authorization: Bearer` contra `utilizadores.remember_token`. Sanctum `auth:sanctum` também usado nalgumas rotas. Middleware `ForcePasswordChange` bloqueia utilizadores com `must_change_password`.
- **Idioma:** Tudo em português — models, controllers, rotas, enums, comentários, nomes de tabelas. `Utilizador` em vez de `User`, `senha` em vez de `password`.
- **Enums:** Nativos do PHP 8.1 (`App\Enums\EstadoDocumento`, `EstadoPagamento`, `EstadoVencimento`).
- **Services:** Lógica de negócio em `App\Services\` (`DocumentoService`, `StockService`, `SmsService`, `LogotipoService`). `DocumentoService` tem dois métodos de atualização de stock — `updateStock` (mais recente, com lotes/validade) e `updateStock2` (mais antigo, simples). `LogotipoService::carregar($empresaId)` centraliza o carregamento do logotipo da empresa para PDFs.
- **PDF:** `barryvdh/laravel-dompdf` + `endroid/qr-code` para faturas com QR codes.
- **SMS:** API Useombala para alertas de stock baixo (configurado em `services.sms.*`).
- **Frontend:** SPA em `https://softseven-faturacao-front.vercel.app`. CORS permite `localhost:3000` para desenvolvimento.
- **Storage/Cache/Fila:** Padrão é driver `database`. Sessão também `database`.

## Convenções importantes

- Todas as rotas da API em `routes/api.php` — maior parte sob middleware `AuthenticateWithRememberToken` + `ForcePasswordChange`.
- Autorização usa `Perfil` (Role) + `Permissao` (Permission). Verificar com `$user->temPermissao('nome')`.
- Models usam nomes de tabelas personalizados (`$table = 'utilizadores'`, etc.).
- Evitar modificar ficheiros em `config/` a menos que seja intencional.
