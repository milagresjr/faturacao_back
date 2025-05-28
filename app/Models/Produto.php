<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{

    protected $table = 'produtos';

    protected $fillable = [
        'descricao',
        'preco_custo',
        'preco_venda',
        'stock_min',
        'stock_max',
        'stock_ideial',
        'modelo',
        'imagem',
        'movimenta_stock',
        'estado',
        'marca_id',
        'tipo_id',
        'armazem_id',
        'categoria_id',
        'sub_categoria_id',
        'empresa_id',
        'fornecedor_id',
        'utilizador_id'
    ];

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function tipo()
    {
        return $this->belongsTo(TipoProduto::class);
    }

    public function armazem()
    {
        return $this->belongsTo(Armazem::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaProduto::class);
    }

    public function subCategoria()
    {
        return $this->belongsTo(SubCategoria::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class);
    }
}
