<?php

namespace Database\Seeders;

use App\Enums\NotificationMilestone;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class NotificationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NotificationMilestone::cases() as $milestone) {
            foreach (['email', 'whatsapp'] as $channel) {
                Setting::query()->firstOrCreate(
                    ['setting_key' => "notifications.{$milestone->value}.{$channel}"],
                    [
                        'setting_value' => '0',
                        'value_type' => 'boolean',
                        'setting_group' => 'customer_notifications',
                        'is_public' => false,
                    ],
                );
            }
        }
    }
}
