<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Proof;

use InvalidArgumentException;

/**
 * A small computation the browser must do before it submits — find a number whose SHA-256, appended to the token's
 * nonce, starts with `difficulty` zero bits —, which costs a person a fraction of a second once and a robot that
 * cost on every submission. The server checks the answer with one hash.
 *
 * A difficulty of 0 turns the proof off. Each bit doubles the browser's work: 16 bits take about 65,000 hashes on
 * average.
 */
final readonly class ProofOfWork
{
    public const int MAXIMUM_DIFFICULTY = 24;

    /**
     * @throws InvalidArgumentException when the difficulty is outside 0 to 24
     */
    public function __construct(
        public int $difficulty = 12,
    ) {
        if ($difficulty < 0 || $difficulty > self::MAXIMUM_DIFFICULTY) {
            throw new InvalidArgumentException(\sprintf('A proof of work has 0 to %d bits of difficulty; %d given.', self::MAXIMUM_DIFFICULTY, $difficulty));
        }
    }

    public function isRequired(): bool
    {
        return $this->difficulty > 0;
    }

    /**
     * Whether `solution` — decimal digits — solves the challenge `nonce`.
     */
    public function verify(string $nonce, string $solution): bool
    {
        if (!$this->isRequired()) {
            return true;
        }

        if (1 !== preg_match('/^\d{1,12}$/', $solution)) {
            return false;
        }

        return $this->leadingZeroBits(hash('sha256', $nonce.':'.$solution, true)) >= $this->difficulty;
    }

    /**
     * The leading zero bits of the hash: eight for each zero byte, then those of the first byte that is not zero,
     * which its binary writing, without leading zeros, leaves out of eight. A byte with its top bit set closes the
     * hash, so that a hash of zeros only counts all its bits.
     */
    private function leadingZeroBits(string $hash): int
    {
        $zeroBytes = strspn($hash, "\0");

        return 8 * $zeroBytes + 8 - \strlen(decbin(\ord(($hash."\x80")[$zeroBytes])));
    }
}
