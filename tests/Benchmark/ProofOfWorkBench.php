<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Tests\Benchmark;

use Generator;
use Phil\SpamProtection\Proof\ProofOfWork;

/**
 * The server checks a proof of work on every protected submission, so its verifier must cost one hash whatever the
 * difficulty. The browser side, the Stimulus solver, cannot run under phpbench: a browser solve takes about
 * 2^difficulty hashes on average (4,096 at 12, 65,536 at 16, about a million at 20).
 *
 * The solutions below were found beforehand for the nonce, so no solving happens here.
 *
 * @BeforeMethods("setUp")
 */
final class ProofOfWorkBench
{
    private const string NONCE = '4f1c9a0e7b2d6e83a5c0f9172b4d8e6a';

    private ProofOfWork $proof;

    public function __construct()
    {
        $this->proof = new ProofOfWork();
    }

    /**
     * @param array{difficulty: int, solution: string} $params
     */
    public function setUp(array $params): void
    {
        $this->proof = new ProofOfWork($params['difficulty']);
    }

    /**
     * @ParamProviders("solutions")
     *
     * @Revs(1000)
     *
     * @param array{difficulty: int, solution: string} $params
     */
    public function benchVerify(array $params): void
    {
        $this->proof->verify(self::NONCE, $params['solution']);
    }

    /**
     * @return Generator<string, array{difficulty: int, solution: string}>
     */
    public function solutions(): Generator
    {
        yield '12 bits, solved' => ['difficulty' => 12, 'solution' => '10959'];
        yield '16 bits, solved' => ['difficulty' => 16, 'solution' => '59309'];
        yield '20 bits, solved' => ['difficulty' => 20, 'solution' => '120647'];
        yield '20 bits, wrong' => ['difficulty' => 20, 'solution' => '1'];
        yield '20 bits, not digits' => ['difficulty' => 20, 'solution' => 'abc'];
    }
}
