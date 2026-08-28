<?php

/**
 * The static base stylesheet of the semantic web profile.
 *
 * A byte-for-byte port of the reference renderer's BASE_CSS: the
 * data-attribute presentation vocabulary, the layout primitives, the
 * component scaffolding, and the prefers-reduced-motion overrides. It is a
 * constant — no per-request computation and no stored styles ever reach it.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Css;

final class BaseStylesheet
{
    private const CSS = <<<'CSS'
[data-studio-block]{box-sizing:border-box;min-inline-size:0}
.studio-visually-hidden{block-size:1px;clip-path:inset(50%);inline-size:1px;overflow:hidden;position:absolute;white-space:nowrap}
[data-studio-align="center"]{text-align:center}[data-studio-align="end"]{text-align:end}[data-studio-align="stretch"]{align-self:stretch}
[data-studio-height="content"]{block-size:fit-content}[data-studio-height="full"]{block-size:100%}[data-studio-height="viewport"]{min-block-size:100dvb}
[data-studio-inverse="true"]{background:var(--studio-inverse-background,CanvasText);color:var(--studio-inverse-foreground,Canvas)}
[data-studio-margin="none"]{margin:0}[data-studio-margin="compact"]{margin:.5rem}[data-studio-margin="comfortable"]{margin:1rem}[data-studio-margin="spacious"]{margin:2rem}
[data-studio-padding="none"]{padding:0}[data-studio-padding="compact"]{padding:.5rem}[data-studio-padding="comfortable"]{padding:1rem}[data-studio-padding="spacious"]{padding:2rem}
[data-studio-marker="none"]{list-style:none}[data-studio-marker="disc"]{list-style:disc}[data-studio-marker="decimal"]{list-style:decimal}[data-studio-marker="check"]{list-style:"✓  "}
[data-studio-position="relative"]{position:relative}[data-studio-position="sticky"]{inset-block-start:0;position:sticky;z-index:10}
[data-studio-scroll="auto"]{overflow:auto}[data-studio-scroll="clip"]{overflow:clip}[data-studio-scroll="snap"]{overflow:auto;scroll-snap-type:block mandatory}
[data-studio-width="content"]{inline-size:fit-content;max-inline-size:100%}[data-studio-width="full"]{inline-size:100%}
[data-studio-print="only"]{display:none}[data-studio-visible-compact="hidden"]{display:none}
[data-studio-motion]{opacity:0;transition:opacity .25s ease,transform .25s ease}[data-studio-motion="scale"]{transform:scale(.98)}[data-studio-motion="slide"]{transform:translateY(1rem)}[data-studio-motion-visible]{opacity:1;transform:none}[data-studio-motion="parallax"]{opacity:1;transform:translateY(var(--studio-parallax-offset,0))}
@media (min-width:48rem){[data-studio-visible-medium="hidden"]{display:none}[data-studio-visible-medium="visible"]{display:block}}
@media (min-width:75rem){[data-studio-visible-expanded="hidden"]{display:none}[data-studio-visible-expanded="visible"]{display:block}}
@media print{[data-studio-print="hide"]{display:none!important}[data-studio-print="only"]{display:block}}
[data-studio-layout="section"]{inline-size:100%}
[data-studio-layout="stack"]{display:flex;flex-direction:column;gap:var(--studio-space,1rem)}
[data-studio-layout="grid"],[data-studio-layout="columns"]{display:grid;gap:var(--studio-space,1rem);grid-template-columns:repeat(var(--studio-columns-compact,1),minmax(0,1fr))}
@media (min-width:48rem){[data-studio-layout="grid"],[data-studio-layout="columns"]{grid-template-columns:repeat(var(--studio-columns-medium,var(--studio-columns-compact,1)),minmax(0,1fr))}}
@media (min-width:75rem){[data-studio-layout="grid"],[data-studio-layout="columns"]{grid-template-columns:repeat(var(--studio-columns-expanded,var(--studio-columns-medium,var(--studio-columns-compact,1))),minmax(0,1fr))}}
[data-studio-gallery="grid"]{display:grid;gap:1rem;grid-template-columns:repeat(var(--studio-gallery-columns,1),minmax(0,1fr))}
[data-studio-gallery="slideshow"] [data-studio-slide]{scroll-snap-align:start}
[data-studio-gallery="slideshow"] [data-studio-part="content"]{display:flex;overflow-x:auto;scroll-snap-type:x mandatory}
[data-studio-gallery] figure{margin:0}
[data-studio-block="drawing"] svg,[data-studio-part="media"]{block-size:auto;max-inline-size:100%}
[data-studio-block="tabs"] [data-studio-tab-list][hidden]{display:none}
[data-studio-dialog],[data-studio-popover]{position:relative}
[data-studio-dialog] summary,[data-studio-popover] summary{cursor:pointer}
[data-studio-dialog-panel],[data-studio-popover-panel]{background:Canvas;border:1px solid currentColor;color:CanvasText;max-block-size:min(80vh,50rem);max-inline-size:min(90vw,50rem);overflow:auto;padding:1rem}
[data-studio-dialog][open][data-studio-dialog-modal="true"] [data-studio-dialog-panel]{inset:50% auto auto 50%;position:fixed;transform:translate(-50%,-50%);z-index:1000}
[data-studio-dialog-presentation="offcanvas"][open] [data-studio-dialog-panel]{block-size:100dvb;inset:0 0 0 auto;max-block-size:none;max-inline-size:min(90vw,30rem);position:fixed;transform:none;z-index:1000}
[data-studio-dialog-presentation="overlay"][open] [data-studio-dialog-panel]{inset:auto 1rem 1rem;max-inline-size:none;position:fixed;z-index:1000}
[data-studio-popover-panel]{inset-block-start:100%;inset-inline-start:0;position:absolute;z-index:100}
[data-studio-popover-placement="top"] [data-studio-popover-panel]{inset-block:auto 100%}
[data-studio-notice]{border-inline-start:.25rem solid currentColor;padding:.75rem 1rem}
[data-studio-cover]{display:grid;isolation:isolate;min-block-size:20rem;overflow:hidden;place-items:center;position:relative}[data-studio-cover] img{block-size:100%;inline-size:100%;inset:0;object-fit:cover;position:absolute;z-index:-2}[data-studio-cover]::after{background:rgb(0 0 0/var(--studio-cover-overlay,.35));content:"";inset:0;position:absolute;z-index:-1}
[data-studio-navigation] ul{display:flex;flex-wrap:wrap;gap:.75rem;list-style:none;margin:0;padding:0}[data-studio-navigation="breadcrumbs"] li+li::before{content:"/";margin-inline-end:.75rem}[data-studio-navigation="navbar"]{align-items:center;display:flex;justify-content:space-between}
[data-studio-badge],[data-studio-label]{border-radius:.25rem;display:inline-block;padding:.15em .5em}[data-studio-badge="soft"]{opacity:.85}[data-studio-badge="outline"]{border:1px solid currentColor}
[data-studio-spinner]{animation:studio-spin 1s linear infinite;border:.2em solid currentColor;border-inline-end-color:transparent;border-radius:50%;block-size:1.5em;display:inline-block;inline-size:1.5em}@keyframes studio-spin{to{transform:rotate(1turn)}}
[data-studio-lightbox-dialog]{background:Canvas;color:CanvasText;inline-size:min(90vw,70rem);max-block-size:90dvb;padding:1rem}[data-studio-lightbox-dialog] img{block-size:auto;max-block-size:75dvb;max-inline-size:100%}
[data-studio-chart-table]{border-collapse:collapse;inline-size:100%}
[data-studio-chart-table] th,[data-studio-chart-table] td{border:1px solid currentColor;padding:.35rem;text-align:end}
[data-studio-chart-table] th:first-child{text-align:start}
@media (prefers-reduced-motion:reduce){[data-studio-gallery="slideshow"] [data-studio-part="content"]{scroll-behavior:auto}[data-studio-motion]{opacity:1!important;transform:none!important;transition:none!important}}
CSS;

    private function __construct()
    {
    }

    public static function css(): string
    {
        return self::CSS;
    }
}
