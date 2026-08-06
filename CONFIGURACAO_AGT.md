# Configuração Faturação Eletrónica — AGT (Angola)

Este documento lista todos os pontos que **tu deves preencher com dados reais** da vossa empresa e da AGT.

---

## 1. Número de Validação do Software (junto da AGT)

O software de faturação precisa de ser certificado pela AGT para obter um **Número de Validação de Software**.

### Onde usar

**Ficheiro:** `app/Http/Controllers/Api/SaftController.php`

Localiza a linha que contém o número de validação:

```php
'numero_validacao_software' => '123456/AGT/2023',  // <-- SUBSTITUIR
```

Substituir `123456/AGT/2023` pelo número real obtido após certificação.

### Outras referências

No PDF de recibos (`app/Services/FaturaPdfService.php`), procurar:

```php
$canvas->text(40, $canvas->get_height() - 38, 'FzBf-Processado por programa validado n. /AGT/2019', ...);
```

Substituir a string de texto pela que a AGT exigir (geralmente inclui o número de validação).

---

## 2. Chave Privada RSA (assinatura digital)

A hash AGT é gerada com uma chave privada RSA 1024 bits.

### Localização esperada

```
storage/app/keys/ChavePrivada.pem
```

### O que fazer

1. **Gerar** o par de chaves (pode usar OpenSSL):
   ```bash
   openssl genrsa -out ChavePrivada.pem 1024
   openssl rsa -in ChavePrivada.pem -pubout -out ChavePublica.pem
   ```

2. **Colocar** `ChavePrivada.pem` em `storage/app/keys/`

3. **Comunicar** a chave pública (`ChavePublica.pem`) à AGT conforme o processo de certificação

### Referência no código

**Ficheiro:** `app/Services/AgtHashService.php`

```php
$privateKey = openssl_pkey_get_private(
    file_get_contents(storage_path('app/keys/ChavePrivada.pem'))
);
```

Se o caminho ou nome do ficheiro for diferente, atualizar aqui.

---

## 3. Base de Dados — Migrations AGT

As migrations para as tabelas AGT já foram criadas. Executar:

```bash
php artisan migrate
```

Isto criará duas tabelas:

- `configuracoes_agt` — configurações AGT por empresa
- `comunicacoes_agt` — registo de comunicações com a AGT

### Povoar configurações

Inserir registo para cada empresa:

```sql
INSERT INTO configuracoes_agt (empresa_id, numero_validacao_software, ambiente, comunicacao_ativa)
VALUES (1, 'NUMERO_REAL_AGT', 'testes', true);
```

**Campos:**
- `numero_validacao_software` — o número obtido no passo 1
- `certificado_digital` — (opcional) certificado digital se a AGT exigir
- `ambiente` — `'testes'` para desenvolvimento, `'producao'` quando em produção
- `comunicacao_ativa` — `true` para ativar o envio automático

---

## 4. Webservice AGT (endpoint de comunicação)

O `AgtComunicacaoService` tem um método `enviarParaAGT()` que atualmente retorna sucesso simulado.

**Ficheiro:** `app/Services/AgtComunicacaoService.php`

**Método a implementar:** `enviarParaAGT()`

Substituir o conteúdo atual:

```php
// TODO: Implementar chamada real ao webservice da AGT
return [
    'status' => 'confirmado',
    'codigo_validacao' => 'AGT-' . strtoupper(uniqid()),
    'mensagem' => 'Documento recebido com sucesso.',
];
```

Por:

```php
// Chamada HTTP real para a AGT
$url = $config->ambiente === 'producao'
    ? 'https://ws.agt.ao/api/documentos'  // <-- SUBSTITUIR pela URL real
    : 'https://test.agt.ao/api/documentos'; // <-- SUBSTITUIR pela URL de testes

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $config->token_agt, // se aplicável
    'Content-Type' => 'application/json',
])->post($url, $payload);

if ($response->successful()) {
    return [
        'status' => 'confirmado',
        'codigo_validacao' => $response->json('codigo_validacao'),
        'mensagem' => 'Documento recebido com sucesso.',
    ];
} else {
    return [
        'status' => 'erro',
        'codigo_erro' => $response->status(),
        'mensagem' => $response->body(),
    ];
}
```

**Nota:** A URL, headers e formato do payload devem ser confirmados junto da AGT ou consultando a documentação oficial.

---

## 5. Configuração do Software na AGT (processo de certificação)

Além da parte técnica, é necessário o processo administrativo:

- [ ] Registar a empresa no portal da AGT
- [ ] Submeter o software para certificação (geralmente requer manual, testes, etc.)
- [ ] Obter o **Número de Validação do Software**
- [ ] Comunicar a chave pública RSA à AGT
- [ ] Obter as credenciais de acesso ao webservice (se aplicável)
- [ ] Configurar ambiente de testes na AGT (sandbox)

Para mais informações, contactar a **Administração Geral Tributária (AGT)**:
- Website: https://www.agt.minfin.gov.ao
- Portal do Contribuinte: https://www.portaldocontribuinte.ao

---

## 6. Variáveis de Ambiente (recomendado)

Adicionar ao `.env`:

```env
# AGT
AGT_AMBIENTE=testes
AGT_TOKEN=                       # token de autenticação (se aplicável)
AGT_CERTIFICADO_DIGITAL=         # caminho para certificado (se aplicável)
```

E atualizar o `config/services.php`:

```php
'agt' => [
    'ambiente' => env('AGT_AMBIENTE', 'testes'),
    'token' => env('AGT_TOKEN'),
    'url_teste' => env('AGT_URL_TESTE', 'https://test.agt.ao/api'),
    'url_producao' => env('AGT_URL_PRODUCAO', 'https://ws.agt.ao/api'),
],
```

Depois usar `config('agt.url_teste')` no `AgtComunicacaoService` em vez de URLs hardcoded.

---

## 7. Verificação Final

Após configurar tudo:

1. **Criar uma fatura de teste** via API e verificar se o `AgtComunicacaoService` regista a comunicação
2. **Consultar a tabela** `comunicacoes_agt` para ver o status
3. **Gerar SAF-T** e validar o XML com as ferramentas da AGT
4. **Verificar a hash** no PDF da fatura (QR code)
