<?php

/**
 * Replay Studio's canonical serialization conformance corpus.
 *
 * Every vector fixes the exact canonical string and the SRI-style digest of
 * its bytes, or the stable rejection code; producing the same answers is what
 * makes this encoder byte-compatible with every other conforming runtime.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalEncodingException;
use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Tests\TestCase;

final class CanonicalJsonTest extends TestCase
{
    public function testReplaysTheCompleteCanonicalCorpus(): void
    {
        $directory = dirname(__DIR__, 2) . '/resources/studio-contract/testkit/vectors/canonical';
        $files = glob($directory . '/*.json') ?: [];
        sort($files);
        $this->assertTrue(count($files) >= 12, 'The canonical corpus must be vendored.');

        foreach ($files as $file) {
            $vector = CanonicalJson::decode((string) file_get_contents($file));
            $label = $vector->id;
            $maximumDepth = $vector->maximumDepth ?? CanonicalJson::DEFAULT_MAXIMUM_DEPTH;

            if (isset($vector->expect->rejected)) {
                $error = $this->assertThrows(
                    static fn (): string => CanonicalJson::stringify($vector->value, $maximumDepth),
                    CanonicalEncodingException::class,
                    "{$label} must be refused."
                );
                $this->assertSame(
                    $vector->expect->rejected,
                    $error->rejection(),
                    "{$label} must carry the stable rejection code."
                );
                continue;
            }

            $this->assertSame(
                $vector->expect->canonical,
                CanonicalJson::stringify($vector->value, $maximumDepth),
                "{$label} canonical string must match."
            );
            $this->assertSame(
                $vector->expect->digest,
                CanonicalJson::digest($vector->value, $maximumDepth),
                "{$label} digest must match."
            );
        }
    }

    public function testAstralMemberNamesSortByUtf16CodeUnit(): void
    {
        $value = CanonicalJson::decode("{\"\\ud800\\udc00\": 1, \"\\ufffd\": 2}");
        $canonical = CanonicalJson::stringify($value);
        $replacement = strpos($canonical, "\u{FFFD}");
        $astral = strpos($canonical, "\u{10000}");
        $this->assertTrue(
            $replacement !== false && $astral !== false && $astral < $replacement,
            'U+10000 must sort before U+FFFD: its lead surrogate D800 precedes FFFD in UTF-16 '
                . 'code-unit order, the reverse of raw UTF-8 byte order.'
        );
        $this->assertTrue(
            CanonicalJson::compareCodeUnits("\u{10000}", "\u{FFFD}") < 0,
            'Host code can use the same public comparator without duplicating canonical ordering.',
        );
    }

    public function testRoundTripsThroughDecode(): void
    {
        $document = CanonicalJson::decode('{"b":[1,2.5,{"z":null,"a":true}],"a":"héllo"}');
        $first = CanonicalJson::stringify($document);
        $second = CanonicalJson::stringify(CanonicalJson::decode($first));
        $this->assertSame($first, $second, 'Canonical output must be a fixed point of decode.');
    }

    public function testInvalidUtf8AndNumbersOutsideTheSafeRangeAreRefused(): void
    {
        $invalidMember = new \stdClass();
        $invalidMember->{"member\xff"} = 1;
        $values = [
            'invalid string bytes' => "value\xff",
            'invalid member bytes' => $invalidMember,
            'integer above safe maximum' => 9007199254740992,
            'integer below safe minimum' => -9007199254740992,
            'float above safe maximum' => 9007199254740992.0,
        ];
        foreach ($values as $label => $value) {
            $error = $this->assertThrows(
                static fn () => CanonicalJson::stringify($value),
                CanonicalEncodingException::class,
                'The ' . $label . ' must be refused.'
            );
            $this->assertSame('unrepresentable', $error->rejection(), 'The ' . $label . ' code must be stable.');
        }

        $this->assertSame(
            '9007199254740991',
            CanonicalJson::stringify(9007199254740991),
            'The exact positive safe-integer boundary must remain portable.'
        );
        $this->assertSame(
            '-9007199254740991',
            CanonicalJson::stringify(-9007199254740991),
            'The exact negative safe-integer boundary must remain portable.'
        );
    }
}
