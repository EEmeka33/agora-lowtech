<?php
/*
 * Rendering pages. Templates are plain PHP files in app/views/: HTML with
 * <?= h($value) ?> wherever a value is printed. h() escapes HTML, so a name
 * like "<script>" is shown as text and never run.
 *
 * The helpers below build the few pieces of HTML used everywhere: form fields,
 * one-button forms, confirmations, badges, progress bars.
 */

/** Escapes a value for HTML. Use it for every value printed in a template. */
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Renders app/views/$view.php inside a layout and sends it.
 * Layouts: 'app' (the workspace, with navigation), 'auth' (sign-in pages, no navigation).
 */
function render(string $view, array $vars = [], string $title = '', string $layout = 'app'): void
{
    $content = template($view, $vars);
    $html = template($layout === 'app' ? 'layout' : 'layout-auth', ['content' => $content, 'title' => $title]);
    send_html($html, http_response_code() ?: 200);
}

/** Returns the HTML of a template (also used for parts of pages). */
function template(string $__template, array $__vars = []): string
{
    extract($__vars, EXTR_SKIP);
    ob_start();
    try {
        include APP . "/views/$__template.php";
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return (string) ob_get_clean();
}

/** Text typed by someone: escaped, line breaks kept (CSS), web addresses made clickable. */
function text_html(?string $text): string
{
    $html = h($text);
    return preg_replace('~https?://[^\s<>"]+[^\s<>".,;:!?)\]]~', '<a href="$0" rel="nofollow noopener">$0</a>', $html) ?? $html;
}

/** HTML attributes from an array: ['required' => true, 'min' => 1] → ' required min="1"'. */
function attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $out .= $v === true ? " $k" : ' ' . $k . '="' . h($v) . '"';
    }
    return $out;
}

// ─── Forms ─────────────────────────────────────────────────────────────────

/** The hidden fields every POST form needs: the anti-CSRF token and, optionally, a form name. */
function csrf_field(?string $form = null): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'
        . ($form ? '<input type="hidden" name="_form" value="' . h($form) . '">' : '');
}

/** Hidden field to come back to the current page after the form. */
function back_field(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = request_path() . (str_contains($uri, '?') ? '?' . explode('?', $uri, 2)[1] : '');
    return '<input type="hidden" name="_back" value="' . h($path) . '">';
}

function field_error(string $name): string
{
    $error = form_errors()[$name] ?? '';
    return $error === '' ? '' : '<small class="error">' . h($error) . '</small>';
}

/**
 * <label>Label <input></label>. Options: type, required, placeholder, hint, attrs.
 * Wrapping the input in its label makes it accessible without any id.
 */
function input_field(string $name, string $label, mixed $value = '', array $opt = []): string
{
    $type = $opt['type'] ?? 'text';
    $attributes = attrs([
        'type' => $type,
        'name' => $name,
        'value' => (string) $value,
        'required' => $opt['required'] ?? false,
        'placeholder' => $opt['placeholder'] ?? null,
        'aria-invalid' => isset(form_errors()[$name]) ? 'true' : null,
        'maxlength' => in_array($type, ['text', 'email', 'url', 'search', 'tel'], true) ? ($opt['maxlength'] ?? 300) : null,
    ] + ($opt['attrs'] ?? []));
    return '<label class="field' . (!empty($opt['class']) ? ' ' . h($opt['class']) : '') . '"><span>' . h($label)
        . (!empty($opt['required']) ? '' : (!empty($opt['optional']) ? ' <i>(' . h(t('common.optional')) . ')</i>' : ''))
        . '</span><input' . $attributes . '>'
        . (!empty($opt['hint']) ? '<small>' . h($opt['hint']) . '</small>' : '') . field_error($name) . '</label>';
}

function textarea_field(string $name, string $label, mixed $value = '', array $opt = []): string
{
    $attributes = attrs([
        'name' => $name,
        'rows' => $opt['rows'] ?? 4,
        'required' => $opt['required'] ?? false,
        'placeholder' => $opt['placeholder'] ?? null,
        'maxlength' => $opt['maxlength'] ?? 20000,
    ]);
    return '<label class="field wide"><span>' . h($label) . '</span><textarea' . $attributes . '>' . h($value) . '</textarea>'
        . (!empty($opt['hint']) ? '<small>' . h($opt['hint']) . '</small>' : '') . field_error($name) . '</label>';
}

/** <select>. $options = [value => label]. Option 'empty' adds a first empty choice with that label. */
function select_field(string $name, string $label, array $options, mixed $selected = '', array $opt = []): string
{
    $html = '<label class="field"><span>' . h($label) . '</span><select' . attrs(['name' => $name, 'required' => $opt['required'] ?? false]) . '>';
    if (array_key_exists('empty', $opt)) {
        $html .= '<option value="">' . h($opt['empty']) . '</option>';
    }
    $html .= options_html($options, $selected);
    return $html . '</select>' . (!empty($opt['hint']) ? '<small>' . h($opt['hint']) . '</small>' : '') . field_error($name) . '</label>';
}

function options_html(array $options, mixed $selected): string
{
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . h($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    return $html;
}

function checkbox_field(string $name, string $label, bool $checked = false, ?string $hint = null): string
{
    return '<label class="check"><input type="checkbox" name="' . h($name) . '" value="1"' . ($checked ? ' checked' : '') . '> '
        . h($label) . ($hint ? ' <small>' . h($hint) . '</small>' : '') . '</label>';
}

/** Several checkboxes named "name[]". */
function checkboxes_field(string $name, string $label, array $options, array $checked = []): string
{
    $checked = array_map('strval', $checked);
    $html = '<fieldset class="field wide"><legend>' . h($label) . '</legend>';
    foreach ($options as $value => $text) {
        $html .= '<label class="check"><input type="checkbox" name="' . h($name) . '[]" value="' . h($value) . '"'
            . (in_array((string) $value, $checked, true) ? ' checked' : '') . '> ' . h($text) . '</label>';
    }
    return $html . field_error($name) . '</fieldset>';
}

/** A form with a single button (POST), e.g. "Take it", "Mark as done". */
function button_form(string $action, string $label, array $hidden = [], string $class = '', ?string $title = null): string
{
    $html = '<form method="post" action="' . h(url($action)) . '" class="inline">' . csrf_field() . back_field();
    foreach ($hidden as $k => $v) {
        $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return $html . '<button' . attrs(['class' => $class ?: null, 'title' => $title]) . '>' . h($label) . '</button></form>';
}

/**
 * A destructive action (delete, remove…). With JavaScript, the browser asks
 * "are you sure?"; without it, the server shows a confirmation page first
 * (see needs_confirmation() in http.php).
 */
function confirm_form(string $action, string $label, string $warning = '', array $hidden = [], string $class = 'link danger'): string
{
    $question = t('common.confirmQuestion', ['action' => $label]) . ' ' . (trim($warning) !== '' ? $warning : t('common.confirmDeleteText'));
    $html = '<form method="post" action="' . h(url($action)) . '" class="inline" data-confirm="' . h($question) . '">'
        . csrf_field() . back_field() . '<input type="hidden" name="confirmed" value="0"><input type="hidden" name="_question" value="' . h($question) . '">';
    foreach ($hidden as $k => $v) {
        $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return $html . '<button class="' . h($class) . '">' . h($label) . '</button></form>';
}

// ─── Small pieces ──────────────────────────────────────────────────────────

/** ": " in English, " : " in French (with a non-breaking space). */
function colon(): string
{
    return locale() === 'fr' ? "\u{00A0}: " : ': ';
}

/** A small label. Tones: ok, warn, bad, info, muted. */
function badge(string $text, string $tone = ''): string
{
    return '<span class="badge' . ($tone ? ' ' . h($tone) : '') . '">' . h($text) . '</span>';
}

/** Tone of a status badge. */
function tone(?string $status): string
{
    return match ($status) {
        'DONE', 'COMPLETED', 'ON_TRACK', 'GOING', 'INCOME', 'ACTIVE' => 'ok',
        'IN_PROGRESS', 'TAKEN', 'REVIEW', 'MAYBE' => 'info',
        'AT_RISK', 'HIGH', 'ON_HOLD', 'OPEN' => 'warn',
        'OFF_TRACK', 'URGENT', 'NOT_GOING', 'EXPENSE', 'CANCELLED' => 'bad',
        default => 'muted',
    };
}

function status_badge(string $enum, ?string $value): string
{
    return $value ? badge(enum_label($enum, $value), tone($value)) : '';
}

/** A horizontal bar for charts (a native <progress>: no inline style, no script). */
function bar(int|float $value, int|float $max, string $class = ''): string
{
    $max = max(1, $max);
    return '<progress class="' . h($class) . '" max="' . h($max) . '" value="' . h(max(0, $value)) . '">' . pct($value, $max) . ' %</progress>';
}

/** Native progress bar (works everywhere, no CSS needed) followed by the number. */
function progress_html(int $percent): string
{
    return '<span class="progress"><progress max="100" value="' . $percent . '">' . $percent . ' %</progress> ' . $percent . '&nbsp;%</span>';
}

/** Link to a member's page, or a dash. */
function person(?int $id, ?string $name): string
{
    return $id && $name ? '<a href="' . h(url("/members/$id")) . '">' . h($name) . '</a>' : '<span class="muted">—</span>';
}

/** A due date, in red when late. */
function due_html(?string $due, bool $done = false): string
{
    if (!$due) {
        return '';
    }
    $late = is_overdue($due, $done);
    return '<time datetime="' . h($due) . '" class="' . ($late ? 'late' : '') . '" title="' . h($done ? '' : due_label($due)) . '">'
        . h(fmt_date($due)) . ($late ? ' · ' . h(due_label($due)) : '') . '</time>';
}

function empty_html(string $text): string
{
    return '<p class="empty">' . h($text) . '</p>';
}

/** Page header: title, optional subtitle and actions. */
function page_head(string $title, string $subtitle = '', string $actions = ''): string
{
    return '<header class="page-head"><div><h1>' . h($title) . '</h1>' . ($subtitle ? '<p class="muted">' . h($subtitle) . '</p>' : '')
        . '</div>' . ($actions ? '<div class="actions">' . $actions . '</div>' : '') . '</header>';
}

/** Tabs made of plain links. $tabs = [key => [label, url]]. */
function tabs_html(array $tabs, string $active): string
{
    $html = '<nav class="tabs">';
    foreach ($tabs as $key => [$label, $href]) {
        $html .= '<a href="' . h($href) . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . h($label) . "</a>\n";
    }
    return $html . '</nav>';
}

/** Error page (404, 403, 500…), inside the workspace frame when possible. */
function render_error(int $code, string $detail = ''): void
{
    $code = in_array($code, [400, 403, 404, 405, 413, 422, 429, 500], true) ? $code : 500;
    $known = in_array($code, [403, 404, 405], true);
    $message = $known ? t("errors.$code.text") : ($detail !== '' && $code !== 500 ? $detail : t('errors.500.text'));
    if ($code === 403 && $detail !== '') {
        $message = $detail;
    }
    $vars = [
        'code' => $code,
        'heading' => t('errors.' . ($known ? $code : 500) . '.title'),
        'message' => $message,
        'detail' => $code === 500 && config('debug') ? $detail : '',
    ];
    if (ob_get_level() > 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    http_response_code($code);
    try {
        $layout = current_account() && current_association() ? 'app' : 'auth';
        render('error', $vars, $vars['heading'], $layout);
    } catch (Throwable $e) {
        error_log((string) $e);
        echo '<!doctype html><title>Error</title><h1>' . $code . '</h1><p>' . h($vars['message']) . '</p>';
    }
}
