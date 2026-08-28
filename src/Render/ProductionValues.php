<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Typed production value parsing, ported from the Studio core contract.
 *
 * Chart specs, tables, money, drawings, and presentation intent arrive as
 * plain data and are refused unless they match the closed canonical shape:
 * exact member sets, bounded sizes, closed vocabularies. A refusal makes the
 * owning block render its labeled semantic fallback — the typed data never
 * leaks into markup.
 *
 * @since   0.1.0
 */
final class ProductionValues
{
    /**
     * The closed chart-type vocabulary; any other type refuses the spec.
     *
     * @since   0.1.0
     */
    private const CHART_TYPES = ['bar', 'doughnut', 'line', 'pie'];

    /**
     * The canonical money-amount grammar: an optionally negative integer
     * part of at most eighteen digits with no leading zero, and at most six
     * decimal places. Matched as a string so no binary float is involved.
     *
     * @since   0.1.0
     */
    private const MONEY_AMOUNT = '/^-?(?:0|[1-9][0-9]{0,17})(?:\.[0-9]{1,6})?$/';

    /**
     * The money-currency grammar: exactly three uppercase ASCII letters.
     *
     * @since   0.1.0
     */
    private const CURRENCY = '/^[A-Z]{3}$/';

    /**
     * The closed drawing-color grammar: a six-digit hex color or a bounded
     * namespaced token reference (`family/name`).
     *
     * @since   0.1.0
     */
    private const DRAWING_COLOR = '%^(?:#[0-9A-Fa-f]{6}|[a-z][a-z0-9-]{0,62}/[a-z][a-z0-9-]{0,62})$%';

    /**
     * Static parser; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Parse one canonical chart spec, refusing library-specific
     * configuration. Accepts at most 200 labels of at most 500 characters,
     * 1 through 20 datasets each carrying exactly one finite number (of
     * magnitude at most 1e15) per label, and an optional bounded title.
     *
     * @param   mixed  $value  The decoded chart spec candidate.
     * @return  \stdClass  {type, labels, datasets: list of {label, values},
     *     title?}.
     * @throws  RenderException  On an unknown member, a type outside the
     *     closed vocabulary, or any bound exceeded.
     * @since   0.1.0
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
     * declared column: 1 through 50 columns of at most 500 characters, at
     * most 1000 rows of cells of at most 5000 characters, and an optional
     * bounded caption. Cells are strings only — no markup, no nesting.
     *
     * @param   mixed  $value  The decoded table document candidate.
     * @return  \stdClass  {caption?, columns, rows}.
     * @throws  RenderException  On an unknown member, a ragged row, or any
     *     bound exceeded.
     * @since   0.1.0
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
     * Parse exact decimal money without converting through a binary float:
     * the amount stays the canonical decimal string it arrived as (at most
     * eighteen integer digits, at most six decimal places), and the
     * currency must be an uppercase three-letter code.
     *
     * @param   mixed  $value  The decoded money value candidate.
     * @return  \stdClass  {amount, currency}.
     * @throws  RenderException  On an unknown member or a value outside
     *     either closed grammar.
     * @since   0.1.0
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
     * commands have no representation here. Dimensions are integers from 1
     * through 4096; the alternative text carries 1 through 5000 characters;
     * at most 5000 strokes, each with a closed-grammar color, a width from
     * 0.25 through 64, and 1 through 10000 finite points inside the
     * declared dimensions.
     *
     * @param   mixed  $value  The decoded drawing document candidate.
     * @return  \stdClass  {alt, height, strokes, width}.
     * @throws  RenderException  On an unknown member, an out-of-bounds
     *     coordinate, or any bound exceeded.
     * @since   0.1.0
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
     * absent from the input stay absent from the result, so the renderer
     * emits no attribute for them.
     *
     * @param   mixed  $value  The decoded design intent candidate.
     * @return  \stdClass  Only the members present in the input, each
     *     restricted to its closed vocabulary (visibility as a nested
     *     object of per-breakpoint choices).
     * @throws  RenderException  On an unknown member or a value outside its
     *     closed vocabulary.
     * @since   0.1.0
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
     * Require a plain decoded object whose members all come from the
     * declared key set — an unknown member is refused, never ignored.
     * Absent members are not required here; each caller checks its own.
     *
     * @param   mixed         $value  The candidate value.
     * @param   list<string>  $keys   The complete allowed member set.
     * @param   string        $name   Human label used in refusal messages.
     * @return  \stdClass  The same object, admitted.
     * @throws  RenderException  When the value is not a plain object or
     *     carries an unknown member.
     * @since   0.1.0
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
     * Require an array of bounded strings, enforcing the item limit before
     * inspecting any item so an oversized list cannot amplify work.
     *
     * @param   mixed   $value          The candidate value.
     * @param   int     $maximumItems   Most items allowed.
     * @param   int     $maximumLength  Most UTF-8 characters per item.
     * @param   string  $name           Human label used in refusal messages.
     * @return  list<string>  The admitted strings, order preserved.
     * @throws  RenderException  When the value is not an array, exceeds the
     *     item limit, or contains a non-string or overlong item.
     * @since   0.1.0
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

    /**
     * Require an in-range integer, accepting a JSON-decoded whole float
     * (e.g. 4.0) as the integer it denotes.
     *
     * @param   mixed   $value    The candidate value.
     * @param   int     $minimum  Least allowed value, inclusive.
     * @param   int     $maximum  Greatest allowed value, inclusive.
     * @param   string  $name     Human label used in refusal messages.
     * @return  int  The admitted integer.
     * @throws  RenderException  When the value is not a whole number inside
     *     the range.
     * @since   0.1.0
     */
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

    /**
     * Require a finite drawing coordinate from zero through the drawing's
     * declared dimension, preserving the arrived numeric type so the
     * emitted bytes match the reference exactly.
     *
     * @param   mixed   $value    The candidate value.
     * @param   int     $maximum  The drawing dimension bounding this axis.
     * @param   string  $name     Human label used in refusal messages.
     * @return  int|float  The admitted coordinate, type preserved.
     * @throws  RenderException  When the value is not a finite number
     *     inside the bounds.
     * @since   0.1.0
     */
    private static function coordinate(mixed $value, int $maximum, string $name): int|float
    {
        if (!self::isFiniteNumber($value) || $value < 0 || $value > $maximum) {
            throw new RenderException("{$name} must be a finite coordinate inside the drawing bounds.");
        }

        return $value;
    }

    /**
     * Copy one optional member onto the result when present, refusing any
     * value outside its closed vocabulary. An absent member stays absent —
     * no default is invented.
     *
     * @param   \stdClass     $record  The admitted input record.
     * @param   \stdClass     $target  The result object being built.
     * @param   string        $name    The member to copy.
     * @param   list<string>  $values  The member's closed vocabulary.
     * @throws  RenderException  When the member is present but not one of
     *     the allowed strings.
     * @since   0.1.0
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
     * True only for an int or a finite float — NAN, infinities, and every
     * non-number are false.
     *
     * @param   mixed  $value  The candidate value.
     * @return  bool  Whether the value is a finite number.
     *
     * @phpstan-assert-if-true int|float $value
     *
     * @since   0.1.0
     */
    private static function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }
}
