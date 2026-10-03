<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryCodeService
{
    public const CODE_COUNT = 10;

    /**
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generate(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    /**
     * @param  list<string>  $hashedCodes
     * @return array{valid: bool, remaining: list<string>}
     */
    public function consume(string $code, array $hashedCodes): array
    {
        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashedCodes[$index]);

                return ['valid' => true, 'remaining' => array_values($hashedCodes)];
            }
        }

        return ['valid' => false, 'remaining' => $hashedCodes];
    }
}
