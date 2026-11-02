const role = localStorage.getItem("role");

    if (!role) {
        window.location.href = "home.html";
    } 

function admin() {
    localStorage.setItem("role", "admin");
    window.location.href = "stash.html";
}

function worker() {
    localStorage.setItem("role", "worker");
    window.location.href = "stash.html";
}

function sorter() {
    localStorage.setItem("role", "sorter");
    window.location.href = "stash.html";
}

document.addEventListener("DOMContentLoaded", function () {
    const role = localStorage.getItem("role");
    const menu = document.getElementById("menu");

    if (!menu) return;

    let buttons = [];

    if (role === "admin") {
        buttons = [
            ["⌂", "Sākums", "stash.html"],
            ["＋", "Pievienot produktu", "#"],
            ["＋", "Pievienot lietotāju", "#"],
            ["♙", "Lietotāji", "#"],
            ["⇥", "Iziet", "logout"]
        ];
    } else if (role === "worker") {
        buttons = [
            ["⌂", "Sākums", "stash.html"],
            ["🚚", "Veikt pasūtījumu", "#"],
            ["▤", "Sagatavot atskaiti", "#"],
            ["⇥", "Iziet", "logout"]
        ];
    } else if (role === "sorter") {
        buttons = [
            ["⌂", "Sākums", "stash.html"],
            ["▤", "Izvietot preces", "#"],
            ["▤", "Sagatavot atskaiti", "#"],
            ["♙", "Datu ievade", "#"],
            ["⇥", "Iziet", "logout"]
        ];
    } else {
        window.location.href = "home.html";
        return;
    }

    menu.innerHTML = "";

    buttons.forEach(function (button) {
        const link = document.createElement("a");
        link.href = button[2] === "logout" ? "#" : button[2];
        link.innerHTML = `<span>${button[0]}</span>${button[1]}`;

        if (button[2] === "logout") {
            link.addEventListener("click", function (event) {
                event.preventDefault();
                localStorage.removeItem("role");
                window.location.href = "home.html";
            });
        }

        menu.appendChild(link);
    });
});



