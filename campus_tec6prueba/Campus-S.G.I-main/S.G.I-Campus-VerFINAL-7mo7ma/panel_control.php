<?php
require_once __DIR__ . '/auth.php';
requireAnyRole(ROLES_PANEL);
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireCsrf();

$rol = currentRole();
$rolesPanel = ['directivo','admin','root','jefe_preceptores','preceptor'];
$rolesAsignarProfes = CAP_ASIGNAR_DOCENTES;

// Capacidades según rol
$puedeAsignarProfes   = in_array($rol, $rolesAsignarProfes, true);
$puedeAsignarPreceptor = in_array($rol, ['directivo','admin','root','jefe_preceptores'], true);
$puedeVincularFamilia  = in_array($rol, CAP_GESTION_ALUMNOS, true);
$esDirectivoOSuperior  = in_array($rol, ['directivo','admin','root'], true);

$dniUsuario = (int)($_SESSION['dni'] ?? 0);

// Conexión
$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
    die("Error de conexión: ".$conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Año escolar actual
$yearActual = (int)date('Y');
$yearEscolarId = 0;
$stmt = $conn->prepare("SELECT id FROM year_escolar WHERE year = ? LIMIT 1");
$stmt->bind_param("i", $yearActual);
$stmt->execute();
$stmt->bind_result($yearEscolarId);
$stmt->fetch();
$stmt->close();
if (!$yearEscolarId) {
    $res = $conn->query("SELECT id FROM year_escolar ORDER BY year DESC LIMIT 1");
    $row = $res->fetch_assoc();
    $yearEscolarId = $row ? (int)$row['id'] : 1;
}

// MENSAJE de feedback
$mensaje = "";

/* =====================================================
   ACCIONES: ASIGNAR / BORRAR
   ===================================================== */

// Asignar materia a profesor en un curso (directivo y preceptor)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_docente') {

    if (!in_array($rol, ['directivo','admin','root','jefe_area','jefe_taller','jefe_departamento'], true)) {
        $mensaje = "No autorizado para asignar materias.";
    } else {
        $maestro_dni = (int)($_POST['maestro_dni'] ?? 0);
        $curso_id    = (int)($_POST['curso_id'] ?? 0);
        $materia_id  = (int)($_POST['materia_id'] ?? 0);

        if ($maestro_dni && $curso_id && $materia_id) {
            // 1) Buscar o crear curso_materia
            $cm_id = 0;
            $stmt = $conn->prepare("SELECT id FROM curso_materia WHERE curso_id=? AND materia_id=? AND year_escolar_id=?");
            $stmt->bind_param("iii", $curso_id, $materia_id, $yearEscolarId);
            $stmt->execute();
            $stmt->bind_result($cm_id);
            $stmt->fetch();
            $stmt->close();

            if (!$cm_id) {
                $stmt = $conn->prepare("INSERT INTO curso_materia (curso_id, materia_id, year_escolar_id) VALUES (?,?,?)");
                $stmt->bind_param("iii", $curso_id, $materia_id, $yearEscolarId);
                if ($stmt->execute()) {
                    $cm_id = $stmt->insert_id;
                }
                $stmt->close();
            }

            if ($cm_id) {
                // 2) Asignar docente
                $stmt = $conn->prepare("SELECT id FROM docente_materia_curso WHERE maestro_dni=? AND curso_materia_id=?");
                $stmt->bind_param("ii", $maestro_dni, $cm_id);
                $stmt->execute();
                $stmt->bind_result($dmc_id);
                $existe = $stmt->fetch();
                $stmt->close();

                if (!$existe) {
                    $stmt = $conn->prepare("INSERT INTO docente_materia_curso (maestro_dni, curso_materia_id) VALUES (?,?)");
                    $stmt->bind_param("ii", $maestro_dni, $cm_id);
                    if ($stmt->execute()) {
                        $mensaje = "Materia asignada al profesor correctamente.";
                    } else {
                        $mensaje = "Error al asignar materia al profesor.";
                    }
                    $stmt->close();
                } else {
                    $mensaje = "Ese profesor ya tiene esa materia en ese curso.";
                }
            } else {
                $mensaje = "No se pudo crear/obtener el curso_materia.";
            }
        } else {
            $mensaje = "Faltan datos para asignar la materia.";
        }
    }
}

// Asignar curso a preceptor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_preceptor') {

    if (!in_array($rol, ['directivo','admin','root','jefe_preceptores'], true)) {
        $mensaje = "No tenés permisos para asignar cursos a preceptores.";
    } else {
        $preceptor_dni = (int)($_POST['preceptor_dni'] ?? 0);
        $curso_id      = (int)($_POST['curso_id'] ?? 0);

        if ($preceptor_dni && $curso_id) {
            $stmt = $conn->prepare("SELECT id FROM preceptor_curso WHERE preceptor_dni=? AND curso_id=? AND year_escolar_id=?");
            $stmt->bind_param("iii", $preceptor_dni, $curso_id, $yearEscolarId);
            $stmt->execute();
            $stmt->bind_result($pc_id);
            $existe = $stmt->fetch();
            $stmt->close();

            if (!$existe) {
                $stmt = $conn->prepare("INSERT INTO preceptor_curso (preceptor_dni, curso_id, year_escolar_id) VALUES (?,?,?)");
                $stmt->bind_param("iii", $preceptor_dni, $curso_id, $yearEscolarId);
                if ($stmt->execute()) {
                    $mensaje = "Curso asignado al preceptor correctamente.";
                } else {
                    $mensaje = "Error al asignar curso al preceptor.";
                }
                $stmt->close();
            } else {
                $mensaje = "Ese preceptor ya tiene ese curso asignado.";
            }
        } else {
            $mensaje = "Faltan datos para asignar el curso.";
        }
    }
}

// Asignar familia a alumno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_familia') {
    if (!in_array($rol, CAP_GESTION_ALUMNOS, true)) {
        $mensaje = 'No tenés permisos para vincular familias.';
    } else {
    $familia_dni = (int)($_POST['familia_dni'] ?? 0);
    $alumno_dni  = (int)($_POST['alumno_dni'] ?? 0);
    $parentesco  = trim($_POST['parentesco'] ?? '');

    if ($familia_dni && $alumno_dni) {
        $resCheck = $conn->query("SHOW TABLES LIKE 'familia_alumno'");
        if ($resCheck && $resCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT id FROM familia_alumno WHERE familia_dni=? AND alumno_dni=?");
            $stmt->bind_param("ii", $familia_dni, $alumno_dni);
            $stmt->execute();
            $stmt->bind_result($fa_id);
            $existe = $stmt->fetch();
            $stmt->close();

            if (!$existe) {
                $stmt = $conn->prepare("INSERT INTO familia_alumno (familia_dni, alumno_dni, parentesco) VALUES (?,?,?)");
                $stmt->bind_param("iis", $familia_dni, $alumno_dni, $parentesco);
                if ($stmt->execute()) {
                    $mensaje = "Familia vinculada al alumno correctamente.";
                } else {
                    $mensaje = "Error al vincular familia y alumno.";
                }
                $stmt->close();
            } else {
                $mensaje = "Esa familia ya está vinculada a ese alumno.";
            }
        } else {
            $mensaje = "Falta crear la tabla familia_alumno en la base de datos.";
        }
    } else {
        $mensaje = "Faltan datos para vincular familia y alumno.";
    }
    }
}

// Borrar asignación docente-materia-curso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'del_dmc' && in_array($rol, ['root','admin','directivo'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM docente_materia_curso WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Asignación de profesor eliminada.";
    }
}

// Borrar asignación preceptor-curso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'del_prec' && in_array($rol, ['root','admin','directivo','jefe_preceptores'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM preceptor_curso WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Asignación de preceptor eliminada.";
    }
}

// Borrar vínculo familia-alumno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'del_fam' && in_array($rol, ['root','admin','directivo'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM familia_alumno WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Vínculo familia–alumno eliminado.";
    }
}

/* =====================================================
   LISTADOS PARA SELECTS
   ===================================================== */

// Profesores
$profesores = [];
$res = $conn->query("SELECT dni, COALESCE(NULLIF(TRIM(nombre), ''), CONCAT('DNI ', dni)) AS nombre FROM usuarios WHERE rol='profesor' ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $profesores[] = $fila; }

// Preceptores
$preceptores = [];
$res = $conn->query("SELECT dni, COALESCE(NULLIF(TRIM(nombre), ''), CONCAT('DNI ', dni)) AS nombre FROM usuarios WHERE rol='preceptor' ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $preceptores[] = $fila; }

// Materias
$materias = [];
$res = $conn->query("SELECT id, nombre FROM materias ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $materias[] = $fila; }

// CURSOS SEGÚN ROL
$cursos = [];

if (in_array($rol, ['directivo','admin','root','jefe_preceptores','jefe_area','jefe_taller','jefe_departamento'], true)) {
    // Roles de gestión ven todos los cursos
    $sqlCursos = "SELECT c.id,
                         CONCAT(cy.year,' ', cd.division,
                                IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
                  FROM curso c
                  JOIN curso_year cy ON cy.id = c.curso_year_id
                  JOIN curso_division cd ON cd.id = c.curso_division_id
                  LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
                  ORDER BY cy.id, cd.id";
    $res = $conn->query($sqlCursos);
    while ($fila = $res->fetch_assoc()) {
        $cursos[] = $fila;
    }

} else if ($rol === 'preceptor') {
    // PRECEPTOR SOLO VE SUS CURSOS
    $sqlCursos = "SELECT c.id,
                         CONCAT(cy.year,' ', cd.division,
                                IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
                  FROM preceptor_curso pc
                  JOIN curso c           ON c.id = pc.curso_id
                  JOIN curso_year cy     ON cy.id = c.curso_year_id
                  JOIN curso_division cd ON cd.id = c.curso_division_id
                  LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
                  WHERE pc.preceptor_dni = ?
                    AND pc.year_escolar_id = ?
                  ORDER BY cy.id, cd.id";
    $stmt = $conn->prepare($sqlCursos);
    $stmt->bind_param("ii", $dniUsuario, $yearEscolarId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $cursos[] = $fila;
    }
    $stmt->close();
}

// Familias (usuarios rol familia)
$familias = [];
$res = $conn->query("SELECT dni, COALESCE(NULLIF(TRIM(nombre), ''), CONCAT('DNI ', dni)) AS nombre FROM usuarios WHERE rol='familia' ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $familias[] = $fila; }

// Alumnos activos
$alumnosActivos = [];
$sqlAlu = "SELECT DISTINCT u.dni, u.nombre
           FROM asignado_alumno aa
           JOIN usuarios u ON u.dni = aa.alumno_dni
           WHERE aa.year_escolar_id = $yearEscolarId
             AND aa.estado = 'activo'
           ORDER BY u.nombre";
$res = $conn->query($sqlAlu);
while ($fila = $res->fetch_assoc()) { $alumnosActivos[] = $fila; }

// Asignaciones existentes
$listaDocentes = [];
$sql = "SELECT dmc.id,
               u.nombre AS profesor,
               u.dni    AS profesor_dni,
               ma.nombre AS materia,
               c.id AS curso_id,
               CONCAT(cy.year,' ', cd.division,
                      IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS curso
        FROM docente_materia_curso dmc
        JOIN usuarios u       ON u.dni = dmc.maestro_dni
        JOIN curso_materia cm ON cm.id = dmc.curso_materia_id
        JOIN materias ma      ON ma.id = cm.materia_id
        JOIN curso c          ON c.id = cm.curso_id
        JOIN curso_year cy    ON cy.id = c.curso_year_id
        JOIN curso_division cd ON cd.id = c.curso_division_id
        LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
        WHERE cm.year_escolar_id = $yearEscolarId
        ORDER BY profesor, curso, materia";
$res = $conn->query($sql);
while ($fila = $res->fetch_assoc()) { $listaDocentes[] = $fila; }

// Asignaciones de preceptores
$listaPreceptores = [];
$sql = "SELECT pc.id,
               u.nombre AS preceptor,
               u.dni    AS preceptor_dni,
               c.id AS curso_id,
               CONCAT(cy.year,' ', cd.division,
                      IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS curso
        FROM preceptor_curso pc
        JOIN usuarios u        ON u.dni = pc.preceptor_dni
        JOIN curso c           ON c.id = pc.curso_id
        JOIN curso_year cy     ON cy.id = c.curso_year_id
        JOIN curso_division cd ON cd.id = c.curso_division_id
        LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
        WHERE pc.year_escolar_id = $yearEscolarId
        ORDER BY preceptor, curso";
$res = $conn->query($sql);
while ($fila = $res->fetch_assoc()) { $listaPreceptores[] = $fila; }

// Vínculos familia–alumno
$listaFamilia = [];
$resCheckFA = $conn->query("SHOW TABLES LIKE 'familia_alumno'");
if ($resCheckFA && $resCheckFA->num_rows > 0) {
    $sql = "SELECT fa.id,
                   uf.nombre AS familia,
                   uf.dni    AS familia_dni,
                   ua.nombre AS alumno,
                   ua.dni    AS alumno_dni,
                   fa.parentesco
            FROM familia_alumno fa
            JOIN usuarios uf ON uf.dni = fa.familia_dni
            JOIN usuarios ua ON ua.dni = fa.alumno_dni
            ORDER BY ua.nombre, uf.nombre";
    $resFA = $conn->query($sql);
    while ($fila = $resFA->fetch_assoc()) { $listaFamilia[] = $fila; }
}

$nombreUsuario = $_SESSION['usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control - S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" /> 
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#e5e7eb;}
    header{background:#0f172a;color:#fff;}
    .navbar{display:flex;align-items:center;justify-content:space-between;padding:10px 20px;flex-wrap:wrap;}
    .logo,.logo2{width:90px;height:auto;}
    .title-box{text-align:center;flex-grow:1;}
    .title-box h1{margin:0;font-size:24px;}
    .title-box h2{margin:0;font-size:14px;font-weight:normal;}
    .back-btn{background:none;border:none;color:white;font-size:26px;cursor:pointer;margin-right:10px;}
    .account{display:flex;align-items:center;position:relative;cursor:pointer;}
    .avatar{width:35px;height:35px;border-radius:50%;background:#e2e8f0;color:#0f172a;font-weight:bold;display:flex;align-items:center;justify-content:center;}
    .account-menu{position:absolute;top:50px;right:0;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.15);display:none;min-width:160px;z-index:10;}
    .account-menu a{display:block;padding:8px 12px;color:#333;text-decoration:none;font-size:14px;}
    .account-menu a:hover{background:#f1f5f9;}

    main{padding:20px;}
    .contenedor{max-width:1200px;margin:0 auto;}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:25px;}
    .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 4px 8px rgba(0,0,0,0.12);}
    .card h3{margin-top:0;margin-bottom:10px;font-size:18px;color:#0f172a;}
    .card form{display:flex;flex-direction:column;gap:10px;}
    label{font-size:14px;font-weight:bold;}
    select,input[type="text"]{padding:6px;border-radius:6px;border:1px solid #9ca3af;font-size:14px;}
    .btn{background:#0f172a;color:white;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:14px;}
    .btn:hover{background:#1e293b;}
    .mensaje{margin-bottom:15px;padding:8px 12px;border-radius:8px;font-size:14px;display:<?php echo $mensaje ? 'block' : 'none'; ?>;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
    .search-wrap{display:flex;align-items:center;gap:8px;}
    .search-btn{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:14px;}
    .search-box{display:none;}
    .search-box input{width:100%;box-sizing:border-box;}
    .search-list{max-height:180px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;padding:6px;background:#fff;display:none;}
    .search-list option{padding:6px;}
    .search-list option:hover{background:#f3f4f6;}

    table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px;}
    th,td{border:1px solid #d1d5db;padding:6px;text-align:left;}
    th{background:#f3f4f6;}
    .acciones a{color:#b91c1c;text-decoration:none;font-weight:bold;}
    .acciones a:hover{text-decoration:underline;}

    @media(max-width:768px){
      .logo{display:none;}
      .title-box h1{font-size:20px;}
    }
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <a href="SGI.php"><button type="button" class="back-btn" aria-label="Volver a inicio">⟵</button></a>
    <a href="SGI.php"><img src="imagenes/newlogo1.png" class="logo2" alt="SGI"></a>
    <div class="title-box">
      <h1>Panel de Control</h1>
      <h2>Gestión de Materias, Cursos y Familia - <?php echo htmlspecialchars($yearActual); ?></h2>
    </div>
    <img src="imagenes/logotecn6.png" class="logo" alt="">
    <div class="account" id="accountBtn">
      <div class="avatar" id="avatarInitials"></div>
      <div class="account-menu" id="accountMenu">
        <a href="usuario.php">Perfil</a>
        <a href="changepassword.html">Cambiar contraseña</a>
        <a href="index.html">Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>

<main>
  <div class="contenedor">
    <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>

    <div class="cards">
      <!-- CARD 1: asignar materias a profes -->
      <?php if ($puedeAsignarProfes): ?>
      <div class="card">
        <h3>Asignar materias a profesores</h3>
        <form method="POST">
          <input type="hidden" name="accion" value="asignar_docente">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <div>
            <label>Profesor:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" data-target="maestro_dni" aria-label="Buscar profesor">🔎</button>
              <select name="maestro_dni" id="maestro_dni" required>
                <option value="">-- Seleccionar profesor --</option>
                <?php foreach($profesores as $p): ?>
                  <option value="<?php echo $p['dni']; ?>"><?php echo htmlspecialchars($p['nombre'])." (".$p['dni'].")"; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBox_maestro_dni">
              <input type="text" id="input_maestro_dni" placeholder="Buscar profesor por nombre o DNI" autocomplete="off">
            </div>
            <div class="search-list" id="list_maestro_dni"></div>
          </div>
          <div>
            <label>Curso:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" data-target="curso_id" aria-label="Buscar curso">🔎</button>
              <select name="curso_id" id="curso_id" required>
                <option value="">-- Seleccionar curso --</option>
                <?php foreach($cursos as $c): ?>
                  <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBox_curso_id">
              <input type="text" id="input_curso_id" placeholder="Buscar curso" autocomplete="off">
            </div>
            <div class="search-list" id="list_curso_id"></div>
          </div>
          <div>
            <label>Materia:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" data-target="materia_id" aria-label="Buscar materia">🔎</button>
              <select name="materia_id" id="materia_id" required>
                <option value="">-- Seleccionar materia --</option>
                <?php foreach($materias as $m): ?>
                  <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBox_materia_id">
              <input type="text" id="input_materia_id" placeholder="Buscar materia" autocomplete="off">
            </div>
            <div class="search-list" id="list_materia_id"></div>
          </div>
          <button type="submit" class="btn">Asignar materia</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($puedeAsignarPreceptor): ?>
      <!-- CARD 2: cursos a preceptores (solo directivo) -->
      <div class="card">
        <h3>Asignar cursos a preceptores</h3>
        <form method="POST">
          <input type="hidden" name="accion" value="asignar_preceptor">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <div>
            <label>Preceptor:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" data-target="preceptor_dni" aria-label="Buscar preceptor">🔎</button>
              <select name="preceptor_dni" id="preceptor_dni" required>
                <option value="">-- Seleccionar preceptor --</option>
                <?php foreach($preceptores as $p): ?>
                  <option value="<?php echo $p['dni']; ?>"><?php echo htmlspecialchars($p['nombre'])." (".$p['dni'].")"; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBox_preceptor_dni">
              <input type="text" id="input_preceptor_dni" placeholder="Buscar preceptor por nombre o DNI" autocomplete="off">
            </div>
            <div class="search-list" id="list_preceptor_dni"></div>
          </div>
          <div>
            <label>Curso:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" data-target="curso_id_2" aria-label="Buscar curso">🔎</button>
              <select name="curso_id" id="curso_id_2" required>
                <option value="">-- Seleccionar curso --</option>
                <?php foreach($cursos as $c): ?>
                  <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBox_curso_id_2">
              <input type="text" id="input_curso_id_2" placeholder="Buscar curso" autocomplete="off">
            </div>
            <div class="search-list" id="list_curso_id_2"></div>
          </div>
          <button type="submit" class="btn">Asignar curso</button>
        </form>
      </div>
      <?php endif; ?>


      <?php if (in_array($rol, ['root','admin','directivo','jefe_preceptores'], true)): ?>
      <div class="card">
        <h3>➕ Agregar preceptor</h3>
        <form method="POST" onsubmit="return agregarUsuarioPanel('preceptor', this);">
          <label>Nombre completo:</label>
          <input type="text" name="nombre" required placeholder="Apellido y nombre">
          <label>DNI:</label>
          <input type="text" name="dni" required inputmode="numeric" placeholder="DNI">
          <label>Contraseña inicial (opcional):</label>
          <input type="password" name="password" placeholder="Se generará Pre1234 si queda vacío">
          <button type="submit" class="btn">Crear preceptor</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if (in_array($rol, ['root','admin','directivo'], true)): ?>
      <div class="card">
        <h3>➕ Agregar profesor</h3>
        <form onsubmit="return agregarUsuarioPanel('profesor', this);">
          <label>Nombre completo:</label>
          <input type="text" name="nombre" required placeholder="Apellido y nombre">
          <label>DNI:</label>
          <input type="text" name="dni" required inputmode="numeric" placeholder="DNI">
          <label>Contraseña inicial (opcional):</label>
          <input type="password" name="password" placeholder="Se generará Pro1234 si queda vacío">
          <button type="submit" class="btn">Crear profesor</button>
        </form>
      </div>
      <?php endif; ?>


      <?php if ($puedeVincularFamilia): ?>
      <!-- CARD 3: vincular familia con alumno -->
      <div class="card">
        <h3>Vincular familia con alumno</h3>
        <?php if (empty($familias) || empty($alumnosActivos)): ?>
          <p>Para usar esto necesitás tener familias y alumnos cargados.</p>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="accion" value="asignar_familia">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <div>
            <label>Familia:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" id="btnBuscarFamilia" aria-label="Buscar familia">🔎</button>
              <select name="familia_dni" id="familia_dni" required>
                <option value="">-- Seleccionar familia --</option>
                <?php foreach($familias as $f): ?>
                  <option value="<?php echo $f['dni']; ?>">
                    <?php echo htmlspecialchars($f['nombre'])." (".$f['dni'].")"; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBoxFamilia">
              <input type="text" id="inputBuscarFamilia" placeholder="Escribí nombre o DNI de la familia" autocomplete="off">
            </div>
            <div class="familias-list" id="listaFamiliasFiltradas"></div>
          </div>
          <div>
            <label>Alumno:</label>
            <div class="search-wrap">
              <button type="button" class="search-btn" data-target="alumno_dni" aria-label="Buscar alumno">🔎</button>
              <select name="alumno_dni" id="alumno_dni" required>
                <option value="">-- Seleccionar alumno --</option>
                <?php foreach($alumnosActivos as $a): ?>
                  <option value="<?php echo $a['dni']; ?>"><?php echo htmlspecialchars($a['nombre'])." (".$a['dni'].")"; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="search-box" id="searchBox_alumno_dni">
              <input type="text" id="input_alumno_dni" placeholder="Buscar alumno por nombre o DNI" autocomplete="off">
            </div>
            <div class="search-list" id="list_alumno_dni"></div>
          </div>
          <div>
            <label>Parentesco:</label>
            <select name="parentesco" required>
            <option value="">-- Seleccionar parentesco --</option>
              <option value="Madre">Madre</option>
              <option value="Padre">Padre</option>
              <option value="Tutor">Tutor</option>
            </select>
          </div>
          <button type="submit" class="btn">Vincular familia y alumno</button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- Listado de asignaciones -->
    <?php if ($puedeAsignarProfes): ?>
    <div class="card">
      <h3>Materias por profesor y curso</h3>
      <table>
        <thead>
          <tr>
            <th>Profesor</th>
            <th>DNI</th>
            <th>Curso</th>
            <th>Materia</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$listaDocentes): ?>
          <tr><td colspan="5">No hay asignaciones registradas.</td></tr>
        <?php else: ?>
          <?php foreach($listaDocentes as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['profesor']); ?></td>
              <td><?php echo $row['profesor_dni']; ?></td>
              <td><?php echo htmlspecialchars($row['curso']); ?></td>
              <td><?php echo htmlspecialchars($row['materia']); ?></td>
              <td class="acciones">
                <?php if (in_array($rol, ['root','admin','directivo'], true)): ?>
<form method="post" onsubmit="return confirm('¿Eliminar esta asignación?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"><input type="hidden" name="accion" value="del_dmc"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button type="submit">Eliminar</button></form>
<?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($puedeAsignarPreceptor): ?>
    <div class="card">
      <h3>Cursos asignados a preceptores</h3>
      <table>
        <thead>
          <tr>
            <th>Preceptor</th>
            <th>DNI</th>
            <th>Curso</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$listaPreceptores): ?>
          <tr><td colspan="4">No hay asignaciones registradas.</td></tr>
        <?php else: ?>
          <?php foreach($listaPreceptores as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['preceptor']); ?></td>
              <td><?php echo $row['preceptor_dni']; ?></td>
              <td><?php echo htmlspecialchars($row['curso']); ?></td>
              <td class="acciones">
                <?php if (in_array($rol, ['root','admin','directivo','jefe_preceptores'], true)): ?>
<form method="post" onsubmit="return confirm('¿Eliminar esta asignación?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"><input type="hidden" name="accion" value="del_prec"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button type="submit">Eliminar</button></form>
<?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($puedeVincularFamilia): ?>
    <div class="card">
      <h3>Familia vinculada a alumnos</h3>
      <table>
        <thead>
          <tr>
            <th>Alumno</th>
            <th>DNI Alumno</th>
            <th>Familia</th>
            <th>DNI Familia</th>
            <th>Parentesco</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if(!$listaFamilia): ?>
          <tr><td colspan="6">No hay vínculos familia–alumno registrados.</td></tr>
        <?php else: ?>
          <?php foreach($listaFamilia as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['alumno']); ?></td>
              <td><?php echo $row['alumno_dni']; ?></td>
              <td><?php echo htmlspecialchars($row['familia']); ?></td>
              <td><?php echo $row['familia_dni']; ?></td>
              <td><?php echo htmlspecialchars($row['parentesco']); ?></td>
              <td class="acciones">
                <?php if (in_array($rol, ['root','admin','directivo'], true)): ?>
<form method="post" onsubmit="return confirm('¿Eliminar este vínculo?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"><input type="hidden" name="accion" value="del_fam"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button type="submit">Eliminar</button></form>
<?php else: ?>—<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</main>

<script>
const csrfTokenPanel = <?= json_encode(csrfToken()) ?>;
const fetchPanelSeguro = window.fetch.bind(window);
window.fetch = (url, options = {}) => {
  if ((options.method || 'GET').toUpperCase() === 'POST' && options.body instanceof FormData) {
    options.body.set('csrf_token', csrfTokenPanel);
  }
  return fetchPanelSeguro(url, options);
};
  // Iniciales del usuario en el avatar
  const nombreUsuario = <?php echo json_encode($nombreUsuario); ?>;
  const initials = nombreUsuario.split(" ").map(p=>p.charAt(0)).join("").substring(0,2).toUpperCase();
  document.getElementById("avatarInitials").textContent = initials;

  const accountBtn = document.getElementById("accountBtn");
  const accountMenu = document.getElementById("accountMenu");

  function setupSearch(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const btn = document.querySelector('.search-btn[data-target="' + selectId + '"]');
    const searchBox = document.getElementById('searchBox_' + selectId);
    const input = document.getElementById('input_' + selectId);
    const list = document.getElementById('list_' + selectId);
    if (!btn || !input || !list) return;

    const render = () => {
      const texto = (input.value || '').toLowerCase().trim();
      const opciones = Array.from(select.options).slice(1);
      const filtradas = opciones.filter(opt => !texto || (opt.textContent || '').toLowerCase().includes(texto));
      list.innerHTML = filtradas.map(opt => `<option value="${opt.value}" style="display:block;cursor:pointer;">${opt.textContent}</option>`).join('');
      list.style.display = filtradas.length && texto ? 'block' : 'none';
    };

    btn.addEventListener('click', () => {
      const visible = searchBox.style.display === 'block';
      searchBox.style.display = visible ? 'none' : 'block';
      list.style.display = 'none';
      if (!visible) { input.value=''; input.focus(); render(); }
    });

    input.addEventListener('input', render);
    list.addEventListener('click', (e) => {
      const opt = e.target.closest('option');
      if (!opt) return;
      select.value = opt.value;
      list.style.display = 'none';
      searchBox.style.display = 'none';
    });
  }

  ['maestro_dni','curso_id','materia_id','preceptor_dni','curso_id_2','alumno_dni'].forEach(setupSearch);

  const selectFamilia = document.getElementById('familia_dni');
  const btnBuscarFamilia = document.getElementById('btnBuscarFamilia');
  const searchBoxFamilia = document.getElementById('searchBoxFamilia');
  const inputBuscarFamilia = document.getElementById('inputBuscarFamilia');
  const listaFamiliasFiltradas = document.getElementById('listaFamiliasFiltradas');

  function filtrarFamilias() {
    const texto = (inputBuscarFamilia.value || '').toLowerCase().trim();
    const opciones = Array.from(selectFamilia.options).slice(1);
    const filtradas = opciones.filter(opt => !texto || (opt.textContent || '').toLowerCase().includes(texto));
    listaFamiliasFiltradas.innerHTML = filtradas.map(opt => `<option value="${opt.value}" style="display:block;cursor:pointer;">${opt.textContent}</option>`).join('');
    listaFamiliasFiltradas.style.display = filtradas.length && texto ? 'block' : 'none';
  }

  btnBuscarFamilia.addEventListener('click', () => {
    const visible = searchBoxFamilia.style.display === 'block';
    searchBoxFamilia.style.display = visible ? 'none' : 'block';
    listaFamiliasFiltradas.style.display = 'none';
    if (!visible) { inputBuscarFamilia.value=''; inputBuscarFamilia.focus(); filtrarFamilias(); }
  });

  inputBuscarFamilia.addEventListener('input', filtrarFamilias);

  listaFamiliasFiltradas.addEventListener('click', (e) => {
    const opt = e.target.closest('option');
    if (!opt) return;
    selectFamilia.value = opt.value;
    listaFamiliasFiltradas.style.display = 'none';
    searchBoxFamilia.style.display = 'none';
  });

  accountBtn.addEventListener("click", e=>{
    e.stopPropagation();
    accountMenu.style.display = accountMenu.style.display === "block" ? "none" : "block";
  });
  document.addEventListener("click", ()=> accountMenu.style.display="none");

function agregarUsuarioPanel(tipo, form) {
  const fd = new FormData(form);
  fd.append('rol', tipo);
  fetch('api_agregar_usuario.php', {method:'POST', body:fd, credentials:'same-origin'})
    .then(r=>r.json())
    .then(j=>{
      if (!j.ok) { alert(j.msg || 'No se pudo crear el usuario.'); return; }
      alert('Usuario creado correctamente.\nContraseña temporal: ' + j.temp_password);
      form.reset();
      location.reload();
    })
    .catch(()=>alert('Error de red.'));
  return false;
}
</script>
</body>
</html>
