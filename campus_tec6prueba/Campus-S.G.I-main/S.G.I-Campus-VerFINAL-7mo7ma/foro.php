<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$cursoIds = [];
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();

$pdo      = db();
$user_dni = $_SESSION['dni'];
$rol      = $_SESSION['rol'] ?? '';

/* ============================
   DATOS DEL USUARIO
   ============================ */
$stmt = $pdo->prepare("SELECT nombre, dni, rol FROM usuarios WHERE dni = ?");
$stmt->execute([$user_dni]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header("Location: logout.php");
  exit;
}

/* ============================
   AÑO ESCOLAR ACTUAL
   ============================ */
$stmtYear = $pdo->query("SELECT id FROM year_escolar ORDER BY year DESC LIMIT 1");
$yearEscolarActualId = $stmtYear->fetchColumn();
if (!$yearEscolarActualId) {
  $yearEscolarActualId = 1;
}

/* ============================
   CURSOS / AÑOS ACCESIBLES SEGÚN ROL
   (para el formulario de publicar)
   ============================ */

$cursosAccesibles = []; // [ ['id'=>int,'nombre'=>string,'anio'=>string], ... ]
$aniosAccesibles  = []; // array de strings tipo '1ro','2do', etc.

if ($rol === 'directivo') {
  // TODOS los cursos
  $sql = "SELECT 
            c.id,
            cy.year AS anio,
            CONCAT(cy.year,' ', cd.division,
                   IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
          FROM curso c
          JOIN curso_year cy     ON cy.id = c.curso_year_id
          JOIN curso_division cd ON cd.id = c.curso_division_id
          LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
          ORDER BY cy.id, cd.id";
  $stmtCursos = $pdo->query($sql);
  $cursosAccesibles = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cursosAccesibles as $c) {
    if (!in_array($c['anio'], $aniosAccesibles, true)) {
      $aniosAccesibles[] = $c['anio'];
    }
  }

} elseif ($rol === 'preceptor') {
  // Cursos a cargo del preceptor este año escolar
  $sql = "SELECT 
            c.id,
            cy.year AS anio,
            CONCAT(cy.year,' ', cd.division,
                   IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
          FROM preceptor_curso pc
          JOIN curso c           ON c.id = pc.curso_id
          JOIN curso_year cy     ON cy.id = c.curso_year_id
          JOIN curso_division cd ON cd.id = c.curso_division_id
          LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
          WHERE pc.preceptor_dni = ? AND pc.year_escolar_id = ?
          ORDER BY cy.id, cd.id";
  $stmtCursos = $pdo->prepare($sql);
  $stmtCursos->execute([$user_dni, $yearEscolarActualId]);
  $cursosAccesibles = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cursosAccesibles as $c) {
    if (!in_array($c['anio'], $aniosAccesibles, true)) {
      $aniosAccesibles[] = $c['anio'];
    }
  }

} elseif ($rol === 'profesor') {
  // Cursos donde el profe da clases este año
  $sql = "SELECT DISTINCT
            c.id,
            cy.year AS anio,
            CONCAT(cy.year,' ', cd.division,
                   IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
          FROM docente_materia_curso dmc
          JOIN curso_materia cm   ON cm.id = dmc.curso_materia_id
          JOIN curso c            ON c.id = cm.curso_id
          JOIN curso_year cy      ON cy.id = c.curso_year_id
          JOIN curso_division cd  ON cd.id = c.curso_division_id
          LEFT JOIN modalidad mo  ON mo.id = c.modalidad_id
          WHERE dmc.maestro_dni = ? 
            AND cm.year_escolar_id = ?
          ORDER BY cy.id, cd.id";
  $stmtCursos = $pdo->prepare($sql);
  $stmtCursos->execute([$user_dni, $yearEscolarActualId]);
  $cursosAccesibles = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cursosAccesibles as $c) {
    if (!in_array($c['anio'], $aniosAccesibles, true)) {
      $aniosAccesibles[] = $c['anio'];
    }
  }
}

/* ============================
   POSTS VISIBLES SEGÚN ROL
   ============================ */

$posts = [];

if ($rol === 'directivo') {
  // Directivo ve todo
  $sql = "SELECT f.*, u.nombre as autor_nombre, u.rol as autor_rol
          FROM foro f
          LEFT JOIN usuarios u ON f.autor_dni = u.dni
          ORDER BY f.fecha DESC";
  $stmt_posts = $pdo->query($sql);
  $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'alumno') {
  // Cursos y años del alumno
  $sqlCursosAl = "SELECT DISTINCT 
                    aa.curso_id,
                    cy.year AS anio
                  FROM asignado_alumno aa
                  JOIN curso c       ON c.id = aa.curso_id
                  JOIN curso_year cy ON cy.id = c.curso_year_id
                  WHERE aa.alumno_dni = ?
                    AND aa.estado     = 'activo'
                    AND aa.year_escolar_id = ?";
  $stmtCA = $pdo->prepare($sqlCursosAl);
  $stmtCA->execute([$user_dni, $yearEscolarActualId]);
  $cursoIdsAlumno = [];
  $aniosAlumno    = [];
  while ($fila = $stmtCA->fetch(PDO::FETCH_ASSOC)) {
    $cursoIdsAlumno[] = (string)$fila['curso_id'];
    if (!in_array($fila['anio'], $aniosAlumno, true)) {
      $aniosAlumno[] = $fila['anio'];
    }
  }

  $where = ["(f.destino_tipo='general')"];
  $params = [];

// Destino por rol: SOLO alumno
$where[] = "(f.destino_tipo='rol' AND f.destino_valor = 'alumno')";


  // Por curso
  if ($cursoIdsAlumno) {
    $in = implode(',', array_fill(0, count($cursoIdsAlumno), '?'));
    $where[] = "(f.destino_tipo='curso' AND f.destino_valor IN ($in))";
    foreach ($cursoIdsAlumno as $cid) {
      $params[] = $cid;
    }
  }

  // Por año
  if ($aniosAlumno) {
    $in = implode(',', array_fill(0, count($aniosAlumno), '?'));
    $where[] = "(f.destino_tipo='anio' AND f.destino_valor IN ($in))";
    foreach ($aniosAlumno as $a) {
      $params[] = $a;
    }
  }

  $sql = "SELECT f.*, u.nombre as autor_nombre, u.rol as autor_rol
          FROM foro f
          LEFT JOIN usuarios u ON f.autor_dni = u.dni
          WHERE " . implode(" OR ", $where) . "
          ORDER BY f.fecha DESC";
  $stmt_posts = $pdo->prepare($sql);
  $stmt_posts->execute($params);
  $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'familia') {
  // Familia: general + rol familia/alumno
  $sql = "SELECT f.*, u.nombre as autor_nombre, u.rol as autor_rol
          FROM foro f
          LEFT JOIN usuarios u ON f.autor_dni = u.dni
          WHERE f.destino_tipo='general'
             OR (f.destino_tipo='rol' AND f.destino_valor IN ('familia','alumno'))
          ORDER BY f.fecha DESC";
  $stmt_posts = $pdo->query($sql);
  $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'preceptor' || $rol === 'profesor') {
  // Cursos / años accesibles (ya los tenemos de arriba en $cursosAccesibles / $aniosAccesibles)
  $cursoIds = [];
  foreach ($cursosAccesibles as $c) {
    $cursoIds[] = (string)$c['id'];
  }

  // Condiciones base
  $where  = [];
  $params = [];

  // 1) Siempre ver sus propias publicaciones (sin importar destino)
  $where[]  = "(f.autor_dni = ?)";
  $params[] = $user_dni;

  // 2) Publicaciones generales
  $where[] = "(f.destino_tipo='general')";

  // 3) Publicaciones dirigidas a su rol (preceptor / profesor)
  $where[]  = "(f.destino_tipo='rol' AND f.destino_valor = ?)";
  $params[] = $rol;

  // 4) Publicaciones por curso (cursos a los que tiene acceso)
  if ($cursoIds) {
    $in = implode(',', array_fill(0, count($cursoIds), '?'));
    $where[] = "(f.destino_tipo='curso' AND f.destino_valor IN ($in))";
    foreach ($cursoIds as $cid) {
      $params[] = $cid;
    }
  }

  // 5) Publicaciones por año (años accesibles)
  if ($aniosAccesibles) {
    $in = implode(',', array_fill(0, count($aniosAccesibles), '?'));
    $where[] = "(f.destino_tipo='anio' AND f.destino_valor IN ($in))";
    foreach ($aniosAccesibles as $a) {
      $params[] = $a;
    }
  }

  $sql = "SELECT f.*, u.nombre as autor_nombre, u.rol as autor_rol
          FROM foro f
          LEFT JOIN usuarios u ON f.autor_dni = u.dni
          WHERE " . implode(" OR ", $where) . "
          ORDER BY f.fecha DESC";

  $stmt_posts = $pdo->prepare($sql);
  $stmt_posts->execute($params);
  $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

}

?>
<!DOCTYPE html> 
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>S.G.I - Foro Institucional</title>
<link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />
<!-- STYLES -->
<link rel="stylesheet" href="css/menuHamburguesa.css">
<link rel="stylesheet" href="css/navbar.css">
<link rel="stylesheet" href="css/avatar.css">
<link rel="stylesheet" href="css/ChatFlotante.css">
<style>
body { margin:0; font-family: Arial,sans-serif; background:#e5e7eb; }

/* ================================
   HEADER Y NAVBAR
================================ */
.logo, .logo2 { width: 100px; height: auto; }
.title-box { text-align: center; flex-grow: 1; }
.title-box h1 { margin: 0; font-size: 24px; }
.title-box h2 { margin: 0; font-size: 16px; font-weight: normal; }

h1,h2,h3 { text-align:center; background:#0f172a; color:white; margin:10px; padding:15px; }
h1#tit { font-size:2em; border:5px solid white; }

/* CONTENEDOR FORO */
.contenedor-foro { width:80%; margin:30px auto; background:white; padding:25px; border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,0.15); }
.titulo { text-align:center; padding:14px; background:#0f172a; color:white; border-radius:10px; margin-bottom:22px; font-size:22px; }

/* FORMULARIO */
.form-post { background:#f1f5f9; padding:16px; border-radius:12px; margin-bottom:22px; }
.form-post input[type="text"], .form-post textarea, .form-post input[type="file"], .form-post select {
  width:100%; padding:10px; margin:8px 0; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box;
}
.form-post button { background:#1e3a8a; color:white; border:none; padding:12px; width:100%; border-radius:8px; cursor:pointer; font-size:16px; }
.form-post button:hover { background:#2563eb; }

/* PUBLICACION */
.post { background:#fff; border:2px solid #0f172a; padding:14px; border-radius:12px; margin-bottom:18px; }
.post h3 { margin:0 0 8px 0; background:none; color:#0f172a; padding:0; }
.post p { margin:0; white-space:pre-wrap; }
.post img { display:block; margin:10px auto 0 auto; max-width:90%; height:auto; border-radius:8px; }
.post-meta { margin-top:8px; font-size:13px; color:#4b5563; }
.post-edit input,
.post-edit textarea {
  font-family: inherit;
  font-size: 14px;
}

.post-edit button {
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
}

.post-edit button:first-child {
  background:#16a34a;
  color:white;
}

.post-edit button:last-child {
  background:#ef4444;
  color:white;
}

@media(max-width:800px){
  .contenedor-foro { width:94%; margin:16px auto; padding:16px; }
  .logo{display: none;}
  .title-box h1 { font-size:18px; }
}
$posts as $post
</style>
</head>
<body>

<header>
  <div class="navbar">
    <button class="menu-icon" id="menuBtn">☰</button>
    <a href="SGI.php"><img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
    <div class="title-box">
      <h1>S.G.I</h1>
      <h2>Foro Institucional</h2>
    </div>
    <img src="imagenes/logotecn6.webp" alt="E.E.S.T N°6" class="logo2" />
    <div class="account" id="accountBtn">
      <div class="avatar" id="avatarInitials">JL</div>
      <div class="account-menu" id="accountMenu">
        <a href="usuario.php">Mi perfil</a>
        <a href="changepassword.html">Ajustes</a>
        <a href="logout.php">Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>

<div class="overlay" id="overlay">
  <nav class="menu-panel" onclick="event.stopPropagation()">
    <button class="close-btn" id="closeMenuBtn">×</button>
    <div class="menu-top">
      <a href="SGI.php"><img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
      <h1>S.G.I</h1>
      <h2>Sistema De Gestión Institucional</h2>
    </div>
    <div class="menu-links">
      <a href="SGI.php">Inicio</a>
      <a href="lista.alumnos.php">Lista de alumnos</a>
      <a href="infoacademica.php">Informacion academica</a>
      <a href="materias.php">Materias</a>
      <a href="asistencia.php">Asistencia</a>
      <a href="contactos.php">Contacto</a>
    </div>
    <div class="menu-bottom">
      <div class="avatar" id="avatarMenuInitials"></div>
    </div>
  </nav>
</div>

<div class="contenedor-foro">
  <h2 class="titulo">Bienvenido al Foro Institucional</h2>

  <?php if (in_array($rol, ['directivo','preceptor','profesor'])): ?>
  <div class="form-post">
    <input type="text" id="titulo" placeholder="Título de la publicación" />
    <textarea id="contenido" rows="3" placeholder="Escribe la noticia..."></textarea>
    <input
  type="file"
  id="archivo"
  accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.zip,.txt"
/>


    <label for="destino_tipo"><b>Enviar a:</b></label>
    <select id="destino_tipo" onchange="actualizarControlesDestino()">
      <option value="general">General (todos)</option>

      <?php if (!empty($aniosAccesibles)): ?>
        <option value="anio">Año completo</option>
      <?php endif; ?>

      <?php if (!empty($cursosAccesibles)): ?>
        <option value="curso">Curso específico</option>
      <?php endif; ?>

      <option value="rol">Por rol</option>
    </select>

    <div id="destino_extra" style="margin-top:8px;">
      <?php if (!empty($aniosAccesibles)): ?>
      <select id="destino_anio" style="display:none;">
        <?php foreach ($aniosAccesibles as $anio): ?>
          <option value="<?php echo htmlspecialchars($anio); ?>">
            <?php echo htmlspecialchars($anio); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>

      <?php if (!empty($cursosAccesibles)): ?>
      <select id="destino_curso" style="display:none;">
        <?php foreach ($cursosAccesibles as $c): ?>
          <option value="<?php echo htmlspecialchars($c['id']); ?>">
            <?php echo htmlspecialchars($c['nombre']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>

      <select id="destino_rol" style="display:none;">
        <?php if ($rol === 'directivo'): ?>
          <option value="alumno">Alumnos</option>
          <option value="familia">Familias</option>
          <option value="profesor">Profesores</option>
          <option value="preceptor">Preceptores</option>
        <?php elseif ($rol === 'preceptor'): ?>
          <option value="preceptor">Solo preceptores</option>
          <option value="alumno">Alumnos</option>
          <option value="familia">Familias</option>
        <?php elseif ($rol === 'profesor'): ?>
          <option value="profesor">Solo profesores</option>
          <option value="alumno">Alumnos</option>
        <?php endif; ?>
      </select>
    </div>

    <button onclick="crearPublicacion()">Publicar</button>
  </div>
  <?php endif; ?>
</div>
<?php if ($rol === 'directivo'): ?>
<div class="contenedor-foro" style="margin-bottom:10px;">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <span><b>Filtros:</b></span>
    <select id="filtroRol">
      <option value="">Todos los roles</option>
      <option value="alumno">Alumnos</option>
      <option value="familia">Familias</option>
      <option value="profesor">Profesores</option>
      <option value="preceptor">Preceptores</option>
      <option value="directivo">Directivos</option>
    </select>

    <select id="filtroDestino">
      <option value="">Todos los destinos</option>
      <option value="general">General</option>
      <option value="rol">Por rol</option>
      <option value="anio">Por año</option>
      <option value="curso">Por curso</option>
    </select>
  </div>
</div>
<?php endif; ?>

<div id="listaPosts">
  <?php if (empty($posts)): ?>
    <div class="contenedor-foro">
      <p>No hay publicaciones en el foro todavía.</p>
    </div>
  <?php else: ?>
    <?php
// Mapa id_curso => nombre para mostrar
$cursosMap = [];
if (!empty($cursosAccesibles)) {
    foreach ($cursosAccesibles as $c) {
        $cursosMap[(string)$c['id']] = $c['nombre'];
    }
}

// función para mostrar destino legible
function destinoLegible($post, $cursosMap) {
    $tipo  = $post['destino_tipo'];
    $valor = $post['destino_valor'];

    if ($tipo === 'general' || $valor === null || $valor === '') {
        return 'General (todos)';
    }
    if ($tipo === 'rol') {
        return 'Para rol: ' . ucfirst($valor);
    }
    if ($tipo === 'anio') {
        return 'Para año: ' . htmlspecialchars($valor);
    }
    if ($tipo === 'curso') {
        if (isset($cursosMap[$valor])) {
            return 'Para curso: ' . $cursosMap[$valor];
        }
        return 'Para curso ID: ' . $valor;
    }
    return '';
}
?>

   <?php foreach ($posts as $post): ?>
  <div class="contenedor-foro post"
       data-post-id="<?php echo (int)$post['id']; ?>"
       data-autor-rol="<?php echo htmlspecialchars($post['autor_rol']); ?>"
       data-destino-tipo="<?php echo htmlspecialchars($post['destino_tipo']); ?>"
       data-destino-valor="<?php echo htmlspecialchars($post['destino_valor']); ?>">

    <!-- MODO LECTURA -->
    <div class="post-view">
      <h3 class="post-titulo">
        <?php echo htmlspecialchars($post['titulo']); ?>
        <?php if (!empty($post['editado'])): ?>
          <small style="font-size:12px;color:#6b7280;">(editado)</small>
        <?php endif; ?>
      </h3>

      <p class="post-texto">
        <?php echo nl2br(htmlspecialchars($post['contenido'])); ?>
      </p>

      <?php if (!empty($post['imagen'])): ?>
        <?php
          // Ruta guardada en la BD (ej: "uploads_foro/archivo_xyz.pdf")
          $ruta = htmlspecialchars($post['imagen'], ENT_QUOTES, 'UTF-8');

          // Extensión del archivo
          $ext  = strtolower(pathinfo($post['imagen'], PATHINFO_EXTENSION));
          $esImagen = in_array($ext, ['jpg','jpeg','png','gif','webp'], true);

          // Nombre bonito para descargar
          $nombreDescarga = !empty($post['archivo_nombre_original'])
              ? $post['archivo_nombre_original']
              : basename($post['imagen']);
        ?>

        <?php if ($esImagen): ?>
          <!-- Mostrar imagen -->
          <img src="<?php echo $ruta; ?>" alt="Imagen de la publicación">
        <?php endif; ?>

        <!-- Mostrar SIEMPRE el enlace de descarga, sea imagen o documento -->
        <p style="margin-top:6px;">
          📎 Archivo adjunto:
          <a href="<?php echo $ruta; ?>"
             download="<?php echo htmlspecialchars($nombreDescarga, ENT_QUOTES, 'UTF-8'); ?>">
            Descargar <?php echo htmlspecialchars($nombreDescarga, ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </p>
      <?php endif; ?>

      <div class="post-meta">
        Publicado por: <?php echo htmlspecialchars($post['autor_nombre'] ?? 'Anónimo'); ?>
        | Rol: <?php echo htmlspecialchars($post['autor_rol']); ?>
        | Fecha: <?php echo date('d/m/Y H:i', strtotime($post['fecha'])); ?>
      </div>

      <?php if ($rol === 'directivo' || $user_dni == $post['autor_dni']): ?>
        <div class="post-acciones" style="margin-top:8px;font-size:13px;">
          <button type="button"
                  onclick="entrarEdicion(<?php echo (int)$post['id']; ?>)"
                  style="margin-right:6px;">✏️ Editar</button>
          <button type="button"
                  onclick="eliminarPost(<?php echo (int)$post['id']; ?>)">🗑 Eliminar</button>
        </div>
      <?php endif; ?>
    </div>

    <!-- MODO EDICIÓN -->
    <div class="post-edit" style="display:none; margin-top:8px;">
      <input type="text"
             class="edit-titulo"
             style="width:100%;padding:6px;margin-bottom:6px;border-radius:6px;border:1px solid #cbd5e1;">

      <textarea class="edit-texto"
                rows="4"
                style="width:100%;padding:6px;border-radius:6px;border:1px solid #cbd5e1;"></textarea>

      <div style="margin-top:6px;text-align:right;">
        <button type="button"
                onclick="guardarEdicion(<?php echo (int)$post['id']; ?>)"
                style="margin-right:6px;">💾 Guardar</button>
        <button type="button"
                onclick="cancelarEdicion(<?php echo (int)$post['id']; ?>)">✖ Cancelar</button>
      </div>
    </div>

  </div>
<?php endforeach; ?>


  <?php endif; ?>
</div>

<!-- BOTÓN CHAT -->
<a href="msg.php"><button id="boton-flotante">💬</button></a>

<script src="/js/main.js"></script>
<script>
window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? $user['nombre'] ?? 'Usuario');?>"; // Iniciales

/* CONTROL DE DESTINO (mostrar select correcto) */
function actualizarControlesDestino() {
  const tipo  = document.getElementById('destino_tipo').value;
  const selAn = document.getElementById('destino_anio');
  const selCu = document.getElementById('destino_curso');
  const selRo = document.getElementById('destino_rol');

  if (selAn) selAn.style.display = 'none';
  if (selCu) selCu.style.display = 'none';
  if (selRo) selRo.style.display = 'none';

  if (tipo === 'anio' && selAn) selAn.style.display = 'block';
  if (tipo === 'curso' && selCu) selCu.style.display = 'block';
  if (tipo === 'rol' && selRo)   selRo.style.display = 'block';
}
    
/*basicamente para q el dire vea bien las cosas*/
function aplicarFiltros() {
  const selRol   = document.getElementById('filtroRol');
  const selDest  = document.getElementById('filtroDestino');
  const rolVal   = selRol ? selRol.value : '';
  const destVal  = selDest ? selDest.value : '';

  document.querySelectorAll('#listaPosts .post').forEach(div => {
    const autorRol  = div.getAttribute('data-autor-rol') || '';
    const destTipo  = div.getAttribute('data-destino-tipo') || '';

    let visible = true;

    if (rolVal && autorRol !== rolVal) visible = false;
    if (destVal && destTipo !== destVal) visible = false;

    div.style.display = visible ? 'block' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const fr = document.getElementById('filtroRol');
  const fd = document.getElementById('filtroDestino');
  if (fr) fr.addEventListener('change', aplicarFiltros);
  if (fd) fd.addEventListener('change', aplicarFiltros);
});

// PUBLICACIONES
function crearPublicacion() {
  const titulo    = document.getElementById("titulo").value.trim();
  const contenido = document.getElementById("contenido").value.trim();
  const tipo      = document.getElementById("destino_tipo").value;
  const archivoInput = document.getElementById("archivo");
  const archivo   = archivoInput ? archivoInput.files[0] : null;

  // ⚠️ Validación básico de campos de texto
  if (!titulo || !contenido) {
    alert("Completa título y contenido.");
    return;
  }

  // ⚠️ Límite de 10 MB
  if (archivo && archivo.size > 10 * 1024 * 1024) { // 10 MB
    alert("El archivo es muy pesado (máx 10 MB). Elegí uno más liviano.");
    return; // no enviamos nada
  }

  // Destino (anio / curso / rol)
  let destino_valor = "";
  if (tipo === "anio") {
    const sel = document.getElementById("destino_anio");
    if (sel && sel.value) destino_valor = sel.value;
  } else if (tipo === "curso") {
    const sel = document.getElementById("destino_curso");
    if (sel && sel.value) destino_valor = sel.value;
  } else if (tipo === "rol") {
    const sel = document.getElementById("destino_rol");
    if (sel && sel.value) destino_valor = sel.value;
  }

  // Armamos FormData
  const formData = new FormData();
  formData.append("titulo", titulo);
  formData.append("contenido", contenido);
  formData.append("destino_tipo", tipo);
  formData.append("destino_valor", destino_valor);

  // 👇 clave "archivo" para que coincida con $_FILES['archivo']
  if (archivo) {
    formData.append("archivo", archivo);
  }

  fetch("foro_guardar_post.php", {
    method: "POST",
    body: formData
  })
    .then(r => r.text())
    .then(t => {
      if (t === "ok") {
        location.reload();
      } else {
        alert("Error: " + t);
      }
    })
    .catch(error => {
      alert("Error al publicar: " + error);
    });
}

function eliminarPost(id) {
  if (!confirm("¿Eliminar esta publicación?")) return;

  const fd = new FormData();
  fd.append('id', id);

  fetch('foro_eliminar_post.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.text())
  .then(t => {
    if (t.trim() === 'ok') {
      const postDiv = document.querySelector('[data-post-id="'+id+'"]');
      if (postDiv) postDiv.remove();
    } else {
      alert('Error al eliminar: ' + t);
    }
  })
  .catch(e => alert('Error de red: ' + e));
}

function editarPost(id) {
  const postDiv = document.querySelector('[data-post-id="'+id+'"]');
  if (!postDiv) return;

  const tituloEl = postDiv.querySelector('h3');
  const textoEl  = postDiv.querySelector('p');

  const tituloActual = (tituloEl ? tituloEl.textContent : '').replace('(editado)', '').trim();
  const contenidoActual = textoEl ? textoEl.textContent : '';

  const nuevoTitulo = prompt("Nuevo título:", tituloActual);
  if (nuevoTitulo === null) return;

  const nuevoContenido = prompt("Nuevo contenido:", contenidoActual);
  if (nuevoContenido === null) return;

  const fd = new FormData();
  fd.append('id', id);
  fd.append('titulo', nuevoTitulo);
  fd.append('contenido', nuevoContenido);

  fetch('foro_editar_post.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.text())
  .then(t => {
    if (t.trim() === 'ok') {
      // Actualizo en pantalla
      if (tituloEl) {
        tituloEl.innerHTML = '';
        const hText = document.createTextNode(nuevoTitulo + ' ');
        tituloEl.appendChild(hText);
        const small = document.createElement('small');
        small.style.fontSize = '12px';
        small.style.color = '#6b7280';
        small.textContent = '(editado)';
        tituloEl.appendChild(small);
      }
      if (textoEl) {
        textoEl.textContent = nuevoContenido;
      }
    } else {
      alert('Error al editar: ' + t);
    }
  })
  .catch(e => alert('Error de red: ' + e));
}
function entrarEdicion(id) {
  const post = document.querySelector('[data-post-id="'+id+'"]');
  if (!post) return;

  const view  = post.querySelector('.post-view');
  const edit  = post.querySelector('.post-edit');
  const h3    = post.querySelector('.post-titulo');
  const p     = post.querySelector('.post-texto');
  const inTit = post.querySelector('.edit-titulo');
  const txt   = post.querySelector('.edit-texto');

  if (!view || !edit || !h3 || !p || !inTit || !txt) return;

  // texto sin "(editado)"
  let tituloActual = h3.textContent || '';
  tituloActual = tituloActual.replace('(editado)', '').trim();

  // contenido plano (sacamos los <br>)
  const divAux = document.createElement('div');
  divAux.innerHTML = p.innerHTML;
  const contenidoActual = divAux.textContent || divAux.innerText || '';

  inTit.value = tituloActual;
  txt.value   = contenidoActual;

  view.style.display = 'none';
  edit.style.display = 'block';
}

function cancelarEdicion(id) {
  const post = document.querySelector('[data-post-id="'+id+'"]');
  if (!post) return;

  const view = post.querySelector('.post-view');
  const edit = post.querySelector('.post-edit');

  if (view) view.style.display = 'block';
  if (edit) edit.style.display = 'none';
}

function guardarEdicion(id) {
  const post = document.querySelector('[data-post-id="'+id+'"]');
  if (!post) return;

  const inTit = post.querySelector('.edit-titulo');
  const txt   = post.querySelector('.edit-texto');
  const nuevoTitulo   = (inTit?.value || '').trim();
  const nuevoContenido= (txt?.value || '').trim();

  if (!nuevoTitulo || !nuevoContenido) {
    alert('Completá título y contenido.');
    return;
  }

  const fd = new FormData();
  fd.append('id', id);
  fd.append('titulo', nuevoTitulo);
  fd.append('contenido', nuevoContenido);

  fetch('foro_editar_post.php', {
    method: 'POST',
    body: fd
  })
  .then(r => r.text())
  .then(t => {
    if (t.trim() !== 'ok') {
      alert('Error al editar: ' + t);
      return;
    }

    // Actualizar vista sin recargar
    const view    = post.querySelector('.post-view');
    const edit    = post.querySelector('.post-edit');
    const h3      = post.querySelector('.post-titulo');
    const p       = post.querySelector('.post-texto');

    if (h3) {
      h3.innerHTML = '';
      const textNode = document.createTextNode(nuevoTitulo + ' ');
      h3.appendChild(textNode);
      const small = document.createElement('small');
      small.style.fontSize = '12px';
      small.style.color = '#6b7280';
      small.textContent = '(editado)';
      h3.appendChild(small);
    }
    if (p) {
      p.innerHTML = nuevoContenido.replace(/\n/g, '<br>');
    }

    if (view) view.style.display = 'block';
    if (edit) edit.style.display = 'none';
  })
  .catch(e => {
    alert('Error de red al editar: ' + e);
  });
}

// Inicializar select de destino
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('destino_tipo')) {
    actualizarControlesDestino();
  }
});
</script>
</body>
</html>
