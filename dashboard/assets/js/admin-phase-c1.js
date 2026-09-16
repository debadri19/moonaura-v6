(function () {
    /* ===================================================================
       #21 PHASE C1 - SAFE ADMIN AJAX
       -------------------------------------------------------------------
       Two independent, isolated features. Neither touches server-side
       business logic - each just calls the SAME endpoint the existing
       <form> already posts to (with the same fields, including the
       existing CSRF token input), reading the X-Requested-With header
       the endpoint now checks for to decide whether to reply with JSON
       instead of a redirect. If a request fails for any reason (network
       error, CSRF failure, session expired, unexpected response), the
       UI is left exactly where it was and a plain-language error is
       shown - never a silent "assume it worked".

       1. Order Status Update      (dashboard/order-detail.php)
       2. Product Image Set Primary (dashboard/product-form.php gallery)
    =================================================================== */

    // Reuses the existing admin-alert / admin-alert-success / admin-alert-error
    // classes already used for the page's flash messages, instead of
    // introducing new styling.
    function showFeedback(el, message, isError) {
        if (!el) {
            return;
        }
        el.textContent = message;
        el.hidden = false;
        el.classList.remove('admin-alert-success', 'admin-alert-error');
        el.classList.add('admin-alert', isError ? 'admin-alert-error' : 'admin-alert-success');
    }

    function parseJsonSafely(response) {
        return response.json().catch(function () {
            // Non-JSON response (e.g. a redirected login page after a
            // session timeout) - treat as a generic failure rather than
            // throwing something the .catch below can't describe.
            throw new Error('Unexpected response from server.');
        });
    }

    /* ===================================================================
       1. ORDER STATUS UPDATE
    =================================================================== */

    (function initOrderStatusUpdate() {
        var form = document.getElementById('order-status-form');
        if (!form) {
            return; // Not on dashboard/order-detail.php.
        }

        var select   = document.getElementById('order-status-select');
        var submit   = document.getElementById('order-status-submit');
        var badge    = document.getElementById('order-status-badge');
        var feedback = document.getElementById('order-status-feedback');

        var isSubmitting = false;

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (isSubmitting) {
                return; // Prevent duplicate submissions.
            }

            isSubmitting = true;
            submit.disabled = true;
            select.disabled = true;
            if (feedback) {
                feedback.hidden = true;
            }

            var formData = new FormData(form);

            fetch(form.action || window.location.href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
                .then(function (response) {
                    return parseJsonSafely(response).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    var data = result.data;

                    if (!result.ok || !data || !data.success) {
                        var message = (data && data.message) ? data.message : 'Could not update order status. Please try again.';
                        showFeedback(feedback, message, true);
                        return;
                    }

                    // Update the status badge in place.
                    if (badge) {
                        badge.className = 'admin-badge admin-badge-' + data.order_status;
                        badge.textContent = data.order_status_label;
                    }

                    showFeedback(feedback, data.message || 'Order status updated.', false);
                })
                .catch(function () {
                    // Restore UI state - do not assume success.
                    select.value = select.getAttribute('data-last-value') || select.value;
                    showFeedback(feedback, 'Could not update order status. Please check your connection and try again.', true);
                })
                .then(function () {
                    isSubmitting = false;
                    submit.disabled = false;
                    select.disabled = false;
                });
        });

        select.addEventListener('focus', function () {
            // Remember the value in case an in-flight request fails and
            // the select needs to be restored to what the server still has.
            select.setAttribute('data-last-value', select.value);
        });
    })();

    /* ===================================================================
       2. PRODUCT IMAGE SET PRIMARY
    =================================================================== */

    (function initProductImageSetPrimary() {
        var gallery = document.querySelector('.admin-image-gallery');
        if (!gallery) {
            return; // Not on dashboard/product-form.php, or no images yet.
        }

        var isSubmitting = false;

        gallery.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.classList.contains('js-set-primary-form')) {
                return;
            }

            event.preventDefault();

            if (isSubmitting) {
                return; // Prevent duplicate submissions.
            }

            var button = form.querySelector('button');

            isSubmitting = true;
            if (button) {
                button.disabled = true;
            }
            clearGalleryError();

            var formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
                .then(function (response) {
                    return parseJsonSafely(response).then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    var data = result.data;

                    if (!result.ok || !data || !data.success) {
                        var message = (data && data.message) ? data.message : 'Could not update primary image. Please try again.';
                        showGalleryError(message);
                        if (button) {
                            button.disabled = false;
                        }
                        return;
                    }

                    // Remove the primary marker/hide the "Set Primary"
                    // button from whichever item currently has it...
                    var items = gallery.querySelectorAll('.admin-image-gallery-item');
                    items.forEach(function (galleryItem) {
                        var isTarget = galleryItem.getAttribute('data-image-id') === String(data.image_id);
                        var galleryBadge = galleryItem.querySelector('.admin-image-primary-badge');
                        var setPrimaryForm = galleryItem.querySelector('.js-set-primary-form');
                        var setPrimaryButton = setPrimaryForm ? setPrimaryForm.querySelector('button') : null;

                        if (galleryBadge) {
                            galleryBadge.hidden = !isTarget;
                        }
                        if (setPrimaryForm) {
                            setPrimaryForm.hidden = isTarget;
                        }
                        if (setPrimaryButton) {
                            setPrimaryButton.disabled = false;
                        }
                    });
                })
                .catch(function () {
                    showGalleryError('Could not update primary image. Please check your connection and try again.');
                    if (button) {
                        button.disabled = false;
                    }
                })
                .then(function () {
                    isSubmitting = false;
                });
        });

        function clearGalleryError() {
            var existing = gallery.parentNode.querySelector('.js-set-primary-feedback');
            if (existing) {
                existing.remove();
            }
        }

        function showGalleryError(message) {
            clearGalleryError();
            var el = document.createElement('div');
            el.className = 'admin-alert admin-alert-error js-set-primary-feedback';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            el.textContent = message;
            gallery.insertAdjacentElement('afterend', el);
        }
    })();

    /* ===================================================================
       3. PRODUCT IMAGE PENDING SELECTION
       Local selected-file list only. Does not upload or imply a save.
    =================================================================== */

    (function initProductImagePending() {
        var input = document.getElementById('images');
        var pending = document.getElementById('admin-image-pending');
        if (!input || !pending) {
            return;
        }

        input.addEventListener('change', function () {
            pending.replaceChildren();

            var files = input.files;
            if (!files || files.length === 0) {
                pending.hidden = true;
                return;
            }

            for (var i = 0; i < files.length; i++) {
                var item = document.createElement('div');
                item.className = 'admin-image-pending-item';
                item.textContent = files[i].name + ' (selected, not saved yet)';
                pending.appendChild(item);
            }

            pending.hidden = false;
        });
    })();
})();
