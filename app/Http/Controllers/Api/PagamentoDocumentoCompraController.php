<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PagamentoDocumentoCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PagamentoDocumentoCompraController extends Controller
{
    public function index(Request $request) {}

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'valor' => 'required|integer',
            'data_pagamento' => 'date|nullable',
            'documento_compra_id' => 'integer|exists:documentos_compra,id',
            'observacao' => 'string|nullable'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validacao',
                'error' => $validated->errors()
            ]);
        }

        $data = $request->all();

        try {
            DB::beginTransaction();

            $pagamento =  PagamentoDocumentoCompra::create($data);
            
            DB::commit();

            return response()->json([
                'message' => 'Pagamento realizado com sucesso!',
                'pagamento' =>  $pagamento
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao cadastrar pagamento',
                'error' => $th->getMessage()
            ]);
        }
    }

    public function destroy(string $id)
    {
        $pagamento = PagamentoDocumentoCompra::find($id);

        if(!$pagamento) {
            return "Pagamento nao encontrado";
        }

        $pagamento->delete();

        return response()->json([
            'message' => 'Pagamento excluido.'
        ]);
    }
}
