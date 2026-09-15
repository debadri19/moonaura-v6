(function () {
    /* ===================================================================
       #38 DASHBOARD LIVE ORDER PANEL - LIGHTWEIGHT POLLING
       -------------------------------------------------------------------
       Refreshes only the Recent Orders <tbody> on admin/dashboard.php by
       polling admin/dashboard-recent-orders.php (read-only JSON, same
       admin-session check as every other admin page). Does NOT touch any
       other part of the dashboard, does not reload the page, and never
       duplicates rows - each poll simply rebuilds the small fixed-size
       (max 8-row) list it's given, keyed by order id.

       Server remains authoritative: this never invents or predicts
       order data, it only displays whatever the endpoint just returned.
    =================================================================== */

    var POLL_INTERVAL_MS = 45000; // ~45s - within the suggested 30-60s range
    var tbody = document.getElementById('recent-orders-tbody');

    if (!tbody) {
        return; // Not on the dashboard page.
    }

    var lastSignature = tbody.getAttribute('data-signature') || '';
    var pollTimer = null;
    var isFetching = false;

    /* ===================================================================
       NEW ORDER NOTIFICATION SOUND
       -------------------------------------------------------------------
       New-order detection is deliberately separate from the id:status
       signature above (that signature still drives the table refresh -
       a status-only change must NOT ring). Instead a known-order-ID set
       tracks which orders this tab has already seen:

       - The server-rendered rows seed the set on page load, silently.
       - On each successful poll, any returned ID that is not already in
         the set is a genuinely new order -> ring once for that cycle.
       - IDs are added to the set whether or not the signature changed,
         and the set is never cleared, so the same order can never ring
         twice and duplicate responses are ignored.

       The polling interval, endpoint, signature check and rendering are
       all untouched.
    =================================================================== */

    var knownOrderIds = Object.create(null);
    var audio = null;
    var audioReady = false;
    var audioUnlocked = false;

    (function seedKnownOrderIdsFromDom() {
        var rows = tbody.querySelectorAll('[data-order-id]');
        for (var i = 0; i < rows.length; i++) {
            knownOrderIds[String(rows[i].getAttribute('data-order-id'))] = true;
        }
    })();

    function collectOrderIds(orders) {
        var ids = Object.create(null);
        for (var i = 0; i < orders.length; i++) {
            ids[String(orders[i].id)] = true;
        }
        return ids;
    }

    function initNotificationAudio() {
        var soundUrl = tbody.getAttribute('data-notification-sound');
        if (!soundUrl) {
            return; // No sound configured - polling/rendering continues as before.
        }
        audio = new Audio(soundUrl);
        audio.preload = 'auto';
        audio.addEventListener('error', function () {
            // Asset failed to load - stop attempting playback, but never
            // let it affect the dashboard.
            audioReady = false;
        });
        audioReady = true;
    }

    function unlockNotificationAudio() {
        // One-time, SILENT warm-up on the first user gesture so a later
        // new-order ring is allowed by the browser. This never plays the
        // notification sound on its own.
        if (!audio || audioUnlocked) {
            return;
        }
        audioUnlocked = true;
        try {
            audio.muted = true;
            var attempt = audio.play();
            if (attempt && typeof attempt.then === 'function') {
                attempt.then(function () {
                    try {
                        audio.pause();
                        audio.currentTime = 0;
                    } catch (e) {}
                    audio.muted = false;
                }).catch(function () {
                    audio.muted = false;
                });
            } else {
                audio.pause();
                audio.currentTime = 0;
                audio.muted = false;
            }
        } catch (e) {
            try { audio.muted = false; } catch (e2) {}
        }
    }

    function playNotificationSound() {
        if (!audio || !audioReady) {
            return;
        }
        try {
            audio.currentTime = 0; // Restart from the beginning each time.
        } catch (e) {}
        try {
            var attempt = audio.play();
            if (attempt && typeof attempt.catch === 'function') {
                // Autoplay-policy rejections before any user gesture are
                // expected; swallow them silently (no console spam) so
                // polling and rendering keep working.
                attempt.catch(function () {});
            }
        } catch (e) {
            // A sound failure must never interrupt the dashboard.
        }
    }

    initNotificationAudio();

    ['click', 'pointerdown', 'keydown'].forEach(function (gesture) {
        window.addEventListener(gesture, unlockNotificationAudio, { once: true, passive: true });
    });

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    function rowsSignature(orders) {
        // Cheap way to detect "did anything actually change" without a
        // deep diff - avoids re-rendering (and any flicker) when nothing
        // is new. Order status can change on an existing row too, so the
        // status is included alongside the id.
        return orders.map(function (o) { return o.id + ':' + o.status; }).join(',');
    }

    function render(orders) {
        if (!orders.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="admin-table-empty">No orders yet.</td></tr>';
            return;
        }

        var html = orders.map(function (order) {
            return (
                '<tr data-order-id="' + order.id + '">' +
                    '<td><code>' + escapeHtml(order.order_number) + '</code></td>' +
                    '<td>' + escapeHtml(order.customer_name) + '</td>' +
                    '<td>' + escapeHtml(order.amount) + '</td>' +
                    '<td><span class="admin-badge admin-badge-' + escapeHtml(order.status) + '">' +
                        escapeHtml(order.status_label) +
                    '</span></td>' +
                    '<td>' + escapeHtml(order.created_at) + '</td>' +
                    '<td class="admin-table-actions">' +
                        '<a href="' + escapeHtml(order.detail_url) + '" title="View Details">' +
                            '<i class="fa-solid fa-eye"></i>' +
                        '</a>' +
                    '</td>' +
                '</tr>'
            );
        }).join('');

        tbody.innerHTML = html;
    }

    function poll() {
        if (isFetching || document.hidden) {
            return; // Skip while a request is in flight, or the tab isn't visible.
        }

        isFetching = true;

        fetch('dashboard-recent-orders.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Recent orders request failed: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                var orders = (data && Array.isArray(data.orders)) ? data.orders : [];
                var signature = rowsSignature(orders);

                // New-order detection is based on the ID set, not the
                // signature. At most one ring per polling cycle, however
                // many new IDs arrived. IDs are recorded here so a repeat
                // response (or a later poll) can never ring them again.
                var currentIds = collectOrderIds(orders);
                var hasNewOrder = false;
                for (var id in currentIds) {
                    if (!knownOrderIds[id]) {
                        hasNewOrder = true;
                    }
                    knownOrderIds[id] = true;
                }

                // Scroll position of the page is untouched either way since
                // we only ever replace the <tbody> contents in place - never
                // the surrounding page or table structure.
                if (signature !== lastSignature) {
                    render(orders);
                    lastSignature = signature;
                    tbody.setAttribute('data-signature', signature);
                }

                if (hasNewOrder) {
                    playNotificationSound();
                }
            })
            .catch(function () {
                // Silent by design: a background refresh failing (session
                // expired, brief network blip) shouldn't interrupt the admin
                // or throw a visible error onto the dashboard. The next
                // scheduled poll simply tries again.
            })
            .finally(function () {
                isFetching = false;
            });
    }

    pollTimer = window.setInterval(poll, POLL_INTERVAL_MS);

    // Stop polling once the admin navigates away, so no request fires
    // after the page is gone.
    window.addEventListener('beforeunload', function () {
        window.clearInterval(pollTimer);
    });
})();
