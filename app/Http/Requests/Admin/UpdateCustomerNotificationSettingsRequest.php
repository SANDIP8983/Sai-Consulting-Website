<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationMilestone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.manage') ?? false;
    }

    public function rules(): array
    {
        $rules = [];
        foreach (NotificationMilestone::cases() as $milestone) {
            foreach (['email', 'whatsapp'] as $channel) {
                $rules["milestones.{$milestone->value}.{$channel}"] = ['required', 'boolean'];
            }
        }

        return $rules;
    }
}
