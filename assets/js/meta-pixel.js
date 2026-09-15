/* ===================================================================
   META (FACEBOOK) PIXEL - PHASE 2 BROWSER EVENT HELPER
   -------------------------------------------------------------------
   Single, reusable place where standard Meta browser-event payloads
   are built, so every call site is shaped identically.

   Responsibilities:
   - Build standard ecommerce params (content_ids, content_name,
     content_type, value, currency, contents) from the SAME GA4-shaped
     item arrays MoonAura already produces for its own analytics -
     no new product/cart data source is introduced here.
   - Always use INR (window.moonauraMetaCurrency, default 'INR').
   - Drain window.moonauraMetaQueue (server-queued page events).
   - Expose window.moonauraMeta for the AJAX success handlers.
   - No-op safely when fbq is unavailable (Pixel unconfigured / the
     base tag never rendered), so the storefront is never affected.

   Phase 3 adds the standard Purchase conversion event on top of this
   helper (same builder, same guards). A Purchase record carries an
   internal "dedupe" key - the completed order's stable token - which
   is used only to remember (in a Purchase-scoped localStorage list)
   that this order already converted, so a refresh/revisit cannot emit
   it twice. The dedupe key is never part of the Meta payload.

   Deliberately NOT implemented: server-side / Conversions API events,
   Advanced Matching, or any PII (email, phone, name, address). The
   order id is used only as the internal dedupe token and is never sent.

   Phase 4 adds an optional, opaque event_id to Purchase records. It is
   forwarded to fbq as Meta's standard eventID option so the browser
   event can be deduplicated against the server-side event. It is not a
   custom parameter and carries no order or customer data.
================================================================== */

(function () {
    if (window.__moonauraMetaInit) {
        return;
    }
    window.__moonauraMetaInit = true;

    // Per-page-load guard so a queued "once" event can never fire twice
    // because of repeated callbacks, re-renders or event bubbling.
    var firedOnce = {};

    function hasFbq() {
        return typeof window.fbq === 'function';
    }

    function currency() {
        return window.moonauraMetaCurrency || 'INR';
    }

    function contentId(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }
        var id = item.item_id;
        if (typeof id === 'undefined' || id === null || id === '') {
            id = item.id;
        }
        return (typeof id === 'undefined' || id === null) ? '' : String(id);
    }

    function quantity(item) {
        var q = parseInt(item && item.quantity, 10);
        if (!isFinite(q) || q < 1) {
            q = 1;
        }
        return q;
    }

    function price(item) {
        var p = parseFloat(item && item.price);
        return isFinite(p) && p > 0 ? p : 0;
    }

    function round2(value) {
        var n = Number(value);
        if (!isFinite(n)) {
            n = 0;
        }
        return Math.round(n * 100) / 100;
    }

    function itemsValue(items) {
        var total = 0;
        for (var i = 0; i < items.length; i++) {
            total += price(items[i]) * quantity(items[i]);
        }
        return round2(total);
    }

    /* ==========================================
       PAYLOAD BUILDER
       Returns null when there is nothing valid to
       send (e.g. empty query / no usable items) so
       the caller can skip the event entirely.
    ========================================== */

    function buildParams(data) {
        data = (data && typeof data === 'object') ? data : {};
        var params = {};

        // Search event: only a non-empty query is ever sent.
        if (typeof data.search_string === 'string') {
            var query = data.search_string.trim();
            if (query === '') {
                return null;
            }
            params.search_string = query;
            return params;
        }

        var items = Array.isArray(data.items) ? data.items : [];
        var ids = [];
        var contents = [];

        for (var i = 0; i < items.length; i++) {
            var id = contentId(items[i]);
            if (id === '') {
                continue;
            }
            ids.push(id);
            contents.push({ id: id, quantity: quantity(items[i]) });
        }

        if (!ids.length) {
            return null;
        }

        var type = data.type === 'product_group' ? 'product_group' : 'product';

        params.content_ids = ids;
        params.content_type = type;

        if (typeof data.value === 'number' && isFinite(data.value)) {
            params.value = round2(data.value);
        } else {
            params.value = itemsValue(items);
        }

        params.currency = currency();

        // data.name === false explicitly omits content_name (used by the
        // order-level Purchase event, which has no single product name).
        var name = typeof data.name === 'string' ? data.name : '';
        var nameAllowed = data.name !== false;
        if (name === '' && nameAllowed && type === 'product' && items[0] && typeof items[0].item_name === 'string') {
            name = items[0].item_name;
        }
        if (name !== '') {
            params.content_name = name;
        }

        // Meta "contents" is included for cart/checkout events and
        // omitted where the phase spec does not call for it.
        if (type === 'product' && data.contents !== false) {
            params.contents = contents;
        }

        return params;
    }

    /* ==========================================
       PURCHASE DEDUPE STORE
       A single, Purchase-scoped localStorage list
       of completed-order tokens. It survives a
       page refresh (new JS context) so the same
       order never emits a second Purchase, while a
       genuinely different order still fires.
       Only opaque order tokens are kept; no
       customer data. All storage access is
       try/caught so a blocked/unavailable
       localStorage never breaks the storefront -
       the in-memory "once" guard still applies.
    ========================================== */

    var PURCHASE_STORE_KEY = 'moonauraMetaPurchases';
    var PURCHASE_STORE_LIMIT = 50;

    function readPurchaseStore() {
        try {
            var store = window.localStorage;
            if (!store) {
                return null;
            }
            var raw = store.getItem(PURCHASE_STORE_KEY);
            if (!raw) {
                return [];
            }
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (ignore) {
            return null;
        }
    }

    function purchaseAlreadyFired(dedupeKey) {
        var store = readPurchaseStore();
        if (!store) {
            return false;
        }
        return store.indexOf(dedupeKey) !== -1;
    }

    function rememberPurchase(dedupeKey) {
        var store = readPurchaseStore();
        if (!store) {
            return;
        }
        if (store.indexOf(dedupeKey) === -1) {
            store.push(dedupeKey);
            if (store.length > PURCHASE_STORE_LIMIT) {
                store = store.slice(store.length - PURCHASE_STORE_LIMIT);
            }
        }
        try {
            window.localStorage.setItem(PURCHASE_STORE_KEY, JSON.stringify(store));
        } catch (ignore) {
            // Storage full/blocked - the in-memory guard still prevents
            // a duplicate within this page load.
        }
    }

    /* ==========================================
       SEND
       Guarded by hasFbq(); "once" and "dedupe"
       keys are only marked after a successful
       fbq call, so a Pixel that is unavailable
       now can still convert on a later load.
    ========================================== */

    function send(eventName, data, onceKey, dedupeKey) {
        if (!hasFbq() || typeof eventName !== 'string' || eventName === '') {
            return false;
        }

        if (onceKey && firedOnce[onceKey]) {
            return false;
        }

        if (dedupeKey) {
            if (firedOnce['dedupe:' + dedupeKey] || purchaseAlreadyFired(dedupeKey)) {
                return false;
            }
        }

        var params = buildParams(data);
        if (params === null) {
            return false;
        }

        // Phase 4: forward the shared, opaque event id used by the
        // server-side Conversions API so Meta can deduplicate the two
        // Purchase events. It is passed as Meta's standard 4th
        // ("eventID") argument - never merged into the custom params.
        var eventId = (data && typeof data.event_id === 'string') ? data.event_id.trim() : '';
        var options = eventId !== '' ? { eventID: eventId } : null;

        try {
            if (options !== null) {
                window.fbq('track', eventName, params, options);
            } else {
                window.fbq('track', eventName, params);
            }
        } catch (ignore) {
            return false;
        }

        if (onceKey) {
            firedOnce[onceKey] = true;
        }

        if (dedupeKey) {
            firedOnce['dedupe:' + dedupeKey] = true;
            rememberPurchase(dedupeKey);
        }

        return true;
    }

    function dispatch(record) {
        if (!record || typeof record !== 'object' || typeof record.event !== 'string') {
            return;
        }

        var onceKey = record.once ? 'once:' + record.event : null;
        var dedupeKey = (typeof record.dedupe === 'string' && record.dedupe !== '') ? record.dedupe : null;
        send(record.event, record, onceKey, dedupeKey);
    }

    window.moonauraMeta = {
        currency: currency,
        track: function (eventName, data, onceKey) {
            return send(eventName, data, onceKey || null);
        },
        // Convenience wrapper for client-side product actions
        // (AddToCart / RemoveFromCart / AddToWishlist).
        product: function (eventName, items, options) {
            var data = { type: 'product', items: items };
            if (options && typeof options === 'object') {
                for (var key in options) {
                    if (Object.prototype.hasOwnProperty.call(options, key)) {
                        data[key] = options[key];
                    }
                }
            }
            return send(eventName, data, null);
        },
        // Phase 3: standard Purchase for a genuinely completed order.
        // $value is the authoritative final amount; $dedupeKey is the
        // order's internal once-only token (never sent to Meta).
        // $eventId (Phase 4) is the shared browser/server deduplication
        // id, forwarded to fbq as Meta's standard eventID option.
        purchase: function (items, value, dedupeKey, eventId) {
            var data = {
                type: 'product',
                items: items,
                value: value,
                name: false
            };
            if (typeof eventId === 'string' && eventId !== '') {
                data.event_id = eventId;
            }
            return send('Purchase', data, null, dedupeKey || null);
        },
        // Exposed for tests / future phases.
        _build: buildParams
    };

    // Drain anything the server queued before this file loaded, then
    // forward any later pushes straight to send().
    var queued = Array.isArray(window.moonauraMetaQueue) ? window.moonauraMetaQueue.slice() : [];
    window.moonauraMetaQueue = { push: dispatch };

    for (var i = 0; i < queued.length; i++) {
        dispatch(queued[i]);
    }
})();
