/* ===================================================================
   SUPPORT PAGE - FAQ ACCORDION + LIVE SEARCH
   -------------------------------------------------------------------
   Scoped to the Support page only. Wires up two independent
   behaviours on the server-rendered FAQ markup:

   - Accordion: each .faq-question toggles its own .faq-item open
     state via aria-expanded. support.css shows/hides .faq-answer
     through the .is-open class (no animation).
   - Live search: filters .faq-item elements as the user types,
     matching against the question AND answer text with a
     case-insensitive partial match. No Enter key or submit button
     is required, and clearing the field restores every item
     immediately.

   Filtering only adds/removes the .is-hidden class and never
   re-creates or unbinds handlers, so the accordion keeps working on
   every item after filtering.
=================================================================== */

function initSupportFaq() {

    const list = document.getElementById("faqList");

    if (!list) return;

    const items = Array.from(list.querySelectorAll(".faq-item"));
    const searchInput = document.getElementById("faqSearch");
    const emptyState = document.getElementById("faqEmpty");

    items.forEach((item) => {

        const question = item.querySelector(".faq-question");

        if (!question) return;

        question.addEventListener("click", () => {

            const isOpen = item.classList.toggle("is-open");

            question.setAttribute("aria-expanded", isOpen ? "true" : "false");

        });

    });

    if (!searchInput) return;

    function applyFilter() {

        const query = searchInput.value.trim().toLowerCase();
        let matches = 0;

        list.scrollTop = 0;

        items.forEach((item) => {

            const haystack = (item.textContent || "").toLowerCase();
            const isMatch = query === "" || haystack.indexOf(query) !== -1;

            item.classList.toggle("is-hidden", !isMatch);

            if (isMatch) matches += 1;

        });

        if (emptyState) emptyState.hidden = matches !== 0;

    }

    searchInput.addEventListener("input", applyFilter);
    searchInput.addEventListener("search", applyFilter);

    applyFilter();

}

initSupportFaq();
