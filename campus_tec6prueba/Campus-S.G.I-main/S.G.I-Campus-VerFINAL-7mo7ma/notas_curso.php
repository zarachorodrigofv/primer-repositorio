<?php
require_once 'auth.php';
requireLogin();
require_once 'config.php';
require_once 'helpers_academico.php';

$pdo     = db();
$rol     = strtolower(trim($_SESSION['rol']));
$dni     = (int)($_SESSION['dni'] ?? 0);
$usuario = $_SESSION['usuario'] ?? '';
$yearId  = currentYearEscolarId($pdo);

// roles autorizados a ver esta pantalla
if (!in_array($rol, ['profesor','preceptor','directivo','alumno','familia'], true)) {
    http_response_code(403);
    echo "<p style='text-align:center;margin-top:40px;font-family:sans-serif'>
            No tenés permisos para ver las notas de este curso.
          </p>";
    exit;
}

$cursoId   = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
$materiaId = isset($_GET['materia_id']) ? (int)$_GET['materia_id'] : 0;

if ($cursoId <= 0 || $materiaId <= 0) {
    echo "Curso o materia no especificados.";
    exit;
}

// comprobar que el usuario tenga acceso al curso (para su rol)
if (!usuarioTieneAccesoACurso($rol, $dni, $cursoId, $yearId) && $rol !== 'directivo') {
    http_response_code(403);
    echo "<p style='text-align:center;margin-top:40px;font-family:sans-serif'>
            No tenés acceso a este curso.
          </p>";
    exit;
}

// materias visibles para el rol en este curso
$materias = materiasDeCursoParaUsuario($rol, $dni, $cursoId, $yearId);
$tieneMateria   = false;
$materiaNombre  = '';

foreach ($materias as $m) {
    if ((int)$m['id'] === $materiaId) {
        $tieneMateria  = true;
        $materiaNombre = $m['nombre'];
        break;
    }
}
if (!$tieneMateria) {
    http_response_code(403);
    echo "<p style='text-align:center;margin-top:40px;font-family:sans-serif'>
            Esta materia no está asignada para tu rol en este curso.
          </p>";
    exit;
}

$infoCurso = infoCurso($cursoId);
if (!$infoCurso) {
    echo "Curso inexistente.";
    exit;
}
$modsUser   = modalidadesPermitidasPorRol($rol, $dni, $yearId);
$cursoLabel = htmlspecialchars($infoCurso['year'] . ' ' . $infoCurso['division']);

$verListado = in_array($rol, ['profesor','preceptor','directivo'], true);
$verPanel   = in_array($rol, ['preceptor','directivo'], true);

// solo el profesor escribe; el resto ve en solo lectura
$soloLectura = ($rol !== 'profesor');

$mensaje = "";

/**
 * Guarda/actualiza notas de un cuatrimestre en notas_detalle.
 * Incluye: concepto, TP, examen, promedio numérico y nota_final.
 */
function guardarNotasDetalleCuatrimestre(
    PDO $pdo,
    int $alumnoDni,
    int $materiaId,
    int $yearId,
    string $cuatr,
    ?float $notaConcepto,
    ?float $notaTp,
    ?float $notaExamen,
    ?float $notaFinal,
    ?float $notaNumerica
) {
    // si no hay nada cargado, no hacemos nada
    if (
        $notaFinal === null && $notaNumerica === null &&
        $notaConcepto === null && $notaTp === null && $notaExamen === null
    ) {
        return;
    }

    $sqlSel = "
        SELECT id
        FROM notas_detalle
        WHERE alumno_dni = :alumno
          AND materia_id = :materia
          AND year_escolar_id = :year
          AND cuatrimestre = :cuatr
        LIMIT 1
    ";
    $stmtSel = $pdo->prepare($sqlSel);
    $stmtSel->execute([
        ':alumno'  => $alumnoDni,
        ':materia' => $materiaId,
        ':year'    => $yearId,
        ':cuatr'   => $cuatr
    ]);
    $id = $stmtSel->fetchColumn();

    if ($id) {
        $sqlUpd = "
            UPDATE notas_detalle
            SET nota_concepto = :nota_concepto,
                nota_tp       = :nota_tp,
                nota_examen   = :nota_examen,
                nota_numerica = :nota_numerica,
                nota_final    = :nota_final
            WHERE id = :id
        ";
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute([
            ':nota_concepto' => $notaConcepto,
            ':nota_tp'       => $notaTp,
            ':nota_examen'   => $notaExamen,
            ':nota_numerica' => $notaNumerica,
            ':nota_final'    => $notaFinal,
            ':id'            => $id
        ]);
    } else {
        $sqlIns = "
            INSERT INTO notas_detalle
                (alumno_dni, materia_id, year_escolar_id, cuatrimestre,
                 nota_concepto, nota_tp, nota_examen,
                 nota_numerica, nota_final)
            VALUES
                (:alumno, :materia, :year, :cuatr,
                 :nota_concepto, :nota_tp, :nota_examen,
                 :nota_numerica, :nota_final)
        ";
        $stmtIns = $pdo->prepare($sqlIns);
        $stmtIns->execute([
            ':alumno'        => $alumnoDni,
            ':materia'       => $materiaId,
            ':year'          => $yearId,
            ':cuatr'         => $cuatr,
            ':nota_concepto' => $notaConcepto,
            ':nota_tp'       => $notaTp,
            ':nota_examen'   => $notaExamen,
            ':nota_numerica' => $notaNumerica,
            ':nota_final'    => $notaFinal
        ]);
    }
}

// SOLO el profesor guarda notas
if (
    !$soloLectura &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['notas']) &&
    is_array($_POST['notas'])
) {
    foreach ($_POST['notas'] as $alumnoDni => $datosAlumno) {
        $alumnoDni = (int)$alumnoDni;

        // CUATRIMESTRE 1
        $c1_concepto = ($datosAlumno['c1_concepto'] ?? '') !== '' ? (float)$datosAlumno['c1_concepto'] : null;
        $c1_tp       = ($datosAlumno['c1_tp']       ?? '') !== '' ? (float)$datosAlumno['c1_tp']       : null;
        $c1_examen   = ($datosAlumno['c1_examen']   ?? '') !== '' ? (float)$datosAlumno['c1_examen']   : null;

        $valoresC1 = [];
        foreach ([$c1_concepto, $c1_tp, $c1_examen] as $v) {
            if ($v !== null) $valoresC1[] = $v;
        }

        $c1_promNum = null;
        $c1_final   = null;
        if (count($valoresC1) > 0) {
            $c1_promNum = array_sum($valoresC1) / count($valoresC1); // promedio real
            $c1_final   = floor($c1_promNum);                        // redondeo para abajo
        }

        guardarNotasDetalleCuatrimestre(
            $pdo,
            $alumnoDni,
            $materiaId,
            $yearId,
            '1',
            $c1_concepto,
            $c1_tp,
            $c1_examen,
            $c1_final,
            $c1_promNum
        );

        // CUATRIMESTRE 2
        $c2_concepto = ($datosAlumno['c2_concepto'] ?? '') !== '' ? (float)$datosAlumno['c2_concepto'] : null;
        $c2_tp       = ($datosAlumno['c2_tp']       ?? '') !== '' ? (float)$datosAlumno['c2_tp']       : null;
        $c2_examen   = ($datosAlumno['c2_examen']   ?? '') !== '' ? (float)$datosAlumno['c2_examen']   : null;

        $valoresC2 = [];
        foreach ([$c2_concepto, $c2_tp, $c2_examen] as $v) {
            if ($v !== null) $valoresC2[] = $v;
        }

        $c2_promNum = null;
        $c2_final   = null;
        if (count($valoresC2) > 0) {
            $c2_promNum = array_sum($valoresC2) / count($valoresC2);
            $c2_final   = floor($c2_promNum);
        }

        guardarNotasDetalleCuatrimestre(
            $pdo,
            $alumnoDni,
            $materiaId,
            $yearId,
            '2',
            $c2_concepto,
            $c2_tp,
            $c2_examen,
            $c2_final,
            $c2_promNum
        );
    }

    $mensaje = "✅ Notas guardadas y notas finales actualizadas correctamente.";
}

/* ==== ALUMNOS QUE SE MUESTRAN SEGÚN ROL ==== */

if ($rol === 'alumno') {
    // solo el propio alumno
    $sqlAlu = "
        SELECT u.dni, u.nombre
        FROM asignado_alumno aa
        JOIN usuarios u ON u.dni = aa.alumno_dni
        WHERE aa.curso_id = :curso
          AND aa.year_escolar_id = :year
          AND aa.estado = 'activo'
          AND aa.alumno_dni = :dni
        ORDER BY u.nombre
    ";
    $paramsAlu = [':curso' => $cursoId, ':year' => $yearId, ':dni' => $dni];

} elseif ($rol === 'familia') {
    // solo hijos asociados a esa familia
    $sqlAlu = "
        SELECT DISTINCT u.dni, u.nombre
        FROM familia_alumno fa
        JOIN asignado_alumno aa ON aa.alumno_dni = fa.alumno_dni
        JOIN usuarios u ON u.dni = aa.alumno_dni
        WHERE aa.curso_id = :curso
          AND aa.year_escolar_id = :year
          AND aa.estado = 'activo'
          AND fa.familia_dni = :dni
        ORDER BY u.nombre
    ";
    $paramsAlu = [':curso' => $cursoId, ':year' => $yearId, ':dni' => $dni];

} else {
    // profesor, preceptor, directivo: todos los alumnos del curso
    $sqlAlu = "
        SELECT u.dni, u.nombre
        FROM asignado_alumno aa
        JOIN usuarios u ON u.dni = aa.alumno_dni
        WHERE aa.curso_id = :curso
          AND aa.year_escolar_id = :year
          AND aa.estado = 'activo'
        ORDER BY u.nombre
    ";
    $paramsAlu = [':curso' => $cursoId, ':year' => $yearId];
}

$stmtAlu = $pdo->prepare($sqlAlu);
$stmtAlu->execute($paramsAlu);
$alumnos = $stmtAlu->fetchAll();

// notas ya guardadas (concepto, tp, examen, final) para mostrar
$notasDet = [];
if (!empty($alumnos)) {
    $dniList = array_column($alumnos, 'dni');
    $placeholders = implode(',', array_fill(0, count($dniList), '?'));

    $sqlNotas = "
        SELECT alumno_dni, cuatrimestre,
               nota_concepto, nota_tp, nota_examen, nota_final
        FROM notas_detalle
        WHERE materia_id = ?
          AND year_escolar_id = ?
          AND alumno_dni IN ($placeholders)
    ";
    $params = array_merge([$materiaId, $yearId], $dniList);
    $stmtNotas = $pdo->prepare($sqlNotas);
    $stmtNotas->execute($params);

    while ($row = $stmtNotas->fetch(PDO::FETCH_ASSOC)) {
        $aDni = (int)$row['alumno_dni'];
        $c    = (string)$row['cuatrimestre'];

        $notasDet[$aDni][$c] = [
            'concepto' => $row['nota_concepto'],
            'tp'       => $row['nota_tp'],
            'examen'   => $row['nota_examen'],
            'final'    => $row['nota_final'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Notas - S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />

  <link rel="stylesheet" href="css/menuHamburguesa.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <link rel="stylesheet" href="css/ChatFlotante.css">

  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#d8d7d7;}
    .sgi-title{margin-left:auto;font-weight:bold;padding:8px 15px;font-size:28px;}
    .table-box{
      background:white;
      padding:20px;
      border-radius:12px;
      box-shadow:0 4px 12px rgba(0,0,0,0.08);
      max-width:1100px;
      margin:20px auto;
    }
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{border:1px solid #ddd;padding:6px;text-align:center}
    th{background:#0f172a;color:white}
    input[type=number]{
      width:64px;
      text-align:center;
      padding:4px;
      border-radius:6px;
      border:1px solid #ccc
    }
    .btn-guardar{
      margin-top:14px;
      padding:10px 16px;
      background:#0f172a;
      color:white;
      border:none;
      border-radius:8px;
      cursor:pointer;
      font-weight:bold;
    }
    .btn-guardar:hover{background:#1e293b}
    .boton-volver{
      margin-top:10px;
      padding:8px 14px;
      background:#555;
      color:white;
      border:none;
      border-radius:8px;
      cursor:pointer;
      font-size:13px;
    }
    .boton-volver:hover{background:#333}
    .msj-ok{
      background:#e8f5e9;
      color:#256029;
      border-left:4px solid #388e3c;
      padding:8px 12px;
      border-radius:8px;
      margin-bottom:10px;
      font-size:14px;
    }
    @media(max-width:768px){
      table{font-size:12px}
      input[type=number]{width:52px}
    }
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

<div class="table-box">
  <h2 style="margin-top:0;margin-bottom:8px">
    <?= htmlspecialchars($materiaNombre); ?> - <?= $cursoLabel; ?>
  </h2>
  <p style="margin-top:0;font-size:13px;color:#333">
    Rol: <?= htmlspecialchars(ucfirst($rol)); ?> · Usuario: <?= htmlspecialchars($usuario ?: ''); ?>
  </p>

  <?php if ($mensaje): ?>
    <div class="msj-ok"><?= htmlspecialchars($mensaje); ?></div>
  <?php endif; ?>

  <?php if (empty($alumnos)): ?>
    <p>No hay alumnos visibles para tu rol en este curso para este año.</p>
  <?php else: ?>
    <form id="formNotas" method="post">
      <table>
        <tr>
          <th rowspan="2">DNI</th>
          <th rowspan="2">Alumno</th>
          <th colspan="4">1º Cuatrimestre</th>
          <th colspan="4">2º Cuatrimestre</th>
        </tr>
        <tr>
          <th>Concepto</th>
          <th>TP</th>
          <th>Examen</th>
          <th>Final</th>
          <th>Concepto</th>
          <th>TP</th>
          <th>Examen</th>
          <th>Final</th>
        </tr>

        <?php foreach ($alumnos as $a):
          $dniAlu   = (int)$a['dni'];
          $nombreAl = htmlspecialchars($a['nombre']);

          $nd1 = $notasDet[$dniAlu]['1'] ?? [];
          $nd2 = $notasDet[$dniAlu]['2'] ?? [];

          $c1ConceptoVal = $nd1['concepto'] ?? '';
          $c1TpVal       = $nd1['tp']       ?? '';
          $c1ExamenVal   = $nd1['examen']   ?? '';
          $c1Final       = $nd1['final']    ?? '';

          $c2ConceptoVal = $nd2['concepto'] ?? '';
          $c2TpVal       = $nd2['tp']       ?? '';
          $c2ExamenVal   = $nd2['examen']   ?? '';
          $c2Final       = $nd2['final']    ?? '';
        ?>
        <tr>
          <td><?= $dniAlu; ?></td>
          <td style="text-align:left;padding-left:8px"><?= $nombreAl; ?></td>

          <!-- C1 -->
          <td>
            <input type="number" min="1" max="10" step="0.01"
                   name="notas[<?= $dniAlu; ?>][c1_concepto]"
                   data-cuatr="1" data-tipo="concepto"
                   oninput="calcFila(this)"
                   value="<?= $c1ConceptoVal !== '' ? (float)$c1ConceptoVal : ''; ?>"
                   <?= $soloLectura ? 'disabled' : ''; ?>>
          </td>
          <td>
            <input type="number" min="1" max="10" step="0.01"
                   name="notas[<?= $dniAlu; ?>][c1_tp]"
                   data-cuatr="1" data-tipo="tp"
                   oninput="calcFila(this)"
                   value="<?= $c1TpVal !== '' ? (float)$c1TpVal : ''; ?>"
                   <?= $soloLectura ? 'disabled' : ''; ?>>
          </td>
          <td>
            <input type="number" min="1" max="10" step="0.01"
                   name="notas[<?= $dniAlu; ?>][c1_examen]"
                   data-cuatr="1" data-tipo="examen"
                   oninput="calcFila(this)"
                   value="<?= $c1ExamenVal !== '' ? (float)$c1ExamenVal : ''; ?>"
                   <?= $soloLectura ? 'disabled' : ''; ?>>
          </td>
          <td>
            <input type="number" disabled data-cuatr="1" data-tipo="final"
                   value="<?= $c1Final !== '' ? (float)$c1Final : ''; ?>">
          </td>

          <!-- C2 -->
          <td>
            <input type="number" min="1" max="10" step="0.01"
                   name="notas[<?= $dniAlu; ?>][c2_concepto]"
                   data-cuatr="2" data-tipo="concepto"
                   oninput="calcFila(this)"
                   value="<?= $c2ConceptoVal !== '' ? (float)$c2ConceptoVal : ''; ?>"
                   <?= $soloLectura ? 'disabled' : ''; ?>>
          </td>
          <td>
            <input type="number" min="1" max="10" step="0.01"
                   name="notas[<?= $dniAlu; ?>][c2_tp]"
                   data-cuatr="2" data-tipo="tp"
                   oninput="calcFila(this)"
                   value="<?= $c2TpVal !== '' ? (float)$c2TpVal : ''; ?>"
                   <?= $soloLectura ? 'disabled' : ''; ?>>
          </td>
          <td>
            <input type="number" min="1" max="10" step="0.01"
                   name="notas[<?= $dniAlu; ?>][c2_examen]"
                   data-cuatr="2" data-tipo="examen"
                   oninput="calcFila(this)"
                   value="<?= $c2ExamenVal !== '' ? (float)$c2ExamenVal : ''; ?>"
                   <?= $soloLectura ? 'disabled' : ''; ?>>
          </td>
          <td>
            <input type="number" disabled data-cuatr="2" data-tipo="final"
                   value="<?= $c2Final !== '' ? (float)$c2Final : ''; ?>">
          </td>
        </tr>
        <?php endforeach; ?>
      </table>

      <?php if (!$soloLectura): ?>
        <button class="btn-guardar" type="submit">💾 Guardar notas</button>
      <?php endif; ?>
      <button class="boton-volver" type="button" onclick="history.back()">⬅ Volver</button>
    </form>
  <?php endif; ?>
</div>

<a href="msg.php"><button id="boton-flotante">💬</button></a>

<script>
// cálculo de finales (profesor); alumnos/familia/preceptor solo ven los valores guardados
function calcFila(input){
  const row = input.closest('tr');
  if (!row) return;

  [1,2].forEach(cuatr => {
    const concepto = row.querySelector('input[data-cuatr="'+cuatr+'"][data-tipo="concepto"]');
    const tp       = row.querySelector('input[data-cuatr="'+cuatr+'"][data-tipo="tp"]');
    const examen   = row.querySelector('input[data-cuatr="'+cuatr+'"][data-tipo="examen"]');
    const finalInp = row.querySelector('input[data-cuatr="'+cuatr+'"][data-tipo="final"]');

    if (!finalInp) return;

    const valores = [];
    [concepto, tp, examen].forEach(el => {
      if (!el) return;
      const v = parseFloat(el.value);
      if (!isNaN(v)) valores.push(v);
    });

    if (valores.length === 0) {
      finalInp.value = "";
      return;
    }

    const prom = valores.reduce((a,b)=>a+b,0) / valores.length;
    finalInp.value = Math.floor(prom); // siempre para abajo
  });
}

window.APP_USER_NAME = "<?= htmlspecialchars($usuario ?: 'Usuario'); ?>";
</script>
<script src="/js/main.js"></script>
</body>
</html>

