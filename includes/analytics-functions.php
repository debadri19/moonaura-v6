<?php
/* ===================================================================
   GOOGLE ANALYTICS 4 (STANDARD)
   -------------------------------------------------------------------
   Storefront gtag helpers + Admin Data API reads. Credentials come
   only from env() via config.php. No personal customer data is
   included in event payloads.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';


function ga4_measurement_id(): string
{
    $id = defined('GA4_MEASUREMENT_ID') ? trim((string) GA4_MEASUREMENT_ID) : '';

    if ($id === '' || !preg_match('/^G-[A-Z0-9]+$/i', $id)) {
        return '';
    }

    return $id;
}

function ga4_is_configured(): bool
{
    return ga4_measurement_id() !== '';
}

function ga4_property_id(): string
{
    $id = defined('GA4_PROPERTY_ID') ? trim((string) GA4_PROPERTY_ID) : '';
    $id = preg_replace('#^properties/#i', '', $id) ?? $id;
    $id = trim($id);

    if ($id === '' || !preg_match('/^[0-9]+$/', $id)) {
        return '';
    }

    return $id;
}

function ga4_currency(): string
{
    return 'INR';
}

function ga4_reporting_is_configured(): bool
{
    return ga4_property_id() !== '' && ga4_load_credentials() !== null;
}

function ga4_project_root(): string
{
    return dirname(__DIR__);
}

function ga4_web_root(): string
{
    $doc = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc === '') {
        return '';
    }

    $real = realpath($doc);

    return $real !== false ? $real : rtrim($doc, "/\\");
}

function ga4_is_absolute_path(string $path): bool
{
    return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
}

function ga4_normalize_fs_path(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function ga4_path_is_inside(string $path, string $root): bool
{
    if ($root === '') {
        return false;
    }

    $normalizedPath = strtolower(str_replace('\\', '/', $path));
    $normalizedRoot = strtolower(str_replace('\\', '/', rtrim($root, '/\\')));

    return $normalizedPath === $normalizedRoot
        || str_starts_with($normalizedPath, $normalizedRoot . '/');
}

function ga4_is_safe_credentials_file(string $path): bool
{
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return false;
    }

    $real = realpath($path);
    if ($real === false) {
        return false;
    }

    $webRoot = ga4_web_root();
    if ($webRoot !== '' && ga4_path_is_inside($real, $webRoot)) {
        error_log('GA4 credentials file must remain outside the public web root');
        return false;
    }

    return true;
}

function ga4_credentials_search_roots(): array
{
    $roots = [];

    $webRoot = ga4_web_root();
    if ($webRoot !== '') {
        $roots[] = $webRoot;
    }

    $roots[] = ga4_project_root();

    $script = trim((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($script !== '') {
        $scriptDir = dirname($script);
        if ($scriptDir !== '' && $scriptDir !== '.') {
            $roots[] = $scriptDir;
        }
    }

    $home = getenv('HOME');
    if (is_string($home) && trim($home) !== '') {
        $roots[] = trim($home);
    }

    $unique = [];
    foreach ($roots as $root) {
        $normalized = rtrim(ga4_normalize_fs_path($root), DIRECTORY_SEPARATOR);
        if ($normalized !== '' && !in_array($normalized, $unique, true)) {
            $unique[] = $normalized;
        }
    }

    return $unique;
}

function ga4_account_private_candidates(): array
{
    $relative = 'private' . DIRECTORY_SEPARATOR . 'moonaura-ga4.json';
    $candidates = [];
    $seen = [];

    foreach (ga4_credentials_search_roots() as $start) {
        $dir = $start;
        $guard = 0;

        while ($dir !== '' && $guard < 12) {
            $guard++;
            $candidate = $dir . DIRECTORY_SEPARATOR . $relative;
            if (!isset($seen[$candidate])) {
                $seen[$candidate] = true;
                $candidates[] = $candidate;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
    }

    return $candidates;
}

function ga4_resolve_credentials_path(): string
{
    $configured = defined('GA4_CREDENTIALS_PATH') ? trim((string) GA4_CREDENTIALS_PATH) : '';
    $candidates = [];

    if ($configured !== '') {
        if (ga4_is_absolute_path($configured)) {
            $candidates[] = ga4_normalize_fs_path($configured);
        } else {
            $relative = ltrim(ga4_normalize_fs_path($configured), DIRECTORY_SEPARATOR);
            $candidates[] = ga4_project_root() . DIRECTORY_SEPARATOR . $relative;
            foreach (ga4_credentials_search_roots() as $root) {
                $candidates[] = $root . DIRECTORY_SEPARATOR . $relative;
                $parent = dirname($root);
                if ($parent !== $root) {
                    $candidates[] = $parent . DIRECTORY_SEPARATOR . $relative;
                }
            }
        }
    }

    foreach (ga4_account_private_candidates() as $candidate) {
        $candidates[] = $candidate;
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        if ($candidate === '' || isset($seen[$candidate])) {
            continue;
        }
        $seen[$candidate] = true;

        if (ga4_is_safe_credentials_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function ga4_item_from_product(array $product, int $quantity = 1): array
{
    $sku = trim((string) ($product['sku'] ?? ''));
    $id  = (string) (int) ($product['id'] ?? 0);

    $item = [
        'item_id'   => $sku !== '' ? $sku : $id,
        'item_name' => (string) ($product['name'] ?? ''),
        'price'     => round((float) ($product['sell_price'] ?? $product['unit_price'] ?? 0), 2),
        'quantity'  => max(1, $quantity),
    ];

    $category = trim((string) ($product['category_name'] ?? ''));
    if ($category !== '') {
        $item['item_category'] = $category;
    }

    return $item;
}

function ga4_item_from_product_id(int $productId, int $quantity = 1): ?array
{
    if ($productId <= 0) {
        return null;
    }

    require_once __DIR__ . '/db.php';

    $stmt = db()->prepare(
        'SELECT p.id, p.sku, p.name, p.sell_price, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        return null;
    }

    return ga4_item_from_product($product, $quantity);
}

function ga4_items_from_products(array $products): array
{
    $items = [];
    $index = 0;

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $item = ga4_item_from_product($product, 1);
        $item['index'] = $index;
        $items[] = $item;
        $index++;
    }

    return $items;
}

function ga4_items_from_cart_items(array $cartItems): array
{
    $items = [];

    foreach ($cartItems as $cartItem) {
        $product  = $cartItem['product'] ?? null;
        $quantity = (int) ($cartItem['quantity'] ?? 1);
        if (!is_array($product)) {
            continue;
        }
        $items[] = ga4_item_from_product($product, $quantity);
    }

    return $items;
}

function ga4_items_from_order_items(array $orderItems): array
{
    $productIds = [];
    foreach ($orderItems as $orderItem) {
        $productId = (int) ($orderItem['product_id'] ?? 0);
        if ($productId > 0) {
            $productIds[] = $productId;
        }
    }
    $productIds = array_values(array_unique($productIds));

    $lookup = [];
    if ($productIds) {
        require_once __DIR__ . '/db.php';
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = db()->prepare(
            "SELECT p.id, p.sku, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id IN ($placeholders)"
        );
        $stmt->execute($productIds);
        foreach ($stmt->fetchAll() as $row) {
            $lookup[(int) $row['id']] = $row;
        }
    }

    $items = [];
    foreach ($orderItems as $orderItem) {
        $productId = (int) ($orderItem['product_id'] ?? 0);
        $meta      = $lookup[$productId] ?? [];
        $sku       = trim((string) ($meta['sku'] ?? ''));

        $item = [
            'item_id'   => $sku !== '' ? $sku : (string) $productId,
            'item_name' => (string) ($orderItem['product_name'] ?? ''),
            'price'     => round((float) ($orderItem['unit_price'] ?? 0), 2),
            'quantity'  => max(1, (int) ($orderItem['quantity'] ?? 1)),
        ];

        $category = trim((string) ($meta['category_name'] ?? ''));
        if ($category !== '') {
            $item['item_category'] = $category;
        }

        $items[] = $item;
    }

    return $items;
}

function ga4_queue_event(string $event, array $params = []): void
{
    if (!ga4_is_configured() || $event === '') {
        return;
    }

    $payload = json_encode(
        ['event' => $event, 'params' => $params],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
    );

    if ($payload === false) {
        return;
    }

    echo '<script>window.moonauraGa4Queue=window.moonauraGa4Queue||[];window.moonauraGa4Queue.push(' . $payload . ');</script>' . "\n";
}

function ga4_items_value(array $items): float
{
    $value = 0.0;
    foreach ($items as $item) {
        $value += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
    }

    return round($value, 2);
}

function ga4_print_storefront_tag(): void
{
    $measurementId = ga4_measurement_id();
    if ($measurementId === '') {
        return;
    }

    $config = [];
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $config['debug_mode'] = true;
    }

    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $idJson     = json_encode($measurementId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $currencyJson = json_encode(ga4_currency(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($measurementId) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?= $idJson ?><?= $configJson !== '[]' ? ', ' . $configJson : '' ?>);
window.moonauraGa4Currency = <?= $currencyJson ?>;
</script>
<script src="<?= versioned_asset('assets/js/ga4.js') ?>"></script>
    <?php
}

function ga4_get_dashboard_visitor_stats(): array
{
    $result = [
        'reporting_configured' => false,
        'current'              => null,
        'total'                => null,
        'current_status'       => 'Not Configured',
        'total_status'         => 'Not Configured',
        'period_label'         => date('F Y'),
    ];

    if (ga4_property_id() === '') {
        return $result;
    }

    if (!ga4_reporting_is_configured()) {
        $result['current_status'] = 'Unavailable';
        $result['total_status'] = 'Unavailable';
        return $result;
    }

    $result['reporting_configured'] = true;
    $result['current_status'] = 'Unavailable';
    $result['total_status'] = 'Unavailable';

    $current = ga4_fetch_active_users();
    if ($current !== null) {
        $result['current'] = $current;
        $result['current_status'] = 'ok';
    }

    $total = ga4_fetch_month_total_users();
    if ($total !== null) {
        $result['total'] = $total;
        $result['total_status'] = 'ok';
    }

    return $result;
}

function ga4_fetch_active_users(): ?int
{
    $body = [
        'metrics' => [
            ['name' => 'activeUsers'],
        ],
    ];

    $data = ga4_data_api_request('runRealtimeReport', $body);
    if ($data === null) {
        return null;
    }

    return ga4_read_metric_value($data);
}

function ga4_fetch_month_total_users(): ?int
{
    $body = [
        'dateRanges' => [
            [
                'startDate' => date('Y-m-01'),
                'endDate'   => 'today',
            ],
        ],
        'metrics' => [
            ['name' => 'totalUsers'],
        ],
    ];

    $data = ga4_data_api_request('runReport', $body);
    if ($data === null) {
        return null;
    }

    return ga4_read_metric_value($data);
}

function ga4_read_metric_value(array $data): ?int
{
    if (empty($data['rows']) || !is_array($data['rows'])) {
        return 0;
    }

    $value = $data['rows'][0]['metricValues'][0]['value'] ?? null;
    if ($value === null || !is_numeric($value)) {
        return null;
    }

    return (int) $value;
}

function ga4_load_credentials(): ?array
{
    static $cached = false;
    static $value = null;

    if ($cached) {
        return $value;
    }
    $cached = true;

    $path = ga4_resolve_credentials_path();
    $json = defined('GA4_CREDENTIALS_JSON') ? trim((string) GA4_CREDENTIALS_JSON) : '';
    $raw  = '';

    if ($path !== '') {
        $raw = (string) file_get_contents($path);
    } elseif ($json !== '') {
        $raw = $json;
    } else {
        error_log('GA4 credentials file is not readable');
        return null;
    }

    $data = json_decode($raw, true);
    if (
        !is_array($data)
        || empty($data['client_email'])
        || empty($data['private_key'])
    ) {
        error_log('GA4 credentials JSON is invalid');
        return null;
    }

    $value = $data;
    return $value;
}

function ga4_google_access_token(): ?string
{
    static $token = false;

    if ($token !== false) {
        return $token;
    }

    $creds = ga4_load_credentials();
    if ($creds === null) {
        $token = null;
        return null;
    }

    $now = time();
    $header = ga4_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = ga4_base64url(json_encode([
        'iss'   => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $unsigned = $header . '.' . $claims;
    $signature = '';
    $ok = openssl_sign($unsigned, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
    if (!$ok || $signature === '') {
        error_log('GA4 service-account JWT signing failed');
        $token = null;
        return null;
    }

    $jwt = $unsigned . '.' . ga4_base64url($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_TIMEOUT        => 8,
    ]);

    $responseBody = curl_exec($ch);
    $httpStatus   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        error_log('GA4 OAuth token request failed');
        $token = null;
        return null;
    }

    $decoded = json_decode((string) $responseBody, true);
    $access  = is_array($decoded) ? trim((string) ($decoded['access_token'] ?? '')) : '';

    if ($httpStatus >= 400 || $access === '') {
        error_log('GA4 OAuth token request returned HTTP ' . $httpStatus);
        $token = null;
        return null;
    }

    $token = $access;
    return $token;
}

function ga4_data_api_request(string $method, array $body): ?array
{
    $propertyId = ga4_property_id();
    $accessToken = ga4_google_access_token();
    if ($propertyId === '' || $accessToken === null) {
        return null;
    }

    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'
        . rawurlencode($propertyId) . ':' . $method;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 8,
    ]);

    $responseBody = curl_exec($ch);
    $httpStatus   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        error_log('GA4 Data API ' . $method . ' request failed');
        return null;
    }

    $decoded = json_decode((string) $responseBody, true);
    if ($httpStatus >= 400 || !is_array($decoded)) {
        error_log('GA4 Data API ' . $method . ' returned HTTP ' . $httpStatus);
        return null;
    }

    return $decoded;
}

function ga4_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
