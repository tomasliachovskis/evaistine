<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $loginUrl, public ?string $code = null)
    {
    }

    public function build(): self
    {
        return $this->subject($this->code ? "Prisijungimo kodas {$this->code} - eVaistinė" : 'Prisijungimo nuoroda - eVaistinė')
            ->view('emails.magic-link');
    }
}
