<?php

/**
 * The layout and container family: section, stack, grid, columns, cover,
 * article, and card.
 *
 * Layout kinds emit the data-attribute layout vocabulary; grid and columns
 * additionally scope their responsive column custom properties; cover, the
 * article, and the card compose media, headings, and slot content into
 * semantic containers.
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
 * Renders the container family into semantic wrappers that carry Studio's
 * data-attribute layout vocabulary.
 *
 * Stateless: every per-render effect (generated CSS, child rendering, media
 * resolution) goes through the supplied {@see RenderState}, and every stored
 * value reaches the markup only through {@see SafeMarkup} or a closed
 * property vocabulary.
 *
 * @since   0.1.0
 */
final class LayoutBlocks implements BlockRenderer
{
    /**
     * The seven container types this renderer serves: section, stack, grid,
     * columns, cover, article, and card.
     *
     * @return  list<string>  The block type identifiers, in catalog order.
     *
     * @since   0.1.0
     */
    public function types(): array
    {
        return [
            BlockTypes::SECTION,
            BlockTypes::STACK,
            BlockTypes::GRID,
            BlockTypes::COLUMNS,
            BlockTypes::COVER,
            BlockTypes::ARTICLE,
            BlockTypes::CARD,
        ];
    }

    /**
     * Dispatches one container node to its type-specific renderer.
     *
     * @param   \stdClass    $node   The block node; its type must be one the
     *                               renderer lists, or the match refuses it.
     * @param   string       $scope  The node's unique scope token, used to key
     *                               generated per-node CSS custom properties.
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
            BlockTypes::SECTION => $this->layout($node, 'section', $state),
            BlockTypes::STACK => $this->layout($node, 'stack', $state),
            BlockTypes::GRID => $this->responsiveLayout($node, 'grid', $scope, $state),
            BlockTypes::COLUMNS => $this->responsiveLayout($node, 'columns', $scope, $state),
            BlockTypes::COVER => $this->cover($node, $scope, $state),
            BlockTypes::ARTICLE => $this->article($node, $state),
            BlockTypes::CARD => $this->card($node, $state),
        };
    }

    /**
     * Renders a plain container (section or stack) to a single `div` carrying
     * `data-studio-layout` and its rendered children.
     *
     * A section's children come from its `content` slot; every other kind
     * reads the `items` slot. Nothing else on the node is consulted.
     *
     * @param   \stdClass    $node   The container node.
     * @param   string       $kind   The layout vocabulary word emitted in
     *                               `data-studio-layout` ('section' or 'stack').
     * @param   RenderState  $state  Renders the slot children.
     *
     * @return  string  The container `div` with its children inside.
     *
     * @since   0.1.0
     */
    private function layout(\stdClass $node, string $kind, RenderState $state): string
    {
        $slot = $kind === 'section' ? 'content' : 'items';

        return '<div data-studio-layout="' . $kind . '" data-studio-part="content">'
            . $state->renderChildren($node, $slot) . '</div>';
    }

    /**
     * Renders a grid or columns container and scopes its responsive column
     * counts as CSS custom properties.
     *
     * The compact count comes from the `columns` property clamped to 1–12
     * (fallback 1); the medium and expanded counts come from
     * `responsive.columns` and cascade from the next-narrower breakpoint when
     * absent or out of bounds. The three counts are appended to the render's
     * CSS keyed by the node's scope; the markup itself is the same
     * `data-studio-layout` `div` with the `items` slot rendered inside.
     *
     * @param   \stdClass    $node   The container node.
     * @param   string       $kind   The layout vocabulary word ('grid' or
     *                               'columns').
     * @param   string       $scope  Keys the generated custom-property rule to
     *                               this node only.
     * @param   RenderState  $state  Receives the CSS and renders the children.
     *
     * @return  string  The container `div` with its children inside.
     *
     * @since   0.1.0
     */
    private function responsiveLayout(\stdClass $node, string $kind, string $scope, RenderState $state): string
    {
        $compact = Properties::integerProperty(Properties::property($node, 'columns'), 1, 12, 1);
        $responsive = $node->responsive ?? null;
        $columns = $responsive instanceof \stdClass ? ($responsive->columns ?? null) : null;
        $mediumValue = $columns instanceof \stdClass ? ($columns->medium ?? null) : null;
        $expandedValue = $columns instanceof \stdClass ? ($columns->expanded ?? null) : null;
        $medium = Properties::integerProperty($mediumValue, 1, 12, $compact);
        $expanded = Properties::integerProperty($expandedValue, 1, 12, $medium);
        $state->css[] = '[data-studio-scope=' . $scope . ']{--studio-columns-compact:' . $compact
            . ';--studio-columns-medium:' . $medium . ';--studio-columns-expanded:' . $expanded . '}';

        return '<div data-studio-layout="' . $kind . '" data-studio-part="content">'
            . $state->renderChildren($node, 'items') . '</div>';
    }

    /**
     * Renders a cover to a `section` with an optional decorative background
     * image and a scoped overlay opacity.
     *
     * The background binding resolves through the host and the media URL
     * allowlist; an unresolvable or refused asset simply renders no image —
     * the section and its `content` slot still render. A resolved image is
     * decorative by contract (empty `alt`, `aria-hidden`). The overlay
     * opacity maps the closed none/light/medium/strong vocabulary onto a
     * scoped custom property (any other stored value means medium), and the
     * content alignment falls back to center outside center/end/start.
     *
     * @param   \stdClass    $node   The cover node.
     * @param   string       $scope  Keys the overlay custom property to this
     *                               node only.
     * @param   RenderState  $state  Resolves media, receives CSS, renders the
     *                               content slot.
     *
     * @return  string  The cover `section` markup.
     *
     * @since   0.1.0
     */
    private function cover(\stdClass $node, string $scope, RenderState $state): string
    {
        $background = $state->resolvedMedia($state->bindingValue($node, 'background'));
        $overlay = Properties::stringProperty(Properties::property($node, 'overlay'), 'medium');
        $opacity = match ($overlay) {
            'none' => '0',
            'light' => '0.2',
            'strong' => '0.65',
            default => '0.4',
        };
        $state->css[] = '[data-studio-scope=' . $scope . ']{--studio-cover-overlay:' . $opacity . '}';
        $alignment = Properties::enumProperty(
            Properties::property($node, 'alignment'),
            ['center', 'end', 'start'],
            'center'
        );
        $image = $background === null
            ? ''
            : '<img src="' . SafeMarkup::escapeAttribute($background->src) . '" alt="" aria-hidden="true"'
                . $background->dimensionsAttribute() . '>';

        return '<section data-studio-cover data-studio-cover-align="' . $alignment . '">' . $image
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></section>';
    }

    /**
     * Renders an article container to an `article` element with an optional
     * escaped `h2` heading and its `content` slot.
     *
     * The heading appears only when the bound title is a non-empty string;
     * a missing or non-string title renders no heading at all.
     *
     * @param   \stdClass    $node   The article node.
     * @param   RenderState  $state  Resolves the title binding and renders
     *                               the content slot.
     *
     * @return  string  The `article` markup.
     *
     * @since   0.1.0
     */
    private function article(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));

        return '<article>'
            . ($title === '' ? '' : '<h2 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h2>')
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></article>';
    }

    /**
     * Renders a card to an `article` composing optional media, an `h3`
     * heading, a summary body, and the `actions` slot.
     *
     * The media binding resolves through the host and the media URL
     * allowlist; an unresolvable or refused asset renders no image. The
     * summary renders through the validating rich-text grammar; a refused
     * document falls back to the summary escaped as plain text, so hostile
     * structure degrades to inert text rather than markup.
     *
     * @param   \stdClass    $node   The card node.
     * @param   RenderState  $state  Resolves bindings and media and renders
     *                               the actions slot.
     *
     * @return  string  The card `article` markup.
     *
     * @since   0.1.0
     */
    private function card(\stdClass $node, RenderState $state): string
    {
        $media = $state->resolvedMedia($state->bindingValue($node, 'media'));
        $title = Properties::stringValue($state->bindingValue($node, 'title'));
        $summary = $state->bindingValue($node, 'summary');
        try {
            $body = RichText::render(RichText::parse($summary));
        } catch (\Throwable) {
            $body = SafeMarkup::escapeHtml(Properties::stringValue($summary));
        }
        $image = $media === null
            ? ''
            : '<img data-studio-part="media" src="' . SafeMarkup::escapeAttribute($media->src)
                . '" alt="' . SafeMarkup::escapeAttribute($media->altText) . '"'
                . $media->dimensionsAttribute() . '>';

        return '<article>' . $image
            . '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>'
            . '<div data-studio-part="content">' . $body . '</div>'
            . '<div data-studio-part="action">' . $state->renderChildren($node, 'actions') . '</div></article>';
    }
}
