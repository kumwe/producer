<?php

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
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class SafeMarkup
{
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'blockquote', 'br', 'code', 'del', 'em', 'h2', 'h3', 'h4',
        'h5', 'h6', 'hr', 'li', 'mark', 'ol', 'p', 'pre', 'strong', 'sub',
        'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul',
    ];

    private const VOID_TAGS = ['br', 'hr'];

    private const GLOBAL_ATTRIBUTES = ['aria-label', 'dir', 'lang', 'title'];

    private const TAG_ATTRIBUTES = [
        'a' => ['href'],
        'ol' => ['start'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    private function __construct()
    {
    }

    public static function escapeHtml(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    public static function escapeAttribute(string $value): string
    {
        return str_replace(['"', "'"], ['&quot;', '&#39;'], self::escapeHtml($value));
    }

    /**
     * The closed URL allowlist: site-relative paths, fragments, and https
     * origins only. Anything else — javascript:, data:, http:, protocol
     * tricks — is refused and the caller renders inert text instead.
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
     * Media URL vetting: the ordinary allowlist first, then — only under the
     * host's explicit blob authority — well-formed blob: URLs whose media
     * type cannot execute (SVG, HTML, and XHTML blobs stay refused).
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
     * ECMAScript-style number-to-string used wherever the reference renderer
     * interpolates a number: integer values print without a fraction.
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
     * than a canonical rich-text document.
     */
    public static function isFragment(mixed $value): bool
    {
        return $value instanceof \stdClass
            && ($value->kind ?? null) === 'safe-markup-fragment'
            && is_array($value->nodes ?? null)
            && is_string($value->policy ?? null);
    }

    /**
     * Render an already structural safe fragment, failing closed on unknown
     * HTML vocabulary. Raw HTML strings are never accepted.
     */
    public static function renderFragment(\stdClass $fragment): string
    {
        $policy = $fragment->policy ?? null;
        if (!is_string($policy) || preg_match('%^[a-z][a-z0-9.-]{0,126}/[a-z][a-z0-9.-]{0,126}$%u', $policy) !== 1) {
            throw new RenderException('Safe markup requires a qualified policy identifier.');
        }
        $out = '';
        foreach ($fragment->nodes ?? [] as $node) {
            $out .= self::renderFragmentNode($node, 1);
        }

        return $out;
    }

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

    private static function safeHref(string $value): bool
    {
        return str_starts_with($value, '/')
            || str_starts_with($value, '#')
            || preg_match('%^https://[A-Za-z0-9.-]+(?::[0-9]+)?(?:[/#?]|$)%u', $value) === 1;
    }
}
