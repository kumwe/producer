<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * The escaping discipline of Studio's semantic web renderer.
 *
 * Ported from renderer-web's safe-markup.ts: minimal HTML text escaping,
 * attribute escaping on top of it, a closed URL scheme allowlist, and the
 * structural safe-markup fragment renderer that fails closed on any tag or
 * attribute outside its vocabulary. Every dynamic value the renderer emits
 * passes through one of these functions; no stored string ever reaches the
 * output unescaped.
 *
 * @since   0.1.0
 */
final class SafeMarkup
{
    /**
     * The complete tag vocabulary a safe-markup fragment may use. Nothing
     * interactive, embedding, or scripting-capable — no script, style,
     * iframe, img, form, or event surface of any kind.
     *
     * @since   0.1.0
     */
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'blockquote', 'br', 'code', 'del', 'em', 'h2', 'h3', 'h4',
        'h5', 'h6', 'hr', 'li', 'mark', 'ol', 'p', 'pre', 'strong', 'sub',
        'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul',
    ];

    /**
     * Tags emitted without a closing tag; a fragment giving one children
     * is refused.
     *
     * @since   0.1.0
     */
    private const VOID_TAGS = ['br', 'hr'];

    /**
     * The attributes every allowed tag may carry.
     *
     * @since   0.1.0
     */
    private const GLOBAL_ATTRIBUTES = ['aria-label', 'dir', 'lang', 'title'];

    /**
     * Per-tag attribute allowances on top of the global set; a tag absent
     * here allows only the global attributes.
     *
     * @since   0.1.0
     */
    private const TAG_ATTRIBUTES = [
        'a' => ['href'],
        'ol' => ['start'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    /**
     * Static escaping path; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Minimal text escaping for element content, matching the reference
     * byte for byte: `&`, `<`, and `>` become entities and nothing else
     * changes. Safe for text content only — attribute values go through
     * {@see self::escapeAttribute()} instead.
     *
     * @param   string  $value  The untrusted text.
     * @return  string  The text, safe as element content.
     * @since   0.1.0
     */
    public static function escapeHtml(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    /**
     * Attribute-value escaping: {@see self::escapeHtml()} plus both quote
     * characters, making the result safe inside single- or double-quoted
     * attribute values.
     *
     * @param   string  $value  The untrusted text.
     * @return  string  The text, safe as a quoted attribute value.
     * @since   0.1.0
     */
    public static function escapeAttribute(string $value): string
    {
        return str_replace(['"', "'"], ['&quot;', '&#39;'], self::escapeHtml($value));
    }

    /**
     * The closed URL allowlist: site-relative paths, fragments, and https
     * origins only. Anything else — javascript:, data:, http:, protocol
     * tricks — is refused and the caller renders inert text instead. An
     * accepted URL is returned unchanged; escaping stays the emitter's job.
     *
     * @param   string  $value  The untrusted URL candidate.
     * @return  ?string  The value unchanged when allowed, else null.
     * @since   0.1.0
     */
    public static function safeUrl(string $value): ?string
    {
        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return $value;
        }

        return preg_match('%^https://[A-Za-z0-9.-]+(?::[0-9]+)?(?:[/#?]|$)%u', $value) === 1
            ? $value
            : null;
    }

    /**
     * Media URL vetting: the ordinary allowlist first, then — only under
     * the host's explicit blob authority — well-formed blob: URLs whose
     * media type cannot execute (SVG, HTML, and XHTML blobs stay refused).
     *
     * @param   ResolvedMedia  $media      The host-resolved media whose src
     *     and media type are vetted together.
     * @param   bool           $allowBlob  The host's explicit blob
     *     authority; false vets against the ordinary allowlist alone.
     * @return  ?string  The src unchanged when allowed, else null.
     * @since   0.1.0
     */
    public static function safeMediaUrl(ResolvedMedia $media, bool $allowBlob): ?string
    {
        $ordinary = self::safeUrl($media->src);
        if ($ordinary !== null) {
            return $ordinary;
        }
        if (
            !$allowBlob
            || preg_match('%^blob:https?://[A-Za-z0-9.-]+(?::[0-9]+)?/[A-Za-z0-9._~-]+$%u', $media->src) !== 1
        ) {
            return null;
        }
        $mediaType = $media->mediaType === null ? null : strtolower($media->mediaType);
        if (in_array($mediaType, ['image/svg+xml', 'text/html', 'application/xhtml+xml'], true)) {
            return null;
        }

        return $media->src;
    }

    /**
     * ECMAScript-style number-to-string used wherever the reference
     * renderer interpolates a number: integer values print without a
     * fraction, non-finite values print as 0, and everything else takes
     * JSON's shortest round-trip form — identical bytes for identical
     * input, no locale involved.
     *
     * @param   int|float  $value  The number to print.
     * @return  string  The reference-identical decimal text.
     * @since   0.1.0
     */
    public static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (!is_finite($value) || $value === 0.0) {
            return '0';
        }
        if (floor($value) === $value && abs($value) < 9007199254740992.0) {
            return sprintf('%.0F', $value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * True when a bound value is a structural safe-markup fragment rather
     * than a canonical rich-text document. Shape only — the fragment's
     * vocabulary is judged later, by {@see self::renderFragment()}.
     *
     * @param   mixed  $value  The bound value.
     * @return  bool  Whether the value has the fragment envelope shape.
     * @since   0.1.0
     */
    public static function isFragment(mixed $value): bool
    {
        return $value instanceof \stdClass
            && ($value->kind ?? null) === 'safe-markup-fragment'
            && is_array($value->nodes ?? null)
            && is_string($value->policy ?? null);
    }

    /**
     * Render an already structural safe fragment, failing closed on
     * unknown HTML vocabulary. Raw HTML strings are never accepted: the
     * input is a node tree, every text value is escaped on the way out,
     * and one refused node refuses the whole fragment.
     *
     * @param   \stdClass  $fragment  The structural fragment (nodes plus a
     *     qualified policy identifier).
     * @return  string  The fragment's escaped markup.
     * @throws  RenderException  On a missing or malformed policy, or any
     *     node outside the closed tag, attribute, URL, depth, or size
     *     vocabulary.
     * @since   0.1.0
     */
    public static function renderFragment(\stdClass $fragment): string
    {
        $policy = $fragment->policy ?? null;
        if (!is_string($policy) || preg_match('%^[a-z][a-z0-9.-]{0,126}/[a-z][a-z0-9.-]{0,126}$%u', $policy) !== 1) {
            throw new RenderException('Safe markup requires a qualified policy identifier.');
        }
        $nodes = $fragment->nodes ?? null;
        if (!is_array($nodes) || !array_is_list($nodes)) {
            throw new RenderException('Safe markup requires a node list.');
        }
        $out = '';
        foreach ($nodes as $node) {
            $out .= self::renderFragmentNode($node, 1);
        }

        return $out;
    }

    /**
     * Render one fragment node — escaped text for a text node, an element
     * otherwise — enforcing the 64-level depth bound before anything else.
     *
     * @param   mixed  $node   The structural node candidate.
     * @param   int    $depth  The node's depth, root children at 1.
     * @return  string  The node's escaped markup.
     * @throws  RenderException  Past the depth bound, on a non-object
     *     node, or on a refused element.
     * @since   0.1.0
     */
    private static function renderFragmentNode(mixed $node, int $depth): string
    {
        if ($depth > 64) {
            throw new RenderException('Safe markup exceeds 64 levels.');
        }
        if (!$node instanceof \stdClass) {
            throw new RenderException('Safe markup nodes must be structural objects.');
        }
        if (($node->kind ?? null) === 'text') {
            if (!is_string($node->value ?? null)) {
                throw new RenderException('Safe markup text requires a string value.');
            }

            return self::escapeHtml($node->value);
        }

        return self::renderFragmentElement($node, $depth);
    }

    /**
     * Render one fragment element: the tag must be in the closed
     * vocabulary, every attribute in the tag's allowance with a string
     * value (href additionally vetted against the URL allowlist), at most
     * 10000 children, and void tags childless. Attributes are emitted
     * sorted by name, so identical input renders identical bytes.
     *
     * @param   \stdClass  $node   The structural element node.
     * @param   int        $depth  The element's depth, root children at 1.
     * @return  string  The element's escaped markup.
     * @throws  RenderException  On any tag, attribute, URL, or size
     *     outside the closed vocabulary.
     * @since   0.1.0
     */
    private static function renderFragmentElement(\stdClass $node, int $depth): string
    {
        $tag = $node->tag ?? null;
        if (!is_string($tag) || !in_array($tag, self::ALLOWED_TAGS, true)) {
            throw new RenderException('Safe markup tag ' . (is_string($tag) ? $tag : get_debug_type($tag)) . ' is not allowed.');
        }
        $children = $node->children ?? null;
        if (!is_array($children)) {
            throw new RenderException('Safe markup elements require a children list.');
        }
        if (count($children) > 10000) {
            throw new RenderException('Safe markup element exceeds its child limit.');
        }
        $allowed = self::TAG_ATTRIBUTES[$tag] ?? [];
        $attributeSource = $node->attributes ?? new \stdClass();
        if (!$attributeSource instanceof \stdClass) {
            throw new RenderException('Safe markup attributes must be a name-to-string map.');
        }
        $entries = get_object_vars($attributeSource);
        ksort($entries, SORT_STRING);
        $attributes = '';
        foreach ($entries as $name => $value) {
            $name = (string) $name;
            if (!in_array($name, self::GLOBAL_ATTRIBUTES, true) && !in_array($name, $allowed, true)) {
                throw new RenderException("Attribute {$name} is not allowed on {$tag}.");
            }
            if (!is_string($value)) {
                throw new RenderException("Attribute {$name} requires a string value.");
            }
            if ($name === 'href' && !self::safeHref($value)) {
                throw new RenderException('Safe markup link uses a forbidden URL.');
            }
            $attributes .= ' ' . $name . '="' . self::escapeAttribute($value) . '"';
        }
        if (in_array($tag, self::VOID_TAGS, true)) {
            if ($children !== []) {
                throw new RenderException("Void tag {$tag} cannot have children.");
            }

            return "<{$tag}{$attributes}>";
        }
        $inner = '';
        foreach ($children as $child) {
            $inner .= self::renderFragmentNode($child, $depth + 1);
        }

        return "<{$tag}{$attributes}>{$inner}</{$tag}>";
    }

    /**
     * The link allowlist for fragment `href` values: site-relative paths,
     * fragments, and https origins — the same closed set as
     * {@see self::safeUrl()}, expressed as a predicate.
     *
     * @param   string  $value  The untrusted href candidate.
     * @return  bool  Whether the href is allowed.
     * @since   0.1.0
     */
    private static function safeHref(string $value): bool
    {
        return str_starts_with($value, '/')
            || str_starts_with($value, '#')
            || preg_match('%^https://[A-Za-z0-9.-]+(?::[0-9]+)?(?:[/#?]|$)%u', $value) === 1;
    }
}
