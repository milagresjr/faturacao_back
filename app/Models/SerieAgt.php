<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerieAgt extends Model
{
    protected $table = 'series_agt';

    protected $fillable = [
        'empresa_id',
        'armazem_id',
        'series_code',
        'tipo_documento',
        'series_year',
        'contingency_indicator',
        'authorized_quantity',
        'first_document_no',
        'last_document_no',
        'status',
        'solicitud_payload',
        'resposta_payload',
    ];

    protected $casts = [
        'authorized_quantity' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function armazem(): BelongsTo
    {
        return $this->belongsTo(Armazem::class);
    }

    /**
     * Tipos de documento suportados pela AGT.
     */
    public static function tiposDocumento(): array
    {
        return [
            'FT' => 'Factura',
            'FR' => 'Factura/Recibo',
            'FG' => 'Factura Global',
            'FA' => 'Factura de Adiantamento',
            'GF' => 'Factura Genérica',
            'NC' => 'Nota de Crédito',
            'ND' => 'Nota de Débito',
            'RC' => 'Recibo em Numerário',
            'RG' => 'Recibo Geral',
            'RE' => 'Estorno',
            'AC' => 'Aviso de Cobrança',
            'AR' => 'Aviso de Cobrança/Recibo',
            'TV' => 'Talão de Venda',
            'AF' => 'Factura de Autofacturação',
            'RP' => 'Prémio',
            'RA' => 'Resseguro Aceite',
            'CS' => 'Imputação a Co-seguradoras',
            'LD' => 'Imputação a Co-seguradora Líder',
        ];
    }

    /**
     * Filtrar por empresa.
     */
    public function scopeDaEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /**
     * Filtrar por tipo de documento.
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_documento', $tipo);
    }

    /**
     * Filtrar por ano.
     */
    public function scopePorAno($query, string $ano)
    {
        return $query->where('series_year', $ano);
    }

    /**
     * Filtrar apenas activas.
     */
    public function scopeAtivas($query)
    {
        return $query->where('status', 'ativa');
    }
}
