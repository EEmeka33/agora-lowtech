<?php
/*
 * Small helpers: dates, money, durations, tokens, passwords.
 * No dependency: month and day names come from the dictionaries (app/lang/).
 */

// ─── Text ──────────────────────────────────────────────────────────────────

/** Cuts a text to $max characters (UTF-8 safe, works without mbstring). */
function str_cut(?string $s, int $max): string
{
    $s = (string) $s;
    if (preg_match_all('/./us', $s, $m) <= $max) {
        return $s;
    }
    return rtrim(implode('', array_slice($m[0], 0, $max - 1))) . '…';
}

/** The first word of a name ("Camille Martin" → "Camille"). */
function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?: $name;
}

// ─── Dates ─────────────────────────────────────────────────────────────────
// Dates typed by people are stored as typed ("wall time"): '2026-03-04' or
// '2026-03-04 18:30'. Instants written by the server are UTC: '2026-03-04 17:30:00'.

/** Current UTC instant, as stored in created_at columns. */
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Current local date and time ("wall time", same format as typed dates). */
function now_wall(): string
{
    return date('Y-m-d H:i');
}

function today(): string
{
    return date('Y-m-d');
}

/** A UTC instant $seconds from now (negative = in the past). */
function utc_in(int $seconds): string
{
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}

/**
 * Reads a date typed in a form. Accepts what browsers send ("2026-03-04",
 * "2026-03-04T18:30") and what people type in old browsers without a date
 * picker ("04/03/2026", "04/03/2026 18:30"). Returns null when invalid.
 */
function parse_date_input(?string $input, bool $withTime = false): ?string
{
    $raw = trim((string) $input);
    if ($raw === '') {
        return null;
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{1,2}):(\d{2}))?#', $raw, $m)) {
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(?: (\d{1,2})[:h](\d{2}))?#', $raw, $m)) {
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } else {
        return null;
    }
    if (!checkdate($mo, $d, $y)) {
        return null;
    }
    $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
    if (!$withTime) {
        return $date;
    }
    $h = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
    $i = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
    if ($h > 23 || $i > 59) {
        return null;
    }
    return sprintf('%s %02d:%02d', $date, $h, $i);
}

/** Value for <input type="datetime-local">. */
function datetime_input(?string $wall): string
{
    return $wall ? str_replace(' ', 'T', substr($wall, 0, 16)) : '';
}

/** Converts a stored UTC instant to local time ('Y-m-d H:i'). */
function utc_to_local(string $utc): string
{
    return date('Y-m-d H:i', (int) strtotime($utc . ' UTC'));
}

/**
 * Formats a wall date. Styles:
 *   medium  4 Mar 2026     long  Wednesday 4 March     short  4 Mar
 *   datetime  Wed 4 Mar, 18:30     time  18:30     month  March 2026
 */
function fmt_date(?string $date, string $style = 'medium'): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime(substr($date, 0, 16) . (strlen($date) > 10 ? '' : ' 00:00') . ' UTC');
    if ($ts === false) {
        return $date;
    }
    $day = (int) gmdate('j', $ts);
    $month = (int) gmdate('n', $ts) - 1;
    $weekday = (int) gmdate('N', $ts) - 1;
    $months = explode(',', t('date.months'));
    $short = explode(',', t('date.monthsShort'));
    $days = explode(',', t('date.days'));
    $daysShort = explode(',', t('date.daysShort'));
    $year = gmdate('Y', $ts);
    $time = gmdate('H:i', $ts);
    return match ($style) {
        'long' => "{$days[$weekday]} $day {$months[$month]}",
        'short' => "$day {$short[$month]}",
        'datetime' => "{$daysShort[$weekday]} $day {$short[$month]}, $time",
        'time' => $time,
        'month' => "{$months[$month]} $year",
        default => "$day {$short[$month]} $year",
    };
}

/** Formats a UTC instant in local time: "4 Mar 2026, 18:30". */
function fmt_instant(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $local = utc_to_local($utc);
    return fmt_date($local) . ', ' . substr($local, 11, 5);
}

/** "3 days ago", "in 2 hours"… for a UTC instant or a wall date. */
function fmt_relative(?string $when, bool $isUtc = true): string
{
    if (!$when) {
        return '';
    }
    $ts = $isUtc ? strtotime($when . ' UTC') : strtotime($when);
    $diff = $ts - time();
    $abs = abs($diff);
    $dir = $diff < 0 ? 'past' : 'future';
    if ($abs < 60) {
        return t('time.now');
    }
    foreach ([[3600, 60, 'minutes'], [86400, 3600, 'hours'], [30 * 86400, 86400, 'days'], [365 * 86400, 30 * 86400, 'months']] as [$limit, $unit, $key]) {
        if ($abs < $limit) {
            return tn("time.$dir.$key", (int) round($abs / $unit));
        }
    }
    return tn("time.$dir.years", (int) round($abs / (365 * 86400)));
}

/** Whole days from date $a to date $b ('Y-m-d…'). */
function days_between(string $a, string $b): int
{
    return (int) round((strtotime(substr($b, 0, 10) . ' UTC') - strtotime(substr($a, 0, 10) . ' UTC')) / 86400);
}

function is_overdue(?string $due, bool $done = false): bool
{
    return $due && !$done && substr($due, 0, 10) < today();
}

/** "Due tomorrow", "3 days late"… */
function due_label(?string $due): string
{
    if (!$due) {
        return '';
    }
    $days = days_between(today(), $due);
    if ($days < 0) {
        return tn('common.overdueBy', -$days);
    }
    return tn('common.dueIn', $days);
}

// ─── Money ─────────────────────────────────────────────────────────────────

/** 123450 → "1 234,50 €" (fr) or "€1,234.50" (en). */
function fmt_money(?int $cents, ?string $currency = null, ?string $locale = null): string
{
    $currency = $currency ?? (current_association()['currency'] ?? 'EUR');
    $locale = $locale ?? locale();
    $value = ($cents ?? 0) / 100;
    $decimals = ($cents ?? 0) % 100 === 0 ? 0 : 2;
    $symbol = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF', 'CAD' => '$CA'][$currency] ?? $currency;
    $sign = $value < 0 ? '-' : '';
    if ($locale === 'fr') {
        return $sign . number_format(abs($value), $decimals, ',', "\u{202F}") . "\u{00A0}" . $symbol;
    }
    return $sign . $symbol . number_format(abs($value), $decimals, '.', ',');
}

/** "12,50" / "12.5" / "1 200" → cents. Null when empty or invalid. */
function parse_money(?string $input): ?int
{
    $raw = str_replace([' ', "\u{00A0}", "\u{202F}", '€', '$', '£'], '', trim((string) $input));
    $raw = str_replace(',', '.', $raw);
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (int) round((float) $raw * 100);
}

/** Cents → value for a form input ("12.5"). */
function money_input(?int $cents): string
{
    if ($cents === null) {
        return '';
    }
    $s = number_format($cents / 100, 2, '.', '');
    return str_ends_with($s, '.00') ? substr($s, 0, -3) : $s;
}

// ─── Durations ─────────────────────────────────────────────────────────────

/** "1h30", "1h", "90min", "45m", "1:30", "2.5", "2,5" → minutes (null if invalid or > 24 h). */
function parse_duration(?string $input): ?int
{
    $raw = preg_replace('/\s+/', '', str_replace(',', '.', strtolower(trim((string) $input))));
    if ($raw === '') {
        return null;
    }
    $minutes = null;
    if (preg_match('/^(\d+(?:\.\d+)?)h(?:(\d{1,2})(?:m|min)?)?$/', $raw, $m)) {
        $minutes = (float) $m[1] * 60 + (int) ($m[2] ?? 0);
    } elseif (preg_match('/^(\d+)(?:m|min|mn)$/', $raw, $m)) {
        $minutes = (int) $m[1];
    } elseif (preg_match('/^(\d+):(\d{2})$/', $raw, $m)) {
        $minutes = (int) $m[1] * 60 + (int) $m[2];
    } elseif (preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
        $minutes = (float) $raw * 60;
    }
    if ($minutes === null) {
        return null;
    }
    $minutes = (int) round($minutes);
    return $minutes > 0 && $minutes <= 24 * 60 ? $minutes : null;
}

/** 90 → "1.5" (or "1,5" in French). */
function minutes_to_hours(int $minutes): string
{
    $h = round($minutes / 60, 1);
    $s = $h == (int) $h ? (string) (int) $h : (string) $h;
    return locale() === 'fr' ? str_replace('.', ',', $s) : $s;
}

// ─── Numbers ───────────────────────────────────────────────────────────────

function pct(int|float $part, int|float $total): int
{
    return $total ? (int) round($part / $total * 100) : 0;
}

function clamp(int $n, int $min, int $max): int
{
    return min($max, max($min, $n));
}

function fmt_bytes(?int $bytes): string
{
    if (!$bytes) {
        return '0 B';
    }
    $units = explode(',', t('units.bytes'));
    $i = 0;
    $v = (float) $bytes;
    while ($v >= 1024 && $i < count($units) - 1) {
        $v /= 1024;
        $i++;
    }
    $s = number_format($v, $v < 10 && $i > 0 ? 1 : 0, locale() === 'fr' ? ',' : '.', '');
    return "$s {$units[$i]}";
}

/**
 * A horizontal bar made of text characters (█▏▎▍▌▋▊▉), $width characters long
 * for 100 %. Works in every browser, in text browsers and on paper.
 */
function text_bar(int|float $value, int|float $max, int $width = 20): string
{
    if ($max <= 0 || $value <= 0) {
        return '';
    }
    $eighths = (int) round(min(1, $value / $max) * $width * 8);
    $bar = str_repeat('█', intdiv($eighths, 8));
    $rest = $eighths % 8;
    return $bar . ($rest ? ['', '▏', '▎', '▍', '▌', '▋', '▊', '▉'][$rest] : '') ?: '▏';
}

// ─── Tokens ────────────────────────────────────────────────────────────────

/** 256-bit random token, URL-safe. */
function new_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

/** Tokens are stored hashed: a stolen database never exposes usable sessions or links. */
function sha256(string $value): string
{
    return hash('sha256', $value);
}

// No 0/O/1/I, to avoid confusion when codes are read aloud or typed.
const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

/** Human-friendly join code, e.g. "K7QXM-2RPZA" (32^10 ≈ 10^15 combinations). */
function new_join_code(): string
{
    $raw = '';
    for ($i = 0; $i < 10; $i++) {
        $raw .= CODE_ALPHABET[random_int(0, strlen(CODE_ALPHABET) - 1)];
    }
    return substr($raw, 0, 5) . '-' . substr($raw, 5);
}

/** "k7qxm2rpza", "K7QXM 2RPZA"… → "K7QXM-2RPZA" ('' if invalid). */
function normalize_join_code(?string $input): string
{
    $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $input));
    if (strlen($raw) !== 10 || strspn($raw, CODE_ALPHABET) !== 10) {
        return '';
    }
    return substr($raw, 0, 5) . '-' . substr($raw, 5);
}

// ─── Passwords (NIST SP 800-63B style: length over complexity) ──────────────

const MIN_PASSWORD_LENGTH = 10;
const MAX_PASSWORD_LENGTH = 200;
const COMMON_PASSWORDS = [
    'password', 'password1', 'password12', 'password123', 'passw0rd', 'motdepasse', 'motdepasse1',
    '1234567890', '0123456789', '123456789a', 'azertyuiop', 'qwertyuiop', 'qwerty1234', 'azerty1234',
    'iloveyou12', 'letmein123', 'welcome123', 'bienvenue1', 'administrateur', 'administrator',
    'abcdefghij', 'aaaaaaaaaa', '1111111111', 'football12', 'soleil1234', 'doudou1234', 'loveyou123',
    'changeme12', 'association', 'association1', 'agora12345', 'agora123456', 'lowtech123', 'lowtech1234',
];

/** What is wrong with a password: 'length' | 'tooLong' | 'common' | 'personal' | null. */
function password_issue(string $password, string $email = '', string $name = ''): ?string
{
    $length = preg_match_all('/./us', $password);
    if ($length < MIN_PASSWORD_LENGTH) {
        return 'length';
    }
    if ($length > MAX_PASSWORD_LENGTH) {
        return 'tooLong';
    }
    $lower = strtolower($password);
    if (in_array($lower, COMMON_PASSWORDS, true) || preg_match('/^(.)\1+$/u', $lower)) {
        return 'common';
    }
    $local = strtolower(explode('@', $email)[0] ?? '');
    if (strlen($local) >= 4 && str_contains($lower, $local)) {
        return 'personal';
    }
    foreach (preg_split('/\s+/', strtolower($name)) as $n) {
        if (strlen($n) >= 4 && in_array($lower, [$n, "{$n}123", "{$n}1234"], true)) {
            return 'personal';
        }
    }
    return null;
}

function valid_email(string $email): bool
{
    return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}
