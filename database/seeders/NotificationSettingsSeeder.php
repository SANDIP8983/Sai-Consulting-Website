<?php

namespace Database\Seeders;

use App\Enums\NotificationMilestone;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class NotificationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(
            ['setting_key' => 'notifications.admin_new_online_request.email'],
            ['setting_value' => '0', 'value_type' => 'boolean', 'setting_group' => 'admin_notifications', 'is_public' => false],
        );

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
