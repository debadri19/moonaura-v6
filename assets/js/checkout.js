/* ===================================================================
   CHECKOUT PAGE JS
   -------------------------------------------------------------------
   Saved Address selector auto-fill. Reads the selected <option>'s
   data-* attributes (already HTML-escaped server-side via h() when
   the page was rendered - see checkout.php) and assigns them to the
   existing form fields' .value property. This is inherently safe
   against XSS regardless of what characters an address contains
   (quotes, ampersands, etc.): .dataset reads already-decoded text,
   and setting .value never interprets it as HTML/script - unlike
   innerHTML, which this file never uses.

   Selecting "+ Enter a new address" (the empty-value option) clears
   the address-related fields only - Full Name and Mobile Number are
   left as-is too (a customer might genuinely want to ship to a
   different address for the same name/phone, or might have already
   started typing a name before opening the dropdown) and Email is
   never touched by this script at all, in either direction - it
   stays tied to the logged-in customer's account, exactly as
   required.

   Any manual edit the customer makes AFTER picking a saved address is
   preserved as-is - this script only runs on the <select>'s own
   "change" event, so it never re-runs or overwrites anything unless
   the customer picks a different option again.

   The custom Saved Address panel is a presentation layer only: it
   reads options from #saved_address, writes the chosen value back
   to that select, and dispatches a bubbling "change" event so the
   auto-fill listener below remains the authority for form fields.
=================================================================== */

function initCheckoutSavedAddress() {

    const savedAddressSelect = document.getElementById('saved_address');

    if (!savedAddressSelect) {
        return;
    }

    if (savedAddressSelect.dataset.autofillBound !== '1') {
        savedAddressSelect.dataset.autofillBound = '1';

        const fieldMap = {
            fullName:     document.getElementById('full_name'),
            phone:        document.getElementById('mobile'),
            addressLine1: document.getElementById('address_line1'),
            addressLine2: document.getElementById('address_line2'),
            landmark:     document.getElementById('landmark'),
            city:         document.getElementById('city'),
            state:        document.getElementById('state'),
            pincode:      document.getElementById('pincode'),
        };

        savedAddressSelect.addEventListener('change', function () {

            const selectedOption = savedAddressSelect.options[savedAddressSelect.selectedIndex];

            if (!selectedOption || selectedOption.value === '') {
                if (fieldMap.addressLine1) fieldMap.addressLine1.value = '';
                if (fieldMap.addressLine2) fieldMap.addressLine2.value = '';
                if (fieldMap.landmark)     fieldMap.landmark.value = '';
                if (fieldMap.city)         fieldMap.city.value = '';
                if (fieldMap.state)        fieldMap.state.value = '';
                if (fieldMap.pincode)      fieldMap.pincode.value = '';
                return;
            }

            const data = selectedOption.dataset;

            if (fieldMap.fullName)     fieldMap.fullName.value = data.fullName || '';
            if (fieldMap.phone)        fieldMap.phone.value = data.phone || '';
            if (fieldMap.addressLine1) fieldMap.addressLine1.value = data.addressLine1 || '';
            if (fieldMap.addressLine2) fieldMap.addressLine2.value = data.addressLine2 || '';
            if (fieldMap.landmark)     fieldMap.landmark.value = data.landmark || '';
            if (fieldMap.city)         fieldMap.city.value = data.city || '';
            if (fieldMap.state)        fieldMap.state.value = data.state || '';
            if (fieldMap.pincode)      fieldMap.pincode.value = data.pincode || '';
        });
    }

    initCheckoutSavedAddressUi(savedAddressSelect);

}

function initCheckoutSavedAddressUi(select) {

    const wrap = select.closest('.checkout-saved-address');
    const trigger = document.getElementById('saved_address_trigger');
    const panel = document.getElementById('saved_address_listbox');
    const triggerText = trigger ? trigger.querySelector('.checkout-saved-address-trigger-text') : null;

    if (!wrap || !trigger || !panel || !triggerText) {
        return;
    }

    if (wrap.dataset.customUiBound === '1') {
        return;
    }

    wrap.dataset.customUiBound = '1';
    wrap.classList.add('is-ready');

    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.addEventListener('mousedown', function (event) { event.preventDefault(); });
    select.addEventListener('click', function (event) { event.preventDefault(); });
    select.addEventListener('focus', function () {
        select.blur();
        trigger.focus();
    });

    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-autocomplete', 'none');

    let activeIndex = 0;

    function optionNodes() {
        return Array.prototype.slice.call(panel.querySelectorAll('[role="option"]'));
    }

    function selectedIndex() {
        const value = select.value;
        const opts = Array.prototype.slice.call(select.options);
        const idx = opts.findIndex(function (opt) { return opt.value === value; });
        return idx < 0 ? 0 : idx;
    }

    function selectedLabel() {
        const opt = select.options[select.selectedIndex];
        return opt ? String(opt.textContent || '').trim() : '';
    }

    function syncTriggerLabel() {
        triggerText.textContent = selectedLabel() || '+ Enter a new address';
    }

    function isOpen() {
        return wrap.classList.contains('is-open');
    }

    function setActive(index) {
        const nodes = optionNodes();
        if (!nodes.length) {
            return;
        }

        const max = nodes.length - 1;
        activeIndex = Math.max(0, Math.min(max, index));

        nodes.forEach(function (node, i) {
            const on = i === activeIndex;
            node.classList.toggle('is-active', on);
            if (on) {
                trigger.setAttribute('aria-activedescendant', node.id);
                node.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function positionPanel() {
        panel.style.top = '';
        panel.style.bottom = '';
        panel.style.marginTop = '';
        panel.style.marginBottom = '';
        panel.style.maxHeight = '';

        const triggerRect = trigger.getBoundingClientRect();
        const margin = 12;
        const gap = 6;
        const spaceBelow = window.innerHeight - triggerRect.bottom - margin;
        const spaceAbove = triggerRect.top - margin;
        const contentHeight = panel.scrollHeight;
        const desired = Math.min(contentHeight, 240);
        const openAbove = spaceBelow < Math.min(desired, 160) && spaceAbove > spaceBelow;
        const available = (openAbove ? spaceAbove : spaceBelow) - gap;
        const nextMax = Math.max(120, Math.min(desired, available));

        panel.style.maxHeight = nextMax + 'px';

        if (openAbove) {
            panel.style.top = 'auto';
            panel.style.bottom = '100%';
            panel.style.marginBottom = gap + 'px';
            panel.style.marginTop = '0';
        } else {
            panel.style.top = '100%';
            panel.style.bottom = 'auto';
            panel.style.marginTop = gap + 'px';
            panel.style.marginBottom = '0';
        }
    }

    function closePanel() {
        if (!isOpen()) {
            return;
        }

        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
        trigger.removeAttribute('aria-activedescendant');
        panel.style.maxHeight = '';
        panel.style.top = '';
        panel.style.bottom = '';
        panel.style.marginTop = '';
        panel.style.marginBottom = '';
    }

    function openPanel() {
        if (isOpen()) {
            return;
        }

        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
        positionPanel();
        setActive(selectedIndex());
    }

    function togglePanel() {
        if (isOpen()) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function applyValue(value) {
        if (select.value !== value) {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        optionNodes().forEach(function (node) {
            const on = node.getAttribute('data-value') === select.value;
            node.classList.toggle('is-selected', on);
            node.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        syncTriggerLabel();
        closePanel();
        trigger.focus();
    }

    function buildOptions() {
        panel.textContent = '';

        const scroll = document.createElement('div');
        scroll.className = 'checkout-saved-address-scroll';

        Array.prototype.forEach.call(select.options, function (opt, i) {
            const node = document.createElement('div');
            node.className = 'checkout-saved-address-option';
            node.id = 'saved_address_opt_' + i;
            node.setAttribute('role', 'option');
            node.setAttribute('data-value', opt.value);
            node.textContent = String(opt.textContent || '').trim();

            const on = opt.value === select.value;
            node.classList.toggle('is-selected', on);
            node.setAttribute('aria-selected', on ? 'true' : 'false');

            node.addEventListener('mousedown', function (event) {
                event.preventDefault();
            });

            node.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                applyValue(opt.value);
            });

            scroll.appendChild(node);
        });

        panel.appendChild(scroll);
        syncTriggerLabel();
    }

    buildOptions();
    panel.setAttribute('aria-hidden', 'true');

    trigger.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        togglePanel();
    });

    trigger.addEventListener('blur', function () {
        window.setTimeout(function () {
            if (!wrap.contains(document.activeElement)) {
                closePanel();
            }
        }, 0);
    });

    trigger.addEventListener('keydown', function (event) {
        const key = event.key;

        if (key === 'ArrowDown' || key === 'ArrowUp') {
            event.preventDefault();
            if (!isOpen()) {
                openPanel();
                setActive(selectedIndex());
                return;
            }
            setActive(key === 'ArrowUp' ? activeIndex - 1 : activeIndex + 1);
            return;
        }

        if (key === 'Home') {
            if (!isOpen()) {
                return;
            }
            event.preventDefault();
            setActive(0);
            return;
        }

        if (key === 'End') {
            if (!isOpen()) {
                return;
            }
            event.preventDefault();
            setActive(optionNodes().length - 1);
            return;
        }

        if (key === 'Enter' || key === ' ') {
            if (!isOpen()) {
                return;
            }
            event.preventDefault();
            const current = optionNodes()[activeIndex];
            if (current) {
                applyValue(current.getAttribute('data-value') || '');
            }
            return;
        }

        if (key === 'Escape' && isOpen()) {
            event.preventDefault();
            closePanel();
        }
    });

    wrap._moonauraCloseSavedAddress = closePanel;
    wrap._moonauraPositionSavedAddress = positionPanel;
    wrap._moonauraSavedAddressTrigger = trigger;

    bindCheckoutSavedAddressDocumentEvents();
}

function bindCheckoutSavedAddressDocumentEvents() {

    if (window.__moonauraCheckoutSavedAddressEvents) {
        return;
    }

    window.__moonauraCheckoutSavedAddressEvents = true;

    function openWrap() {
        return document.querySelector('.checkout-saved-address.is-open');
    }

    document.addEventListener('click', function (event) {
        const wrap = openWrap();
        if (!wrap) {
            return;
        }
        if (!wrap.contains(event.target)) {
            if (typeof wrap._moonauraCloseSavedAddress === 'function') {
                wrap._moonauraCloseSavedAddress();
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        const wrap = openWrap();
        if (!wrap) {
            return;
        }
        if (typeof wrap._moonauraCloseSavedAddress === 'function') {
            wrap._moonauraCloseSavedAddress();
        }
        if (wrap._moonauraSavedAddressTrigger) {
            wrap._moonauraSavedAddressTrigger.focus();
        }
    });

    window.addEventListener('resize', function () {
        const wrap = openWrap();
        if (wrap && typeof wrap._moonauraPositionSavedAddress === 'function') {
            wrap._moonauraPositionSavedAddress();
        }
    });

    window.addEventListener('scroll', function () {
        const wrap = openWrap();
        if (wrap && typeof wrap._moonauraPositionSavedAddress === 'function') {
            wrap._moonauraPositionSavedAddress();
        }
    }, { passive: true });

    window.addEventListener('pagehide', function () {
        const wrap = openWrap();
        if (wrap && typeof wrap._moonauraCloseSavedAddress === 'function') {
            wrap._moonauraCloseSavedAddress();
        }
    });
}

function bootCheckoutSavedAddress() {
    initCheckoutSavedAddress();
}

window.initCheckoutSavedAddress = initCheckoutSavedAddress;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootCheckoutSavedAddress);
} else {
    bootCheckoutSavedAddress();
}
