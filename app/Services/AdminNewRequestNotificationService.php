<?php

namespace App\Services;

use App\Mail\AdminNewCustomerRequestMail;
use App\Models\CustomerRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminNewRequestNotificationService
{
    public function afterCommit(CustomerRequest $request): void
    {
        DB::afterCommit(function () use ($request): void {
            if (! $this->enabled()) {
                return;
            }

            $recipient = Setting::query()->where('setting_key', 'contact.email')->value('setting_value');
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            try {
                $mail = (new AdminNewCustomerRequestMail($request->fresh(['requestServices.service'])))
                    ->onQueue(config('customer-notifications.queue'));
                Mail::to($recipient)->queue($mail);
            } catch (\Throwable $exception) {
                Log::error('Admin new customer request notification could not be queued.', [
                    'request_id' => $request->id,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    private function enabled(): bool
    {
        $stored = Setting::query()->where('setting_key', 'notifications.admin_new_online_request.email')->value('setting_value');

        return $stored === null ? false : filter_var($stored, FILTER_VALIDATE_BOOL);
    }
}
