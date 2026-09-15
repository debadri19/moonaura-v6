/* ===================================================================
   PAYMENT WIDGET INITIALIZATION
   -------------------------------------------------------------------
   Reads the config payment.php embedded as JSON (same pattern as the
   JSON-LD product schema on product.php), then opens WHICHEVER
   gateway's widget is active (config.gateway) - this file mirrors
   the PHP side's gateway-agnostic design: one shared entry point
   that dispatches to a gateway-specific init function, each of which
   knows nothing about the others.

   On success, both paths converge on the same
   submitPaymentVerification() - a POST to payment-verify.php for
   server-side confirmation. This script never decides whether a
   payment succeeded on its own.
=================================================================== */

function initGatewayCheckout() {

    const configElement = document.getElementById('gatewayConfig');
    const payButton      = document.getElementById('gatewayPayButton');

    if (!configElement || !payButton) {
        return;
    }

    const config = JSON.parse(configElement.textContent);

    payButton.addEventListener('click', function () {

        if (config.gateway === 'razorpay') {
            openRazorpayCheckout(config);
        }

    });

}


/* ==========================================
   RAZORPAY
========================================== */

function openRazorpayCheckout(config) {

    const razorpayOptions = {
        key:         config.key,
        amount:      config.amount,
        currency:    config.currency,
        order_id:    config.gateway_order_id,
        name:        config.name,
        description: config.description,
        prefill:     config.prefill,

        handler: function (response) {
            submitPaymentVerification(config, {
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id:   response.razorpay_order_id,
                razorpay_signature:  response.razorpay_signature,
            });
        },

        modal: {
            ondismiss: function () {
                window.location.href = config.failure_url;
            },
        },
    };

    const razorpayCheckout = new Razorpay(razorpayOptions);

    razorpayCheckout.on('payment.failed', function () {
        window.location.href = config.failure_url;
    });

    razorpayCheckout.open();

}


/* ==========================================
   SUBMIT THE PAYMENT PROOF TO payment-verify.php
   Builds a real form and submits it (POST), rather
   than an AJAX call, so the browser simply navigates
   to the result page exactly like every other form in
   this project. $extraFields carries whatever fields
   are specific to the gateway that was just used (e.g.
   Razorpay's signature).
========================================== */

function submitPaymentVerification(config, extraFields) {

    const form = document.createElement('form');
    form.method = 'post';
    form.action = config.verify_url;

    const fields = Object.assign({
        csrf_token:        config.csrf_token,
        order_number:      config.order_number,
        gateway_order_id:  config.gateway_order_id,
    }, extraFields);

    Object.keys(fields).forEach(function (fieldName) {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = fieldName;
        input.value = fields[fieldName];
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();

}

initGatewayCheckout();
