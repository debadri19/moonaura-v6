<?php
/* ===================================================================
   CUSTOMER LOGOUT
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';

customer_logout();

flash_set('success', 'You have been logged out.');
redirect('login.php');
