<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{

    use SoftDeletes;

    protected $table = 'produtos';

    protected $appends = ['imagem_url'];

    protected $fillable = [
        'id',
        'nome',
        'descricao',
        'preco_custo',
        'preco_venda',
        'preco_final',
        'margem_lucro',
        'valor_iva',
        'stock_min',
        'stock_max',
        'stock_ideial',
        'modelo',
        'imagem',
        'movimenta_stock',
        'codigo_produto',
        'codigo_barra',
        'data_validade',
        'imposto',
        'unidade',
        'motivo_isencao_id',
        'estado',
        'tipo_stock_id',
        'marca_id',
        'tipo_id',
        'armazem_id',
        'categoria_id',
        'sub_categoria_id',
        'empresa_id',
        'fornecedor_id',
        'utilizador_id'
    ];

    //Esse método cria automaticamente o campo imagem_url sempre que um produto for convertido em JSON.
    public function getImagemUrlAttribute()
    {
        return $this->imagem ? asset('storage/produtos/' . $this->imagem) : null;
    }

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

    public function tipoStock()
    {
        return $this->belongsTo(TipoStock::class);
    }

    public function motivoIsencao()
    {
        return $this->belongsTo(MotivoIsencao::class);
    }

    public function tipoIva()
    {
        return $this->belongsTo(TipoTaxaIva::class, 'imposto', 'id');
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

    public function movimentosStock()
    {
        return $this->hasMany(MovimentoStock::class, 'produto_id');
    }
}
