/* ===================================================================
   THEME MANAGER — PHASE 1 FOUNDATION + PHASE 4 PERSISTENCE
   -------------------------------------------------------------------
   Light / Dark / System mode. Browser-side persistence via
   localStorage key "moonaura_theme". No account sync, no
   preload/no-flash behaviour.

   Source of truth: document.documentElement[data-theme]
     - "light"  → light tokens
     - "dark"   → dark tokens
     - "system" → follow prefers-color-scheme
     - unset    → Light Mode fallback (existing appearance)

   Selected mode is stored in localStorage as "light"|"dark"|"system".
   Missing, blocked, or malformed storage falls back to Light Mode
   without breaking the page.

   Resolved appearance is mirrored on data-theme-resolved="light|dark"
   for later phases. This file never restyles components.
=================================================================== */

(function (window, document) {
    'use strict';

    if (window.moonauraTheme) {
        return;
    }

    var ROOT = document.documentElement;
    var MODES = { light: true, dark: true, system: true };
    var STORAGE_KEY = 'moonaura_theme';
    var SYSTEM_QUERY = '(prefers-color-scheme: dark)';
    var media = null;
    var listening = false;

    function isMode(value) {
        return typeof value === 'string' && MODES[value] === true;
    }

    function readMode() {
        var value = ROOT.getAttribute('data-theme');
        return isMode(value) ? value : 'light';
    }

    function systemPrefersDark() {
        try {
            if (!media) {
                media = window.matchMedia(SYSTEM_QUERY);
            }
            return !!(media && media.matches);
        } catch (ignore) {
            return false;
        }
    }

    function resolve(mode) {
        var current = isMode(mode) ? mode : readMode();
        if (current === 'dark') {
            return 'dark';
        }
        if (current === 'system') {
            return systemPrefersDark() ? 'dark' : 'light';
        }
        return 'light';
    }

    function writeResolved(resolved) {
        ROOT.setAttribute('data-theme-resolved', resolved === 'dark' ? 'dark' : 'light');
    }

    function apply(mode) {
        var current = isMode(mode) ? mode : readMode();

        if (ROOT.getAttribute('data-theme') !== current) {
            ROOT.setAttribute('data-theme', current);
        }

        writeResolved(resolve(current));
        syncSystemListener(current);
        return current;
    }

    function onSystemChange() {
        if (readMode() !== 'system') {
            return;
        }
        writeResolved(resolve('system'));
    }

    function bindMediaListener(target) {
        if (!target) {
            return;
        }
        if (typeof target.addEventListener === 'function') {
            target.addEventListener('change', onSystemChange);
            return;
        }
        if (typeof target.addListener === 'function') {
            target.addListener(onSystemChange);
        }
    }

    function unbindMediaListener(target) {
        if (!target) {
            return;
        }
        if (typeof target.removeEventListener === 'function') {
            target.removeEventListener('change', onSystemChange);
            return;
        }
        if (typeof target.removeListener === 'function') {
            target.removeListener(onSystemChange);
        }
    }

    function syncSystemListener(mode) {
        var shouldListen = mode === 'system';
        if (shouldListen === listening) {
            return;
        }

        try {
            if (!media) {
                media = window.matchMedia(SYSTEM_QUERY);
            }
        } catch (ignore) {
            listening = false;
            return;
        }

        if (shouldListen) {
            bindMediaListener(media);
            listening = true;
            return;
        }

        unbindMediaListener(media);
        listening = false;
    }

    function readStoredMode() {
        try {
            var store = window.localStorage;
            if (!store || typeof store.getItem !== 'function') {
                return null;
            }
            var value = store.getItem(STORAGE_KEY);
            return isMode(value) ? value : null;
        } catch (ignore) {
            return null;
        }
    }

    function writeStoredMode(mode) {
        if (!isMode(mode)) {
            return;
        }
        try {
            var store = window.localStorage;
            if (!store || typeof store.setItem !== 'function') {
                return;
            }
            store.setItem(STORAGE_KEY, mode);
        } catch (ignore) {
        }
    }

    function getMode() {
        return readMode();
    }

    function syncToggle() {
        var doc = document;
        if (!doc || typeof doc.querySelectorAll !== 'function') {
            return;
        }

        var buttons;
        try {
            buttons = doc.querySelectorAll('.theme-toggle [data-theme-mode]');
        } catch (ignore) {
            return;
        }

        var mode = readMode();
        var i;
        for (i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var pressed = btn.getAttribute('data-theme-mode') === mode;
            btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        }
    }

    function onToggleClick(event) {
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
        if (!btn) {
            return;
        }
        if (typeof btn.closest === 'function' && !btn.closest('.theme-toggle')) {
            return;
        }

        if (event.preventDefault) {
            event.preventDefault();
        }

        setMode(btn.getAttribute('data-theme-mode'));
    }

    function bindToggle() {
        if (typeof document.addEventListener !== 'function') {
            return;
        }
        try {
            document.addEventListener('click', onToggleClick);
        } catch (ignore) {
        }
        syncToggle();
    }

    function setMode(mode) {
        if (!isMode(mode)) {
            return readMode();
        }
        var applied = apply(mode);
        writeStoredMode(applied);
        syncToggle();
        return applied;
    }

    function getResolvedTheme() {
        return resolve(readMode());
    }

    window.moonauraTheme = {
        modes: ['light', 'dark', 'system'],
        getMode: getMode,
        setMode: setMode,
        getResolvedTheme: getResolvedTheme,
        resolve: resolve,
        apply: apply
    };

    try {
        var stored = readStoredMode();
        if (stored) {
            apply(stored);
        } else if (ROOT.hasAttribute('data-theme')) {
            apply(readMode());
        }
    } catch (ignore) {
    }

    bindToggle();
})(window, document);
