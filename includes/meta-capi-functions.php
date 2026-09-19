<?php
/* ===================================================================
   META (FACEBOOK) CONVERSIONS API (CAPI) - PHASE 4 FOUNDATION
   -------------------------------------------------------------------
   Server-side Conversions API support for the MoonAura storefront.
   This file is intentionally separate from meta-pixel-functions.php so
   the browser-side Phase 1/2/3 implementation stays byte-for-byte free
   of any server-to-server code.

   Design notes:
   - Credential-optional: everything here no-ops unless BOTH a valid
     META_PIXEL_ID and a non-empty META_CAPI_ACCESS_TOKEN are configured.
     With no token, no HTTP request is ever made and the storefront
     behaves exactly as it did before this phase existed.
    - The access token is read only via config.php (env() -> .env /
      system env / config.local.php, all gitignored). It is never echoed
      to HTML/JS, never written to logs, never placed in a URL, and
      never committed.
    - No PII is sent by default: only the privacy-safe request context
      (client IP + user agent) is attached when available. Phase 5
      Advanced Matching may pass already-collected customer identifiers
      through the existing hashing helpers; those values are SHA-256
      hashed server-side and never echoed to the browser.
   - Failures never propagate: CAPI is best-effort telemetry. Any HTTP
     error, timeout, malformed response, missing token or missing event
     data is swallowed and the customer's order-success page renders
     normally.
   - No retry queue, no background workers, no other Meta events.

   Phase 4 scope: Purchase only. The event is dispatched exclusively
   from order-success.php after the existing settled-order guard, and
   shares one stable, opaque event_id with the browser-side Phase 3
   Purchase so Meta can deduplicate the two.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/meta-pixel-functions.php';


/* ==========================================
   CONFIGURATION
========================================== */

// The CAPI access token, or '' when not configured. The value is only
// ever held server-side; callers must never echo/log it.
function meta_capi_access_token(): string
{
    $token = defined('META_CAPI_ACCESS_TOKEN') ? trim((string) META_CAPI_ACCESS_TOKEN) : '';

    return $token;
}

// Graph API version for the events endpoint. Keep this a valid,
// bounded version string so it can never be used to inject a path.
function meta_capi_api_version(): string
{
    $version = defined('META_CAPI_API_VERSION') ? trim((string) META_CAPI_API_VERSION) : '';

    if ($version === '' || !preg_match('/^v[0-9]{1,2}\.[0-9]{1,2}$/', $version)) {
        return 'v21.0';
    }

    return $version;
}

// CAPI is only "active" when both the dataset (Pixel) id and the
// access token are present. Missing token => permanently inactive.
function meta_capi_is_configured(): bool
{
    return meta_capi_access_token() !== '' && meta_pixel_id() !== '';
}

// The Graph API events endpoint WITHOUT any credential. The token is
// deliberately never placed in the URL (URLs can leak into logs).
function meta_capi_endpoint_url(): string
{
    $pixelId = meta_pixel_id();
    if ($pixelId === '') {
        return '';
    }

    return 'https://graph.facebook.com/' . meta_capi_api_version() . '/' . rawurlencode($pixelId) . '/events';
}

// The public URL where the Purchase happened. The order number is
// intentionally omitted from the query string so it is not forwarded
// to Meta as payload data.
function meta_capi_site_url(): string
{
    $base = defined('SITE_URL') ? trim((string) SITE_URL) : '';

    if ($base === '') {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base   = $scheme . '://' . $host;
        }
    }

    return $base;
}

function meta_capi_order_success_url(): string
{
    $base = meta_capi_site_url();

    return $base === '' ? '' : rtrim($base, '/') . '/order-success.php';
}


/* ==========================================
   EVENT ID (BROWSER + SERVER DEDUPLICATION)
   -------------------------------------------------
   One stable, opaque id per completed order, derived deterministically
   from the authoritative order identity. Both the browser Phase 3
   Purchase and the server CAPI Purchase use this exact value so Meta
   can collapse them into one conversion.

   The raw order number is never part of the emitted value - only a
   one-way digest - and the id is generated from existing order data
   (no new query, no second order-state mechanism).
========================================== */

function meta_capi_purchase_event_id(array $order): string
{
    $seed = trim((string) ($order['order_number'] ?? ''));

    if ($seed === '') {
        $seed = trim((string) ($order['id'] ?? ''));
    }

    if ($seed === '') {
        return '';
    }

    return 'ma_' . hash('sha256', 'moonaura:purchase:' . $seed);
}


/* ==========================================
   USER DATA (PRIVACY-SAFE MATCHING)
   -------------------------------------------------
   Default dispatch still sends only the request IP + user agent.
   Phase 5 may pass already-collected customer identifiers through
   these hashing helpers; hashed values are never exposed to the
   browser and empty values are omitted.
========================================== */

function meta_capi_hash_pii(string $value): string
{
    if (function_exists('meta_pixel_hash_pii')) {
        return meta_pixel_hash_pii($value);
    }

    $value = trim($value);

    return $value === '' ? '' : hash('sha256', $value);
}

function meta_capi_normalize_email(string $email): string
{
    if (function_exists('meta_pixel_normalize_email')) {
        return meta_pixel_normalize_email($email);
    }

    return strtolower(trim($email));
}

// Meta expects digits only (country code included, no symbols/spaces).
function meta_capi_normalize_phone(string $phone): string
{
    if (function_exists('meta_pixel_normalize_phone')) {
        return meta_pixel_normalize_phone($phone);
    }

    $digits = preg_replace('/\D+/', '', $phone);

    return is_string($digits) ? $digits : '';
}

function meta_capi_assign_hashed_list(array &$userData, string $key, string $normalized): void
{
    if ($normalized === '') {
        return;
    }

    $hash = meta_capi_hash_pii($normalized);
    if ($hash !== '') {
        $userData[$key] = [$hash];
    }
}

// Builds Meta's user_data object from an explicit context array.
// Recognised keys: email, phone, name/first_name/last_name, city, state,
// postal_code, country, client_ip_address, client_user_agent.
// Customer identifiers are normalized and SHA-256 hashed before inclusion.
function meta_capi_build_user_data(array $context = []): array
{
    $userData = [];

    meta_capi_assign_hashed_list(
        $userData,
        'em',
        meta_capi_normalize_email((string) ($context['email'] ?? ''))
    );

    meta_capi_assign_hashed_list(
        $userData,
        'ph',
        meta_capi_normalize_phone((string) ($context['phone'] ?? ''))
    );

    $first = trim((string) ($context['first_name'] ?? ''));
    $last  = trim((string) ($context['last_name'] ?? ''));
    if ($first === '' && $last === '' && function_exists('meta_pixel_split_full_name')) {
        $split = meta_pixel_split_full_name((string) ($context['name'] ?? ''));
        $first = $split['first_name'];
        $last  = $split['last_name'];
    }

    $normalizeName = function_exists('meta_pixel_normalize_name_part')
        ? 'meta_pixel_normalize_name_part'
        : static fn (string $value): string => strtolower(trim($value));
    $normalizeCity = function_exists('meta_pixel_normalize_city')
        ? 'meta_pixel_normalize_city'
        : static fn (string $value): string => strtolower(trim($value));
    $normalizeState = function_exists('meta_pixel_normalize_state')
        ? 'meta_pixel_normalize_state'
        : static fn (string $value): string => strtolower(trim($value));
    $normalizePostal = function_exists('meta_pixel_normalize_postal_code')
        ? 'meta_pixel_normalize_postal_code'
        : static fn (string $value): string => strtolower(trim($value));
    $normalizeCountry = function_exists('meta_pixel_normalize_country')
        ? 'meta_pixel_normalize_country'
        : static fn (string $value): string => strtolower(trim($value));

    meta_capi_assign_hashed_list($userData, 'fn', $normalizeName($first));
    meta_capi_assign_hashed_list($userData, 'ln', $normalizeName($last));
    meta_capi_assign_hashed_list($userData, 'ct', $normalizeCity((string) ($context['city'] ?? '')));
    meta_capi_assign_hashed_list($userData, 'st', $normalizeState((string) ($context['state'] ?? '')));
    meta_capi_assign_hashed_list($userData, 'zp', $normalizePostal((string) ($context['postal_code'] ?? '')));
    meta_capi_assign_hashed_list($userData, 'country', $normalizeCountry((string) ($context['country'] ?? '')));

    $ip = trim((string) ($context['client_ip_address'] ?? ''));
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
        $userData['client_ip_address'] = $ip;
    }

    $userAgent = trim((string) ($context['client_user_agent'] ?? ''));
    if ($userAgent !== '') {
        $userData['client_user_agent'] = $userAgent;
    }

    return $userData;
}

// Default request context for a storefront Purchase. NO customer PII is
// read or sent here - only the request's own network context.
function meta_capi_request_user_data(): array
{
    return meta_capi_build_user_data([
        'client_ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'client_user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ]);
}


/* ==========================================
   PURCHASE EVENT BUILDER
   -------------------------------------------------
   Reuses the authoritative order row (grand_total) and the canonical
   GA4-shaped order items already built by order-success.php (SKU when
   present, otherwise product id; authoritative quantities). Nothing is
   recalculated from the cart.
========================================== */

function meta_capi_purchase_custom_data(array $order, array $items): array
{
    $value = $order['grand_total'] ?? null;

    if (!is_numeric($value) || (float) $value < 0) {
        return [];
    }

    $contentIds = [];
    $contents   = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = trim((string) ($item['item_id'] ?? ''));
        if ($id === '') {
            $id = trim((string) ($item['id'] ?? ''));
        }
        if ($id === '') {
            continue;
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));

        $contentIds[] = $id;
        $contents[]   = [
            'id'       => $id,
            'quantity' => $quantity,
        ];
    }

    if (!$contentIds) {
        return [];
    }

    return [
        'value'        => round((float) $value, 2),
        'currency'     => 'INR',
        'content_ids'  => $contentIds,
        'content_type' => 'product',
        'contents'     => $contents,
    ];
}

// Builds the single CAPI event. Returns [] when the event id or the
// authoritative order data is unavailable, so callers can safely skip.
function meta_capi_build_purchase_event(array $order, array $items, string $eventId, string $sourceUrl = '', array $userData = []): array
{
    $eventId = trim($eventId);
    if ($eventId === '') {
        return [];
    }

    $customData = meta_capi_purchase_custom_data($order, $items);
    if (!$customData) {
        return [];
    }

    $event = [
        'event_name'    => 'Purchase',
        'event_time'    => time(),
        'event_id'      => $eventId,
        'action_source' => 'website',
        'custom_data'   => $customData,
    ];

    if ($sourceUrl !== '') {
        $event['event_source_url'] = $sourceUrl;
    }

    if ($userData) {
        $event['user_data'] = $userData;
    }

    return $event;
}


/* ==========================================
   HTTP TRANSPORT
   -------------------------------------------------
   A test/mock transport may be injected with
   meta_capi_set_transport(); production always uses a real server-side
   request. The transport contract is:
     function (string $url, array $payload, string $jsonBody, int $timeout): array
   returning ['ok' => bool, 'status' => int, 'body' => string, 'error' => string].
========================================== */

function &meta_capi_transport_ref()
{
    static $transport = null;

    return $transport;
}

function meta_capi_set_transport(?callable $transport): void
{
    $ref = &meta_capi_transport_ref();
    $ref = $transport;
}

function meta_capi_transport(): ?callable
{
    $transport = meta_capi_transport_ref();

    return is_callable($transport) ? $transport : null;
}

// Server-side POST of a JSON payload. Uses an injected transport when
// present (tests), otherwise cURL, otherwise PHP streams. Never throws.
function meta_capi_http_post_json(string $url, array $payload, int $timeout = 5): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'encode_error'];
    }

    $transport = meta_capi_transport();
    if ($transport !== null) {
        try {
            $result = $transport($url, $payload, $body, $timeout);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'transport_exception'];
        }

        return is_array($result) ? $result : ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'transport_error'];
    }

    if (function_exists('curl_init')) {
        return meta_capi_http_post_curl($url, $body, $timeout);
    }

    return meta_capi_http_post_stream($url, $body, $timeout);
}

function meta_capi_http_post_curl(string $url, string $body, int $timeout): array
{
    $ch = curl_init($url);

    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init_failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        return [
            'ok'     => false,
            'status' => $status,
            'body'   => '',
            'error'  => $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network_error',
        ];
    }

    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'body'   => (string) $response,
        'error'  => '',
    ];
}

function meta_capi_http_post_stream(string $url, string $body, int $timeout): array
{
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $body,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
    }

    if ($response === false) {
        return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'network_error'];
    }

    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'body'   => (string) $response,
        'error'  => '',
    ];
}


/* ==========================================
   FAILURE LOGGING
   -------------------------------------------------
   A single, shallow reason code only. Deliberately never logs the
   access token, customer data, the order, or the event payload.
========================================== */

function meta_capi_log_failure(array $result): void
{
    $reason = trim((string) ($result['error'] ?? ''));

    if ($reason === '' && !empty($result['status'])) {
        $reason = 'http_' . (int) $result['status'];
    }

    if ($reason === '') {
        $reason = 'unknown_error';
    }

    error_log('[moonaura-meta-capi] Purchase dispatch failed: ' . $reason);
}


/* ==========================================
   DISPATCH
   -------------------------------------------------
   Best-effort, non-blocking, never throws. Returns true only when the
   request was sent and Meta acknowledged it; false in every other case
   (unconfigured, missing data, HTTP/network/timeout/malformed).
   Callers must ignore the return value for UI purposes.
========================================== */

function meta_capi_send_purchase(array $order, array $items, string $eventId, string $sourceUrl = '', ?array $userData = null): bool
{
    if (!meta_capi_is_configured()) {
        return false;
    }

    if ($sourceUrl === '') {
        $sourceUrl = meta_capi_order_success_url();
    }

    if ($userData === null) {
        $userData = meta_capi_request_user_data();
    }

    $event = meta_capi_build_purchase_event($order, $items, $eventId, $sourceUrl, $userData);
    if (!$event) {
        return false;
    }

    $url = meta_capi_endpoint_url();
    if ($url === '') {
        return false;
    }

    // The token is passed in the JSON body, never the URL.
    $payload = [
        'data'         => [$event],
        'access_token' => meta_capi_access_token(),
    ];

    $result = meta_capi_http_post_json($url, $payload, 5);

    if (empty($result['ok'])) {
        meta_capi_log_failure($result);
        return false;
    }

    // A 2xx with a non-empty, non-JSON body is treated as a malformed
    // acknowledgement rather than a success.
    $responseBody = (string) ($result['body'] ?? '');
    if ($responseBody !== '' && json_decode($responseBody, true) === null) {
        meta_capi_log_failure(['error' => 'malformed_response']);
        return false;
    }

    return true;
}
