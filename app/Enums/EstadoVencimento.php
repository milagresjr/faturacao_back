<?php

namespace App\Enums;

enum EstadoVencimento: String
{
    case NO_PRAZO = 'no_prazo';
    case VENCIDO = 'vencido';
    case EM_ATRASO = 'em_atraso';
}
