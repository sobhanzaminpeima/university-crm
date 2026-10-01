<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

final class SecretValue
{
    private const PREFIX = 'enc:';

    public static function encrypt(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return str_starts_with($value, self::PREFIX)
            ? $value
            : self::PREFIX.Crypt::encryptString($value);
    }

    public static function decrypt(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (!str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (\Throwable) {
            return '';
        }
    }
}
