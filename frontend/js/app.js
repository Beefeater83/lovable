const API_BASE = 'http://localhost:8000';
const PRODUCTS_URL = `${API_BASE}/api/products`;

const categories = [
    { label: 'All', value: null },
    { label: 'Phones', value: 'phone' },
    { label: 'Notebooks', value: 'notebook' },
    { label: 'Headphones', value: 'headphones' }
];

const state = {
    activeCategory: null
};

const elements = {
    grid: document.getElementById('product-grid'),
    filterBar: document.getElementById('filter-bar')
};

function buildUrl() {
    if (state.activeCategory === null) return PRODUCTS_URL;
    return `${PRODUCTS_URL}?filter[category][eq]=${encodeURIComponent(state.activeCategory)}`;
}

function showMessage(text, isError = false) {
    elements.grid.innerHTML = `
    <div class="message${isError ? ' error' : ''}">
      ${text}
    </div>
  `;
}

function renderFilters() {
    elements.filterBar.innerHTML = categories
        .map(({ label, value }) => `
      <button 
        class="filter-btn${state.activeCategory === value ? ' active' : ''}" 
        data-value="${value ?? ''}">
        ${label}
      </button>
    `)
        .join('');

    elements.filterBar.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            state.activeCategory = value === '' ? null : value;
            renderFilters();
            fetchProducts();
        });
    });
}

function renderProducts(items) {
    if (!items.length) {
        showMessage('No products found.');
        return;
    }

    elements.grid.innerHTML = items
        .map(p => `
      <div class="product-card">
        <img src="${API_BASE}${p.imagePath}" alt="${p.name}" loading="lazy" />
        <div class="product-info">
          <div class="product-category">${p.category}</div>
          <div class="product-name">${p.name}</div>
          <div class="product-price">$${Number(p.price).toFixed(2)}</div>
        </div>
      </div>
    `)
        .join('');
}

async function fetchProducts() {
    showMessage('Loading…');

    try {
        const response = await fetch(buildUrl());

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        renderProducts(data.items ?? []);
    } catch (error) {
        showMessage(`Error loading products: ${error.message}`, true);
    }
}

renderFilters();
fetchProducts();