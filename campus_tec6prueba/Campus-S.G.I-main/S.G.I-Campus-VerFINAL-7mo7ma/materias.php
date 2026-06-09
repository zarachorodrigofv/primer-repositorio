<?php
require_once 'auth.php';
requireLogin();
require_once 'config.php';
require_once 'helpers_academico.php';

$pdo      = db();
$rol      = strtolower(trim($_SESSION['rol']));
$dni      = (int)($_SESSION['dni'] ?? 0);
$usuario  = $_SESSION['usuario'] ?? '';
$yearId   = currentYearEscolarId($pdo);
$modsUser = modalidadesPermitidasPorRol($rol, $dni, $yearId);

$verListado = in_array($rol, ['profesor','preceptor','directivo'], true);
$verPanel   = in_array($rol, ['preceptor','directivo'], true);

function tieneMod(array $mods, string $key): bool {
    return !empty($mods[$key]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Materias - S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />

  <link rel="stylesheet" href="css/menuHamburguesa.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <link rel="stylesheet" href="css/ChatFlotante.css">

  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#d8d7d7;}
    .sgi-title{margin-left:auto;font-weight:bold;padding:8px 15px;font-size:28px;}
    main{max-width:1100px;margin:20px auto;padding:0 10px;}
    h1,h2{text-align:center;color:#fff;background:#0f172a;margin:10px;padding:15px;border-radius:8px;}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:20px;}
    .card{background:#fff;border-radius:12px;overflow:hidden;cursor:pointer;
          box-shadow:0 4px 12px rgba(0,0,0,0.12);transition:.2s;}
    .card:hover{transform:translateY(-3px);}
    .card img{width:100%;height:160px;object-fit:cover;}
    .card h3{text-align:center;margin:0;padding:12px;}
    .mensaje-vacio{text-align:center;margin-top:25px;font-weight:bold;}
  </style>
</head>
<body>

<!-- NAVBAR igual a SGI.php -->
<div class="navbar">
  <button class="menu-icon" aria-label="Abrir menú" onclick="openMenu()">☰</button>

  <div class="menu">
    <?php if ($verListado): ?>
      <a href="asistencia.php" onclick="closeMenu()">Asistencia</a>
      <a href="lista.alumnos.php">Lista de alumnos</a>
    <?php endif; ?>
    <?php if ($verPanel): ?>
      <a href="panel_control.php">Panel de Control</a>
    <?php endif; ?>
    <a href="infoacademica.php">Información académica</a>
    <a href="materias.php">Materias</a>
    <a href="foro.php" onclick="closeMenu()">Foro</a>
    <a href="msg.php">Mensajería</a>
    <a href="contactos.php">Contactos</a>
  </div>

  <span class="sgi-title">S.G.I</span>

  <div class="account" id="accountBtn">
    <div class="avatar" id="avatarInitials"></div>
    <div class="account-menu" id="accountMenu">
      <a href="usuario.php">Perfil</a>
      <a href="changepassword.html">Cambiar Contraseña</a>
      <a href="logout.php">Cerrar sesión</a>
    </div>
  </div>
</div>

<!-- MENÚ HAMBURGUESA -->
<div class="overlay" id="overlay" onclick="closeMenu(event)">
  <nav class="menu-panel" onclick="event.stopPropagation()">
    <button class="close-btn" aria-label="Cerrar menú" onclick="closeMenu()">×</button>

    <div class="menu-top">
      <a href="SGI.php"><img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"></a>
      <h1>S.G.I</h1>
      <h2>Sistema de Gestión Institucional</h2>
    </div>

    <div class="menu-links">
      <a href="SGI.php" onclick="closeMenu()">Inicio</a>
      <?php if ($verListado): ?>
        <a href="lista.alumnos.php" onclick="closeMenu()">Lista de alumnos</a>
        <a href="asistencia.php" onclick="closeMenu()">Asistencia</a>
      <?php endif; ?>
      <?php if ($verPanel): ?>
        <a href="panel_control.php">Panel de Control</a>
      <?php endif; ?>
      <a href="infoacademica.php" onclick="closeMenu()">Información académica</a>
      <a href="materias.php" onclick="closeMenu()">Materias</a>
      <a href="foro.php" onclick="closeMenu()">Foro</a>
      <a href="contactos.php" onclick="closeMenu()">Contactos</a>
    </div>

    <div class="menu-bottom">
      <div class="avatar" id="avatarMenu"></div>
    </div>
  </nav>
</div>

<main>
  <h1>Materias</h1>
  <h2>Elegí la modalidad</h2>

  <div class="grid">
    <?php if (tieneMod($modsUser, 'cb')): ?>
      <div class="card" onclick="location.href='ciclo_basico.php'">
        <img src="https://img.freepik.com/fotos-premium/surtido-utiles-escolares-pizarra-negra-concepto-regreso-escuela_123827-12156.jpg" alt="">
        <h3>Ciclo Básico</h3>
      </div>
    <?php endif; ?>

    <?php if (tieneMod($modsUser, 'maderero')): ?>
      <div class="card" onclick="location.href='tecnico_maderero.php'">
        <img src="https://img.freepik.com/fotos-premium/diseno-dibujo-tecnico-marcaje-fabricacion-marco-madera_749851-1675.jpg" alt="">
        <h3>Técnico en Maderero</h3>
      </div>
    <?php endif; ?>

    <?php if (tieneMod($modsUser, 'maquinaria')): ?>
      <div class="card" onclick="location.href='tecnico_maquinaria.php'">
        <img src="https://img.freepik.com/fotos-premium/ingenieria-precision-entorno-fabrica-fabricacion-industrial-metales-maquinaria-ferramentas-torno_875722-38192.jpg" alt="">
        <h3>Técnico en Maquinaria</h3>
      </div>
    <?php endif; ?>

    <?php if (empty($modsUser)): ?>
      <p class="mensaje-vacio">No tenés modalidades asignadas para el ciclo lectivo actual.</p>
    <?php endif; ?>
  </div>
</main>

<a href="msg.php"><button id="boton-flotante">💬</button></a>

<script>
  window.APP_USER_NAME = "<?= htmlspecialchars($usuario ?: 'Usuario'); ?>";
</script>
<script src="/js/main.js"></script>
</body>
</html>

