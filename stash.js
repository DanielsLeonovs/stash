const content = document.getElementById("content");

async function api(action, data = {}) {
    const r = await fetch("api.php?action=" + action, {
        method: "POST",
        body: JSON.stringify({ ...data, role })
    });
    return r.json();
}

function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

const access = {
    home: ["admin", "worker", "sorter"],
    products: ["admin", "worker", "sorter"],
    users: ["admin"],
    orders: ["worker"],
    place: ["sorter"],
    report: ["admin", "worker", "sorter"]
};

function show() {
    const view = location.hash.slice(1) || "home";
    if (!views[view] || !access[view].includes(role)) return views.home();
    views[view]();
}

async function act(action, data, reload) {
    const res = await api(action, data);
    alert(res.message);
    if (res.success) reload();
}

const views = {
    home() {
        content.innerHTML = `<h2>Sveicināts!</h2><p>Tava loma: <b>${esc(role)}</b></p>`;
    },

    async products() {
        const { products } = await api("products_list");
        const canEdit = role === "admin" || role === "worker";
        content.innerHTML = `<h2>Preces</h2>
            ${canEdit ? `<div class="box">
                <input type="hidden" id="pid">
                <input id="pname" placeholder="Nosaukums">
                <input id="pqty" type="number" min="0" placeholder="Daudzums">
                <input id="ploc" placeholder="Atrašanās vieta">
                <button onclick="saveProduct()">Saglabāt</button>
            </div>` : ""}
            <table><tr><th>ID</th><th>Nosaukums</th><th>Daudzums</th><th>Vieta</th><th></th></tr>
            ${products.map(p => `<tr>
                <td>${p.id}</td><td>${esc(p.name)}</td><td>${p.qty}</td><td>${esc(p.location)}</td>
                <td>${canEdit ? `<button onclick='editProduct(${JSON.stringify(p)})'>Labot</button>` : ""}
                    ${role === "admin" ? `<button onclick="delProduct(${p.id})">Dzēst</button>` : ""}</td>
            </tr>`).join("")}</table>`;
    },

    async users() {
        const { users } = await api("users_list");
        const opts = sel => ["admin", "worker", "sorter"].map(r => `<option ${r === sel ? "selected" : ""}>${r}</option>`).join("");
        content.innerHTML = `<h2>Lietotāji</h2>
            <div class="box">
                <input id="ulogin" placeholder="Lietotājvārds">
                <input id="upass" type="password" placeholder="Parole">
                <select id="urole">${opts("worker")}</select>
                <button onclick="addUser()">Izveidot</button>
            </div>
            <table><tr><th>Lietotājs</th><th>Loma</th><th>Jauna parole</th><th></th></tr>
            ${users.map((u, i) => `<tr>
                <td>${esc(u.login)}</td>
                <td><select id="r${i}">${opts(u.role)}</select></td>
                <td><input id="p${i}" type="password" placeholder="(nemainīt)"></td>
                <td><button onclick="updUser('${esc(u.login)}', ${i})">Saglabāt</button>
                    <button onclick="delUser('${esc(u.login)}')">Dzēst</button></td>
            </tr>`).join("")}</table>`;
    },

    async orders() {
        const { products } = await api("products_list");
        const { orders, statuses } = await api("orders_list");
        content.innerHTML = `<h2>Pasūtījumi</h2>
            <div class="box">
                <select id="oprod">${products.map(p => `<option value="${p.id}">${esc(p.name)} (${p.qty})</option>`).join("")}</select>
                <input id="oqty" type="number" min="1" placeholder="Daudzums">
                <input id="oclient" placeholder="Klients">
                <button onclick="addOrder()">Izveidot pasūtījumu</button>
            </div>
            <table><tr><th>ID</th><th>Datums</th><th>Prece</th><th>Daudz.</th><th>Klients</th><th>Statuss</th><th></th></tr>
            ${orders.map(o => `<tr>
                <td>${o.id}</td><td>${o.date}</td><td>${esc(o.product)}</td><td>${o.qty}</td><td>${esc(o.client)}</td>
                <td><select onchange="setStatus(${o.id}, this.value)">
                    ${statuses.map(s => `<option ${s === o.status ? "selected" : ""}>${s}</option>`).join("")}</select></td>
                <td><button onclick="delOrder(${o.id})">Dzēst</button></td>
            </tr>`).join("")}</table>`;
    },

    async place() {
        const { products } = await api("products_list");
        content.innerHTML = `<h2>Preču izvietošana</h2>
            <table><tr><th>Prece</th><th>Daudz.</th><th>Atrašanās vieta</th><th></th></tr>
            ${products.map(p => `<tr>
                <td>${esc(p.name)}</td><td>${p.qty}</td>
                <td><input id="loc${p.id}" value="${esc(p.location)}" placeholder="piem. A-2-3"></td>
                <td><button onclick="placeProduct(${p.id})">Saglabāt</button></td>
            </tr>`).join("")}</table>`;
    },

    async report() {
        const r = await api("report");
        const list = (arr, f) => arr.length ? "<ul>" + arr.map(f).join("") + "</ul>" : "<p>—</p>";
        content.innerHTML = `<h2>Noliktavas pārskats</h2>
            <p>Ģenerēts: ${r.date}</p>
            <p>Preču veidu skaits: <b>${r.products.length}</b> | Kopējais daudzums: <b>${r.total_qty}</b></p>
            <h3>Zems atlikums (&lt; 5)</h3>${list(r.low_stock, p => `<li>${esc(p.name)} — ${p.qty}</li>`)}
            <h3>Preces bez atrašanās vietas</h3>${list(r.no_place, p => `<li>${esc(p.name)}</li>`)}
            <h3>Pasūtījumi pēc statusa</h3>${list(Object.entries(r.orders), ([s, n]) => `<li>${s}: ${n}</li>`)}
            <h3>Pēdējās izvietojuma izmaiņas</h3>${list(r.log, l => `<li>${l.date}: ${esc(l.product)} — ${esc(l.from || "nav")} → ${esc(l.to)}</li>`)}
            <button onclick="window.print()">Drukāt / saglabāt PDF</button>`;
    }
};

const val = id => document.getElementById(id).value;

function saveProduct() {
    act("product_save", { id: val("pid"), name: val("pname"), qty: val("pqty"), location: val("ploc") }, views.products);
}
function editProduct(p) {
    pid.value = p.id; pname.value = p.name; pqty.value = p.qty; ploc.value = p.location;
}
function delProduct(id) { if (confirm("Dzēst produktu?")) act("product_delete", { id }, views.products); }

function addUser() {
    act("register", { login: val("ulogin"), password: val("upass"), role: val("urole") }, views.users);
}
function updUser(login, i) {
    act("user_update", { login, newRole: val("r" + i), newPassword: val("p" + i) }, views.users);
}
function delUser(login) { if (confirm("Dzēst lietotāju?")) act("user_delete", { login }, views.users); }

function addOrder() {
    act("order_save", { product_id: val("oprod"), qty: val("oqty"), client: val("oclient") }, views.orders);
}
function setStatus(id, status) { act("order_status", { id, status }, views.orders); }
function delOrder(id) { if (confirm("Dzēst pasūtījumu?")) act("order_delete", { id }, views.orders); }

function placeProduct(id) { act("place", { id, location: val("loc" + id) }, views.place); }

window.addEventListener("hashchange", show);
show();
