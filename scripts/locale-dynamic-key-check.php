<?php

// Dynamic-key check — the runtime-assembled keys no static scanner can see.
//
// `LocaleUsageTest` verifies the *literal* keys written in code, and it deliberately
// skips a key that is assembled at runtime (`__('api/payments.'.$gateway.'.x')` ends in
// a concatenation, not a `,` or `)`, so its regex does not match). Those keys are
// exactly where a missing translation survives review: nothing compares them with
// English, and the user is shown a raw dotted key.
//
// This enumerates each runtime-assembled key set from its source of truth and asserts
// it resolves in every supported locale:
//
//   - `api/activity-log.events.*`  — one key per distinct recorded event name
//   - `api/notifications.types.*`  — one key per `NotificationType` case
//   - `api/error-log.statuses.*`   — one key per `ErrorLog::STATUS_*`
//   - `api/payments.*`             — the `message_key` values the gateway services
//                                    return, plus the Midtrans status labels
//   - English-sentence keys        — the reset-password notification resolves whole
//                                    English sentences from the flat pack, so each
//                                    sentence is a key that must be translated
//
// Scope is the backend, because the backend renders these: the API returns the
// translated `message` / `label` in the envelope, and the SPA prints what it is sent.
// (The SPA pack is a curated subset of the backend `api.*` namespace, not a mirror —
// 130 of 664 backend `api.*` keys have no SPA counterpart by design — so requiring SPA
// coverage here would invent a contract that does not exist.)
//
// The last block scans for *new* dynamic call sites and fails until the set above is
// extended, so the check cannot quietly fall behind the code.
//
// Usage:
//   php scripts/locale-dynamic-key-check.php               all locales
//   php scripts/locale-dynamic-key-check.php ko ru         only these locales
//   php scripts/locale-dynamic-key-check.php --verbose     per-key detail
//   php scripts/locale-dynamic-key-check.php --json=out.json also write the worklist
require __DIR__.'/lib/locale-scan-support.php';

$root = locale_scan_root();
require $root.'/vendor/autoload.php';

$locales = locale_scan_supported();

[$requested, $flags] = locale_scan_args($argv);
$verbose = in_array('--verbose', $flags, true);
$jsonOut = locale_scan_flag_value($flags, '--json');
$locales = locale_scan_select($locales, $requested, 'locale');

/**
 * Key sets, each with the code fact it is derived from.
 *
 * @return array<string, array{source: string, keys: array<int, string>}>
 */
function dynamicKeySets(string $root): array
{
    $sets = [];

    // Activity-log events: the resource builds `api/activity-log.events.'.$event`, so
    // every event name the audit trail can record needs a label.
    $events = array_keys((include $root.'/lang/en/api/activity-log.php')['events'] ?? []);
    $sets['api/activity-log.events.*'] = [
        'source' => 'ActivityLogResource::resolveEventLabel()',
        'keys' => array_map(static fn (string $e): string => 'api/activity-log.events.'.$e, $events),
    ];

    // Notification types: `NotificationType::messageKey()` returns
    // `api/notifications.types.{$this->value}`, one per enum case.
    $type = 'App\\Domain\\Notification\\Notifications\\NotificationType';
    $typeKeys = [];

    if (enum_exists($type)) {
        foreach ($type::cases() as $case) {
            $typeKeys[] = $case->messageKey();
        }
    }

    $sets['api/notifications.types.*'] = [
        'source' => $type.'::messageKey()',
        'keys' => array_values(array_unique($typeKeys)),
    ];

    // Error-log statuses: `ErrorLogResource` builds `api/error-log.statuses.'.$status`.
    $model = 'App\\Domain\\ErrorLog\\Models\\ErrorLog';
    $statuses = [];

    if (class_exists($model)) {
        foreach ((new ReflectionClass($model))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'STATUS_') && is_string($value)) {
                $statuses[] = $value;
            }
        }
    }

    $sets['api/error-log.statuses.*'] = [
        'source' => $model.'::STATUS_*',
        'keys' => array_map(
            static fn (string $s): string => 'api/error-log.statuses.'.$s,
            array_values(array_unique($statuses)),
        ),
    ];

    // Payment messages: the gateway services return a `message_key` the controller
    // passes straight to `__()`, and the Midtrans status endpoint assembles
    // `api/payments.midtrans.'.$label.'_ok'` from a request-supplied label.
    $paymentKeys = [];

    foreach (['midtrans', 'ipaymu'] as $gateway) {
        foreach (['external_sync_failed', 'invoice_not_payable', 'snap_created', 'charge_created', 'status_retrieved', 'sync_ok'] as $suffix) {
            $paymentKeys[] = 'api/payments.'.$gateway.'.'.$suffix;
        }
    }

    foreach (['approve', 'deny', 'cancel', 'expire', 'refund'] as $label) {
        $paymentKeys[] = 'api/payments.midtrans.'.$label.'_ok';
    }

    $paymentKeys[] = 'api/payments.channels.checkout_created';

    $sets['api/payments.* (runtime assembled)'] = [
        'source' => 'PaymentGateway services (`message_key`) + Midtrans status labels',
        'keys' => array_values(array_unique($paymentKeys)),
    ];

    // English-sentence keys: the reset-password notification resolves whole English
    // sentences from the flat pack, so each sentence is a key that must be translated.
    $sets['English-sentence keys (flat pack)'] = [
        'source' => 'ResetPassword notification + lang/{locale}/mail.php',
        'keys' => [
            'Reset your password',
            'You are receiving this email because we received a password reset request for your account.',
            'Reset Password',
            'This password reset link will expire in :count minutes.',
            'If you did not request a password reset, no further action is required.',
            'mail.reset_password_api_hint',
        ],
    ];

    return $sets;
}

$sets = dynamicKeySets($root);
$failures = [];
$report = [];

foreach ($locales as $locale) {
    $known = locale_scan_backend_keys($root, $locale);
    $missing = 0;

    foreach ($sets as $name => $set) {
        foreach ($set['keys'] as $key) {
            if (isset($known[$key]) || isset($known[str_replace('/', '.', $key)])) {
                continue;
            }

            $missing++;
            $failures[] = [$locale, $name, $key];
            $report[] = ['locale' => $locale, 'set' => $name, 'key' => $key];
        }
    }

    echo str_pad($locale, 8), str_pad((string) $missing, 8), 'missing key(s)', PHP_EOL;
}

// New dynamic call sites: a `__($var)` the check above does not know about is exactly
// the blind spot this guard exists to close, so its arrival must fail the build.
$knownSites = [
    'app/Domain/ActivityLog/Http/Resources/ActivityLogResource.php',
    'packages/notification/src/Services/NotificationAudienceService.php',
    'packages/notification/src/Http/Resources/NotificationResource.php',
    'app/Domain/ErrorLog/Http/Resources/ErrorLogResource.php',
    'app/Domain/PaymentGateway/Http/Controllers/API/V1/Payments/Midtrans/MidtransPaymentController.php',
    'app/Domain/PaymentGateway/Http/Controllers/API/V1/Payments/Ipaymu/IpaymuPaymentController.php',
    'app/Domain/PaymentGateway/Http/Controllers/API/V1/InvoiceChannelCheckoutController.php',
];

$unknownSites = [];

foreach (['app', 'routes', 'config', 'packages'] as $directory) {
    $base = $root.'/'.$directory;

    if (! is_dir($base)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        // The lookbehind matters: without it `forMidtrans($x)` matches `trans(`.
        if (! preg_match('/(?<![\w])(?:__|trans|trans_choice)\(\s*\$|(?<![\w])Lang::get\(\s*\$/m', $contents)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

        if (! in_array($relative, $knownSites, true)) {
            $unknownSites[] = $relative;
        }
    }
}

sort($unknownSites);

locale_scan_write_json($jsonOut, [
    'generated_by' => 'scripts/locale-dynamic-key-check.php',
    'sets' => array_map(
        static fn (array $set): array => ['source' => $set['source'], 'keys' => count($set['keys'])],
        $sets,
    ),
    'missing' => $report,
    'unknown_dynamic_call_sites' => $unknownSites,
]);

echo PHP_EOL, 'total missing: ', count($failures), PHP_EOL;

if ($verbose) {
    foreach ($failures as [$locale, $name, $key]) {
        echo PHP_EOL, $locale, '  ', $name, '  ', $key, PHP_EOL;
    }
}

foreach ($unknownSites as $site) {
    echo PHP_EOL, 'new dynamic translation call site: ', $site, PHP_EOL;
    echo '    add its key set to dynamicKeySets() in scripts/locale-dynamic-key-check.php', PHP_EOL;
}

exit($failures === [] && $unknownSites === [] ? 0 : 1);
