<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $loginUrl)
    {
    }

    public function build(): self
    {
        return $this->subject('Prisijungimo nuoroda - SuperAkcijos')
            ->view('emails.magic-link');
    }
}
