(function () {
    var wrapper = document.querySelector('.admin-wrapper');
    var toggle = document.getElementById('admin-menu-toggle');
    var closeBtn = document.getElementById('admin-sidebar-close');
    var backdrop = document.getElementById('admin-sidebar-backdrop');
    var collapseBtn = document.getElementById('admin-sidebar-collapse');
    var storageKey = 'moonaura-admin-sidebar-collapsed';

    if (!wrapper) {
        return;
    }

    function isMobile() {
        return window.matchMedia('(max-width: 980px)').matches;
    }

    function setOpen(open) {
        wrapper.classList.toggle('admin-sidebar-open', open);
        document.body.classList.toggle('admin-nav-locked', open && isMobile());
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }
    }

    function setCollapsed(collapsed) {
        wrapper.classList.toggle('admin-sidebar-collapsed', collapsed);
        if (collapseBtn) {
            collapseBtn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            collapseBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
        try {
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (e) {}
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setOpen(!wrapper.classList.contains('admin-sidebar-open'));
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            setOpen(false);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
    }

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            if (isMobile()) {
                return;
            }
            setCollapsed(!wrapper.classList.contains('admin-sidebar-collapsed'));
        });
    }

    var nav = document.querySelector('#admin-sidebar .admin-nav');
    if (nav) {
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a') && isMobile()) {
                setOpen(false);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            setOpen(false);
        }
    });

    try {
        if (!isMobile() && localStorage.getItem(storageKey) === '1') {
            setCollapsed(true);
        }
    } catch (e) {}
})();
