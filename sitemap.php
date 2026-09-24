<?php
/* ===================================================================
   DYNAMIC XML SITEMAP
   -------------------------------------------------------------------
   Public indexable catalog + static storefront URLs only.
   Served as application/xml. Apache rewrites /sitemap.xml here.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/sitemap-functions.php';

try {
    $xml = sitemap_build_xml(sitemap_public_urls());
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Sitemap unavailable.';
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');
echo $xml;
