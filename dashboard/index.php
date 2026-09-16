<?php
/* Admin panel entry point. Send visitors to the admin login page in
   this same directory (relative, so it works on the storefront host at
   /dashboard/ as well as on a split admin host). */
header('Location: login.php');
exit;
