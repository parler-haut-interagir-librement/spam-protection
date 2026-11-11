<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Timing;

use InvalidArgumentException;
use Phil\SpamProtection\Inspection\Signal;

/**
 * How long a person takes to fill a form: at least `minimum` seconds — a robot posts at once —, at most `maximum`
 * — a token that old was collected and kept for later.
 */
final readonly class TimingPolicy
{
    /**
     * @throws InvalidArgumentException when a bound is negative, or the minimum is not below the maximum
     */
    public function __construct(
        public int $minimum = 3,
        public int $maximum = 3600,
    ) {
        if ($minimum < 0 || $maximum <= $minimum) {
            throw new InvalidArgumentException(\sprintf('A form is filled in 0 seconds or more, and the minimum is below the maximum; %d and %d given.', $minimum, $maximum));
        }
    }

    /**
     * The signal a submission raises when it comes back after this many seconds, or `null` within the bounds.
     */
    public function check(int $issuedAt, int $now): ?Signal
    {
        $elapsed = $now - $issuedAt;

        return match (true) {
            $elapsed < $this->minimum => Signal::TooFast,
            $elapsed > $this->maximum => Signal::TokenExpired,
            default => null,
        };
    }
}
