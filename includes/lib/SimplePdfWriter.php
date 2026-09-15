<?php
/* ===================================================================
   SIMPLE PDF WRITER
   -------------------------------------------------------------------
   A minimal, dependency-free PDF 1.4 writer - no Composer, no SDK,
   consistent with this project's established convention for
   integrations (see includes/payments/: both gateways are raw cURL,
   "no Composer SDK, consistent with the rest of the project"). This
   applies the same convention to PDF generation.

   It is NOT a general-purpose PDF library - it only implements the
   handful of primitives an invoice actually needs: positioned text
   (left- or right-aligned, with word-wrapping), straight lines,
   filled rectangles, JPEG image embedding, and automatic page-break
   handling (including a repeating page header via
   setPageHeaderCallback() and an automatic "Page X of Y" footer on
   every page). Text uses the standard 14 PDF fonts (Helvetica /
   Helvetica-Bold) only - these are built into every PDF reader, so
   nothing needs to be embedded and the output stays small.

   KNOWN LIMITATIONS (by design, given the above):
   - No embedded Unicode font, so only WinAnsi-range characters render
     correctly (see pdf_safe_text() in invoice-functions.php, which
     converts UTF-8 database content before it reaches this class).
     This is why invoices show "Rs." instead of the "Rupee sign -
     that glyph isn't in the standard 14 fonts' built-in encoding.
   - textRightAligned() estimates string width from a fixed
     average-character-width ratio rather than real Helvetica AFM
     metrics - accurate enough to right-align short labels and
     currency figures cleanly, not a typesetting-grade layout engine.
   - Image embedding (registerImageJpeg()/drawImage()) only accepts
     already-JPEG-encoded bytes (DCTDecode) - no PNG/WebP decoding
     happens in this class. See register_invoice_logo() in
     invoice-functions.php for how a non-JPEG source is converted
     before it reaches here.
=================================================================== */

class SimplePdfWriter
{
    public const PAGE_WIDTH  = 595.28; // A4, points (1/72 inch)
    public const PAGE_HEIGHT = 841.89;

    private array $pagesContent = [];
    private string $currentContent = '';

    private float $marginTop = 50;
    private float $marginBottom = 50;
    private float $marginLeft = 50;
    private float $marginRight = 50;

    /** Fired by newPage()/output() right after a page's cursor resets
     *  to the top margin, so repeating content (e.g. the invoice
     *  letterhead) can be redrawn on every continuation page. Not
     *  invoked for the first page - the caller draws that one
     *  manually before registering the callback (see
     *  build_invoice_pdf()'s doc comment on $drawLetterhead). */
    private $pageHeaderCallback = null;

    /** 1-based number of the page currently being drawn on - used
     *  only for the "Page X of Y" footer (see drawPageFooter()). */
    private int $pageCounter = 1;

    /** Registered images (registerImageJpeg()), keyed by the same
     *  "ImN" name used as their /XObject resource name in every
     *  page's /Resources dict AND as the operand of the `Do` operator
     *  drawImage() emits - see both methods' doc comments. */
    private array $images = [];
    private int $imageCounter = 0;

    /** Cursor - "distance from the top of the page", NOT raw PDF
     *  coordinates. Every drawing method below takes/returns
     *  positions in this same top-down system so invoice-building
     *  code never has to think in PDF's bottom-up coordinate space. */
    private float $y;

    /** Document-level metadata (setMetadata()), emitted as the PDF's
     *  /Info dictionary (Title/Author/Subject/etc.). Values are
     *  output verbatim as PDF strings, so they must already be
     *  deterministic-safe for the caller to emit; output() performs
     *  no date()/time()/rand() on its own. See output()'s doc
     *  comment for how the dictionary is added to the trailer. */
    private array $metadata = [];

    public function __construct()
    {
        $this->y = $this->marginTop;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): void
    {
        $this->y = $y;
    }

    public function advanceY(float $delta): void
    {
        $this->y += $delta;
    }

    public function getMarginLeft(): float
    {
        return $this->marginLeft;
    }

    public function getUsableWidth(): float
    {
        return self::PAGE_WIDTH - $this->marginLeft - $this->marginRight;
    }

    public function getRightX(): float
    {
        return self::PAGE_WIDTH - $this->marginRight;
    }

    public function setPageHeaderCallback(callable $callback): void
    {
        $this->pageHeaderCallback = $callback;
    }

    /* ------------------------------------------
       Sets the document-level /Info metadata (PDF-standard keys:
       Title, Author, Subject, Keywords, Creator, Producer,
       CreationDate, ModDate, ...). Values are rendered as literal
       PDF strings (the same escaping used for drawn text - see
       escape()) and must therefore be Windows-1252/ASCII-safe
       already - callers that feed database content through here
       should run it through pdf_safe_text() first (see
       invoice-functions.php). Setting a key to '' omits it from the
       dictionary. No validation of key names: keep to the standard
       /Info keys above.
    ------------------------------------------ */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /* ------------------------------------------
       Starts a new page. Called automatically by
       ensureSpace() when content would run past the
       bottom margin - callers rarely need to call
       this directly.
    ------------------------------------------ */
    public function newPage(): void
    {
        $this->drawPageFooter();

        $this->pagesContent[] = $this->currentContent;
        $this->currentContent = '';
        $this->y = $this->marginTop;

        if ($this->pageHeaderCallback !== null) {
            ($this->pageHeaderCallback)($this);
        }
    }

    /* ------------------------------------------
       Guarantees at least $needed points remain before
       the bottom margin, starting a new page first if
       not - so a table row (or any other unit of content)
       is never split across a page boundary.
    ------------------------------------------ */
    // Returns true if a new page was started (so callers that draw
    // repeating content, like a table header, know to redraw it).
    public function ensureSpace(float $needed): bool
    {
        if ($this->y + $needed > self::PAGE_HEIGHT - $this->marginBottom) {
            $this->newPage();
            return true;
        }
        return false;
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function toPdfY(float $yFromTop): float
    {
        return self::PAGE_HEIGHT - $yFromTop;
    }

    public function text(float $x, float $yFromTop, string $str, string $font = 'F1', float $size = 10, array $rgb = [0, 0, 0]): void
    {
        [$r, $g, $b] = $rgb;
        $this->currentContent .= sprintf(
            "q\n%.3F %.3F %.3F rg\nBT\n/%s %.2F Tf\n%.2F %.2F Td\n(%s) Tj\nET\nQ\n",
            $r, $g, $b, $font, $size, $x, $this->toPdfY($yFromTop), $this->escape($str)
        );
    }

    // See the class-level doc comment - this is an estimate, not real
    // font metrics, and is only meant for right-aligning short labels
    // and currency figures.
    private function estimateWidth(string $str, string $font, float $size): float
    {
        $ratio = ($font === 'F2') ? 0.56 : 0.5;
        return strlen($str) * $size * $ratio;
    }

    public function textRightAligned(float $rightX, float $yFromTop, string $str, string $font = 'F1', float $size = 10, array $rgb = [0, 0, 0]): void
    {
        $this->text($rightX - $this->estimateWidth($str, $font, $size), $yFromTop, $str, $font, $size, $rgb);
    }

    /* ------------------------------------------
       Same contract as textRightAligned(), but centers $str on
       $centerX instead of ending it there. Added for the Invoice
       Designer's "center" alignment option (header title, footer
       text) - existing text()/textRightAligned() callers and their
       output are completely unaffected by this method's presence.
    ------------------------------------------ */
    public function textCentered(float $centerX, float $yFromTop, string $str, string $font = 'F1', float $size = 10, array $rgb = [0, 0, 0]): void
    {
        $this->text($centerX - ($this->estimateWidth($str, $font, $size) / 2), $yFromTop, $str, $font, $size, $rgb);
    }

    /* ------------------------------------------
       Greedy word-wrap to fit $maxWidth, using the same
       character-count estimate as textRightAligned() (see
       the class-level doc comment on its limits). A single
       word wider than $maxWidth on its own is left on its
       own line rather than split or dropped - an edge case
       for an invoice (an unbroken product name/URL), not
       worth a hyphenation algorithm here.
    ------------------------------------------ */
    public function wrapText(string $text, float $maxWidth, string $font = 'F1', float $size = 10): array
    {
        if ($text === '') {
            return [''];
        }

        $words   = explode(' ', $text);
        $lines   = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = ($current === '') ? $word : ($current . ' ' . $word);

            if ($current === '' || $this->estimateWidth($candidate, $font, $size) <= $maxWidth) {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /* ------------------------------------------
       Wraps $text to $maxWidth and draws every resulting
       line starting at ($x, $yFromTop), one $lineHeight
       apart (defaults to $size + 4 - matches the 13pt
       spacing already used by hand at 9pt elsewhere in
       build_invoice_pdf()). Returns the y position
       immediately below the last line drawn, so callers can
       chain further content (or further wrappedText() calls
       for the next logical line) directly onto the return
       value - see its call sites in build_invoice_pdf().
    ------------------------------------------ */
    public function wrappedText(float $x, float $yFromTop, float $maxWidth, string $text, string $font = 'F1', float $size = 10, array $rgb = [0, 0, 0], ?float $lineHeight = null): float
    {
        $lineHeight ??= $size + 4;
        $lines = $this->wrapText($text, $maxWidth, $font, $size);

        foreach ($lines as $i => $line) {
            $this->text($x, $yFromTop + ($i * $lineHeight), $line, $font, $size, $rgb);
        }

        return $yFromTop + (count($lines) * $lineHeight);
    }

    /* ------------------------------------------
       "Page X of Y" footer, centered in the bottom margin
       band (below marginBottom, so it never collides with
       ensureSpace()-guarded content). Called automatically
       for every page from newPage() (the page being closed)
       and output() (the final page) - never called directly.

       Y (total page count) isn't known until every page has
       been drawn, so this writes a literal placeholder token
       here and output() replaces it everywhere once the
       final count is known - see output()'s doc comment.
    ------------------------------------------ */
    private function drawPageFooter(): void
    {
        $label = 'Page ' . $this->pageCounter . ' of {{TOTAL_PAGES}}';
        $size  = 8;
        $x     = (self::PAGE_WIDTH - $this->estimateWidth($label, 'F1', $size)) / 2;

        $this->text($x, self::PAGE_HEIGHT - 28, $label, 'F1', $size, [0.5, 0.5, 0.5]);
        $this->pageCounter++;
    }

    public function line(float $x1, float $y1FromTop, float $x2, float $y2FromTop, float $width = 0.5, array $rgb = [0, 0, 0]): void
    {
        [$r, $g, $b] = $rgb;
        $this->currentContent .= sprintf(
            "q\n%.3F %.3F %.3F RG\n%.2F w\n%.2F %.2F m\n%.2F %.2F l\nS\nQ\n",
            $r, $g, $b, $width, $x1, $this->toPdfY($y1FromTop), $x2, $this->toPdfY($y2FromTop)
        );
    }

    // $yFromTop is the TOP edge of the rectangle (not the PDF-native
    // bottom-left origin) - kept consistent with text()'s top-down
    // coordinate system.
    public function filledRect(float $x, float $yFromTop, float $w, float $h, array $rgb): void
    {
        [$r, $g, $b] = $rgb;
        $pdfY = $this->toPdfY($yFromTop) - $h;
        $this->currentContent .= sprintf(
            "q\n%.3F %.3F %.3F rg\n%.2F %.2F %.2F %.2F re\nf\nQ\n",
            $r, $g, $b, $x, $pdfY, $w, $h
        );
    }

    /* ------------------------------------------
       Same as filledRect() but with rounded corners - a small,
       additive primitive (PDF has no native "rounded rect" operator,
       so this builds the path by hand: straight edges plus a cubic
       Bezier at each corner, using the standard "kappa" ~0.5523
       constant that approximates a quarter-circle arc closely enough
       for a UI corner radius). $radius is clamped to half the
       shorter side so it can never overshoot into a self-intersecting
       path. $radius <= 0 draws a plain rectangle (delegates to
       filledRect()) rather than emitting a degenerate curve.
    ------------------------------------------ */
    public function filledRoundedRect(float $x, float $yFromTop, float $w, float $h, float $radius, array $rgb): void
    {
        $radius = max(0.0, min($radius, min($w, $h) / 2));

        if ($radius <= 0.0) {
            $this->filledRect($x, $yFromTop, $w, $h, $rgb);
            return;
        }

        [$r, $g, $b] = $rgb;
        $k = 0.5523 * $radius;

        $yTop = $this->toPdfY($yFromTop);
        $yBot = $yTop - $h;
        $xL   = $x;
        $xR   = $x + $w;

        $path = sprintf(
            "%.2F %.2F m\n" .
            "%.2F %.2F l\n" .
            "%.2F %.2F %.2F %.2F %.2F %.2F c\n" .
            "%.2F %.2F l\n" .
            "%.2F %.2F %.2F %.2F %.2F %.2F c\n" .
            "%.2F %.2F l\n" .
            "%.2F %.2F %.2F %.2F %.2F %.2F c\n" .
            "%.2F %.2F l\n" .
            "%.2F %.2F %.2F %.2F %.2F %.2F c\n" .
            "h\nf\n",
            $xL + $radius, $yTop,
            $xR - $radius, $yTop,
            $xR - $radius + $k, $yTop, $xR, $yTop - $radius + $k, $xR, $yTop - $radius,
            $xR, $yBot + $radius,
            $xR, $yBot + $radius - $k, $xR - $radius + $k, $yBot, $xR - $radius, $yBot,
            $xL + $radius, $yBot,
            $xL + $radius - $k, $yBot, $xL, $yBot + $radius - $k, $xL, $yBot + $radius,
            $xL, $yTop - $radius,
            $xL, $yTop - $radius + $k, $xL + $radius - $k, $yTop, $xL + $radius, $yTop
        );

        $this->currentContent .= sprintf("q\n%.3F %.3F %.3F rg\n%s Q\n", $r, $g, $b, $path);
    }

    /* ------------------------------------------
       Registers already-JPEG-encoded bytes (DCTDecode - a JPEG's own
       compressed bytes drop straight into a PDF image stream, no
       re-encoding) as a drawable image and returns its resource key
       ("Im1", "Im2", ...). $colorSpace is 'DeviceRGB' or
       'DeviceGray'. $smaskKey, if given, must be the key of an
       already-registered DeviceGray image used as this image's
       soft-mask (per-pixel alpha) - see register_invoice_logo()'s
       doc comment for how the two are built together. Every page
       gets every registered image in its /Resources dict regardless
       of which page(s) actually draw it (same approach already used
       for /F1 /F2) - simpler than tracking per-page usage, and an
       unused resource reference is harmless.
    ------------------------------------------ */
    public function registerImageJpeg(string $jpegData, int $width, int $height, string $colorSpace = 'DeviceRGB', ?string $smaskKey = null): string
    {
        $key = 'Im' . (++$this->imageCounter);

        $this->images[$key] = [
            'data'       => $jpegData,
            'width'      => $width,
            'height'     => $height,
            'colorSpace' => $colorSpace,
            'smaskKey'   => $smaskKey,
        ];

        return $key;
    }

    /* ------------------------------------------
       Registers an already-compressed raw image stream. Currently used
       only for lossless grayscale invoice alpha masks: PDF applies the
       caller-supplied filter directly to the stream, avoiding DCT/JPEG
       loss on sharp transparency edges.
    ------------------------------------------ */
    public function registerImageFlate(string $compressedData, int $width, int $height, string $colorSpace = 'DeviceGray', ?string $smaskKey = null): string
    {
        $key = 'Im' . (++$this->imageCounter);

        $this->images[$key] = [
            'data'       => $compressedData,
            'width'      => $width,
            'height'     => $height,
            'colorSpace' => $colorSpace,
            'smaskKey'   => $smaskKey,
            'filter'     => 'FlateDecode',
        ];

        return $key;
    }

    /* ------------------------------------------
       Draws a previously-registered image at ($x, $yFromTop) - the
       image's TOP-LEFT corner, consistent with this class's top-down
       coordinate system - scaled to exactly $width x $height. An
       unknown $key draws nothing rather than emitting a reference to
       a resource that doesn't exist (which would corrupt the page).
    ------------------------------------------ */
    public function drawImage(string $key, float $x, float $yFromTop, float $width, float $height): void
    {
        if (!isset($this->images[$key])) {
            return;
        }

        $pdfY = $this->toPdfY($yFromTop) - $height;
        $this->currentContent .= sprintf(
            "q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n",
            $width, $height, $x, $pdfY, $key
        );
    }

    /* ------------------------------------------
       Same contract as drawImage() (draws a previously-registered
       image scaled to $width x $height), but rotated $degrees
       clockwise around its own center point ($x, $yFromTop is still
       the image's unrotated top-left corner - the rotation pivots
       around the middle of that $width x $height box, not the page
       origin, so the image stays centered on the same spot regardless
       of angle). $degrees === 0 draws identically to drawImage() (same
       output, just routed through the general rotation matrix instead
       of the axis-aligned shortcut) - added for the Invoice Designer's
       watermark rotation control; nothing else in this class or any
       existing caller is changed by this method's presence.
    ------------------------------------------ */
    public function drawImageRotated(string $key, float $x, float $yFromTop, float $width, float $height, float $degrees): void
    {
        if (!isset($this->images[$key])) {
            return;
        }

        // PDF's CTM rotation is counter-clockwise for a positive
        // angle in its own (bottom-up) coordinate space, which is
        // visually CLOCKWISE once flipped into this class's top-down
        // system - negating here keeps "$degrees clockwise" true from
        // the caller's point of view, matching how a rotation dial in
        // a UI is normally read.
        $rad = -deg2rad($degrees);
        $cos = cos($rad);
        $sin = sin($rad);

        $cx = $x + $width / 2;
        $cyFromTop = $yFromTop + $height / 2;
        $cy = $this->toPdfY($cyFromTop);

        // Full affine transform: translate the image's own unit
        // square (0..1 x 0..1, which /Do always draws into) to
        // width x height, rotate it about its own center, then
        // translate that center to (cx, cy) in page space.
        $a = $width * $cos;
        $b = $width * $sin;
        $c = -$height * $sin;
        $d = $height * $cos;
        $e = $cx - ($a + $c) / 2;
        $f = $cy - ($b + $d) / 2;

        $this->currentContent .= sprintf(
            "q\n%.4F %.4F %.4F %.4F %.2F %.2F cm\n/%s Do\nQ\n",
            $a, $b, $c, $d, $e, $f, $key
        );
    }

    /* ------------------------------------------
       Assembles the full PDF 1.4 byte stream: header, one object per
       font/page/content-stream, an xref table with exact byte offsets,
       and a trailer. Returns the raw bytes ready to send with a
       "Content-Type: application/pdf" header - nothing is written to
       disk (see the "why on-demand, not stored" note in
       includes/invoice-functions.php for why that matters here).
    ------------------------------------------ */
    public function output(): string
    {
        $this->drawPageFooter();
        $this->pagesContent[] = $this->currentContent;

        // Every page's footer was drawn with a literal "{{TOTAL_PAGES}}"
        // placeholder (see drawPageFooter()'s doc comment) because the
        // true count wasn't known until just now.
        $totalPages = count($this->pagesContent);
        foreach ($this->pagesContent as $i => $content) {
            $this->pagesContent[$i] = str_replace('{{TOTAL_PAGES}}', (string) $totalPages, $content);
        }

        $catalogNum = 1;
        $pagesNum   = 2;
        $fontF1Num  = 3;
        $fontF2Num  = 4;
        $nextObjNum = 5;

        // Images get object numbers before pages/content so an
        // /SMask reference (which points at another image's object
        // number) is always already known by the time it's needed
        // below - registerImageJpeg() guarantees a mask is always
        // registered before the image that references it.
        $imageObjNums = [];
        foreach ($this->images as $key => $img) {
            $imageObjNums[$key] = $nextObjNum++;
        }

        $pageNums    = [];
        $contentNums = [];
        foreach ($this->pagesContent as $i => $content) {
            $pageNums[$i]    = $nextObjNum++;
            $contentNums[$i] = $nextObjNum++;
        }

        // /Info metadata (when set) gets the last object number -
        // allocated after every structural object so existing numbering
        // (catalog=1, pages=2, F1=3, F2=4, then images/pages/content)
        // is untouched. A metadata-free document allocates nothing, so
        // its output stays byte-identical to pre-metadata output.
        $infoNum    = null;
        $maxObjNum  = $nextObjNum - 1;
        if (!empty($this->metadata)) {
            $infoNum   = $nextObjNum++;
            $maxObjNum = $nextObjNum - 1;
        }

        $objStrings = [];
        $bodies     = [];
        $rawBodies  = []; // objects with a pre-built dict + binary stream (images)

        $kids = implode(' ', array_map(static fn($n) => "{$n} 0 R", $pageNums));
        $objStrings[$catalogNum] = "<< /Type /Catalog /Pages {$pagesNum} 0 R >>";
        $objStrings[$pagesNum]   = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageNums) . ' >>';
        $objStrings[$fontF1Num]  = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objStrings[$fontF2Num]  = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        if ($infoNum !== null) {
            $infoParts = [];
            foreach ($this->metadata as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $infoParts[] = '/' . $key . ' (' . $this->escape((string) $value) . ')';
                }
            }
            $objStrings[$infoNum] = '<< ' . implode(' ', $infoParts) . ' >>';
        }

        foreach ($this->images as $key => $img) {
            $objNum = $imageObjNums[$key];
            $filter = $img['filter'] ?? 'DCTDecode';
            $dict = "<< /Type /XObject /Subtype /Image /Width {$img['width']} /Height {$img['height']} "
                . "/ColorSpace /{$img['colorSpace']} /BitsPerComponent 8 /Filter /{$filter}";
            if ($img['smaskKey'] !== null && isset($imageObjNums[$img['smaskKey']])) {
                $dict .= ' /SMask ' . $imageObjNums[$img['smaskKey']] . ' 0 R';
            }
            $dict .= ' /Length ' . strlen($img['data']) . ' >>';
            $rawBodies[$objNum] = $dict . "\nstream\n" . $img['data'] . "\nendstream";
        }

        $xobjectDict = '';
        if (!empty($imageObjNums)) {
            $entries = [];
            foreach ($imageObjNums as $key => $num) {
                $entries[] = "/{$key} {$num} 0 R";
            }
            $xobjectDict = ' /XObject << ' . implode(' ', $entries) . ' >>';
        }

        foreach ($this->pagesContent as $i => $content) {
            $pNum = $pageNums[$i];
            $cNum = $contentNums[$i];
            $objStrings[$pNum] = '<< /Type /Page /Parent ' . $pagesNum . ' 0 R '
                . '/MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] '
                . "/Resources << /Font << /F1 {$fontF1Num} 0 R /F2 {$fontF2Num} 0 R >>{$xobjectDict} >> "
                . "/Contents {$cNum} 0 R >>";
            $bodies[$cNum] = $content;
        }

        $out       = "%PDF-1.4\n";
        $offsets   = [];

        for ($n = 1; $n <= $maxObjNum; $n++) {
            $offsets[$n] = strlen($out);
            if (isset($rawBodies[$n])) {
                $out .= "{$n} 0 obj\n{$rawBodies[$n]}\nendobj\n";
            } elseif (isset($bodies[$n])) {
                $stream = $bodies[$n];
                $out .= "{$n} 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n";
            } else {
                $out .= "{$n} 0 obj\n{$objStrings[$n]}\nendobj\n";
            }
        }

        $xrefStart = strlen($out);
        $out .= "xref\n0 " . ($maxObjNum + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $maxObjNum; $n++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }
        // /Info is referenced from the trailer only when metadata was
        // set - otherwise the trailer is byte-identical to the
        // pre-metadata format.
        $infoRef = '';
        if ($infoNum !== null) {
            $infoRef = " /Info {$infoNum} 0 R";
        }
        $out .= 'trailer' . "\n" . '<< /Size ' . ($maxObjNum + 1) . " /Root {$catalogNum} 0 R{$infoRef} >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $out;
    }
}
