<?php

namespace App\Jobs;

use App\Services\PdfFlyerIncomingProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessPdfFlyerJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $tries = 1;

    public $uniqueFor = 7200;

    public function __construct()
    {
        $this->onQueue('flyers');
    }

    public function uniqueId(): string
    {
        return 'pdf-flyer';
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('pdf-flyer'))->releaseAfter(120)->expireAfter(7200),
        ];
    }

    public function handle(PdfFlyerIncomingProcessor $processor): void
    {
        $processor->processIncomingDirectory();
    }
}
