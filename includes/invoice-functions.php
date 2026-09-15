<?php
/* ===================================================================
   INVOICE FUNCTIONS
   -------------------------------------------------------------------
   Phase 3B (Invoice System). Four responsibilities:

   1. get_or_create_invoice_number() - the ONLY place invoice_number/
      invoice_generated_at are ever written. Lazy: an order has no
      invoice until someone actually downloads/prints one.
   2. register_invoice_logo() - optional logo embedding (see its own
      doc comment - this is what satisfies "future branding additions
      without an architecture change").
   3. build_invoice_pdf() - renders a PDF from an order snapshot using
      SimplePdfWriter (includes/lib/SimplePdfWriter.php).
   4. generate_guest_invoice_token()/verify_guest_invoice_token() -
      signed-link authorization so a GUEST (no account, no session)
      can download their own invoice from order-success.php without
      exposing order_id or trusting the order_number in the URL on
      its own - see their own doc comment, and guest-invoice.php.

   ON-DEMAND GENERATION, NOT STORED PDFs - WHY:
   The PDF is rebuilt from `orders`/`order_items`/`order_addresses` on
   every single download/print request; no PDF file is ever written
   to disk, and there is no "invoices" table. Deliberate, not a
   shortcut:
     - The order data (product name/price/GST snapshot in
       order_items, address in order_addresses) is already immutable
       and already the correct source of truth - it's exactly what
       was captured at checkout and never edited afterwards. A stored
       PDF would be a SECOND copy of that same information, in a
       format that can't be corrected if a rendering bug is ever
       fixed - regenerating from the snapshot means every invoice,
       past and future, automatically reflects the current (fixed)
       renderer.
     - No file storage / cleanup / disk-space concern, no orphaned
       files, no filesystem permissions to manage on deploy.
     - Regenerating a one-page PDF from ~10 database rows is cheap -
       well under what a network round-trip already costs on this
       page - so there's no real performance argument for caching it
       as a file. (The one place this isn't perfectly free is a logo
       image: see register_invoice_logo()'s doc comment on its
       per-pixel alpha-extraction cost.)
     - invoice_number/invoice_generated_at are still stored (on
       `orders` directly - both columns have existed since Phase 2A)
       specifically so the number and date stay FIXED after first
       generation - that's the one thing that must persist, and it's
       two small columns, not a file.

   DETERMINISM ("the generated PDF must remain identical every time it
   is downloaded" - verified):
   Every value that reaches SimplePdfWriter's drawing calls below
   comes from either the stored order snapshot (orders/order_items/
   order_addresses - immutable after checkout) or from get_setting()
   (business details - see below). Nothing here reads date()/time()
   for "now", rand(), uniqid(), or anything else that changes between
   requests - the two dates shown on the invoice
   (invoice_generated_at, created_at) are themselves stored columns,
   not computed at render time. SimplePdfWriter's own output() is
   likewise pure - see its doc comment. Net effect: two downloads of
   the same order, with no settings change in between, produce
   byte-identical PDFs.
   The one input that CAN legitimately change output between
   downloads is business settings (business_name/business_gstin/etc.)
   - if an admin edits those in Settings, future downloads reflect
   the new values. This is intentional, not a determinism bug: unlike
   product/price data (which is snapshotted into order_items because
   an invoice must always show what was actually purchased), the
   seller's own letterhead details are NOT part of what changed hands
   in the transaction - showing the business's current, correct
   contact details on every invoice (rather than freezing whatever
   was configured, or misconfigured, on the day of purchase) is the
   more useful and more correct behaviour for a live business. Worth
   knowing about, not a defect - noted again in PROJECT_STATE.md.

   BUSINESS INFO - CONFIGURABLE, NOT HARDCODED:
   All fields required on a GST invoice are read via get_setting()
   with a settings key of the form business_<field>:
   business_name, business_trade_name, business_gstin,
   business_address, business_phone, business_email,
   business_website (see get_invoice_business_details() below). None
   of these are hardcoded in this file. Defaults passed to
   get_setting() are real,
   already-published values from this site's own Support page
   (address/phone/email) - NOT invented placeholder data - except
   business_gstin and business_trade_name, for which no real value
   exists anywhere in this codebase, so those two default to null and
   print as "Not configured" on the invoice until an admin sets them.
   Phase 5G added the Business / GST section to admin/settings.php
   (business_name / trade_name / gstin / address / phone / email /
   website, plus the Phase 5F business_state) - so since that phase
   these are editable through the admin UI instead of a direct
   `INSERT INTO settings` statement.

   PHASE 5G (GST-compliant production invoice):
   - Per-line GST Rate column in the items table (snapshotted
     order_items.gst_rate - the same snapshot that made the Phase 5F
     order-level GST totals immutable).
   - A proper tax breakdown instead of a single "GST (x%)" line:
     Taxable Value + CGST / SGST / IGST rows (only those with a
     non-zero amount are drawn, and a rate suffix like "CGST @9%"
     appears only when the whole order is a single rate - mixed-rate
     orders show amounts without a rate label, since the per-line
     rates are already shown next to each line item). All values come
     from the stored Phase 5F snapshot (taxable_value, cgst_amount,
     sgst_amount, igst_amount) - nothing is recalculated at render
     time, so a pre-5F historical order (taxable_value NULL, GST 0)
     simply shows no tax rows and stays accurate.
   - PDF /Info metadata (Title/Author/Subject/Keywords/Creator/
     Producer/CreationDate via SimplePdfWriter::setMetadata()) - the
     metadata is built from the same stored order snapshot + settings
     as the visible content, so it stays deterministic.
   - A "A Brand by DS Lifestyle" tagline under the business name, and
     the optional business_website displayed when configured.

   KNOWN LIMITATION (documented, not hidden): the hand-rolled
   SimplePdfWriter emits PDF 1.4 with no encryption, so "read-only" /
   owner-password protection (PDF /Encrypt dict, e.g. "allow print but
   no editing") is NOT supported. Nothing here pretends otherwise -
   an invoice generated by this system is a normal, fully-editable
   PDF. If read-only protection is ever required, the standard
   options are: (a) post-process the generated bytes with an external
   tool that does real encryption (qpdf --encrypt, Ghostscript, or
   LibreOffice), or (b) swap SimplePdfWriter for a maintained library
   (TCPDF/FPDF/dompdf, all of which support RC4/AES encryption). See
   PROJECT_STATE.md for the full write-up.
=================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order-functions.php';
require_once __DIR__ . '/settings-functions.php';
require_once __DIR__ . '/invoice-designer-functions.php';
require_once __DIR__ . '/lib/SimplePdfWriter.php';


/* ==========================================
   GET (OR LAZILY CREATE) AN ORDER'S INVOICE NUMBER
   -------------------------------------------------
   Locks the order's own row with SELECT ... FOR UPDATE before
   deciding whether to generate - same technique
   generate_daily_sequence_number() uses on `daily_sequences`, applied
   here to `orders` instead. Without this lock, two near-simultaneous
   requests for the same not-yet-invoiced order (a doubled click, or
   Download and Print opened in two tabs at once) could each read
   invoice_number as NULL, each generate a DIFFERENT number from
   generate_invoice_number(), and race to write - the loser's number
   would be silently discarded but still have consumed a slot in the
   day's sequence. The lock makes the second request wait for the
   first to commit, then simply see the number the first one just
   wrote and reuse it - exactly one number is ever generated per
   order.

   Returns ['invoice_number' => string, 'invoice_generated_at' => string]
   Throws if the order doesn't exist.
========================================== */

function get_or_create_invoice_number(int $orderId): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT invoice_number, invoice_generated_at FROM orders WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException("get_or_create_invoice_number(): order #{$orderId} not found");
        }

        // Already generated - reuse both the number and the original
        // date (an invoice's date never moves, no matter how many
        // times it's re-downloaded).
        if ($row['invoice_number'] !== null) {
            $pdo->commit();
            return [
                'invoice_number'       => $row['invoice_number'],
                'invoice_generated_at' => $row['invoice_generated_at'],
            ];
        }

        // First time - generate_invoice_number() runs its own
        // transaction-safe INSERT/SELECT-FOR-UPDATE/UPDATE cycle
        // against `daily_sequences`, safe to call from inside this
        // already-open transaction (same convention documented on
        // generate_order_number() in order-functions.php).
        $invoiceNumber = generate_invoice_number();
        $generatedAt   = date('Y-m-d H:i:s');

        $pdo->prepare(
            'UPDATE orders SET invoice_number = ?, invoice_generated_at = ? WHERE id = ?'
        )->execute([$invoiceNumber, $generatedAt, $orderId]);

        $pdo->commit();

        return ['invoice_number' => $invoiceNumber, 'invoice_generated_at' => $generatedAt];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('get_or_create_invoice_number() failed: ' . $e->getMessage());
        throw $e;
    }
}


/* ==========================================
   GUEST INVOICE DOWNLOAD - SIGNED TOKEN
   -------------------------------------------------
   Lets a guest (no account, no session) download their own invoice
   from order-success.php without exposing order_id or trusting
   order_number alone - the link also carries an HMAC-SHA256
   signature over (order_number, expiry) keyed by INVOICE_TOKEN_SECRET
   (config/config.php), verified with hash_equals() (timing-safe, same
   convention already used for CSRF/webhook-signature checks
   elsewhere in this project). Forging a valid token without the
   secret is computationally infeasible regardless of how guessable
   order_number itself is (e.g. sequential MOAOD... numbers) - the
   signature is what actually authorizes the request, not obscurity
   of the order number.

   Tokens expire (default 7 days - long enough for a customer to
   reasonably come back and re-download, short enough to bound how
   long a leaked/forwarded link (browser history, a screenshot, an
   email) stays usable) - order-success.php only shows the guest
   download links while the customer is actively viewing their own
   just-placed order, so a short-lived link is not a real
   inconvenience there, and get_or_create_invoice_number() being
   idempotent means a NEW token generated later (e.g. a fresh visit
   from a bookmarked link, if one is ever added) still reaches the
   exact same invoice.

   Both functions FAIL CLOSED if INVOICE_TOKEN_SECRET isn't
   configured - generate returns null (caller should hide the
   download links entirely, not show a link that will never verify)
   and verify returns false - same "missing required secret means
   unavailable, not silently insecure" pattern already established by
   PaymentConfigurationException for the payment gateways.
========================================== */

function generate_guest_invoice_token(string $orderNumber, int $ttlSeconds = 604800): ?array
{
    if (INVOICE_TOKEN_SECRET === '') {
        error_log('generate_guest_invoice_token(): INVOICE_TOKEN_SECRET is not configured - guest invoice links are disabled.');
        return null;
    }

    $expiresAt = time() + $ttlSeconds;
    $signature = hash_hmac('sha256', $orderNumber . '|' . $expiresAt, INVOICE_TOKEN_SECRET);

    return ['exp' => $expiresAt, 'sig' => $signature];
}

function verify_guest_invoice_token(string $orderNumber, string $expParam, string $sigParam): bool
{
    if (INVOICE_TOKEN_SECRET === '' || $orderNumber === '' || !ctype_digit($expParam) || $sigParam === '') {
        return false;
    }

    $expiresAt = (int) $expParam;

    if ($expiresAt < time()) {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $orderNumber . '|' . $expiresAt, INVOICE_TOKEN_SECRET);

    return hash_equals($expectedSignature, $sigParam);
}


/* ==========================================
   BUSINESS DETAILS (all 6 GST-invoice fields, all configurable)
========================================== */

function get_invoice_business_details(): array
{
    return [
        'name'       => get_setting('business_name', 'MoonAura Crystals'),
        'trade_name' => get_setting('business_trade_name'), // no default - see file doc comment
        'gstin'      => get_setting('business_gstin'),       // no default - see file doc comment
        'address'    => get_setting('business_address', 'South Chanduria, Simurali, Nadia, West Bengal - 741248, India'),
        'phone'      => get_setting('business_phone', '+91 92423 19596'),
        'email'      => get_setting('business_email', 'support@moonauracrystals.in'),
        'website'    => get_setting('business_website', ''),
    ];
}


/* ==========================================
   LOGO (optional - "future branding without an architecture change")
   -------------------------------------------------
   SimplePdfWriter only embeds JPEG (see its doc comment - a JPEG's
   bytes drop straight into a PDF stream, no re-encoding needed). This
   project's own image assets, despite their .png/.jpg-looking names,
   are actually all WebP files (verified project-wide during Phase
   3B: assets/images/*.png are WEBP by file signature, not PNG) -
   a format this PDF engine has no reason to decode by hand. Rather
   than hand-rolling a second image codec here, this function uses
   PHP's GD extension (imagecreatefromstring()) as a universal
   decoder: GD auto-detects PNG/JPEG/WebP/GIF/BMP by content and hands
   back a normal image resource regardless of which one it was, and
   this function then re-encodes that resource as JPEG (the one
   format SimplePdfWriter understands) - so it works for the actual
   WebP-as-.png site logo today, and for a real PNG or JPEG logo
   dropped in later, with no changes to this function or to
   SimplePdfWriter.

   Transparency: if the source has an alpha channel, it's split into
   a second, single-channel grayscale JPEG and registered as the
   color image's /SMask (soft mask) - see SimplePdfWriter::
   registerImageJpeg()'s $smaskKey parameter. This is a per-pixel PHP
   loop (imagecolorat() has no bulk/array API), so it is NOT free -
   for the actual site logo (1024x348px) this is roughly 350,000
   iterations. Fine for an on-demand single-invoice download; a much
   larger source image would make this the slowest part of invoice
   generation. Recommendation if a real logo file is added: keep it
   at roughly the size it'll actually be drawn at (a few hundred
   pixels wide is plenty for a header logo), not a multi-megapixel
   original.

   Returns null - never throws - on any failure (file missing, GD
   extension not compiled in, GD's build lacking WebP read support,
   decode failure, anything): a missing/broken logo must never break
   invoice generation, it should just mean no logo is drawn. The
    letterhead falls back to text-only exactly as before this function
    existed (see build_invoice_pdf()).
========================================== */

function invoice_project_root(): string
{
    return dirname(__DIR__);
}

function invoice_resolve_asset_path(string $relativeOrAbsolute): ?string
{
    $path = trim(str_replace('\\', '/', $relativeOrAbsolute));
    if ($path === '') {
        return null;
    }

    $candidates = [];
    $root = invoice_project_root();
    $rel = ltrim($path, '/');

    if (str_starts_with($rel, 'assets/') || str_starts_with($rel, 'includes/')) {
        $candidates[] = $root . '/' . $rel;
    } elseif ($path[0] === '/') {
        $candidates[] = $path;
        $candidates[] = $root . $path;
    } else {
        $candidates[] = $root . '/' . $rel;
    }

    $rootReal = realpath($root) ?: $root;

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            continue;
        }
        if (str_starts_with($real, rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return $real;
        }
    }

    return null;
}

function invoice_decode_image_bytes(string $raw)
{
    if ($raw === '') {
        return false;
    }

    $source = @imagecreatefromstring($raw);
    if ($source !== false) {
        return $source;
    }

    $isWebp = strlen($raw) >= 12 && strncmp($raw, 'RIFF', 4) === 0 && substr($raw, 8, 4) === 'WEBP';
    if ($isWebp && function_exists('imagecreatefromwebp')) {
        $tmp = tmpfile();
        if ($tmp !== false) {
            $meta = stream_get_meta_data($tmp);
            $tmpPath = $meta['uri'] ?? '';
            if ($tmpPath !== '' && fwrite($tmp, $raw) !== false) {
                $source = @imagecreatefromwebp($tmpPath);
                fclose($tmp);
                if ($source !== false) {
                    return $source;
                }
            } else {
                fclose($tmp);
            }
        }
    }

    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->readImageBlob($raw);
            $im->setImageFormat('png');
            $png = $im->getImagesBlob();
            $im->clear();
            $im->destroy();
            $source = @imagecreatefromstring($png);
            if ($source !== false) {
                return $source;
            }
        } catch (Throwable $e) {
            error_log('invoice_decode_image_bytes() Imagick fallback failed: ' . $e->getMessage());
        }
    }

    return false;
}

function invoice_register_branding_image(SimplePdfWriter $pdf, string $absolutePath, float $peakOpacityPercent = 100.0): ?array
{
    if (!is_readable($absolutePath) || !function_exists('imagecreatefromstring')) {
        return null;
    }

    try {
        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $source = invoice_decode_image_bytes($raw);
        if ($source === false) {
            return null;
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);
            return null;
        }

        if (function_exists('imageistruecolor') && !imageistruecolor($source) && function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($source);
        }

        imagealphablending($source, false);
        imagesavealpha($source, true);

        $flattened = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefill($flattened, 0, 0, $white);
        imagealphablending($flattened, true);
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($flattened, null, 90);
        $colorJpeg = ob_get_clean();
        imagedestroy($flattened);

        $peakOpacity = max(0.0, min(1.0, $peakOpacityPercent / 100));
        $maskRows = '';
        for ($py = 0; $py < $height; $py++) {
            $row = '';
            for ($px = 0; $px < $width; $px++) {
                $rgba    = imagecolorat($source, $px, $py);
                $alpha   = ($rgba >> 24) & 0x7F;
                $opacity = (int) round((127 - $alpha) / 127 * 255 * $peakOpacity);
                $row    .= chr($opacity);
            }
            $maskRows .= $row;
        }
        imagedestroy($source);

        $maskFlate = gzcompress($maskRows, 9);
        if ($maskFlate === false) {
            return null;
        }

        $maskKey  = $pdf->registerImageFlate($maskFlate, $width, $height, 'DeviceGray');
        $colorKey = $pdf->registerImageJpeg($colorJpeg, $width, $height, 'DeviceRGB', $maskKey);

        return ['key' => $colorKey, 'width' => $width, 'height' => $height];
    } catch (Throwable $e) {
        error_log('invoice_register_branding_image() failed for ' . $absolutePath . ': ' . $e->getMessage());
        return null;
    }
}

function register_invoice_logo(SimplePdfWriter $pdf, string $customPath = ''): ?array
{
    // See config/config.php's doc comment on INVOICE_LOGO_ENABLED -
    // disabled by default following the ERR_CONNECTION_RESET
    // diagnosis (a suspected native GD/WebP crash, which bypasses
    // this function's own try/catch below entirely). Checked before
    // touching the filesystem or GD at all.
    if (!INVOICE_LOGO_ENABLED) {
        return null;
    }

    // Invoice Designer's optional dedicated invoice logo (uploaded via
    // Admin > Settings > Invoice Designer) - empty (the default) falls
    // back to the exact same site logo this used before Phase 6.
    $defaultPath = invoice_resolve_asset_path('assets/images/icons/logos/logo-header.webp');
    $resolved = $customPath !== '' ? invoice_resolve_asset_path($customPath) : $defaultPath;

    if ($resolved === null) {
        $resolved = $defaultPath;
    }

    if ($resolved === null) {
        return null;
    }

    $registered = invoice_register_branding_image($pdf, $resolved, 100.0);
    if ($registered !== null) {
        return $registered;
    }

    if ($defaultPath !== null && $defaultPath !== $resolved) {
        return invoice_register_branding_image($pdf, $defaultPath, 100.0);
    }

    return null;
}


/* ==========================================
   WATERMARK (logo-mobile-nav.webp)
   -------------------------------------------------
   Same technique as register_invoice_logo() just above (WebP decode
   via GD -> flatten to a color JPEG + a separate grayscale JPEG used
   as /SMask) - the one difference is $peakOpacity below, which caps
   the mask at a uniform 5-8% regardless of the source image's own
   alpha, so the watermark reads as "very faint" everywhere it's
   visible rather than only faint at its already-transparent edges.

   Registered once per build_invoice_pdf() call (not once per page)
   and drawn from inside the page-header callback (see
   $drawLetterhead in build_invoice_pdf()) as the very FIRST thing on
   every page, so it sits behind the letterhead and all page content
   automatically - no per-page bookkeeping needed here.

   Same "never throws, returns null on any failure" contract as
   register_invoice_logo() - a missing/broken watermark source must
   never break invoice generation, it should just mean no watermark
   is drawn. Gated behind the same INVOICE_LOGO_ENABLED flag as the
   header logo (see config/config.php) since both go through the
   identical GD image-embedding pipeline.
========================================== */

function register_invoice_watermark(SimplePdfWriter $pdf, float $peakOpacityPercent = 7.0, string $customPath = ''): ?array
{
    if (!INVOICE_LOGO_ENABLED) {
        return null;
    }

    // Invoice Designer's optional dedicated watermark image - empty
    // (the default) falls back to the exact same site mark this used
    // before Phase 6.
    $defaultPath = invoice_resolve_asset_path('assets/images/icons/logos/logo-mobile-nav.webp');
    $resolved = $customPath !== '' ? invoice_resolve_asset_path($customPath) : $defaultPath;

    if ($resolved === null) {
        $resolved = $defaultPath;
    }

    if ($resolved === null) {
        return null;
    }

    $registered = invoice_register_branding_image($pdf, $resolved, $peakOpacityPercent);
    if ($registered !== null) {
        return $registered;
    }

    if ($defaultPath !== null && $defaultPath !== $resolved) {
        return invoice_register_branding_image($pdf, $defaultPath, $peakOpacityPercent);
    }

    return null;
}


/* ==========================================
   PDF-SAFE TEXT / CURRENCY HELPERS
   -------------------------------------------------
   SimplePdfWriter uses the standard 14 PDF fonts only - their
   built-in encoding is WinAnsi (~= Windows-1252), not UTF-8. Database
   content (customer names, addresses, product names) comes back from
   MySQL as UTF-8, so it has to be converted before it reaches the
   PDF, or multi-byte characters would render as garbled glyph
   sequences instead of failing loudly.
========================================== */

function pdf_safe_text(?string $value): string
{
    $value = $value ?? '';

    if (function_exists('mb_convert_encoding')) {
        // '?' substitution for anything outside Windows-1252 (e.g. an
        // emoji in a customer name) - better than a corrupted PDF
        // string, and Windows-1252 covers everyday English/Indian-
        // English text (accented Latin letters included) correctly.
        $converted = @mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
        if ($converted !== false) {
            return $converted;
        }
    }

    // mbstring not available (very unlikely on a modern PHP install):
    // strip anything outside printable ASCII instead of risking
    // multi-byte corruption in the raw PDF string.
    return (string) preg_replace('/[^\x20-\x7E]/', '', $value);
}

// PDF-specific currency formatting - deliberately "Rs." rather than
// the site's format_price()'s Rupee-sign character, because that
// glyph isn't in the standard 14 fonts' built-in encoding (see
// SimplePdfWriter's class doc comment) and would render as a missing-
// glyph box instead. Same 2-decimals-only-when-needed rule as
// format_price() for visual consistency with the rest of the site.
function format_price_for_pdf(float $amount): string
{
    $decimals = (fmod($amount, 1) === 0.0) ? 0 : 2;
    return 'Rs. ' . number_format($amount, $decimals);
}

// Reads the existing payment_transactions table (never a new column,
// never invented data) for the Invoice Designer's optional Transaction
// ID / Payment Date fields. Prefers the most recent 'paid' transaction;
// falls back to the most recent transaction of any status if the order
// was never marked paid (e.g. a pending/failed online-gateway attempt);
// returns nulls (never a placeholder string) for COD or any order with
// no transaction row at all.
function get_invoice_payment_transaction_info(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT gateway_payment_id, updated_at
         FROM payment_transactions
         WHERE order_id = ?
         ORDER BY (status = 'paid') DESC, updated_at DESC
         LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();

    return [
        'transaction_id' => $row['gateway_payment_id'] ?? null,
        'payment_date'   => $row['updated_at'] ?? null,
    ];
}

// Draws $str inside the zone [$zoneX0, $zoneX0 + $zoneWidth] per the
// Invoice Designer's left|center|right alignment setting - one shared
// implementation so every alignable section (header, order info,
// addresses, tax summary, footer) behaves identically. 'left' always
// draws at exactly $zoneX0 (byte-identical to every pre-Phase-6
// left-aligned call site).
function draw_aligned_text(SimplePdfWriter $pdf, string $alignment, float $zoneX0, float $zoneWidth, float $yFromTop, string $str, string $font, float $size, array $rgb): void
{
    switch ($alignment) {
        case 'center':
            $pdf->textCentered($zoneX0 + ($zoneWidth / 2), $yFromTop, $str, $font, $size, $rgb);
            break;
        case 'right':
            $pdf->textRightAligned($zoneX0 + $zoneWidth, $yFromTop, $str, $font, $size, $rgb);
            break;
        default:
            $pdf->text($zoneX0, $yFromTop, $str, $font, $size, $rgb);
    }
}

// Alignment-aware equivalent of SimplePdfWriter::wrappedText() - same
// wrapping + line-height (size + 4) logic, but each resulting line is
// drawn via draw_aligned_text() instead of always-left text(), so a
// multi-line address block can be centered/right-aligned too. 'left'
// (the default) produces byte-identical output to wrappedText().
function draw_aligned_wrapped_text(SimplePdfWriter $pdf, string $alignment, float $zoneX0, float $zoneWidth, float $yFromTop, string $text, string $font, float $size, array $rgb): float
{
    $lines      = $pdf->wrapText($text, $zoneWidth, $font, $size);
    $lineHeight = $size + 4;

    foreach ($lines as $i => $line) {
        draw_aligned_text($pdf, $alignment, $zoneX0, $zoneWidth, $yFromTop + ($i * $lineHeight), $line, $font, $size, $rgb);
    }

    return $yFromTop + (count($lines) * $lineHeight);
}

// Download = PDF attachment only (never a print dialog).
// Print (desktop) = HTML viewer that loads the generated PDF in an
// iframe and opens the browser print dialog. Raw application/pdf
// cannot run window.print(). There is no wrapper-page print fallback:
// printing the empty HTML shell was the blank-preview failure mode.
// Print (mobile / iOS / Android, including iPadOS desktop-UA Safari) =
// inline PDF. Mobile browsers cannot print a PDF shown in an iframe
// from a blob: URL (blank preview; UUID-like blob name). The native
// PDF viewer opens the invoice instead so the user can Print from
// the system share/print sheet.
function invoice_print_prefers_native_viewer(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if ($ua === '') {
        return false;
    }

    if (preg_match('/iPhone|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
        return true;
    }

    // iPadOS 13+ often sends a Macintosh desktop UA. Treat touch Mac
    // Safari as mobile so Print still uses the native PDF viewer.
    if (stripos($ua, 'Macintosh') !== false && stripos($ua, 'Mobile') !== false) {
        return true;
    }

    if (stripos($ua, 'iPad') !== false) {
        return true;
    }

    return false;
}

function invoice_send_pdf_bytes(string $pdfBytes, string $safeFilename, string $disposition): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
    header('Content-Length: ' . (string) strlen($pdfBytes));
    header('X-Content-Type-Options: nosniff');
    if ($disposition === 'inline') {
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    } else {
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
    echo $pdfBytes;
    exit;
}

function send_invoice_pdf_response(string $pdfBytes, string $filename, string $mode): void
{
    $safeFilename = str_replace(['"', "\r", "\n"], '', $filename);

    if ($mode !== 'print') {
        invoice_send_pdf_bytes($pdfBytes, $safeFilename, 'attachment');
    }

    if (invoice_print_prefers_native_viewer()) {
        invoice_send_pdf_bytes($pdfBytes, $safeFilename, 'inline');
    }

    $title = htmlspecialchars($safeFilename, ENT_QUOTES, 'UTF-8');
    $pdfBase64 = base64_encode($pdfBytes);

    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . $title . '</title>';
    echo '<style>html,body{margin:0;height:100%;background:#525659}iframe{border:0;width:100%;height:100%;background:#fff}@media print{html,body{margin:0;background:#fff}iframe{position:fixed;inset:0;width:100%;height:100%;border:0}}</style>';
    echo '</head><body>';
    echo '<iframe id="invoice-print-frame" title="' . $title . '"></iframe>';
    echo '<script>(function(){';
    echo 'var printed=false;';
    echo 'function triggerPrint(){if(printed)return;printed=true;try{var w=document.getElementById("invoice-print-frame").contentWindow;w.focus();w.print();}catch(e){}}';
    echo 'var b64=' . json_encode($pdfBase64, JSON_THROW_ON_ERROR) . ';';
    echo 'var bin=atob(b64);var bytes=new Uint8Array(bin.length);';
    echo 'for(var i=0;i<bin.length;i++)bytes[i]=bin.charCodeAt(i);';
    echo 'var blob=new Blob([bytes],{type:"application/pdf"});';
    echo 'var url=URL.createObjectURL(blob);';
    echo 'var frame=document.getElementById("invoice-print-frame");';
    echo 'frame.onload=function(){setTimeout(triggerPrint,300);};';
    echo 'frame.src=url;';
    echo '})();</script>';
    echo '</body></html>';
    exit;
}


/* ==========================================
   BUILD THE INVOICE PDF
   -------------------------------------------------
   $order        - a full row from `orders`, WITH invoice_number/
                    invoice_generated_at already populated (call
                    get_or_create_invoice_number() first)
   $orderItems   - all rows from `order_items` for this order
   $orderAddress - the row from `order_addresses` for this order, or
                    null (should not normally happen - every order
                    created by create_order() writes one - but handled
                    defensively rather than assumed)

   Returns raw PDF bytes.
========================================== */

function build_invoice_pdf(array $order, array $orderItems, ?array $orderAddress): string
{
    $pdf = new SimplePdfWriter();

    $purple    = [0.357, 0.180, 0.569]; // brand --primary    #5B2E91
    $gold      = [0.831, 0.686, 0.216]; // brand --gold       #D4AF37
    $text      = [0.133, 0.133, 0.133]; // brand --text       #222222
    $textLight = [0.4, 0.4, 0.4];       // brand --text-light #666666
    $white     = [1.0, 1.0, 1.0];       // header row text on the purple fill below

    $x0      = $pdf->getMarginLeft();
    $rightX  = $pdf->getRightX();
    $usableW = $pdf->getUsableWidth();

    $business = get_invoice_business_details();
    $designer = get_invoice_designer_settings();
    $layout   = sanitize_invoice_layout($designer['layout'] ?? get_invoice_layout_defaults());
    $logo     = register_invoice_logo($pdf, (string) $designer['logo']['custom_path']);

    // Modular layout: section offsets move complete groups; line offsets
    // fine-tune individual elements. Both the admin canvas and this PDF
    // renderer read the same persisted JSON model.
    $layoutPos = static fn (string $section, string $line, float $baseX, float $baseY): array =>
        get_invoice_layout_position($layout, $section, $line, $baseX, $baseY);
    $sectionOffset = static fn (string $section): array => get_invoice_section_offset($layout, $section);

    /* ---------- Letterhead - registered as the repeating page-header,
       so it's automatically redrawn on every continuation page (see
       SimplePdfWriter::setPageHeaderCallback()'s doc comment). Drawn
       once manually for page 1 below, since the callback only fires
       for page 2 onwards. ---------- */

    $watermark = register_invoice_watermark($pdf, (float) $designer['watermark']['opacity'], (string) $designer['watermark']['custom_path']);

    $drawLetterhead = function (SimplePdfWriter $pdf) use ($business, $logo, $watermark, $order, $x0, $rightX, $usableW, $purple, $gold, $text, $textLight, $designer, $layoutPos) {
        // Watermark drawn first so every later draw call this page
        // (the letterhead below, plus the table/totals/footer drawn
        // after this closure returns) paints on top of it - PDF
        // content streams composite in draw order, so "first" is
        // "behind everything else on this page".
        if ($watermark !== null) {
            $wmSize = (float) $designer['watermark']['scale']; // square, points
            $wmRotation = (float) $designer['watermark']['rotation'];

            // Position options place the watermark's own box fully
            // within the page margins in every corner, with the same
            // clearance the original center placement always had.
            switch ($designer['watermark']['position']) {
                case 'top-left':
                    $wmX = $x0;
                    $wmY = 60;
                    break;
                case 'top-right':
                    $wmX = $rightX - $wmSize;
                    $wmY = 60;
                    break;
                case 'bottom-left':
                    $wmX = $x0;
                    $wmY = SimplePdfWriter::PAGE_HEIGHT - $wmSize - 60;
                    break;
                case 'bottom-right':
                    $wmX = $rightX - $wmSize;
                    $wmY = SimplePdfWriter::PAGE_HEIGHT - $wmSize - 60;
                    break;
                default: // center - matches the pre-Phase-6 hardcoded placement
                    $wmX = (SimplePdfWriter::PAGE_WIDTH - $wmSize) / 2;
                    $wmY = (SimplePdfWriter::PAGE_HEIGHT - $wmSize) / 2;
            }

            if ($wmRotation != 0.0) {
                $pdf->drawImageRotated($watermark['key'], $wmX, $wmY, $wmSize, $wmSize, $wmRotation);
            } else {
                $pdf->drawImage($watermark['key'], $wmX, $wmY, $wmSize, $wmSize);
            }
        }

        // The logo is positioned by the modular layout model. The old
        // alignment selector is retained for backward compatibility, but
        // actual X/Y placement now comes from the saved canvas coordinates.
        $leftZoneWidth = $usableW * 0.55;
        $logoDrawWidth  = (float) $designer['logo']['width'];
        $logoDrawHeight = ($logo !== null) ? $logoDrawWidth * ($logo['height'] / $logo['width']) : 0;
        $logoLine = $layoutPos('header', 'logo', $x0, 40);
        if ($logo !== null && $logoLine['visible']) {
            $pdf->drawImage($logo['key'], $logoLine['x'], $logoLine['y'], $logoDrawWidth, $logoDrawHeight);
        }

        // Branding settings are intentionally not auto-rendered beside the
        // logo. The logo remains the sole left-side brand mark.

        // Header title - lives in the right half of the letterhead
        // (same reserved zone the invoice number/date sit in further
        // down), so left/center/right here never overlaps the
        // logo/name block above regardless of alignment chosen.
        $rightZoneX0 = $x0 + $leftZoneWidth + 10;
        $rightZoneWidth = $usableW - $leftZoneWidth - 10;
        $titleLine = $layoutPos('header', 'title', $rightZoneX0, 60);
        if ($titleLine['visible']) {
            draw_aligned_text($pdf, $designer['header']['alignment'], $titleLine['x'], $rightZoneWidth, $titleLine['y'], pdf_safe_text($designer['header']['title']), 'F2', (float) $designer['header']['font_size'], $text);
        }

        $divider = $layoutPos('header', 'divider', $x0, 108);
        if ($divider['visible']) $pdf->line($divider['x'], $divider['y'], $divider['x'] + $usableW, $divider['y'], 1.2, $gold);
        $pdf->setY(max(108, $divider['y']));
    };

    $drawLetterhead($pdf);
    $pdf->setPageHeaderCallback($drawLetterhead);

    /* ---------- Seller details (left) + invoice meta (right) - page 1 only ---------- */

    [$sellerDx, $sellerDy] = $sectionOffset('seller');
    $orderInfoZoneX0    = $x0 + ($usableW * 0.55) + 10 + $sellerDx;
    $orderInfoZoneWidth = $usableW - ($usableW * 0.55) - 10;

    // Keep the PDF seller/order-meta geometry in lockstep with the
    // visual builder. The seller section's right-hand meta column is
    // 230pt wide and starts 285pt into the section; line.x/line.y are
    // persisted offsets from those exact boxes.
    $sellerBaseY = 120 + $sellerDy;
    $orderMetaDefaultY = [
        'invoice_date'   => 0,
        'invoice_number' => 16,
        'order_date'     => 32,
        'order_number'   => 48,
    ];

    $y = $sellerBaseY;
    $addrLine = $layoutPos('seller', 'business_address', $x0, $y);
    $addrEndY = $addrLine['y'];
    if ($addrLine['visible']) {
        $addrEndY = $pdf->wrappedText($addrLine['x'], $addrLine['y'], $usableW * 0.55, pdf_safe_text($business['address']), 'F1', 8.5, $textLight);
    }

    $contactLine = $layoutPos('seller', 'business_contact', $x0, $addrEndY);
    if ($contactLine['visible']) {
        $pdf->text($contactLine['x'], $contactLine['y'], 'Email: ' . pdf_safe_text($business['email']) . '  |  Phone: ' . pdf_safe_text($business['phone']), 'F1', 8.5, $textLight);
    }

    $orderMetaRows = [
        'invoice_date'   => date('d M Y', strtotime($order['invoice_generated_at'])),
        'invoice_number' => (string) $order['invoice_number'],
        'order_date'     => date('d M Y', strtotime($order['created_at'])),
        'order_number'   => (string) $order['order_number'],
    ];

    $orderMetaLabels = [
        'invoice_date'   => 'Invoice Date:',
        'invoice_number' => 'Invoice No:',
        'order_date'     => 'Order Date:',
        'order_number'   => 'Order No:',
    ];

    foreach ($orderMetaRows as $lineId => $value) {
        if (!$designer['order_info'][$lineId]['visible']) {
            continue;
        }

        $lineData = $layout['sections']['seller']['lines'][$lineId] ?? [];
        $lineX = $orderInfoZoneX0 + (float) ($lineData['x'] ?? 0);
        $lineY = $sellerBaseY + ($orderMetaDefaultY[$lineId] ?? 0) + (float) ($lineData['y'] ?? 0);

        if (!(bool) ($lineData['visible'] ?? true)) {
            continue;
        }

        // Render Seller / Order Meta as a true two-column row: every
        // label starts at the same X coordinate and every value shares
        // the same right edge. Previously the whole string was right-
        // aligned, which made the left edge drift from row to row because
        // the labels have different lengths. The builder shows each line
        // in one fixed-width box, so this pair layout is the stable PDF
        // equivalent while preserving the saved line X/Y offsets.
        // Keep a fixed label/value grid for all four rows. The label and the
        // actual value must not both contain the label text, and the value
        // itself should start at one shared X coordinate in every row.
        // The saved line X/Y offsets still move the complete row, preserving
        // the modular designer's individual drag position.
        $labelWidth = min(72.0, max(62.0, $orderInfoZoneWidth * 0.32));
        $labelX = $lineX;
        $valueX = $lineX + $labelWidth;
        $valueWidth = max(40.0, $orderInfoZoneWidth - $labelWidth);

        $pdf->text($labelX, $lineY, $orderMetaLabels[$lineId], 'F1', 9, $text);
        // Values intentionally use a shared left anchor. This prevents the
        // visible data (date/number) from drifting row-to-row when label
        // lengths differ or when a per-line right alignment was previously
        // applied to the combined label + value string.
        $pdf->text($valueX, $lineY, $value, 'F1', 9, $text);
    }

    $sellerInfoY = $addrEndY + 12;
    if (!empty($business['website'])) {
        $line = $layoutPos('seller', 'business_website', $x0, $sellerInfoY);
        if ($line['visible']) $sellerInfoY = $pdf->wrappedText($line['x'], $line['y'], $usableW * 0.55, 'Website: ' . pdf_safe_text($business['website']), 'F1', 8.5, $textLight);
    }
    $gstLine = $layoutPos('seller', 'business_gstin', $x0, $sellerInfoY);
    if ($gstLine['visible']) $pdf->text($gstLine['x'], $gstLine['y'], 'GSTIN: ' . (!empty($business['gstin']) ? pdf_safe_text($business['gstin']) : 'Not configured'), 'F1', 8.5, $textLight);

    $y = max($sellerInfoY + 12, $y + 12);

    /* ---------- Billed To / Ship To ---------- */

    $billingVisible  = $designer['addresses']['billing']['visible'];
    $shippingVisible = $designer['addresses']['shipping']['visible'];

    if ($billingVisible || $shippingVisible) {
        [$addressDx, $addressDy] = $sectionOffset('addresses');
        $colWidth = ($usableW / 2) - 10;
        $shipX = $x0 + ($usableW / 2);
        $addressBaseY = $y + 20;

        if ($billingVisible) {
            $line = $layoutPos('addresses', 'billing_heading', $x0, $addressBaseY);
            if ($line['visible']) draw_aligned_text($pdf, $designer['addresses']['billing']['alignment'], $line['x'], $colWidth, $line['y'], 'Billed To', 'F2', 11, $purple);
        }
        if ($shippingVisible) {
            $line = $layoutPos('addresses', 'shipping_heading', $shipX, $addressBaseY);
            if ($line['visible']) draw_aligned_text($pdf, $designer['addresses']['shipping']['alignment'], $line['x'], $colWidth, $line['y'], 'Ship To', 'F2', 11, $purple);
        }

        $billLines = [
            ['billing_name', pdf_safe_text($order['customer_name'])],
            ['billing_email', pdf_safe_text($order['customer_email'])],
            ['billing_phone', pdf_safe_text($order['customer_phone'])],
        ];
        if ($orderAddress) {
            $shipAddressLine = $orderAddress['address_line1'];
            if (!empty($orderAddress['address_line2'])) $shipAddressLine .= ', ' . $orderAddress['address_line2'];
            $shipCityLine = trim(($orderAddress['landmark'] ? $orderAddress['landmark'] . ', ' : '') . $orderAddress['city'] . ', ' . $orderAddress['state'] . ' - ' . $orderAddress['postal_code']);
            $shipLines = [
                ['shipping_name', pdf_safe_text($orderAddress['full_name'])],
                ['shipping_address', pdf_safe_text($shipAddressLine)],
                ['shipping_city', pdf_safe_text($shipCityLine)],
            ];
        } else {
            $shipLines = [['shipping_name', 'No shipping address on file']];
        }

        // Each address line gets its own stable baseline. Previously every
        // billing/shipping body line was drawn at the same Y coordinate,
        // so the modular line offsets all started from one shared baseline
        // and the PDF text overlapped. Keep the drag/drop offsets additive,
        // but give each logical line its proper default row.
        $billingBaseY = [
            'billing_name'  => $addressBaseY + 17,
            'billing_email' => $addressBaseY + 31,
            'billing_phone' => $addressBaseY + 45,
        ];
        $shippingBaseY = [
            'shipping_name'    => $addressBaseY + 17,
            'shipping_address' => $addressBaseY + 31,
            'shipping_city'    => $addressBaseY + 47,
        ];

        if ($billingVisible) {
            foreach ($billLines as [$lineId, $textLine]) {
                $baseLineY = $billingBaseY[$lineId] ?? ($addressBaseY + 17);
                $line = $layoutPos('addresses', $lineId, $x0, $baseLineY);
                if ($line['visible']) draw_aligned_wrapped_text($pdf, $designer['addresses']['billing']['alignment'], $line['x'], $colWidth, $line['y'], $textLine, 'F1', 9.5, $text);
            }
        }
        // Ship To: draw each field at its designer baseline when it
        // fits. If a previous field wrapped, clamp this field below
        // that block so extra space appears only when wrapping
        // requires it - short addresses stay tight, long ones never
        // overlap. layoutPos() always uses the default baseline so
        // designer offsets are not applied twice.
        $shippingOccupiedY = null;
        $shippingBottomY = $addressBaseY;
        if ($shippingVisible) {
            foreach ($shipLines as [$lineId, $textLine]) {
                $defaultY = $shippingBaseY[$lineId] ?? ($addressBaseY + 17);
                $line = $layoutPos('addresses', $lineId, $shipX, $defaultY);
                if ($line['visible']) {
                    $drawY = ($shippingOccupiedY === null) ? $line['y'] : max($line['y'], $shippingOccupiedY);
                    $endY = draw_aligned_wrapped_text($pdf, $designer['addresses']['shipping']['alignment'], $line['x'], $colWidth, $drawY, $textLine, 'F1', 9.5, $text);
                    $shippingOccupiedY = $endY;
                    $shippingBottomY = max($shippingBottomY, $endY);
                }
            }
        }
        $y = max($addressBaseY + 74, $shippingBottomY + 8);
    }

    /* ---------- Items table ---------- */

    [$tableDx, $tableDy] = $sectionOffset('product_table');
    $y += 20;
    $tableX0 = $x0 + $tableDx;
    $tableRightX = $rightX + $tableDx;
    $pdf->setY($y);

    // Phase 5G six-column table - '# / Item / Qty / Unit Price /
    // GST Rate / Line Total'. The GST Rate column comes straight from
    // the snapshotted order_items.gst_rate (the same per-line rate the
    // Phase 5F tax split was computed from), so a mixed-rate order
    // shows each line's own rate next to it.
    //
    // Phase 6: column boundaries, font size, and item-name alignment
    // are now Invoice Designer settings - resolve_invoice_column_
    // boundaries() clamps whatever's stored into a safe, strictly-
    // increasing sequence (see includes/invoice-designer-functions.php)
    // so a malformed config can never produce overlapping columns.
    // Defaults (52/68/84 at 9pt) match the original hardcoded layout
    // exactly.
    $boundaries = resolve_invoice_column_boundaries($designer['product_table']);
    $colQty   = $tableX0 + ($usableW * ($boundaries['quantity'] / 100));
    $colPrice = $tableX0 + ($usableW * ($boundaries['unit_price'] / 100));
    $colGst   = $tableX0 + ($usableW * ($boundaries['gst_rate'] / 100));
    $itemColWidth = $colQty - ($tableX0 + 22) - 10; // wrap width for product names

    $tableFontSize   = (float) $designer['product_table']['font_size'];
    $itemNameAlign   = $designer['product_table']['item_name_alignment'];
    $tableRowLineHeight = $tableFontSize + 4; // matches wrappedText()'s own default (size + 4); 13 at the default 9pt, same as before

    $drawTableHeader = function () use ($pdf, $tableX0, $usableW, $tableRightX, $colQty, $colPrice, $colGst, $layout, $purple, $white, $tableFontSize, $itemNameAlign, $itemColWidth, $x0) {
        $y = $pdf->getY();
        $headerLine = get_invoice_layout_position($layout, 'product_table', 'table_header', $x0, $y);
        if (!$headerLine['visible']) { return; }
        $y = $headerLine['y'];
        $tableLeft = $headerLine['x'];
        // Premium MoonAura purple header (Phase 3C UI refinement) -
        // was a light lavender fill with dark text; same row
        // height/position/column layout as before, just the header
        // row's own colors changed. White (#FFFFFF) on brand purple
        // (#5B2E91) is a very high contrast pairing (WCAG-AA-and-
        // beyond territory) so it stays readable both on a lit screen
        // and on a printed page, including a plain black-and-white
        // print (the purple still renders as a clearly darker band
        // with light text on it). A 4pt corner radius softens the
        // bar without changing its footprint - data rows below are
        // untouched and keep their plain white background.
        $pdf->filledRoundedRect($tableLeft, $y, $usableW, 20, 4, $purple);
        $pdf->text($tableLeft + 5, $y + 14, '#', 'F2', $tableFontSize, $white);
        draw_aligned_text($pdf, $itemNameAlign, $tableX0 + 22, $itemColWidth, $y + 14, 'Item', 'F2', $tableFontSize, $white);
        $pdf->textRightAligned($colQty, $y + 14, 'Qty', 'F2', $tableFontSize, $white);
        $pdf->textRightAligned($colPrice, $y + 14, 'Unit Price', 'F2', $tableFontSize, $white);
        $pdf->textRightAligned($colGst, $y + 14, 'GST Rate', 'F2', $tableFontSize, $white);
        $pdf->textRightAligned($tableRightX, $y + 14, 'Line Total', 'F2', $tableFontSize, $white);
        $pdf->advanceY(20);
    };

    $drawTableHeader();

    foreach ($orderItems as $index => $item) {
        $productName = pdf_safe_text($item['product_name']);
        $nameLines   = $pdf->wrapText($productName, $itemColWidth, 'F1', $tableFontSize);
        $rowHeight   = max(20, count($nameLines) * $tableRowLineHeight + 7);

        // ensureSpace() resets the cursor to the top margin (and, via
        // the page-header callback registered above, redraws the
        // letterhead) if it starts a new page - redraw the table's
        // own column header too in that case, so a table spanning
        // multiple pages never shows an orphaned row with no header
        // above it.
        if ($pdf->ensureSpace($rowHeight)) {
            $drawTableHeader();
        }

        $rowTop = $pdf->getY();
        $textY  = $rowTop + 14;

        // Per-line GST rate from the snapshot: "18%", "5.5%", "0%" -
        // trailing zeros trimmed so a whole rate doesn't render as
        // "18.00%".
        $gstRateLabel = rtrim(rtrim(number_format((float) $item['gst_rate'], 2), '0'), '.') . '%';

        $pdf->text($x0 + 5, $textY, (string) ($index + 1), 'F1', $tableFontSize, $text);
        foreach ($nameLines as $li => $line) {
            draw_aligned_text($pdf, $itemNameAlign, $tableX0 + 22, $itemColWidth, $textY + ($li * $tableRowLineHeight), $line, 'F1', $tableFontSize, $text);
        }
        $pdf->textRightAligned($colQty, $textY, (string) $item['quantity'], 'F1', $tableFontSize, $text);
        $pdf->textRightAligned($colPrice, $textY, format_price_for_pdf((float) $item['unit_price']), 'F1', $tableFontSize, $text);
        $pdf->textRightAligned($colGst, $textY, $gstRateLabel, 'F1', $tableFontSize, $text);
        $pdf->textRightAligned($tableRightX, $textY, format_price_for_pdf((float) $item['line_total']), 'F1', $tableFontSize, $text);

        $pdf->advanceY($rowHeight);
    }

    $tableDivider = get_invoice_layout_position($layout, 'product_table', 'table_divider', $x0, $pdf->getY() + 4 - $tableDy);
    if ($tableDivider['visible']) $pdf->line($tableDivider['x'], $tableDivider['y'], $tableDivider['x'] + $usableW, $tableDivider['y'], 0.5, [0.8, 0.8, 0.8]);
    $pdf->advanceY(20);

    /* ---------- Totals ---------- */

    [$totalsDx, $totalsDy] = $sectionOffset('totals');
    $pdf->ensureSpace(200);
    $pdf->setY($pdf->getY() + $totalsDy);
    $totalsX0 = $x0 + $totalsDx;
    $totalsRightX = $rightX + $totalsDx;
    $totalsLabelX   = $totalsX0 + ($usableW * 0.66);
    $discountAmount = (float) $order['discount'];

    $rows = [
        ['subtotal', 'Subtotal', format_price_for_pdf((float) $order['subtotal'])],
        ['discount', 'Discount', $discountAmount > 0 ? ('- ' . format_price_for_pdf($discountAmount)) : format_price_for_pdf(0)],
        ['shipping', 'Shipping', format_price_for_pdf((float) $order['shipping_charge'])],
    ];

    // Phase 5G GST breakdown - from the stored Phase 5F snapshot, never
    // recalculated. Taxable Value plus one row per non-zero component;
    // the whole block is skipped for a pre-5F historical order (which
    // has taxable_value NULL / GST 0) so those invoices keep showing
    // exactly what they always did. A rate suffix ("CGST @9%") is added
    // only when the whole order is a single GST rate (orders.gst_rate >
    // 0) - a mixed-rate order has gst_rate 0 and shows plain amounts,
    // since its per-line rates are already displayed next to each item.
    //
    // Phase 6: an optional combined "GST (Included)" row can also be
    // shown (off by default - the itemized breakdown above already
    // covers it, this would just duplicate the same total unless an
    // admin specifically wants the single-line style used on the
    // website's cart/checkout/order pages instead of/alongside it).
    $orderHasTax = ((float) $order['gst_amount']) > 0 || ((float) $order['taxable_value']) > 0;
    if ($orderHasTax) {
        $rows[] = ['taxable_value', 'Taxable Value', format_price_for_pdf((float) $order['taxable_value'])];

        $rate = (float) $order['gst_rate'];
        $rateSuffix = function (float $ratePart) use ($rate): string {
            if ($rate <= 0) {
                return '';
            }
            return ' @' . rtrim(rtrim(number_format($ratePart, 2), '0'), '.') . '%';
        };

        if ((float) $order['cgst_amount'] > 0) {
            $rows[] = ['cgst', 'CGST' . $rateSuffix($rate / 2), format_price_for_pdf((float) $order['cgst_amount'])];
        }
        if ((float) $order['sgst_amount'] > 0) {
            $rows[] = ['sgst', 'SGST' . $rateSuffix($rate / 2), format_price_for_pdf((float) $order['sgst_amount'])];
        }
        if ((float) $order['igst_amount'] > 0) {
            $rows[] = ['igst', 'IGST' . $rateSuffix($rate), format_price_for_pdf((float) $order['igst_amount'])];
        }
        $rows[] = ['gst_included', 'GST (Included)', format_price_for_pdf((float) $order['gst_amount'])];
    }

    foreach ($rows as [$rowKey, $label, $valueStr]) {
        // Keep totals as a stable two-column block: labels read naturally
        // from the left while all monetary values terminate on the same
        // right edge. The designer's visibility/alignment setting still
        // controls whether the row is shown and can opt into a centered
        // presentation, but left/right now use the same visual anchors.
        $rowSettings = $designer['tax_summary'][$rowKey] ?? ['visible' => true, 'alignment' => 'right'];
        if (!$rowSettings['visible']) {
            continue;
        }

        $rowY = $pdf->getY();
        $totalLine = get_invoice_layout_position($layout, 'totals', $rowKey, $x0, $rowY - $totalsDy);
        if (!$totalLine['visible']) { continue; }
        $rowY = $totalLine['y'];
        $lineDx = $totalLine['x'] - $totalsX0;
        $valueRightX = $totalsRightX + $lineDx;
        switch ($rowSettings['alignment']) {
            case 'center':
                $pdf->textCentered($x0 + ($usableW / 2), $rowY + 10, $label . ': ' . $valueStr, 'F1', 9.5, $text);
                break;
            case 'left':
                $pdf->text($totalsLabelX + $lineDx, $rowY + 10, $label, 'F1', 9.5, $text);
                $pdf->textRightAligned($valueRightX, $rowY + 10, $valueStr, 'F1', 9.5, $text);
                break;
            default:
                // Standard invoice presentation: labels are left-aligned
                // inside the totals block; values stay right-aligned.
                $pdf->text($totalsLabelX - 4 + $lineDx, $rowY + 10, $label, 'F1', 9.5, $text);
                $pdf->textRightAligned($valueRightX, $rowY + 10, $valueStr, 'F1', 9.5, $text);
        }
        $pdf->advanceY(16);
    }

    $grandTotalVisible = $designer['tax_summary']['grand_total']['visible'];

    if ($grandTotalVisible) {
        $pdf->line($totalsLabelX - 90, $pdf->getY(), $totalsRightX, $pdf->getY(), 0.8, $text);
        $pdf->advanceY(16);

        $rowY = $pdf->getY();
        $grandLine = get_invoice_layout_position($layout, 'totals', 'grand_total', $x0, $rowY - $totalsDy);
        if (!$grandLine['visible']) { $grandTotalVisible = false; }
        $rowY = $grandLine['y'];
        $grandLineDx = $grandLine['x'] - $totalsX0;
        $grandRightX = $totalsRightX + $grandLineDx;
        $grandTotalStr = format_price_for_pdf((float) $order['grand_total']);
        switch ($designer['tax_summary']['grand_total']['alignment']) {
            case 'left':
                $pdf->text($x0 + $grandLineDx, $rowY + 12, 'Grand Total', 'F2', 12, $purple);
                $pdf->textRightAligned($grandRightX, $rowY + 12, $grandTotalStr, 'F2', 12, $purple);
                break;
            case 'center':
                $pdf->textCentered($x0 + ($usableW / 2) + $grandLineDx, $rowY + 12, 'Grand Total: ' . $grandTotalStr, 'F2', 12, $purple);
                break;
            default:
                $pdf->text($totalsLabelX - 4 + $grandLineDx, $rowY + 12, 'Grand Total', 'F2', 12, $purple);
                $pdf->textRightAligned($grandRightX, $rowY + 12, $grandTotalStr, 'F2', 12, $purple);
        }
        $pdf->advanceY(30);
    }

    /* ---------- Payment + footer ---------- */

    $paymentInfo = $designer['payment_info'];
    $paymentInfoLines = [];

    if ($paymentInfo['payment_method']['visible']) {
        $paymentInfoLines['payment_method'] = 'Payment Method: ' . strtoupper(pdf_safe_text($order['payment_method'] ?? 'N/A'));
    }
    if ($paymentInfo['payment_status']['visible']) {
        $paymentInfoLines['payment_status'] = 'Payment Status: ' . ucfirst(pdf_safe_text($order['payment_status']));
    }
    if (!empty($paymentInfo['invoice_status']['visible'])) {
        $paymentInfoLines['invoice_status'] = 'Invoice Status: ' . ucfirst(pdf_safe_text($order['order_status'] ?? 'issued'));
    }
    if ($paymentInfo['transaction_id']['visible'] || $paymentInfo['payment_date']['visible']) {
        $transactionInfo = get_invoice_payment_transaction_info((int) $order['id']);

        if ($paymentInfo['transaction_id']['visible']) {
            $paymentInfoLines['transaction_id'] = 'Transaction ID: ' . pdf_safe_text($transactionInfo['transaction_id'] ?? 'N/A');
        }
        if ($paymentInfo['payment_date']['visible']) {
            $paymentInfoLines['payment_date'] = 'Payment Date: ' . ($transactionInfo['payment_date'] ? date('d M Y', strtotime($transactionInfo['payment_date'])) : 'N/A');
        }
    }

    if (!empty($paymentInfoLines)) {
        [$paymentDx, $paymentDy] = $sectionOffset('payment');
        $pdf->ensureSpace((count($paymentInfoLines) * 13) + 36);
        $sectionY = $pdf->getY() + $paymentDy;
        $paymentX0 = $x0 + $paymentDx;

        // Give the payment block a clear section heading and keep the
        // detail lines aligned to one consistent left edge. This makes
        // the section read like the rest of the invoice instead of
        // appearing as two free-floating text lines.
        $paymentHeading = get_invoice_layout_position($layout, 'payment', 'heading', $x0, $sectionY + 11 - $paymentDy);
        if ($paymentHeading['visible']) draw_aligned_text($pdf, $paymentInfo['alignment'], $paymentHeading['x'], $usableW, $paymentHeading['y'], 'Payment Information', 'F2', 10.5, $purple);
        $rowY = $sectionY + 27;
        $i = 0;
        foreach ($paymentInfoLines as $lineId => $line) {
            $linePos = get_invoice_layout_position($layout, 'payment', $lineId, $x0, $rowY + ($i * 13) - $paymentDy);
            if ($linePos['visible']) draw_aligned_text($pdf, $paymentInfo['alignment'], $linePos['x'], $usableW, $linePos['y'], $line, 'F1', 9, $textLight);
            $i++;
        }
        $pdf->advanceY(27 + ($i * 13) + 9);
    }

    // Footer: Thank You text, the (editable) system-generated notice,
    // Terms/Notes, and Contact Information - each an independent,
    // optional line, drawn only when non-empty, in that order. The
    // default footer_text matches the exact pre-Phase-6 wording; the
    // other three are empty by default (nothing new drawn unless an
    // admin fills them in).
    $footer = $designer['footer'];
    $footerLines = [];
    foreach ([
        'thank_you' => $footer['thank_you_text'],
        'footer_text' => $footer['footer_text'],
        'terms_notes' => $footer['terms_notes'],
        'contact_information' => $footer['contact_information'],
    ] as $lineId => $lineText) {
        if (trim((string) $lineText) !== '') {
            $footerLines[$lineId] = (string) $lineText;
        }
    }

    if (!empty($footerLines)) {
        [$footerDx, $footerDy] = $sectionOffset('footer');
        $footerX0 = $x0 + $footerDx;
        $footerY = $pdf->getY() + $footerDy;
        $footerDivider = get_invoice_layout_position($layout, 'footer', 'divider', $x0, $footerY - $footerDy);
        if ($footerDivider['visible']) $pdf->line($footerDivider['x'], $footerDivider['y'], $footerDivider['x'] + $usableW, $footerDivider['y'], 0.5, [0.85, 0.85, 0.85]);
        $pdf->advanceY(14);
        $i = 0;
        foreach ($footerLines as $lineId => $line) {
            $linePos = get_invoice_layout_position($layout, 'footer', $lineId, $x0, $pdf->getY() + ($i * 12) - $footerDy);
            if ($linePos['visible']) draw_aligned_text($pdf, $footer['alignment'], $linePos['x'], $usableW, $linePos['y'], pdf_safe_text($line), 'F1', 8, $textLight);
            $i++;
        }
    }

    // Phase 5G: PDF /Info metadata (visible in a reader's Document
    // Properties). Built ONLY from the stored order snapshot +
    // business settings - no date()/time() for "now" - so the metadata
    // is as deterministic as the visible content (the invoice date is
    // the stored invoice_generated_at, not the render moment). Note
    // the deliberate absence of any /Encrypt owner-password protection:
    // this hand-rolled writer does not support encryption, and the
    // limitation is documented in the file header rather than faked -
    // see the "KNOWN LIMITATION" note above.
    $pdf->setMetadata([
        'Title'        => 'Tax Invoice ' . $order['invoice_number'],
        'Author'       => $business['name'],
        'Subject'      => 'Invoice for order ' . $order['order_number'],
        'Keywords'     => 'Tax Invoice, GST, ' . $business['name'],
        'Creator'      => 'MoonAura Invoice System',
        'Producer'     => 'MoonAura Invoice System (SimplePdfWriter)',
        'CreationDate' => 'D:' . date('YmdHis', strtotime($order['invoice_generated_at'])),
        'ModDate'      => 'D:' . date('YmdHis', strtotime($order['invoice_generated_at'])),
    ]);

    return $pdf->output();
}
