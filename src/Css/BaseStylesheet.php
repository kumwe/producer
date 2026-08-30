<?php

declare(strict_types=1);

namespace Kumwe\Producer\Css;

/**
 * The static base stylesheet of the semantic web profile.
 *
 * A byte-for-byte port of the reference renderer's BASE_CSS: the
 * data-attribute presentation vocabulary, the layout primitives, the
 * component scaffolding, and the prefers-reduced-motion overrides. It is a
 * constant — no per-request computation and no stored styles ever reach it.
 *
 * @since   0.1.0
 */
final class BaseStylesheet
{
    /**
     * The complete base stylesheet text, pinned byte for byte to the
     * reference renderer's BASE_CSS so every conforming runtime ships the
     * identical presentation vocabulary. Nothing stored, host-supplied, or
     * per-request is ever interpolated into it.
     *
     * @since   0.1.0
     */
    private const CSS = <<<'CSS'
[data-studio-block]{box-sizing:border-box;min-inline-size:0}.studio-visually-hidden{clip-path:inset(50%);white-space:nowrap;block-size:1px;inline-size:1px;position:absolute;overflow:hidden}[data-studio-align=center]{text-align:center}[data-studio-align=end]{text-align:end}[data-studio-align=stretch]{align-self:stretch}[data-studio-height=content]{block-size:fit-content}[data-studio-height=full]{block-size:100%}[data-studio-height=viewport]{min-block-size:100dvb}[data-studio-inverse=true]{background:var(--studio-inverse-background,CanvasText);color:var(--studio-inverse-foreground,Canvas)}[data-studio-margin=none]{margin:0}[data-studio-margin=compact]{margin:.5rem}[data-studio-margin=comfortable]{margin:1rem}[data-studio-margin=spacious]{margin:2rem}[data-studio-padding=none]{padding:0}[data-studio-padding=compact]{padding:.5rem}[data-studio-padding=comfortable]{padding:1rem}[data-studio-padding=spacious]{padding:2rem}[data-studio-marker=none]{list-style:none}[data-studio-marker=disc]{list-style:outside}[data-studio-marker=decimal]{list-style:decimal}[data-studio-marker=check]{list-style:"✓  "}[data-studio-position=relative]{position:relative}[data-studio-position=sticky]{z-index:10;position:sticky;inset-block-start:0}[data-studio-scroll=auto]{overflow:auto}[data-studio-scroll=clip]{overflow:clip}[data-studio-scroll=snap]{scroll-snap-type:block mandatory;overflow:auto}[data-studio-width=content]{inline-size:fit-content;max-inline-size:100%}[data-studio-width=full]{inline-size:100%}[data-studio-print=only],[data-studio-visible-compact=hidden]{display:none}[data-studio-motion]{opacity:0;transition:opacity .25s,transform .25s}[data-studio-motion=scale]{transform:scale(.98)}[data-studio-motion=slide]{transform:translateY(1rem)}[data-studio-motion-visible]{opacity:1;transform:none}[data-studio-motion=parallax]{opacity:1;transform:translateY(var(--studio-parallax-offset,0))}@media (width>=48rem){[data-studio-visible-medium=hidden]{display:none}[data-studio-visible-medium=visible]{display:block}}@media (width>=75rem){[data-studio-visible-expanded=hidden]{display:none}[data-studio-visible-expanded=visible]{display:block}}@media print{[data-studio-print=hide]{display:none!important}[data-studio-print=only]{display:block}}[data-studio-layout=section]{inline-size:100%}[data-studio-layout=stack]{gap:var(--studio-space,1rem);flex-direction:column;display:flex}[data-studio-layout=grid],[data-studio-layout=columns]{gap:var(--studio-space,1rem);grid-template-columns:repeat(var(--studio-columns-compact,1),minmax(0,1fr));display:grid}@media (width>=48rem){[data-studio-layout=grid],[data-studio-layout=columns]{grid-template-columns:repeat(var(--studio-columns-medium,var(--studio-columns-compact,1)),minmax(0,1fr))}}@media (width>=75rem){[data-studio-layout=grid],[data-studio-layout=columns]{grid-template-columns:repeat(var(--studio-columns-expanded,var(--studio-columns-medium,var(--studio-columns-compact,1))),minmax(0,1fr))}}[data-studio-gallery=grid]{grid-template-columns:repeat(var(--studio-gallery-columns,1),minmax(0,1fr));gap:1rem;display:grid}[data-studio-gallery=slideshow] [data-studio-slide]{scroll-snap-align:start}[data-studio-gallery=slideshow] [data-studio-part=content]{scroll-snap-type:x mandatory;display:flex;overflow-x:auto}[data-studio-gallery] figure{margin:0}[data-studio-block=drawing] svg,[data-studio-part=media]{block-size:auto;max-inline-size:100%}[data-studio-block=tabs] [data-studio-tab-list][hidden]{display:none}[data-studio-dialog],[data-studio-popover]{position:relative}[data-studio-dialog] summary,[data-studio-popover] summary{cursor:pointer}[data-studio-dialog-panel],[data-studio-popover-panel]{color:canvastext;background:canvas;border:1px solid;max-block-size:min(80vh,50rem);max-inline-size:min(90vw,50rem);padding:1rem;overflow:auto}[data-studio-dialog][open][data-studio-dialog-modal=true] [data-studio-dialog-panel]{z-index:1000;position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%)}[data-studio-dialog-presentation=offcanvas][open] [data-studio-dialog-panel]{z-index:1000;block-size:100dvb;max-block-size:none;max-inline-size:min(90vw,30rem);position:fixed;inset:0 0 0 auto;transform:none}[data-studio-dialog-presentation=overlay][open] [data-studio-dialog-panel]{z-index:1000;max-inline-size:none;position:fixed;inset:auto 1rem 1rem}[data-studio-popover-panel]{z-index:100;position:absolute;inset-block-start:100%;inset-inline-start:0}[data-studio-popover-placement=top] [data-studio-popover-panel]{inset-block:auto 100%}[data-studio-notice]{border-inline-start:.25rem solid;padding:.75rem 1rem}[data-studio-cover]{isolation:isolate;place-items:center;min-block-size:20rem;display:grid;position:relative;overflow:hidden}[data-studio-cover] img{object-fit:cover;z-index:-2;block-size:100%;inline-size:100%;position:absolute;inset:0}[data-studio-cover]:after{background:rgb(0 0 0/var(--studio-cover-overlay,.35));content:"";z-index:-1;position:absolute;inset:0}[data-studio-navigation] ul{flex-wrap:wrap;gap:.75rem;margin:0;padding:0;list-style:none;display:flex}[data-studio-navigation=breadcrumbs] li+li:before{content:"/";margin-inline-end:.75rem}[data-studio-navigation=navbar]{justify-content:space-between;align-items:center;display:flex}[data-studio-badge],[data-studio-label]{border-radius:.25rem;padding:.15em .5em;display:inline-block}[data-studio-badge=soft]{opacity:.85}[data-studio-badge=outline]{border:1px solid}[data-studio-spinner]{border:.2em solid;border-inline-end-color:#0000;border-radius:50%;block-size:1.5em;inline-size:1.5em;animation:1s linear infinite studio-spin;display:inline-block}@keyframes studio-spin{to{transform:rotate(1turn)}}[data-studio-lightbox-dialog]{color:canvastext;background:canvas;max-block-size:90dvb;inline-size:min(90vw,70rem);padding:1rem}[data-studio-lightbox-dialog] img{block-size:auto;max-block-size:75dvb;max-inline-size:100%}[data-studio-chart-table]{border-collapse:collapse;inline-size:100%}[data-studio-chart-table] th,[data-studio-chart-table] td{text-align:end;border:1px solid;padding:.35rem}[data-studio-chart-table] th:first-child{text-align:start}@media (prefers-reduced-motion:reduce){[data-studio-gallery=slideshow] [data-studio-part=content]{scroll-behavior:auto}[data-studio-motion]{opacity:1!important;transition:none!important;transform:none!important}}
CSS;

    /**
     * Static vocabulary holder; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * The base stylesheet: identical bytes on every call within a Producer
     * release — no input, clock, or configuration influences it.
     *
     * @return  string  The complete static base stylesheet text.
     * @since   0.1.0
     */
    public static function css(): string
    {
        return self::CSS;
    }
}
