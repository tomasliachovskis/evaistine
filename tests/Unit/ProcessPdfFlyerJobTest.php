<?php

namespace Tests\Unit;

use App\Jobs\ProcessPdfFlyerJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PHPUnit\Framework\TestCase;

class ProcessPdfFlyerJobTest extends TestCase
{
    public function test_job_is_configured_for_flyers_queue_with_mutex(): void
    {
        $job = new ProcessPdfFlyerJob();

        $this->assertSame('flyers', $job->queue);
        $this->assertSame(0, $job->timeout);
        $this->assertSame(1, $job->tries);
        $this->assertSame(7200, $job->uniqueFor);
        $this->assertSame('pdf-flyer', $job->uniqueId());

        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }
}
