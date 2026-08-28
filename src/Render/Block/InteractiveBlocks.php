<?php

/**
 * The interactive family: accordion, tabs, dialog, popover, notice,
 * navigation, call to action, search, spinner, progress, and countdown.
 *
 * Every block works without JavaScript — details/summary disclosure, plain
 * forms, native progress — and requests its progressive behavior from
 * Studio's prebuilt enhancement runtime by name and data attribute only.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render\Block;

use Kumwe\Producer\Render\BlockRenderer;
use Kumwe\Producer\Render\BlockTypes;
use Kumwe\Producer\Render\Properties;
use Kumwe\Producer\Render\RenderState;
use Kumwe\Producer\Render\RichText;
use Kumwe\Producer\Render\SafeMarkup;

final class InteractiveBlocks implements BlockRenderer
{
    public function types(): array
    {
        return [
            BlockTypes::ACCORDION,
            BlockTypes::ACCORDION_ITEM,
            BlockTypes::TABS,
            BlockTypes::TAB,
            BlockTypes::DIALOG,
            BlockTypes::POPOVER,
            BlockTypes::NOTICE,
            BlockTypes::NAVIGATION,
            BlockTypes::NAVIGATION_ITEM,
            BlockTypes::CALL_TO_ACTION,
            BlockTypes::SEARCH,
            BlockTypes::SPINNER,
            BlockTypes::PROGRESS,
            BlockTypes::COUNTDOWN,
        ];
    }

    public function render(\stdClass $node, string $scope, RenderState $state): string
    {
        return match ($node->type) {
            BlockTypes::ACCORDION => $state->renderChildren($node, 'items'),
            BlockTypes::ACCORDION_ITEM => $this->accordionItem($node, $state),
            BlockTypes::TABS => $this->tabs($node, $scope, $state),
            BlockTypes::TAB => $this->tab($node, $state),
            BlockTypes::DIALOG => $this->dialog($node, $scope, $state),
            BlockTypes::POPOVER => $this->popover($node, $scope, $state),
            BlockTypes::NOTICE => $this->notice($node, $scope, $state),
            BlockTypes::NAVIGATION => $this->navigation($node, $scope, $state),
            BlockTypes::NAVIGATION_ITEM => $this->navigationItem($node, $state),
            BlockTypes::CALL_TO_ACTION => $this->callToAction($node, $state),
            BlockTypes::SEARCH => $this->search($node, $state),
            BlockTypes::SPINNER => $this->spinner($node, $state),
            BlockTypes::PROGRESS => $this->progress($node, $state),
            BlockTypes::COUNTDOWN => $this->countdown($node, $scope, $state),
        };
    }

    private function accordionItem(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));

        return '<details' . (Properties::property($node, 'expanded') === true ? ' open' : '')
            . '><summary>' . SafeMarkup::escapeHtml($title) . '</summary>'
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></details>';
    }

    private function tabs(\stdClass $node, string $scope, RenderState $state): string
    {
        $activation = Properties::property($node, 'activation') === 'manual' ? 'manual' : 'automatic';
        $state->enhance('tabs', $node, $scope, ['activation' => $activation]);
        $tabNodes = $state->slotNodes($node, 'items');
        $buttons = '';
        foreach ($tabNodes as $index => $tabNode) {
            $buttons .= '<button type="button" data-studio-tab="' . $index . '">'
                . SafeMarkup::escapeHtml(Properties::stringValue($state->bindingValue($tabNode, 'title')))
                . '</button>';
        }

        return '<div data-studio-tabs><div data-studio-tab-list hidden>' . $buttons . '</div>'
            . '<div data-studio-part="content">' . $state->renderNodes($tabNodes) . '</div></div>';
    }

    private function tab(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));

        return '<section data-studio-tab-panel><h3 data-studio-part="heading">'
            . SafeMarkup::escapeHtml($title) . '</h3>'
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></section>';
    }

    private function dialog(\stdClass $node, string $scope, RenderState $state): string
    {
        $trigger = Properties::stringValueOr($state->bindingValue($node, 'trigger-label'), 'Open dialog');
        $title = Properties::stringValueOr($state->bindingValue($node, 'title'), 'Dialog');
        $modal = Properties::property($node, 'modal') !== false;
        $presentation = Properties::enumProperty(
            Properties::property($node, 'presentation'),
            ['modal', 'offcanvas', 'overlay'],
            'modal'
        );
        $state->enhance('dialog', $node, $scope, ['modal' => $modal]);
        $modalText = $modal ? 'true' : 'false';

        return '<details data-studio-dialog data-studio-dialog-modal="' . $modalText
            . '" data-studio-dialog-presentation="' . $presentation . '">'
            . '<summary data-studio-dialog-trigger>' . SafeMarkup::escapeHtml($trigger) . '</summary>'
            . '<section data-studio-dialog-panel role="dialog" aria-modal="' . $modalText
            . '" aria-labelledby="' . $scope . '-dialog-title" tabindex="-1">'
            . '<h2 data-studio-part="heading" id="' . $scope . '-dialog-title">'
            . SafeMarkup::escapeHtml($title) . '</h2>'
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div>'
            . '<button type="button" data-studio-dialog-close>Close</button></section></details>';
    }

    private function popover(\stdClass $node, string $scope, RenderState $state): string
    {
        $trigger = Properties::stringValueOr($state->bindingValue($node, 'trigger-label'), 'Show details');
        $title = Properties::stringValue($state->bindingValue($node, 'title'));
        $placement = Properties::enumProperty(
            Properties::property($node, 'placement'),
            ['auto', 'bottom', 'left', 'right', 'top'],
            'auto'
        );
        $presentation = Properties::enumProperty(
            Properties::property($node, 'presentation'),
            ['dropbar', 'dropdown', 'tooltip'],
            'popover'
        );
        $state->enhance('popover', $node, $scope, [
            'dismissOnBlur' => Properties::property($node, 'dismiss-on-blur') !== false,
            'presentation' => $presentation,
        ]);
        $titleMarkup = $title === ''
            ? '<span class="studio-visually-hidden" id="' . $scope . '-popover-title">'
                . SafeMarkup::escapeHtml($trigger) . '</span>'
            : '<h3 data-studio-part="heading" id="' . $scope . '-popover-title">'
                . SafeMarkup::escapeHtml($title) . '</h3>';

        return '<details data-studio-popover data-studio-popover-placement="' . $placement
            . '" data-studio-popover-presentation="' . $presentation . '">'
            . '<summary data-studio-popover-trigger>' . SafeMarkup::escapeHtml($trigger) . '</summary>'
            . '<aside data-studio-popover-panel role="' . ($presentation === 'tooltip' ? 'tooltip' : 'region')
            . '" aria-labelledby="' . $scope . '-popover-title" tabindex="-1">' . $titleMarkup
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content')
            . '</div></aside></details>';
    }

    private function notice(\stdClass $node, string $scope, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));
        $content = $state->bindingValue($node, 'content');
        $tone = Properties::enumProperty(
            Properties::property($node, 'tone'),
            ['comment', 'error', 'information', 'success', 'warning'],
            'information'
        );
        $assertive = $tone === 'error' || $tone === 'warning';
        $dismissible = Properties::property($node, 'dismissible') === true;
        if ($dismissible) {
            $state->enhance('notice', $node, $scope);
        }
        try {
            $body = RichText::render(RichText::parse($content));
        } catch (\Throwable) {
            $body = SafeMarkup::escapeHtml(Properties::stringValue($content));
        }

        return '<aside data-studio-notice data-studio-tone="' . $tone . '" role="'
            . ($assertive ? 'alert' : 'status') . '" aria-live="' . ($assertive ? 'assertive' : 'polite') . '">'
            . ($title === '' ? '' : '<h3 data-studio-part="heading">' . SafeMarkup::escapeHtml($title) . '</h3>')
            . '<div data-studio-part="content">' . $body . '</div>'
            . ($dismissible ? '<button type="button" data-studio-notice-dismiss>Dismiss</button>' : '')
            . '</aside>';
    }

    private function navigation(\stdClass $node, string $scope, RenderState $state): string
    {
        $presentation = Properties::enumProperty(
            Properties::property($node, 'presentation'),
            ['breadcrumbs', 'dotnav', 'dropnav', 'navbar', 'nav', 'pagination', 'subnav', 'thumbnav'],
            'nav'
        );
        $label = Properties::stringValueOr($state->bindingValue($node, 'label'), 'Navigation');
        $items = $state->slotNodes($node, 'items');
        foreach ($items as $item) {
            if ($item instanceof \stdClass && $state->slotNodes($item, 'children') !== []) {
                $state->enhance('navigation', $node, $scope);
                break;
            }
        }

        return '<nav data-studio-navigation="' . $presentation . '" aria-label="'
            . SafeMarkup::escapeAttribute($label) . '"><ul>' . $state->renderNodes($items) . '</ul></nav>';
    }

    private function navigationItem(\stdClass $node, RenderState $state): string
    {
        $label = Properties::stringValue($state->bindingValue($node, 'label'));
        $href = SafeMarkup::safeUrl(Properties::stringProperty(Properties::property($node, 'href'), ''));
        $labelMarkup = $href === null
            ? '<span>' . SafeMarkup::escapeHtml($label) . '</span>'
            : '<a href="' . SafeMarkup::escapeAttribute($href) . '"'
                . (Properties::property($node, 'current') === true ? ' aria-current="page"' : '') . '>'
                . SafeMarkup::escapeHtml($label) . '</a>';
        $children = $state->slotNodes($node, 'children');
        if ($children === []) {
            return $labelMarkup;
        }

        return $labelMarkup . '<button type="button" data-studio-navigation-toggle aria-label="Toggle '
            . SafeMarkup::escapeAttribute($label) . ' navigation">Expand</button>'
            . '<ul data-studio-navigation-children>' . $state->renderNodes($children) . '</ul>';
    }

    private function callToAction(\stdClass $node, RenderState $state): string
    {
        $label = Properties::stringValue($state->bindingValue($node, 'label'));
        $href = SafeMarkup::safeUrl(Properties::stringProperty(Properties::property($node, 'href'), ''));

        return $href === null
            ? '<span data-studio-part="action">' . SafeMarkup::escapeHtml($label) . '</span>'
            : '<a data-studio-part="action" href="' . SafeMarkup::escapeAttribute($href) . '">'
                . SafeMarkup::escapeHtml($label) . '</a>';
    }

    private function search(\stdClass $node, RenderState $state): string
    {
        $action = SafeMarkup::safeUrl(Properties::stringProperty(Properties::property($node, 'action'), ''));
        $candidate = Properties::stringProperty(Properties::property($node, 'query-parameter'), 'q');
        $parameter = preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,99}$/u', $candidate) === 1 ? $candidate : 'q';
        $label = Properties::stringValueOr($state->bindingValue($node, 'label'), 'Search');
        $placeholder = Properties::stringValue($state->bindingValue($node, 'placeholder'));

        return '<form role="search" method="get"'
            . ($action === null ? '' : ' action="' . SafeMarkup::escapeAttribute($action) . '"')
            . '><label>' . SafeMarkup::escapeHtml($label) . ' <input type="search" name="' . $parameter . '"'
            . ($placeholder === '' ? '' : ' placeholder="' . SafeMarkup::escapeAttribute($placeholder) . '"')
            . '></label><button type="submit">Search</button></form>';
    }

    private function spinner(\stdClass $node, RenderState $state): string
    {
        $label = Properties::stringValueOr($state->bindingValue($node, 'label'), 'Loading');
        if (Properties::property($node, 'active') === false) {
            return '<span role="status">' . SafeMarkup::escapeHtml($label) . '</span>';
        }
        $size = Properties::enumProperty(
            Properties::property($node, 'size'),
            ['large', 'medium', 'small'],
            'medium'
        );

        return '<span role="status"><span data-studio-spinner data-studio-spinner-size="' . $size
            . '" aria-hidden="true"></span><span class="studio-visually-hidden">'
            . SafeMarkup::escapeHtml($label) . '</span></span>';
    }

    private function progress(\stdClass $node, RenderState $state): string
    {
        $maximum = Properties::integerProperty(Properties::property($node, 'maximum'), 1, 1000000, 100);
        $candidate = $state->bindingValue($node, 'value');
        $value = (is_int($candidate) || (is_float($candidate) && is_finite($candidate)))
            ? max(0, min($maximum, $candidate))
            : 0;
        $label = Properties::stringValueOr($state->bindingValue($node, 'label'), 'Progress');
        $valueText = SafeMarkup::number($value);

        return '<label>' . SafeMarkup::escapeHtml($label) . ' <progress max="' . $maximum
            . '" value="' . $valueText . '">' . $valueText . ' / ' . $maximum . '</progress></label>';
    }

    private function countdown(\stdClass $node, string $scope, RenderState $state): string
    {
        $target = Properties::stringValue($state->bindingValue($node, 'target'));
        $targetIso = self::isoTimestamp($target);
        if ($targetIso === null) {
            return '<span role="status">Countdown unavailable</span>';
        }
        $completionMessage = Properties::stringValue($state->bindingValue($node, 'completion-message'));
        $display = Properties::property($node, 'display') === 'compact' ? 'compact' : 'detailed';
        $expiredBehavior = Properties::enumProperty(
            Properties::property($node, 'expired-behavior'),
            ['hide', 'message', 'zero'],
            'zero'
        );
        $state->enhance('countdown', $node, $scope, [
            'completionMessage' => $completionMessage,
            'display' => $display,
            'expiredBehavior' => $expiredBehavior,
            'target' => $targetIso,
        ]);

        return '<time data-studio-countdown datetime="' . $targetIso . '" aria-live="polite">'
            . '<span data-studio-countdown-value>' . SafeMarkup::escapeHtml($targetIso) . '</span>'
            . '<span data-studio-countdown-complete hidden>' . SafeMarkup::escapeHtml($completionMessage)
            . '</span></time>';
    }

    /**
     * The parsed target normalized to the reference's UTC ISO-8601 form with
     * millisecond precision; anything but an absolute ISO-8601-style
     * timestamp renders the unavailable fallback. (Relative date phrases are
     * refused so the rendered bytes stay a pure function of the input.)
     */
    private static function isoTimestamp(string $value): ?string
    {
        $absolute = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';
        if (preg_match($absolute, $value) !== 1) {
            return null;
        }
        try {
            $moment = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }

        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}
