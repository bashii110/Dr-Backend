<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public string $otp;
    public string $type;

    public function __construct($user, string $otp, string $type = 'verification')
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        $subject = $this->type === 'password_reset'
            ? 'Reset Your Doctor App Password'
            : 'Verify Your Doctor App Account';

        return new Envelope(
            subject: $subject
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'otp',
        );
    }
}