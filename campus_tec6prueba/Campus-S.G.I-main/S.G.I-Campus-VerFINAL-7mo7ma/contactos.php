<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/config.php';
require __DIR__.'/auth.php';

require_once __DIR__ . '/auth.php';
requirePage('contactos');

$user_id = $_SESSION['dni'];
$pdo = db();

$stmt = $pdo->prepare("SELECT nombre, dni, rol FROM usuarios WHERE dni = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contactos S.G.I</title>
<link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" /> <!-- Ícono (favicon) que aparece en la pestaña -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- STYLES -->
  <link rel="stylesheet" href="css/menuHamburguesa.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <link rel="stylesheet" href="css/ChatFlotante.css">
<style>
/* ================================
   ESTILOS GENERALES
================================ */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: #f3f4f6;
}

/* ================================
   HEADER Y NAVBAR
================================ */
header {
    background-color: #0f172a;
    color: white;
    position: relative;
    z-index: 1001;
}

.logo {
    width: 100px;
    height: auto;
}
.logo2 {
    width: 100px;
    height: auto;
}

.title-box {
    text-align: center;
    flex-grow: 1;
}
.title-box h1 {
    margin: 0;
    font-size: 24px;
}
.title-box h2 {
    margin: 0;
    font-size: 16px;
    font-weight: normal;
}

 h1, h2, h3 {
      text-align: center;
      background: #0f172a;
      color: white;
      margin: 10px;
      padding: 15px;
    }
    h1#tit {
      font-size: 2em;
      border: 5px solid white;
    }

/* ================================
   CONTENIDO PRINCIPAL
================================ */
main { padding: 20px; }
.contacto-lista {
    max-width: 900px;
    margin: auto;
    display: flex;
    gap: 20px;
    justify-content: center;
    align-items: stretch;
}
.cuadro-contacto {
    background-color: white;
    padding: 20px;
    flex: 1;
    min-height: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border-radius: 10px;
    box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
    text-align: center;
}
.cuadro-contacto h3, .cuadro-contacto h4 {
    margin-top: 0;
    color: #0f172a;
}
.cuadro-contacto img {
    width: 250px;
    max-width: 90%;
    border-radius: 15px;
    margin-bottom: 10px;
}
.cuadro-contacto a {
    color: #dd2a7b;
    font-weight: bold;
    text-decoration: none;
}
.cuadro-contacto a:hover {
    text-decoration: underline;
}

footer {
    text-align: center;
    padding: 10px;
    margin-top: 20px;
    font-weight: bold;
    font-size: 16px;
    background-color: #e5e7eb;
}

/* RESPONSIVO */
@media (max-width: 700px) {
    .contacto-lista {
        flex-direction: column;
    }
    .cuadro-contacto {
        min-height: auto;
    }
}
    /* TELEFONO ESCONDER LOGOS */
    @media (max-width: 768px) {
        .logo{ 
        display: none;
}
</style>
</head>
<body>
<header>
    <div class="navbar">
        <!-- Botón menú hamburguesa -->
        <button class="menu-icon" aria-label="Abrir menú" onclick="openMenu()">☰</button>

        <!-- Logo izquierdo -->
        <a href="SGI.php"> <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>

        <!-- Título centrado -->
        <div class="title-box">
            <h1>Contactos E.E.S.T N°6</h1>
            <h2></h2>
        </div>

        <!-- Logo derecho -->
        <img src="imagenes/logotecn6.webp" alt="E.E.S.T N°6" class="logo">

        <!-- Avatar de usuario -->
        <div class="account" id="accountBtn">
            <div class="avatar" id="avatarInitials"></div>
            <div class="account-menu" id="accountMenu">
                <a href="usuario.php">Perfil</a>
                <a href="changepassword.html">Cambiar Contraseña</a>
                <a href="index.html">Cerrar sesión</a>
            </div>
        </div>
    </div>

    <!-- MENÚ HAMBURGUESA -->
    <div class="overlay" id="overlay" onclick="closeMenu(event)">
        <nav class="menu-panel" onclick="event.stopPropagation()">
            <button class="close-btn" aria-label="Cerrar menú" onclick="closeMenu()">×</button>
            <div class="menu-top">
                <a href="SGI.php"> <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
                <h1>S.G.I</h1>
                <h2>Sistema De Gestion Institucional</h2>
            </div>
            <div class="menu-links">
                <a href="SGI.php" onclick="closeMenu()">Inicio</a>
                <a href="lista.alumnos.php" onclick="closeMenu()">Lista de alumnos</a>
                <a href="infoacademica.php" onclick="closeMenu()">Información académica</a>
                <a href="materias.php" onclick="closeMenu()">Materias</a>
                <a href="foro.php" onclick="closeMenu()">Foro</a>
                <a href="asistencia.php" onclick="closeMenu()">Asistencia</a>
            </div>
            <div class="menu-bottom">
                <div class="avatar" id="avatarMenu"></div>
            </div>
        </nav>
    </div>
</header>

<main>
<section class="contacto-lista">
    <div class="cuadro-contacto">
        <h4>Instagram</h4>
        <img src="imagenes/igqr.webp" alt="QR Instagram">
        <p><a href="https://www.instagram.com/tecnica6berazategui" target="_blank">Ir al perfil</a></p>
    </div>
    <div class="cuadro-contacto">
        <h4>Correo Electrónico</h4>
        <p><a href="mailto:eestberazategui6@gmail.com">eestberazategui6@gmail.com</a></p>
    </div>
</section>
</main>
<!-- 🟦 BOTÓN chat--> 
<a href="msg.php"><button id="boton-flotante" >💬</button></a>
<footer>
    <p>&copy; S.G.I</p>
</footer>
<script src="/js/main.js"></script>
<script>
window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? $user['nombre'] ?? 'Usuario');?>"; // Iniciales
    
</script>
</body>
</html>
