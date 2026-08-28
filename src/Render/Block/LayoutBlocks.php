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

final class LayoutBlocks implements BlockRenderer
{
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

    private function layout(\stdClass $node, string $kind, RenderState $state): string
    {
        $slot = $kind === 'section' ? 'content' : 'items';

        return '<div data-studio-layout="' . $kind . '" data-studio-part="content">'
            . $state->renderChildren($node, $slot) . '</div>';
    }

    private function responsiveLayout(\stdClass $node, string $kind, string $scope, RenderState $state): string
    {
        $compact = Properties::integerProperty(Properties::property($node, 'columns'), 1, 12, 1);
        $medium = Properties::integerProperty($node->responsive->columns->medium ?? null, 1, 12, $compact);
        $expanded = Properties::integerProperty($node->responsive->columns->expanded ?? null, 1, 12, $medium);
        $state->css[] = '[data-studio-scope="' . $scope . '"]{--studio-columns-compact:' . $compact
            . ';--studio-columns-medium:' . $medium . ';--studio-columns-expanded:' . $expanded . '}';

        return '<div data-studio-layout="' . $kind . '" data-studio-part="content">'
            . $state->renderChildren($node, 'items') . '</div>';
    }

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
        $state->css[] = '[data-studio-scope="' . $scope . '"]{--studio-cover-overlay:' . $opacity . '}';
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

    private function article(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));

        return '<article>'
            . ($title === '' ? '' : '<h2 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h2>')
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></article>';
    }

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
