<?php

namespace App\Services\Shared;

class Text
{
    public static function clean(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim(\Normalizer::normalize($value, \Normalizer::FORM_KC)));
    }

    public static function key(string $value): string
    {
        return mb_strtoupper(self::clean($value), 'UTF-8');
    }

    public static function grades(): array
    {
        return ['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'J1', 'J2', 'J3', 'S1', 'S2', 'S3'];
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'max:64', 'regex:/^(?=.*[A-Za-z])(?=.*[0-9]).+$/s', function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('密码的 UTF-8 长度不能超过 72 字节');
            }
        }];
    }
}
