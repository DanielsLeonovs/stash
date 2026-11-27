
let role = "";
let csrf = "";
const menus = {
    admin: [
        { icon: "⌂", text: "Sākums", href: "#home" },
        { icon: "▤", text: "Preces", href: "#products" },
        { icon: "🏭", text: "Piegādātāji", href: "#suppliers" },
        { icon: "♙", text: "Lietotāji", href: "#users" },
        { icon: "▤", text: "Pārskats", href: "#report" }
    ],
    worker: [
        { icon: "⌂", text: "Sākums", href: "#home" },
        { icon: "▤", text: "Preces", href: "#products" },
        { icon: "🚚", text: "Pasūtījumi", href: "#orders" },
        { icon: "▤", text: "Pārskats", href: "#report" }
    ],
    sorter: [
        { icon: "⌂", text: "Sākums", href: "#home" },
        { icon: "▤", text: "Preces", href: "#products" },
        { icon: "▤", text: "Izvietot preces", href: "#place" },
        { icon: "▤", text: "Pārskats", href: "#report" }
    ]
};

// pajautā serverim, kas ir pieteicies un uzzīmē izvēlni
// atgriež true ja viss kārtībā
async function loadUser() {
    const response = await fetch("api.php?action=me");
    const result = await response.json();

    // ja nav pieteicies aizsūta uz ielogošanās lapu
    if (!result.success) {
        window.location.href = "home.html";
        return false;
    }

    role = result.role;
    csrf = result.csrf;
    drawMenu();
    return true;
}

function drawMenu() {
    const menu = document.getElementById("menu");
    menu.innerHTML = "";

    const items = menus[role];
    for (const item of items) {
        const link = document.createElement("a");
        link.href = item.href;
        link.innerHTML = "<span>" + item.icon + "</span>" + item.text;
        menu.appendChild(link);
    }

    // izejas poga
    const logoutLink = document.createElement("a");
    logoutLink.href = "#";
    logoutLink.innerHTML = "<span>⇥</span>Iziet";
    logoutLink.addEventListener("click", logout);
    menu.appendChild(logoutLink);
}

async function logout(event) {
    event.preventDefault();
    await fetch("api.php?action=logout", {
        method: "POST",
        headers: { "X-CSRF-Token": csrf }
    });
    window.location.href = "home.html";
}
