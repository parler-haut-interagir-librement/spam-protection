<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Tests\Unit;

use InvalidArgumentException;
use Phil\SpamProtection\Content\ContentRules;
use Phil\SpamProtection\Inspection\Signal;
use Phil\SpamProtection\Proof\ProofOfWork;
use Phil\SpamProtection\Timing\TimingPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(TimingPolicy::class)]
#[CoversClass(ProofOfWork::class)]
#[CoversClass(ContentRules::class)]
final class PoliciesTest extends TestCase
{
    public function testTimingHasBoundsBothIncluded(): void
    {
        $timing = new TimingPolicy(3, 60);

        self::assertSame(Signal::TooFast, $timing->check(100, 102));
        self::assertNull($timing->check(100, 103));
        self::assertNull($timing->check(100, 160));
        self::assertSame(Signal::TokenExpired, $timing->check(100, 161));
        self::assertSame(3, new TimingPolicy()->minimum);
        self::assertSame(3600, new TimingPolicy()->maximum);
        self::assertNull(new TimingPolicy(0, 1)->check(5, 5));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function badTimings(): iterable
    {
        yield 'negative minimum' => [-1, 10];
        yield 'equal bounds' => [10, 10];
        yield 'maximum below minimum' => [10, 9];
    }

    #[DataProvider('badTimings')]
    public function testBadTimingsAreRefused(int $minimum, int $maximum): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('%d and %d given.', $minimum, $maximum));

        new TimingPolicy($minimum, $maximum);
    }

    public function testAProofIsCheckedWithOneHash(): void
    {
        $proof = new ProofOfWork(8);
        $solution = $this->solve('abc', 8);

        self::assertTrue($proof->isRequired());
        self::assertTrue($proof->verify('abc', $solution));
        self::assertFalse($proof->verify('abd', $solution) && $proof->verify('abe', $solution) && $proof->verify('abf', $solution));
        self::assertFalse($proof->verify('abc', ''));
        self::assertFalse($proof->verify('abc', '-1'));
        self::assertFalse($proof->verify('abc', '1234567890123'));
        self::assertFalse($proof->verify('abc', $solution.'x'));
        self::assertSame(12, new ProofOfWork()->difficulty);
    }

    public function testTheProofCountsLeadingZeroBitsExactly(): void
    {
        foreach ([1, 4, 9, 12] as $difficulty) {
            $solution = $this->solve('nonce', $difficulty);

            self::assertTrue(new ProofOfWork($difficulty)->verify('nonce', $solution));

            $bits = $this->zeroBits(hash('sha256', 'nonce:'.$solution, true));
            self::assertFalse(new ProofOfWork(min($bits + 1, 24))->verify('nonce', $solution));
        }

        self::assertSame(256, $this->zeroBits(str_repeat("\0", 32)));
    }

    /**
     * A hash no solution reaches, read through the private count: zero bytes only, or a first byte at each width.
     */
    public function testTheCountReadsEveryBitOfAHashOfZeros(): void
    {
        $count = new ReflectionMethod(ProofOfWork::class, 'leadingZeroBits');
        $proof = new ProofOfWork();

        self::assertSame(256, $count->invoke($proof, str_repeat("\0", 32)));
        self::assertSame(255, $count->invoke($proof, str_repeat("\0", 31)."\x01"));
        self::assertSame(0, $count->invoke($proof, "\x80".str_repeat("\0", 31)));
        self::assertSame(15, $count->invoke($proof, "\0\x01\xFF"));
    }

    public function testASolutionIsDigitsOnly(): void
    {
        $proof = new ProofOfWork(1);

        // Each spelling solves the challenge on its hash alone; only its shape refuses it.
        foreach (['x', ' ', '+'] as $junk) {
            $suffixed = $this->solveAs('nonce', 1, static fn (int $n): string => $n.$junk);
            $prefixed = $this->solveAs('nonce', 1, static fn (int $n): string => $junk.$n);

            self::assertFalse($proof->verify('nonce', $suffixed), $suffixed);
            self::assertFalse($proof->verify('nonce', $prefixed), $prefixed);
        }
    }

    public function testAProofOfZeroBitsIsOff(): void
    {
        $proof = new ProofOfWork(0);

        self::assertFalse($proof->isRequired());
        self::assertTrue($proof->verify('anything', ''));
        self::assertTrue(new ProofOfWork(24)->isRequired());
    }

    public function testADifficultyOutsideTheRangeIsRefused(): void
    {
        try {
            new ProofOfWork(25);
            self::fail('25 bits were accepted.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('A proof of work has 0 to 24 bits of difficulty; 25 given.', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);

        new ProofOfWork(-1);
    }

    public function testLinksAreCountedAgainstTheMaximum(): void
    {
        $rules = new ContentRules(2, [], false);

        self::assertSame([], $rules->inspect('See https://a.example and www.b.example.'));
        self::assertSame([Signal::TooManyLinks], $rules->inspect('https://a.example http://b.example WWW.c.example'));
        self::assertSame([], new ContentRules(null, [], false)->inspect(str_repeat('https://a.example ', 50)));
        self::assertSame([Signal::TooManyLinks], new ContentRules(0, [], false)->inspect('www.a.example'));
        self::assertSame(3, new ContentRules()->maximumLinks);
    }

    /**
     * `https://www.` is one address, not one at `https://` and another at `www.`.
     */
    public function testEachLinkIsCountedOnce(): void
    {
        $rules = new ContentRules(2, [], false);

        self::assertSame([], $rules->inspect('see https://www.example.com and https://www.example.org'));
        self::assertSame([], $rules->inspect('http://WWW.a.example/www.b'));
        self::assertSame([Signal::TooManyLinks], $rules->inspect('https://www.a.example www.b.example http://c.example'));
        self::assertSame([Signal::TooManyLinks], $rules->inspect('https://a.example,https://b.example,"www.c.example"'), 'addresses glued together are each counted');
        self::assertSame([], $rules->inspect('xhttps://a.example mywww.b.example https://c.example'));
    }

    public function testATextLongerThanTheBoundIsTooLongAndReadOnlyUpToIt(): void
    {
        $rules = new ContentRules(null, ['casino'], false, maximumLength: 10);

        self::assertSame([], $rules->inspect('éééééééééé'), 'ten characters, twenty bytes');
        self::assertSame([Signal::TooLong], $rules->inspect('ééééééééééé'));
        self::assertSame([Signal::TooLong, Signal::ForbiddenTerm], $rules->inspect('casino is a long word'));
        self::assertSame([Signal::TooLong], $rules->inspect('long text, casino'), 'what lies past the bound is not read');
        self::assertSame([], new ContentRules(null, [], false, null)->inspect(str_repeat('a ', 50_000)));
        self::assertSame(ContentRules::DEFAULT_MAXIMUM_LENGTH, new ContentRules()->maximumLength);
        self::assertSame([Signal::TooLong], new ContentRules(null, [], false)->inspect(str_repeat('a ', 10_001)));
        self::assertSame([], new ContentRules(null, [], false, 1)->inspect('é'), 'a bound of one character is allowed');
        self::assertSame([Signal::TooLong], new ContentRules(null, [], false, 1)->inspect('ab'));
    }

    public function testForbiddenTermsAreWholeWordsWithoutCaseOrAccents(): void
    {
        $rules = new ContentRules(null, ['  Casino ', 'crédit rapide', 'free-money'], false);

        self::assertSame(['casino', 'credit rapide', 'free money'], $rules->forbiddenTerms);
        self::assertSame([Signal::ForbiddenTerm], $rules->inspect('Best CASINÓ online'));
        self::assertSame([], $rules->inspect('Two casinos nearby'));
        self::assertSame([], new ContentRules()->inspect('casino'));
    }

    /**
     * A term of several words matches the same words in a row, whatever separates them, and nothing else.
     */
    public function testATermOfSeveralWordsMatchesThemInARow(): void
    {
        $rules = new ContentRules(null, ['crédit rapide', 'free money', 'e-mail'], false);

        self::assertSame([Signal::ForbiddenTerm], $rules->inspect('Un CRÉDIT   rapide !'));
        self::assertSame([Signal::ForbiddenTerm], $rules->inspect('get free money now'));
        self::assertSame([Signal::ForbiddenTerm], $rules->inspect('Free, money!'));
        self::assertSame([Signal::ForbiddenTerm], $rules->inspect('send an e mail'));
        self::assertSame([], $rules->inspect('free moneys'));
        self::assertSame([], $rules->inspect('money free'));
        self::assertSame([], $rules->inspect('carefree money'));
    }

    public function testGibberishIsAVowellessWordOrAVowelPoorText(): void
    {
        $rules = new ContentRules();

        self::assertSame([Signal::Gibberish], $rules->inspect('hello qwrtzpsdfghj there'));
        self::assertSame([], $rules->inspect('hello qwrtzpsdfgh there'));
        self::assertSame([Signal::Gibberish], $rules->inspect('xkcd bvnm qwrt plmk zxcv jhgf'));
        self::assertSame([], $rules->inspect('xkcd bvnm qwrt plmk zxcv aei'));
        self::assertSame([Signal::Gibberish], $rules->inspect('xkcd bvnm qwrt plmk zxcv ae'));
        self::assertSame([], $rules->inspect('Strengths: rhythm, nymphs and crypts are real words.'));
        // 20 letters, 2 of them vowels: at the length bound, under one vowel in eight.
        self::assertSame([Signal::Gibberish], $rules->inspect('bcdf ghjk lmnp qrst vw ae'));
        // 19 letters, 0 vowels: too short to judge by the ratio.
        self::assertSame([], $rules->inspect('bcdf ghjk lmnp qrst vwx'));
        // 25 letters, 3 vowels: 24 is still under 25.
        self::assertSame([Signal::Gibberish], $rules->inspect('bcdf ghjk lmnp qrst vwxz bc aei'));
        // 24 letters, 3 vowels: exactly one vowel in eight is enough.
        self::assertSame([], $rules->inspect('bcdf ghjk lmnp qrst vwxz b aei'));
        // Digits are not letters: 16 letters and 5 digits stay under the length bound.
        self::assertSame([], $rules->inspect('bcdf ghjk lmnp qr 12345 ae'));
        // A vowel before or after twelve consonants makes a word.
        self::assertSame([], $rules->inspect('abcdfghjklmnp'));
        self::assertSame([], $rules->inspect('bcdfghjklmnpa'));
        self::assertSame([], new ContentRules(null, [], false)->inspect('hello qwrtzpsdfghj there'));
        self::assertTrue(new ContentRules()->detectGibberish);
    }

    /**
     * A browser always sends UTF-8: bytes that are not raise `Malformed`, and the rest of the text is still read,
     * each bad sequence read as one replacement character.
     */
    public function testATextThatIsNotUtf8IsMalformedAndStillRead(): void
    {
        $rules = new ContentRules(0, ['casino'], maximumLength: 10);

        self::assertSame([Signal::Malformed], $rules->inspect("hello \xff"));
        self::assertSame([Signal::Malformed], $rules->inspect("\xc3"), 'a sequence cut short');
        self::assertSame([Signal::Malformed, Signal::ForbiddenTerm], $rules->inspect("casino\xff"));
        self::assertSame([Signal::Malformed, Signal::TooLong, Signal::TooManyLinks], $rules->inspect("www.a\xff\xfe\xfd\xfc\xfb\xfa"), 'each bad byte counts as one character');
        self::assertSame([Signal::Malformed], $rules->inspect("\xff\xfe\xfd\xfc\xfb\xfa\xf9\xf8\xf7\xf6"), 'ten bad bytes are ten characters');
        self::assertSame([Signal::Malformed, Signal::Gibberish], new ContentRules()->inspect("hello qwrtzpsdfghj\xff there"));
        self::assertSame([], $rules->inspect('héllo'));
    }

    public function testAllSignalsOfATextAreRaised(): void
    {
        self::assertSame(
            [Signal::TooManyLinks, Signal::ForbiddenTerm, Signal::Gibberish],
            new ContentRules(0, ['viagra'])->inspect('viagra www.a.example qwrtzpsdfghjk'),
        );
    }

    public function testBadContentRulesAreRefused(): void
    {
        try {
            new ContentRules(-1);
            self::fail('A negative maximum was accepted.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('A text holds 0 links or more; -1 given.', $invalidArgumentException->getMessage());
        }

        try {
            new ContentRules(3, [], true, 0);
            self::fail('A zero length was accepted.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('A text may hold 1 character or more; 0 given.', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A forbidden term is not empty.');

        new ContentRules(3, ['spam', ' - ']);
    }

    /**
     * @param callable(int): string $spell
     */
    private function solveAs(string $nonce, int $difficulty, callable $spell): string
    {
        for ($candidate = 0;; ++$candidate) {
            if ($this->zeroBits(hash('sha256', $nonce.':'.$spell($candidate), true)) >= $difficulty) {
                return $spell($candidate);
            }
        }
    }

    private function solve(string $nonce, int $difficulty): string
    {
        for ($candidate = 0;; ++$candidate) {
            if ($this->zeroBits(hash('sha256', $nonce.':'.$candidate, true)) >= $difficulty) {
                return (string) $candidate;
            }
        }
    }

    /**
     * The leading zero bits of a hash, counted on its binary writing: a second reading of what the proof counts.
     */
    private function zeroBits(string $hash): int
    {
        $binary = implode('', array_map(static fn (string $byte): string => \sprintf('%08b', \ord($byte[0])), str_split($hash)));

        return \strlen($binary) - \strlen(ltrim($binary, '0'));
    }
}
