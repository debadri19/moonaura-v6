<?php
/* ===================================================================
   ADMIN - NEWSLETTER CAMPAIGN IMAGE UPLOAD
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload-functions.php';

require_admin_login();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please choose a JPG, PNG, or WEBP image.']);
    exit;
}

$result = save_uploaded_image(
    $_FILES['image'],
    __DIR__ . '/../assets/uploads/newsletter',
    'nl'
);

if ($result['error'] !== null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$path = 'assets/uploads/newsletter/' . $result['path'];

echo json_encode([
    'ok'  => true,
    'url' => asset_url($path),
]);
