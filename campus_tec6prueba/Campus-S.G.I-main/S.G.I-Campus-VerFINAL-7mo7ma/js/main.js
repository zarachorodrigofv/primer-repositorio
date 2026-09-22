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
function setMenuState(isOpen) {
    const overlay = document.getElementById('overlay');
    if (!overlay) return;
    overlay.classList.toggle('show', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
}

function openMenu() {
    setMenuState(true);
}

function closeMenu(event) {
    const overlay = document.getElementById('overlay');
    if (!overlay) return;
    const shouldClose = !event || event.target === overlay || event.target.id === 'overlay';
    if (shouldClose) {
        setMenuState(false);
    }
}

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeMenu();
    }
});
