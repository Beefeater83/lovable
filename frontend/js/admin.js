const API_BASE = 'http://localhost:8000';
const PRODUCTS_URL = `${API_BASE}/api/products`;
const categories = ['phone', 'notebook', 'headphones'];

const container = document.getElementById('admin-content');

let editingId = null;
let addingCategory = null;

let jwtToken = localStorage.getItem('jwt');

function extractTokenFromUrl() {
    const hash = window.location.hash;
    if (hash.startsWith('#token=')) {
        const token = hash.substring(7);
        localStorage.setItem('jwt', token);
        jwtToken = token;
        history.replaceState({}, '', window.location.pathname + window.location.search);
    }
}

extractTokenFromUrl();

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

function handleValidationErrors(errors) {
    if (!errors || errors.length === 0) {
        showError('Validation error');
        return;
    }

    showError(errors.join('\n'));
}

async function saveEdit(id) {
    clearError();

    if (!jwtToken) {
        showError('Not authenticated. Please login.');
        cancelEdit();
        return;
    }

    const nameInput = document.querySelector(`.name-input[data-id="${id}"]`);
    const priceInput = document.querySelector(`.price-input[data-id="${id}"]`);

    const name = nameInput.value.trim();
    const price = priceInput.value === '' ? 0 : Number(priceInput.value);

    formData.append('name', name);
    const res = await fetch(`${PRODUCTS_URL}/${id}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${jwtToken}`
        },
        body: JSON.stringify({ name, price }),
        //credentials: 'include'
    });

    if (res.status === 400) {
        const data = await res.json();
        handleValidationErrors(data.error);
        return;
    }

    if (res.status === 401) {
        showError('Not authenticated. Please login.');
        cancelEdit();
        return;
    }

    if (res.status === 403) {
        showError('You do not have permission to perform this action');
        cancelEdit();
        return;
    }

    editingId = null;
    fetchProducts();
}

async function deleteProduct(id) {
    clearError();

    if (!jwtToken) {
        showError('Not authenticated. Please login.');
        return;
    }

    const res = await fetch(`${PRODUCTS_URL}/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${jwtToken}` }
        //credentials: 'include'
    });

    if (res.status === 401) {
        showError('Not authenticated. Please login.');
        return;
    }

    if (res.status === 403) {
        showError('You do not have permission to perform this action');
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

    if (!jwtToken) {
        showError('Not authenticated. Please login.');
        cancelAdd();
        return;
    }

    const addRow = document.querySelector('.file-input')?.closest('.admin-row')
    const fileInput = addRow.querySelector('.file-input');
    const nameInput = addRow.querySelector('.name-input');
    const priceInput = addRow.querySelector('.price-input');

    const name = nameInput.value.trim();
    const sendName = name.length === 0 ? ' ' : name;
    const price = priceInput.value === '' ? 0 : Number(priceInput.value);
    const file = fileInput.files[0];

    if (!file) {
        showError('Image is required');
        return;
    }

    const formData = new FormData();
    formData.append('name', sendName);
    formData.append('price', price);
    formData.append('category', category);
    formData.append('image', file);

    const res = await fetch(PRODUCTS_URL, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${jwtToken}` },
        body: formData,
       // credentials: 'include'
    });

    if (res.status === 400) {
        const data = await res.json();
        handleValidationErrors(data.error);
        return;
    }

    if (res.status === 401) {
        showError('Not authenticated. Please login.');
        cancelAdd();
        return;
    }

    if (res.status === 403) {
        showError('You do not have permission to perform this action');
        cancelAdd();
        return;
    }

    addingCategory = null;
    fetchProducts();
}

async function exportXlsx() {
    const response = await fetch(PRODUCTS_URL, {
       // credentials: 'include',
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
    errorDiv.innerHTML = message.replace(/\n/g, '<br>');
}

function clearError() {
    errorDiv.textContent = '';
}

function checkLoginResult() {
    const params = new URLSearchParams(window.location.search);
    const login = params.get('login');

    if (login === 'success') {
        showError('Authenticated. Admin access will be checked per action.');
        fetchProducts();
    } else if (login === 'failed') {
        showError('Login failed');
    }

    if (login) {
        history.replaceState({}, '', window.location.pathname);
    }
}

checkLoginResult();

function loginWithGoogle() {
    window.location.href = `${API_BASE}/api/connect/google`;
}

/*
sessions security
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
 */

function logoutAdmin() {
    localStorage.removeItem('jwt');
    jwtToken = null;
    showError('Logged out');
}

fetchProducts();