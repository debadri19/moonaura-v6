<?php
/* ===================================================================
   PHP BUILT-IN SERVER ROUTER
   -------------------------------------------------------------------
   Apache uses .htaccess to map /sitemap.xml -> sitemap.php.
   php -S does not read .htaccess, so local preview needs this file:

     SITE_URL=http://127.0.0.1:8000 php -S 127.0.0.1:8000 -t /workspace router.php

   Returning false lets the built-in server serve the file as-is.
=================================================================== */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($uri) || $uri === '') {
    $uri = '/';
}

if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

return false;
