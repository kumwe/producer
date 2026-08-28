<?php

/**
 * The media family: image, gallery, video, audio, attachment, and drawing.
 *
 * Every media URL passes the closed allowlist (blob URLs only under explicit
 * host authority and never for active content types); an unresolvable or
 * refused asset renders the reference's labeled unavailable fallback with no
 * data leak.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render\Block;

use Kumwe\Producer\Render\BlockRenderer;
use Kumwe\Producer\Render\BlockTypes;
use Kumwe\Producer\Render\ProductionValues;
use Kumwe\Producer\Render\Properties;
use Kumwe\Producer\Render\RenderState;
use Kumwe\Producer\Render\SafeMarkup;

final class MediaBlocks implements BlockRenderer
{
    public function types(): array
    {
        return [
            BlockTypes::IMAGE,
            BlockTypes::GALLERY,
            BlockTypes::VIDEO,
            BlockTypes::AUDIO,
            BlockTypes::ATTACHMENT,
            BlockTypes::DRAWING,
        ];
    }

    public function render(\stdClass $node, string $scope, RenderState $state): string
    {
        return match ($node->type) {
            BlockTypes::IMAGE => $this->image($node, $state),
            BlockTypes::GALLERY => $this->gallery($node, $scope, $state),
            BlockTypes::VIDEO => $this->video($node, $state),
            BlockTypes::AUDIO => $this->audio($node, $state),
            BlockTypes::ATTACHMENT => $this->attachment($node, $state),
            BlockTypes::DRAWING => $this->drawing($node, $state),
        };
    }

    private function image(\stdClass $node, RenderState $state): string
    {
        $media = $state->resolvedMedia($state->bindingValue($node, 'asset'));
        if ($media === null) {
            return '<p role="status">Image unavailable</p>';
        }
        $caption = $media->caption === null
            ? ''
            : '<figcaption>' . SafeMarkup::escapeHtml($media->caption) . '</figcaption>';
        $loading = Properties::property($node, 'loading') === 'eager' ? 'eager' : 'lazy';

        return '<figure><img data-studio-part="media" src="' . SafeMarkup::escapeAttribute($media->src)
            . '" alt="' . SafeMarkup::escapeAttribute($media->altText) . '" loading="' . $loading . '"'
            . $media->dimensionsAttribute() . '>' . $caption . '</figure>';
    }

    private function gallery(\stdClass $node, string $scope, RenderState $state): string
    {
        $value = $state->bindingValue($node, 'items');
        $references = is_array($value) ? $value : [];
        $media = [];
        foreach ($references as $reference) {
            $item = $state->resolvedMedia($reference);
            if ($item !== null) {
                $media[] = $item;
            }
        }
        $presentation = Properties::property($node, 'presentation') === 'slideshow' ? 'slideshow' : 'grid';
        $lightbox = Properties::property($node, 'lightbox') === true;
        $columns = Properties::integerProperty(Properties::property($node, 'columns'), 1, 12, 4);
        $state->css[] = '[data-studio-scope="' . $scope . '"]{--studio-gallery-columns:' . $columns . '}';
        if ($presentation === 'slideshow') {
            $state->enhance('slideshow', $node, $scope, [
                'autoplay' => Properties::property($node, 'autoplay') === true,
            ]);
        }
        if ($lightbox && $media !== []) {
            $state->enhance('lightbox', $node, $scope);
        }
        $items = '';
        foreach ($media as $index => $item) {
            $items .= '<figure data-studio-slide="' . $index . '">'
                . ($lightbox
                    ? '<a data-studio-lightbox-open="' . $index . '" href="'
                        . SafeMarkup::escapeAttribute($item->src) . '">'
                    : '')
                . '<img data-studio-part="media" src="' . SafeMarkup::escapeAttribute($item->src)
                . '" alt="' . SafeMarkup::escapeAttribute($item->altText) . '"' . $item->dimensionsAttribute() . '>'
                . ($lightbox ? '</a>' : '')
                . ($item->caption === null
                    ? ''
                    : '<figcaption>' . SafeMarkup::escapeHtml($item->caption) . '</figcaption>')
                . '</figure>';
        }

        return '<section data-studio-gallery="' . $presentation . '" aria-label="Media gallery">'
            . '<div data-studio-part="content">' . $items . '</div>'
            . ($presentation === 'slideshow'
                ? '<p><button type="button" data-studio-slide-previous>Previous</button>'
                    . '<button type="button" data-studio-slide-next>Next</button></p>'
                : '')
            . '</section>';
    }

    private function video(\stdClass $node, RenderState $state): string
    {
        $media = $state->resolvedMedia($state->bindingValue($node, 'asset'));
        if ($media === null) {
            return '<p role="status">Video unavailable</p>';
        }
        $poster = $state->resolvedMedia($state->bindingValue($node, 'poster'));
        $flags = (Properties::property($node, 'controls') === false ? '' : ' controls')
            . (Properties::property($node, 'autoplay') === true ? ' autoplay' : '')
            . (Properties::property($node, 'muted') === true ? ' muted' : '');
        $captions = Properties::stringValue($state->bindingValue($node, 'captions'));

        return '<video data-studio-part="media" src="' . SafeMarkup::escapeAttribute($media->src) . '"'
            . ($poster === null ? '' : ' poster="' . SafeMarkup::escapeAttribute($poster->src) . '"')
            . $flags . '>' . SafeMarkup::escapeHtml($captions) . '</video>';
    }

    private function audio(\stdClass $node, RenderState $state): string
    {
        $media = $state->resolvedMedia($state->bindingValue($node, 'asset'));
        if ($media === null) {
            return '<p role="status">Audio unavailable</p>';
        }
        $flags = (Properties::property($node, 'controls') === false ? '' : ' controls')
            . (Properties::property($node, 'autoplay') === true ? ' autoplay' : '');
        $transcript = Properties::stringValue($state->bindingValue($node, 'transcript'));

        return '<audio data-studio-part="media" src="' . SafeMarkup::escapeAttribute($media->src) . '"'
            . $flags . '></audio>'
            . ($transcript === ''
                ? ''
                : '<details><summary>Transcript</summary><p>' . SafeMarkup::escapeHtml($transcript)
                    . '</p></details>');
    }

    private function attachment(\stdClass $node, RenderState $state): string
    {
        $media = $state->resolvedMedia($state->bindingValue($node, 'asset'));
        if ($media === null) {
            return '<p role="status">Attachment unavailable</p>';
        }
        $label = Properties::stringValueOr($state->bindingValue($node, 'label'), 'Download attachment');

        return '<a data-studio-part="action" href="' . SafeMarkup::escapeAttribute($media->src) . '"'
            . (Properties::property($node, 'download') === false ? '' : ' download') . '>'
            . SafeMarkup::escapeHtml($label) . '</a>';
    }

    private function drawing(\stdClass $node, RenderState $state): string
    {
        try {
            $value = ProductionValues::parseDrawingDocument($state->bindingValue($node, 'drawing'));
        } catch (\Throwable) {
            return '<p role="status">Drawing unavailable</p>';
        }
        $strokes = '';
        foreach ($value->strokes as $stroke) {
            $points = [];
            foreach ($stroke->points as $point) {
                $points[] = SafeMarkup::number($point->x) . ',' . SafeMarkup::number($point->y);
            }
            $strokes .= '<polyline fill="none" stroke="'
                . (str_starts_with($stroke->color, '#') ? $stroke->color : 'currentColor')
                . '" stroke-width="' . SafeMarkup::number($stroke->width)
                . '" points="' . implode(' ', $points) . '"></polyline>';
        }

        return '<svg data-studio-part="media" viewBox="0 0 ' . $value->width . ' ' . $value->height
            . '" role="img" aria-label="' . SafeMarkup::escapeAttribute($value->alt)
            . '" xmlns="http://www.w3.org/2000/svg">' . $strokes . '</svg>';
    }
}
