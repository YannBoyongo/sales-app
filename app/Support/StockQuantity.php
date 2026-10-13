<?php

namespace App\Support;

final class StockQuantity
{
    public const SCALE = 3;

    public static function normalize(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return number_format(0, self::SCALE, '.', '');
        }

        return number_format((float) $value, self::SCALE, '.', '');
    }

    public static function format(float|int|string|null $value): string
    {
        $normalized = self::normalize($value);
        if (! str_contains($normalized, '.')) {
            return $normalized;
        }

        return rtrim(rtrim($normalized, '0'), '.') ?: '0';
    }

    public static function add(float|int|string $a, float|int|string $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(float|int|string $a, float|int|string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function comp(float|int|string $a, float|int|string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function isZero(float|int|string|null $value): bool
    {
        return self::comp($value ?? 0, 0) === 0;
    }

    public static function isPositive(float|int|string|null $value): bool
    {
        return self::comp($value ?? 0, 0) === 1;
    }

    public static function abs(float|int|string $value): string
    {
        $normalized = self::normalize($value);

        return self::comp($normalized, 0) < 0 ? self::sub(0, $normalized) : $normalized;
    }

    public static function min(float|int|string $a, float|int|string $b): string
    {
        return self::comp($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    /** @return list<string> */
    public static function validationRules(bool $required = true, bool $allowZero = true): array
    {
        $rules = ['numeric', 'regex:/^\d+(\.\d{1,'.self::SCALE.'})?$/'];
        if ($required) {
            array_unshift($rules, 'required');
        }
        $rules[] = $allowZero ? 'min:0' : 'min:0.001';

        return $rules;
    }
}
