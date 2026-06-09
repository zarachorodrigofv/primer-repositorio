<?php
session_start();

// 1) CONTROL DE ACCESO
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['profesor','preceptor','directivo'])) {
    header("Location: index.html");
    exit;
}

$rol        = strtolower($_SESSION['rol']);
$dniUsuario = (int)$_SESSION['dni'];

// 2) CONEXIÓN A BD
$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 3) AÑO ESCOLAR ACTUAL
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

// 4) CURSOS SEGÚN ROL
$cursos = [];

if ($rol === 'directivo') {
    $sql = "SELECT c.id,
                   CONCAT(cy.year,' ', cd.division,
                          IF(m.nombre IS NULL,'', CONCAT(' - ', m.nombre))) AS nombre
            FROM curso c
            JOIN curso_year cy ON cy.id = c.curso_year_id
            JOIN curso_division cd ON cd.id = c.curso_division_id
            LEFT JOIN modalidad m ON m.id = c.modalidad_id
            ORDER BY cy.id, cd.id";
    $res = $conn->query($sql);
    while ($fila = $res->fetch_assoc()) {
        $cursos[] = $fila;
    }
} elseif ($rol === 'profesor') {
    $sql = "SELECT DISTINCT c.id,
                   CONCAT(cy.year,' ', cd.division,
                          IF(m.nombre IS NULL,'', CONCAT(' - ', m.nombre))) AS nombre
            FROM docente_materia_curso dmc
            JOIN curso_materia cm ON cm.id = dmc.curso_materia_id
            JOIN curso c ON c.id = cm.curso_id
            JOIN curso_year cy ON cy.id = c.curso_year_id
            JOIN curso_division cd ON cd.id = c.curso_division_id
            LEFT JOIN modalidad m ON m.id = c.modalidad_id
            WHERE dmc.maestro_dni = ?
            ORDER BY cy.id, cd.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dniUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $cursos[] = $fila;
    }
    $stmt->close();
} elseif ($rol === 'preceptor') {
    // SOLO cursos asignados al preceptor en preceptor_curso para el año actual
    $sql = "SELECT DISTINCT c.id,
                   CONCAT(cy.year,' ', cd.division,
                          IF(m.nombre IS NULL,'', CONCAT(' - ', m.nombre))) AS nombre
            FROM preceptor_curso pc
            JOIN curso c           ON c.id = pc.curso_id
            JOIN curso_year cy     ON cy.id = c.curso_year_id
            JOIN curso_division cd ON cd.id = c.curso_division_id
            LEFT JOIN modalidad m  ON m.id = c.modalidad_id
            WHERE pc.preceptor_dni   = ?
              AND pc.year_escolar_id = ?
            ORDER BY cy.id, cd.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $dniUsuario, $yearEscolarId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $cursos[] = $fila;
    }
    $stmt->close();
}


// 5) PARÁMETROS DE FILTRO
$cursoSeleccionado = isset($_REQUEST['curso_id']) ? (int)$_REQUEST['curso_id'] : (count($cursos) ? (int)$cursos[0]['id'] : 0);
$idsPermitidos = array_map(fn($c) => (int)$c['id'], $cursos);
if (!in_array($cursoSeleccionado, $idsPermitidos, true)) {
    $cursoSeleccionado = count($cursos) ? (int)$cursos[0]['id'] : 0;
}
$mesSeleccionado   = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : (int)date('n');
$anioSeleccionado  = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : (int)date('Y');

// 6) GUARDAR ASISTENCIA (P / A)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar']) && $cursoSeleccionado) {
    if (isset($_POST['estado']) && is_array($_POST['estado'])) {
        $estadoData = $_POST['estado'];
        foreach ($estadoData as $dniAlumno => $dias) {
            $dniAlumno = (int)$dniAlumno;
            foreach ($dias as $dia => $estado) {
                $dia    = (int)$dia;
                $estado = ($estado === 'presente' || $estado === 'ausente' || $estado === 'tarde') ? $estado : '';

                $fecha = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $dia);

                // Borrar registro anterior de ese alumno + fecha
                $del = $conn->prepare("DELETE FROM asistencia WHERE alumno_dni = ? AND fecha = ?");
                $del->bind_param("is", $dniAlumno, $fecha);
                $del->execute();
                $del->close();

                // Insertar solo si hay presencia o ausencia marcada
                if ($estado !== '') {
                    $ins = $conn->prepare("INSERT INTO asistencia (alumno_dni, fecha, estado) VALUES (?,?,?)");
                    $ins->bind_param("iss", $dniAlumno, $fecha, $estado);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
        $mensajeOK = "Asistencia guardada correctamente.";
    }
}

// 7) ALUMNOS DEL CURSO
$alumnos = [];
if ($cursoSeleccionado) {
    $sql = "SELECT u.dni, u.nombre
            FROM asignado_alumno aa
            JOIN usuarios u ON u.dni = aa.alumno_dni
            WHERE aa.curso_id = ? AND aa.year_escolar_id = ? AND aa.estado = 'activo'
            ORDER BY u.nombre";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cursoSeleccionado, $yearEscolarId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $alumnos[] = $fila;
    }
    $stmt->close();
}

// 8) ASISTENCIA YA GUARDADA PARA ESE MES
$asistencias = []; // [dni][dia] = 'presente' | 'ausente' 'tarde'
if ($alumnos) {
    $dniList = implode(',', array_map('intval', array_column($alumnos, 'dni')));
    $totalDias = cal_days_in_month(CAL_GREGORIAN, $mesSeleccionado, $anioSeleccionado);
    $desde = sprintf('%04d-%02d-01', $anioSeleccionado, $mesSeleccionado);
    $hasta = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $totalDias);

    $sql = "SELECT alumno_dni, fecha, estado
            FROM asistencia
            WHERE alumno_dni IN ($dniList)
              AND fecha BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $desde, $hasta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $dia = (int)date('j', strtotime($fila['fecha']));
        $asistencias[(int)$fila['alumno_dni']][$dia] = $fila['estado'];
    }
    $stmt->close();
}

$nombreUsuario = $_SESSION['usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Sistema de Asistencia - S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" /> 
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- STYLES -->
  <!-- <link rel="stylesheet" href="css/menuHamburguesa.css"> -->
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">

  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #d8d7d7;
    }
    header { background-color: #0f172a; color: #fff; }
    .logo, .logo2 { width: 100px; height: auto; }
    .title-box { text-align: center; flex-grow: 1; }
    .title-box h1 { margin: 0; font-size: 28px; }
    .title-box h2 { margin: 0; font-size: 16px; font-weight: normal; }

    main { padding: 20px; }
    .contenedor {
      max-width: 1100px; margin: 20px auto;
      background: #ffffff; padding: 20px;
      border-radius: 15px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .filtros-superior {
      display: flex; gap: 20px; align-items: center; justify-content: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .filtros-superior label { font-weight: bold; }
    .filtros-superior select {
      padding: 5px 8px;
      border-radius: 6px;
      border: 1px solid #999;
    }

    h3.titulo-asistencia {
      text-align: center;
      margin-bottom: 10px;
      font-size: 22px;
      text-transform: uppercase;
    }

    .subtitulo-mes {
      text-align: center;
      font-size: 18px;
      margin-bottom: 15px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 13px;
    }
    th, td {
      border: 1px solid #333;
      padding: 4px;
      text-align: center;
      word-wrap: break-word;
    }
    th.alumnos-col { width: 180px; text-align: left; background:#f3f4f6; }
    th.dia-col { background:#e5e7eb; }

    .celda-estado {
      cursor: pointer;
      user-select: none;
      font-weight: bold;
      position: relative;
    }
    .celda-estado span.letra {
      display: block;
      width: 100%;
    }

    .estado-presente   { background-color: #c8f7c5; color: #0a7511; }
    .estado-ausente    { background-color: #f7c5c5; color: #a00000; }
    .estado-tarde      { background-color:  #fde68a; color: #92400e;}

    .botones {
      text-align: center;
      margin-top: 15px;
    }
    .botones button {
      background: #0f172a;
      color: white;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      cursor: pointer;
      margin: 5px;
      font-size: 14px;
    }
    .botones button:hover { background: #1e293b; }

    .alerta-ok {
      text-align:center;
      background:#16a34a;
      color:white;
      padding:8px;
      border-radius:8px;
      margin-bottom:10px;
      display: <?php echo isset($mensajeOK) ? 'block' : 'none'; ?>;
    }

    @media (max-width: 768px) {
      .logo { display:none; }
      table { font-size: 11px; }
      th.alumnos-col { width: 130px; }
    }
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <!-- Botón volver a SGI.php -->
    <a href="SGI.php" class="back-link">
      <button class="menu-icon" type="button" aria-label="Volver a inicio">⟵</button>
    </a>

    <a href="SGI.php"><img src="imagenes/newlogo1.webp" class="logo2" alt="SGI"></a>
    <div class="title-box">
      <h1>Asistencia de Alumnos</h1>
      <h2>Ciclo lectivo <?php echo htmlspecialchars($yearActual); ?></h2>
    </div>
    <img src="imagenes/logotecn6.webp" alt="E.E.S.T N°6" class="logo">
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
    <div class="alerta-ok"><?php echo isset($mensajeOK) ? htmlspecialchars($mensajeOK) : ''; ?></div>

    <h3 class="titulo-asistencia">Lista de Asistencia</h3>

    <!-- Filtros curso/mes/año (GET) -->
    <form method="GET" style="margin:0 0 10px 0;">
      <div class="filtros-superior">
        <div>
          <label>Curso:</label>
          <select name="curso_id" onchange="this.form.submit()">
            <?php foreach ($cursos as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php if ($cursoSeleccionado == $c['id']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($c['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Mes:</label>
          <select name="mes" onchange="this.form.submit()">
            <?php
              $meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
              foreach ($meses as $num => $nom):
            ?>
              <option value="<?php echo $num; ?>" <?php if ($mesSeleccionado == $num) echo 'selected'; ?>>
                <?php echo $nom; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Año:</label>
          <select name="anio" onchange="this.form.submit()">
            <?php for ($y = $yearActual-1; $y <= $yearActual+1; $y++): ?>
              <option value="<?php echo $y; ?>" <?php if ($anioSeleccionado == $y) echo 'selected'; ?>>
                <?php echo $y; ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
    </form>

    <div class="subtitulo-mes">
      MES: <?php echo $meses[$mesSeleccionado]; ?>
    </div>

    <!-- Formulario de guardado (POST) -->
    <form method="POST">
      <input type="hidden" name="curso_id" value="<?php echo $cursoSeleccionado; ?>">
      <input type="hidden" name="mes" value="<?php echo $mesSeleccionado; ?>">
      <input type="hidden" name="anio" value="<?php echo $anioSeleccionado; ?>">

      <table>
        <thead>
          <tr>
            <th class="alumnos-col">Alumnos</th>
            <?php
              $totalDias = cal_days_in_month(CAL_GREGORIAN, $mesSeleccionado, $anioSeleccionado);
              for ($d=1; $d<=$totalDias; $d++): ?>
                <th class="dia-col"><?php echo $d; ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$alumnos): ?>
          <tr><td colspan="<?php echo $totalDias+1; ?>">No hay alumnos asignados a este curso.</td></tr>
        <?php else: ?>
          <?php foreach ($alumnos as $al):
                $dniAl = (int)$al['dni']; ?>
            <tr>
              <td style="text-align:left;"><?php echo htmlspecialchars($al['nombre']); ?></td>
              <?php for ($d=1; $d<=$totalDias; $d++):
                    $valor = $asistencias[$dniAl][$d] ?? ''; // 'presente' / 'ausente' / '' / 'tarde'
                    $clase = '';
                    $letra = '';
                    if ($valor === 'presente') { $clase = 'estado-presente'; $letra = 'P'; }
                    elseif ($valor === 'ausente') { $clase = 'estado-ausente'; $letra = 'A'; }
                    elseif ($valor === 'tarde')   { $clase = 'estado-tarde'; $letra = 'T';}
              ?>
                <td class="celda-estado <?php echo $clase; ?>"
                    data-dni="<?php echo $dniAl; ?>"
                    data-dia="<?php echo $d; ?>"
                    data-valor="<?php echo $valor; ?>">
                  <span class="letra"><?php echo $letra; ?></span>
                  <input type="hidden" name="estado[<?php echo $dniAl; ?>][<?php echo $d; ?>]" value="<?php echo $valor; ?>">
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

      <div class="botones">
        <button type="submit" name="guardar">💾 Guardar</button>
      </div>
    </form>
  </div>
</main>

<script src="js/main.js"></script>
<script>
window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? $user['nombre'] ?? 'Usuario');?>"; // Iniciales

  // Clic en cada celda: ciclo "", P, A, T
  document.querySelectorAll('.celda-estado').forEach(celda => {
    celda.addEventListener('click', () => {
      let valor = celda.dataset.valor || "";
      if (valor === "")        valor = "presente";
      else if (valor === "presente") valor = "ausente";
      else if (valor === "ausente")  valor = "tarde";
      else                      valor = "";

      celda.dataset.valor = valor;

      const hidden = celda.querySelector('input[type="hidden"]');
      const span   = celda.querySelector('span.letra');

      hidden.value = valor;

      celda.classList.remove('estado-presente','estado-ausente', 'estado-tarde');
      span.textContent = "";

      if (valor === "presente") {
        span.textContent = "P";
        celda.classList.add('estado-presente');
      } 
        else if (valor === "ausente") {
        span.textContent = "A";
        celda.classList.add('estado-ausente');
      }
        else if (valor === "tarde") {
          span.textContent = "T";
          celda.classList.add('estado-tarde');
        } 
    });
  });
</script>
</body>
</html>
