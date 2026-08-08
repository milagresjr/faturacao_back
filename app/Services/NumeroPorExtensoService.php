<?php

namespace App\Services;

class NumeroPorExtensoService
{
    private const UNIDADES = [
        0 => 'zero', 1 => 'um', 2 => 'dois', 3 => 'três', 4 => 'quatro',
        5 => 'cinco', 6 => 'seis', 7 => 'sete', 8 => 'oito', 9 => 'nove',
        10 => 'dez', 11 => 'onze', 12 => 'doze', 13 => 'treze', 14 => 'catorze',
        15 => 'quinze', 16 => 'dezasseis', 17 => 'dezassete', 18 => 'dezoito',
        19 => 'dezanove',
    ];

    private const DEZENAS = [
        20 => 'vinte', 30 => 'trinta', 40 => 'quarenta', 50 => 'cinquenta',
        60 => 'sessenta', 70 => 'setenta', 80 => 'oitenta', 90 => 'noventa',
    ];

    private const CENTENAS = [
        100 => 'cento', 200 => 'duzentos', 300 => 'trezentos', 400 => 'quatrocentos',
        500 => 'quinhentos', 600 => 'seiscentos', 700 => 'setecentos',
        800 => 'oitocentos', 900 => 'novecentos',
    ];

    public function converter(float $valor): string
    {
        $partes = explode('.', number_format(abs(round($valor, 2)), 2, '.', ''));

        $kwanzas = (int) $partes[0];
        $centimos = (int) $partes[1];

        $textoKwanzas = $this->converterInteiro($kwanzas);

        if ($kwanzas === 1) {
            $palavraKwanza = 'Kwanza';
        } elseif ($kwanzas === 0) {
            $palavraKwanza = 'Kwanzas';
        } else {
            $palavraKwanza = 'Kwanzas';
        }

        if ($centimos > 0) {
            $textoCentimos = $this->converterInteiro($centimos);
            $palavraCentimo = $centimos === 1 ? 'Cêntimo' : 'Cêntimos';

            return "{$textoKwanzas} {$palavraKwanza} e {$textoCentimos} {$palavraCentimo}";
        }

        return "{$textoKwanzas} {$palavraKwanza}";
    }

    private function converterInteiro(int $numero): string
    {
        if ($numero < 0) {
            return 'menos ' . $this->converterInteiro(abs($numero));
        }

        if ($numero < 20) {
            return self::UNIDADES[$numero];
        }

        if ($numero < 100) {
            return $this->converterDezena($numero);
        }

        if ($numero < 1000) {
            return $this->converterCentena($numero);
        }

        if ($numero < 1000000) {
            return $this->converterMilhar($numero);
        }

        if ($numero < 1000000000) {
            return $this->converterMilhao($numero);
        }

        return $this->converterBilhao($numero);
    }

    private function converterDezena(int $numero): string
    {
        if ($numero < 20) {
            return self::UNIDADES[$numero];
        }

        $dezena = (int) (floor($numero / 10) * 10);
        $unidade = $numero % 10;

        if ($unidade === 0) {
            return self::DEZENAS[$dezena];
        }

        return self::DEZENAS[$dezena] . ' e ' . self::UNIDADES[$unidade];
    }

    private function converterCentena(int $numero): string
    {
        if ($numero === 100) {
            return 'cem';
        }

        $centena = (int) (floor($numero / 100) * 100);
        $resto = $numero % 100;

        if ($resto === 0) {
            return self::CENTENAS[$centena];
        }

        return self::CENTENAS[$centena] . ' e ' . $this->converterInteiro($resto);
    }

    private function converterMilhar(int $numero): string
    {
        $milhares = (int) floor($numero / 1000);
        $resto = $numero % 1000;

        $milhar = $milhares === 1
            ? 'mil'
            : $this->converterInteiro($milhares) . ' mil';

        if ($resto === 0) {
            return $milhar;
        }

        return $milhar . ' e ' . $this->converterInteiro($resto);
    }

    private function converterMilhao(int $numero): string
    {
        $milhoes = (int) floor($numero / 1000000);
        $resto = $numero % 1000000;

        $unidade = $milhoes === 1
            ? 'um milhão'
            : $this->converterInteiro($milhoes) . ' milhões';

        if ($resto === 0) {
            return $unidade;
        }

        return $unidade . ' e ' . $this->converterInteiro($resto);
    }

    private function converterBilhao(int $numero): string
    {
        $bilhoes = (int) floor($numero / 1000000000);
        $resto = $numero % 1000000000;

        $bilhao = $bilhoes === 1
            ? 'um bilião'
            : $this->converterInteiro($bilhoes) . ' biliões';

        if ($resto === 0) {
            return $bilhao;
        }

        return $bilhao . ' e ' . $this->converterInteiro($resto);
    }
}