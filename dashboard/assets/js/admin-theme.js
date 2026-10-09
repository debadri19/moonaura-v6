(function () {
    var STORAGE_KEY = 'moonaura_admin_theme';
    var ROOT = document.documentElement;
    var media = null;

    function isMode(value) {
        return value === 'light' || value === 'dark' || value === 'system';
    }

    function systemPrefersDark() {
        try {
            return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        } catch (ignore) {
            return false;
        }
    }

    function readMode() {
        var value = ROOT.getAttribute('data-admin-theme');
        if (isMode(value)) {
            return value;
        }
        try {
            value = localStorage.getItem(STORAGE_KEY);
        } catch (ignore) {
            value = null;
        }
        return isMode(value) ? value : 'light';
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

    function persist(mode) {
        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch (ignore) {}
        try {
            document.cookie = STORAGE_KEY + '=' + mode + '; path=/; max-age=31536000; SameSite=Lax';
        } catch (ignore) {}
    }

    function writeResolved(resolved) {
        ROOT.setAttribute('data-admin-theme-resolved', resolved === 'dark' ? 'dark' : 'light');
    }

    function syncButtons(mode) {
        var buttons = document.querySelectorAll('[data-admin-theme-mode]');
        for (var i = 0; i < buttons.length; i += 1) {
            var btn = buttons[i];
            var pressed = btn.getAttribute('data-admin-theme-mode') === mode;
            btn.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            if (pressed) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        }
    }

    function syncSystemListener(mode) {
        if (!window.matchMedia) {
            return;
        }
        if (!media) {
            media = window.matchMedia('(prefers-color-scheme: dark)');
            var onChange = function () {
                if (readMode() !== 'system') {
                    return;
                }
                writeResolved(resolve('system'));
            };
            if (typeof media.addEventListener === 'function') {
                media.addEventListener('change', onChange);
            } else if (typeof media.addListener === 'function') {
                media.addListener(onChange);
            }
        }
    }

    function apply(mode) {
        var current = isMode(mode) ? mode : readMode();
        if (ROOT.getAttribute('data-admin-theme') !== current) {
            ROOT.setAttribute('data-admin-theme', current);
        }
        writeResolved(resolve(current));
        syncButtons(current);
        syncSystemListener(current);
        return current;
    }

    function setMode(mode) {
        var current = isMode(mode) ? mode : 'light';
        persist(current);
        apply(current);
        return current;
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        var btn = target.closest('[data-admin-theme-mode]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        setMode(btn.getAttribute('data-admin-theme-mode'));
    });

    apply(readMode());

    window.moonauraAdminTheme = {
        getMode: readMode,
        setMode: setMode,
        resolve: resolve
    };
})();
