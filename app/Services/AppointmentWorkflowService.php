<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationMilestone;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Notifications\AppointmentNotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWorkflowService
{
    public function __construct(private AppointmentAvailabilityService $availability, private AppointmentNotificationService $notifications) {}

    public function create(array $data, string $source = 'online', ?User $actor = null): Appointment
    {
        return Cache::lock('appointments:booking', 10)->block(5, function () use ($data, $source, $actor) {
            return DB::transaction(function () use ($data, $source, $actor) {
                $at = $this->availability->scheduledAt($data['appointment_date'], $data['appointment_time']);
                $year = $at->year;
                $last = Appointment::query()->where('reference_no', 'like', "APT/$year/%")->lockForUpdate()->orderByDesc('reference_no')->value('reference_no');
                $seq = $last ? ((int) substr($last, -6)) + 1 : 1;
                try {
                    $a = Appointment::query()->create(['reference_no' => sprintf('APT/%d/%06d', $year, $seq), 'customer_name' => $data['customer_name'], 'mobile' => $data['mobile'], 'whatsapp' => $data['whatsapp'] ?? null, 'email' => $data['email'] ?? null, 'service_id' => $data['service_id'], 'scheduled_at' => $at, 'status' => AppointmentStatus::Pending, 'source' => $source, 'customer_note' => $data['customer_note'] ?? null, 'admin_note' => $data['admin_note'] ?? null, 'slot_key' => $at->format('Y-m-d H:i')]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['appointment_time' => 'This slot has just been booked. Please choose another.']);
                }
                $a->histories()->create(['action' => 'created', 'new_status' => 'pending', 'new_scheduled_at' => $at, 'user_id' => $actor?->id]);
                $this->notifications->afterCommit($a, NotificationMilestone::AppointmentReceived);

                return $a;
            });
        });
    }

    public function transition(Appointment $appointment, AppointmentStatus $status, ?User $actor = null, ?string $note = null, ?array $slot = null): void
    {
        DB::transaction(function () use ($appointment, $status, $actor, $note, $slot) {
            $a = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $oldStatus = $a->status;
            $oldAt = $a->scheduled_at;
            $newAt = $oldAt;
            if ($status === AppointmentStatus::Rescheduled) {
                $newAt = $this->availability->scheduledAt($slot['appointment_date'], $slot['appointment_time'], $a->id);
            }
            $active = in_array($status->value, AppointmentStatus::active(), true);
            $a->update(['status' => $status, 'scheduled_at' => $newAt, 'slot_key' => $active ? $newAt->format('Y-m-d H:i') : null, 'admin_note' => $note ?: $a->admin_note, 'confirmed_at' => $status === AppointmentStatus::Confirmed ? now() : $a->confirmed_at, 'completed_at' => $status === AppointmentStatus::Completed ? now() : $a->completed_at, 'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : $a->cancelled_at, 'reminder_sent_at' => $status === AppointmentStatus::Rescheduled ? null : $a->reminder_sent_at]);
            $a->histories()->create(['action' => $status->value, 'old_status' => $oldStatus->value, 'new_status' => $status->value, 'old_scheduled_at' => $oldAt, 'new_scheduled_at' => $newAt, 'note' => $note, 'user_id' => $actor?->id]);
            $milestone = match ($status) {
                AppointmentStatus::Confirmed => NotificationMilestone::AppointmentConfirmed,AppointmentStatus::Rescheduled => NotificationMilestone::AppointmentRescheduled,AppointmentStatus::Cancelled => NotificationMilestone::AppointmentCancelled,default => null
            };
            if ($milestone) {
                $this->notifications->afterCommit($a, $milestone);
            }
        });
    }
}
