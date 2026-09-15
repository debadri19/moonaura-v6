/* ===================================================================
   PRODUCT GALLERY - THUMBNAIL CLICK TO SWAP MAIN IMAGE
   -------------------------------------------------------------------
   Simple vanilla JS, no dependencies. Each thumbnail button stores
   its full-size image path in a data-image attribute; clicking it
   copies that path into the main image and marks the thumbnail as
   active.
=================================================================== */

function initProductGallery() {

    const mainImage = document.getElementById('productMainImage');
    const thumbButtons = document.querySelectorAll('.product-gallery-thumb');

    // Nothing to do if this product only has one image (no thumbnails).
    if (!mainImage || thumbButtons.length === 0) {
        return;
    }

    thumbButtons.forEach(function (thumbButton) {

        thumbButton.addEventListener('click', function () {

            const newImageSrc = thumbButton.getAttribute('data-image');
            const newImageAlt  = thumbButton.getAttribute('data-alt');

            mainImage.src = newImageSrc;

            if (newImageAlt) {
                mainImage.alt = newImageAlt;
            }

            // Move the "active" outline to the clicked thumbnail only.
            thumbButtons.forEach(function (btn) {
                btn.classList.remove('active');
            });

            thumbButton.classList.add('active');

        });

    });

}

initProductGallery();
