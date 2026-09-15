<?php
/* ===================================================================
   ADMIN LOGOUT
   -------------------------------------------------------------------
   POST-only and CSRF-protected so a logout can never be triggered by
   an external link / cross-site request (login CSRF hardening, Phase
   5E). The admin top bar submits this as a small POST form.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    admin_logout();
    flash_set('success', 'You have been logged out.');
    redirect('login.php');
}

// Direct / GET access: nothing to log out, send them away.
redirect('dashboard.php');
