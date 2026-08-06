<?php

namespace App\Services;

use App\Models\ComunicacaoAgt;
use App\Models\ConfiguracaoAgt;
use App\Models\Documento;

class AgtComunicacaoService
{
    public function enviarDocumento(Documento $documento): array
    {
        $config = ConfiguracaoAgt::where('empresa_id', $documento->empresa_id)->first();

        if (!$config || !$config->comunicacao_ativa) {
            return [
                'success' => false,
                'message' => 'Comunicação AGT não configurada ou inativa.',
            ];
        }

        $payload = $this->montarPayload($documento);

        $comunicacao = ComunicacaoAgt::create([
            'empresa_id' => $documento->empresa_id,
            'documento_id' => $documento->id,
            'tipo_comunicacao' => 'envio_documento',
            'status' => 'pendente',
            'request_payload' => json_encode($payload),
            'tentativas' => 0,
        ]);

        try {
            $resposta = $this->enviarParaAGT($payload, $config);

            $comunicacao->update([
                'status' => $resposta['status'],
                'response_payload' => json_encode($resposta),
                'codigo_validacao_agt' => $resposta['codigo_validacao'] ?? null,
                'codigo_erro' => $resposta['codigo_erro'] ?? null,
                'tentativas' => $comunicacao->tentativas + 1,
                'ultima_tentativa' => now(),
            ]);

            return [
                'success' => $comunicacao->status === 'confirmado',
                'comunicacao' => $comunicacao,
            ];
        } catch (\Throwable $th) {
            $comunicacao->update([
                'status' => 'erro',
                'response_payload' => $th->getMessage(),
                'codigo_erro' => $th->getCode(),
                'tentativas' => $comunicacao->tentativas + 1,
                'ultima_tentativa' => now(),
            ]);

            return [
                'success' => false,
                'message' => $th->getMessage(),
                'comunicacao' => $comunicacao,
            ];
        }
    }

    private function montarPayload(Documento $documento): array
    {
        return [
            'numero_documento' => $documento->num_fatura,
            'tipo_documento' => $documento->tipo_sigla,
            'data_emissao' => $documento->data_emissao,
            'nif_emitente' => $documento->empresa_nif,
            'nif_adquirente' => $documento->cliente_nif,
            'nome_adquirente' => $documento->cliente_nome,
            'total_geral' => $documento->total_geral,
            'hash' => $documento->hash,
            'itens' => $documento->itens->map(fn($item) => [
                'produto' => $item->produto_nome,
                'quantidade' => $item->quantidade,
                'preco_unitario' => $item->preco_unitario,
                'total' => $item->total,
            ]),
        ];
    }

    private function enviarParaAGT(array $payload, ConfiguracaoAgt $config): array
    {
        // TODO: Implementar chamada real ao webservice da AGT
        // Por enquanto, retorna sucesso simulado
        return [
            'status' => 'confirmado',
            'codigo_validacao' => 'AGT-' . strtoupper(uniqid()),
            'mensagem' => 'Documento recebido com sucesso.',
        ];
    }

    public function consultarEstado(int $comunicacaoId): ?ComunicacaoAgt
    {
        return ComunicacaoAgt::find($comunicacaoId);
    }

    public function reenviar(int $comunicacaoId): array
    {
        $comunicacao = ComunicacaoAgt::findOrFail($comunicacaoId);
        $documento = Documento::find($comunicacao->documento_id);

        if (!$documento) {
            return ['success' => false, 'message' => 'Documento não encontrado.'];
        }

        return $this->enviarDocumento($documento);
    }
}
