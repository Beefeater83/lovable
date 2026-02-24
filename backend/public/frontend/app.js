const API_BASE = 'http://localhost:8000';
const PRODUCTS_URL = `${API_BASE}/api/products`;
const categories = [
    { label: 'All', value: null },
    { label: 'Phones', value: 'phone' },
    { label: 'Notebooks', value: 'notebook' },
    { label: 'Headphones', value: 'headphones' },
];
let activeCategory = null;
const grid = document.getElementById('product-grid');
const filterBar = document.getElementById('filter-bar');
function renderFilters() {
    filterBar.innerHTML = '';
    categories.forEach(({ label, value }) => {
        const btn = document.createElement('button');
        btn.className = `filter-btn${activeCategory === value ? ' active' : ''}`;
        btn.textContent = label;
        btn.addEventListener('click', () => {
            activeCategory = value;
            renderFilters();
            fetchProducts();
        });
        filterBar.appendChild(btn);
    });
}
function buildUrl() {
    if (!activeCategory) return PRODUCTS_URL;
    return `${PRODUCTS_URL}?filter[category][eq]=${encodeURIComponent(activeCategory)}`;
}
function showMessage(text, isError = false) {
    grid.innerHTML = `<div class="message${isError ? ' error' : ''}">${text}</div>`;
}
function renderProducts(items) {
    if (!items.length) {
        showMessage('No products found.');
        return;
    }
    grid.innerHTML = items.map(p => `
    <div class="product-card">
      <img src="${API_BASE}${p.imagePath}" alt="${p.name}" loading="lazy" />
      <div class="product-info">
        <div class="product-category">${p.category}</div>
        <div class="product-name">${p.name}</div>
        <div class="product-price">$${Number(p.price).toFixed(2)}</div>
      </div>
    </div>
  `).join('');
}
async function fetchProducts() {
    showMessage('Loading…');
    try {
        const res = await fetch(buildUrl());
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        renderProducts(data.items || []);
    } catch (err) {
        showMessage(`Error loading products: ${err.message}`, true);
    }
}
renderFilters();
fetchProducts();