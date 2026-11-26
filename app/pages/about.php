<?php
/*
 * "About this Agora": the low-tech choices of this software, with live numbers
 * measured on this server. Public: useful for a course or a curious visitor.
 */

function about_show(): void
{
    $code = ['files' => 0, 'lines' => 0, 'bytes' => 0];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ([...iterator_to_array($it, false), new SplFileInfo(ROOT . '/public/style.css'), new SplFileInfo(ROOT . '/public/index.php')] as $file) {
        if (in_array($file->getExtension(), ['php', 'css', 'sql'], true)) {
            $code['files']++;
            $code['bytes'] += $file->getSize();
            $code['lines'] += substr_count((string) file_get_contents($file->getPathname()), "\n");
        }
    }
    $uploads = ['count' => 0, 'bytes' => 0];
    foreach (glob(rtrim((string) config('upload_dir'), '/') . '/*') ?: [] as $f) {
        $uploads['count']++;
        $uploads['bytes'] += (int) filesize($f);
    }
    render('about/show', [
        'code' => $code,
        'uploads' => $uploads,
        'dbBytes' => (int) @filesize((string) config('database')),
        'css' => (int) filesize(ROOT . '/public/style.css'),
        'sqlite' => (string) val('SELECT sqlite_version()'),
    ], t('about.title'), current_account() && current_association() ? 'app' : 'auth');
}

/** For monitoring: "ok" when the database answers. */
function about_health(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        val('SELECT 1');
        echo "ok\n";
    } catch (Throwable $e) {
        http_response_code(503);
        echo "error\n";
    }
}
