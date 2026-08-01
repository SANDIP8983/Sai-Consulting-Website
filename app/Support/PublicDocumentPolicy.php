<?php

namespace App\Support;

class PublicDocumentPolicy
{
    public const PROHIBITED_TERMS = ['aadhaar', 'aadhar', 'pan card', 'passport', 'voter id', 'driving licence', 'driving license', 'bank passbook', 'bank statement', 'cheque book', 'check book', 'atm card', 'credit card', 'debit card', 'income proof', 'salary slip', 'identity proof', 'address proof', 'kyc'];

    public static function isSafe(string $name): bool
    {
        $normalized = str($name)->lower()->replace(['-', '_'], ' ')->squish()->value();
        return collect(self::PROHIBITED_TERMS)->doesntContain(fn (string $term) => str_contains($normalized, $term));
    }
}
