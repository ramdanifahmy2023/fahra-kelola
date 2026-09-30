(() => {
  const page = document.getElementById('products-page');
  const dialog = document.getElementById('product-detail-dialog');
  if (!page || !dialog) return;
  const close = document.getElementById('product-detail-close');
  let opener;

  function replaceFailedImage(img) {
    const fallback = document.createElement('span');
    fallback.className = 'material-symbols-outlined ' + (img.classList.contains('product-shop-logo') ? 'product-shop-fallback' : 'product-image-fallback');
    fallback.textContent = img.classList.contains('product-shop-logo') ? 'storefront' : 'inventory_2';
    fallback.setAttribute('aria-hidden', 'true');
    img.replaceWith(fallback);
  }

  function watchImages(target) {
    target.querySelectorAll('.product-cover, .product-shop-logo').forEach(img => {
      if (img.complete && !img.naturalWidth) replaceFailedImage(img);
      else img.addEventListener('error', () => replaceFailedImage(img), {once: true});
    });
  }

  page.addEventListener('click', event => {
    const button = event.target.closest('[data-product-details]');
    if (!button) return;
    const template = document.getElementById('product-detail-' + button.dataset.productDetails);
    if (!template) return;
    opener = button;
    const content = document.getElementById('product-detail-content');
    content.replaceChildren(template.content.cloneNode(true));
    watchImages(content);
    dialog.showModal();
    close.focus();
  });
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => opener?.focus());
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const box = dialog.getBoundingClientRect();
    if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
  });
  watchImages(page);
})();
