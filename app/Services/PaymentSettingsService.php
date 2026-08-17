<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentSettingsService
{
    private const KEYS = [
        'enabled' => 'payments.upi.enabled',
        'upi_id' => 'payments.upi.id',
        'payee_name' => 'payments.upi.payee_name',
        'qr_path' => 'payments.upi.qr_path',
        'instructions' => 'payments.upi.instructions',
        'proof_upload_allowed' => 'payments.upi.proof_upload_allowed',
    ];

    /** @return array{enabled: bool, upi_id: string|null, payee_name: string|null, qr_path: string|null, instructions: string|null, proof_upload_allowed: bool} */
    public function settings(): array
    {
        $stored = Setting::query()->whereIn('setting_key', self::KEYS)->pluck('setting_value', 'setting_key');

        return [
            'enabled' => filter_var($stored->get(self::KEYS['enabled'], '0'), FILTER_VALIDATE_BOOL),
            'upi_id' => $stored->get(self::KEYS['upi_id']),
            'payee_name' => $stored->get(self::KEYS['payee_name']),
            'qr_path' => $stored->get(self::KEYS['qr_path']),
            'instructions' => $stored->get(self::KEYS['instructions']),
            'proof_upload_allowed' => filter_var($stored->get(self::KEYS['proof_upload_allowed'], '0'), FILTER_VALIDATE_BOOL),
        ];
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): void
    {
        $current = $this->settings();
        $qrPath = $current['qr_path'];
        $uploaded = $values['qr_code'] ?? null;

        if ($uploaded instanceof UploadedFile) {
            $extension = strtolower($uploaded->guessExtension() ?: $uploaded->extension());
            $qrPath = $uploaded->storeAs('settings/payments', Str::uuid().'.'.$extension, 'local');
        } elseif ((bool) ($values['remove_qr_code'] ?? false)) {
            $qrPath = null;
        }

        $stored = [
            'enabled' => (bool) ($values['enabled'] ?? false) ? '1' : '0',
            'upi_id' => $values['upi_id'] ?? null,
            'payee_name' => $values['payee_name'] ?? null,
            'qr_path' => $qrPath,
            'instructions' => $values['instructions'] ?? null,
            'proof_upload_allowed' => (bool) ($values['proof_upload_allowed'] ?? false) ? '1' : '0',
        ];

        DB::transaction(function () use ($stored): void {
            foreach ($stored as $field => $value) {
                Setting::query()->updateOrCreate(
                    ['setting_key' => self::KEYS[$field]],
                    ['setting_value' => $value, 'value_type' => in_array($field, ['enabled', 'proof_upload_allowed'], true) ? 'boolean' : 'string', 'setting_group' => 'payments', 'is_public' => false],
                );
            }
        });

        if ($current['qr_path'] && $current['qr_path'] !== $qrPath) {
            Storage::disk('local')->delete($current['qr_path']);
        }
    }

    public function qrPath(): ?string
    {
        $settings = $this->settings();

        return $settings['enabled'] && $settings['qr_path'] ? $settings['qr_path'] : null;
    }
}
