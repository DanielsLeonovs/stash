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