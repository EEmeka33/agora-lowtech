<?php
/*
 * Translations. Every text of the interface lives in app/lang/en.php and
 * app/lang/fr.php as 'key' => 'text'. Texts may contain {placeholders}, and
 * plural forms separated by "|":  "{n} task|{n} tasks"  or  "none|{n} task|{n} tasks"
 * (zero | one | many). tests/run.php checks that both files have the same keys.
 */

/** The language of the current page: the account's, else the cookie's, else the browser's. */
function locale(?string $set = null): string
{
    static $locale = null;
    if ($set !== null && in_array($set, LOCALES, true)) {
        $locale = $set;
    }
    if ($locale === null) {
        $locale = detect_locale();
    }
    return $locale;
}

function detect_locale(): string
{
    $account = function_exists('current_account') ? current_account() : null;
    foreach ([$account['locale'] ?? null, $_COOKIE['agora_locale'] ?? null] as $candidate) {
        if (in_array($candidate, LOCALES, true)) {
            return $candidate;
        }
    }
    $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if (preg_match('/^([a-z]{2})/', $accept, $m) && in_array($m[1], LOCALES, true)) {
        return $m[1];
    }
    $default = (string) config('default_locale');
    return in_array($default, LOCALES, true) ? $default : 'en';
}

/** Loads a dictionary once per request. */
function dictionary(string $locale): array
{
    static $cache = [];
    return $cache[$locale] ??= require APP . "/lang/$locale.php";
}

/** Translates a key: t('tasks.title'), t('tasks.createdBy', ['name' => 'Léa']). */
function t(string $key, array $params = [], ?string $locale = null): string
{
    $text = dictionary($locale ?? locale())[$key] ?? dictionary('en')[$key] ?? $key;
    if (is_array($text)) {
        $text = implode("\n", $text);
    }
    if ($params) {
        $replace = [];
        foreach ($params as $k => $v) {
            $replace['{' . $k . '}'] = (string) $v;
        }
        $text = strtr($text, $replace);
    }
    return $text;
}

/** Translates a text with plural forms: tn('common.countTasks', 3) → "3 tasks". */
function tn(string $key, int $n, array $params = [], ?string $locale = null): string
{
    $locale ??= locale();
    $forms = explode('|', t($key, [], $locale));
    if (count($forms) === 3) {
        $form = $n === 0 ? $forms[0] : ($n === 1 ? $forms[1] : $forms[2]);
    } elseif (count($forms) === 2) {
        // French uses the singular for 0 and 1, English only for 1.
        $one = $locale === 'fr' ? $n <= 1 : $n === 1;
        $form = $one ? $forms[0] : $forms[1];
    } else {
        $form = $forms[0];
    }
    $replace = ['{n}' => (string) $n];
    foreach ($params as $k => $v) {
        $replace['{' . $k . '}'] = (string) $v;
    }
    return strtr($form, $replace);
}

/** Label of an enumerated value: enum_label('taskStatus', 'TODO') → "To do". */
function enum_label(string $enum, ?string $value): string
{
    return $value === null || $value === '' ? '' : t("enums.$enum.$value");
}

/** [VALUE => label] for a <select>. */
function enum_options(string $enum, array $values): array
{
    $options = [];
    foreach ($values as $v) {
        $options[$v] = enum_label($enum, $v);
    }
    return $options;
}
