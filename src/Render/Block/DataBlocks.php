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

final class DataBlocks implements BlockRenderer
{
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

        return ($title === null
                ? ''
                : '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>')
            . '<div data-studio-chart-visual aria-hidden="true"></div>'
            . '<table data-studio-chart-table><thead><tr><th scope="col">Series</th>' . $head
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

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

        return '<table data-studio-table>'
            . ($caption === null ? '' : '<caption>' . SafeMarkup::escapeHtml($caption) . '</caption>')
            . '<thead><tr>' . $headings . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

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

    private function math(\stdClass $node, string $scope, RenderState $state): string
    {
        $source = Properties::stringValue($state->bindingValue($node, 'source'));
        $displayMode = Properties::property($node, 'display-mode') !== false;
        $state->enhance('math', $node, $scope, ['displayMode' => $displayMode, 'source' => $source]);

        return '<code data-studio-math-source>' . SafeMarkup::escapeHtml($source) . '</code>';
    }

    private function diagram(\stdClass $node, string $scope, RenderState $state): string
    {
        $source = Properties::stringValue($state->bindingValue($node, 'source'));
        $state->enhance('diagram', $node, $scope, ['source' => $source]);

        return '<pre data-studio-diagram-source><code>' . SafeMarkup::escapeHtml($source) . '</code></pre>';
    }

    private function embed(\stdClass $node, RenderState $state): string
    {
        $resource = ResolvedResource::parse($state->bindingValue($node, 'resource'));

        return $resource === null
            ? '<p role="status">Embedded resource unavailable</p>'
            : self::renderResource($resource, true);
    }

    private function contentReference(\stdClass $node, RenderState $state): string
    {
        $resource = ResolvedResource::parse($state->bindingValue($node, 'item'));

        return $resource === null
            ? '<p role="status">Content unavailable</p>'
            : self::renderResource($resource, Properties::property($node, 'presentation') !== 'title');
    }

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

    private function descriptionList(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));
        $items = $state->renderChildren($node, 'items');

        return ($title === ''
                ? ''
                : '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>')
            . '<dl>' . $items . '</dl>';
    }

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
