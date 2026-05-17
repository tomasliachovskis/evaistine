<?php

namespace Tests\Unit;

use App\Services\PdfFlyerIncomingProcessor;
use App\Services\PdfFlyerProcessingService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PdfFlyerIncomingProcessorTest extends TestCase
{
    /**
     * @dataProvider slugProvider
     */
    public function test_extract_store_slug_from_pdf_filename(string $filename, string $expected): void
    {
        $processor = new PdfFlyerIncomingProcessor(
            $this->createMock(PdfFlyerProcessingService::class)
        );

        $method = new ReflectionMethod(PdfFlyerIncomingProcessor::class, 'extractStoreSlugFromPdfFilename');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($processor, $filename));
    }

    public static function slugProvider(): array
    {
        return [
            ['aibe-1.pdf', 'aibe'],
            ['maxima-2.pdf', 'maxima'],
            ['store-name-123.pdf', 'store-name'],
            ['simple.pdf', 'simple'],
        ];
    }
}
