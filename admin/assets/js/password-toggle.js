/* ===================================================================
   PASSWORD VISIBILITY TOGGLE (shared - Customer + Admin Login)
   -------------------------------------------------------------------
   #33: application-level show/hide toggle, independent of any
   browser/password-manager native reveal control. One small file,
   loaded by both account/login.php and admin/login.php, using event
   delegation so it needs no per-page setup and works for any number
   of ".pw-field" instances without duplicating this logic anywhere.

   Expected markup (see account/login.php / admin/login.php):

       <div class="pw-field">
           <input type="password" ...>
           <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
               <i class="fa-solid fa-eye" aria-hidden="true"></i>
           </button>
       </div>

   The button is already type="button" in the markup, which is what
   stops it from submitting the form on its own - this script never
   needs to call preventDefault() for that reason, but does so anyway
   as a defensive no-op in case a future button PICKS UP TYPE="submit"
   in that context (e.g. a page building its own field manually).
=================================================================== */

(function () {

    document.addEventListener('click', function (event) {

        var toggle = event.target.closest('.pw-toggle');

        if (!toggle) {
            return;
        }

        // Belt-and-braces: never let this click submit the form, even
        // if some future markup accidentally omits type="button".
        event.preventDefault();

        var field = toggle.closest('.pw-field');
        var input = field ? field.querySelector('input') : null;

        if (!input) {
            return;
        }

        var isCurrentlyHidden = input.type === 'password';

        input.type = isCurrentlyHidden ? 'text' : 'password';

        var icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-eye', !isCurrentlyHidden);
            icon.classList.toggle('fa-eye-slash', isCurrentlyHidden);
        }

        toggle.setAttribute('aria-pressed', isCurrentlyHidden ? 'true' : 'false');
        toggle.setAttribute('aria-label', isCurrentlyHidden ? 'Hide password' : 'Show password');

        // Keep focus on the input the admin/customer was just typing
        // into, rather than leaving focus stranded on the button.
        input.focus();
    });

})();
