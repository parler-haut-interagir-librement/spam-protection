<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Tests\Unit;

use Phil\SpamProtection\Inspection\Inspection;
use Phil\SpamProtection\Inspection\Signal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inspection::class)]
final class InspectionTest extends TestCase
{
    public function testACleanInspectionIsNotSpam(): void
    {
        $inspection = Inspection::clean();

        self::assertFalse($inspection->isSpam());
        self::assertSame([], $inspection->signals);
    }

    public function testASignalMakesItSpam(): void
    {
        $inspection = Inspection::clean()->with(Signal::TooFast, Signal::HoneypotFilled, Signal::TooFast)->with(Signal::Gibberish, Signal::HoneypotFilled);

        self::assertTrue($inspection->isSpam());
        self::assertSame([Signal::TooFast, Signal::HoneypotFilled, Signal::Gibberish], $inspection->signals);
        self::assertTrue($inspection->has(Signal::Gibberish));
        self::assertFalse($inspection->has(Signal::RateLimited));
        self::assertFalse(Inspection::clean()->isSpam());
        self::assertSame([Signal::TooFast, Signal::Gibberish], Inspection::clean()->with(Signal::TooFast, Signal::TooFast, Signal::Gibberish)->signals);
    }
}
