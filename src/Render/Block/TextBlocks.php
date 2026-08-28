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

/**
 * Renders the text family to inline and prose-level semantic elements.
 *
 * Stateless: bindings resolve through the supplied {@see RenderState}, every
 * stored string is escaped by {@see SafeMarkup}, and free-form vocabulary
 * values (tone, appearance, style, icon names) are coerced into closed sets
 * before they reach an attribute.
 *
 * @since   0.1.0
 */
final class TextBlocks implements BlockRenderer
{
    /**
     * The eight text types this renderer serves: heading, rich text, code,
     * callout, badge, label, icon, and divider.
     *
     * @return  list<string>  The block type identifiers, in catalog order.
     *
     * @since   0.1.0
     */
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

    /**
     * Dispatches one text node to its type-specific renderer.
     *
     * @param   \stdClass    $node   The block node; its type must be one the
     *                               renderer lists, or the match refuses it.
     * @param   string       $scope  The node's unique scope token (unused by
     *                               this family, which generates no CSS).
     * @param   RenderState  $state  Engine services and per-render accumulators.
     *
     * @return  string  The node's inner semantic HTML, every dynamic value
     *                  escaped.
     *
     * @throws  \UnhandledMatchError  When the node's type is not one this
     *                                renderer declared in {@see types()}.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a heading to the `h1`–`h6` element its `level` property
     * selects, with the bound text escaped.
     *
     * The level is accepted only as an integer 1 through 6; anything else
     * renders an `h2`. A missing or non-string text binding renders an empty
     * heading rather than failing.
     *
     * @param   \stdClass    $node   The heading node.
     * @param   RenderState  $state  Resolves the text binding.
     *
     * @return  string  The heading element markup.
     *
     * @since   0.1.0
     */
    private function heading(\stdClass $node, RenderState $state): string
    {
        $level = Properties::integerProperty(Properties::property($node, 'level'), 1, 6, 2);
        $text = Properties::stringValue($state->bindingValue($node, 'text'));

        return "<h{$level} data-studio-part=\"heading\">" . SafeMarkup::escapeHtml($text) . "</h{$level}>";
    }

    /**
     * Renders bound rich content into a content `div` through one of the two
     * validating paths, never as raw markup.
     *
     * A structural safe-markup fragment renders through the fail-closed
     * fragment vocabulary; anything else must parse as a canonical rich-text
     * document. A value either path refuses renders an empty content `div` —
     * the block never leaks the refused value or the refusal reason.
     *
     * @param   \stdClass    $node   The rich-text node.
     * @param   RenderState  $state  Resolves the content binding.
     *
     * @return  string  The content `div`, possibly empty.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a source-code block to `pre > code` with the source escaped and
     * the language named in an escaped `data-language` attribute.
     *
     * The source is highlighted by nothing and executed by nothing: it is
     * plain escaped text. A non-string language property falls back to the
     * caller's default.
     *
     * @param   \stdClass    $node      The code node.
     * @param   string       $language  The fallback language identifier when
     *                                  the node's `language` property is not
     *                                  a string.
     * @param   RenderState  $state     Resolves the source binding.
     *
     * @return  string  The `pre > code` markup.
     *
     * @since   0.1.0
     */
    private function source(\stdClass $node, string $language, RenderState $state): string
    {
        $value = Properties::stringValue($state->bindingValue($node, 'source'));
        $selected = Properties::stringProperty(Properties::property($node, 'language'), $language);

        return '<pre data-studio-part="content"><code data-language="'
            . SafeMarkup::escapeAttribute($selected) . '">' . SafeMarkup::escapeHtml($value) . '</code></pre>';
    }

    /**
     * Renders a callout to an `aside` with `role="note"`, a toned border
     * vocabulary, an escaped `h3` title, and a rich-text body.
     *
     * The tone is coerced into danger/information/success/warning (fallback
     * information). The body renders through the validating rich-text
     * grammar; a refused document falls back to the bound content escaped as
     * plain text, so hostile structure degrades to inert text.
     *
     * @param   \stdClass    $node   The callout node.
     * @param   RenderState  $state  Resolves the title and content bindings.
     *
     * @return  string  The callout `aside` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a badge to a `span` whose appearance and tone attributes come
     * only from closed vocabularies.
     *
     * Appearance is coerced into outline/soft/solid (fallback solid), tone
     * into the five-tone vocabulary (fallback neutral), and the bound label
     * is escaped; a missing label renders an empty badge.
     *
     * @param   \stdClass    $node   The badge node.
     * @param   RenderState  $state  Resolves the label binding.
     *
     * @return  string  The badge `span` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a label to a toned `span` with the bound text escaped.
     *
     * The tone attribute is coerced into the closed five-tone vocabulary
     * (fallback neutral); a missing text binding renders an empty label.
     *
     * @param   \stdClass    $node   The label node.
     * @param   RenderState  $state  Resolves the text binding.
     *
     * @return  string  The label `span` markup.
     *
     * @since   0.1.0
     */
    private function label(\stdClass $node, RenderState $state): string
    {
        return '<span data-studio-label data-studio-tone="'
            . Properties::toneProperty(Properties::property($node, 'tone')) . '">'
            . SafeMarkup::escapeHtml(Properties::stringValue($state->bindingValue($node, 'text'))) . '</span>';
    }

    /**
     * Renders an icon to an `aria-hidden` `span` naming the glyph by a
     * validated slug, with a text alternative when the icon carries meaning.
     *
     * The icon name must match the lowercase slug shape (optionally
     * namespaced with one `/`); anything else renders the neutral `symbol`
     * glyph, so a hostile name never reaches the attribute. Icons are
     * decorative unless `decorative` is stored `false`, in which case a
     * visually hidden span carries the bound alternative text (fallback
     * "Icon").
     *
     * @param   \stdClass    $node   The icon node.
     * @param   RenderState  $state  Resolves the alternative-text binding.
     *
     * @return  string  The icon markup, with the hidden text alternative
     *                  when the icon is not decorative.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a divider to an `hr` styled by a closed vocabulary, labelled
     * only when a label is bound.
     *
     * The style is coerced into dashed/dotted/solid (fallback solid). A
     * non-empty bound label becomes an escaped `aria-label`; an empty or
     * missing label leaves the rule purely presentational.
     *
     * @param   \stdClass    $node   The divider node.
     * @param   RenderState  $state  Resolves the label binding.
     *
     * @return  string  The `hr` markup.
     *
     * @since   0.1.0
     */
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
