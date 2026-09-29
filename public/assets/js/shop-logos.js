(() => {
  const logos = new Map(JSON.parse(document.getElementById('shop-logo-data').textContent).map(shop => [String(shop.id), shop.logo]));
  window.renderShopLogo = (container, shopId) => {
    const fallback = document.createElement('span');
    fallback.className = 'material-symbols-outlined';
    fallback.textContent = 'storefront';
    container.setAttribute('aria-hidden', 'true');
    container.replaceChildren(fallback);
    const source = logos.get(String(shopId));
    if (!source) return;
    const image = document.createElement('img');
    image.alt = '';
    image.width = image.height = 40;
    image.loading = 'lazy';
    image.addEventListener('error', () => image.replaceWith(fallback), {once: true});
    image.src = source;
    container.replaceChildren(image);
  };
})();
