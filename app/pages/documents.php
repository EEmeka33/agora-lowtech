<?php
/*
 * Documents: uploaded files or links, by category. Files are stored outside the
 * public folder and only sent to members of the association.
 * Big photos are reduced on upload (config 'shrink_images'): a phone picture of
 * 4 MB usually becomes 200–400 KB, which is plenty to read a receipt or a poster.
 */

function documents_list(): void
{
    $user = require_member();
    $category = in_array(get_str('category'), DOCUMENT_CATEGORIES, true) ? get_str('category') : '';
    $search = get_str('q');
    $where = ['d.association_id = ?'];
    $params = [aid()];
    if ($category) {
        $where[] = 'd.category = ?';
        $params[] = $category;
    }
    if ($search !== '') {
        $where[] = '(d.title LIKE ? OR d.description LIKE ? OR d.file_name LIKE ?)';
        array_push($params, "%$search%", "%$search%", "%$search%");
    }
    $documents = rows('SELECT d.*, u.name AS uploader_name, p.name AS pole_name, pr.title AS project_title FROM documents d
                       LEFT JOIN users u ON u.id = d.uploaded_by_id LEFT JOIN poles p ON p.id = d.pole_id LEFT JOIN projects pr ON pr.id = d.project_id
                       WHERE ' . implode(' AND ', $where) . ' ORDER BY d.category, d.title', $params);
    $storage = (int) val('SELECT COALESCE(SUM(size_bytes), 0) FROM documents WHERE association_id = ?', [aid()]);
    render('documents/list', compact('user', 'documents', 'category', 'search', 'storage'), t('documents.title'));
}

function upload_dir(): string
{
    $dir = (string) config('upload_dir');
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

function documents_create(): void
{
    $user = require_member();
    $title = post_str('title');
    $url = post_str('url');
    $file = $_FILES['file'] ?? null;
    $hasFile = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $maxBytes = (int) config('max_upload_mb') * 1024 * 1024;
    $errors = [];
    if ($title === '') {
        $errors['title'] = t('validation.required');
    }
    if (!$hasFile && $url === '') {
        $errors['file'] = t('documents.fileOrLinkRequired');
    }
    if ($hasFile && ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > $maxBytes)) {
        $errors['file'] = t('documents.tooLarge', ['mb' => config('max_upload_mb')]);
    } elseif ($hasFile && $file['error'] !== UPLOAD_ERR_OK) {
        $errors['file'] = t('documents.uploadFailed');
    }
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $errors['url'] = t('validation.url');
    }
    if ($errors) {
        fail_form($errors, 'documents_list');
    }
    $refs = ['pole_id' => post_id('pole_id'), 'project_id' => post_id('project_id')];
    assert_refs($refs);
    $stored = [];
    $note = '';
    if ($hasFile) {
        $original = (int) $file['size'];
        $mime = detect_mime($file['tmp_name'], $file['name']);
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($file['name'], PATHINFO_EXTENSION)));
        $name = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . substr($ext, 0, 8) : '');
        $path = upload_dir() . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            fail_form(['file' => t('documents.uploadFailed')], 'documents_list');
        }
        if ($shrunk = shrink_image($path, $mime)) {
            [$path, $mime] = $shrunk;
            $name = basename($path);
            $note = ' ' . t('documents.shrunk', ['from' => fmt_bytes($original), 'to' => fmt_bytes((int) filesize($path))]);
        }
        $stored = ['file_name' => str_cut(basename($file['name']), 200), 'stored_name' => $name, 'mime_type' => $mime, 'size_bytes' => (int) filesize($path)];
    }
    $id = insert('documents', $refs + $stored + [
        'association_id' => aid(),
        'title' => $title,
        'description' => post_opt('description'),
        'category' => post_enum('category', DOCUMENT_CATEGORIES, 'OTHER'),
        'url' => $hasFile ? null : $url,
        'uploaded_by_id' => $user['id'],
        'created_at' => now_utc(),
        'updated_at' => now_utc(),
    ]);
    log_activity('uploaded', 'document', $id, $title);
    back('/documents', t('common.created') . $note);
}

/** The real type of a file, read from its content when possible. */
function detect_mime(string $path, string $name): string
{
    if (function_exists('finfo_open')) {
        $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
        if ($type) {
            return $type;
        }
    }
    return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'pdf' => 'application/pdf', 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'txt' => 'text/plain',
        default => 'application/octet-stream',
    };
}

/**
 * Reduces a big photo: longest side 1600 px, JPEG quality 80 (PNG kept for
 * images with transparency). Returns [new path, new type] or null if unchanged.
 */
function shrink_image(string $path, string $mime): ?array
{
    if (!config('shrink_images') || !function_exists('imagecreatefromstring') || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return null;
    }
    $size = @getimagesize($path);
    if (!$size || $size[0] * $size[1] > 40_000_000) {
        return null;
    }
    [$w, $h] = $size;
    if (filesize($path) < 300 * 1024 && max($w, $h) <= 1600) {
        return null;
    }
    try {
        $src = imagecreatefromstring((string) file_get_contents($path));
        if (!$src) {
            return null;
        }
        // Photos from phones are often stored sideways with an "orientation" tag.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $angle = [3 => 180, 6 => -90, 8 => 90][@exif_read_data($path)['Orientation'] ?? 1] ?? 0;
            if ($angle) {
                $src = imagerotate($src, $angle, 0);
                [$w, $h] = [imagesx($src), imagesy($src)];
            }
        }
        $scale = min(1, 1600 / max($w, $h));
        $dst = imagescale($src, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        $keepPng = $mime === 'image/png' && (imagecolortransparent($src) >= 0 || !imageistruecolor($src) || has_alpha($src));
        $newPath = preg_replace('/\.[a-z0-9]+$/i', '', $path) . ($keepPng ? '.png' : '.jpg');
        $keepPng ? imagepng($dst, $newPath . '.tmp', 9) : imagejpeg($dst, $newPath . '.tmp', 80);
        if (filesize($newPath . '.tmp') >= filesize($path)) {
            unlink($newPath . '.tmp');
            return null;
        }
        rename($newPath . '.tmp', $newPath);
        if ($newPath !== $path) {
            unlink($path);
        }
        return [$newPath, $keepPng ? 'image/png' : 'image/jpeg'];
    } catch (Throwable $e) {
        error_log('Image not reduced: ' . $e->getMessage());
        return null;
    }
}

/** True if a PNG uses transparency (sampled, to stay fast). */
function has_alpha(GdImage $img): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    for ($i = 0; $i < 200; $i++) {
        if ((imagecolorat($img, random_int(0, $w - 1), random_int(0, $h - 1)) >> 24) & 0x7F) {
            return true;
        }
    }
    return false;
}

/** Fields of a document form (the file itself is chosen only when adding). */
function document_fields(array $d = [], bool $new = false): string
{
    $v = fn ($k, $def = '') => old($k, $d[$k] ?? $def);
    return input_field('title', t('common.title'), $v('title'), ['required' => true])
        . select_field('category', t('common.category'), enum_options('documentCategory', DOCUMENT_CATEGORIES), $v('category', 'OTHER'))
        . ($new || empty($d['stored_name']) ? input_field('url', t('documents.link'), $v('url'), ['type' => 'url', 'placeholder' => 'https://', 'optional' => $new]) : '')
        . select_field('pole_id', t('common.pole'), pole_options(), $v('pole_id'), ['empty' => t('common.none')])
        . select_field('project_id', t('common.project'), project_options(true), $v('project_id'), ['empty' => t('common.none')])
        . textarea_field('description', t('common.description'), $v('description'), ['rows' => 2]);
}

function documents_edit(int $id): void
{
    $user = require_member();
    $doc = find_or_404('documents', $id);
    ensure(can('editDocument', $user, $doc));
    render('edit', ['heading' => t('documents.editDocument'), 'back' => '/documents', 'action' => "/documents/$id",
        'fields' => document_fields($doc), 'delete' => "/documents/$id/delete"], $doc['title']);
}

function documents_update(int $id): void
{
    $user = require_member();
    $doc = find_or_404('documents', $id);
    ensure(can('editDocument', $user, $doc));
    $title = post_str('title');
    $url = $doc['stored_name'] ? null : post_str('url');
    if ($title === '' || ($url !== null && !preg_match('#^https?://#i', $url))) {
        fail_form([$title === '' ? 'title' : 'url' => $title === '' ? t('validation.required') : t('validation.url')], fn () => documents_edit($id));
    }
    $refs = ['pole_id' => post_id('pole_id'), 'project_id' => post_id('project_id')];
    assert_refs($refs);
    update('documents', $refs + ['title' => $title, 'description' => post_opt('description'), 'url' => $url,
        'category' => post_enum('category', DOCUMENT_CATEGORIES, 'OTHER'), 'updated_at' => now_utc()], 'id = ?', [$id]);
    back('/documents', t('common.saved'));
}

function documents_delete(int $id): void
{
    $user = require_member();
    $doc = find_or_404('documents', $id);
    ensure(can('editDocument', $user, $doc));
    q('DELETE FROM documents WHERE id = ?', [$id]);
    if ($doc['stored_name']) {
        @unlink(upload_dir() . '/' . basename($doc['stored_name']));
    }
    log_activity('deleted', 'document', null, $doc['title']);
    back('/documents', t('common.deleted'));
}

/** Sends a stored file, only to members of its association. */
function documents_file(int $id): void
{
    require_member();
    $doc = find_or_404('documents', $id);
    $path = upload_dir() . '/' . basename((string) $doc['stored_name']);
    if (!$doc['stored_name'] || !is_file($path)) {
        abort(404);
    }
    $inline = in_array($doc['mime_type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'], true);
    header('Content-Type: ' . ($inline ? $doc['mime_type'] : 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['file_name'] ?: 'file') . '"');
    header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize($path));
    readfile($path);
}
