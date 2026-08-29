<?php

/**
 * The data-display family: chart, table, money, math, diagram, embed,
 * content reference, content collection, and description lists.
 *
 * Typed values render only after canonical parsing; a refused value becomes
 * the reference's labeled fallback (the chart's accessible data table stays
 * the no-JavaScript baseline even when the chart enhancement runs).
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
use Kumwe\Producer\Render\ResolvedResource;
use Kumwe\Producer\Render\RichText;
use Kumwe\Producer\Render\SafeMarkup;

/**
 * Renders the data-display family from typed, canonically parsed values
 * only.
 *
 * Chart, table, money, and drawing-adjacent values must pass their bounded
 * canonical parse before a byte is emitted; a refused value renders the
 * block's labeled `role="status"` fallback and discloses nothing. Resource
 * references vet their URLs through the closed allowlist at parse time, and
 * the chart, math, diagram, and slideshow behaviours are requested from the
 * enhancement runtime on top of an accessible no-JavaScript baseline.
 *
 * @since   0.1.0
 */
final class DataBlocks implements BlockRenderer
{
    /**
     * The ten data-display types this renderer serves: chart, table, money,
     * math, diagram, embed, content reference, content collection,
     * description list, and description item.
     *
     * @return  list<string>  The block type identifiers, in catalog order.
     *
     * @since   0.1.0
     */
    public function types(): array
    {
        return [
            BlockTypes::CHART,
            BlockTypes::TABLE,
            BlockTypes::MONEY,
            BlockTypes::MATH,
            BlockTypes::DIAGRAM,
            BlockTypes::EMBED,
            BlockTypes::CONTENT_REFERENCE,
            BlockTypes::CONTENT_COLLECTION,
            BlockTypes::DESCRIPTION_LIST,
            BlockTypes::DESCRIPTION_ITEM,
        ];
    }

    /**
     * Dispatches one data-display node to its type-specific renderer.
     *
     * @param   \stdClass    $node   The block node; its type must be one the
     *                               renderer lists, or the match refuses it.
     * @param   string       $scope  The node's unique scope token, used to key
     *                               enhancement requests.
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
            BlockTypes::CHART => $this->chart($node, $scope, $state),
            BlockTypes::TABLE => $this->table($node, $state),
            BlockTypes::MONEY => $this->money($node, $state),
            BlockTypes::MATH => $this->math($node, $scope, $state),
            BlockTypes::DIAGRAM => $this->diagram($node, $scope, $state),
            BlockTypes::EMBED => $this->embed($node, $state),
            BlockTypes::CONTENT_REFERENCE => $this->contentReference($node, $state),
            BlockTypes::CONTENT_COLLECTION => $this->contentCollection($node, $scope, $state),
            BlockTypes::DESCRIPTION_LIST => $this->descriptionList($node, $state),
            BlockTypes::DESCRIPTION_ITEM => $this->descriptionItem($node, $state),
        };
    }

    /**
     * Renders a chart as an accessible data table plus an empty visual mount
     * the 'chart' enhancement draws into.
     *
     * The bound value must pass the bounded canonical chart parse (closed
     * type set, 1–20 datasets, at most 200 values each, finite numbers
     * only); a refused value renders
     * `<p role="status">Chart data unavailable</p>`. The table — escaped
     * labels, dataset row headers, and deterministically formatted numbers —
     * is the no-JavaScript baseline and stays in the document even when the
     * enhancement (requested with the parsed spec as its detail) draws the
     * `aria-hidden` visual.
     *
     * @param   \stdClass    $node   The chart node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Resolves the binding and receives the
     *                               enhancement request.
     *
     * @return  string  The heading, visual mount, and data table markup, or
     *                  the unavailable fallback.
     *
     * @since   0.1.0
     */
    private function chart(\stdClass $node, string $scope, RenderState $state): string
    {
        try {
            $spec = ProductionValues::parseChartSpec($state->bindingValue($node, 'chart'));
        } catch (\Throwable) {
            return '<p role="status">Chart data unavailable</p>';
        }
        $state->enhance('chart', $node, $scope, ['spec' => $spec]);
        $head = '';
        foreach ($spec->labels as $label) {
            $head .= '<th scope="col">' . SafeMarkup::escapeHtml($label) . '</th>';
        }
        $rows = '';
        foreach ($spec->datasets as $dataset) {
            $cells = '';
            foreach ($dataset->values as $value) {
                $cells .= '<td>' . SafeMarkup::escapeHtml(SafeMarkup::number($value)) . '</td>';
            }
            $rows .= '<tr><th scope="row">' . SafeMarkup::escapeHtml($dataset->label) . '</th>' . $cells . '</tr>';
        }
        $title = $spec->title ?? null;
        if (!is_string($title)) {
            $title = null;
        }

        return ($title === null
                ? ''
                : '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>')
            . '<div data-studio-chart-visual aria-hidden="true"></div>'
            . '<table data-studio-chart-table><thead><tr><th scope="col">Series</th>' . $head
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Renders a data table to a native `table` with column header scope,
     * an optional caption, and every cell escaped.
     *
     * The bound value must pass the bounded canonical table parse (at least
     * one column, at most 1000 rows, one cell per column); a refused value
     * renders `<p role="status">Table data unavailable</p>` and discloses
     * nothing. No enhancement is requested — the table is complete as
     * rendered.
     *
     * @param   \stdClass    $node   The table node.
     * @param   RenderState  $state  Resolves the table binding.
     *
     * @return  string  The `table` markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
    private function table(\stdClass $node, RenderState $state): string
    {
        try {
            $value = ProductionValues::parseTableDocument($state->bindingValue($node, 'table'));
        } catch (\Throwable) {
            return '<p role="status">Table data unavailable</p>';
        }
        $headings = '';
        foreach ($value->columns as $column) {
            $headings .= '<th scope="col">' . SafeMarkup::escapeHtml($column) . '</th>';
        }
        $rows = '';
        foreach ($value->rows as $row) {
            $cells = '';
            foreach ($row as $cell) {
                $cells .= '<td>' . SafeMarkup::escapeHtml($cell) . '</td>';
            }
            $rows .= '<tr>' . $cells . '</tr>';
        }
        $caption = $value->caption ?? null;
        if (!is_string($caption)) {
            $caption = null;
        }

        return '<table data-studio-table>'
            . ($caption === null ? '' : '<caption>' . SafeMarkup::escapeHtml($caption) . '</caption>')
            . '<thead><tr>' . $headings . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * Renders a monetary amount to a `data` element pairing the
     * machine-readable value with its human-readable text.
     *
     * The bound value must pass the canonical money parse (a canonical
     * decimal amount string with at most six places and an uppercase
     * three-letter currency code); a refused value renders
     * `<span role="status">Amount unavailable</span>`. No locale formatting
     * is applied — the canonical strings render as stored, keeping the
     * output bytes deterministic.
     *
     * @param   \stdClass    $node   The money node.
     * @param   RenderState  $state  Resolves the amount binding.
     *
     * @return  string  The `data` element markup, or the unavailable
     *                  fallback.
     *
     * @since   0.1.0
     */
    private function money(\stdClass $node, RenderState $state): string
    {
        try {
            $value = ProductionValues::parseMoneyValue($state->bindingValue($node, 'amount'));
        } catch (\Throwable) {
            return '<span role="status">Amount unavailable</span>';
        }

        return '<data value="' . SafeMarkup::escapeAttribute($value->currency . ' ' . $value->amount) . '">'
            . SafeMarkup::escapeHtml($value->amount . ' ' . $value->currency) . '</data>';
    }

    /**
     * Renders math as its escaped source in a `code` element and requests
     * the 'math' typesetting enhancement.
     *
     * The escaped source is the no-JavaScript baseline; the enhancement
     * request carries the raw source and the display-mode flag (block
     * display unless `display-mode` is stored `false`) so the runtime can
     * typeset in place. The source itself is never interpreted here.
     *
     * @param   \stdClass    $node   The math node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Resolves the binding and receives the
     *                               enhancement request.
     *
     * @return  string  The `code` element holding the escaped source.
     *
     * @since   0.1.0
     */
    private function math(\stdClass $node, string $scope, RenderState $state): string
    {
        $source = Properties::stringValue($state->bindingValue($node, 'source'));
        $displayMode = Properties::property($node, 'display-mode') !== false;
        $state->enhance('math', $node, $scope, ['displayMode' => $displayMode, 'source' => $source]);

        return '<code data-studio-math-source>' . SafeMarkup::escapeHtml($source) . '</code>';
    }

    /**
     * Renders a diagram as its escaped source in `pre > code` and requests
     * the 'diagram' enhancement.
     *
     * The escaped source text is the no-JavaScript baseline; the enhancement
     * request carries the raw source so the runtime can replace it with a
     * drawn diagram. The source is never interpreted here.
     *
     * @param   \stdClass    $node   The diagram node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Resolves the binding and receives the
     *                               enhancement request.
     *
     * @return  string  The `pre > code` markup holding the escaped source.
     *
     * @since   0.1.0
     */
    private function diagram(\stdClass $node, string $scope, RenderState $state): string
    {
        $source = Properties::stringValue($state->bindingValue($node, 'source'));
        $state->enhance('diagram', $node, $scope, ['source' => $source]);

        return '<pre data-studio-diagram-source><code>' . SafeMarkup::escapeHtml($source) . '</code></pre>';
    }

    /**
     * Renders an embedded resource as a plain labelled link with its
     * summary — never as an iframe or third-party markup.
     *
     * The bound value must parse as a resolved resource (string id and
     * label); a value that does not renders
     * `<p role="status">Embedded resource unavailable</p>`. The resource's
     * URL was vetted through the closed allowlist at parse time, so a
     * refused URL renders the label as unlinked text.
     *
     * @param   \stdClass    $node   The embed node.
     * @param   RenderState  $state  Resolves the resource binding.
     *
     * @return  string  The resource markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
    private function embed(\stdClass $node, RenderState $state): string
    {
        $resource = ResolvedResource::parse($state->bindingValue($node, 'resource'));

        return $resource === null
            ? '<p role="status">Embedded resource unavailable</p>'
            : self::renderResource($resource, true);
    }

    /**
     * Renders a reference to one content item as a labelled link, with the
     * summary shown unless the node asks for title-only presentation.
     *
     * The bound value must parse as a resolved resource; a value that does
     * not renders `<p role="status">Content unavailable</p>`. The URL was
     * vetted at parse time (a refused URL renders unlinked text), and the
     * summary is included except when `presentation` is exactly 'title'.
     *
     * @param   \stdClass    $node   The content-reference node.
     * @param   RenderState  $state  Resolves the item binding.
     *
     * @return  string  The resource markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
    private function contentReference(\stdClass $node, RenderState $state): string
    {
        $resource = ResolvedResource::parse($state->bindingValue($node, 'item'));

        return $resource === null
            ? '<p role="status">Content unavailable</p>'
            : self::renderResource($resource, Properties::property($node, 'presentation') !== 'title');
    }

    /**
     * Renders a collection of content references as `article`s in a
     * presentation-attributed wrapper, dropping every item that fails the
     * resource parse.
     *
     * Unparseable items vanish silently; survivors are capped by the `limit`
     * property clamped to 1–100 (fallback 12). Presentation is coerced into
     * cards/grid/list/slideshow (fallback cards); the slideshow variant
     * indexes each article as a slide and requests the 'slideshow'
     * enhancement without autoplay, while the full list in document flow
     * stays the no-JavaScript baseline. Item URLs were vetted at parse
     * time.
     *
     * @param   \stdClass    $node   The content-collection node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Resolves the items binding and receives
     *                               the enhancement request.
     *
     * @return  string  The collection `div` markup (empty when no item
     *                  survived parsing).
     *
     * @since   0.1.0
     */
    private function contentCollection(\stdClass $node, string $scope, RenderState $state): string
    {
        $value = $state->bindingValue($node, 'items');
        $resources = [];
        foreach (is_array($value) ? $value : [] as $candidate) {
            $resource = ResolvedResource::parse($candidate);
            if ($resource !== null) {
                $resources[] = $resource;
            }
        }
        $limit = Properties::integerProperty(Properties::property($node, 'limit'), 1, 100, 12);
        $resources = array_slice($resources, 0, $limit);
        $presentation = Properties::enumProperty(
            Properties::property($node, 'presentation'),
            ['cards', 'grid', 'list', 'slideshow'],
            'cards'
        );
        if ($presentation === 'slideshow') {
            $state->enhance('slideshow', $node, $scope, ['autoplay' => false]);
        }
        $items = '';
        foreach ($resources as $index => $resource) {
            $items .= '<article' . ($presentation === 'slideshow' ? ' data-studio-slide="' . $index . '"' : '')
                . '>' . self::renderResource($resource, true) . '</article>';
        }

        return '<div data-studio-collection="' . $presentation . '" data-studio-part="content">'
            . $items . '</div>';
    }

    /**
     * Renders a description list to a `dl` of its `items` slot, preceded by
     * an escaped `h3` heading only when a title is bound.
     *
     * The children are expected to be description items rendering their own
     * `dt`/`dd` pairs; this block adds no wrapper around them beyond the
     * `dl` itself.
     *
     * @param   \stdClass    $node   The description-list node.
     * @param   RenderState  $state  Resolves the title binding and renders
     *                               the items slot.
     *
     * @return  string  The optional heading and the `dl` markup.
     *
     * @since   0.1.0
     */
    private function descriptionList(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));
        $items = $state->renderChildren($node, 'items');

        return ($title === ''
                ? ''
                : '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>')
            . '<dl>' . $items . '</dl>';
    }

    /**
     * Renders one description item to a `dt`/`dd` pair: the escaped term and
     * a rich-text description body.
     *
     * The description renders through the validating rich-text grammar; a
     * refused document falls back to the bound value escaped as plain text,
     * so hostile structure degrades to inert text rather than markup.
     *
     * @param   \stdClass    $node   The description-item node.
     * @param   RenderState  $state  Resolves the term and description
     *                               bindings.
     *
     * @return  string  The `dt` and `dd` markup.
     *
     * @since   0.1.0
     */
    private function descriptionItem(\stdClass $node, RenderState $state): string
    {
        $term = Properties::stringValue($state->bindingValue($node, 'term'));
        $description = $state->bindingValue($node, 'description');
        try {
            $body = RichText::render(RichText::parse($description));
        } catch (\Throwable) {
            $body = SafeMarkup::escapeHtml(Properties::stringValue($description));
        }

        return '<dt>' . SafeMarkup::escapeHtml($term) . '</dt><dd>' . $body . '</dd>';
    }

    /**
     * Renders one parsed resource: the escaped label, the escaped summary
     * when requested and present, wrapped in an anchor only when the
     * resource kept a vetted URL.
     *
     * A resource whose URL the allowlist refused at parse time carries none,
     * so it renders here as plain unlinked content — the refused URL is
     * unreachable by construction.
     *
     * @param   ResolvedResource  $resource  The parsed, URL-vetted resource.
     * @param   bool              $summary   Whether to include the summary
     *                                       paragraph when the resource has
     *                                       one.
     *
     * @return  string  The resource's inner markup, linked or plain.
     *
     * @since   0.1.0
     */
    private static function renderResource(ResolvedResource $resource, bool $summary): string
    {
        $content = '<span>' . SafeMarkup::escapeHtml($resource->label) . '</span>'
            . ($summary && $resource->summary !== null
                ? '<p>' . SafeMarkup::escapeHtml($resource->summary) . '</p>'
                : '');

        return $resource->url === null
            ? $content
            : '<a href="' . SafeMarkup::escapeAttribute($resource->url) . '">' . $content . '</a>';
    }
}
