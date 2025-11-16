<?php

namespace App\Enums;

enum EstadoPagamento: String
{
    case NAO_PAGO = 'nao_pago';
    case PARCIALMENTE_PAGO = 'parcialmente_pago';
    case PAGO = 'pago';
    case REEMBOLSADO = 'reembolsado';
}
