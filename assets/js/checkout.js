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
=================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const savedAddressSelect = document.getElementById('saved_address');

    if (!savedAddressSelect) {
        return;
    }

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

        // "+ Enter a new address" (empty value) - clear only the
        // address fields, never Full Name/Mobile/Email.
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

        // Email is deliberately never touched - it stays whatever the
        // logged-in customer's account email already prefilled it to.
    });

});
