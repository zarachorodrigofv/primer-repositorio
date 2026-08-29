
<?php
require_once __DIR__ . '/auth.php';
requirePage('materias');
require_once 'config.php';
require_once 'helpers_academico.php';

$pdo    = db();
$rol    = strtolower(trim($_SESSION['rol']));
$verForo = tieneAccesoForo();
$verMensajeria = tieneAccesoMensajeria();
$verInfo = in_array($rol, ROLES_INFO, true);
$verContactos = in_array($rol, ROLES_CONTACTOS, true);
$dni    = (int)($_SESSION['dni'] ?? 0);
$usuario = $_SESSION['usuario'] ?? '';
$yearId = currentYearEscolarId($pdo);

$cursoId = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
if ($cursoId <= 0) {
    echo "Curso no especificado.";
    exit;
}

$verListado = tieneAccesoListado();
$verPanel   = tieneAccesoPanel();

if (!usuarioTieneAccesoACurso($rol, $dni, $cursoId, $yearId)) {
    http_response_code(403);
    echo "<p style='text-align:center;margin-top:40px;font-family:sans-serif'>No tenés acceso a este curso.</p>";
    exit;
}

$infoCurso = infoCurso($cursoId);
if (!$infoCurso) {
    echo "Curso inexistente.";
    exit;
}

$modsUser = modalidadesPermitidasPorRol($rol, $dni, $yearId);
$materias = materiasDeCursoParaUsuario($rol, $dni, $cursoId, $yearId);
$cursoLabel = htmlspecialchars($infoCurso['year'] . ' ' . $infoCurso['division']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Materias del curso - S.G.I</title>
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
    .card img{width:100%;height:150px;object-fit:cover;}
    .card h3{text-align:center;margin:0;padding:12px;}
    .boton-volver{
      margin-top:14px;padding:8px 14px;background:#555;color:white;border:none;
      border-radius:8px;cursor:pointer;font-size:13px;
      display:inline-block;
    }
    .boton-volver:hover{background:#333;}
  </style>
</head>
<body>

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
    <?php if ($verInfo): ?><a href="infoacademica.php">Información académica</a><?php endif; ?>
    <a href="materias.php">Materias</a>
    <?php if ($verForo): ?><a href="foro.php" onclick="closeMenu()">Foro</a><?php endif; ?>
    <?php if ($verMensajeria): ?><a href="msg.php">Mensajería</a><?php endif; ?>
    <?php if ($verContactos): ?><a href="contactos.php">Contactos</a><?php endif; ?>
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
      <?php if ($verInfo): ?><a href="infoacademica.php" onclick="closeMenu()">Información académica</a><?php endif; ?>
      <a href="materias.php" onclick="closeMenu()">Materias</a>
      <?php if ($verForo): ?><a href="foro.php" onclick="closeMenu()">Foro</a><?php endif; ?>
      <?php if ($verContactos): ?><a href="contactos.php" onclick="closeMenu()">Contactos</a><?php endif; ?>
    </div>

    <div class="menu-bottom">
      <div class="avatar" id="avatarMenu"></div>
    </div>
  </nav>
</div>

<main>
  <h1>Materias del curso <?= $cursoLabel; ?></h1>
  <h2>Elegí una materia</h2>

  <div class="grid">
    <?php if (empty($materias)): ?>
      <p style="grid-column:1/-1;text-align:center;margin-top:20px;">
        Todavía no hay materias asignadas a este curso para tu rol.
      </p>
    <?php else: ?>
      <?php foreach ($materias as $m): ?>
<div class="card"
  <?php if (in_array($rol, ['profesor','preceptor','directivo','admin','root'], true)): ?>
    onclick="location.href='notas_curso.php?curso_id=<?= $cursoId; ?>&materia_id=<?= (int)$m['id']; ?>'"
  <?php endif; ?>
>



          <img src="https://img.freepik.com/fotos-premium/surtido-utiles-escolares-pizarra-negra-concepto-regreso-escuela_123827-12156.jpg" alt="">
          <h3><?= htmlspecialchars($m['nombre']); ?></h3>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <button class="boton-volver" onclick="history.back()">⬅ Volver</button>
</main>

<a href="msg.php"><button id="boton-flotante">💬</button></a>
<script>
  window.APP_USER_NAME = "<?= htmlspecialchars($usuario ?: 'Usuario'); ?>";
</script>
<script src="/js/main.js"></script>
</body>
</html>
