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
        'stock_atual',
        'controla_validade',
        'modelo',
        'imagem',
        'movimenta_stock',
        'codigo_produto',
        'codigo_barra',
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

    //casts
    protected $casts = [
        'controla_validade' => 'boolean',
        'movimenta_stock' => 'boolean'
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

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'produto_id');
    }

    public function movimentosStock()
    {
        return $this->hasMany(MovimentoStock::class, 'produto_id');
    }

    //Um produto pode ter muitos lotes
    public function lotes()
    {
        return $this->hasMany(LoteProduto::class, 'produto_id');
    }

    // Configuração de validade
    public function configuracaoValidade()
    {
        return $this->hasOne(ConfigValidacaoProduto::class, 'produto_id');
    }

    //Verificar se o produto precisa controlar validade
    public function precisaControlarValidade()
    {
        return $this->controla_validade;
    }

    // Pegar stock total considerando apenas lotes válidos
    public function getStockValidoAttribute()
    {
        if (!$this->precisaControlarValidade()) {
            return $this->stock_atual; // Retorna o stock normal da tabela produtos
        }

        // Soma apenas lotes não vencidos
        return $this->lotes()
            ->where('status', 'activo')
            ->where('data_validade', '>=', now())
            ->sum('quantidade_actual');
    }

    //Atualizar stock na tabela produtos (para manter compatibilidade)
    public function atualizarStockConsolidado()
    {
        if ($this->precisaControlarValidade()) {
            $this->stock_atual = $this->stock_valido;
            $this->saveQuietly(); // salva sem disparar eventos
        }
    }
}
