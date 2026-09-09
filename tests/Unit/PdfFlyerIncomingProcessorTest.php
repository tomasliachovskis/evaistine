<?php

namespace Tests\Unit;

use App\Services\PdfFlyerIncomingProcessor;
use PHPUnit\Framework\TestCase;

class PdfFlyerIncomingProcessorTest extends TestCase
{
    // matchStoreSlugFromFilename() is the pure part of
    // extractStoreSlugFromPdfFilename() — takes a slug list as a plain
    // argument instead of querying Store::query() itself, so this stays a
    // real unit test (no DB connection) while still exercising the real
    // matching logic, DB lookup included via the fixture list below.
    private const KNOWN_SLUGS = ['aibe', 'maxima', 'thomas-philipps', 'gintarine-vaistine'];

    /**
     * @dataProvider slugProvider
     */
    public function test_extract_store_slug_from_pdf_filename(string $filename, string $expected): void
    {
        $this->assertSame(
            $expected,
            PdfFlyerIncomingProcessor::matchStoreSlugFromFilename($filename, self::KNOWN_SLUGS)
        );
    }

    public static function slugProvider(): array
    {
        return [
            ['aibe-1.pdf', 'aibe'],
            ['maxima-2.pdf', 'maxima'],
            // Multi-word slugs must match in full against the known-slugs
            // list, not just up to the first hyphen or a trailing "-digits"
            // — this is exactly the bug the DB-backed matching replaced a
            // fragile hyphen-position regex to fix.
            ['thomas-philipps-nr-5.pdf', 'thomas-philipps'],
            ['gintarine-vaistine-leidinys.pdf', 'gintarine-vaistine'],
            // Falls back to regex guessing for a store with no fixture slug.
            ['store-name-123.pdf', 'store-name'],
            ['simple.pdf', 'simple'],
        ];
    }
}
