<?php
/* ===================================================================
   ADMIN - DELETE CATEGORY
   -------------------------------------------------------------------
   Refuses to delete a category that still has products in it, so
   products never end up with a broken/missing category.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('categories.php');
}

csrf_verify();

$categoryId = (int) ($_POST['id'] ?? 0);

if ($categoryId <= 0) {
    flash_set('error', 'Invalid category.');
    redirect('categories.php');
}

/* ==========================================
   CHECK FOR PRODUCTS USING THIS CATEGORY
========================================== */

$stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
$stmt->execute([$categoryId]);
$productCount = (int) $stmt->fetchColumn();

if ($productCount > 0) {
    flash_set(
        'error',
        "Can't delete this category - it still has {$productCount} product(s) assigned to it. Move or delete those products first."
    );
    redirect('categories.php');
}

/* ==========================================
   SAFE TO DELETE
========================================== */

$stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
$stmt->execute([$categoryId]);

flash_set('success', 'Category deleted.');
redirect('categories.php');
