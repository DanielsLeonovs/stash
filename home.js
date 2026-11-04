function showMessage(id, text, isError = true) {
    const el = document.getElementById(id);
    el.textContent = text;
    el.classList.toggle("success", !isError);
}

async function api(action, data) {
    const response = await fetch("api.php?action=" + action, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    return response.json();
}

document.getElementById("loginForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const login = document.getElementById("LoginName").value.trim();
    const password = document.getElementById("LoginPass").value;

    const result = await api("login", { login, password });
    if (!result.success) {
        showMessage("loginmessage", result.message);
        return;
    }
    
    localStorage.setItem("currentUser", result.user.login);
    window.location.href = "stash.html";
});

document.getElementById("registerForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const login = document.getElementById("RegLogin").value.trim();
    const password = document.getElementById("RegPass").value;

    const result = await api("register", { login, password});
    showMessage("regmessage", result.message, !result.success);
    if (result.success) {
        e.target.reset();
        document.getElementById("LoginName").value = login;
    }
});