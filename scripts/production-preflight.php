<?php
/**
 * TaskForge deployment preflight. Read-only: never connects to services, sends mail,
 * migrates data, prints secret values, or changes the target environment.
 *
 * Usage: php scripts/production-preflight.php [path/to/.env]
 * Exit 0 means static configuration checks passed, NOT deployment approval.
 */
declare(strict_types=1);

function readEnvironment(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Configuration file is missing or unreadable.');
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $match)) {
            continue;
        }
        $value = trim($match[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) ||
            ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        } else {
            // Unquoted dotenv comment. Never display raw config values.
            $value = preg_replace('/\s+#.*$/', '', $value);
        }
        $values[$match[1]] = $value;
    }

    return $values;
}

function productionChecks(array $env): array
{
    $value = static fn (string $key): string => trim($env[$key] ?? '');
    $truthy = static fn (string $key): bool => in_array(strtolower($value($key)), ['true', '1'], true);
    $falsey = static fn (string $key): bool => in_array(strtolower($value($key)), ['false', '0'], true);
    $checks = [
        'Production environment' => $value('APP_ENV') === 'production',
        'Debugging disabled' => $falsey('APP_DEBUG'),
        'HTTPS public application URL' => filter_var($value('APP_URL'), FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($value('APP_URL'), PHP_URL_SCHEME)) === 'https'
            && !in_array(strtolower((string) parse_url($value('APP_URL'), PHP_URL_HOST)), ['localhost', '127.0.0.1'], true),
        'Valid Laravel encryption key' => str_starts_with($value('APP_KEY'), 'base64:')
            && strlen((string) base64_decode(substr($value('APP_KEY'), 7), true)) === 32,
        'HTTPS-only session cookies' => $truthy('SESSION_SECURE_COOKIE'),
        'HTTP-only session cookies' => !array_key_exists('SESSION_HTTP_ONLY', $env) || $truthy('SESSION_HTTP_ONLY'),
        'CSRF-compatible SameSite cookies' => in_array(strtolower($value('SESSION_SAME_SITE')), ['lax', 'strict'], true),
        'Persistent session store' => in_array($value('SESSION_DRIVER'), ['database', 'redis'], true),
        'Persistent background queue' => in_array($value('QUEUE_CONNECTION'), ['database', 'redis', 'sqs'], true),
        'Protected database credentials' => strlen($value('DB_PASSWORD')) >= 12
            && !in_array(strtolower($value('DB_PASSWORD')), ['password', 'root', 'secret', 'changeme'], true),
        'Non-development log verbosity' => in_array(strtolower($value('LOG_LEVEL')), ['warning', 'error', 'critical', 'alert', 'emergency'], true),
        'Explicit outbound mail decision' => in_array($value('MAIL_MAILER'), ['smtp', 'ses', 'postmark', 'resend', 'mailgun', 'failover'], true),
        'Verified mail sender' => filter_var($value('MAIL_FROM_ADDRESS'), FILTER_VALIDATE_EMAIL) !== false
            && !str_ends_with(strtolower($value('MAIL_FROM_ADDRESS')), '.test'),
    ];

    if ($value('BROADCAST_CONNECTION') === 'reverb') {
        $checks['Reverb server configured'] = $value('REVERB_APP_ID') !== ''
            && $value('REVERB_APP_KEY') !== ''
            && $value('REVERB_APP_SECRET') !== ''
            && $value('REVERB_SCHEME') === 'https'
            && $value('REVERB_HOST') !== '';
    } else {
        $checks['No unreviewed WebSocket deployment'] = in_array($value('BROADCAST_CONNECTION'), ['null', 'log'], true);
    }
    return $checks;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $path = $argv[1] ?? '.env';
    try {
        $results = productionChecks(readEnvironment($path));
    } catch (RuntimeException $e) {
        fwrite(STDERR, "FAIL: configuration cannot be evaluated.\n");
        exit(2);
    }

    foreach ($results as $name => $passed) {
        echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    }
    echo "Static checks only: verify infrastructure, secrets, authorization, backup restore, TLS and worker supervision separately.\n";
    exit(in_array(false, $results, true) ? 1 : 0);
}
