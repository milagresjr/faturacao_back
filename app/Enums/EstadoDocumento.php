<?php

namespace App\Enums;

enum EstadoDocumento: String
{
    case RASCUNHO = 'rascunho';
    case EMITIDO = 'emitido';
    case ANULADO = 'anulado';
    case CANCELADO = 'cancelado';
    case ARQUIVADO = 'arquivado';
    case TRANSFORMADO = 'transformado';
}
