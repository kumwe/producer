<?php

/**
 * The text family: heading, rich text, code, callout, badge, label, icon,
 * and divider.
 *
 * Every stored string is escaped; rich-text content renders only through the
 * validating portable grammar (or a structural safe-markup fragment) and
 * falls back to escaped plain text when the document is refused.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render\Block;

use Kumwe\Producer\Render\BlockRenderer;
use Kumwe\Producer\Render\BlockTypes;
use Kumwe\Producer\Render\Properties;
use Kumwe\Producer\Render\RenderState;
use Kumwe\Producer\Render\RichText;
use Kumwe\Producer\Render\SafeMarkup;

final class TextBlocks implements BlockRenderer
{
    public function types(): array
    {
        return [
            BlockTypes::HEADING,
            BlockTypes::RICH_TEXT,
            BlockTypes::CODE,
            BlockTypes::CALLOUT,
            BlockTypes::BADGE,
            BlockTypes::LABEL,
            BlockTypes::ICON,
            BlockTypes::DIVIDER,
        ];
    }

    public function render(\stdClass $node, string $scope, RenderState $state): string
    {
        return match ($node->type) {
            BlockTypes::HEADING => $this->heading($node, $state),
            BlockTypes::RICH_TEXT => $this->richText($node, $state),
            BlockTypes::CODE => $this->source($node, 'code', $state),
            BlockTypes::CALLOUT => $this->callout($node, $state),
            BlockTypes::BADGE => $this->badge($node, $state),
            BlockTypes::LABEL => $this->label($node, $state),
            BlockTypes::ICON => $this->icon($node, $state),
            BlockTypes::DIVIDER => $this->divider($node, $state),
        };
    }

    private function heading(\stdClass $node, RenderState $state): string
    {
        $level = Properties::integerProperty(Properties::property($node, 'level'), 1, 6, 2);
        $text = Properties::stringValue($state->bindingValue($node, 'text'));

        return "<h{$level} data-studio-part=\"heading\">" . SafeMarkup::escapeHtml($text) . "</h{$level}>";
    }

    private function richText(\stdClass $node, RenderState $state): string
    {
        $value = $state->bindingValue($node, 'content');
        if (SafeMarkup::isFragment($value) && $value instanceof \stdClass) {
            return '<div data-studio-part="content">' . SafeMarkup::renderFragment($value) . '</div>';
        }
        try {
            return '<div data-studio-part="content">' . RichText::render(RichText::parse($value)) . '</div>';
        } catch (\Throwable) {
            return '<div data-studio-part="content"></div>';
        }
    }

    private function source(\stdClass $node, string $language, RenderState $state): string
    {
        $value = Properties::stringValue($state->bindingValue($node, 'source'));
        $selected = Properties::stringProperty(Properties::property($node, 'language'), $language);

        return '<pre data-studio-part="content"><code data-language="'
            . SafeMarkup::escapeAttribute($selected) . '">' . SafeMarkup::escapeHtml($value) . '</code></pre>';
    }

    private function callout(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));
        $content = $state->bindingValue($node, 'content');
        try {
            $body = RichText::render(RichText::parse($content));
        } catch (\Throwable) {
            $body = SafeMarkup::escapeHtml(Properties::stringValue($content));
        }
        $tone = Properties::enumProperty(
            Properties::property($node, 'tone'),
            ['danger', 'information', 'success', 'warning'],
            'information'
        );

        return '<aside role="note" data-studio-tone="' . $tone . '">'
            . '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>'
            . '<div data-studio-part="content">' . $body . '</div></aside>';
    }

    private function badge(\stdClass $node, RenderState $state): string
    {
        $appearance = Properties::enumProperty(
            Properties::property($node, 'appearance'),
            ['outline', 'soft', 'solid'],
            'solid'
        );

        return '<span data-studio-badge="' . $appearance . '" data-studio-tone="'
            . Properties::toneProperty(Properties::property($node, 'tone')) . '">'
            . SafeMarkup::escapeHtml(Properties::stringValue($state->bindingValue($node, 'label'))) . '</span>';
    }

    private function label(\stdClass $node, RenderState $state): string
    {
        return '<span data-studio-label data-studio-tone="'
            . Properties::toneProperty(Properties::property($node, 'tone')) . '">'
            . SafeMarkup::escapeHtml(Properties::stringValue($state->bindingValue($node, 'text'))) . '</span>';
    }

    private function icon(\stdClass $node, RenderState $state): string
    {
        $candidate = Properties::stringProperty(Properties::property($node, 'name'), 'symbol');
        $name = preg_match('%^[a-z][a-z0-9-]{0,62}(?:/[a-z][a-z0-9-]{0,62})?$%u', $candidate) === 1
            ? $candidate
            : 'symbol';
        $decorative = Properties::property($node, 'decorative') !== false;
        $alternative = Properties::stringValueOr($state->bindingValue($node, 'alternative-text'), 'Icon');

        return '<span data-studio-icon="' . SafeMarkup::escapeAttribute($name) . '" aria-hidden="true"></span>'
            . ($decorative
                ? ''
                : '<span class="studio-visually-hidden">' . SafeMarkup::escapeHtml($alternative) . '</span>');
    }

    private function divider(\stdClass $node, RenderState $state): string
    {
        $style = Properties::enumProperty(
            Properties::property($node, 'style'),
            ['dashed', 'dotted', 'solid'],
            'solid'
        );
        $label = Properties::stringValue($state->bindingValue($node, 'label'));

        return '<hr data-studio-divider="' . $style . '"'
            . ($label === '' ? '' : ' aria-label="' . SafeMarkup::escapeAttribute($label) . '"') . '>';
    }
}
