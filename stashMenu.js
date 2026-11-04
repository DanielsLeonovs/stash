// Loma un CSRF token tiek ņemti no servera sesijas (nevis no localStorage)
let role = null, csrf = null;

const menus = {
    admin: [["⌂", "Sākums", "#home"], ["▤", "Preces", "#products"], ["♙", "Lietotāji", "#users"], ["▤", "Pārskats", "#report"]],
    worker: [["⌂", "Sākums", "#home"], ["▤", "Preces", "#products"], ["🚚", "Pasūtījumi", "#orders"], ["▤", "Pārskats", "#report"]],
    sorter: [["⌂", "Sākums", "#home"], ["▤", "Preces", "#products"], ["▤", "Izvietot preces", "#place"], ["▤", "Pārskats", "#report"]]
};

const ready = (async () => {
    const r = await (await fetch("api.php?action=me")).json();
    if (!r.success) { window.location.href = "home.html"; return; }
    role = r.role;
    csrf = r.csrf;

    const menu = document.getElementById("menu");
    menu.innerHTML = "";
    menus[role].forEach(([icon, text, href]) => {
        const a = document.createElement("a");
        a.href = href;
        a.innerHTML = `<span>${icon}</span>${text}`;
        menu.appendChild(a);
    });

    const out = document.createElement("a");
    out.href = "#";
    out.innerHTML = "<span>⇥</span>Iziet";
    out.addEventListener("click", async (e) => {
        e.preventDefault();
        await fetch("api.php?action=logout", { method: "POST", headers: { "X-CSRF-Token": csrf } });
        window.location.href = "home.html";
    });
    menu.appendChild(out);
})();
