<?php
session_start();

$rol = strtolower($_SESSION['rol'] ?? '');

// Permitir DIRECTIVO y PRECEPTOR
if (!in_array($rol, ['directivo','preceptor'], true)) {
    header("Location: index.html");
    exit;
}

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

    if (!in_array($rol, ['directivo','preceptor'], true)) {
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

// Asignar curso a preceptor (SOLO DIRECTIVO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_preceptor') {

    if ($rol !== 'directivo') {
        $mensaje = "Solo los directivos pueden asignar cursos a preceptores.";
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

// Borrar asignación docente-materia-curso
if (isset($_GET['del_dmc'])) {
    $id = (int)$_GET['del_dmc'];
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM docente_materia_curso WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Asignación de profesor eliminada.";
    }
}

// Borrar asignación preceptor-curso
if (isset($_GET['del_prec'])) {
    $id = (int)$_GET['del_prec'];
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM preceptor_curso WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Asignación de preceptor eliminada.";
    }
}

// Borrar vínculo familia-alumno
if (isset($_GET['del_fam'])) {
    $id = (int)$_GET['del_fam'];
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
$res = $conn->query("SELECT dni, nombre FROM usuarios WHERE rol='profesor' ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $profesores[] = $fila; }

// Preceptores
$preceptores = [];
$res = $conn->query("SELECT dni, nombre FROM usuarios WHERE rol='preceptor' ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $preceptores[] = $fila; }

// Materias
$materias = [];
$res = $conn->query("SELECT id, nombre FROM materias ORDER BY nombre");
while ($fila = $res->fetch_assoc()) { $materias[] = $fila; }

// CURSOS SEGÚN ROL
$cursos = [];

if ($rol === 'directivo') {
    // DIRECTIVO VE TODOS LOS CURSOS
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
$res = $conn->query("SELECT dni, nombre FROM usuarios WHERE rol='familia' ORDER BY nombre");
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
      <!-- CARD 1: materias a profesores (directivo y preceptor) -->
      <div class="card">
        <h3>Asignar materias a profesores</h3>
        <form method="POST">
          <input type="hidden" name="accion" value="asignar_docente">
          <div>
            <label>Profesor:</label>
            <select name="maestro_dni" required>
              <option value="">-- Seleccionar profesor --</option>
              <?php foreach($profesores as $p): ?>
                <option value="<?php echo $p['dni']; ?>">
                  <?php echo htmlspecialchars($p['nombre'])." (".$p['dni'].")"; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Curso:</label>
            <select name="curso_id" required>
              <option value="">-- Seleccionar curso --</option>
              <?php foreach($cursos as $c): ?>
                <option value="<?php echo $c['id']; ?>">
                  <?php echo htmlspecialchars($c['nombre']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Materia:</label>
            <select name="materia_id" required>
              <option value="">-- Seleccionar materia --</option>
              <?php foreach($materias as $m): ?>
                <option value="<?php echo $m['id']; ?>">
                  <?php echo htmlspecialchars($m['nombre']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Asignar materia</button>
        </form>
      </div>

      <?php if ($rol === 'directivo'): ?>
      <!-- CARD 2: cursos a preceptores (solo directivo) -->
      <div class="card">
        <h3>Asignar cursos a preceptores</h3>
        <form method="POST">
          <input type="hidden" name="accion" value="asignar_preceptor">
          <div>
            <label>Preceptor:</label>
            <select name="preceptor_dni" required>
              <option value="">-- Seleccionar preceptor --</option>
              <?php foreach($preceptores as $p): ?>
                <option value="<?php echo $p['dni']; ?>">
                  <?php echo htmlspecialchars($p['nombre'])." (".$p['dni'].")"; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Curso:</label>
            <select name="curso_id" required>
              <option value="">-- Seleccionar curso --</option>
              <?php foreach($cursos as $c): ?>
                <option value="<?php echo $c['id']; ?>">
                  <?php echo htmlspecialchars($c['nombre']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Asignar curso</button>
        </form>
      </div>
      <?php endif; ?>

      <!-- CARD 3: vincular familia con alumno -->
      <div class="card">
        <h3>Vincular familia con alumno</h3>
        <?php if (empty($familias) || empty($alumnosActivos)): ?>
          <p>Para usar esto necesitás tener familias y alumnos cargados.</p>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="accion" value="asignar_familia">
          <div>
            <label>Familia:</label>
            <select name="familia_dni" required>
              <option value="">-- Seleccionar familia --</option>
              <?php foreach($familias as $f): ?>
                <option value="<?php echo $f['dni']; ?>">
                  <?php echo htmlspecialchars($f['nombre'])." (".$f['dni'].")"; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Alumno:</label>
            <select name="alumno_dni" required>
              <option value="">-- Seleccionar alumno --</option>
              <?php foreach($alumnosActivos as $a): ?>
                <option value="<?php echo $a['dni']; ?>">
                  <?php echo htmlspecialchars($a['nombre'])." (".$a['dni'].")"; ?>
                </option>
              <?php endforeach; ?>
            </select>
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
    </div>

    <!-- Listado de asignaciones -->
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
                <a href="?del_dmc=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar esta asignación?');">Eliminar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

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
                <a href="?del_prec=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar esta asignación?');">Eliminar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

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
                <a href="?del_fam=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar este vínculo?');">Eliminar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<script>
  // Iniciales del usuario en el avatar
  const nombreUsuario = <?php echo json_encode($nombreUsuario); ?>;
  const initials = nombreUsuario.split(" ").map(p=>p.charAt(0)).join("").substring(0,2).toUpperCase();
  document.getElementById("avatarInitials").textContent = initials;

  const accountBtn = document.getElementById("accountBtn");
  const accountMenu = document.getElementById("accountMenu");
  accountBtn.addEventListener("click", e=>{
    e.stopPropagation();
    accountMenu.style.display = accountMenu.style.display === "block" ? "none" : "block";
  });
  document.addEventListener("click", ()=> accountMenu.style.display="none");
</script>
</body>
</html>

