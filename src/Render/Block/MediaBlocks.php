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

/**
 * Renders the media family to native `figure`/`img`, `video`, `audio`,
 * anchor, and inline-SVG elements.
 *
 * Every asset reference resolves through the host resolver and then the
 * closed media URL allowlist (https and site-relative only; blob URLs only
 * under the host's explicit authority and never for executable media types).
 * An unresolvable or refused asset renders the block's labeled
 * `role="status"` unavailable fallback — the stored value never leaks into
 * the output.
 *
 * @since   0.1.0
 */
final class MediaBlocks implements BlockRenderer
{
    /**
     * The six media types this renderer serves: image, gallery, video,
     * audio, attachment, and drawing.
     *
     * @return  list<string>  The block type identifiers, in catalog order.
     *
     * @since   0.1.0
     */
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

    /**
     * Dispatches one media node to its type-specific renderer.
     *
     * @param   \stdClass    $node   The block node; its type must be one the
     *                               renderer lists, or the match refuses it.
     * @param   string       $scope  The node's unique scope token, used by the
     *                               gallery for its column custom property.
     * @param   RenderState  $state  Engine services and per-render accumulators.
     *
     * @return  string  The node's inner semantic HTML, every dynamic value
     *                  escaped, or the block's unavailable fallback.
     *
     * @throws  \UnhandledMatchError  When the node's type is not one this
     *                                renderer declared in {@see types()}.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders an image to a `figure` holding an `img` with the vetted URL,
     * escaped alternative text, and an optional escaped caption.
     *
     * An asset the host cannot resolve, or whose URL the media allowlist
     * refuses, renders `<p role="status">Image unavailable</p>` instead.
     * Loading is lazy unless the `loading` property is exactly 'eager', and
     * the resolved intrinsic dimensions are emitted when the host provided
     * them.
     *
     * @param   \stdClass    $node   The image node.
     * @param   RenderState  $state  Resolves and vets the asset binding.
     *
     * @return  string  The `figure` markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a gallery to a labelled `section` of `figure`s, dropping every
     * item the host cannot resolve or the media allowlist refuses.
     *
     * The no-JavaScript baseline is the full list of images in document
     * flow; the slideshow presentation and the lightbox are progressive
     * behaviours requested from the enhancement runtime by name — 'slideshow'
     * (with the autoplay flag) when `presentation` is 'slideshow', and
     * 'lightbox' when `lightbox` is `true` and at least one item survived
     * vetting. Lightbox items additionally wrap each image in a plain anchor
     * to the vetted asset URL, so the fallback is a normal link. The column
     * count (1–12, fallback 4) is scoped as a CSS custom property, and the
     * slideshow's Previous/Next buttons are inert `type="button"` controls
     * until the enhancement wires them.
     *
     * @param   \stdClass    $node   The gallery node.
     * @param   string       $scope  Keys the column custom property and the
     *                               enhancement requests to this node.
     * @param   RenderState  $state  Resolves media, receives CSS and
     *                               enhancement requests.
     *
     * @return  string  The gallery `section` markup (empty of figures when
     *                  no item survived vetting).
     *
     * @since   0.1.0
     */
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
        $autoplay = Properties::property($node, 'autoplay') === true;
        $columns = Properties::integerProperty(Properties::property($node, 'columns'), 1, 12, 4);
        $state->css[] = '[data-studio-scope=' . $scope . ']{--studio-gallery-columns:' . $columns . '}';
        if ($presentation === 'slideshow' && $media !== []) {
            $state->enhance('slideshow', $node, $scope, ['autoplay' => $autoplay]);
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

        return '<section data-studio-gallery="' . $presentation . '"'
            . ($presentation === 'slideshow'
                ? ' data-studio-slideshow-autoplay="' . ($autoplay ? 'true' : 'false') . '"'
                : '')
            . ' aria-label="Media gallery">'
            . '<div data-studio-part="content">' . $items . '</div>'
            . ($presentation === 'slideshow' && $media !== []
                ? '<p><button type="button" data-studio-slide-previous>Previous</button>'
                    . '<button type="button" data-studio-slide-next>Next</button></p>'
                : '')
            . '</section>';
    }

    /**
     * Renders a video to a native `video` element with vetted source and
     * poster URLs and escaped caption text as its element fallback content.
     *
     * An asset the host cannot resolve, or whose URL the media allowlist
     * refuses, renders `<p role="status">Video unavailable</p>`; a refused
     * poster is simply omitted while the video still renders. Controls are
     * on unless `controls` is stored `false`; autoplay and muted are strict
     * opt-ins (`true` only). No player script is requested — playback is the
     * browser's own.
     *
     * @param   \stdClass    $node   The video node.
     * @param   RenderState  $state  Resolves and vets the asset and poster
     *                               bindings.
     *
     * @return  string  The `video` markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders audio to a native `audio` element followed by an optional
     * transcript inside a `details` disclosure.
     *
     * An asset the host cannot resolve, or whose URL the media allowlist
     * refuses, renders `<p role="status">Audio unavailable</p>`. Controls
     * are on unless `controls` is stored `false`; autoplay is a strict
     * opt-in. A non-empty bound transcript renders escaped inside
     * `details/summary`, which works without JavaScript.
     *
     * @param   \stdClass    $node   The audio node.
     * @param   RenderState  $state  Resolves and vets the asset binding and
     *                               resolves the transcript.
     *
     * @return  string  The `audio` markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders an attachment to a plain anchor with the vetted asset URL and
     * an escaped label.
     *
     * An asset the host cannot resolve, or whose URL the media allowlist
     * refuses, renders `<p role="status">Attachment unavailable</p>`. The
     * `download` attribute is present unless the property is stored `false`,
     * and the label falls back to "Download attachment" when the binding is
     * missing or falsy.
     *
     * @param   \stdClass    $node   The attachment node.
     * @param   RenderState  $state  Resolves and vets the asset binding and
     *                               resolves the label.
     *
     * @return  string  The anchor markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a drawing to inline SVG polylines built only from the typed,
     * bounded drawing document.
     *
     * The bound value must pass the canonical drawing parse (bounded strokes,
     * points, widths, and coordinates); any refusal renders
     * `<p role="status">Drawing unavailable</p>` without disclosing why.
     * Stroke colors are emitted verbatim only when they carry the parser's
     * `#`-prefixed form, otherwise `currentColor`, and every number renders
     * through the deterministic number formatter. The `svg` carries
     * `role="img"` with the document's required alternative text escaped
     * into `aria-label`.
     *
     * @param   \stdClass    $node   The drawing node.
     * @param   RenderState  $state  Resolves the drawing binding.
     *
     * @return  string  The `svg` markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
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
