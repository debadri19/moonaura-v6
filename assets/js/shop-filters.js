/* ===================================================================
   #18 - SHOP FILTER POPUPS
   -------------------------------------------------------------------
   Replaces the old CSS-only invisible-<select> presentation on the
   Shop page with themed popup menus, while the actual filtering
   stays exactly as it was:

   - Each filter (.shop-filter) still contains its original <select>
     (assets/css/shop.css hides it visually via .shop-filter-native,
     it is never removed from the DOM).
   - Clicking a .shop-filter-option button here only sets that
     select's .value and calls the SAME #shopFilterForm.submit()
     the old inline onchange handlers used - shop.php's filter/query
     logic is untouched.
   - Deliberately isolated from main.js / cart-wishlist-ajax.js:
     this file only ever looks at clicks inside .shop-filter-btn /
     .shop-filter-popup elements and the Escape key, so it cannot
     interfere with the #21 Phase A cart/wishlist AJAX (form
     submits) or the #21 Phase B navigation loader (<a> clicks).
     Filter selection still triggers a normal full form submit/page
     navigation exactly as before, so the existing loader behaves
     unchanged for it.
   - If this file fails to load or JS is disabled, the <noscript>
     rules next to each <select> in shop.php restore the plain
     native dropdown as a working fallback.
=================================================================== */

function initShopFilters() {

    const filters = document.querySelectorAll(".shop-filter");

    if (!filters.length) return;

    const searchBox = document.querySelector(".shop-search-box");
    const searchInput = document.querySelector(".shop-search-box input");

    document.documentElement.classList.add("shop-filters-ready");

    let openFilter = null;

    function closeFilter(filterEl) {

        if (!filterEl) return;

        const btn = filterEl.querySelector(".shop-filter-btn");
        const popup = filterEl.querySelector(".shop-filter-popup");

        if (!btn || !popup) return;

        popup.hidden = true;
        btn.setAttribute("aria-expanded", "false");
        popup.style.left = "";
        popup.style.top = "";
        popup.style.maxHeight = "";
        const scrollEl = popup.querySelector(".shop-filter-popup-scroll");
        if (scrollEl) scrollEl.style.maxHeight = "";

        if (openFilter === filterEl) openFilter = null;

    }

    function positionPopup(filterEl, popup) {

        const btn = filterEl.querySelector(".shop-filter-btn");
        if (!btn) return;

        const margin = 12;
        const gap = 8;
        const btnRect = btn.getBoundingClientRect();

        popup.style.maxHeight = "";
        const scrollEl = popup.querySelector(".shop-filter-popup-scroll");
        if (scrollEl) scrollEl.style.maxHeight = "";

        const popupWidth = Math.min(popup.offsetWidth || 240, window.innerWidth - (margin * 2));
        const spaceBelow = window.innerHeight - btnRect.bottom - margin;
        const spaceAbove = btnRect.top - margin;
        const contentHeight = scrollEl ? scrollEl.scrollHeight : popup.scrollHeight;
        const desiredHeight = Math.min(contentHeight, 320);
        const openAbove = spaceBelow < Math.min(desiredHeight, 180) && spaceAbove > spaceBelow;
        const available = openAbove ? spaceAbove - gap : spaceBelow - gap;

        const nextMaxHeight = Math.max(120, Math.min(desiredHeight, available)) + "px";
        popup.style.maxHeight = nextMaxHeight;
        if (scrollEl) scrollEl.style.maxHeight = nextMaxHeight;

        let left = btnRect.left;
        if (left + popupWidth > window.innerWidth - margin) {
            left = window.innerWidth - popupWidth - margin;
        }
        if (left < margin) left = margin;

        let top = openAbove
            ? btnRect.top - gap - popup.offsetHeight
            : btnRect.bottom + gap;

        if (top < margin) top = margin;
        if (top + popup.offsetHeight > window.innerHeight - margin) {
            top = Math.max(margin, window.innerHeight - popup.offsetHeight - margin);
        }

        popup.style.left = left + "px";
        popup.style.top = top + "px";

    }

    function openFilterPopup(filterEl) {

        const btn = filterEl.querySelector(".shop-filter-btn");
        const popup = filterEl.querySelector(".shop-filter-popup");

        if (!btn || !popup) return;

        if (openFilter && openFilter !== filterEl) closeFilter(openFilter);

        popup.hidden = false;
        btn.setAttribute("aria-expanded", "true");
        openFilter = filterEl;

        positionPopup(filterEl, popup);

    }

    function isSearchTarget(target) {

        if (!target || !searchBox) return false;

        return searchBox.contains(target);

    }

    filters.forEach((filterEl) => {

        const btn = filterEl.querySelector(".shop-filter-btn");
        const popup = filterEl.querySelector(".shop-filter-popup");
        const select = filterEl.querySelector(".shop-filter-native");

        if (!btn || !popup || !select) return;

        select.tabIndex = -1;
        select.setAttribute("aria-hidden", "true");
        select.addEventListener("mousedown", (event) => event.preventDefault());
        select.addEventListener("click", (event) => event.preventDefault());
        select.addEventListener("focus", (event) => {
            event.preventDefault();
            select.blur();
        });

        btn.addEventListener("click", (event) => {

            event.preventDefault();
            event.stopPropagation();

            if (popup.hidden) {
                openFilterPopup(filterEl);
            } else {
                closeFilter(filterEl);
            }

        });

        popup.querySelectorAll(".shop-filter-option").forEach((option) => {

            option.addEventListener("click", (event) => {

                event.preventDefault();
                event.stopPropagation();

                const value = option.dataset.value;

                select.value = value;

                popup.querySelectorAll(".shop-filter-option").forEach((opt) => {
                    opt.classList.toggle("selected", opt === option);
                    opt.setAttribute("aria-selected", opt === option ? "true" : "false");
                });

                closeFilter(filterEl);

                const form = document.getElementById("shopFilterForm");
                if (form) form.submit();

            });

        });

    });

    if (searchBox) {
        searchBox.addEventListener("click", (event) => {
            event.stopPropagation();
            if (openFilter) closeFilter(openFilter);
        });
        searchBox.addEventListener("mousedown", (event) => {
            event.stopPropagation();
        });
    }

    if (searchInput) {
        searchInput.addEventListener("focus", () => {
            if (openFilter) closeFilter(openFilter);
        });
        searchInput.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && openFilter) {
                event.stopPropagation();
                closeFilter(openFilter);
            }
        });
    }

    document.addEventListener("click", (event) => {

        if (!openFilter) return;

        if (isSearchTarget(event.target)) {
            closeFilter(openFilter);
            return;
        }

        if (!openFilter.contains(event.target) && !openFilter.querySelector(".shop-filter-popup").contains(event.target)) {
            closeFilter(openFilter);
        }

    });

    document.addEventListener("keydown", (event) => {

        if (event.key !== "Escape") return;

        if (!openFilter) return;

        const btn = openFilter.querySelector(".shop-filter-btn");

        closeFilter(openFilter);

        if (btn) btn.focus();

    });

    window.addEventListener("resize", () => {

        if (!openFilter) return;

        const popup = openFilter.querySelector(".shop-filter-popup");
        if (popup) positionPopup(openFilter, popup);

    });

    window.addEventListener("scroll", () => {

        if (!openFilter) return;

        const popup = openFilter.querySelector(".shop-filter-popup");
        if (popup) positionPopup(openFilter, popup);

    }, { passive: true });

}

initShopFilters();
