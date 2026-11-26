<?php
/*
 * HTTP: routing, reading forms, cookies, redirects, protection against
 * cross-site forms (CSRF) and the security headers.
 *
 * The whole interface is made of links (GET) and forms (POST). After a
 * successful POST the server answers with a redirect (the "Post/Redirect/Get"
 * pattern), so reloading a page never submits a form twice.
 */

final class HttpError extends Exception
{
}

/** Stops the request with an error page (404, 403…). */
function abort(int $code, string $message = ''): never
{
    throw new HttpError($message, $code);
}

/** Entry point of the website, called by public/index.php. */
function run_app(): void
{
    register_shutdown_function('after_response');
    send_security_headers();
    try {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        [$handler, $params] = match_route(require APP . '/routes.php', $method, request_path());
        if ($method === 'POST') {
            check_csrf();
            if (needs_confirmation(request_path()) && !post_bool('confirmed')) {
                render_confirmation();
                return;
            }
        }
        // The page files are named after the first word of the handler: tasks_show → pages/tasks.php
        require_once APP . '/pages/' . explode('_', $handler)[0] . '.php';
        $handler(...$params);
    } catch (HttpError $e) {
        render_error($e->getCode(), $e->getMessage());
    } catch (Throwable $e) {
        error_log((string) $e);
        render_error(500, config('debug') ? (string) $e : '');
    }
}

/** Finds the handler of a request. Patterns: {id} = digits, {token} = URL-safe text. */
function match_route(array $routes, string $method, string $path): array
{
    $allowed = false;
    foreach ($routes as $route => $handler) {
        [$m, $pattern] = explode(' ', $route, 2);
        $regex = '#^' . strtr($pattern, ['{id}' => '(\d+)', '{id2}' => '(\d+)', '{token}' => '([A-Za-z0-9_-]+)']) . '$#';
        if (preg_match($regex, $path, $matches)) {
            if ($m !== $method) {
                $allowed = true;
                continue;
            }
            array_shift($matches);
            return [$handler, array_map(fn ($v) => ctype_digit($v) ? (int) $v : $v, $matches)];
        }
    }
    abort($allowed ? 405 : 404);
}

// ─── Addresses ─────────────────────────────────────────────────────────────

/** Folder where Agora is installed ('' at the root of the domain, '/agora' in a sub-folder). */
function base_path(): string
{
    static $base = null;
    if ($base === null && config('base_url')) {
        $base = rtrim((string) parse_url((string) config('base_url'), PHP_URL_PATH), '/');
    }
    if ($base === null && PHP_SAPI === 'cli-server') {
        $base = ''; // PHP's own server always serves Agora at the root
    }
    if ($base === null) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Installed with a root .htaccess that forwards to public/: links don't show "/public".
        if (str_ends_with($dir, '/public') && !str_starts_with($uri, $dir . '/') && $uri !== $dir) {
            $dir = substr($dir, 0, -7);
        }
        $base = $dir;
    }
    return $base;
}

/** Path of the current request inside Agora ("/tasks/12"). */
function request_path(): string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (str_starts_with($path, '/index.php')) {
        $path = substr($path, 10);
    }
    $path = '/' . trim($path, '/');
    return $path;
}

/** Link inside Agora: url('/tasks', ['scope' => 'mine']). */
function url(string $path, array $query = []): string
{
    $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
    return base_path() . $path . ($query ? '?' . http_build_query($query) : '');
}

/** Full address, for emails and calendar feeds. */
function absolute_url(string $path = '/'): string
{
    $base = rtrim((string) config('base_url'), '/');
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!preg_match('/^[A-Za-z0-9.:\[\]-]+$/', $host)) {
            $host = 'localhost';
        }
        $base = (is_https() ? 'https' : 'http') . '://' . $host . base_path();
    }
    return $base . $path;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** A path inside Agora that is safe to redirect to (never another site). */
function safe_local_path(?string $path, string $default = '/'): string
{
    $path = (string) $path;
    return preg_match('#^/(?![/\\\\])[^\s]*$#', $path) ? $path : $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

/** Irreversible actions (…/delete, …/remove…) must be confirmed before they run. */
function needs_confirmation(string $path): bool
{
    return (bool) preg_match('#/(delete|remove|revoke|cancel|release)$#', $path) && !str_starts_with($path, '/settings/')
        || $path === '/settings/leave';
}

/** "Are you sure?" page: the same form again, with confirmed=1 (used when JavaScript is off). */
function render_confirmation(): void
{
    $fields = array_diff_key($_POST, array_flip(['_csrf', 'confirmed', '_question']));
    $vars = ['question' => post_str('_question') ?: t('common.confirmDeleteText'), 'fields' => $fields,
        'action' => request_path(), 'back' => safe_local_path($_POST['_back'] ?? null, '/')];
    render('confirm', $vars, t('common.confirm'), current_account() && current_association() ? 'app' : 'auth');
}

// ─── Responses ─────────────────────────────────────────────────────────────

/**
 * Redirects (303 See Other) to a page of Agora, with an optional message shown
 * there. Ends the request.
 */
function redirect(string $path, ?string $message = null, string $type = 'ok'): never
{
    if ($message !== null) {
        set_cookie('agora_flash', base64_encode(json_encode([$type, $message])), 60);
    }
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, 303);
    exit;
}

/** Redirects back to the page given in the form's hidden "_back" field, or to $default. */
function back(string $default, ?string $message = null, string $type = 'ok'): never
{
    redirect(safe_local_path($_POST['_back'] ?? null, $default), $message, $type);
}

/** A message to show on the page rendered in this request (or after a redirect). */
function flash(?string $type = null, ?string $message = null): ?array
{
    static $now = null;
    if ($type !== null) {
        $now = [$type, $message];
        return $now;
    }
    if ($now) {
        return $now;
    }
    if (isset($_COOKIE['agora_flash'])) {
        $data = json_decode(base64_decode((string) $_COOKIE['agora_flash'], true) ?: '', true);
        delete_cookie('agora_flash');
        if (is_array($data) && count($data) === 2) {
            return $now = [(string) $data[0], (string) $data[1]];
        }
    }
    return null;
}

function send_security_headers(): void
{
    header_remove('X-Powered-By');
    // No JavaScript at all: the browser refuses any script, even if one was injected.
    // Only our own stylesheet and script are allowed: nothing injected can run.
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
}

/**
 * Sends an HTML page, compressed when the browser accepts it, with an ETag so
 * that a page that didn't change is not downloaded again (HTTP 304).
 */
function send_html(string $html, int $status = 200): void
{
    if (headers_sent()) {
        echo $html;
        return;
    }
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-cache');
    header('Vary: Accept-Encoding, Cookie');
    $etag = '"' . substr(sha256($html), 0, 20) . '"';
    $gzip = function_exists('gzencode') && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')
        && !ini_get('zlib.output_compression');
    // The footer shows the weight of the page itself: measure, then write it in.
    if (str_contains($html, '{{WEIGHT}}')) {
        $raw = strlen($html);
        $label = page_weight_label($raw, $gzip ? strlen(gzencode($html, 6)) : null);
        $html = str_replace('{{WEIGHT}}', $label, $html);
    }
    header('ETag: ' . $etag);
    if ($status === 200 && trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        return;
    }
    if ($gzip) {
        header('Content-Encoding: gzip');
        $html = gzencode($html, 6);
    }
    header('Content-Length: ' . strlen($html));
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo $html;
    }
}

function page_weight_label(int $raw, ?int $compressed): string
{
    $ms = (int) round((microtime(true) - STARTED_AT) * 1000);
    $label = t('footer.weight', ['size' => fmt_bytes($raw)]);
    if ($compressed) {
        $label .= ' ' . t('footer.compressed', ['size' => fmt_bytes($compressed)]);
    }
    return $label . ' · ' . t('footer.generated', ['ms' => max(1, $ms)]);
}

/** Sends a file to download (CSV, backup…). */
function send_download(string $content, string $fileName, string $type): void
{
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $fileName) . '"');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . strlen($content));
    echo $content;
}

/** Runs once the page has been sent: emails, daily maintenance. */
function after_response(): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // the visitor doesn't wait for what follows
    }
    try {
        flush_mail_queue();
        maybe_run_maintenance();
    } catch (Throwable $e) {
        error_log('After response: ' . $e->getMessage());
    }
}

// ─── Cookies ───────────────────────────────────────────────────────────────

function secure_cookies(): bool
{
    $setting = config('secure_cookies');
    return $setting === null ? is_https() : (bool) $setting;
}

function set_cookie(string $name, string $value, int $maxAge, bool $httpOnly = true): void
{
    setcookie($name, $value, [
        'expires' => $maxAge > 0 ? time() + $maxAge : 0,
        'path' => (base_path() ?: '') . '/',
        'secure' => secure_cookies(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $value;
}

function delete_cookie(string $name): void
{
    setcookie($name, '', ['expires' => time() - 3600, 'path' => (base_path() ?: '') . '/', 'samesite' => 'Lax']);
    unset($_COOKIE[$name]);
}

// ─── Cross-site request forgery ────────────────────────────────────────────
// Every form carries a random token that must match a cookie. Another website
// can make your browser send a form, but it cannot read that token.

function csrf_token(): string
{
    $token = $_COOKIE['agora_csrf'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
        $token = new_token();
        set_cookie('agora_csrf', $token, 365 * 86400);
    }
    return $token;
}

function check_csrf(): void
{
    // A file bigger than PHP's post_max_size arrives as an empty form.
    if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        abort(413, t('documents.tooLarge', ['mb' => config('max_upload_mb')]));
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== 'null' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        abort(403, t('errors.csrf'));
    }
    $cookie = $_COOKIE['agora_csrf'] ?? '';
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($cookie === '' || !hash_equals($cookie, $sent)) {
        abort(403, t('errors.csrf'));
    }
}

// ─── Reading forms ─────────────────────────────────────────────────────────

/** A text field, trimmed ('' when missing). */
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : '';
}

/** A text field, or null when empty. */
function post_opt(string $key): ?string
{
    $v = post_str($key);
    return $v === '' ? null : $v;
}

function post_int(string $key, int $default = 0): int
{
    $v = post_str($key);
    return preg_match('/^-?\d+$/', $v) ? (int) $v : $default;
}

/** An id chosen in a <select>, or null. */
function post_id(string $key): ?int
{
    $v = post_str($key);
    return ctype_digit($v) && $v !== '0' ? (int) $v : null;
}

function post_bool(string $key): bool
{
    return in_array($_POST[$key] ?? '', ['1', 'on', 'true', 'yes'], true);
}

/** One of the allowed values, or $default. */
function post_enum(string $key, array $values, ?string $default): ?string
{
    $v = post_str($key);
    return in_array($v, $values, true) ? $v : $default;
}

function post_date(string $key, bool $withTime = false): ?string
{
    return parse_date_input(post_str($key), $withTime);
}

function post_money(string $key): ?int
{
    return parse_money(post_str($key));
}

/** Every non-empty value of a multiple field (checkboxes named "key[]"). */
function post_all(string $key): array
{
    $v = $_POST[$key] ?? [];
    $v = is_array($v) ? $v : [$v];
    return array_values(array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : '', $v), fn ($x) => $x !== ''));
}

/** A query-string parameter ("?scope=mine"). */
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

// ─── Form errors ───────────────────────────────────────────────────────────
// When a form is invalid, the same page is shown again with the errors next to
// the fields and what the person typed (old()). No redirect, no JavaScript.

function form_errors(?array $set = null): array
{
    static $errors = [];
    if ($set !== null) {
        $errors = $set;
    }
    return $errors;
}

/** The value typed in the form that just failed, or the stored value. */
function old(string $key, mixed $default = ''): mixed
{
    if (form_errors()) {
        return $_POST[$key] ?? (is_array($default) ? [] : '');
    }
    return $default ?? '';
}

/** Shows the page again with the errors (HTTP 422) and stops. */
function fail_form(array $errors, callable $render): never
{
    form_errors($errors);
    flash('error', (string) reset($errors));
    http_response_code(422);
    $render();
    exit;
}

/** True if the form identified by $name just failed (to reopen it). */
function form_failed(string $name): bool
{
    return form_errors() && ($_POST['_form'] ?? '') === $name;
}
