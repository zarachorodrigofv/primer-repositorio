document.addEventListener("DOMContentLoaded", () => {

    // --- INICIALES DEL USUARIO ---
    const nombreUsuario = window.APP_USER_NAME || "USUARIO"; // variable global para ingresar luego con php el usuario y sacar sus iniciales.

    const iniciales = nombreUsuario
        .split(" ")
        .map(p => p[0])
        .join("")
        .substring(0, 2)
        .toUpperCase();

    const avatarInitials = document.getElementById("avatarInitials");
    const avatarMenu = document.getElementById("avatarMenu");

    if (avatarInitials) avatarInitials.textContent = iniciales;
    if (avatarMenu) avatarMenu.textContent = iniciales;

    // --- MENÚ DE CUENTA ---
    const accountBtn = document.getElementById("accountBtn");
    const accountMenu = document.getElementById("accountMenu");

    if (accountBtn && accountMenu) {
        accountBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            accountMenu.style.display =
                accountMenu.style.display === "block" ? "none" : "block";
        });

        document.addEventListener("click", () => {
            accountMenu.style.display = "none";
        });
    }

});
  
// --- MENÚ HAMBURGUESA ---
function openMenu() {
    const overlay = document.getElementById('overlay');
    if (overlay) overlay.classList.add('show');
}

function closeMenu(event) {
    if (event && event.target.id !== 'overlay') return;
    const overlay = document.getElementById('overlay');
    if (overlay) overlay.classList.remove('show');
}
