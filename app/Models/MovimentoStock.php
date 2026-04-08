<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimentoStock extends Model
{
    protected $table = 'movimentos_stock';

    protected $appends = ['tipo_documento'];

    protected $fillable = [
        'id',
        'armazem_id',
        'stock_id',
        'produto_id',
        'quantidade',
        'operacao',
        'observacao',
        'utilizador_id',
        'origem_movimento',
        'armazem_origem_id',
        'armazem_destino_id',
        'empresa_id',
        // NOVOS CAMPOS
        'data_validade_lote',
        'detalhes_lote',
        'observacao',
        'lote_id',
        'codigo_lote',
        'data_validade_momento'
    ];

    protected $casts = [
        'detalhes_lote' => 'array',
        'data_validade_lote' => 'date'
    ];

    public function getTipoDocumentoAttribute()
    {
        return match ($this->documento_type) {
            Documento::class => 'VENDA',
            DocumentoCompra::class => 'COMPRA',
            DocumentoInterno::class => 'INTERNO',
            default => 'DESCONHECIDO',
        };
    }


    // NOVA RELAÇÃO
    public function lote()
    {
        return $this->belongsTo(LoteProduto::class, 'lote_id');
    }

    // Verificar se o movimento tem lote
    public function temLote()
    {
        return !is_null($this->lote_id);
    }

    // Produto tem validade?
    public function produtoControlaValidade()
    {
        return $this->produto && $this->produto->controla_validade;
    }

    /**
     * Relacionamento com Produto
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function armazem()
    {
        return $this->belongsTo(Armazem::class);
    }

    public function documento()
    {
        return $this->morphTo();
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Usuário responsável (opcional)
     */
    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }

    //Registrar movimento de venda com lote
    public static function registrarVendaComLote($produto, $lote, $quantidade, $faturaId = null)
    {
        return self::create([
            'id_produto' => $produto->id,
            'operacao' => 'saida',
            'quantidade' => $quantidade,
            // 'preco_unitario' => $preco,
            'lote_id' => $lote->id,
            'codigo_lote' => $lote->codigo_lote,
            'data_validade_momento' => $lote->data_validade,
            'observacao' => "Venda - Fatura: {$faturaId} - Lote: {$lote->codigo_lote}"
        ]);
    }
}
