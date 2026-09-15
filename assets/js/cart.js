(function () {
    var cartItems = document.querySelector('.cart-items');
    if (!cartItems) return;

    var qtyTimer = null;

    function updateCartBadge(count) {
        var floatBtn = document.querySelector('.cart-float');
        if (!floatBtn) return;

        var badge = floatBtn.querySelector('.cart-count-badge');
        count = parseInt(count, 10) || 0;

        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'cart-count-badge';
                floatBtn.appendChild(badge);
            }
            badge.textContent = String(count);
        } else if (badge) {
            badge.remove();
        }
    }

    function applySnapshot(data) {
        if (!data || !data.ok) return;

        if (data.empty) {
            window.location.reload();
            return;
        }

        var remaining = {};
        (data.items || []).forEach(function (item) {
            remaining[String(item.product_id)] = item;
        });

        document.querySelectorAll('.cart-item').forEach(function (row) {
            var item = remaining[row.getAttribute('data-product-id')];
            if (!item) {
                row.remove();
                return;
            }

            var qtyInput = row.querySelector('input[name="quantity"]');
            if (qtyInput && String(qtyInput.value) !== String(item.quantity)) {
                qtyInput.value = item.quantity;
            }

            var lineTotal = row.querySelector('.cart-item-line-total');
            if (lineTotal) {
                lineTotal.textContent = item.line_total_formatted;
            }
        });

        var subtotal = document.querySelector('[data-cart-subtotal]');
        var gst = document.querySelector('[data-cart-gst]');
        var total = document.querySelector('[data-cart-total]');
        if (subtotal) subtotal.textContent = data.subtotal_formatted;
        if (gst) gst.textContent = data.gst_formatted;
        if (total) total.textContent = data.total_formatted;

        updateCartBadge(data.cart_count);
    }

    function postForm(form) {
        if (form.classList.contains('is-busy')) return;

        var body = new FormData(form);
        body.append('ajax', '1');
        form.classList.add('is-busy');

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('request-failed');
            }
            return response.json();
        }).then(applySnapshot).catch(function () {
            form.submit();
        }).finally(function () {
            form.classList.remove('is-busy');
        });
    }

    function clampQuantity(value) {
        var qty = parseInt(value, 10);
        if (isNaN(qty)) qty = 1;
        return Math.max(1, Math.min(99, qty));
    }

    cartItems.addEventListener('click', function (event) {
        var minus = event.target.closest('.cart-qty-minus');
        var plus = event.target.closest('.cart-qty-plus');
        if (!minus && !plus) return;

        var form = event.target.closest('.cart-item-quantity-form');
        if (!form) return;

        event.preventDefault();
        var input = form.querySelector('input[name="quantity"]');
        if (!input) return;

        input.value = clampQuantity(clampQuantity(input.value) + (plus ? 1 : -1));
        postForm(form);
    });

    cartItems.addEventListener('change', function (event) {
        var input = event.target.closest('.cart-item-quantity-form input[name="quantity"]');
        if (!input) return;

        clearTimeout(qtyTimer);
        input.value = clampQuantity(input.value);
        postForm(input.closest('.cart-item-quantity-form'));
    });

    cartItems.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.classList.contains('cart-item-quantity-form') && !form.classList.contains('cart-item-remove-form')) {
            return;
        }

        event.preventDefault();
        if (form.classList.contains('cart-item-quantity-form')) {
            var input = form.querySelector('input[name="quantity"]');
            if (input) input.value = clampQuantity(input.value);
        }
        postForm(form);
    });
})();
