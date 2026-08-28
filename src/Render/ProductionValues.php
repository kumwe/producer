<?php

/**
 * Typed production value parsing, ported from the Studio core contract.
 *
 * Chart specs, tables, money, drawings, and presentation intent arrive as
 * plain data and are refused unless they match the closed canonical shape:
 * exact member sets, bounded sizes, closed vocabularies. A refusal makes the
 * owning block render its labeled semantic fallback — the typed data never
 * leaks into markup.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class ProductionValues
{
    private const CHART_TYPES = ['bar', 'doughnut', 'line', 'pie'];

    private const MONEY_AMOUNT = '/^-?(?:0|[1-9][0-9]{0,17})(?:\.[0-9]{1,6})?$/';

    private const CURRENCY = '/^[A-Z]{3}$/';

    private const DRAWING_COLOR = '%^(?:#[0-9A-Fa-f]{6}|[a-z][a-z0-9-]{0,62}/[a-z][a-z0-9-]{0,62})$%';

    private function __construct()
    {
    }

    /**
     * Parse one canonical chart spec, refusing library-specific
     * configuration. Returns {type, labels, datasets: list of {label,
     * values}, title?}.
     */
    public static function parseChartSpec(mixed $value): \stdClass
    {
        $record = self::exactRecord($value, ['datasets', 'labels', 'title', 'type'], 'Chart');
        $type = $record->type ?? null;
        if (!is_string($type) || !in_array($type, self::CHART_TYPES, true)) {
            throw new RenderException('Chart type must be bar, doughnut, line, or pie.');
        }
        $labels = self::stringArray($record->labels ?? null, 200, 500, 'Chart labels');
        $rawDatasets = $record->datasets ?? null;
        if (!is_array($rawDatasets) || count($rawDatasets) < 1 || count($rawDatasets) > 20) {
            throw new RenderException('Chart datasets must contain between 1 and 20 datasets.');
        }
        $datasets = [];
        foreach ($rawDatasets as $index => $candidate) {
            $dataset = self::exactRecord($candidate, ['label', 'values'], "Chart dataset {$index}");
            $label = $dataset->label ?? null;
            if (!is_string($label) || mb_strlen($label, 'UTF-8') > 500) {
                throw new RenderException("Chart dataset {$index} label must be a bounded string.");
            }
            $rawValues = $dataset->values ?? null;
            if (!is_array($rawValues) || count($rawValues) > 200) {
                throw new RenderException("Chart dataset {$index} values exceed the 200-value limit.");
            }
            $values = [];
            foreach ($rawValues as $item) {
                if (!self::isFiniteNumber($item) || abs((float) $item) > 1e15) {
                    throw new RenderException("Chart dataset {$index} contains an invalid finite number.");
                }
                $values[] = $item;
            }
            if (count($values) !== count($labels)) {
                throw new RenderException("Chart dataset {$index} must have one value per label.");
            }
            $datasets[] = (object) ['label' => $label, 'values' => $values];
        }
        $result = (object) ['datasets' => $datasets, 'labels' => $labels, 'type' => $type];
        if (property_exists($record, 'title')) {
            if (!is_string($record->title) || mb_strlen($record->title, 'UTF-8') > 500) {
                throw new RenderException('Chart title must be a bounded string.');
            }
            $result->title = $record->title;
        }

        return $result;
    }

    /**
     * Parse a bounded, text-only table document requiring one cell per
     * declared column. Returns {caption?, columns, rows}.
     */
    public static function parseTableDocument(mixed $value): \stdClass
    {
        $record = self::exactRecord($value, ['caption', 'columns', 'rows'], 'Table');
        $columns = self::stringArray($record->columns ?? null, 50, 500, 'Table columns');
        if ($columns === []) {
            throw new RenderException('Table must declare at least one column.');
        }
        $rawRows = $record->rows ?? null;
        if (!is_array($rawRows) || count($rawRows) > 1000) {
            throw new RenderException('Table rows exceed the 1000-row limit.');
        }
        $rows = [];
        foreach ($rawRows as $index => $candidate) {
            $cells = self::stringArray($candidate, 50, 5000, "Table row {$index}");
            if (count($cells) !== count($columns)) {
                throw new RenderException("Table row {$index} must contain one cell per column.");
            }
            $rows[] = $cells;
        }
        $result = (object) ['columns' => $columns, 'rows' => $rows];
        if (property_exists($record, 'caption')) {
            if (!is_string($record->caption) || mb_strlen($record->caption, 'UTF-8') > 500) {
                throw new RenderException('Table caption must be a bounded string.');
            }
            $result->caption = $record->caption;
        }

        return $result;
    }

    /**
     * Parse exact decimal money without converting through a binary float.
     * Returns {amount, currency}.
     */
    public static function parseMoneyValue(mixed $value): \stdClass
    {
        $record = self::exactRecord($value, ['amount', 'currency'], 'Money');
        $amount = $record->amount ?? null;
        if (!is_string($amount) || preg_match(self::MONEY_AMOUNT, $amount) !== 1) {
            throw new RenderException('Money amount must be a canonical decimal string with at most six places.');
        }
        $currency = $record->currency ?? null;
        if (!is_string($currency) || preg_match(self::CURRENCY, $currency) !== 1) {
            throw new RenderException('Money currency must be an uppercase ISO-style three-letter code.');
        }

        return (object) ['amount' => $amount, 'currency' => $currency];
    }

    /**
     * Parse bounded vector strokes; SVG markup, data URLs, and canvas
     * commands have no representation here. Returns {alt, height, strokes,
     * width}.
     */
    public static function parseDrawingDocument(mixed $value): \stdClass
    {
        $record = self::exactRecord($value, ['alt', 'height', 'strokes', 'width'], 'Drawing');
        $width = self::integer($record->width ?? null, 1, 4096, 'Drawing width');
        $height = self::integer($record->height ?? null, 1, 4096, 'Drawing height');
        $alt = $record->alt ?? null;
        if (!is_string($alt) || mb_strlen($alt, 'UTF-8') < 1 || mb_strlen($alt, 'UTF-8') > 5000) {
            throw new RenderException('Drawing alternative text must contain between 1 and 5000 characters.');
        }
        $rawStrokes = $record->strokes ?? null;
        if (!is_array($rawStrokes) || count($rawStrokes) > 5000) {
            throw new RenderException('Drawing strokes exceed the 5000-stroke limit.');
        }
        $strokes = [];
        foreach ($rawStrokes as $strokeIndex => $candidate) {
            $stroke = self::exactRecord($candidate, ['color', 'points', 'width'], "Drawing stroke {$strokeIndex}");
            $color = $stroke->color ?? null;
            if (!is_string($color) || preg_match(self::DRAWING_COLOR, $color) !== 1) {
                throw new RenderException("Drawing stroke {$strokeIndex} uses an invalid color token.");
            }
            $strokeWidth = $stroke->width ?? null;
            if (!self::isFiniteNumber($strokeWidth) || $strokeWidth < 0.25 || $strokeWidth > 64) {
                throw new RenderException("Drawing stroke {$strokeIndex} width is outside 0.25 through 64.");
            }
            $rawPoints = $stroke->points ?? null;
            if (!is_array($rawPoints) || count($rawPoints) < 1 || count($rawPoints) > 10000) {
                throw new RenderException("Drawing stroke {$strokeIndex} must contain 1 through 10000 points.");
            }
            $points = [];
            foreach ($rawPoints as $pointIndex => $candidatePoint) {
                $point = self::exactRecord($candidatePoint, ['x', 'y'], "Drawing point {$pointIndex}");
                $points[] = (object) [
                    'x' => self::coordinate($point->x ?? null, $width, "Drawing point {$pointIndex} x"),
                    'y' => self::coordinate($point->y ?? null, $height, "Drawing point {$pointIndex} y"),
                ];
            }
            $strokes[] = (object) ['color' => $color, 'points' => $points, 'width' => $strokeWidth];
        }

        return (object) ['alt' => $alt, 'height' => $height, 'strokes' => $strokes, 'width' => $width];
    }

    /**
     * Parse closed presentation intent: no selectors, no declarations, no
     * measurements — only the contract's enumerated design choices. Members
     * absent from the input stay absent from the result.
     */
    public static function parsePresentationIntent(mixed $value): \stdClass
    {
        $record = self::exactRecord(
            $value,
            [
                'align', 'animation', 'height', 'inverse', 'margin', 'marker',
                'padding', 'position', 'print', 'scrolling', 'visibility', 'width',
            ],
            'Presentation intent'
        );
        $intent = new \stdClass();
        self::optionalEnumMember($record, $intent, 'align', ['center', 'end', 'start', 'stretch']);
        self::optionalEnumMember($record, $intent, 'animation', ['fade', 'none', 'parallax', 'scale', 'slide']);
        self::optionalEnumMember($record, $intent, 'height', ['auto', 'content', 'full', 'viewport']);
        if (property_exists($record, 'inverse')) {
            if (!is_bool($record->inverse)) {
                throw new RenderException('inverse must be a boolean.');
            }
            $intent->inverse = $record->inverse;
        }
        self::optionalEnumMember($record, $intent, 'margin', ['comfortable', 'compact', 'none', 'spacious']);
        self::optionalEnumMember($record, $intent, 'marker', ['check', 'decimal', 'disc', 'none']);
        self::optionalEnumMember($record, $intent, 'padding', ['comfortable', 'compact', 'none', 'spacious']);
        self::optionalEnumMember($record, $intent, 'position', ['flow', 'relative', 'sticky']);
        self::optionalEnumMember($record, $intent, 'print', ['hide', 'only', 'show']);
        self::optionalEnumMember($record, $intent, 'scrolling', ['auto', 'clip', 'snap', 'visible']);
        self::optionalEnumMember($record, $intent, 'width', ['auto', 'content', 'full']);
        if (property_exists($record, 'visibility')) {
            $visibilityRecord = self::exactRecord(
                $record->visibility,
                ['compact', 'expanded', 'medium'],
                'Presentation visibility'
            );
            $visibility = new \stdClass();
            self::optionalEnumMember($visibilityRecord, $visibility, 'compact', ['hidden', 'visible']);
            self::optionalEnumMember($visibilityRecord, $visibility, 'expanded', ['hidden', 'visible']);
            self::optionalEnumMember($visibilityRecord, $visibility, 'medium', ['hidden', 'visible']);
            $intent->visibility = $visibility;
        }

        return $intent;
    }

    /**
     * @param list<string> $keys
     */
    private static function exactRecord(mixed $value, array $keys, string $name): \stdClass
    {
        if (!$value instanceof \stdClass) {
            throw new RenderException("{$name} must be a plain JSON object.");
        }
        foreach (array_keys(get_object_vars($value)) as $key) {
            if (!in_array((string) $key, $keys, true)) {
                throw new RenderException("{$name} contains unknown member {$key}.");
            }
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringArray(mixed $value, int $maximumItems, int $maximumLength, string $name): array
    {
        if (!is_array($value) || count($value) > $maximumItems) {
            throw new RenderException("{$name} exceed their item limit.");
        }
        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || mb_strlen($item, 'UTF-8') > $maximumLength) {
                throw new RenderException("{$name} must be bounded strings.");
            }
            $items[] = $item;
        }

        return $items;
    }

    private static function integer(mixed $value, int $minimum, int $maximum, string $name): int
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new RenderException("{$name} must be an integer from {$minimum} through {$maximum}.");
        }

        return $value;
    }

    private static function coordinate(mixed $value, int $maximum, string $name): int|float
    {
        if (!self::isFiniteNumber($value) || $value < 0 || $value > $maximum) {
            throw new RenderException("{$name} must be a finite coordinate inside the drawing bounds.");
        }

        return $value;
    }

    /**
     * @param list<string> $values
     */
    private static function optionalEnumMember(\stdClass $record, \stdClass $target, string $name, array $values): void
    {
        if (!property_exists($record, $name)) {
            return;
        }
        $value = $record->{$name};
        if (!is_string($value) || !in_array($value, $values, true)) {
            throw new RenderException("{$name} is not an allowed presentation value.");
        }
        $target->{$name} = $value;
    }

    /**
     * @phpstan-assert-if-true int|float $value
     */
    private static function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }
}
