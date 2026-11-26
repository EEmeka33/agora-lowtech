<?php
/*
 * A tiny test toolkit, with no dependency:
 *   - test() / check() / summary() to write and count assertions;
 *   - start_server() runs Agora with PHP's built-in server on a fresh database;
 *   - Browser: a minimal web browser (cookies, forms, redirects) built on PHP streams.
 */
declare(strict_types=1);

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = [];
$GLOBALS['current_test'] = '';

function test(string $name, callable $fn): void
{
    $GLOBALS['current_test'] = $name;
    try {
        $fn();
    } catch (Throwable $e) {
        $GLOBALS['failed'][] = "$name: " . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        echo 'E';
    }
}

function check(bool $condition, string $what = ''): void
{
    if ($condition) {
        $GLOBALS['passed']++;
        echo '.';
        return;
    }
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $GLOBALS['failed'][] = $GLOBALS['current_test'] . ': ' . ($what ?: 'check failed') . ' (' . basename($trace['file']) . ':' . $trace['line'] . ')';
    echo 'F';
}

function check_same(mixed $expected, mixed $actual, string $what = ''): void
{
    check($expected === $actual, ($what ? "$what: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function summary(): int
{
    echo "\n\n";
    foreach ($GLOBALS['failed'] as $f) {
        echo "✗ $f\n";
    }
    $failed = count($GLOBALS['failed']);
    echo $failed ? "\n$failed failed, {$GLOBALS['passed']} passed\n" : "All {$GLOBALS['passed']} checks passed.\n";
    return $failed ? 1 : 0;
}

/** Starts Agora on a free port with a temporary database. Returns [base URL, temp dir]. */
function start_server(array $config = []): array
{
    $dir = sys_get_temp_dir() . '/agora-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/uploads', 0700, true);
    $config += [
        'database' => "$dir/agora.db",
        'upload_dir' => "$dir/uploads",
        'mail' => ['transport' => 'log'],
        'timezone' => 'Europe/Paris',
        'debug' => true,
    ];
    file_put_contents("$dir/config.php", '<?php return ' . var_export($config, true) . ';');
    $port = 20000 + random_int(0, 20000);
    $root = dirname(__DIR__);
    $cmd = sprintf('AGORA_CONFIG=%s exec php -S 127.0.0.1:%d -t %s %s > %s 2>&1 & echo $!',
        escapeshellarg("$dir/config.php"), $port, escapeshellarg("$root/public"), escapeshellarg("$root/public/index.php"), escapeshellarg("$dir/server.log"));
    $pid = (int) shell_exec($cmd);
    register_shutdown_function(function () use ($pid, $dir) {
        if ($pid) {
            posix_kill($pid, 15);
        }
        if (!getenv('KEEP_TEST_DIR')) {
            exec('rm -rf ' . escapeshellarg($dir));
        } else {
            echo "\nTest data kept in $dir\n";
        }
    });
    $base = "http://127.0.0.1:$port";
    for ($i = 0; $i < 50; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            break;
        }
        usleep(100000);
    }
    return [$base, $dir];
}

final class Browser
{
    public array $cookies = [];
    public int $status = 0;
    public string $body = '';
    public array $headers = [];
    public string $url = '';
    /** Every page fetched, for the low-tech budget checks: [path => [bytes, html]]. */
    public static array $pages = [];

    public function __construct(public string $base)
    {
    }

    public function get(string $path, bool $follow = true): self
    {
        return $this->request('GET', $path, null, null, $follow);
    }

    /** Submits a form. The anti-CSRF token is added from the cookie, like the page would. */
    public function post(string $path, array $fields = [], array $files = [], bool $follow = true): self
    {
        if (!isset($this->cookies['agora_csrf'])) {
            $this->get('/login');
        }
        $fields += ['_csrf' => $this->cookies['agora_csrf'] ?? ''];
        if ($files) {
            $boundary = '----agora' . bin2hex(random_bytes(8));
            $body = '';
            foreach ($this->flatten($fields) as [$name, $value]) {
                $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
            }
            foreach ($files as $name => [$fileName, $content, $type]) {
                $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"$fileName\"\r\nContent-Type: $type\r\n\r\n$content\r\n";
            }
            $body .= "--$boundary--\r\n";
            return $this->request('POST', $path, $body, "multipart/form-data; boundary=$boundary", $follow);
        }
        return $this->request('POST', $path, http_build_query($fields), 'application/x-www-form-urlencoded', $follow);
    }

    private function flatten(array $fields): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            foreach ((array) $v as $item) {
                $out[] = [is_array($v) ? "{$k}[]" : $k, (string) $item];
            }
        }
        return $out;
    }

    public function request(string $method, string $path, ?string $body, ?string $type, bool $follow): self
    {
        $headers = ["Accept-Language: en"];
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        if ($type) {
            $headers[] = "Content-Type: $type";
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 20,
        ]]);
        $this->url = str_starts_with($path, 'http') ? $path : $this->base . $path;
        $this->body = (string) @file_get_contents($this->url, false, $context);
        $this->headers = [];
        $this->status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
                $this->status = (int) $m[1];
                $this->headers = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                if ($k === 'set-cookie') {
                    [$pair] = explode(';', $v, 2);
                    [$name, $value] = explode('=', $pair, 2) + [1 => ''];
                    if ($value === '' || str_contains(strtolower($v), 'expires=thu, 01 jan 1970') || preg_match('/max-age=0\b/i', $v)) {
                        unset($this->cookies[$name]);
                    } else {
                        $this->cookies[$name] = $value;
                    }
                }
                $this->headers[$k] = $v;
            }
        }
        if ($this->status === 200 && str_contains($this->headers['content-type'] ?? '', 'text/html')) {
            self::$pages[parse_url($this->url, PHP_URL_PATH)] = $this->body;
        }
        if ($follow && in_array($this->status, [301, 302, 303], true) && isset($this->headers['location'])) {
            return $this->get($this->headers['location']);
        }
        return $this;
    }

    public function see(string $text): bool
    {
        return str_contains(html_entity_decode($this->body, ENT_QUOTES | ENT_HTML5), $text);
    }

    /** First match of a regular expression in the page (group 1). */
    public function find(string $regex): ?string
    {
        return preg_match($regex, $this->body, $m) ? html_entity_decode($m[1] ?? $m[0], ENT_QUOTES) : null;
    }

    public function path(): string
    {
        return (string) parse_url($this->url, PHP_URL_PATH);
    }
}
