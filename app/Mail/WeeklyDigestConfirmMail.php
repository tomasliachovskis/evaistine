<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Double opt-in for the Thursday email: nothing is sent until this link is
// clicked, so nobody can sign up someone else's address.
class WeeklyDigestConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $confirmUrl)
    {
    }

    public function build(): self
    {
        return $this->subject('Patvirtinkite savaitės akcijų laišką - SuperAkcijos')
            ->view('emails.weekly-digest-confirm');
    }
}
