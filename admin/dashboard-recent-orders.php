<?php
/* ===================================================================
   ADMIN - DASHBOARD RECENT ORDERS (JSON, READ-ONLY)
   -------------------------------------------------------------------
   #38 Dashboard Live Order Panel: a minimal, read-only endpoint that
   admin/dashboard.php polls to refresh its Recent Orders panel
   without a full page reload. Deliberately does nothing else:
   - No writes, no state changes - a single SELECT, same shape and
     same columns admin/orders.php already reads and already trusts
     in the browser (order number, customer name, amount, status,
     date). Nothing new or more sensitive is exposed.
   - Same admin-session check every other admin page uses
     (is_admin_logged_in()) - reused as-is, not reimplemented. Unlike
     require_admin_login(), this returns a 401 JSON body instead of
     redirecting to login.php, since redirecting a background fetch()
     to an HTML login page would just look like a broken response to
     the polling script. The underlying "who's allowed in" logic is
     identical either way.
   - GET only, read-only - no csrf_verify() needed, matching every
     other GET-only admin page (e.g. orders.php's search/filter),
     which also don't require a CSRF token since nothing is written.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Same admin-session rule as require_admin_login(), just returning
// JSON instead of redirecting - see file header note above.
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Same columns admin/orders.php already selects for its list table -
// nothing extra, nothing new. Fixed, small LIMIT keeps this a cheap
// poll rather than a full order-list query.
$stmt = db()->query(
    'SELECT id, order_number, customer_name, grand_total, order_status, created_at
     FROM orders
     ORDER BY created_at DESC
     LIMIT 8'
);
$orders = $stmt->fetchAll();

$data = array_map(static function (array $order): array {
    return [
        'id'           => (int) $order['id'],
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'amount'       => format_price((float) $order['grand_total']),
        'status'       => $order['order_status'],
        'status_label' => ucfirst($order['order_status']),
        'created_at'   => date('d M Y, h:i A', strtotime($order['created_at'])),
        'detail_url'   => 'order-detail.php?id=' . (int) $order['id'],
    ];
}, $orders);

echo json_encode(['orders' => $data]);
