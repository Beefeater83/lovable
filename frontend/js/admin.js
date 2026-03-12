const API_BASE = 'http://localhost:8000';
const PRODUCTS_URL = `${API_BASE}/api/products`;
const categories = ['phone', 'notebook', 'headphones'];

const container = document.getElementById('admin-content');

let editingId = null;
let addingCategory = null;

async function fetchProducts() {
    const res = await fetch(PRODUCTS_URL);
    const data = await res.json();
    render(data.items ?? []);
}

function groupByCategory(items) {
    return categories.reduce((acc, cat) => {
        acc[cat] = items.filter(i => i.category === cat);
        return acc;
    }, {});
}

function render(items) {
    const grouped = groupByCategory(items);

    container.innerHTML = categories.map(cat => {
        const rows = grouped[cat].map(p => renderRow(p)).join('');
        const addRow = addingCategory === cat ? renderAddRow(cat) : '';
        return `
      <div class="admin-category">
        <h2>${cat}</h2>
        ${rows}
        ${addRow}
        <button class="admin-add-btn" onclick="startAdd('${cat}')">Add</button>
      </div>
    `;
    }).join('');
}

function renderRow(p) {
    const isEditing = editingId === p.id;

    return `
    <div class="admin-row">
      <img src="${API_BASE}/uploads/products/${p.imagePath}" />
      ${isEditing
        ? `<input class="admin-input name-input" value="${p.name}" data-id="${p.id}" />`
        : `<div class="admin-name">${p.name}</div>`}
      ${isEditing
        ? `<input type="number" class="admin-input price-input" value="${p.price}" data-id="${p.id}" />`
        : `<div class="admin-price">$${Number(p.price).toFixed(2)}</div>`}
      <div class="admin-actions">
        ${isEditing
        ? `<button onclick="saveEdit(${p.id})">Save</button>
             <button onclick="cancelEdit()">Cancel</button>`
        : `<button onclick="startEdit(${p.id})">Edit</button>
             <button onclick="deleteProduct(${p.id})">Delete</button>`}
      </div>
    </div>
  `;
}

function renderAddRow(category) {
    return `
    <div class="admin-row">
      <input type="file" class="admin-input file-input" accept="image/*" />
      <input class="admin-input name-input" placeholder="Name" />
      <input type="number" class="admin-input price-input" placeholder="Price" />
      <div class="admin-actions">
        <button onclick="saveAdd('${category}')">Save</button>
        <button onclick="cancelAdd()">Cancel</button>
      </div>
    </div>
  `;
}

function startEdit(id) {
    editingId = id;
    fetchProducts();
}

function cancelEdit() {
    editingId = null;
    fetchProducts();
}

async function saveEdit(id) {
    clearError();
    const nameInput = document.querySelector(`.name-input[data-id="${id}"]`);
    const priceInput = document.querySelector(`.price-input[data-id="${id}"]`);

    const name = nameInput.value.trim();
    const price = Number(priceInput.value);

    if (!name || !price) return;

    const res = await fetch(`${PRODUCTS_URL}/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, price }),
        credentials: 'include'
    });

    if (res.status === 403) {
        showError('You are not admin');
        cancelEdit();
        return;
    }

    editingId = null;
    fetchProducts();
}

async function deleteProduct(id) {
    clearError();
    const res = await fetch(`${PRODUCTS_URL}/${id}`, {
        method: 'DELETE',
        credentials: 'include'
    });
    if (res.status === 403) {
        showError('You are not admin');
        return;
    }

    fetchProducts();
}

function startAdd(category) {
    addingCategory = category;
    fetchProducts();
}

function cancelAdd() {
    addingCategory = null;
    fetchProducts();
}

async function saveAdd(category) {
    clearError();
    const addRow = document.querySelector(`.admin-category:has(.file-input)`);
    const fileInput = addRow.querySelector('.file-input');
    const nameInput = addRow.querySelector('.name-input');
    const priceInput = addRow.querySelector('.price-input');

    const name = nameInput.value.trim();
    const price = Number(priceInput.value);
    const file = fileInput.files[0];

    if (!name || !price || !file) return;

    const formData = new FormData();
    formData.append('name', name);
    formData.append('price', price);
    formData.append('category', category);
    formData.append('image', file);

    const res = await fetch(PRODUCTS_URL, {
        method: 'POST',
        body: formData,
        credentials: 'include'
    });

    if (res.status === 403) {
        showError('You are not admin');
        cancelAdd();
        return;
    }

    addingCategory = null;
    fetchProducts();
}

async function exportXlsx() {
    const response = await fetch(PRODUCTS_URL, {
        credentials: 'include',
        headers: {
            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        }
    });

    if (!response.ok) {
        alert('Export failed');
        return;
    }

    const blob = await response.blob();

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'products.xlsx';
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

const errorDiv = document.getElementById('admin-error');

function showError(message) {
    errorDiv.textContent = message;
}

function clearError() {
    errorDiv.textContent = '';
}

async function loginAdmin() {
    clearError();

    const res = await fetch(`${API_BASE}/api/admin/login`, {
        method: 'POST',
        credentials: 'include'
    });

    if (res.status === 403) {
        showError('You are not admin or not found');
        return;
    }

    if (!res.ok) {
        showError('Login failed');
        return;
    }

    showError('Logged in as admin');
    fetchProducts();
}

async function logoutAdmin() {
    const res = await fetch(`${API_BASE}/api/admin/logout`, {
        method: 'POST',
        credentials: 'include'
    });

    if (!res.ok) {
        showError('Logout failed');
        return;
    }

    showError('Logged out');
}

fetchProducts();