<?php
/*
 * Agora configuration.
 *
 * Copy this file to `config.php` and change what you need. Every setting is
 * optional: a missing key falls back to the value shown here.
 */
return [
    // Public address of this Agora, used in emails, invitation and calendar links.
    // Leave empty to detect it from each request.
    'base_url' => '',

    // Time zone used to display the time of server events (sign-ins, comments…).
    // Dates typed by people (events, due dates) are always shown as typed.
    'timezone' => 'Europe/Paris',

    // Language for visitors who are not signed in: 'en' or 'fr'.
    'default_locale' => 'en',

    // SQLite database file and uploads folder. Keep them OUTSIDE the public/ folder.
    'database'   => __DIR__ . '/data/agora.db',
    'upload_dir' => __DIR__ . '/data/uploads',

    // Uploads: maximum size, and whether big photos are reduced automatically
    // (longest side 1600 px, JPEG quality 80, needs the GD extension).
    'max_upload_mb' => 5,
    'shrink_images' => true,

    // Who can create an account:
    //   'open'   anyone
    //   'invite' only people with a join code (the very first account is always allowed)
    //   'closed' nobody: administrators add members and send them an activation link
    'signup_mode' => 'open',

    // May any signed-in person create a new association? (false = only the first one)
    'allow_new_associations' => true,

    // Cookies marked "Secure" (HTTPS only). null = automatic.
    'secure_cookies' => null,

    // Email (notifications, password reset).
    //   'off'  no email at all
    //   'log'  emails are appended to data/mail.log (development, courses)
    //   'mail' PHP's mail() function (works out of the box on most shared hosting)
    //   'smtp' a SMTP server (settings below)
    'mail' => [
        'transport'     => 'log',
        'from'          => 'Agora <no-reply@example.org>',
        'smtp_host'     => '',
        'smtp_port'     => 587,
        'smtp_secure'   => 'tls',   // 'tls' (STARTTLS, port 587), 'ssl' (port 465) or ''
        'smtp_user'     => '',
        'smtp_password' => '',
    ],

    // Data sobriety: read notifications and activity older than this are deleted.
    'retention_days' => 365,

    // Show the weight of each page in the footer (useful to teach, harmless otherwise).
    'show_page_weight' => true,

    // Show PHP errors in the browser (never on a public server).
    'debug' => false,
];
