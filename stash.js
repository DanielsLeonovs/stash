const role = localStorage.getItem("role");

        if (!role) {
            window.location.href = "index.html";
        } else {
            document.getElementById("welcome").textContent =
                "Tu esi pieslēdzies kā: " + role;
        }