<?php

namespace App\Services\Notifications;

use App\Models\CustomerRequest;
use App\Models\Appointment;

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
                default => $this->defaultLanguage(),
            };
        }

        return $this->defaultLanguage();
    }

    public function resolveForAppointment(Appointment $appointment): string
    {
        // Appointment records currently have no persisted language preference.
        // Use the non-secret configured default, while allowing a future attribute.
        $value = strtolower(trim((string) $appointment->getAttribute('preferred_language')));

        return match ($value) {
            'en', 'en_in', 'en-in', 'english' => 'en',
            'gu', 'gu_in', 'gu-in', 'gujarati' => 'gu',
            default => $this->defaultLanguage(),
        };
    }

    private function defaultLanguage(): string
    {
        return in_array(strtolower((string) config('msg91-whatsapp.default_language', 'gu')), ['en', 'en_in', 'en-in', 'english'], true)
            ? 'en'
            : 'gu';
    }
}
