/* ===================================================================
   01. HEADER/FOOTER ARE NOW SERVER-RENDERED
   -------------------------------------------------------------------
   Header and footer used to be loaded here with fetch(), from
   components/header.html and components/footer.html.

   They are now included directly by PHP (see includes/header.php and
   includes/footer.php), so by the time this script runs the header
   is already in the page - we just need to wire up its behaviour.
=================================================================== */

initHeader();
initHeaderSearch();
initBackToTop();
initNavigationLoader();
initAccountNav();

/* ===================================================================
   02. HEADER
=================================================================== */

function initHeader(){

    const menuToggle = document.querySelector(".menu-toggle");

    const menuClose = document.querySelector(".menu-close");

    const mobileMenu = document.querySelector(".mobile-menu");

    const menuOverlay = document.querySelector(".menu-overlay");

    const mobileDropBtns = document.querySelectorAll(".mobile-drop-btn");

    function bindPoliciesToggle(toggle){

        const submenuId = toggle.getAttribute("aria-controls");

        const submenu = submenuId ? document.getElementById(submenuId) : null;

        if(!submenu) return;

        toggle.addEventListener("click",(event)=>{

            event.preventDefault();

            event.stopPropagation();

            const isOpen = submenu.classList.toggle("active");

            toggle.classList.toggle("active", isOpen);

            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");

        });

        return { toggle, submenu };

    }

    const desktopPoliciesToggle = document.querySelector(".dropdown-policies-toggle");

    const desktopPolicies = desktopPoliciesToggle ? bindPoliciesToggle(desktopPoliciesToggle) : null;

    const mobilePoliciesToggle = document.querySelector(".mobile-policies-toggle");

    const mobilePolicies = mobilePoliciesToggle ? bindPoliciesToggle(mobilePoliciesToggle) : null;

    function collapsePoliciesMenu(policies){

        if(!policies) return;

        policies.submenu.classList.remove("active");

        policies.toggle.classList.remove("active");

        policies.toggle.setAttribute("aria-expanded", "false");

    }

    const helpDropdown = document.querySelector(".navbar .dropdown");

    if(helpDropdown){

        helpDropdown.addEventListener("mouseleave",()=>{

            collapsePoliciesMenu(desktopPolicies);

        });

    }

    if(!menuToggle) return;

    menuToggle.addEventListener("click",()=>{

        mobileMenu.classList.add("active");

        menuOverlay.classList.add("active");

        document.body.style.overflow="hidden";

    });

    menuClose.addEventListener("click",closeMenu);

    menuOverlay.addEventListener("click",closeMenu);

    function closeMenu(){

        mobileMenu.classList.remove("active");

        menuOverlay.classList.remove("active");

        document.body.style.overflow="";

        collapsePoliciesMenu(mobilePolicies);

    }

    mobileDropBtns.forEach((mobileDropBtn)=>{

        const mobileDropdown = mobileDropBtn.nextElementSibling;

        if(!mobileDropdown || !mobileDropdown.classList.contains("mobile-dropdown-content")) return;

        mobileDropBtn.addEventListener("click",()=>{

            const isOpen = mobileDropdown.classList.toggle("active");

            mobileDropBtn.classList.toggle("active", isOpen);

            mobileDropBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");

            if(!isOpen){

                // Freeze the panel at its real rendered height before it
                // collapses, so max-height animates over the visible content
                // instead of its oversized 800px open cap. Without this the
                // close sits still (no size change) and then snaps shut,
                // which reads as a stuck/choppy animation.
                mobileDropdown.style.maxHeight = mobileDropdown.scrollHeight + "px";

                void mobileDropdown.offsetHeight;

                mobileDropdown.style.maxHeight = "";

                collapsePoliciesMenu(mobilePolicies);

            }

        });

    });

    document.addEventListener("keydown",(event)=>{

        if(event.key === "Escape"){

            closeMenu();

        }

    });

    // =========================
    // HEADER SCROLL EFFECT
    // =========================

    window.addEventListener("scroll",()=>{

        const header = document.querySelector(".site-header");

        if(!header) return;

        header.classList.toggle("scrolled",window.scrollY > 20);

    });

}

/* ===================================================================
   02b. ACCOUNT NAV DROPDOWN
   Native <details> menus stay as-is. Close any open Account nav when
   the pointer lands outside it (desktop click and mobile tap).
=================================================================== */

function initAccountNav(){

    const menus = document.querySelectorAll(".account-nav-menu");

    if(!menus.length) return;

    function syncAccountNavState(menu){

        const isOpen = !!menu.open;
        const toggle = menu.querySelector(".account-nav-toggle");

        if(toggle){
            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        }

    }

    function restartAccountNavPanelAnimation(menu){

        const panel = menu.querySelector(".account-nav-panel");

        if(!panel) return;

        panel.classList.remove("account-nav-panel-in");

        if(!menu.open) return;

        void panel.offsetWidth;
        panel.classList.add("account-nav-panel-in");

    }

    function closeAccountNav(menu){

        if(!menu.open) return;

        menu.open = false;
        syncAccountNavState(menu);
        restartAccountNavPanelAnimation(menu);

    }

    menus.forEach((menu)=>{

        syncAccountNavState(menu);

        menu.addEventListener("toggle",()=>{

            syncAccountNavState(menu);
            restartAccountNavPanelAnimation(menu);

        });

    });

    document.addEventListener("pointerdown",(event)=>{

        menus.forEach((menu)=>{

            if(menu.open && !menu.contains(event.target)){
                closeAccountNav(menu);
            }

        });

    });

    document.addEventListener("keydown",(event)=>{

        if(event.key !== "Escape") return;

        menus.forEach((menu)=>{

            closeAccountNav(menu);

        });

    });

}

/* ===================================================================
   02a. HEADER FLOATING SEARCH
   Opens a search panel under the header. Submits GET to shop.php?q=
   (the existing shop search). Does not navigate on icon click.
=================================================================== */

function initHeaderSearch(){

    const toggle = document.querySelector(".header-search-toggle");

    const panel = document.querySelector("#headerSearch");

    const overlay = document.querySelector("#headerSearchOverlay");

    const closeBtn = document.querySelector(".header-search-close");

    const input = panel ? panel.querySelector('input[name="q"]') : null;

    if(!toggle || !panel) return;

    function openSearch(){

        panel.classList.add("open");

        if(overlay) overlay.classList.add("open");

        toggle.setAttribute("aria-expanded", "true");

        if(input) input.focus();

    }

    function closeSearch(){

        panel.classList.remove("open");

        if(overlay) overlay.classList.remove("open");

        toggle.setAttribute("aria-expanded", "false");

    }

    toggle.addEventListener("click", (event)=>{

        event.preventDefault();

        if(panel.classList.contains("open")){

            closeSearch();

        } else {

            openSearch();

        }

    });

    if(closeBtn){

        closeBtn.addEventListener("click", closeSearch);

    }

    if(overlay){

        overlay.addEventListener("click", closeSearch);

    }

    document.addEventListener("keydown", (event)=>{

        if(event.key === "Escape"){

            closeSearch();

        }

    });

}

/* ===================================================================
   02b. BACK TO TOP
   -------------------------------------------------------------------
   #29: #backToTop is now rendered by the shared customer footer
   (includes/footer.php), so this runs on every customer-facing page
   that includes it. Admin pages don't include this file's markup,
   so querySelector simply finds nothing there and no-ops, same as
   any other page that somehow lacks the element.
=================================================================== */

function initBackToTop(){

    const backToTop = document.querySelector("#backToTop");

    if(!backToTop) return;

    window.addEventListener("scroll",()=>{

        backToTop.classList.toggle("show",window.scrollY > 400);

    });

    backToTop.addEventListener("click",()=>{

        window.scrollTo({ top: 0, behavior: "smooth" });

    });

}

/* ===================================================================
   02c. #21 PHASE B - GLOBAL NAVIGATION LOADING INDICATOR
   -------------------------------------------------------------------
   Shows a brief, MoonAura-themed overlay (#pageLoaderOverlay in
   includes/footer.php) while a normal internal link OR a normal
   page-changing form is navigating the browser away.

   Deliberately isolated from everything else in this file:
   - Never calls event.preventDefault(), so normal browser
     navigation - and the no-JS fallback - behave exactly as
     before; this is a purely visual, best-effort overlay.
   - Skips AJAX/fetch/XHR actions, including #21A cart/wishlist
     intercepts (those call preventDefault, so defaultPrevented
     already excludes them; form-class / data-ajax checks are a
     second belt).
   - Respects an explicit `data-ajax` or `data-no-page-loader`
     attribute on the element (or an ancestor) as an opt-out.
=================================================================== */

function initNavigationLoader(){

    if(window.__moonauraNavigationLoaderInit) return;

    const overlay = document.querySelector("#pageLoaderOverlay");

    if(!overlay) return;

    window.__moonauraNavigationLoaderInit = true;

    const SHOW_DELAY = 130;
    const SAFETY_TIMEOUT = 8000;
    const FILE_EXTENSION_PATTERN = /\.(pdf|zip|rar|7z|docx?|xlsx?|pptx?|csv|txt|mp3|mp4|mov|avi)$/i;
    const AJAX_FORM_CLASSES = [
        "wishlist-form",
        "cart-item-remove-form",
        "cart-item-quantity-form"
    ];

    let showTimer = null;
    let safetyTimer = null;

    function clearLoader(){

        clearTimeout(showTimer);
        clearTimeout(safetyTimer);
        showTimer = null;
        safetyTimer = null;
        overlay.classList.remove("active");
        overlay.setAttribute("aria-hidden", "true");

    }

    function scheduleLoader(){

        clearTimeout(showTimer);
        clearTimeout(safetyTimer);

        showTimer = setTimeout(() => {

            overlay.classList.add("active");
            overlay.setAttribute("aria-hidden", "false");

        }, SHOW_DELAY);

        safetyTimer = setTimeout(clearLoader, SAFETY_TIMEOUT);

    }

    function isOptedOut(el){

        return !!(el && (el.closest("[data-ajax]") || el.closest("[data-no-page-loader]")));

    }

    function isAjaxForm(form){

        if(!form || !form.classList) return false;

        for(let i = 0; i < AJAX_FORM_CLASSES.length; i++){

            if(form.classList.contains(AJAX_FORM_CLASSES[i])) return true;

        }

        const declaredAction = form.getAttribute("action") || "";
        const resolvedAction = form.action || "";

        return declaredAction.indexOf("cart-add.php") !== -1 || resolvedAction.indexOf("cart-add.php") !== -1;

    }

    function isInternalPageNavigationUrl(url){

        if(url.protocol !== "http:" && url.protocol !== "https:") return false;

        if(url.origin !== window.location.origin) return false;

        if(url.pathname === window.location.pathname && url.hash) return false;

        if(FILE_EXTENSION_PATTERN.test(url.pathname)) return false;

        if(/[?&]mode=download(?:&|$)/i.test(url.search)) return false;

        if(/(?:^|\/)(?:guest-)?invoice\.php$/i.test(url.pathname) && /[?&]mode=(?:print|download)(?:&|$)/i.test(url.search)) return false;

        return true;

    }

    document.addEventListener("click", (event) => {

        if(event.defaultPrevented) return;

        if(event.button !== 0) return;

        if(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest("a[href]");

        if(!link) return;

        if(isOptedOut(link)) return;

        if(link.hasAttribute("disabled")) return;

        if(link.getAttribute("aria-disabled") === "true") return;

        if(link.target && link.target !== "_self") return;

        if(link.hasAttribute("download")) return;

        const href = link.getAttribute("href");

        if(!href) return;

        if(href.trim().startsWith("#")) return;

        let url;

        try{

            url = new URL(href, window.location.href);

        } catch(e){

            return;

        }

        if(!isInternalPageNavigationUrl(url)) return;

        if(url.pathname === window.location.pathname && url.search === window.location.search && !url.hash) return;

        scheduleLoader();

    });

    function shouldShowLoaderForForm(form){

        if(!form || form.tagName !== "FORM") return false;

        if(isOptedOut(form)) return false;

        if(isAjaxForm(form)) return false;

        if(form.target && form.target !== "_self") return false;

        const method = ((form.getAttribute("method") || "get") + "").toLowerCase();

        let url;

        try{

            url = new URL(form.action || window.location.href, window.location.href);

        } catch(e){

            return false;

        }

        if(url.protocol !== "http:" && url.protocol !== "https:") return false;

        if(url.origin !== window.location.origin) return false;

        if(FILE_EXTENSION_PATTERN.test(url.pathname)) return false;

        if(method === "get" && url.pathname === window.location.pathname && url.search === window.location.search) return false;

        return true;

    }

    document.addEventListener("submit", (event) => {

        if(event.defaultPrevented) return;

        if(!shouldShowLoaderForForm(event.target)) return;

        scheduleLoader();

    });

    const nativeFormSubmit = HTMLFormElement.prototype.submit;

    HTMLFormElement.prototype.submit = function(){

        if(shouldShowLoaderForForm(this)){

            scheduleLoader();

        }

        return nativeFormSubmit.call(this);

    };

    window.addEventListener("pageshow", clearLoader);

}

/* ==========================================
   TESTIMONIALS SWIPER
   Guarded: only pages that actually load the
   Swiper library and contain the testimonial
   markup should initialise it. Without this,
   account.php / admin pages that include
   main.js but no Swiper would throw a
   ReferenceError here.
========================================== */

if (typeof Swiper !== 'undefined' && document.querySelector('.testimonialSwiper')) {

const testimonialSwiper = new Swiper(".testimonialSwiper",{

    slidesPerView:"auto",

    spaceBetween:28,

    loop:true,

    speed:900,

    grabCursor:true,

    watchOverflow: true,

    centerInsufficientSlides: false,

    autoplay:{

        delay:4000,

        disableOnInteraction:false,

        pauseOnMouseEnter:true

    },

    pagination:{

        el:".swiper-pagination",

        clickable:true

    },

    breakpoints:{

        0:{

            slidesPerView:1

        },

        768:{

            slidesPerView:2

        },

        1200:{

            slidesPerView:3.25

        }

    }

});

}

/* ==================================================
   INSTAGRAM SWIPER
   Same guard as the testimonial swiper above - the
   Swiper library and this markup only exist on the
   homepage, so initialise only when both are present.
================================================== */

if (typeof Swiper !== 'undefined' && document.querySelector('.instagram-swiper')) {

const instagramSwiper = new Swiper(".instagram-swiper", {

    loop: true,

    speed: 800,

    spaceBetween: 24,

    grabCursor: true,

    autoplay: {

        delay: 2500,

        disableOnInteraction: false,

        pauseOnMouseEnter: true,

    },

    pagination: {

        el: ".instagram-pagination",

        clickable: true,

    },

    breakpoints: {

        0: {

            slidesPerView: 1,

            centeredSlides: true,

            spaceBetween: 16

        },

        768: {

            slidesPerView: 1,

            centeredSlides: false,

            spaceBetween: 20

        },

        992: {

            slidesPerView: 3,

            spaceBetween: 24

        }

    }

});

}
