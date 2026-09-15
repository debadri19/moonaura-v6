<?php
/* ===================================================================
   PASSWORD-RESET REQUEST THROTTLING
   -------------------------------------------------------------------
   Independent of login_attempts / login lockout. Limits forgot-
   password requests per email + client IP so reset emails cannot be
   bombed, without locking anyone out of login.

   Defaults:
     - 60-second cooldown between requests
     - 5 requests per 15-minute window, then a wait until the window
       expires
   File-backed (temp dir) so it still applies without cookies. The
   same generic throttle message is used whether or not the email
   belongs to an account.
=================================================================== */

if (!defined('PASSWORD_RESET_COOLDOWN_SECONDS')) {
    define('PASSWORD_RESET_COOLDOWN_SECONDS', 60);
}
if (!defined('PASSWORD_RESET_MAX_ATTEMPTS')) {
    define('PASSWORD_RESET_MAX_ATTEMPTS', 5);
}
if (!defined('PASSWORD_RESET_WINDOW_SECONDS')) {
    define('PASSWORD_RESET_WINDOW_SECONDS', 900);
}

function password_reset_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function password_reset_throttle_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'moonaura-reset-throttle';

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

function password_reset_throttle_key(string $scope, string $email): string
{
    return hash(
        'sha256',
        $scope . '|' . normalize_email($email) . '|' . password_reset_client_ip()
    );
}

function password_reset_throttle_path(string $scope, string $email): string
{
    return password_reset_throttle_dir()
        . DIRECTORY_SEPARATOR
        . password_reset_throttle_key($scope, $email)
        . '.json';
}

function password_reset_throttle_read(string $scope, string $email): array
{
    $path = password_reset_throttle_path($scope, $email);
    $empty = [
        'window_start' => 0,
        'count'        => 0,
        'last_at'      => 0,
    ];

    if (!is_file($path)) {
        return $empty;
    }

    $raw  = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;

    if (!is_array($data)) {
        return $empty;
    }

    return [
        'window_start' => (int) ($data['window_start'] ?? 0),
        'count'        => (int) ($data['count'] ?? 0),
        'last_at'      => (int) ($data['last_at'] ?? 0),
    ];
}

function password_reset_throttle_write(string $scope, string $email, array $data): void
{
    @file_put_contents(
        password_reset_throttle_path($scope, $email),
        json_encode([
            'window_start' => (int) ($data['window_start'] ?? 0),
            'count'        => (int) ($data['count'] ?? 0),
            'last_at'      => (int) ($data['last_at'] ?? 0),
        ]),
        LOCK_EX
    );
}

function password_reset_is_throttled(string $scope, string $email): bool
{
    $data = password_reset_throttle_read($scope, $email);
    $now  = time();

    if ($data['window_start'] > 0
        && ($now - $data['window_start']) >= PASSWORD_RESET_WINDOW_SECONDS
    ) {
        password_reset_throttle_write($scope, $email, [
            'window_start' => 0,
            'count'        => 0,
            'last_at'      => 0,
        ]);
        return false;
    }

    if ($data['count'] >= PASSWORD_RESET_MAX_ATTEMPTS) {
        return true;
    }

    if ($data['last_at'] > 0
        && ($now - $data['last_at']) < PASSWORD_RESET_COOLDOWN_SECONDS
    ) {
        return true;
    }

    return false;
}

function password_reset_throttle_hit(string $scope, string $email): void
{
    $now  = time();
    $data = password_reset_throttle_read($scope, $email);

    if ($data['window_start'] <= 0
        || ($now - $data['window_start']) >= PASSWORD_RESET_WINDOW_SECONDS
    ) {
        $data = [
            'window_start' => $now,
            'count'        => 0,
            'last_at'      => 0,
        ];
    }

    $data['count']   = $data['count'] + 1;
    $data['last_at'] = $now;

    password_reset_throttle_write($scope, $email, $data);
}

function password_reset_throttle_message(): string
{
    return 'Please wait a few minutes before requesting another password reset.';
}
