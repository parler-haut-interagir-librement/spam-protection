<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Tests\Benchmark;

use Generator;
use Phil\SpamProtection\Content\ContentRules;

/**
 * Every protected form submission runs its free text through the content rules: an ASCII transliteration, a word
 * split and a letter count over text the visitor chose, up to the length bound.
 *
 * @BeforeMethods("setUp")
 */
final class ContentRulesBench
{
    private const string PARAGRAPH = 'Bonjour, je voudrais réserver une table pour huit personnes le vendredi soir. Pouvez-vous me confirmer la disponibilité et le menu ? Voir https://www.example.com/menu pour les détails. Merci beaucoup ! ';

    private readonly ContentRules $rules;

    private string $text = '';

    public function __construct()
    {
        $terms = [];

        for ($i = 0; $i < 50; ++$i) {
            $terms[] = 'forbidden term '.$i;
        }

        $this->rules = new ContentRules(forbiddenTerms: $terms);
    }

    /**
     * @param array{characters: int, gibberish: bool} $params
     */
    public function setUp(array $params): void
    {
        $unit = $params['gibberish'] ? 'xkcdqwrtzpln vbnmsdfghjkl ' : self::PARAGRAPH;
        $this->text = mb_substr(str_repeat($unit, intdiv($params['characters'], mb_strlen($unit)) + 1), 0, $params['characters']);
    }

    /**
     * @ParamProviders("texts")
     *
     * @Revs(20)
     */
    public function benchInspect(): void
    {
        $this->rules->inspect($this->text);
    }

    /**
     * @return Generator<string, array{characters: int, gibberish: bool}>
     */
    public function texts(): Generator
    {
        yield 'message 200' => ['characters' => 200, 'gibberish' => false];
        yield 'message 2 000' => ['characters' => 2_000, 'gibberish' => false];
        yield 'bound 20 000' => ['characters' => 20_000, 'gibberish' => false];
        yield 'too long 200 000' => ['characters' => 200_000, 'gibberish' => false];
        yield 'gibberish 2 000' => ['characters' => 2_000, 'gibberish' => true];
    }
}
