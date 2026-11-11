<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Content;

use InvalidArgumentException;
use Phil\SpamProtection\Inspection\Signal;
use Symfony\Component\String\CodePointString;
use Symfony\Component\String\UnicodeString;

/**
 * What the text a person writes into a form does not hold: bytes that are not UTF-8, more than `maximumLength` characters, more than
 * `maximumLinks` links, a term the application forbids, or a run of random characters.
 *
 * - **Encoding**: a text that is not valid UTF-8, which no browser sends, raises `Malformed`; it is still read, each
 *   bad sequence replaced as `mb_scrub()` replaces it, so that the other signals are raised too.
 * - **Length** bounds the work: a text longer than `maximumLength` characters raises `TooLong`, and only its first
 *   `maximumLength` characters are read for terms and gibberish, so a huge submission costs no more than a long one.
 * - **Links** are the `http://`, `https://` and `www.` addresses of the text, each counted once: `https://www.a.example`
 *   is one link.
 * - **Terms** are matched as whole words, or whole sequences of words, without regard to case, accents or the
 *   punctuation between words: `casino` matches `Casino` and `CASINÓ`, not `casinos`; `free money` matches
 *   `Free, money!`, not `free moneys`.
 * - **Gibberish** is a word of at least 12 letters with no vowel, or a text of at least 20 letters whose letters are
 *   fewer than one vowel in eight: what a keyboard mashed by a script looks like, and what no language writes.
 */
final readonly class ContentRules
{
    /** The default bound of a text, in characters: a long message, far from what slows the inspection down. */
    public const int DEFAULT_MAXIMUM_LENGTH = 20_000;

    /** @var list<string> each term as its words, lower-cased, without accents, separated by one space */
    public array $forbiddenTerms;

    /**
     * @param list<string> $forbiddenTerms
     *
     * @throws InvalidArgumentException when a maximum is negative (or the length zero), or a term holds no word
     */
    public function __construct(
        public ?int $maximumLinks = 3,
        array $forbiddenTerms = [],
        public bool $detectGibberish = true,
        public ?int $maximumLength = self::DEFAULT_MAXIMUM_LENGTH,
    ) {
        if (null !== $maximumLinks && $maximumLinks < 0) {
            throw new InvalidArgumentException(\sprintf('A text holds 0 links or more; %d given.', $maximumLinks));
        }

        if (null !== $maximumLength && $maximumLength < 1) {
            throw new InvalidArgumentException(\sprintf('A text may hold 1 character or more; %d given.', $maximumLength));
        }

        $terms = [];

        foreach ($forbiddenTerms as $term) {
            $words = $this->words($term);

            if ([] === $words) {
                throw new InvalidArgumentException('A forbidden term is not empty.');
            }

            $terms[] = implode(' ', $words);
        }

        $this->forbiddenTerms = $terms;
    }

    /**
     * The signals the text raises, each once.
     *
     * @return list<Signal>
     */
    public function inspect(string $text): array
    {
        $signals = [];

        // A browser always sends UTF-8, and the string functions below throw on anything else: a text that is not raises
        // `Malformed` and is read with each bad sequence replaced as `mb_scrub()` replaces it.
        if (!mb_check_encoding($text, 'UTF-8')) {
            $signals[] = Signal::Malformed;
            $text = mb_scrub($text, 'UTF-8');
        }

        // A text of no more bytes than the bound holds no more characters: only one with a byte past the bound is
        // counted.
        if (null !== $this->maximumLength && isset($text[$this->maximumLength])) {
            $characters = new CodePointString($text);

            if ($characters->length() > $this->maximumLength) {
                $signals[] = Signal::TooLong;
                $text = $characters->slice(0, $this->maximumLength)->toString();
            }
        }

        // An address starts where no letter, digit, dot or slash precedes it: the `www.` of `https://www.` is not
        // counted a second time, and addresses glued together by a comma or a quote are each counted.
        if (null !== $this->maximumLinks && preg_match_all('~(?<![\p{L}\p{N}_./])(?:https?://|www\.)~iu', $text) > $this->maximumLinks) {
            $signals[] = Signal::TooManyLinks;
        }

        $words = $this->words($text);
        $spaced = ' '.implode(' ', $words).' ';

        if (array_any($this->forbiddenTerms, static fn (string $term): bool => str_contains($spaced, ' '.$term.' '))) {
            $signals[] = Signal::ForbiddenTerm;
        }

        if ($this->detectGibberish && $this->isGibberish($words)) {
            $signals[] = Signal::Gibberish;
        }

        return $signals;
    }

    /**
     * @param list<string> $words lower-cased, without accents
     */
    private function isGibberish(array $words): bool
    {
        $counts = count_chars(implode('', $words));
        $letters = array_sum(array_intersect_key($counts, array_flip(range(\ord('a'), \ord('z')))));
        $vowels = array_sum(array_intersect_key($counts, array_flip(array_map(\ord(...), ['a', 'e', 'i', 'o', 'u', 'y']))));

        if ($letters >= 20 && $vowels * 8 < $letters) {
            return true;
        }

        return array_any($words, static fn (string $word): bool => 1 === preg_match('/^[b-df-hj-np-tv-xz]{12,}$/', $word));
    }

    /**
     * The words of a text, lower case, without accents: what the terms and the text are compared as. Anything that is
     * neither a letter nor a digit separates two words.
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', new UnicodeString($text)->ascii()->lower()->toString(), -1, \PREG_SPLIT_NO_EMPTY);

        return false === $words ? [] : $words;
    }
}
