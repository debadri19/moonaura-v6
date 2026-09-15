(function () {
    if (window.__moonauraGa4Init) {
        return;
    }
    window.__moonauraGa4Init = true;

    var queue = Array.isArray(window.moonauraGa4Queue) ? window.moonauraGa4Queue.slice() : [];

    function currency() {
        return window.moonauraGa4Currency || 'INR';
    }

    function hasGtag() {
        return typeof window.gtag === 'function';
    }

    function send(eventName, params) {
        if (!hasGtag() || !eventName) {
            return;
        }

        var payload = params && typeof params === 'object' ? params : {};
        if (!payload.currency) {
            payload.currency = currency();
        }

        if (eventName === 'purchase') {
            var transactionId = payload.transaction_id ? String(payload.transaction_id) : '';
            if (transactionId) {
                var storageKey = 'ga4_purchase_' + transactionId;
                try {
                    if (window.sessionStorage && window.sessionStorage.getItem(storageKey)) {
                        return;
                    }
                    if (window.sessionStorage) {
                        window.sessionStorage.setItem(storageKey, '1');
                    }
                } catch (ignore) {
                }
            }
        }

        window.gtag('event', eventName, payload);
    }

    window.moonauraGa4 = {
        event: send,
        currency: currency
    };

    window.moonauraGa4Queue = {
        push: function (item) {
            if (!item || !item.event) {
                return;
            }
            send(item.event, item.params || {});
        }
    };

    queue.forEach(function (item) {
        if (!item || !item.event) {
            return;
        }
        send(item.event, item.params || {});
    });
})();
