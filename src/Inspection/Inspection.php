<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Inspection;

/**
 * What the inspection of one submission found: the signals it raised, each once, in the order they were found.
 *
 * A submission without signal is clean; one signal makes it spam. These signals are the server's own observations:
 * the application may refuse the submission on them.
 */
final readonly class Inspection
{
    /**
     * @param list<Signal> $signals
     */
    private function __construct(
        public array $signals,
    ) {
    }

    public static function clean(): self
    {
        return new self([]);
    }

    /**
     * The same inspection with these signals raised too.
     */
    public function with(Signal ...$signals): self
    {
        return new self(array_values(array_unique([...$this->signals, ...$signals], \SORT_REGULAR)));
    }

    public function isSpam(): bool
    {
        return [] !== $this->signals;
    }

    public function has(Signal $signal): bool
    {
        return \in_array($signal, $this->signals, true);
    }
}
