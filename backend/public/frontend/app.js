const API_BASE = 'http://localhost:8000';
const PRODUCTS_URL = `${API_BASE}/api/products`;
const elements = {
    grid: document.getElementById('products-grid'),
    loading: document.getElementById('loading'),
    error: document.getElementById('error'),
};
function createProductCard(product) {
    const imageUrl = `${API_BASE}${product.imagePath}`;
    const price = `$${product.price.toFixed(2)}`;
    const card = document.createElement('article');
    card.className = 'product-card';
    card.innerHTML = `
    <div class="product-card__image-wrapper">
      <img class="product-card__image" src="${imageUrl}" alt="${product.name}" loading="lazy">
    </div>
    <div class="product-card__body">
      <span class="product-card__category">${product.category}</span>
      <h2 class="product-card__name">${product.name}</h2>
      <p class="product-card__price">${price}</p>
    </div>
  `;
    return card;
}
function renderProducts(products) {
    elements.grid.innerHTML = '';
    products.forEach((product) => {
        elements.grid.appendChild(createProductCard(product));
    });
}
function showLoading(show) {
    elements.loading.style.display = show ? 'block' : 'none';
}
function showError(show) {
    elements.error.style.display = show ? 'block' : 'none';
}
async function fetchProducts() {
    showLoading(true);
    showError(false);
    try {
        const response = await fetch(PRODUCTS_URL);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        renderProducts(data.items);
    } catch (err) {
        console.error('Failed to fetch products:', err);
        showError(true);
    } finally {
        showLoading(false);
    }
}
document.addEventListener('DOMContentLoaded', fetchProducts);