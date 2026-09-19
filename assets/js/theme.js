/* ===================================================================
   THEME MANAGER — PHASE 1 FOUNDATION
   -------------------------------------------------------------------
   Light / Dark / System mode only. No UI, no localStorage, no
   account persistence, no preload/no-flash behaviour.

   Source of truth: document.documentElement[data-theme]
     - "light"  → light tokens
     - "dark"   → dark tokens
     - "system" → follow prefers-color-scheme
     - unset    → Light Mode fallback (existing appearance)

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

    function getMode() {
        return readMode();
    }

    function setMode(mode) {
        if (!isMode(mode)) {
            return readMode();
        }
        return apply(mode);
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

    if (ROOT.hasAttribute('data-theme')) {
        apply(readMode());
    }
})(window, document);
