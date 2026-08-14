<?php

namespace App\Jobs;

use App\Contracts\WhatsAppChannelInterface;
use App\Enums\AppointmentNotificationMilestone;
use App\Mail\AppointmentMail;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentNotificationJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 4;

    public array $backoff = [60, 300, 1200, 3600];

    public function __construct(public int $appointmentId, public AppointmentNotificationMilestone $milestone, public string $channel) {}

    public function handle(WhatsAppChannelInterface $whatsApp): void
    {
        $a = Appointment::with('service')->findOrFail($this->appointmentId);
        $data = ['subject' => 'Sai Consulting — '.$this->milestone->label().' — '.$a->reference_no, 'appointment' => $a, 'milestone' => $this->milestone];
        if ($this->channel === 'email' && filter_var($a->email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($a->email)->send(new AppointmentMail($data));
        }
        // WhatsApp remains provider-controlled; DisabledWhatsAppChannel safely skips it.
        if ($this->channel === 'whatsapp') {
            return;
        }
    }
}
