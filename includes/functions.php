<?php
/* ===================================================================
   GENERAL HELPER FUNCTIONS
   -------------------------------------------------------------------
   Small, reusable functions used across the site. Keep this file
   focused on GENERIC helpers only - page-specific logic belongs in
   the page itself, not here.
=================================================================== */


/* ==========================================
   OUTPUT ESCAPING (XSS PROTECTION)
   Wrap ANY database value or user input with
   h() before printing it into HTML.

   Example: <h1><?= h($product['name']) ?></h1>
========================================== */

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}


/* ==========================================
   SITE URL HELPER
   -------------------------------------------------
   Needed because includes/header.php and
   includes/footer.php are shared across pages at
   different folder depths (root pages, and now
   /account/ pages too) - a plain relative href like
   "shop.php" would break when the current page is
   /account/dashboard.php (it would resolve to
   /account/shop.php instead). This always builds a
   correct absolute path from SITE_URL instead.

    Usage: site_url('shop.php')
========================================== */

function site_url(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}


/* ==========================================
   PUBLIC ASSET URL HELPER
   -------------------------------------------------
   Shared CSS/JS/images/uploads always live on the
   public storefront host (SITE_URL), even when the
   current page is /account/... or the admin host.
   DB paths stay relative (e.g. assets/images/...).
   Filesystem paths (__DIR__ . '/../assets/...') are
   NOT URLs and must not go through this helper.

   Usage: asset_url('assets/css/style.css')
          asset_url($product['thumbnail'])
========================================== */

function asset_url(string $path = ''): string
{
    $path = trim($path);

    if ($path === '') {
        return rtrim(SITE_URL, '/') . '/';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = preg_replace('#^(\.\./)+#', '', $path) ?? $path;
    $path = ltrim($path, '/');

    return rtrim(SITE_URL, '/') . '/' . $path;
}


/* ==========================================
   VERSIONED LOCAL ASSET URL
   -------------------------------------------------
   Cache-busting for local CSS/JS only. Reuses
   asset_url() so public URLs stay on SITE_URL and
   image/upload callers of asset_url() are unchanged.
   The version is the file's mtime, so it stays
   stable until the file actually changes.

   $path is project-root relative
   (e.g. 'assets/css/style.css').
   Pass $url to keep an existing URL as-is
   (admin relative assets on a split host).

   Missing files return the unversioned URL.
   External http(s) paths are returned unchanged.

   Usage: versioned_asset('assets/css/style.css')
          versioned_asset('dashboard/assets/css/admin.css', 'assets/css/admin.css')
========================================== */

function versioned_asset(string $path, ?string $url = null): string
{
    $path = trim($path);

    if ($path === '') {
        return $url ?? asset_url('');
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $relative = preg_replace('#^(\.\./)+#', '', $path) ?? $path;
    $relative = ltrim($relative, '/');

    $resolvedUrl = $url ?? asset_url($relative);

    static $mtimeCache = [];

    if (!array_key_exists($relative, $mtimeCache)) {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $mtimeCache[$relative] = is_file($file) ? (int) filemtime($file) : 0;
    }

    $mtime = $mtimeCache[$relative];

    if ($mtime <= 0) {
        return $resolvedUrl;
    }

    $separator = str_contains($resolvedUrl, '?') ? '&' : '?';

    return $resolvedUrl . $separator . 'v=' . $mtime;
}


/* ==========================================
   ADMIN URL HELPER
   -------------------------------------------------
   Admin pages live on ADMIN_URL (split host in
   production). Do not use this for public assets.

   Usage: admin_url('login.php')
========================================== */

function admin_url(string $path = ''): string
{
    return rtrim(ADMIN_URL, '/') . '/' . ltrim($path, '/');
}


/* ==========================================
   ORDER-SUCCESS CERTIFICATE CARD
   -------------------------------------------------
   Show "Certificate of Authenticity Included" only
   when EVERY purchased product has
   certificate_included = 1. Any No, missing
   product_id, missing flag, or unavailable field
   hides the card (fail closed).
========================================== */

function order_should_show_certificate_included(array $orderItems, ?array $certificateFlagsByProductId): bool
{
    if ($certificateFlagsByProductId === null || $orderItems === []) {
        return false;
    }

    foreach ($orderItems as $orderItem) {
        $productId = (int) ($orderItem['product_id'] ?? 0);

        if ($productId <= 0 || !array_key_exists($productId, $certificateFlagsByProductId)) {
            return false;
        }

        if ((int) $certificateFlagsByProductId[$productId] !== 1) {
            return false;
        }
    }

    return true;
}


/* ==========================================
   FULLY DESTROY THE SESSION (Phase 3 audit)
   -------------------------------------------------
   Used by both admin and customer logout. Clears
   ALL session data (not just one "logged in" key),
   expires the session cookie, and destroys the
   session on the server - then starts a brand new,
   unrelated session so the page can still set a
   one-time flash message ("You have been logged
   out") that survives the redirect.
========================================== */

function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    session_start();
    session_regenerate_id(true);
}


/* ==========================================
   NORMALIZE AN EMAIL FOR STORAGE/COMPARISON
   Lowercases + trims, so "User@Example.com" and
   "user@example.com" are always treated as the
   same address everywhere (registration, login,
   checkout, guest-order linking).
========================================== */

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}


/* ==========================================
   REDIRECT
   Sends the visitor to another page and stops
   the script immediately.
========================================== */

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}


/* ==========================================
   FLASH MESSAGES
   A "flash message" is a one-time message
   (e.g. "Logged in successfully") that survives
   exactly one redirect, then disappears.
========================================== */

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]); // show it only once

    return $message;
}


/* ==========================================
   CSRF PROTECTION
   Every form that changes data (login, later:
   admin forms, checkout, etc.) should include
   csrf_field() inside the <form>, and the page
   that handles the submission should call
   csrf_verify() before doing anything else.
========================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Prints a hidden input field - place this inside any <form>.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

// Call at the top of any form-handling script, before using $_POST.
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Security check failed. Please go back and try again.');
    }
}


/* ==========================================
   AJAX REQUEST DETECTION
   -------------------------------------------------
   Customer cart/wishlist AJAX posts send both the
   X-Requested-With header and a POST ajax=1 field.
   Some hosts strip custom headers, so the POST
   field is the reliable fallback. Non-JS form
   posts never send either signal and keep the
   original redirect behaviour.
========================================== */

function is_ajax_request(): bool
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        return true;
    }

    return (($_POST['ajax'] ?? '') === '1');
}


/* ==========================================
   SLUG GENERATION
   Turns a name into a URL-friendly slug, e.g.
   generate_slug("Tiger Eye Bracelet")
       -> "tiger-eye-bracelet"
========================================== */

function generate_slug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug); // anything not a-z/0-9 becomes "-"
    $slug = trim($slug, '-');

    return $slug;
}


/* ==========================================
   UNIQUE SLUG
   Makes sure a slug doesn't already exist in
   the given table. If "tiger-eye-bracelet" is
   taken, tries "tiger-eye-bracelet-2", "-3", etc.

   $excludeId - pass the current row's id when
   editing, so it doesn't collide with itself.
========================================== */

function make_unique_slug(string $baseSlug, string $table, ?int $excludeId = null): string
{
    $slug   = $baseSlug;
    $suffix = 2;

    while (true) {

        $sql    = "SELECT id FROM {$table} WHERE slug = ?" . ($excludeId ? ' AND id != ?' : '');
        $params = $excludeId ? [$slug, $excludeId] : [$slug];

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $slug; // not taken - safe to use
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}


/* ==========================================
   FILE SIZE FORMATTING
   Formats bytes into a readable size, e.g.
   format_file_size(204800) -> "200 KB"
========================================== */

function format_file_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }

    return $bytes . ' bytes';
}


/* ==========================================
   PRICE FORMATTING
   Formats a number as Indian Rupees, e.g.
   format_price(509) -> "₹509"
   format_price(1499.5) -> "₹1,499.50"
========================================== */

function format_price(float $amount): string
{
    // Show 2 decimals only if the price actually has paise.
    $decimals = (fmod($amount, 1) === 0.0) ? 0 : 2;

    return '₹' . number_format($amount, $decimals);
}

/* ==========================================
   PAYMENT METHOD DISPLAY LABEL
   -------------------------------------------------
   Single source for the human-friendly payment-method
   name shown on order-success, admin order detail and
   account order detail. 'manual_upi' (Manual UPI QR)
   must not fall through to a generic "Paid Online".
========================================== */

function payment_method_label(string $method): string
{
    return match ($method) {
        'cod'        => 'Cash on Delivery',
        'manual_upi' => 'Manual UPI Payment',
        'razorpay'   => 'Paid Online (Razorpay)',
        'cashfree'   => 'Paid Online (Cashfree)',
        default      => ucfirst($method),
    };
}


/* ==========================================
   CATEGORY IMAGE FOLDER PATH SAFETY
   -------------------------------------------------
   categories.image_folder_name is a single folder
   segment under assets/images/products/. Reject
   traversal, absolute paths, and anything that
   would escape that directory.
========================================== */

function product_images_root(): string
{
    return dirname(__DIR__) . '/assets/images/products';
}

function normalize_local_path(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $isAbsolute = str_starts_with($path, '/')
        || preg_match('#^[A-Za-z]:/#', $path) === 1;

    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            if ($parts === []) {
                return '';
            }
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    $normalized = implode('/', $parts);

    if ($isAbsolute) {
        if (preg_match('#^[A-Za-z]:#', $path) === 1) {
            return substr($path, 0, 2) . '/' . $normalized;
        }
        return '/' . $normalized;
    }

    return $normalized;
}

function path_is_inside_directory(string $baseDir, string $candidatePath): bool
{
    $base = normalize_local_path($baseDir);
    $candidate = normalize_local_path($candidatePath);

    if ($base === '' || $candidate === '') {
        return false;
    }

    $basePrefixed = rtrim($base, '/') . '/';
    $candidatePrefixed = rtrim($candidate, '/') . '/';

    return $candidatePrefixed === $basePrefixed
        || str_starts_with($candidatePrefixed, $basePrefixed);
}

function is_safe_path_segment(string $segment): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,98}$/', $segment) === 1;
}

function sanitize_category_image_folder_name(string $name): ?string
{
    $name = trim(str_replace("\0", '', $name));

    if ($name === '' || strlen($name) > 100) {
        return null;
    }

    if (str_contains($name, '/')
        || str_contains($name, '\\')
        || str_contains($name, '..')
        || str_contains($name, ':')
        || str_contains($name, "\0")
    ) {
        return null;
    }

    if (!is_safe_path_segment($name)) {
        return null;
    }

    $root = product_images_root();
    $candidate = $root . '/' . $name;

    if (!path_is_inside_directory($root, $candidate)) {
        return null;
    }

    return $name;
}

function resolve_product_image_directory(string $relativeFolder): ?string
{
    $relativeFolder = trim(str_replace(["\0", '\\'], ['', '/'], $relativeFolder));

    if ($relativeFolder === '' || strlen($relativeFolder) > 200) {
        return null;
    }

    if (str_contains($relativeFolder, '..') || str_contains($relativeFolder, ':')) {
        return null;
    }

    $segments = [];
    foreach (explode('/', $relativeFolder) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..' || !is_safe_path_segment($segment)) {
            return null;
        }
        $segments[] = $segment;
    }

    if ($segments === []) {
        return null;
    }

    $root = product_images_root();
    $candidate = $root . '/' . implode('/', $segments);

    if (!path_is_inside_directory($root, $candidate)) {
        return null;
    }

    return $candidate;
}

function resolve_stored_product_image_path(string $relativePath): ?string
{
    $relativePath = trim(str_replace(["\0", '\\'], ['', '/'], $relativePath));
    $relativePath = ltrim($relativePath, '/');

    $prefix = 'assets/images/products/';
    if ($relativePath === '' || !str_starts_with($relativePath, $prefix)) {
        return null;
    }

    $under = substr($relativePath, strlen($prefix));
    if ($under === '' || str_contains($under, '..')) {
        return null;
    }

    $file = basename($under);
    $dir  = dirname($under);

    if ($dir === '.' || $dir === '/' || $dir === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,118}$/', $file) !== 1) {
        return null;
    }

    $directory = resolve_product_image_directory($dir);
    if ($directory === null) {
        return null;
    }

    $candidate = $directory . '/' . $file;
    if (!path_is_inside_directory(product_images_root(), $candidate)) {
        return null;
    }

    return $candidate;
}


/* ==========================================
   HTTP(S) URL VALIDATION
   -------------------------------------------------
   Accept only http:// and https:// URLs. Reject
   javascript:, data:, vbscript:, and other
   unsafe or malformed schemes.
========================================== */

function is_safe_http_url(string $url): bool
{
    $url = trim(str_replace("\0", '', $url));

    if ($url === '') {
        return true;
    }

    if (strlen($url) > 255 || preg_match('/[\x00-\x1f\x7f\s]/', $url) === 1) {
        return false;
    }

    if (preg_match('#^(javascript|data|vbscript|file|about|blob):#i', $url) === 1) {
        return false;
    }

    if (preg_match('#^https?://#i', $url) !== 1) {
        return false;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host   = (string) ($parts['host'] ?? '');

    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    if ($host === '' || str_contains($host, '\\') || str_contains($host, '..')) {
        return false;
    }

    return true;
}
