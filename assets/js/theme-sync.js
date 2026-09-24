/* ===================================================================
   THEME ACCOUNT SYNC — PHASE 5
   -------------------------------------------------------------------
   Thin authenticated layer around the existing theme manager.
   Immediate UI / localStorage still belong to assets/js/theme.js.
   This file POSTs the selected mode for a logged-in customer after
   the manager applies it. Guests never load this file.
=================================================================== */

(function (window, document) {
    'use strict';

    if (window.__moonauraThemeSyncInit) {
        return;
    }

    var theme = window.moonauraTheme;
    var cfg = window.moonauraThemeSync;

    if (!theme || typeof theme.setMode !== 'function' || typeof theme.getMode !== 'function') {
        return;
    }

    if (!cfg || typeof cfg.url !== 'string' || typeof cfg.csrf !== 'string' || !cfg.url || !cfg.csrf) {
        return;
    }

    window.__moonauraThemeSyncInit = true;

    var originalSetMode = theme.setMode;

    function persist(mode) {
        if (mode !== 'light' && mode !== 'dark' && mode !== 'system') {
            return;
        }

        if (typeof window.fetch !== 'function') {
            return;
        }

        var body;
        try {
            body = new window.FormData();
            body.append('csrf_token', cfg.csrf);
            body.append('theme', mode);
            body.append('ajax', '1');
        } catch (ignore) {
            return;
        }

        window.fetch(cfg.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: body
        }).catch(function () {});
    }

    theme.setMode = function (mode) {
        var applied = originalSetMode.call(theme, mode);
        persist(applied);
        return applied;
    };

    if (typeof document.addEventListener === 'function') {
        document.addEventListener('click', function (event) {
            var target = event.target;
            if (!target) {
                return;
            }
            if (typeof target.closest !== 'function') {
                target = target.parentElement;
                if (!target || typeof target.closest !== 'function') {
                    return;
                }
            }

            var btn = target.closest('[data-theme-mode]');
            if (!btn || !btn.closest('.theme-toggle')) {
                return;
            }

            persist(theme.getMode());
        });
    }
})(window, document);
