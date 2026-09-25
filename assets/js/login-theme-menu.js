(function () {
    'use strict';

    var menu = document.querySelector('.login-theme-menu');
    if (!menu) {
        return;
    }

    var btn = menu.querySelector('.login-theme-menu-btn');
    var panel = menu.querySelector('.login-theme-menu-panel');
    var icon = btn ? btn.querySelector('i') : null;

    if (!btn || !panel || !icon) {
        return;
    }

    var ICONS = {
        light: 'fa-sun',
        dark: 'fa-moon',
        system: 'fa-circle-half-stroke'
    };

    function currentMode() {
        if (window.moonauraTheme && typeof window.moonauraTheme.getMode === 'function') {
            return window.moonauraTheme.getMode();
        }
        return 'light';
    }

    function syncTrigger() {
        var mode = currentMode();
        var iconClass = ICONS[mode] || ICONS.light;
        icon.className = 'fa-solid ' + iconClass;
        btn.setAttribute('aria-label', 'Color theme, ' + mode);
    }

    function isOpen() {
        return menu.classList.contains('is-open');
    }

    function openMenu() {
        menu.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        panel.removeAttribute('hidden');
    }

    function closeMenu() {
        if (!isOpen()) {
            return;
        }
        menu.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        panel.setAttribute('hidden', '');
    }

    function toggleMenu() {
        if (isOpen()) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    btn.addEventListener('click', function (event) {
        event.preventDefault();
        toggleMenu();
    });

    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) {
            closeMenu();
            btn.focus();
        }
    });

    panel.addEventListener('click', function (event) {
        if (!event.target || typeof event.target.closest !== 'function') {
            return;
        }
        if (!event.target.closest('[data-theme-mode]')) {
            return;
        }
        window.setTimeout(function () {
            syncTrigger();
            closeMenu();
            btn.focus();
        }, 0);
    });

    syncTrigger();
})();
