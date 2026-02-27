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
    const nameInput = document.querySelector(`.name-input[data-id="${id}"]`);
    const priceInput = document.querySelector(`.price-input[data-id="${id}"]`);

    const name = nameInput.value.trim();
    const price = Number(priceInput.value);

    if (!name || !price) return;

    await fetch(`${PRODUCTS_URL}/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, price })
    });

    editingId = null;
    fetchProducts();
}

async function deleteProduct(id) {
    await fetch(`${PRODUCTS_URL}/${id}`, { method: 'DELETE' });
    fetchProducts();
}

// -------------------- Добавление --------------------
function startAdd(category) {
    addingCategory = category;
    fetchProducts();
}

function cancelAdd() {
    addingCategory = null;
    fetchProducts();
}

async function saveAdd(category) {
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

    await fetch(PRODUCTS_URL, { method: 'POST', body: formData });

    addingCategory = null;
    fetchProducts();
}

fetchProducts();