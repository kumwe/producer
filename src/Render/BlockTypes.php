<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * The canonical Studio core block type identifiers.
 *
 * One constant per first-party block from the pinned catalog (layout family
 * plus production family), mirroring CORE_PRODUCTION_BLOCK_TYPES in the
 * Studio contract. The values are contract-pinned strings: stable across
 * releases and never invented, renamed, or extended outside the contract.
 *
 * @since   0.1.0
 */
final class BlockTypes
{
    /**
     * The accordion disclosure-group container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const ACCORDION = 'studio.core/accordion';

    /**
     * One expandable item inside an accordion block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const ACCORDION_ITEM = 'studio.core/accordion-item';

    /**
     * The long-form article content block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const ARTICLE = 'studio.core/article';

    /**
     * The downloadable file attachment block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const ATTACHMENT = 'studio.core/attachment';

    /**
     * The audio playback block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const AUDIO = 'studio.core/audio';

    /**
     * The inline status badge block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const BADGE = 'studio.core/badge';

    /**
     * The prominent call-to-action link block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CALL_TO_ACTION = 'studio.core/call-to-action';

    /**
     * The emphasized callout aside block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CALLOUT = 'studio.core/callout';

    /**
     * The card container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CARD = 'studio.core/card';

    /**
     * The typed data chart block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CHART = 'studio.core/chart';

    /**
     * The preformatted code listing block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CODE = 'studio.core/code';

    /**
     * The multi-column layout container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const COLUMNS = 'studio.core/columns';

    /**
     * The block listing several referenced content resources. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CONTENT_COLLECTION = 'studio.core/content-collection';

    /**
     * The block referencing one resolved content resource. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const CONTENT_REFERENCE = 'studio.core/content-reference';

    /**
     * The countdown block targeting a fixed moment. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const COUNTDOWN = 'studio.core/countdown';

    /**
     * The full-bleed cover block with a media background. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const COVER = 'studio.core/cover';

    /**
     * One term-and-description pair inside a description list. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const DESCRIPTION_ITEM = 'studio.core/description-item';

    /**
     * The description list container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const DESCRIPTION_LIST = 'studio.core/description-list';

    /**
     * The diagram block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const DIAGRAM = 'studio.core/diagram';

    /**
     * The dialog disclosure block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const DIALOG = 'studio.core/dialog';

    /**
     * The thematic divider block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const DIVIDER = 'studio.core/divider';

    /**
     * The bounded vector-stroke drawing block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const DRAWING = 'studio.core/drawing';

    /**
     * The external embed block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const EMBED = 'studio.core/embed';

    /**
     * The media gallery block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const GALLERY = 'studio.core/gallery';

    /**
     * The grid layout container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const GRID = 'studio.core/grid';

    /**
     * The section heading block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const HEADING = 'studio.core/heading';

    /**
     * The icon block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const ICON = 'studio.core/icon';

    /**
     * The image block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const IMAGE = 'studio.core/image';

    /**
     * The short label block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const LABEL = 'studio.core/label';

    /**
     * The mathematical notation block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const MATH = 'studio.core/math';

    /**
     * The exact-decimal money value block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const MONEY = 'studio.core/money';

    /**
     * The navigation container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const NAVIGATION = 'studio.core/navigation';

    /**
     * One entry inside a navigation block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const NAVIGATION_ITEM = 'studio.core/navigation-item';

    /**
     * The toned notice block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const NOTICE = 'studio.core/notice';

    /**
     * The popover disclosure block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const POPOVER = 'studio.core/popover';

    /**
     * The progress indicator block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const PROGRESS = 'studio.core/progress';

    /**
     * The portable rich-text block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const RICH_TEXT = 'studio.core/rich-text';

    /**
     * The search form block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const SEARCH = 'studio.core/search';

    /**
     * The section layout container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const SECTION = 'studio.core/section';

    /**
     * The loading spinner block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const SPINNER = 'studio.core/spinner';

    /**
     * The vertical stack layout container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const STACK = 'studio.core/stack';

    /**
     * One tab panel inside a tabs block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const TAB = 'studio.core/tab';

    /**
     * The typed data table block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const TABLE = 'studio.core/table';

    /**
     * The tabbed container block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const TABS = 'studio.core/tabs';

    /**
     * The video playback block. Pinned by the Studio
     * contract; the value never changes.
     *
     * @since   0.1.0
     */
    public const VIDEO = 'studio.core/video';

    /**
     * Static identifier catalog; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * The complete pinned catalog in one deterministic order — the
     * constants' declaration (alphabetical) order.
     *
     * @return  list<string>  Every canonical core block type identifier.
     * @since   0.1.0
     */
    public static function all(): array
    {
        $types = [];
        foreach ((new \ReflectionClass(self::class))->getConstants() as $value) {
            if (!is_string($value)) {
                throw new \LogicException('The core block catalog may contain only string identifiers.');
            }
            $types[] = $value;
        }

        return $types;
    }
}
