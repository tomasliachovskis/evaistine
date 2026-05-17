<?php

namespace Tests\Feature;

use App\Jobs\ProcessPdfFlyerJob;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessPdfFlyerCommandTest extends TestCase
{
    public function test_command_dispatches_job_by_default(): void
    {
        Bus::fake();

        $this->artisan('flyers:process-pdf')
            ->assertExitCode(0)
            ->expectsOutputToContain('queued');

        Bus::assertDispatched(ProcessPdfFlyerJob::class);
    }
}
