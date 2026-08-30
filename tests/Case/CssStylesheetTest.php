<?php

/**
 * The stylesheet generators: base vocabulary, theme tokens, and node-scoped
 * style compilation — deterministic output and a fail-closed vocabulary.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Css\BaseStylesheet;
use Kumwe\Producer\Css\CssException;
use Kumwe\Producer\Css\ScopedStylesheet;
use Kumwe\Producer\Css\ThemeStylesheet;
use Kumwe\Producer\Tests\TestCase;

final class CssStylesheetTest extends TestCase
{
    public function testBaseStylesheetCarriesTheAttributeVocabulary(): void
    {
        $css = BaseStylesheet::css();
        foreach (
            [
                'prefers-reduced-motion',
                '[data-studio-block]',
                'data-studio-layout=stack',
                'data-studio-position=sticky',
                '@media print',
                'width>=48rem',
                'width>=75rem',
                'data-studio-cover',
                'data-studio-chart-table',
                'data-studio-lightbox-dialog',
            ] as $needle
        ) {
            $this->assertStringContains($needle, $css, 'The base vocabulary must be complete.');
        }
        $this->assertSame($css, BaseStylesheet::css(), 'The base stylesheet is a constant.');
    }

    public function testThemeTokensCompileDeterministically(): void
    {
        $shuffled = ['space' => '1rem', 'accent' => '#ff4400', 'radius' => '0.25rem'];
        $sorted = ['accent' => '#ff4400', 'radius' => '0.25rem', 'space' => '1rem'];
        $expected = ':root{--studio-accent:#ff4400;--studio-radius:0.25rem;--studio-space:1rem}';
        $this->assertSame($expected, ThemeStylesheet::compile($shuffled), 'Tokens compile in sorted name order.');
        $this->assertSame(
            ThemeStylesheet::compile($shuffled),
            ThemeStylesheet::compile($sorted),
            'Input order must not change the output.'
        );
        $this->assertSame('', ThemeStylesheet::compile([]), 'No tokens compile to no rule.');
        $document = ThemeStylesheet::document($shuffled);
        $this->assertStringContains($expected, $document, 'The theme document leads with its tokens.');
        $this->assertStringContains('prefers-reduced-motion', $document, 'The theme document carries the reduced-motion base.');
        $this->assertSame(BaseStylesheet::css(), ThemeStylesheet::document(), 'Without tokens the document is the base.');
    }

    public function testThemeTokensFailClosed(): void
    {
        $hostile = [
            'a url value' => ['image' => 'url(javascript:alert(1))'],
            'an at-rule value' => ['x' => '@import evil'],
            'a declaration-breaking value' => ['x' => 'red;}body{background:url(x)'],
            'an uppercase name' => ['Accent' => '#fff'],
            'a selector-shaped name' => ['a}body' => '#fff'],
        ];
        foreach ($hostile as $label => $tokens) {
            $this->assertThrows(
                static fn (): string => ThemeStylesheet::compile($tokens),
                CssException::class,
                "Theme tokens must refuse {$label}."
            );
        }
        $this->assertSame(
            ':root{--studio-fine:var(--studio-accent)}',
            ThemeStylesheet::compile(['fine' => 'var(--studio-accent)']),
            'Token references to other studio tokens stay allowed.'
        );
    }

    public function testScopedStylesCompileTheClosedVocabulary(): void
    {
        $sheet = [
            'rules' => [
                ['target' => 'heading', 'declarations' => ['color' => '#fff', 'background-color' => '#101010']],
                ['target' => 'self', 'declarations' => ['padding-block' => '1rem']],
            ],
        ];
        $this->assertSame(
            '[data-studio-scope=s1][data-studio-part="heading"]{background-color:#101010;color:#fff}'
            . '[data-studio-scope=s1]{padding-block:1rem}',
            ScopedStylesheet::compile('s1', $sheet),
            'Scoped rules compile with sorted declarations under the scope attribute.'
        );
    }

    public function testScopedStylesFailClosed(): void
    {
        $cases = [
            'an invalid scope' => ['scope' => 'bad scope', 'sheet' => ['rules' => []]],
            'an unknown target' => ['scope' => 's1', 'sheet' => ['rules' => [
                ['target' => 'body', 'declarations' => ['color' => '#fff']],
            ]]],
            'a disallowed property' => ['scope' => 's1', 'sheet' => ['rules' => [
                ['target' => 'self', 'declarations' => ['position' => 'fixed']],
            ]]],
            'a hostile value' => ['scope' => 's1', 'sheet' => ['rules' => [
                ['target' => 'self', 'declarations' => ['color' => 'red;}x{background:url(x)']],
            ]]],
            'an unscoped variable' => ['scope' => 's1', 'sheet' => ['rules' => [
                ['target' => 'self', 'declarations' => ['color' => 'var(--evil)']],
            ]]],
        ];
        foreach ($cases as $label => $case) {
            $this->assertThrows(
                static fn (): string => ScopedStylesheet::compile($case['scope'], $case['sheet']),
                CssException::class,
                "Scoped styles must refuse {$label}."
            );
        }

        $tooMany = ['rules' => array_fill(0, 101, ['target' => 'self', 'declarations' => ['color' => '#fff']])];
        $this->assertThrows(
            static fn (): string => ScopedStylesheet::compile('s1', $tooMany),
            CssException::class,
            'Scoped styles must refuse more than 100 rules.'
        );
    }
}
