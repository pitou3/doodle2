<?php
declare(strict_types=1);

namespace App;

final class Security
{
    // Minuscules et chiffres, sans caractères ambigus (l, 1, 0, o).
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';

    public static function randomToken(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= self::ALPHABET[random_int(0, $max)];
        }
        return $token;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isToken(string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9]{8,64}$/', $value);
    }
}
