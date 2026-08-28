<?php

/**
 * The demonstration front controller.
 *
 * Three routes, exactly the host guide's adoption path:
 *
 *   POST /port/{route}  one wire endpoint — the raw body and the registry
 *                       route go into the dispatcher, and the canonical
 *                       response bytes and headers come back out
 *   GET  /              the demo composition rendered to a full HTML page
 *   GET  /page.css      the generated stylesheet (theme tokens + render CSS)
 *
 * Run with: php -S localhost:8080 -t examples/minimal-host/public
 */

declare(strict_types=1);

use Kumwe\Producer\Wire\Dispatcher;
use Kumwe\ProducerExamples\DemoPage;
use Kumwe\ProducerExamples\MinimalHost;

require dirname(__DIR__) . '/MinimalHost.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($path, '/port/')) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('content-type: text/plain; charset=utf-8');
        header('allow: POST');
        echo "Studio wire requests are POST bodies; see the README for a curl example.\n";
        return;
    }
    // Identity comes from the trusted transport, never from the envelope. A
    // real host authenticates here (session, token); the demonstration
    // trusts a plain header so refusal paths are easy to try.
    $actor = $_SERVER['HTTP_X_DEMO_ACTOR'] ?? null;
    $dispatcher = new Dispatcher(new MinimalHost(is_string($actor) ? $actor : null));
    $response = $dispatcher->dispatch(
        substr($path, strlen('/port/')),
        (string) file_get_contents('php://input')
    );
    foreach ($response->headers as $name => $value) {
        header($name . ': ' . $value);
    }
    // The contract distinguishes outcomes by body shape, never by status.
    http_response_code(200);
    echo $response->body;
    return;
}

$result = DemoPage::render();

if ($path === '/page.css') {
    // Deterministic bytes: a production host caches this per theme and per
    // composition revision instead of rebuilding it per request.
    header('content-type: text/css; charset=utf-8');
    echo DemoPage::stylesheet($result);
    return;
}

if ($path !== '/') {
    http_response_code(404);
    header('content-type: text/plain; charset=utf-8');
    echo "The demonstration serves /, /page.css, and POST /port/{route}.\n";
    return;
}

// The strict policy from the host guide: no inline script, no inline style,
// nothing the rendered fragment does not hold under.
header('content-type: text/html; charset=utf-8');
header(
    "content-security-policy: default-src 'none'; base-uri 'none'; form-action 'none'; "
    . "frame-ancestors 'none'; img-src 'self' https:; media-src 'self' https:; "
    . "script-src 'self'; style-src 'self'"
);

// The enhancement need signal: only a page that requested progressive
// behavior references Studio's prebuilt runtime. The file is a Studio
// release artifact a real host serves from its own asset mount; this
// demonstration does not vendor it, and the page stays fully usable
// without it — that absence is the no-JavaScript guarantee at work.
$runtime = $result->enhancementNames() === []
    ? ''
    : '<script src="/assets/studio/enhancements.js" defer></script>';

echo '<!doctype html>' . "\n"
    . '<html lang="en">' . "\n"
    . '<head>' . "\n"
    . '<meta charset="utf-8">' . "\n"
    . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
    . '<title>Producer minimal host</title>' . "\n"
    . '<link rel="stylesheet" href="/page.css">' . "\n"
    . $runtime . "\n"
    . '</head>' . "\n"
    . '<body>' . "\n"
    . '<main>' . $result->html . '</main>' . "\n"
    . '</body>' . "\n"
    . '</html>' . "\n";
