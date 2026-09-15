/* ===================================================================
   #21 PHASE A - CART / WISHLIST AJAX
   -------------------------------------------------------------------
   Upgrades existing form-post-and-redirect actions to AJAX,
   without touching their PHP business logic:
     - wishlist-form           -> wishlist-toggle.php
     - cart-item-remove-form   -> cart-remove.php
     - cart-item-quantity-form -> cart-update.php
     - Add to Cart forms       -> cart-add.php (matched by action)

   Every endpoint still supports a plain (non-JS) form submission
   exactly as before - this file only intercepts the submit when
   fetch() is available and short-circuits to JSON. If fetch() is
   unavailable, or any request errors out, the customer can simply
   submit the form again (a real POST) and get the original
   redirect-based behaviour - nothing here removes or disables the
   underlying endpoints.

   Server responses are always treated as authoritative - quantities,
   totals, and counts shown here come from the JSON the server sends
   back, never recomputed on the client.
=================================================================== */

(function () {
    if (window.__moonauraCartWishlistAjaxInit) {
        return;
    }
    window.__moonauraCartWishlistAjaxInit = true;

    initWishlistAjax();
    initCartRemoveAjax();
    initCartQuantityAjax();
    initCartAddAjax();
})();


/* ==========================================
   SHARED HELPERS
========================================== */

function isAjaxCapable() {
    return typeof window.fetch === 'function';
}

function isTargetAjaxForm(form, className) {
    return !!(form && form.classList && form.classList.contains(className));
}

function cartWishlistAjaxPost(url, formData) {
    if (!formData.has('ajax')) {
        formData.append('ajax', '1');
    }

    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    }).then(function (response) {
        return response.text().then(function (raw) {
            var contentType = response.headers.get('Content-Type') || '';
            var data = null;

            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (parseError) {
                data = null;
            }

            if (!response.ok || !data) {
                throw new Error('Request failed');
            }

            if (contentType.indexOf('application/json') === -1 && typeof data.success === 'undefined') {
                throw new Error('Request failed');
            }

            return data;
        });
    });
}

function formActionUrl(form) {
    if (!form) {
        return '';
    }

    if (form.action) {
        return form.action;
    }

    return form.getAttribute('action') || window.location.href;
}

function bindAjaxFormMatcher(formMatches, onSubmit) {
    if (!isAjaxCapable()) {
        return;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!formMatches(form)) {
            return;
        }

        event.preventDefault();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        } else {
            event.stopPropagation();
        }

        onSubmit(form);
    }, true);

    document.addEventListener('click', function (event) {
        var clicked = event.target;

        if (!clicked || typeof clicked.closest !== 'function') {
            return;
        }

        var button = clicked.closest('button');

        if (!button) {
            return;
        }

        var buttonType = (button.getAttribute('type') || 'submit').toLowerCase();

        if (buttonType !== 'submit') {
            return;
        }

        var form = button.form || button.closest('form');

        if (!formMatches(form)) {
            return;
        }

        event.preventDefault();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        } else {
            event.stopPropagation();
        }

        onSubmit(form);
    }, true);
}

function bindAjaxForm(className, onSubmit) {
    bindAjaxFormMatcher(function (form) {
        return isTargetAjaxForm(form, className);
    }, onSubmit);
}

function isCartAddForm(form) {
    if (!form || form.tagName !== 'FORM') {
        return false;
    }

    if (
        isTargetAjaxForm(form, 'wishlist-form') ||
        isTargetAjaxForm(form, 'cart-item-remove-form') ||
        isTargetAjaxForm(form, 'cart-item-quantity-form')
    ) {
        return false;
    }

    var declaredAction = form.getAttribute('action') || '';
    var resolvedAction = form.action || '';

    return declaredAction.indexOf('cart-add.php') !== -1 || resolvedAction.indexOf('cart-add.php') !== -1;
}

function updateHeaderBadge(linkSelector, count) {
    var link = document.querySelector(linkSelector);

    if (!link) {
        return;
    }

    var badge = link.querySelector('.cart-count-badge');

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'cart-count-badge';
            link.appendChild(badge);
        }
        badge.textContent = count;
    } else if (badge) {
        badge.remove();
    }
}

function showInlineError(container, message) {
    if (!container) {
        return;
    }

    var existing = container.querySelector('.cart-wishlist-ajax-error');
    if (existing) {
        existing.remove();
    }

    var alertEl = document.createElement('div');
    alertEl.className = 'cart-alert cart-alert-error cart-wishlist-ajax-error';
    alertEl.textContent = message;
    container.insertBefore(alertEl, container.firstChild);

    window.setTimeout(function () {
        alertEl.remove();
    }, 5000);
}

var GENERIC_ERROR_MESSAGE = 'Something went wrong. Please try again.';


/* ==========================================
   1. WISHLIST TOGGLE
========================================== */

function initWishlistAjax() {
    bindAjaxForm('wishlist-form', submitWishlistForm);
}

function submitWishlistForm(form) {
    if (!form || form.dataset.ajaxBusy === '1') {
        return;
    }

    form.dataset.ajaxBusy = '1';

    var button = form.querySelector('button');
    var icon   = button ? button.querySelector('i') : null;
    var wasActive = button ? button.classList.contains('active') : false;
    var formData = new FormData(form);

    if (button) {
        button.disabled = true;
    }
    if (icon) {
        icon.classList.add('fa-spin');
    }

    cartWishlistAjaxPost(formActionUrl(form), formData)
        .then(function (data) {
            if (!data || !data.success) {
                throw new Error('Unexpected response');
            }

            applyWishlistResult(form, data);

            if (data.in_wishlist && data.item && window.moonauraGa4 && typeof window.moonauraGa4.event === 'function') {
                window.moonauraGa4.event('add_to_wishlist', {
                    currency: window.moonauraGa4.currency(),
                    value: Number(data.item.price) || 0,
                    items: [data.item]
                });
            }

            // Meta Phase 2 - AddToWishlist only when this successful
            // response actually added the item (never on removal).
            if (data.in_wishlist && data.item && window.moonauraMeta && typeof window.moonauraMeta.product === 'function') {
                window.moonauraMeta.product('AddToWishlist', [data.item], { contents: false });
            }
        })
        .catch(function () {
            if (icon) {
                icon.classList.remove('fa-spin');
                icon.classList.toggle('fa-solid', wasActive);
                icon.classList.toggle('fa-regular', !wasActive);
            }
            showInlineError(
                document.querySelector('.wishlist-page .container') ||
                document.querySelector('.container'),
                GENERIC_ERROR_MESSAGE
            );
        })
        .finally(function () {
            form.dataset.ajaxBusy = '0';
            if (button) {
                button.disabled = false;
            }
        });
}

function applyWishlistResult(form, data) {
    var isWishlistPage = !!document.querySelector('.wishlist-page');

    updateHeaderBadge('a.header-icon[aria-label="Wishlist"]', data.wishlist_count);

    if (isWishlistPage) {
        var card = form.closest('.product-card');
        if (card) {
            card.remove();
        }

        var countText = document.querySelector('.wishlist-count-text');

        if (data.wishlist_count > 0) {
            if (countText) {
                countText.textContent =
                    data.wishlist_count + ' saved item' + (data.wishlist_count === 1 ? '' : 's');
            }
        } else {
            var grid = document.querySelector('.product-grid');
            if (countText) {
                countText.remove();
            }
            if (grid) {
                grid.outerHTML =
                    '<div class="empty-state wishlist-empty-state">' +
                    '<span class="wishlist-empty-icon" aria-hidden="true">' +
                    '<i class="fa-solid fa-heart"></i>' +
                    '</span>' +
                    '<p>Your wishlist is empty.</p>' +
                    '<span class="wishlist-empty-divider" aria-hidden="true"></span>' +
                    '<a href="shop.php" class="btn btn-primary">Continue Shopping</a>' +
                    '</div>';
            }
        }

        return;
    }

    var button = form.querySelector('button');
    var icon   = button ? button.querySelector('i') : null;

    if (button) {
        button.classList.toggle('active', data.in_wishlist);
        button.setAttribute('aria-label', data.in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist');
    }
    if (icon) {
        icon.classList.remove('fa-spin');
        icon.classList.toggle('fa-solid', data.in_wishlist);
        icon.classList.toggle('fa-regular', !data.in_wishlist);
    }
}


/* ==========================================
   2. CART REMOVE
========================================== */

function initCartRemoveAjax() {
    bindAjaxForm('cart-item-remove-form', submitCartRemoveForm);
}

function submitCartRemoveForm(form) {
    if (!form || form.dataset.ajaxBusy === '1') {
        return;
    }

    form.dataset.ajaxBusy = '1';

    var button = form.querySelector('button');
    var icon   = button ? button.querySelector('i') : null;
    var formData = new FormData(form);

    if (button) {
        button.disabled = true;
    }
    if (icon) {
        icon.classList.add('fa-spin');
    }

    cartWishlistAjaxPost(formActionUrl(form), formData)
        .then(function (data) {
            if (!data || !data.success) {
                throw new Error('Unexpected response');
            }

            var cartItemRow = form.closest('.cart-item');
            if (cartItemRow) {
                cartItemRow.remove();
            }

            updateCartTotals(data);
            updateHeaderBadge('a.cart-float[aria-label="Shopping Cart"]', data.cart_count);

            if (data.item && window.moonauraGa4 && typeof window.moonauraGa4.event === 'function') {
                window.moonauraGa4.event('remove_from_cart', {
                    currency: window.moonauraGa4.currency(),
                    value: (Number(data.item.price) || 0) * (Number(data.item.quantity) || 1),
                    items: [data.item]
                });
            }

            // Meta Phase 2 - RemoveFromCart only after a successful
            // removal response.
            if (data.item && window.moonauraMeta && typeof window.moonauraMeta.product === 'function') {
                window.moonauraMeta.product('RemoveFromCart', [data.item]);
            }

            if (data.is_empty) {
                var layout = document.querySelector('.cart-layout');
                if (layout) {
                    layout.outerHTML =
                        '<div class="empty-state">' +
                        '<i class="fa-solid fa-cart-shopping"></i>' +
                        '<p>Your cart is empty.</p>' +
                        '<a href="shop.php" class="btn btn-primary">Continue Shopping</a>' +
                        '</div>';
                }
            }
        })
        .catch(function () {
            if (icon) {
                icon.classList.remove('fa-spin');
            }
            showInlineError(document.querySelector('.cart-page .container'), GENERIC_ERROR_MESSAGE);
        })
        .finally(function () {
            form.dataset.ajaxBusy = '0';
            if (button) {
                button.disabled = false;
            }
        });
}


/* ==========================================
   3. CART QUANTITY UPDATE
========================================== */

function initCartQuantityAjax() {
    bindAjaxForm('cart-item-quantity-form', submitCartQuantityForm);
}

function submitCartQuantityForm(form) {
    if (!form || form.dataset.ajaxBusy === '1') {
        return;
    }

    form.dataset.ajaxBusy = '1';

    var button        = form.querySelector('button[type="submit"]');
    var quantityInput = form.querySelector('input[name="quantity"]');
    var originalLabel = button ? button.textContent : '';
    var formData      = new FormData(form);

    if (button) {
        button.disabled = true;
        button.textContent = 'Updating…';
    }
    if (quantityInput) {
        quantityInput.disabled = true;
    }

    cartWishlistAjaxPost(formActionUrl(form), formData)
        .then(function (data) {
            if (!data || !data.success) {
                throw new Error('Unexpected response');
            }

            if (quantityInput) {
                quantityInput.value = data.quantity;
            }

            var cartItemRow = form.closest('.cart-item');
            if (cartItemRow) {
                var lineTotalEl = cartItemRow.querySelector('.cart-item-line-total');
                if (lineTotalEl) {
                    lineTotalEl.textContent = data.line_total;
                }
            }

            updateCartTotals(data);
            updateHeaderBadge('a.cart-float[aria-label="Shopping Cart"]', data.cart_count);
        })
        .catch(function () {
            showInlineError(document.querySelector('.cart-page .container'), GENERIC_ERROR_MESSAGE);
        })
        .finally(function () {
            form.dataset.ajaxBusy = '0';
            if (button) {
                button.disabled = false;
                button.textContent = originalLabel;
            }
            if (quantityInput) {
                quantityInput.disabled = false;
            }
        });
}


/* ==========================================
   SHARED: CART SUMMARY UPDATE
   (subtotal / GST / total) - used by both
   Cart Remove and Cart Quantity Update, since
   both actions can change all three.
========================================== */

function showCartAddFeedback(message, isSuccess) {
    var existing = document.getElementById('cartAddAjaxToast');
    if (existing) {
        existing.remove();
    }

    var toast = document.createElement('div');
    toast.id = 'cartAddAjaxToast';
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    toast.style.position = 'fixed';
    toast.style.left = '50%';
    toast.style.top = '88px';
    toast.style.transform = 'translateX(-50%)';
    toast.style.zIndex = '10050';
    toast.style.maxWidth = 'min(90vw, 420px)';
    toast.style.padding = '14px 22px';
    toast.style.borderRadius = '999px';
    toast.style.fontFamily = 'Poppins, sans-serif';
    toast.style.fontSize = '14px';
    toast.style.fontWeight = '500';
    toast.style.textAlign = 'center';
    toast.style.boxShadow = '0 10px 28px rgba(91, 46, 145, 0.18)';
    toast.style.pointerEvents = 'none';

    if (isSuccess) {
        toast.style.background = '#5B2E91';
        toast.style.color = '#ffffff';
        toast.style.border = '1px solid #43206D';
    } else {
        toast.style.background = '#fdecea';
        toast.style.color = '#b3261e';
        toast.style.border = '1px solid #f6c6c2';
    }

    document.body.appendChild(toast);

    window.setTimeout(function () {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 3200);
}


/* ==========================================
   4. ADD TO CART
   Matched by the existing cart-add.php action so
   Home / Shop / Product / Wishlist / Concern all
   work despite mixed button markup and
   display:contents forms. Buy Now is left alone.
========================================== */

function initCartAddAjax() {
    bindAjaxFormMatcher(isCartAddForm, submitCartAddForm);
}

function submitCartAddForm(form) {
    if (!form || form.dataset.ajaxBusy === '1') {
        return;
    }

    form.dataset.ajaxBusy = '1';

    var button = form.querySelector('button[type="submit"]') || form.querySelector('button');
    var icon   = button ? button.querySelector('i') : null;
    var formData = new FormData(form);

    if (button) {
        button.disabled = true;
    }
    if (icon) {
        icon.classList.add('fa-spin');
    }

    cartWishlistAjaxPost(formActionUrl(form), formData)
        .then(function (data) {
            if (!data || !data.success) {
                showCartAddFeedback(
                    (data && data.message) || GENERIC_ERROR_MESSAGE,
                    false
                );
                return;
            }

            updateHeaderBadge('a.cart-float[aria-label="Shopping Cart"]', data.cart_count);
            showCartAddFeedback(data.message || 'Added to your cart.', true);

            if (data.item && window.moonauraGa4 && typeof window.moonauraGa4.event === 'function') {
                window.moonauraGa4.event('add_to_cart', {
                    currency: window.moonauraGa4.currency(),
                    value: (Number(data.item.price) || 0) * (Number(data.item.quantity) || 1),
                    items: [data.item]
                });
            }

            // Meta Phase 2 - AddToCart only after the server confirmed
            // the add (this branch is only reached when data.success is
            // true; failures fall through to the error feedback path).
            if (data.item && window.moonauraMeta && typeof window.moonauraMeta.product === 'function') {
                window.moonauraMeta.product('AddToCart', [data.item]);
            }
        })
        .catch(function () {
            showCartAddFeedback(GENERIC_ERROR_MESSAGE, false);
        })
        .finally(function () {
            form.dataset.ajaxBusy = '0';
            if (icon) {
                icon.classList.remove('fa-spin');
            }
            if (button) {
                button.disabled = false;
            }
        });
}

function updateCartTotals(data) {
    var subtotalEl = document.getElementById('cartSubtotalValue');
    var gstEl      = document.getElementById('cartGstValue');
    var totalEl    = document.getElementById('cartTotalValue');

    if (subtotalEl && typeof data.subtotal !== 'undefined') {
        subtotalEl.textContent = data.subtotal;
    }
    if (gstEl && typeof data.gst_amount !== 'undefined') {
        gstEl.textContent = data.gst_amount;
    }
    if (totalEl && typeof data.total !== 'undefined') {
        totalEl.textContent = data.total;
    }
}
