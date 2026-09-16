<?php
/* ===================================================================
   META (FACEBOOK) PIXEL - PHASE 1 INFRASTRUCTURE
   -------------------------------------------------------------------
   Centralized loader for the customer-facing storefront. Phase 1
   emits ONLY the base script + a single PageView event.

   Design notes:
   - The Pixel ID comes only from config.php (META_PIXEL_ID, which
     reads .env / config.local.php / the system environment). It is
     never hardcoded here or in templates.
   - When no valid numeric ID is configured, nothing is emitted and
     the storefront is byte-for-byte unchanged.
   - Admin and authenticated account-management pages are excluded.
   - No ecommerce/conversion events and no personally identifiable
     information are sent in this phase.
   - Independent of GA4 - shared config conventions only, no reuse of
     GA4 helpers and no changes to GA4 output.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';


/* ==========================================
   PIXEL ID
   Only a numeric ID (5-20 digits) is accepted.
   Anything else - empty, placeholder text, a
   fake value - keeps the Pixel disabled.
========================================== */

function meta_pixel_id(): string
{
    $id = defined('META_PIXEL_ID') ? trim((string) META_PIXEL_ID) : '';

    if ($id === '' || trim($id, '0') === '' || !preg_match('/^[0-9]{5,20}$/', $id)) {
        return '';
    }

    return $id;
}

function meta_pixel_is_configured(): bool
{
    return meta_pixel_id() !== '';
}


/* ==========================================
   PAGE EXCLUSION
   Meta Pixel is customer-facing only. Admin
   pages use their own admin-header/footer
   includes, but this is a defensive guard.
   Authenticated / account-management pages
   under /account/ are excluded too, even
   though the shared footer renders there.
========================================== */

function meta_pixel_is_excluded_page(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');

    if ($script === '') {
        return false;
    }

    $script = '/' . ltrim(str_replace('\\', '/', $script), '/');

    foreach (['/dashboard/', '/account/'] as $segment) {
        if (str_contains($script, $segment)) {
            return true;
        }
    }

    return false;
}


/* ==========================================
   BASE TAG + PAGEVIEW
   Called once, centrally, from the customer
   footer. Guarded so it can never be emitted
   twice in a single request.
========================================== */

function meta_pixel_print_base_tag(): void
{
    static $printed = false;

    if ($printed || !meta_pixel_is_configured() || meta_pixel_is_excluded_page()) {
        return;
    }

    $printed = true;

    $id     = meta_pixel_id();
    $idJson = json_encode($id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= $idJson ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
src="https://www.facebook.com/tr?id=<?= h($id) ?>&ev=PageView&noscript=1"></noscript>
    <?php
}


/* ===================================================================
   META (FACEBOOK) PIXEL - PHASE 2 INFRASTRUCTURE
   -------------------------------------------------------------------
   Browser-side ecommerce events only. Phase 1 above is left exactly
   as-is: the same base script + single PageView is still emitted once
   by meta_pixel_print_base_tag().

   How Phase 2 works:
   - Pages call the meta_pixel_track_*() wrappers below; they only
     QUEUE a standard Meta event descriptor. Nothing is sent here.
   - meta_pixel_print_events() (called once, right after the Phase 1
     base tag in the shared footer) loads the reusable JS helper and
     flushes the queue. The helper (assets/js/meta-pixel.js) owns the
     actual payload construction so every event is shaped identically.
   - Every wrapper is a no-op when no valid Pixel ID is configured or
     the page is excluded, and the helper no-ops when fbq is missing,
     so an unconfigured Pixel leaves the storefront behaviourally
     unchanged.

   Scope guardrails (Phase 2):
   - Standard Meta browser event names only (no invented events).
   - No PII, no Advanced Matching, no server-side/CAPI.
   - Reuses the existing GA4 item arrays (same product/cart data
     sources) - no extra product/cart queries are issued here.
   - Purchase is intentionally NOT queued here; it is added separately
     in the Phase 3 section below, driven only by order-success.php.
   =================================================================== */

function meta_pixel_currency(): string
{
    return 'INR';
}


/* ==========================================
   EVENT QUEUE
   Static buffer flushed by meta_pixel_print_events().
   Returned by reference so callers append to the single buffer.
========================================== */

function &meta_pixel_events(): array
{
    static $events = [];
    return $events;
}

function meta_pixel_is_valid_event_name(string $event): bool
{
    return (bool) preg_match('/^[A-Z][A-Za-z0-9]{0,39}$/', $event);
}


/* ==========================================
   QUEUE A STANDARD EVENT
   $data is a small descriptor understood by the JS helper:
     items         GA4-shaped item arrays (existing data source)
     type          'product' (default) or 'product_group'
     name          content_name override
     value         numeric value override (e.g. checkout total)
     contents      false to omit Meta's "contents" array
     search_string query for the Search event
     once          true to fire at most once per page load
========================================== */

function meta_pixel_track(string $event, array $data = []): void
{
    if (!meta_pixel_is_configured() || meta_pixel_is_excluded_page() || !meta_pixel_is_valid_event_name($event)) {
        return;
    }

    $events = &meta_pixel_events();
    $events[] = array_merge(['event' => $event], $data);
}


/* ==========================================
   STANDARD EVENT WRAPPERS
========================================== */

// ViewContent for a single product detail page (fired when the product
// content is actually available). Value is the current product price.
function meta_pixel_track_product_view(array $item): void
{
    meta_pixel_track('ViewContent', [
        'type'     => 'product',
        'items'    => [$item],
        'contents' => false,
        'once'     => true,
    ]);
}

// ViewContent (content_type: product_group) for a shop/category/
// listing page - one event per page load, never per product render.
function meta_pixel_track_list_view(array $items, string $name = ''): void
{
    $items = array_values($items);
    if (!$items) {
        return;
    }

    meta_pixel_track('ViewContent', [
        'type'  => 'product_group',
        'name'  => $name,
        'items' => $items,
        'once'  => true,
    ]);
}

// Search - only ever queued for a non-empty, actually-performed query.
function meta_pixel_track_search(string $query): void
{
    $query = trim($query);
    if ($query === '') {
        return;
    }

    meta_pixel_track('Search', [
        'search_string' => $query,
        'once'          => true,
    ]);
}

// ViewCart - cart page viewed with at least one item.
function meta_pixel_track_cart_view(array $items): void
{
    $items = array_values($items);
    if (!$items) {
        return;
    }

    meta_pixel_track('ViewCart', [
        'items' => $items,
        'once'  => true,
    ]);
}

// InitiateCheckout - checkout flow entered. $value is the existing
// checkout value (grand total) when supplied.
function meta_pixel_track_checkout(array $items, $value = null): void
{
    $items = array_values($items);
    if (!$items) {
        return;
    }

    $data = [
        'items' => $items,
        'once'  => true,
    ];

    if (is_numeric($value)) {
        $data['value'] = (float) $value;
    }

    meta_pixel_track('InitiateCheckout', $data);
}


/* ===================================================================
   META (FACEBOOK) PIXEL - PHASE 3 INFRASTRUCTURE
   -------------------------------------------------------------------
   Browser-side Purchase conversion event ONLY. Phase 1 (base tag +
   PageView) and Phase 2 (all pre-purchase ecommerce events above) are
   left exactly as-is; this section only adds the one new event.

   Firing contract:
   - The caller is responsible for invoking this ONLY once an order is
     genuinely completed/confirmed (see order-success.php, which never
     renders until payment is settled or the order was accepted as COD).
     It is never queued from cart/checkout/failure/admin/account pages.
   - value is the authoritative completed-order amount already recorded
     on the order (orders.grand_total). It is passed straight through -
     it is never recalculated here and the cart subtotal is never used.
   - items are the same GA4-shaped order-item arrays already built for
     the storefront's GA4 purchase event, so the canonical identifier
     strategy (SKU when present, otherwise product id) and the
     authoritative quantities are identical to Phase 2.
   - currency is the shared meta_pixel_currency() ("INR").

   Deduplication:
   - $dedupeKey is a stable, non-PII token identifying the completed
     order (its order number). assets/js/meta-pixel.js stores it in a
     small Purchase-only localStorage list so a refresh/revisit cannot
     emit a second Purchase. It is an internal guard value and is NEVER
     sent to Meta - the Meta payload carries no order id/transaction id.
   - $eventId (Phase 4) is the shared, opaque deduplication id also sent
     by the server-side Conversions API implementation in
     includes/meta-capi-functions.php. When present it is passed to the
     browser tag as Meta's standard eventID option (not as a custom
     parameter and never as the raw order number) so Meta can collapse
     the browser and server Purchase events into one conversion.

   Server-side Conversions API lives in its own file (Phase 4); it is
   credential-optional and never affects this browser path. Advanced
   Matching and browser-side PII remain deliberately out of scope.
   =================================================================== */

// Purchase - fire only for a genuinely completed order. $value is the
// authoritative final order amount, $items the authoritative order
// items, $dedupeKey the order's stable dedup token (never sent to Meta),
// $eventId the shared browser/server dedup id (Phase 4).
function meta_pixel_track_purchase(array $items, $value, string $dedupeKey = '', string $eventId = ''): void
{
    $items = array_values($items);

    if (!$items || !is_numeric($value) || (float) $value < 0) {
        return;
    }

    $data = [
        'type'  => 'product',
        'items' => $items,
        'value' => round((float) $value, 2),
        // Order-level event: there is no single product to name.
        'name'  => false,
        'once'  => true,
    ];

    if ($dedupeKey !== '') {
        $data['dedupe'] = $dedupeKey;
    }

    if ($eventId !== '') {
        $data['event_id'] = $eventId;
    }

    meta_pixel_track('Purchase', $data);
}


/* ==========================================
   PRINT QUEUED EVENTS + LOAD HELPER
   Called once, immediately after the Phase 1 base tag, so fbq is
   already defined. The helper drains the queue (and exposes
   window.moonauraMeta for the AJAX add/remove/wishlist events).
========================================== */

function meta_pixel_print_events(): void
{
    static $printed = false;

    if ($printed || !meta_pixel_is_configured() || meta_pixel_is_excluded_page()) {
        return;
    }

    $printed = true;

    $records      = &meta_pixel_events();
    $currencyJson = json_encode(meta_pixel_currency(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $helperUrl    = versioned_asset('assets/js/meta-pixel.js');
    ?>
<script>window.moonauraMetaCurrency = <?= $currencyJson ?>;</script>
<script src="<?= h($helperUrl) ?>"></script>
    <?php if ($records): ?>
<script>
window.moonauraMetaQueue = window.moonauraMetaQueue || [];
<?php foreach ($records as $record):
    $recordJson = json_encode(
        $record,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if ($recordJson === false) {
        continue;
    }
?>
window.moonauraMetaQueue.push(<?= $recordJson ?>);
<?php endforeach; ?>
</script>
    <?php endif;
}
