(function () {
    var editor = document.getElementById('newsletter-editor');
    var bodyField = document.getElementById('campaign-body');
    var form = document.getElementById('newsletter-campaign-form');
    var imageInput = document.getElementById('newsletter-image-input');
    var csrf = form ? form.querySelector('input[name="csrf_token"]') : null;

    if (!editor || !bodyField || !form) {
        return;
    }

    function syncBody() {
        bodyField.value = editor.innerHTML;
    }

    function insertHtml(html) {
        editor.focus();
        document.execCommand('insertHTML', false, html);
        syncBody();
    }

    form.addEventListener('submit', function (event) {
        syncBody();
        var submitter = event.submitter || document.activeElement;
        if (submitter && submitter.getAttribute && submitter.getAttribute('data-send-confirm')) {
            if (!window.confirm(submitter.getAttribute('data-send-confirm'))) {
                event.preventDefault();
            }
        }
    });
    editor.addEventListener('input', syncBody);
    editor.addEventListener('blur', syncBody);

    document.querySelectorAll('[data-editor-cmd]').forEach(function (button) {
        button.addEventListener('click', function () {
            var cmd = button.getAttribute('data-editor-cmd');
            var value = button.getAttribute('data-editor-value') || null;
            editor.focus();
            if (cmd === 'createLink') {
                var url = window.prompt('Link URL', 'https://');
                if (!url || !/^(https?:\/\/|mailto:)/i.test(url)) {
                    return;
                }
                document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, value);
            } else {
                document.execCommand(cmd, false, value);
            }
            syncBody();
        });
    });

    var firstNameBtn = document.getElementById('newsletter-insert-first-name');
    if (firstNameBtn) {
        firstNameBtn.addEventListener('click', function () {
            insertHtml('{{first_name}}');
        });
    }

    var ctaBtn = document.getElementById('newsletter-insert-cta');
    if (ctaBtn) {
        ctaBtn.addEventListener('click', function () {
            var text = (document.getElementById('campaign-cta-text') || {}).value || 'Shop Now';
            var url = (document.getElementById('campaign-cta-url') || {}).value || '#';
            insertHtml(
                '<p style="text-align:center;margin:24px 0;">'
                + '<a href="' + url.replace(/"/g, '&quot;') + '" style="display:inline-block;background:#5B2E91;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600;">'
                + text.replace(/</g, '&lt;')
                + '</a></p>'
            );
        });
    }

    var imageBtn = document.getElementById('newsletter-insert-image');
    if (imageBtn && imageInput) {
        imageBtn.addEventListener('click', function () {
            imageInput.click();
        });

        imageInput.addEventListener('change', function () {
            if (!imageInput.files || !imageInput.files[0] || !csrf) {
                return;
            }
            var data = new FormData();
            data.append('csrf_token', csrf.value);
            data.append('image', imageInput.files[0]);

            fetch('newsletter-image-upload.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (!payload || !payload.ok || !payload.url) {
                    window.alert((payload && payload.error) ? payload.error : 'Could not upload the image.');
                    return;
                }
                insertHtml('<p style="text-align:center;"><img src="' + payload.url.replace(/"/g, '&quot;') + '" alt="" style="max-width:100%;height:auto;border-radius:8px;"></p>');
            }).catch(function () {
                window.alert('Could not upload the image.');
            }).finally(function () {
                imageInput.value = '';
            });
        });
    }
})();
