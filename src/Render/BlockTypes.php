<?php

/**
 * The canonical Studio core block type identifiers.
 *
 * One constant per first-party block from the pinned catalog (layout family
 * plus production family), mirroring CORE_PRODUCTION_BLOCK_TYPES in the
 * Studio contract.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class BlockTypes
{
    public const ACCORDION = 'studio.core/accordion';
    public const ACCORDION_ITEM = 'studio.core/accordion-item';
    public const ARTICLE = 'studio.core/article';
    public const ATTACHMENT = 'studio.core/attachment';
    public const AUDIO = 'studio.core/audio';
    public const BADGE = 'studio.core/badge';
    public const CALL_TO_ACTION = 'studio.core/call-to-action';
    public const CALLOUT = 'studio.core/callout';
    public const CARD = 'studio.core/card';
    public const CHART = 'studio.core/chart';
    public const CODE = 'studio.core/code';
    public const COLUMNS = 'studio.core/columns';
    public const CONTENT_COLLECTION = 'studio.core/content-collection';
    public const CONTENT_REFERENCE = 'studio.core/content-reference';
    public const COUNTDOWN = 'studio.core/countdown';
    public const COVER = 'studio.core/cover';
    public const DESCRIPTION_ITEM = 'studio.core/description-item';
    public const DESCRIPTION_LIST = 'studio.core/description-list';
    public const DIAGRAM = 'studio.core/diagram';
    public const DIALOG = 'studio.core/dialog';
    public const DIVIDER = 'studio.core/divider';
    public const DRAWING = 'studio.core/drawing';
    public const EMBED = 'studio.core/embed';
    public const GALLERY = 'studio.core/gallery';
    public const GRID = 'studio.core/grid';
    public const HEADING = 'studio.core/heading';
    public const ICON = 'studio.core/icon';
    public const IMAGE = 'studio.core/image';
    public const LABEL = 'studio.core/label';
    public const MATH = 'studio.core/math';
    public const MONEY = 'studio.core/money';
    public const NAVIGATION = 'studio.core/navigation';
    public const NAVIGATION_ITEM = 'studio.core/navigation-item';
    public const NOTICE = 'studio.core/notice';
    public const POPOVER = 'studio.core/popover';
    public const PROGRESS = 'studio.core/progress';
    public const RICH_TEXT = 'studio.core/rich-text';
    public const SEARCH = 'studio.core/search';
    public const SECTION = 'studio.core/section';
    public const SPINNER = 'studio.core/spinner';
    public const STACK = 'studio.core/stack';
    public const TAB = 'studio.core/tab';
    public const TABLE = 'studio.core/table';
    public const TABS = 'studio.core/tabs';
    public const VIDEO = 'studio.core/video';

    private function __construct()
    {
    }

    /**
     * @return list<string> every canonical core block type identifier
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
