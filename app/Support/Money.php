<?php

namespace App\Support;

final class Money
{
    /**
     * Format a numeric amount as US dollars (e.g. $1,234.56).
     */
    public static function usd(float|int|string $amount): string
    {
        return '$'.number_format((float) $amount, 2, '.', ',');
    }

    /**
     * Format a numeric amount as Congolese francs (e.g. 144 000 CDF).
     */
    public static function cdf(float|int|string $amount, int $decimals = 0): string
    {
        return number_format((float) $amount, $decimals, ',', ' ').' CDF';
    }
}
