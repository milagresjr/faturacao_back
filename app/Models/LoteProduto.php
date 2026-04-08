<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoteProduto extends Model
{
    use SoftDeletes;

    protected $table = 'lotes_produto';

    protected $fillable = [
        'produto_id',
        'armazem_id',
        'lote',
        'codigo_barra',
        'codigo_lote',
        'empresa_id',
        'data_fabricacao',
        'data_validade',
        'qtd_atual',
        'qtd_inicial',
        'status',
        'observacao'
    ];

    protected $casts = [
        'data_fabricacao' => 'date',
        'data_validade' => 'date',
        'qtd_actual' => 'decimal:3',
        'qtd_inicial' => 'decimal:3',
    ];

    // Relacionamentos
    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function armazem()
    {
        return $this->belongsTo(Armazem::class, 'armazem_id');
    }

    public function movimentos()
    {
        return $this->hasMany(MovimentoStock::class, 'lote_id');
    }

    public function notificacoes()
    {
        return $this->hasMany(NotificacaoValidade::class, 'lote_id');
    }

    // Accessors
    public function getDiasRestantesAttribute()
    {
        if (!$this->data_validade) return null;

        $hoje = Carbon::today();
        if ($this->data_validade->lt($hoje)) {
            return 0;
        }

        return $hoje->diffInDays($this->data_validade);
    }

    public function getStatusValidadeAttribute()
    {
        $dias = $this->dias_restantes;

        if ($dias <= 0) return 'expirado';

        $config = $this->produto->configuracaoValidade;
        if (!$config) return 'normal';

        if ($dias <= $config->dias_alerta_critico) return 'critico';
        if ($dias <= $config->dias_alerta_precoce) return 'precoce';

        return 'normal';
    }

    // Methods
    public function darBaixa($quantidade)
    {
        if ($quantidade > $this->quantidade_actual) {
            throw new \Exception("Quantidade insuficiente no lote {$this->codigo_lote}");
        }

        $this->quantidade_actual -= $quantidade;

        if ($this->quantidade_actual <= 0) {
            $this->status = 'consumido';
        }

        $this->save();

        // Atualizar stock consolidado no produto
        $this->produto->atualizarStockConsolidado();

        return true;
    }

    public function adicionarStock($quantidade)
    {
        $this->quantidade_actual += $quantidade;
        $this->save();

        // Atualizar stock consolidado no produto
        $this->produto->atualizarStockConsolidado();

        return true;
    }
}
