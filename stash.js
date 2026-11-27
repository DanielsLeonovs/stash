const content = document.getElementById("content");

// kuras lomas drīkst redzēt kuru skatu
const access = {
    home: ["admin", "worker", "sorter"],
    products: ["admin", "worker", "sorter"],
    suppliers: ["admin"],
    users: ["admin"],
    orders: ["worker"],
    place: ["sorter"],
    report: ["admin", "worker", "sorter"]
};

// preču filtrs
let lastFilter = { q: "", category: "" };
let currentProducts = [];
let currentSuppliers = [];
let currentUsers = [];

async function api(action, data = {}) {
    const response = await fetch("api.php?action=" + action, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
        body: JSON.stringify(data)
    });
    if (response.status === 401) {
        window.location.href = "home.html";
    }
    return response.json();
}

// pasargā no ļaunprātīga HTML
function esc(text) {
    text = String(text == null ? "" : text);
    text = text.replace(/&/g, "&amp;");
    text = text.replace(/</g, "&lt;");
    text = text.replace(/>/g, "&gt;");
    text = text.replace(/"/g, "&quot;");
    text = text.replace(/'/g, "&#39;");
    return text;
}

function money(number) {
    return Number(number || 0).toFixed(2) + " €";
}

function val(id) {
    return document.getElementById(id).value;
}

// pārbauda ievades laukus (required, min, max u.c. no HTML)
function valid(ids) {
    for (const id of ids) {
        const field = document.getElementById(id);
        if (!field.checkValidity()) {
            field.reportValidity();
            return false;
        }
    }
    return true;
}

// izdara darbību parāda servera ziņu un pārlādē skatu ja izdevās
async function act(action, data, reload) {
    const result = await api(action, data);
    alert(result.message);
    if (result.success) {
        reload();
    }
}

// parāda skatu pēc adreses 
function show() {
    let view = location.hash.slice(1);
    if (view === "") {
        view = "home";
    }

    // ja tāda skata nav vai lomai nav tiesību rāda sākumu
    if (!access.hasOwnProperty(view) || !access[view].includes(role)) {
        showHome();
        return;
    }
    views[view]();
}



function showHome() {
    content.innerHTML = "<h2>Sveicināts!</h2><p>Tava loma: <b>" + esc(role) + "</b></p>";
}

async function showProducts() {
    const productResult = await api("products_list", lastFilter);
    const supplierResult = await api("suppliers_list");
    currentProducts = productResult.products;
    const suppliers = supplierResult.suppliers;
    const canEdit = (role === "admin" || role === "worker");

    // meklēšana
    let html = "<h2>Preces</h2>";
    html += `<div class="box">
        <input id="fq" placeholder="Meklēt pēc nosaukuma" value="${esc(lastFilter.q)}">
        <input id="fcat" placeholder="Kategorija" value="${esc(lastFilter.category)}">
        <button onclick="filterProducts()">Meklēt</button>
        <button onclick="clearFilter()">Notīrīt</button>
    </div>`;

    // preces pievienošanas / labošanas forma (tikai admin un worker)
    if (canEdit) {
        html += `<div class="box">
            <input type="hidden" id="pid">
            <input id="pcode" placeholder="Kods" required maxlength="30">
            <input id="pname" placeholder="Nosaukums" required maxlength="100">
            <input id="pcat" placeholder="Kategorija" maxlength="50">
            <input id="pprice" type="number" min="0" max="1000000" step="0.01" placeholder="Cena (€)" required>
            <input id="pqty" type="number" min="0" max="1000000" step="1" placeholder="Daudzums" required>
            <input id="ploc" placeholder="Atrašanās vieta" maxlength="30">
            <select id="psup">${supplierOptions(suppliers)}</select>
            <button onclick="saveProduct()">Saglabāt</button>
        </div>`;
    }

    // tabula
    html += `<table><tr><th>Kods</th><th>Nosaukums</th><th>Kategorija</th><th>Cena</th>
        <th>Daudz.</th><th>Vieta</th><th>Piegādātājs</th><th></th></tr>`;

    for (const p of currentProducts) {
        let rowClass = "";
        let qtyText = p.qty;
        if (p.low) {
            rowClass = "low";
            qtyText += ' <span title="Zems atlikums">⚠️</span>';
        }

        let buttons = "";
        if (canEdit) {
            buttons += `<button onclick="editProduct(${p.id})">Labot</button> `;
            buttons += `<button onclick="receiveProduct(${p.id})">+ Piegāde</button> `;
        }
        if (role === "admin") {
            buttons += `<button onclick="delProduct(${p.id})">Dzēst</button>`;
        }

        html += `<tr class="${rowClass}">
            <td>${esc(p.code)}</td>
            <td>${esc(p.name)}</td>
            <td>${esc(p.category)}</td>
            <td>${money(p.price)}</td>
            <td>${qtyText}</td>
            <td>${esc(p.location)}</td>
            <td>${esc(p.supplier_name)}</td>
            <td>${buttons}</td>
        </tr>`;
    }
    html += "</table>";

    content.innerHTML = html;
}

// <option> saraksts piegādātāju izvēlnei
function supplierOptions(suppliers) {
    let options = '<option value="">— nav —</option>';
    for (const s of suppliers) {
        options += `<option value="${s.id}">${esc(s.name)}</option>`;
    }
    return options;
}

async function showSuppliers() {
    const result = await api("suppliers_list");
    currentSuppliers = result.suppliers;

    let html = `<h2>Piegādātāji</h2>
        <div class="box">
            <input type="hidden" id="sid">
            <input id="sname" placeholder="Nosaukums" required maxlength="100">
            <input id="scontact" placeholder="Kontakti (e-pasts / tālrunis)" maxlength="100">
            <button onclick="saveSupplier()">Saglabāt</button>
        </div>
        <table><tr><th>Nosaukums</th><th>Kontakti</th><th></th></tr>`;

    for (const s of currentSuppliers) {
        html += `<tr>
            <td>${esc(s.name)}</td>
            <td>${esc(s.contact)}</td>
            <td>
                <button onclick="editSupplier(${s.id})">Labot</button>
                <button onclick="delSupplier(${s.id})">Dzēst</button>
            </td>
        </tr>`;
    }
    html += "</table>";

    content.innerHTML = html;
}

// <option> saraksts lomām
function roleOptions(selected) {
    const roles = ["", "admin", "worker", "sorter"];
    let options = "";
    for (const r of roles) {
        let isSelected = "";
        if (r === selected) {
            isSelected = "selected";
        }
        let label = r;
        if (r === "") {
            label = "— nav lomas —";
        }
        options += `<option value="${r}" ${isSelected}>${label}</option>`;
    }
    return options;
}

async function showUsers() {
    const result = await api("users_list");
    currentUsers = result.users;

    let html = `<h2>Lietotāji</h2>
        <div class="box">
            <input id="ulogin" placeholder="Lietotājvārds" required pattern="[A-Za-z0-9_]{3,30}" title="3-30 simboli: burti, cipari, _">
            <input id="upass" type="password" placeholder="Parole (min. 8)" required minlength="8" maxlength="100">
            <select id="urole">${roleOptions("worker")}</select>
            <button onclick="addUser()">Izveidot</button>
        </div>
        <table><tr><th>Lietotājs</th><th>Loma</th><th>Jauna parole</th><th></th></tr>`;

    for (let i = 0; i < currentUsers.length; i++) {
        const u = currentUsers[i];
        html += `<tr>
            <td>${esc(u.login)}</td>
            <td><select id="r${i}">${roleOptions(u.role)}</select></td>
            <td><input id="p${i}" type="password" placeholder="(nemainīt)" minlength="8" maxlength="100"></td>
            <td>
                <button onclick="updUser(${i})">Saglabāt</button>
                <button onclick="delUser(${i})">Dzēst</button>
            </td>
        </tr>`;
    }
    html += "</table>";

    content.innerHTML = html;
}

async function showOrders() {
    const productResult = await api("products_list");
    const orderResult = await api("orders_list");

    // preču izvēlne jaunam pasūtījumam
    let productOptions = "";
    for (const p of productResult.products) {
        productOptions += `<option value="${p.id}">${esc(p.name)} (${p.qty})</option>`;
    }

    let html = `<h2>Pasūtījumi</h2>
        <div class="box">
            <select id="oprod">${productOptions}</select>
            <input id="oqty" type="number" min="1" max="10000" step="1" placeholder="Daudzums" required>
            <input id="oclient" placeholder="Klients" required maxlength="100">
            <button onclick="addOrder()">Izveidot pasūtījumu</button>
        </div>
        <table><tr><th>ID</th><th>Datums</th><th>Prece</th><th>Daudz.</th><th>Klients</th><th>Statuss</th><th></th></tr>`;

    for (const o of orderResult.orders) {
        // statusa izvēlne
        let statusOptions = "";
        for (const s of orderResult.statuses) {
            let isSelected = "";
            if (s === o.status) {
                isSelected = "selected";
            }
            statusOptions += `<option ${isSelected}>${s}</option>`;
        }

        html += `<tr>
            <td>${o.id}</td>
            <td>${esc(o.created_at)}</td>
            <td>${esc(o.product)}</td>
            <td>${o.qty}</td>
            <td>${esc(o.client)}</td>
            <td><select onchange="setStatus(${o.id}, this.value)">${statusOptions}</select></td>
            <td><button onclick="delOrder(${o.id})">Dzēst</button></td>
        </tr>`;
    }
    html += "</table>";

    content.innerHTML = html;
}

async function showPlace() {
    const result = await api("products_list");

    let html = `<h2>Preču izvietošana</h2>
        <table><tr><th>Prece</th><th>Daudz.</th><th>Atrašanās vieta</th><th></th></tr>`;

    for (const p of result.products) {
        html += `<tr>
            <td>${esc(p.name)}</td>
            <td>${p.qty}</td>
            <td><input id="loc${p.id}" value="${esc(p.location)}" placeholder="piem. A-2-3" required maxlength="30"></td>
            <td><button onclick="placeProduct(${p.id})">Saglabāt</button></td>
        </tr>`;
    }
    html += "</table>";

    content.innerHTML = html;
}

// saraksts no jau sagatavotiem <li> elementiem
function makeList(items) {
    if (items.length === 0) {
        return "<p>—</p>";
    }
    return "<ul>" + items.join("") + "</ul>";
}

async function showReport() {
    const r = await api("report");

    const lowItems = [];
    for (const p of r.low_stock) {
        lowItems.push(`<li>${esc(p.name)} (${esc(p.code)}) — ${p.qty}</li>`);
    }

    const noPlaceItems = [];
    for (const p of r.no_place) {
        noPlaceItems.push(`<li>${esc(p.name)}</li>`);
    }

    const orderItems = [];
    for (const status in r.orders) {
        orderItems.push(`<li>${esc(status)}: ${r.orders[status]}</li>`);
    }

    const logItems = [];
    for (const l of r.log) {
        let moved = "";
        if (l.from_loc) {
            moved = "(" + esc(l.from_loc) + " → " + esc(l.to_loc) + ")";
        }
        logItems.push(`<li>${esc(l.created_at)} — ${esc(l.action)}: ${esc(l.product)} ${moved}, ${esc(l.by_user)}</li>`);
    }

    content.innerHTML = `<h2>Noliktavas pārskats</h2>
        <p>Ģenerēts: ${esc(r.date)}</p>
        <p>Preču veidu skaits: <b>${r.products.length}</b> | Kopējais daudzums: <b>${r.total_qty}</b> |
           Kopējā noliktavas vērtība: <b>${money(r.total_value)}</b></p>
        <h3>Zems atlikums (&lt; 5)</h3>${makeList(lowItems)}
        <h3>Preces bez atrašanās vietas</h3>${makeList(noPlaceItems)}
        <h3>Pasūtījumi pēc statusa</h3>${makeList(orderItems)}
        <h3>Preču kustības vēsture (pēdējās 10)</h3>${makeList(logItems)}
        <button onclick="window.print()">Drukāt / saglabāt PDF</button>`;
}

// visi skati vienā sarakstā, lai show() varētu atrast pēc nosaukuma
const views = {
    home: showHome,
    products: showProducts,
    suppliers: showSuppliers,
    users: showUsers,
    orders: showOrders,
    place: showPlace,
    report: showReport
};


// ---------- preces ----------

function filterProducts() {
    lastFilter = { q: val("fq"), category: val("fcat") };
    showProducts();
}

function clearFilter() {
    lastFilter = { q: "", category: "" };
    showProducts();
}

function saveProduct() {
    if (!valid(["pcode", "pname", "pprice", "pqty", "ploc"])) {
        return;
    }
    act("product_save", {
        id: val("pid"),
        code: val("pcode"),
        name: val("pname"),
        category: val("pcat"),
        price: val("pprice"),
        qty: val("pqty"),
        location: val("ploc"),
        supplier_id: val("psup")
    }, showProducts);
}

// ieliek preces datus formā, lai tos var mainīt
function editProduct(id) {
    let p = null;
    for (const item of currentProducts) {
        if (item.id == id) {
            p = item;
        }
    }
    if (p === null) {
        return;
    }

    document.getElementById("pid").value = p.id;
    document.getElementById("pcode").value = p.code;
    document.getElementById("pname").value = p.name;
    document.getElementById("pcat").value = p.category || "";
    document.getElementById("pprice").value = p.price;
    document.getElementById("pqty").value = p.qty;
    document.getElementById("ploc").value = p.location;
    document.getElementById("psup").value = p.supplier_id || "";
}

function delProduct(id) {
    if (confirm("Dzēst produktu?")) {
        act("product_delete", { id: id }, showProducts);
    }
}

function receiveProduct(id) {
    const qty = prompt("Saņemtais daudzums (jauna piegāde):");
    if (qty === null || qty === "") {
        return;
    }
    act("product_receive", { id: id, qty: qty }, showProducts);
}


// ---------- piegādātāji ----------

function saveSupplier() {
    if (!valid(["sname"])) {
        return;
    }
    act("supplier_save", {
        id: val("sid"),
        name: val("sname"),
        contact: val("scontact")
    }, showSuppliers);
}

// ieliek piegādātāja datus formā
function editSupplier(id) {
    for (const s of currentSuppliers) {
        if (s.id == id) {
            document.getElementById("sid").value = s.id;
            document.getElementById("sname").value = s.name;
            document.getElementById("scontact").value = s.contact || "";
        }
    }
}

function delSupplier(id) {
    if (confirm("Dzēst piegādātāju?")) {
        act("supplier_delete", { id: id }, showSuppliers);
    }
}


// ---------- lietotāji ----------

function addUser() {
    if (!valid(["ulogin", "upass"])) {
        return;
    }
    act("user_create", {
        login: val("ulogin"),
        password: val("upass"),
        newRole: val("urole")
    }, showUsers);
}

// i ir lietotāja numurs tabulā (tas pats, kas laukiem r0, p0, r1, p1...)\
function updUser(i) {
    if (!valid(["p" + i])) {
        return;
    }
    act("user_update", {
        login: currentUsers[i].login,
        newRole: val("r" + i),
        newPassword: val("p" + i)
    }, showUsers);
}

function delUser(i) {
    if (confirm("Dzēst lietotāju?")) {
        act("user_delete", { login: currentUsers[i].login }, showUsers);
    }
}

function addOrder() {
    if (!valid(["oqty", "oclient"])) {
        return;
    }
    act("order_save", {
        product_id: val("oprod"),
        qty: val("oqty"),
        client: val("oclient")
    }, showOrders);
}

function setStatus(id, status) {
    act("order_status", { id: id, status: status }, showOrders);
}

function delOrder(id) {
    if (confirm("Dzēst pasūtījumu?")) {
        act("order_delete", { id: id }, showOrders);
    }
}

function placeProduct(id) {
    if (!valid(["loc" + id])) {
        return;
    }
    act("place", { id: id, location: val("loc" + id) }, showPlace);
}

window.addEventListener("hashchange", show);

loadUser().then(function (ok) {
    if (ok) {
        show();
    }
});
