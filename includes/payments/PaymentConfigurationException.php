<?php
/* ===================================================================
   PAYMENT CONFIGURATION EXCEPTION
   -------------------------------------------------------------------
   Thrown when a payment gateway is selected/active but its required
   credentials aren't set (e.g. RAZORPAY_KEY_ID/RAZORPAY_KEY_SECRET
   are empty). Deliberately a distinct type from the generic
   RuntimeException a real API/network failure throws, so callers
   (payment.php) can show a specific "go configure this" message
   instead of the generic "something went wrong, try again" one -
   the two situations need different messages and different
   responses from the person reading them.

   The message on this exception is meant to be developer-facing but
   safe to display as-is: it never contains a key, secret, or any
   other credential value - just which credential is missing.
=================================================================== */

class PaymentConfigurationException extends RuntimeException
{
}
