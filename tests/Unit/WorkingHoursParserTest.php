<?php

namespace Tests\Unit;

use App\Support\WorkingHoursParser;
use PHPUnit\Framework\TestCase;

class WorkingHoursParserTest extends TestCase
{
    public function test_hyphen_range(): void
    {
        $hours = WorkingHoursParser::parse(['I-V 08:00-20:00', 'VI 08:00-18:00', 'VII 08:00-17:00']);

        $this->assertSame('08:00-20:00', $hours['monday']);
        $this->assertSame('08:00-20:00', $hours['friday']);
        $this->assertSame('08:00-18:00', $hours['saturday']);
        $this->assertSame('08:00-17:00', $hours['sunday']);
    }

    // Senukai and Grustė write "I–V" with an en dash; those weekdays used
    // to be dropped, leaving only Saturday/Sunday.
    public function test_en_dash_range(): void
    {
        $hours = WorkingHoursParser::parse(['I–V 8:00–18:00', 'VI 8:00–14:00', 'VII Nedirba', 'Rugpjūčio 15 d. nedirbs']);

        $this->assertSame('8:00–18:00', $hours['monday']);
        $this->assertSame('8:00–18:00', $hours['wednesday']);
        $this->assertSame('8:00–18:00', $hours['friday']);
        $this->assertSame('8:00–14:00', $hours['saturday']);
        $this->assertSame('Nedirba', $hours['sunday']);
    }

    public function test_unmentioned_days_are_null(): void
    {
        $hours = WorkingHoursParser::parse(['VII 09:00-17:00']);

        $this->assertNull($hours['monday']);
        $this->assertSame('09:00-17:00', $hours['sunday']);
    }
}
