<?php

namespace tests\unit\helpers;

use app\helpers\ChoiceOptionParser;
use Codeception\Test\Unit;

/**
 * Tests ChoiceOptionParser against all patterns found in ai_run.result_text.
 * This is a specification test: the PHP helper mirrors the JS parseChoiceOptions()
 * in ai-chat/index.php. If one changes, the other must follow.
 */
class ChoiceOptionParserTest extends Unit
{
    // ── Format 1: slash-separated (dataProvider) ───────────────────

    public static function slashProvider(): array
    {
        return [
            'three options with Architect (DB #308)' => [
                'Doorvoeren en door naar Architect / Aanpassen / Overslaan?',
                [
                    ['label' => 'Doorvoeren en door naar Architect', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                    ['label' => 'Overslaan', 'action' => 'send'],
                ],
            ],
            'three options with Developer (DB #312)' => [
                'Doorvoeren en door naar Developer / Aanpassen / Overslaan?',
                [
                    ['label' => 'Doorvoeren en door naar Developer', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                    ['label' => 'Overslaan', 'action' => 'send'],
                ],
            ],
            'three options with Eindsamenvatting (DB #320)' => [
                'Doorvoeren en door naar Eindsamenvatting / Aanpassen / Overslaan?',
                [
                    ['label' => 'Doorvoeren en door naar Eindsamenvatting', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                    ['label' => 'Overslaan', 'action' => 'send'],
                ],
            ],
            'two options review (DB #280, #306)' => [
                'Start review / Volgorde aanpassen?',
                [
                    ['label' => 'Start review', 'action' => 'send'],
                    ['label' => 'Volgorde aanpassen', 'action' => 'send'],
                ],
            ],
            'three options spec example' => [
                'Implementatie / Review ronde / Handmatig bewerken?',
                [
                    ['label' => 'Implementatie', 'action' => 'send'],
                    ['label' => 'Review ronde', 'action' => 'send'],
                    ['label' => 'Handmatig bewerken', 'action' => 'send'],
                ],
            ],
            'four options max' => [
                'A / B / C / D?',
                [
                    ['label' => 'A', 'action' => 'send'],
                    ['label' => 'B', 'action' => 'send'],
                    ['label' => 'C', 'action' => 'send'],
                    ['label' => 'D', 'action' => 'send'],
                ],
            ],
            'without question mark' => [
                'Post / Bewerk / Skip',
                [
                    ['label' => 'Post', 'action' => 'send'],
                    ['label' => 'Bewerk', 'action' => 'edit'],
                    ['label' => 'Skip', 'action' => 'send'],
                ],
            ],
            'with trailing whitespace' => [
                "Post / Bewerk / Skip?  \n\n",
                [
                    ['label' => 'Post', 'action' => 'send'],
                    ['label' => 'Bewerk', 'action' => 'edit'],
                    ['label' => 'Skip', 'action' => 'send'],
                ],
            ],
            'buttons as last line of multiline text' => [
                "## Samenvatting\n\nAlles ziet er goed uit.\n\nDoorvoeren / Aanpassen?",
                [
                    ['label' => 'Doorvoeren', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
            'markdown bold/italic/code stripped' => [
                '**Implementatie** / _Review_ / `Skip`?',
                [
                    ['label' => 'Implementatie', 'action' => 'send'],
                    ['label' => 'Review', 'action' => 'send'],
                    ['label' => 'Skip', 'action' => 'send'],
                ],
            ],
            'option at exactly 80 characters' => [
                str_repeat('a', 80) . ' / Kort?',
                [
                    ['label' => str_repeat('a', 80), 'action' => 'send'],
                    ['label' => 'Kort', 'action' => 'send'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider slashProvider
     */
    public function testSlashFormat(string $input, array $expected): void
    {
        $result = ChoiceOptionParser::parse($input);
        verify($result)->notNull();
        verify(count($result))->equals(count($expected));
        foreach ($expected as $i => $opt) {
            verify($result[$i]['label'])->equals($opt['label']);
            verify($result[$i]['action'])->equals($opt['action']);
        }
    }

    // ── Format 2: parenthesized slash-separated (dataProvider) ─────

    public static function parenthesizedProvider(): array
    {
        return [
            'two options (DB #279, #305)' => [
                'Akkoord met analyse? (Ja / Aanpassen)',
                [
                    ['label' => 'Ja', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
            'three options' => [
                'Akkoord met de aanpak? (Ja / Nee / Aanpassen)',
                [
                    ['label' => 'Ja', 'action' => 'send'],
                    ['label' => 'Nee', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider parenthesizedProvider
     */
    public function testParenthesizedSlashFormat(string $input, array $expected): void
    {
        $result = ChoiceOptionParser::parse($input);
        verify($result)->notNull();
        verify(count($result))->equals(count($expected));
        foreach ($expected as $i => $opt) {
            verify($result[$i]['label'])->equals($opt['label']);
            verify($result[$i]['action'])->equals($opt['action']);
        }
    }

    // ── Context-prefix stripping (dataProvider) ────────────────────

    public static function dashPrefixProvider(): array
    {
        return [
            'em-dash with Front-end Developer (DB #311)' => [
                "Geen verbeterpunten \u{2014} door naar Front-end Developer / Aanpassen?",
                [
                    ['label' => 'door naar Front-end Developer', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
            'em-dash with long context (DB #321)' => [
                "Geen verbeterpunten die doorvoering vereisen \u{2014} door naar eindsamenvatting / Aanpassen?",
                [
                    ['label' => 'door naar eindsamenvatting', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
            'en-dash variant' => [
                "Alles akkoord \u{2013} door naar Security / Aanpassen?",
                [
                    ['label' => 'door naar Security', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dashPrefixProvider
     */
    public function testDashPrefixStripping(string $input, array $expected): void
    {
        $result = ChoiceOptionParser::parse($input);
        verify($result)->notNull();
        verify(count($result))->equals(count($expected));
        foreach ($expected as $i => $opt) {
            verify($result[$i]['label'])->equals($opt['label']);
            verify($result[$i]['action'])->equals($opt['action']);
        }
    }

    // ── Edit-word detection ────────────────────────────────────────

    public static function editWordProvider(): array
    {
        return [
            'Bewerk' => ['Bewerk'],
            'Edit' => ['Edit'],
            'Aanpassen' => ['Aanpassen'],
            'Modify' => ['Modify'],
            'Adjust' => ['Adjust'],
        ];
    }

    /**
     * @dataProvider editWordProvider
     */
    public function testDetectsEditWord(string $word): void
    {
        $result = ChoiceOptionParser::parse("Doorgaan / {$word}?");
        verify($result)->notNull();
        verify($result[1]['action'])->equals('edit');
    }

    public function testNonEditWordsGetSendAction(): void
    {
        $result = ChoiceOptionParser::parse("Implementatie / Review ronde / Handmatig bewerken?");
        verify($result)->notNull();
        // "bewerken" != "bewerk", no edit action
        verify($result[2]['action'])->equals('send');
    }

    // ── Format 3: bracket-letter lines (dataProvider) ──────────────

    public static function bracketLinesProvider(): array
    {
        return [
            'three bracket options' => [
                "Kies een optie:\n\n[I] Start implementatie\n[R] Nog een review ronde\n[E] Handmatig bewerken",
                [
                    ['label' => 'Start implementatie', 'action' => 'send'],
                    ['label' => 'Nog een review ronde', 'action' => 'send'],
                    ['label' => 'Handmatig bewerken', 'action' => 'send'],
                ],
            ],
            'two bracket options with edit detection' => [
                "[A] Goedkeuren\n[B] Aanpassen",
                [
                    ['label' => 'Goedkeuren', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
            'five bracket options (max)' => [
                "[A] Optie een\n[B] Optie twee\n[C] Optie drie\n[D] Optie vier\n[E] Aanpassen",
                [
                    ['label' => 'Optie een', 'action' => 'send'],
                    ['label' => 'Optie twee', 'action' => 'send'],
                    ['label' => 'Optie drie', 'action' => 'send'],
                    ['label' => 'Optie vier', 'action' => 'send'],
                    ['label' => 'Aanpassen', 'action' => 'edit'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider bracketLinesProvider
     */
    public function testBracketLineFormat(string $input, array $expected): void
    {
        $result = ChoiceOptionParser::parse($input);
        verify($result)->notNull();
        verify(count($result))->equals(count($expected));
        foreach ($expected as $i => $opt) {
            verify($result[$i]['label'])->equals($opt['label']);
            verify($result[$i]['action'])->equals($opt['action']);
        }
    }

    // ── Format 4: inline bracket-letter ────────────────────────────

    public function testParsesInlineBracketLetters(): void
    {
        $result = ChoiceOptionParser::parse("[I] Start implementatie [R] Review ronde [E] Bewerk");
        verify($result)->notNull();
        verify(count($result))->equals(3);
        verify($result[0]['label'])->equals('Start implementatie');
        verify($result[2]['label'])->equals('Bewerk');
        verify($result[2]['action'])->equals('edit');
    }

    // ── False positives: should NOT produce buttons (dataProvider) ─

    public static function nullProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ["  \n  "],
            'plain question' => ['What would you like to do?'],
            'prose with slash (DB #327)' => [
                "- Geen Codeception tests aanwezig of mogelijk voor deze client-side JavaScript parsing. "
                . "Handmatig te verifi\u{00EB}ren door een response te laten eindigen op "
                . "`Doorvoeren en door naar Architect / Aanpassen / Overslaan?` (33 tekens eerste optie).",
            ],
            'question with or-separator (DB #323)' => [
                'Wil je dat ik optie A (prompt aanpassen) of optie B (limiet verhogen) doorvoer? Of een combinatie?',
            ],
            'finalize question (DB #328)' => [
                'Run `/finalize-changes` to lint, test, and prepare the commit. Ready to finalize?',
            ],
            'review question with of-separator (DB #303)' => [
                'De review is volledig afgerond. Wil je een nieuwe review ronde starten, of is er iets specifieks dat je wilt bespreken?',
            ],
            'five slash options exceeds max' => ['A / B / C / D / E?'],
            'single slash option' => ['Only one option /?'],
            'single bracket line' => ['[A] Only one option'],
            'slash option exceeding 80 chars' => [str_repeat('a', 81) . ' / Kort?'],
            'bracket option exceeding 40 chars' => ['[A] ' . str_repeat('a', 41) . "\n[B] Kort"],
        ];
    }

    /**
     * @dataProvider nullProvider
     */
    public function testReturnsNullForNonButtonText(string $input): void
    {
        verify(ChoiceOptionParser::parse($input))->null();
    }

    // ── Anti-pattern: buttons not on last line ─────────────────────

    public function testIgnoresSlashInMiddleOfText(): void
    {
        $text = "Post / Bewerk / Skip?\n\nDit is extra tekst na de buttons.";
        verify(ChoiceOptionParser::parse($text))->null();
    }
}
