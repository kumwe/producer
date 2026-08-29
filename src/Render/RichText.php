<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Canonical portable rich text: validating parse, semantic HTML rendering,
 * and the renderer projection.
 *
 * Ports the Studio rich-text package's portable grammar (the closed node and
 * mark vocabulary with per-node attribute allowlists and bounded sizes), the
 * reference renderer's HTML projection of a parsed document, and the
 * span/embed block projection the rich-text conformance corpus fixes. A
 * document that fails the grammar is refused, which makes the owning block
 * fall back to escaped plain text — stored markup is never evaluated.
 *
 * @phpstan-type RichMark \stdClass&object{type: string, attrs?: \stdClass}
 * @phpstan-type RichNode \stdClass&object{
 *     type: string,
 *     attrs?: \stdClass,
 *     content?: list<\stdClass>,
 *     marks?: list<\stdClass>,
 *     text?: string
 * }
 * @phpstan-type RichDocument \stdClass&object{type: string, content: list<RichNode>}
 * @phpstan-type ChecklistTree \stdClass&object{items: list<\stdClass>}
 * @phpstan-type ChecklistEntry \stdClass&object{
 *     children: ChecklistTree,
 *     level: int,
 *     node: RichNode|null
 * }
 *
 * @since   0.1.0
 */
final class RichText
{
    /**
     * The closed mark vocabulary; a mark of any other type refuses the
     * document.
     *
     * @since   0.1.0
     */
    private const ALLOWED_MARKS = ['bold', 'code', 'highlight', 'italic', 'strike'];

    /**
     * The closed node-type vocabulary; a node of any other type refuses
     * the document.
     *
     * @since   0.1.0
     */
    private const ALLOWED_NODES = [
        'blockquote', 'bulletList', 'callout', 'checklist', 'checklistItem',
        'codeBlock', 'doc', 'hardBreak', 'heading', 'horizontalRule',
        'listItem', 'orderedList', 'paragraph', 'table', 'tableCell',
        'tableRow', 'text',
    ];

    /**
     * Per-owner attribute allowlists, keyed by node type (or `mark:<type>`
     * for a mark). An owner absent here allows no attributes at all.
     *
     * @since   0.1.0
     */
    private const ALLOWED_ATTRIBUTES = [
        'callout' => ['tone'],
        'checklistItem' => ['checked', 'level'],
        'codeBlock' => ['language'],
        'heading' => ['level'],
        'mark:highlight' => ['tone'],
        'orderedList' => ['start'],
        'table' => ['header'],
    ];

    /**
     * Attribute keys refused everywhere, defusing prototype-pollution
     * shapes carried in stored documents.
     *
     * @since   0.1.0
     */
    private const FORBIDDEN_KEYS = ['__proto__', 'constructor', 'prototype'];

    /**
     * The heading levels the portable grammar admits; h1 belongs to the
     * page, not to stored content.
     *
     * @since   0.1.0
     */
    private const HEADING_LEVELS = [2, 3, 4];

    /**
     * The node types allowed as document-level (and block-container)
     * children.
     *
     * @since   0.1.0
     */
    private const BLOCK_NODES = [
        'blockquote', 'bulletList', 'callout', 'checklist', 'codeBlock',
        'heading', 'horizontalRule', 'orderedList', 'paragraph', 'table',
    ];

    /**
     * The node types allowed inside a leaf block's inline content.
     *
     * @since   0.1.0
     */
    private const INLINE_NODES = ['hardBreak', 'text'];

    /**
     * Deepest allowed nesting; enforced per node before its children are
     * touched.
     *
     * @since   0.1.0
     */
    private const MAXIMUM_DEPTH = 32;

    /**
     * Most nodes one document may carry, counted during the parse so an
     * oversized document is refused mid-walk.
     *
     * @since   0.1.0
     */
    private const MAXIMUM_NODES = 5000;

    /**
     * Most text one document may carry, in UTF-8 code points across every
     * text node.
     *
     * @since   0.1.0
     */
    private const MAXIMUM_TEXT_LENGTH = 250000;

    /**
     * Most marks one document may carry in aggregate; each node is also
     * limited to one mark per type.
     *
     * @since   0.1.0
     */
    private const MAXIMUM_MARKS = 20000;

    /**
     * Static grammar and projection; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Validate a decoded value against the portable rich-text grammar and
     * return the normalized document (a `doc` root whose content member
     * always exists). Bounds are enforced during the walk — depth 32,
     * 5000 nodes, 250000 text code points, 20000 marks — so hostile input
     * cannot amplify work, and unknown keys, node types, marks, and
     * attributes all refuse rather than being dropped.
     *
     * @param   mixed  $value  The decoded document candidate.
     * @return  RichDocument  The normalized document, safe for
     *     {@see self::render()} and {@see self::project()}.
     * @throws  RenderException  On any grammar violation or exceeded
     *     bound; the message carries the JSON-path of the refusal.
     * @since   0.1.0
     */
    public static function parse(mixed $value): \stdClass
    {
        $state = (object) ['marks' => 0, 'nodes' => 0, 'text' => 0];
        $node = self::parseNode($value, '$', 1, $state);
        if ($node->type !== 'doc') {
            throw new RenderException('Rich-text document root must have type "doc".');
        }
        if (!property_exists($node, 'content')) {
            $node->content = [];
        }

        return (object) [
            'content' => self::nodeContent($node),
            'type' => $node->type,
        ];
    }

    /**
     * Render a parsed document into the reference renderer's semantic
     * HTML: every text value escaped, tones and levels clamped to their
     * closed vocabularies, identical bytes for an identical document.
     *
     * @param   RichDocument  $document  A document validated by
     *     {@see self::parse()}; unvalidated input has no guarantees here.
     * @return  string  The document's escaped semantic markup.
     * @since   0.1.0
     */
    public static function render(\stdClass $document): string
    {
        $out = '';
        foreach ($document->content as $node) {
            $out .= self::renderNode($node);
        }

        return $out;
    }

    /**
     * The canonical block projection: leaf blocks flattened in document
     * order, each with code-point text offsets, maximal mark spans, and
     * inline embeds — the shape the rich-text conformance corpus fixes.
     *
     * @param   RichDocument  $document  A document validated by
     *     {@see self::parse()}.
     * @return  list<\stdClass>  One projection per leaf block, in document
     *     order.
     * @throws  RenderException  When a node type has no projection — which
     *     a parsed document never contains.
     * @since   0.1.0
     */
    public static function project(\stdClass $document): array
    {
        $projections = [];
        foreach ($document->content as $block) {
            self::collectProjections($block, $projections);
        }

        return $projections;
    }

    /**
     * Append one block's projections: a leaf block projects itself, a
     * container recurses into its children, and code blocks and rules
     * project their fixed shapes.
     *
     * @param   RichNode          $node         The parsed block node.
     * @param   list<\stdClass>  $projections  The projection list being
     *     built, appended in document order.
     * @throws  RenderException  When the node type has no projection.
     * @since   0.1.0
     */
    private static function collectProjections(\stdClass $node, array &$projections): void
    {
        switch ($node->type ?? '') {
            case 'checklistItem':
            case 'heading':
            case 'paragraph':
            case 'tableCell':
                $projections[] = self::projectLeafBlock($node);

                return;
            case 'codeBlock':
                $projections[] = (object) [
                    'embeds' => [],
                    'spans' => [],
                    'text' => Properties::stringValue($node->text ?? null),
                    'type' => 'codeBlock',
                ];

                return;
            case 'horizontalRule':
                $projections[] = (object) ['embeds' => [], 'spans' => [], 'text' => '', 'type' => 'horizontalRule'];

                return;
            case 'blockquote':
            case 'bulletList':
            case 'callout':
            case 'checklist':
            case 'listItem':
            case 'orderedList':
            case 'table':
            case 'tableRow':
                foreach (self::nodeContent($node) as $child) {
                    self::collectProjections($child, $projections);
                }

                return;
            default:
                throw new RenderException('Node type "' . Properties::stringValue($node->type ?? null) . '" has no renderer projection.');
        }
    }

    /**
     * Project one leaf block: inline text concatenated with code-point
     * offsets, marks as byte-sorted maximal spans (adjacent runs with the
     * same mark set merge), and non-text inlines as embeds at their
     * offset.
     *
     * @param   RichNode  $node  The parsed leaf block node.
     * @return  \stdClass  {embeds, spans, text, type}.
     * @since   0.1.0
     */
    private static function projectLeafBlock(\stdClass $node): \stdClass
    {
        $embeds = [];
        $spans = [];
        $text = '';
        $offset = 0;
        foreach (self::nodeContent($node) as $inline) {
            if ($inline->type === 'text') {
                $value = Properties::stringValue($inline->text ?? null);
                $length = mb_strlen($value, 'UTF-8');
                $marks = [];
                foreach (self::nodeMarks($inline) as $mark) {
                    $marks[] = $mark->type;
                }
                sort($marks, SORT_STRING);
                if ($marks !== [] && $length > 0) {
                    $previous = $spans === [] ? null : $spans[count($spans) - 1];
                    if ($previous !== null && $previous->end === $offset && $previous->marks === $marks) {
                        $previous->end = $offset + $length;
                    } else {
                        $spans[] = (object) ['end' => $offset + $length, 'marks' => $marks, 'start' => $offset];
                    }
                }
                $text .= $value;
                $offset += $length;
            } else {
                $embeds[] = (object) ['index' => $offset, 'kind' => $inline->type];
            }
        }

        return (object) ['embeds' => $embeds, 'spans' => $spans, 'text' => $text, 'type' => $node->type];
    }

    /**
     * Render one parsed node to its reference markup shape, rendering
     * children first; a type without a shape here renders as the empty
     * string rather than failing the document.
     *
     * @param   RichNode  $node  The parsed node.
     * @return  string  The node's escaped markup.
     * @since   0.1.0
     */
    private static function renderNode(\stdClass $node): string
    {
        $children = '';
        foreach (self::nodeContent($node) as $child) {
            $children .= self::renderNode($child);
        }
        $attributes = self::nodeAttributes($node);
        switch ($node->type) {
            case 'doc':
                return $children;
            case 'paragraph':
                return '<p>' . $children . '</p>';
            case 'heading':
                $level = self::headingLevel($attributes->level ?? null);

                return "<h{$level}>" . $children . "</h{$level}>";
            case 'blockquote':
                return '<blockquote>' . $children . '</blockquote>';
            case 'callout':
                return '<aside data-studio-rich-text-callout data-studio-tone="'
                    . self::calloutTone($attributes->tone ?? null) . '">' . $children . '</aside>';
            case 'bulletList':
                return '<ul>' . $children . '</ul>';
            case 'orderedList':
                $start = $attributes->start ?? null;
                $attribute = is_int($start) || is_float($start)
                    ? ' start="' . SafeMarkup::number($start) . '"'
                    : '';

                return '<ol' . $attribute . '>' . $children . '</ol>';
            case 'listItem':
                return '<li>' . $children . '</li>';
            case 'checklist':
                return self::renderChecklist($node);
            case 'checklistItem':
                return self::renderChecklistItem($node, self::checklistLevel($attributes->level ?? null), []);
            case 'table':
                return self::renderTable($node);
            case 'tableRow':
                return '<tr>' . $children . '</tr>';
            case 'tableCell':
                return '<td>' . $children . '</td>';
            case 'codeBlock':
                return '<pre><code data-language="'
                    . SafeMarkup::escapeAttribute(Properties::stringProperty($attributes->language ?? null, 'text'))
                    . '">' . SafeMarkup::escapeHtml(Properties::stringValue($node->text ?? null)) . '</code></pre>';
            case 'horizontalRule':
                return '<hr>';
            case 'hardBreak':
                return '<br>';
            case 'text':
                return self::applyMarks(
                    SafeMarkup::escapeHtml(Properties::stringValue($node->text ?? null)),
                    self::nodeMarks($node)
                );
            default:
                return '';
        }
    }

    /**
     * Wrap already-escaped text in its mark elements in the reference's
     * fixed nesting order — bold, italic, strike, code, then highlight
     * outermost — so the same mark set always renders the same bytes.
     *
     * @param   string           $value  The escaped text to wrap.
     * @param   list<RichMark>  $marks  The node's parsed marks.
     * @return  string  The wrapped markup.
     * @since   0.1.0
     */
    private static function applyMarks(string $value, array $marks): string
    {
        $current = $value;
        foreach (['bold', 'italic', 'strike', 'code', 'highlight'] as $type) {
            $mark = null;
            foreach ($marks as $candidate) {
                if (($candidate->type ?? null) === $type) {
                    $mark = $candidate;
                    break;
                }
            }
            if ($mark === null) {
                continue;
            }
            if ($type === 'highlight') {
                $attributes = self::markAttributes($mark);
                $current = '<mark data-studio-tone="' . self::highlightTone($attributes->tone ?? null) . '">'
                    . $current . '</mark>';
                continue;
            }
            $element = match ($type) {
                'bold' => 'strong',
                'italic' => 'em',
                'strike' => 'del',
                default => 'code',
            };
            $current = "<{$element}>{$current}</{$element}>";
        }

        return $current;
    }

    /**
     * Render a parsed table: with the header attribute the first row
     * becomes a thead of scoped column headers, everything else renders as
     * body rows.
     *
     * @param   RichNode  $node  The parsed table node.
     * @return  string  The table's escaped markup.
     * @since   0.1.0
     */
    private static function renderTable(\stdClass $node): string
    {
        $rows = self::nodeContent($node);
        $renderRow = static function (\stdClass $row, bool $header): string {
            $cells = '';
            foreach (self::nodeContent($row) as $cell) {
                $content = '';
                foreach (self::nodeContent($cell) as $child) {
                    $content .= self::renderNode($child);
                }
                $cells .= $header ? '<th scope="col">' . $content . '</th>' : '<td>' . $content . '</td>';
            }

            return '<tr>' . $cells . '</tr>';
        };
        if ((self::nodeAttributes($node)->header ?? null) === true) {
            $heading = $rows[0] ?? null;
            $body = array_slice($rows, 1);
            $bodyMarkup = '';
            foreach ($body as $row) {
                $bodyMarkup .= $renderRow($row, false);
            }

            return '<table data-studio-rich-text-table>'
                . ($heading === null ? '' : '<thead>' . $renderRow($heading, true) . '</thead>')
                . ($bodyMarkup === '' ? '' : '<tbody>' . $bodyMarkup . '</tbody>')
                . '</table>';
        }
        $bodyMarkup = '';
        foreach ($rows as $row) {
            $bodyMarkup .= $renderRow($row, false);
        }

        return '<table data-studio-rich-text-table><tbody>' . $bodyMarkup . '</tbody></table>';
    }

    /**
     * Render a parsed checklist, rebuilding the nesting the flat
     * level-attributed items imply — a level skipped in the input gets a
     * non-semantic bridge item so the emitted list nesting stays valid.
     *
     * @param   RichNode  $node  The parsed checklist node.
     * @return  string  The checklist's escaped markup.
     * @since   0.1.0
     */
    private static function renderChecklist(\stdClass $node): string
    {
        $root = self::newChecklistTree();
        $levels = [$root];
        foreach (self::nodeContent($node) as $item) {
            $level = self::checklistLevel(self::nodeAttributes($item)->level ?? null);
            $levels = array_slice($levels, 0, min($level + 1, count($levels)));
            while (count($levels) <= $level) {
                $parentItems = $levels[count($levels) - 1];
                $parent = self::lastChecklistEntry($parentItems);
                if ($parent === null) {
                    $parent = self::newChecklistEntry(null, min(4, count($levels) - 1));
                    self::appendChecklistEntry($parentItems, $parent);
                }
                $levels[] = $parent->children;
            }
            if (isset($levels[$level])) {
                self::appendChecklistEntry($levels[$level], self::newChecklistEntry($item, $level));
            }
        }

        return '<ul data-studio-rich-text-checklist>'
            . self::renderChecklistItems(self::checklistEntries($root)) . '</ul>';
    }

    /**
     * Render one level of the rebuilt checklist tree, bridge items
     * included, each nesting its own children.
     *
     * @param   list<ChecklistEntry>  $items  Checklist tree items at one level.
     * @return  string  The level's escaped markup.
     * @since   0.1.0
     */
    private static function renderChecklistItems(array $items): string
    {
        $out = '';
        foreach ($items as $item) {
            $childEntries = self::checklistEntries($item->children);
            $children = $childEntries === []
                ? ''
                : '<ul data-studio-rich-text-checklist-level="' . ($item->level + 1) . '">'
                    . self::renderChecklistItems($childEntries) . '</ul>';
            $out .= $item->node === null
                ? '<li role="none" data-studio-rich-text-checklist-bridge>' . $children . '</li>'
                : self::renderChecklistItem($item->node, $item->level, $childEntries);
        }

        return $out;
    }

    /**
     * Render one checklist item as an inert (disabled) checkbox with its
     * content and nested sub-list; an item with no text gets a fixed
     * aria-label so it never renders unnamed.
     *
     * @param   RichNode          $node      The parsed checklist item.
     * @param   int              $level     The item's nesting level, 0-4.
     * @param   list<ChecklistEntry>  $children  Checklist tree items nested
     *     under this item.
     * @return  string  The item's escaped markup.
     * @since   0.1.0
     */
    private static function renderChecklistItem(\stdClass $node, int $level, array $children): string
    {
        $checked = (self::nodeAttributes($node)->checked ?? null) === true;
        $content = '';
        foreach (self::nodeContent($node) as $child) {
            $content .= self::renderNode($child);
        }
        $fallbackLabel = self::nodeHasText($node) ? '' : ' aria-label="Checklist item"';
        $nested = $children === []
            ? ''
            : '<ul data-studio-rich-text-checklist-level="' . ($level + 1) . '">'
                . self::renderChecklistItems($children) . '</ul>';

        return '<li data-studio-rich-text-checklist-item data-studio-checked="' . ($checked ? 'true' : 'false')
            . '" data-studio-level="' . $level . '" aria-level="' . ($level + 1)
            . '"><label><input type="checkbox" disabled' . $fallbackLabel . ($checked ? ' checked' : '')
            . '><span data-studio-rich-text-checklist-content>' . $content . '</span></label>' . $nested . '</li>';
    }

    /**
     * True when the node or any descendant carries non-empty text — the
     * test deciding whether a checklist item needs its fallback label.
     *
     * @param   RichNode  $node  The parsed node.
     * @return  bool  Whether any non-empty text exists beneath the node.
     * @since   0.1.0
     */
    private static function nodeHasText(\stdClass $node): bool
    {
        foreach (self::nodeContent($node) as $child) {
            if ($child->type === 'text' && Properties::stringValue($child->text ?? null) !== '') {
                return true;
            }
            if (self::nodeHasText($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The emitted callout tone: danger, success, or warning pass through;
     * anything else renders as info.
     *
     * @param   mixed  $value  The stored tone attribute.
     * @return  string  A member of the emitted tone vocabulary.
     * @since   0.1.0
     */
    private static function calloutTone(mixed $value): string
    {
        return in_array($value, ['danger', 'success', 'warning'], true) ? $value : 'info';
    }

    /**
     * The emitted highlight tone: danger, info, success, or warning pass
     * through; anything else renders as accent.
     *
     * @param   mixed  $value  The stored tone attribute.
     * @return  string  A member of the emitted tone vocabulary.
     * @since   0.1.0
     */
    private static function highlightTone(mixed $value): string
    {
        return in_array($value, ['danger', 'info', 'success', 'warning'], true) ? $value : 'accent';
    }

    /**
     * The emitted heading level: 3 and 4 pass through, everything else
     * renders as 2 — never 1, which the page owns.
     *
     * @param   mixed  $value  The stored level attribute.
     * @return  int  The heading level to emit, 2 through 4.
     * @since   0.1.0
     */
    private static function headingLevel(mixed $value): int
    {
        $level = self::integerish($value);

        return $level === 3 || $level === 4 ? $level : 2;
    }

    /**
     * The emitted checklist nesting level: whole numbers 1 through 4 pass
     * through, everything else renders at the top level.
     *
     * @param   mixed  $value  The stored level attribute.
     * @return  int  The nesting level to emit, 0 through 4.
     * @since   0.1.0
     */
    private static function checklistLevel(mixed $value): int
    {
        $level = self::integerish($value);

        return $level !== null && $level >= 1 && $level <= 4 ? $level : 0;
    }

    /**
     * The integer a stored value denotes: an int itself, or a whole float
     * within the safe-integer range; anything else is null.
     *
     * @param   mixed  $value  The stored value.
     * @return  ?int  The denoted integer, or null.
     * @since   0.1.0
     */
    private static function integerish(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value && abs($value) <= 9007199254740991.0) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Admit one normalized rich-text node for statically modelled traversal.
     *
     * @param   mixed  $value  Parsed node candidate.
     * @return  RichNode  Node carrying its required string type.
     * @throws  RenderException  When an internal caller supplies no node.
     * @since   0.2.0
     */
    private static function richNode(mixed $value): \stdClass
    {
        self::assertRichNode($value);

        return $value;
    }

    /**
     * Prove the minimum normalized shape shared by every parsed node.
     *
     * @param   mixed  $value  Parsed node candidate.
     * @throws  RenderException  When the internal node shape is malformed.
     *
     * @phpstan-assert RichNode $value
     *
     * @since   0.2.0
     */
    private static function assertRichNode(mixed $value): void
    {
        if (!$value instanceof \stdClass || !is_string($value->type ?? null)) {
            throw new RenderException('Parsed rich-text content contains no typed node.');
        }
        if (property_exists($value, 'attrs') && !$value->attrs instanceof \stdClass) {
            throw new RenderException('Parsed rich-text node attributes are malformed.');
        }
        if (
            property_exists($value, 'content')
            && (!is_array($value->content) || !array_is_list($value->content))
        ) {
            throw new RenderException('Parsed rich-text node content is not a list.');
        }
        if (
            property_exists($value, 'marks')
            && (!is_array($value->marks) || !array_is_list($value->marks))
        ) {
            throw new RenderException('Parsed rich-text marks are not a list.');
        }
        if (property_exists($value, 'text') && !is_string($value->text)) {
            throw new RenderException('Parsed rich-text node text is malformed.');
        }
    }

    /**
     * Read and prove the parsed children of one node.
     *
     * @param   \stdClass  $node  Parsed parent node.
     * @return  list<RichNode>  Typed children, or an empty list when absent.
     * @throws  RenderException  When internal parsed content is malformed.
     * @since   0.2.0
     */
    private static function nodeContent(\stdClass $node): array
    {
        $content = $node->content ?? null;
        if ($content === null) {
            return [];
        }
        if (!is_array($content) || !array_is_list($content)) {
            throw new RenderException('Parsed rich-text node content is not a list.');
        }
        $nodes = [];
        foreach ($content as $child) {
            $nodes[] = self::richNode($child);
        }

        return $nodes;
    }

    /**
     * Read and prove the parsed marks of one text node.
     *
     * @param   \stdClass  $node  Parsed text node.
     * @return  list<RichMark>  Typed marks, or an empty list when absent.
     * @throws  RenderException  When internal parsed marks are malformed.
     * @since   0.2.0
     */
    private static function nodeMarks(\stdClass $node): array
    {
        $marks = $node->marks ?? null;
        if ($marks === null) {
            return [];
        }
        if (!is_array($marks) || !array_is_list($marks)) {
            throw new RenderException('Parsed rich-text marks are not a list.');
        }
        $normalized = [];
        foreach ($marks as $mark) {
            self::assertRichMark($mark);
            $normalized[] = $mark;
        }

        return $normalized;
    }

    /**
     * Prove the minimum normalized shape shared by every parsed mark.
     *
     * @param   mixed  $value  Parsed mark candidate.
     * @throws  RenderException  When the internal mark shape is malformed.
     *
     * @phpstan-assert RichMark $value
     *
     * @since   0.2.0
     */
    private static function assertRichMark(mixed $value): void
    {
        if (!$value instanceof \stdClass || !is_string($value->type ?? null)) {
            throw new RenderException('Parsed rich-text mark has no type.');
        }
        if (property_exists($value, 'attrs') && !$value->attrs instanceof \stdClass) {
            throw new RenderException('Parsed rich-text mark attributes are malformed.');
        }
    }

    /**
     * Read a node's parsed attribute object without dynamic member chaining.
     *
     * @param   \stdClass  $node  Parsed node.
     * @return  \stdClass  Attribute object, empty when absent.
     * @throws  RenderException  When internal parsed attributes are malformed.
     * @since   0.2.0
     */
    private static function nodeAttributes(\stdClass $node): \stdClass
    {
        $attributes = $node->attrs ?? null;
        if ($attributes === null) {
            return new \stdClass();
        }
        if (!$attributes instanceof \stdClass) {
            throw new RenderException('Parsed rich-text node attributes are malformed.');
        }

        return $attributes;
    }

    /**
     * Read a mark's parsed attribute object without dynamic member chaining.
     *
     * @param   RichMark  $mark  Parsed mark.
     * @return  \stdClass  Attribute object, empty when absent.
     * @throws  RenderException  When internal parsed attributes are malformed.
     * @since   0.2.0
     */
    private static function markAttributes(\stdClass $mark): \stdClass
    {
        return self::nodeAttributes($mark);
    }

    /**
     * Create one mutable checklist branch.
     *
     * @return  ChecklistTree  Empty checklist branch.
     * @since   0.2.0
     */
    private static function newChecklistTree(): \stdClass
    {
        return (object) ['items' => []];
    }

    /**
     * Create one mutable checklist tree entry.
     *
     * @param   RichNode|null  $node   Parsed checklist item, or bridge null.
     * @param   int            $level  Normalized nesting level.
     * @return  ChecklistEntry  New tree entry.
     * @since   0.2.0
     */
    private static function newChecklistEntry(?\stdClass $node, int $level): \stdClass
    {
        return (object) [
            'children' => self::newChecklistTree(),
            'level' => $level,
            'node' => $node,
        ];
    }

    /**
     * Append an entry to a mutable checklist branch.
     *
     * @param   ChecklistTree   $tree   Target branch.
     * @param   ChecklistEntry  $entry  Entry to append.
     * @since   0.2.0
     */
    private static function appendChecklistEntry(\stdClass $tree, \stdClass $entry): void
    {
        $tree->items[] = $entry;
    }

    /**
     * Return the last checklist entry in a branch.
     *
     * @param   ChecklistTree  $tree  Branch to inspect.
     * @return  ChecklistEntry|null  Last entry, or null for an empty branch.
     * @throws  RenderException  When the internal tree is malformed.
     * @since   0.2.0
     */
    private static function lastChecklistEntry(\stdClass $tree): ?\stdClass
    {
        $items = self::checklistEntries($tree);

        return $items === [] ? null : $items[count($items) - 1];
    }

    /**
     * Prove the entries held by one checklist branch.
     *
     * @param   ChecklistTree  $tree  Branch to inspect.
     * @return  list<ChecklistEntry>  Typed entries.
     * @throws  RenderException  When the internal tree is malformed.
     * @since   0.2.0
     */
    private static function checklistEntries(\stdClass $tree): array
    {
        self::assertChecklistTree($tree);
        $entries = [];
        foreach ($tree->items as $entry) {
            self::assertChecklistEntry($entry);
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Prove one internal checklist branch.
     *
     * @param   mixed  $value  Branch candidate.
     * @throws  RenderException  When the internal tree is malformed.
     *
     * @phpstan-assert ChecklistTree $value
     *
     * @since   0.2.0
     */
    private static function assertChecklistTree(mixed $value): void
    {
        if (
            !$value instanceof \stdClass
            || !is_array($value->items ?? null)
            || !array_is_list($value->items)
        ) {
            throw new RenderException('Internal checklist tree is malformed.');
        }
        foreach ($value->items as $item) {
            if (!$item instanceof \stdClass) {
                throw new RenderException('Internal checklist tree is malformed.');
            }
        }
    }

    /**
     * Prove one internal checklist entry.
     *
     * @param   mixed  $value  Entry candidate.
     * @throws  RenderException  When the internal tree is malformed.
     *
     * @phpstan-assert ChecklistEntry $value
     *
     * @since   0.2.0
     */
    private static function assertChecklistEntry(mixed $value): void
    {
        if (
            !$value instanceof \stdClass
            || !property_exists($value, 'children')
            || !property_exists($value, 'level')
            || !property_exists($value, 'node')
            || !is_int($value->level)
        ) {
            throw new RenderException('Internal checklist tree is malformed.');
        }
        self::assertChecklistTree($value->children);
        if ($value->node !== null) {
            self::assertRichNode($value->node);
        }
    }

    /**
     * Parse one node: known keys only, an allowed type, the depth and
     * aggregate bounds charged before children are walked, then the
     * per-type grammar asserted on the assembled node. Only recognized
     * members are copied onto the result.
     *
     * @param   mixed      $value  The decoded node candidate.
     * @param   string     $path   The node's JSON-path, for refusals.
     * @param   int        $depth  The node's depth, the root at 1.
     * @param   \stdClass&object{marks: int, nodes: int, text: int}  $state
     *     The running document-wide counters
     *     {marks, nodes, text}.
     * @return  RichNode  The normalized node.
     * @throws  RenderException  On any grammar violation or exceeded
     *     bound.
     * @since   0.1.0
     */
    private static function parseNode(mixed $value, string $path, int $depth, \stdClass $state): \stdClass
    {
        if (!$value instanceof \stdClass) {
            throw new RenderException("{$path} must be a rich-text node with a non-empty type.");
        }
        self::assertKnownKeys($value, $path, ['attrs', 'content', 'marks', 'text', 'type']);
        $type = $value->type ?? null;
        if (!is_string($type) || $type === '') {
            throw new RenderException("{$path} must be a rich-text node with a non-empty type.");
        }
        if (!in_array($type, self::ALLOWED_NODES, true)) {
            throw new RenderException("{$path} uses disallowed node type \"{$type}\".");
        }
        if ($depth > self::MAXIMUM_DEPTH) {
            throw new RenderException("{$path} exceeds the rich-text depth limit.");
        }
        $state->nodes++;
        if ($state->nodes > self::MAXIMUM_NODES) {
            throw new RenderException('Rich-text document exceeds its node limit.');
        }

        $node = (object) ['type' => $type];
        if (property_exists($value, 'text')) {
            if (!is_string($value->text)) {
                throw new RenderException("{$path}.text must be a string.");
            }
            $node->text = $value->text;
            $state->text += mb_strlen($value->text, 'UTF-8');
            if ($state->text > self::MAXIMUM_TEXT_LENGTH) {
                throw new RenderException('Rich-text document exceeds its text-length limit.');
            }
        }
        if (property_exists($value, 'attrs')) {
            $node->attrs = self::parseAttributes($value->attrs, "{$path}.attrs", $type);
        }
        if (property_exists($value, 'content')) {
            if (!is_array($value->content)) {
                throw new RenderException("{$path}.content must be an array.");
            }
            $content = [];
            foreach ($value->content as $index => $child) {
                $content[] = self::parseNode($child, "{$path}.content[{$index}]", $depth + 1, $state);
            }
            $node->content = $content;
        }
        if (property_exists($value, 'marks')) {
            if (!is_array($value->marks)) {
                throw new RenderException("{$path}.marks must be an array.");
            }
            if (count($value->marks) > count(self::ALLOWED_MARKS)) {
                throw new RenderException("{$path}.marks exceeds the per-node mark limit.");
            }
            $state->marks += count($value->marks);
            if ($state->marks > self::MAXIMUM_MARKS) {
                throw new RenderException('Rich-text document exceeds its aggregate mark limit.');
            }
            $marks = [];
            foreach ($value->marks as $index => $mark) {
                $marks[] = self::parseMark($mark, "{$path}.marks[{$index}]");
            }
            self::assertPortableMarkSet($marks, "{$path}.marks");
            $node->marks = $marks;
        }
        self::assertGrammar($node, $path);

        return $node;
    }

    /**
     * Parse one mark: an allowed type, and attributes only where the
     * grammar grants them — highlight requires a configured tone, every
     * other mark must carry none.
     *
     * @param   mixed   $value  The decoded mark candidate.
     * @param   string  $path   The mark's JSON-path, for refusals.
     * @return  RichMark  The normalized mark.
     * @throws  RenderException  On an unknown key or type, or attributes
     *     the mark may not carry.
     * @since   0.1.0
     */
    private static function parseMark(mixed $value, string $path): \stdClass
    {
        if (!$value instanceof \stdClass) {
            throw new RenderException("{$path} must be a mark with a non-empty type.");
        }
        self::assertKnownKeys($value, $path, ['attrs', 'type']);
        $type = $value->type ?? null;
        if (!is_string($type) || $type === '') {
            throw new RenderException("{$path} must be a mark with a non-empty type.");
        }
        if (!in_array($type, self::ALLOWED_MARKS, true)) {
            throw new RenderException("{$path} uses disallowed mark type \"{$type}\".");
        }
        $mark = (object) ['type' => $type];
        if (property_exists($value, 'attrs')) {
            $mark->attrs = self::parseAttributes($value->attrs, "{$path}.attrs", "mark:{$type}");
        }
        if ($type === 'highlight') {
            $tone = self::markAttributes($mark)->tone ?? null;
            if (!is_string($tone) || !in_array($tone, ['accent', 'danger', 'info', 'success', 'warning'], true)) {
                throw new RenderException("{$path}.attrs.tone must be a configured highlight tone.");
            }
        } elseif (property_exists($mark, 'attrs')) {
            throw new RenderException("{$path} cannot carry attributes in the portable rich-text grammar.");
        }

        return $mark;
    }

    /**
     * Parse an attribute object against its owner's allowlist. Forbidden
     * object keys (__proto__, constructor, prototype) refuse outright;
     * so does any attribute the owner is not granted. Values are copied
     * as-is — each owner's grammar checks them afterward.
     *
     * @param   mixed   $value      The decoded attrs candidate.
     * @param   string  $path       The attrs' JSON-path, for refusals.
     * @param   string  $ownerType  The owning node type, or `mark:<type>`.
     * @return  \stdClass  The admitted attributes.
     * @throws  RenderException  On a non-object, a forbidden key, or an
     *     attribute outside the owner's allowlist.
     * @since   0.1.0
     */
    private static function parseAttributes(mixed $value, string $path, string $ownerType): \stdClass
    {
        if (!$value instanceof \stdClass) {
            throw new RenderException("{$path} must be an object.");
        }
        $allowed = self::ALLOWED_ATTRIBUTES[$ownerType] ?? [];
        $attributes = new \stdClass();
        foreach (get_object_vars($value) as $key => $entry) {
            $key = (string) $key;
            if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                throw new RenderException("{$path}.{$key} is a forbidden object key.");
            }
            if (!in_array($key, $allowed, true)) {
                throw new RenderException("{$path}.{$key} is not allowed for {$ownerType}.");
            }
            $attributes->{$key} = $entry;
        }

        return $attributes;
    }

    /**
     * Assert one node's mark set is portable: no duplicate mark types, and
     * code combined with nothing else.
     *
     * @param   list<RichMark>  $marks  The node's parsed marks.
     * @param   string           $path   The marks' JSON-path, for refusals.
     * @throws  RenderException  On a duplicate type or a code combination.
     * @since   0.1.0
     */
    private static function assertPortableMarkSet(array $marks, string $path): void
    {
        $types = [];
        foreach ($marks as $mark) {
            if (in_array($mark->type, $types, true)) {
                throw new RenderException("{$path} cannot contain duplicate {$mark->type} marks.");
            }
            $types[] = $mark->type;
        }
        if (in_array('code', $types, true) && count($types) > 1) {
            throw new RenderException("{$path} cannot combine code with another mark.");
        }
    }

    /**
     * Assert one assembled node against its type's grammar: the fields it
     * may not carry, the child types it may contain, whether content is
     * required, and each attribute's own closed value set.
     *
     * @param   RichNode  $node  The assembled node.
     * @param   string     $path  The node's JSON-path, for refusals.
     * @throws  RenderException  On any violation of the type's grammar.
     * @since   0.1.0
     */
    private static function assertGrammar(\stdClass $node, string $path): void
    {
        $attributes = self::nodeAttributes($node);
        $content = self::nodeContent($node);
        switch ($node->type) {
            case 'doc':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                if (!property_exists($node, 'content') || $content === []) {
                    throw new RenderException("{$path}.content must contain at least one block node.");
                }
                self::assertChildTypes($content, $path, self::BLOCK_NODES);

                return;
            case 'text':
                self::assertNoFields($node, $path, ['attrs', 'content']);
                if (!property_exists($node, 'text')) {
                    throw new RenderException("{$path}.text is required for a text node.");
                }
                if ($node->text === '') {
                    throw new RenderException("{$path}.text cannot be empty.");
                }

                return;
            case 'paragraph':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertChildTypes($content, $path, self::INLINE_NODES);

                return;
            case 'heading':
                self::assertNoFields($node, $path, ['marks', 'text']);
                self::assertChildTypes($content, $path, self::INLINE_NODES);
                $level = self::integerish($attributes->level ?? null);
                if ($level === null || !in_array($level, self::HEADING_LEVELS, true)) {
                    throw new RenderException("{$path}.attrs.level must be a configured heading level.");
                }

                return;
            case 'orderedList':
                self::assertNoFields($node, $path, ['marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, ['listItem']);
                if (property_exists($attributes, 'start')) {
                    $start = self::integerish($attributes->start);
                    if ($start === null || $start < 1) {
                        throw new RenderException("{$path}.attrs.start must be a positive integer.");
                    }
                }

                return;
            case 'bulletList':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, ['listItem']);

                return;
            case 'listItem':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, self::BLOCK_NODES);
                if (($content[0]->type ?? null) !== 'paragraph') {
                    throw new RenderException("{$path}.content must begin with a paragraph node.");
                }

                return;
            case 'blockquote':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, self::BLOCK_NODES);

                return;
            case 'callout':
                self::assertNoFields($node, $path, ['marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, self::BLOCK_NODES);
                $tone = $attributes->tone ?? null;
                if (!is_string($tone) || !in_array($tone, ['danger', 'info', 'success', 'warning'], true)) {
                    throw new RenderException("{$path}.attrs.tone must be a configured callout tone.");
                }

                return;
            case 'checklist':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, ['checklistItem']);

                return;
            case 'checklistItem':
                self::assertNoFields($node, $path, ['marks', 'text']);
                self::assertChildTypes($content, $path, self::INLINE_NODES);
                if (!is_bool($attributes->checked ?? null)) {
                    throw new RenderException("{$path}.attrs.checked must be a boolean.");
                }
                $level = self::integerish($attributes->level ?? null);
                if ($level === null || $level < 0 || $level > 4) {
                    throw new RenderException("{$path}.attrs.level must be an integer from zero through four.");
                }

                return;
            case 'table':
                self::assertNoFields($node, $path, ['marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, ['tableRow']);
                if (!is_bool($attributes->header ?? null)) {
                    throw new RenderException("{$path}.attrs.header must be a boolean.");
                }
                self::assertRectangularTable($content, $path);

                return;
            case 'tableRow':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertNonEmptyChildTypes($node, $path, ['tableCell']);

                return;
            case 'tableCell':
                self::assertNoFields($node, $path, ['attrs', 'marks', 'text']);
                self::assertChildTypes($content, $path, self::INLINE_NODES);

                return;
            case 'codeBlock':
                self::assertNoFields($node, $path, ['content', 'marks']);
                if (!property_exists($node, 'text')) {
                    throw new RenderException("{$path}.text is required for a code block.");
                }
                $language = $attributes->language ?? null;
                if (!is_string($language) || preg_match('/^[A-Za-z0-9][A-Za-z0-9+_.#-]{0,63}$/u', $language) !== 1) {
                    throw new RenderException("{$path}.attrs.language must be a bounded language identifier.");
                }

                return;
            case 'hardBreak':
            case 'horizontalRule':
                self::assertNoFields($node, $path, ['attrs', 'content', 'marks', 'text']);

                return;
            default:
                throw new RenderException("{$path} uses a node without a portable grammar.");
        }
    }

    /**
     * Refuse the node when it carries any of the named fields — how each
     * type's grammar forbids the members it has no meaning for.
     *
     * @param   RichNode       $node    The assembled node.
     * @param   string        $path    The node's JSON-path, for refusals.
     * @param   list<string>  $fields  The forbidden member names.
     * @throws  RenderException  When a forbidden member is present.
     * @since   0.1.0
     */
    private static function assertNoFields(\stdClass $node, string $path, array $fields): void
    {
        foreach ($fields as $field) {
            if (property_exists($node, $field)) {
                throw new RenderException("{$path}.{$field} is not valid on a {$node->type} node.");
            }
        }
    }

    /**
     * Refuse any child whose type is outside the allowed set; an empty
     * list passes.
     *
     * @param   list<RichNode>  $content  The parsed child nodes.
     * @param   string           $path     The parent's JSON-path, for
     *     refusals.
     * @param   list<string>     $allowed  The allowed child types.
     * @throws  RenderException  On a child of a disallowed type.
     * @since   0.1.0
     */
    private static function assertChildTypes(array $content, string $path, array $allowed): void
    {
        foreach ($content as $index => $child) {
            if (!in_array($child->type, $allowed, true)) {
                throw new RenderException("{$path}.content[{$index}] is not valid inside this node.");
            }
        }
    }

    /**
     * Require at least one child and refuse any child of a disallowed
     * type — the grammar of every container that cannot be empty.
     *
     * @param   RichNode       $node     The assembled node.
     * @param   string        $path     The node's JSON-path, for refusals.
     * @param   list<string>  $allowed  The allowed child types.
     * @throws  RenderException  On missing content or a disallowed child.
     * @since   0.1.0
     */
    private static function assertNonEmptyChildTypes(\stdClass $node, string $path, array $allowed): void
    {
        $content = self::nodeContent($node);
        if ($content === []) {
            throw new RenderException("{$path}.content must contain at least one child node.");
        }
        self::assertChildTypes($content, $path, $allowed);
    }

    /**
     * Require a rectangular table: at least one cell in the first row and
     * exactly that many cells in every row.
     *
     * @param   list<RichNode>  $rows  The parsed table rows.
     * @param   string           $path  The table's JSON-path, for refusals.
     * @throws  RenderException  On an empty first row or a ragged row.
     * @since   0.1.0
     */
    private static function assertRectangularTable(array $rows, string $path): void
    {
        $firstRow = $rows[0] ?? null;
        $width = $firstRow === null ? 0 : count(self::nodeContent($firstRow));
        if ($width < 1) {
            throw new RenderException("{$path}.content must be a non-empty rectangular table.");
        }
        foreach ($rows as $row) {
            if (count(self::nodeContent($row)) !== $width) {
                throw new RenderException("{$path}.content must be a non-empty rectangular table.");
            }
        }
    }

    /**
     * Refuse any object member outside the allowed key set — unknown keys
     * are never silently dropped from a stored document.
     *
     * @param   \stdClass     $value        The decoded object.
     * @param   string        $path         The object's JSON-path, for
     *     refusals.
     * @param   list<string>  $allowedKeys  The complete allowed key set.
     * @throws  RenderException  On an unrecognized member.
     * @since   0.1.0
     */
    private static function assertKnownKeys(\stdClass $value, string $path, array $allowedKeys): void
    {
        foreach (array_keys(get_object_vars($value)) as $key) {
            if (!in_array((string) $key, $allowedKeys, true)) {
                throw new RenderException("{$path}.{$key} is not a recognized rich-text key.");
            }
        }
    }
}
