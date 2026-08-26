<?php

namespace App\Services\Notifications;

use App\Models\CustomerRequest;

final class WhatsAppLanguageResolver
{
    public function resolve(CustomerRequest $request): string
    {
        foreach (['preferred_language', 'language', 'locale'] as $attribute) {
            if (! array_key_exists($attribute, $request->getAttributes())) {
                continue;
            }

            $value = strtolower(trim((string) $request->getAttribute($attribute)));

            return match ($value) {
                'en', 'en_in', 'en-in', 'english' => 'en',
                'gu', 'gu_in', 'gu-in', 'gujarati' => 'gu',
                default => 'gu',
            };
        }

        return 'gu';
    }
}
