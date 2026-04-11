<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewAppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Appointment Booking - DoctorApp')
            ->greeting('Hello Dr. ' . $notifiable->name . '!')
            ->line('You have a new appointment booking.')
            ->line('Patient: ' . ($this->appointment->patient->name ?? 'Unknown'))
            ->line('Date: ' . $this->appointment->appointment_date)
            ->line('Time: ' . date('g:i A', strtotime($this->appointment->appointment_time)))
            ->action('View Appointment', url('/'))
            ->line('Please confirm or manage this appointment.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'new_appointment',
            'appointment_id'   => $this->appointment->id,
            'patient_name'     => $this->appointment->patient->name ?? '',
            'appointment_date' => $this->appointment->appointment_date,
            'appointment_time' => $this->appointment->appointment_time,
        ];
    }
}