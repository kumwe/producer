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

/**
 * Renders the interactive family on a no-JavaScript-first contract.
 *
 * Every block is usable as plain HTML — `details`/`summary` disclosure,
 * native forms and `progress`, visible content in document flow — and each
 * progressive behaviour is requested from the enhancement runtime by name,
 * scope, and data attributes only; no script is ever emitted. Link and form
 * URLs pass the closed URL allowlist, and a refused URL degrades to inert
 * text or an omitted attribute.
 *
 * @since   0.1.0
 */
final class InteractiveBlocks implements BlockRenderer
{
    /**
     * The fourteen interactive types this renderer serves, from accordion
     * through countdown.
     *
     * @return  list<string>  The block type identifiers, in catalog order.
     *
     * @since   0.1.0
     */
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

    /**
     * Dispatches one interactive node to its type-specific renderer.
     *
     * The accordion itself has no wrapper of its own: it renders its `items`
     * slot directly, each item supplying its own `details` disclosure.
     *
     * @param   \stdClass    $node   The block node; its type must be one the
     *                               renderer lists, or the match refuses it.
     * @param   string       $scope  The node's unique scope token, used to key
     *                               enhancement requests and generated
     *                               labelling ids.
     * @param   RenderState  $state  Engine services and per-render accumulators.
     *
     * @return  string  The node's inner semantic HTML, every dynamic value
     *                  escaped, functional without JavaScript.
     *
     * @throws  \UnhandledMatchError  When the node's type is not one this
     *                                renderer declared in {@see types()}.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders one accordion item to a native `details`/`summary` disclosure —
     * fully functional without JavaScript, so no enhancement is requested.
     *
     * The escaped bound title is the `summary`; the `content` slot renders
     * inside. The `open` attribute is a strict opt-in: only `expanded`
     * stored exactly `true` starts the item expanded.
     *
     * @param   \stdClass    $node   The accordion-item node.
     * @param   RenderState  $state  Resolves the title binding and renders
     *                               the content slot.
     *
     * @return  string  The `details` markup.
     *
     * @since   0.1.0
     */
    private function accordionItem(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));

        return '<details' . (Properties::property($node, 'expanded') === true ? ' open' : '')
            . '><summary>' . SafeMarkup::escapeHtml($title) . '</summary>'
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></details>';
    }

    /**
     * Renders a tab group whose no-JavaScript fallback is every panel
     * stacked in document flow under its own heading.
     *
     * The tab buttons render inside a `hidden` tab list of inert
     * `type="button"` controls, indexed by position; the 'tabs' enhancement
     * (requested with the activation mode, 'automatic' unless the property
     * is exactly 'manual') reveals the list and wires the switching. Button
     * captions reuse each tab node's escaped bound title.
     *
     * @param   \stdClass    $node   The tabs node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Reads the `items` slot, resolves titles,
     *                               renders the panels, and receives the
     *                               enhancement request.
     *
     * @return  string  The tab group markup.
     *
     * @since   0.1.0
     */
    private function tabs(\stdClass $node, string $scope, RenderState $state): string
    {
        $activation = Properties::property($node, 'activation') === 'manual' ? 'manual' : 'automatic';
        $tabNodes = $state->slotNodes($node, 'items');
        if ($tabNodes !== []) {
            $state->enhance('tabs', $node, $scope, ['activation' => $activation]);
        }
        $buttons = '';
        foreach ($tabNodes as $index => $tabNode) {
            $buttons .= '<button type="button" data-studio-tab="' . $index . '">'
                . SafeMarkup::escapeHtml(Properties::stringValue($state->bindingValue($tabNode, 'title')))
                . '</button>';
        }

        return '<div data-studio-tabs data-studio-tabs-activation="' . $activation
            . '"><div data-studio-tab-list hidden>' . $buttons . '</div>'
            . '<div data-studio-part="content">' . $state->renderNodes($tabNodes) . '</div></div>';
    }

    /**
     * Renders one tab panel to a `section` with an escaped `h3` title and
     * its `content` slot.
     *
     * The heading keeps the panel navigable when the panels render stacked
     * without JavaScript; the enhancement hides and shows whole panels and
     * needs nothing further from this markup.
     *
     * @param   \stdClass    $node   The tab node.
     * @param   RenderState  $state  Resolves the title binding and renders
     *                               the content slot.
     *
     * @return  string  The tab panel `section` markup.
     *
     * @since   0.1.0
     */
    private function tab(\stdClass $node, RenderState $state): string
    {
        $title = Properties::stringValue($state->bindingValue($node, 'title'));

        return '<section data-studio-tab-panel><h3 data-studio-part="heading">'
            . SafeMarkup::escapeHtml($title) . '</h3>'
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content') . '</div></section>';
    }

    /**
     * Renders a dialog on a `details`/`summary` disclosure so the trigger
     * opens and closes it without JavaScript.
     *
     * The panel is a `section` with `role="dialog"`, `aria-modal` mirroring
     * the modal flag (modal unless `modal` is stored `false`), and
     * `aria-labelledby` bound to a scope-derived title id, so the labelling
     * ids stay unique per node. Presentation is coerced into
     * modal/offcanvas/overlay (fallback modal). The 'dialog' enhancement is
     * requested with the modal flag to add focus containment and dismissal;
     * its Close button is an inert `type="button"` control until then.
     * Trigger and title fall back to "Open dialog" and "Dialog" when
     * unbound or falsy.
     *
     * @param   \stdClass    $node   The dialog node.
     * @param   string       $scope  Keys the enhancement request and the
     *                               title id to this node.
     * @param   RenderState  $state  Resolves bindings, renders the content
     *                               slot, and receives the enhancement
     *                               request.
     *
     * @return  string  The dialog `details` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a popover on a `details`/`summary` disclosure so the trigger
     * toggles it without JavaScript.
     *
     * Placement is coerced into auto/bottom/left/right/top (fallback auto);
     * presentation admits dropbar/dropdown/tooltip and renders as the
     * generic 'popover' for any other stored value. The panel's role is
     * `tooltip` for the tooltip presentation and `region` otherwise, and it
     * is always labelled via a scope-derived id — an empty title labels the
     * panel with a visually hidden copy of the trigger text instead of a
     * heading. The 'popover' enhancement is requested with the presentation
     * and the dismiss-on-blur flag (on unless stored `false`).
     *
     * @param   \stdClass    $node   The popover node.
     * @param   string       $scope  Keys the enhancement request and the
     *                               title id to this node.
     * @param   RenderState  $state  Resolves bindings, renders the content
     *                               slot, and receives the enhancement
     *                               request.
     *
     * @return  string  The popover `details` markup.
     *
     * @since   0.1.0
     */
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
        $dismissOnBlur = Properties::property($node, 'dismiss-on-blur') !== false;
        $state->enhance('popover', $node, $scope, [
            'dismissOnBlur' => $dismissOnBlur,
            'presentation' => $presentation,
        ]);
        $titleMarkup = $title === ''
            ? '<span class="studio-visually-hidden" id="' . $scope . '-popover-title">'
                . SafeMarkup::escapeHtml($trigger) . '</span>'
            : '<h3 data-studio-part="heading" id="' . $scope . '-popover-title">'
                . SafeMarkup::escapeHtml($title) . '</h3>';

        return '<details data-studio-popover data-studio-popover-placement="' . $placement
            . '" data-studio-popover-presentation="' . $presentation
            . '" data-studio-popover-dismiss-on-blur="' . ($dismissOnBlur ? 'true' : 'false') . '">'
            . '<summary data-studio-popover-trigger>' . SafeMarkup::escapeHtml($trigger) . '</summary>'
            . '<aside data-studio-popover-panel role="' . ($presentation === 'tooltip' ? 'tooltip' : 'region')
            . '" aria-labelledby="' . $scope . '-popover-title" tabindex="-1">' . $titleMarkup
            . '<div data-studio-part="content">' . $state->renderChildren($node, 'content')
            . '</div></aside></details>';
    }

    /**
     * Renders a notice to a toned `aside` live region with a rich-text body
     * and an optional dismiss affordance.
     *
     * Tone is coerced into comment/error/information/success/warning
     * (fallback information); error and warning announce assertively
     * (`role="alert"`, `aria-live="assertive"`), everything else politely as
     * status. The body renders through the validating rich-text grammar and
     * falls back to escaped plain text when the document is refused. Only
     * `dismissible` stored exactly `true` renders the Dismiss button and
     * requests the 'notice' enhancement — without it the button would be
     * inert, so neither is emitted.
     *
     * @param   \stdClass    $node   The notice node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Resolves bindings and receives the
     *                               enhancement request.
     *
     * @return  string  The notice `aside` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a navigation block to a labelled `nav` holding a `ul` of its
     * item nodes.
     *
     * Presentation is coerced into the closed navigation vocabulary
     * (fallback 'nav'), and the accessible label falls back to "Navigation"
     * when unbound or falsy. The 'navigation' enhancement is requested only
     * when at least one item carries children — flat navigations need no
     * script, and nested child lists remain visible without one.
     *
     * @param   \stdClass    $node   The navigation node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Reads the `items` slot, renders the
     *                               items, and receives the enhancement
     *                               request.
     *
     * @return  string  The `nav` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders one navigation item as a vetted link (or inert text) plus its
     * optional nested child list.
     *
     * The `href` passes the closed URL allowlist; a refused or missing URL
     * renders the escaped label in a `span` instead of an anchor, so an
     * unsafe scheme can never become a link. `current` stored exactly `true`
     * adds `aria-current="page"`. Items with children append an inert
     * labelled toggle button (wired by the parent navigation's enhancement)
     * and the child `ul`, which stays visible without JavaScript.
     *
     * @param   \stdClass    $node   The navigation-item node.
     * @param   RenderState  $state  Resolves the label binding and renders
     *                               the children slot.
     *
     * @return  string  The item's inner markup for its parent `ul`.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a call to action as an anchor with a vetted URL, or inert
     * labelled text when the URL is refused.
     *
     * The `href` passes the closed URL allowlist (site-relative, fragment,
     * or https only); a refused or missing URL renders the escaped label in
     * a `span` — the block never emits a link it cannot vouch for.
     *
     * @param   \stdClass    $node   The call-to-action node.
     * @param   RenderState  $state  Resolves the label binding.
     *
     * @return  string  The action anchor or `span` markup.
     *
     * @since   0.1.0
     */
    private function callToAction(\stdClass $node, RenderState $state): string
    {
        $label = Properties::stringValue($state->bindingValue($node, 'label'));
        $href = SafeMarkup::safeUrl(Properties::stringProperty(Properties::property($node, 'href'), ''));

        return $href === null
            ? '<span data-studio-part="action">' . SafeMarkup::escapeHtml($label) . '</span>'
            : '<a data-studio-part="action" href="' . SafeMarkup::escapeAttribute($href) . '">'
                . SafeMarkup::escapeHtml($label) . '</a>';
    }

    /**
     * Renders search as a plain GET form with `role="search"` — submission
     * needs no JavaScript and no enhancement is requested.
     *
     * The form action passes the closed URL allowlist; a refused action is
     * omitted entirely, so the form submits to the current URL rather than
     * anywhere unvetted. The query parameter name must match a bounded
     * `[A-Za-z][A-Za-z0-9_-]{0,99}` shape or it falls back to `q`. The
     * visible label falls back to "Search", and a non-empty bound
     * placeholder is escaped into the input.
     *
     * @param   \stdClass    $node   The search node.
     * @param   RenderState  $state  Resolves the label and placeholder
     *                               bindings.
     *
     * @return  string  The search `form` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a spinner as a `role="status"` region: a decorative CSS
     * spinner with its label visually hidden, or the label as plain text
     * when inactive.
     *
     * `active` stored exactly `false` renders only the escaped label text —
     * no spinner element at all. Otherwise the animated element is
     * `aria-hidden` and the label (fallback "Loading") stays available to
     * assistive technology. Size is coerced into large/medium/small
     * (fallback medium). Animation is pure CSS; nothing is enhanced.
     *
     * @param   \stdClass    $node   The spinner node.
     * @param   RenderState  $state  Resolves the label binding.
     *
     * @return  string  The status `span` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders progress as a native labelled `progress` element with the
     * value bounded before it is emitted.
     *
     * The maximum comes from the `maximum` property clamped to 1–1000000
     * (fallback 100). The bound value is accepted only as a finite int or
     * float and clamped into 0..maximum; any other value renders as 0. The
     * element's text content repeats "value / maximum" as the fallback for
     * user agents without `progress` support. No enhancement is requested.
     *
     * @param   \stdClass    $node   The progress node.
     * @param   RenderState  $state  Resolves the value and label bindings.
     *
     * @return  string  The labelled `progress` markup.
     *
     * @since   0.1.0
     */
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

    /**
     * Renders a countdown to a `time` element whose no-JavaScript fallback
     * is the normalized target timestamp itself.
     *
     * The bound target must be an absolute ISO-8601-style timestamp; a
     * relative phrase or unparseable value renders
     * `<span role="status">Countdown unavailable</span>` — the library never
     * reads a clock, so ticking is entirely the enhancement's job. The
     * 'countdown' enhancement is requested with the UTC-normalized target,
     * the display mode ('compact' only when stored exactly so, else
     * 'detailed'), the expired behaviour coerced into hide/message/zero
     * (fallback zero), and the completion message, which also renders
     * hidden inside the element for the runtime to reveal. The region is
     * `aria-live="polite"`.
     *
     * @param   \stdClass    $node   The countdown node.
     * @param   string       $scope  Keys the enhancement request to this node.
     * @param   RenderState  $state  Resolves bindings and receives the
     *                               enhancement request.
     *
     * @return  string  The `time` markup, or the unavailable fallback.
     *
     * @since   0.1.0
     */
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

        return '<time data-studio-countdown data-studio-countdown-display="' . $display
            . '" data-studio-countdown-expired-behavior="' . $expiredBehavior
            . '" datetime="' . $targetIso . '" aria-live="polite">'
            . '<span data-studio-countdown-value>' . SafeMarkup::escapeHtml($targetIso) . '</span>'
            . '<span data-studio-countdown-complete hidden>' . SafeMarkup::escapeHtml($completionMessage)
            . '</span></time>';
    }

    /**
     * The parsed target normalized to the reference's UTC ISO-8601 form with
     * millisecond precision; anything but an absolute ISO-8601-style
     * timestamp renders the unavailable fallback. (Relative date phrases are
     * refused so the rendered bytes stay a pure function of the input.)
     *
     * @param   string  $value  The bound target string, taken exactly as
     *                          stored.
     *
     * @return  ?string  The target in UTC `Y-m-d\TH:i:s.v\Z` form, or null
     *                   when the value is not an absolute, parseable
     *                   ISO-8601-style timestamp.
     *
     * @since   0.1.0
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
