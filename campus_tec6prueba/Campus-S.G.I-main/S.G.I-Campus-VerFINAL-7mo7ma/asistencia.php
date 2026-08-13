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
require __DIR__ . '/config.php';
$conn = db()->query('SELECT 1'); // warm-up
$pdo  = db();

// Helper para mysqli compatible con el código existente (PDO ya disponible)
$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) die("Error de conexión: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// 3) AÑO ESCOLAR ACTUAL
$yearActual    = (int)date('Y');
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
    while ($fila = $res->fetch_assoc()) $cursos[] = $fila;

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
    while ($fila = $res->fetch_assoc()) $cursos[] = $fila;
    $stmt->close();

} elseif ($rol === 'preceptor') {
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
    while ($fila = $res->fetch_assoc()) $cursos[] = $fila;
    $stmt->close();
}

// 5) PARÁMETROS DE FILTRO
$cursoSeleccionado = isset($_REQUEST['curso_id']) ? (int)$_REQUEST['curso_id'] : (count($cursos) ? (int)$cursos[0]['id'] : 0);
$idsPermitidos     = array_map(fn($c) => (int)$c['id'], $cursos);
if (!in_array($cursoSeleccionado, $idsPermitidos, true)) {
    $cursoSeleccionado = count($cursos) ? (int)$cursos[0]['id'] : 0;
}

$mesSeleccionado  = isset($_REQUEST['mes'])  ? (int)$_REQUEST['mes']  : (int)date('n');
$anioSeleccionado = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : (int)date('Y');

// ── MÓDULO 3: Filtro por día ───────────────────────────────────────────
// Si se elige un día específico se muestra solo esa columna.
// Si es "" (vacío) se muestra el mes completo (comportamiento original).
$diaFiltro = isset($_REQUEST['dia']) && $_REQUEST['dia'] !== '' ? (int)$_REQUEST['dia'] : 0;
$totalDias = cal_days_in_month(CAL_GREGORIAN, $mesSeleccionado, $anioSeleccionado);
if ($diaFiltro < 0 || $diaFiltro > $totalDias) $diaFiltro = 0;

// 6) GUARDAR ASISTENCIA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar']) && $cursoSeleccionado) {
    if (isset($_POST['estado']) && is_array($_POST['estado'])) {
        foreach ($_POST['estado'] as $dniAlumno => $dias) {
            $dniAlumno = (int)$dniAlumno;
            foreach ($dias as $dia => $estado) {
                $dia    = (int)$dia;
                $estado = in_array($estado, ['presente','ausente','tarde','justificado'], true) ? $estado : '';
                $fecha  = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $dia);

                if ($estado === '') {
                    $del = $conn->prepare("DELETE FROM asistencia WHERE alumno_dni = ? AND fecha = ?");
                    $del->bind_param("is", $dniAlumno, $fecha);
                    $del->execute(); $del->close();
                    continue;
                }

                $motivo = null;
                $busca  = $conn->prepare("SELECT motivo_justificado FROM asistencia WHERE alumno_dni = ? AND fecha = ? LIMIT 1");
                $busca->bind_param("is", $dniAlumno, $fecha);
                $busca->execute();
                $resultado = $busca->get_result();
                if ($fila = $resultado->fetch_assoc()) $motivo = $fila['motivo_justificado'];
                $busca->close();

                $del = $conn->prepare("DELETE FROM asistencia WHERE alumno_dni = ? AND fecha = ?");
                $del->bind_param("is", $dniAlumno, $fecha);
                $del->execute(); $del->close();

                $ins = $conn->prepare("INSERT INTO asistencia (alumno_dni, fecha, estado, motivo_justificado) VALUES (?, ?, ?, ?)");
                $ins->bind_param("isss", $dniAlumno, $fecha, $estado, $motivo);
                $ins->execute(); $ins->close();
            }
        }
        $mensajeOK = "Asistencia guardada correctamente.";
    }
}

// 7) ALUMNOS DEL CURSO
$alumnos = [];
if ($cursoSeleccionado) {
    $sql  = "SELECT u.dni, u.nombre FROM asignado_alumno aa
             JOIN usuarios u ON u.dni = aa.alumno_dni
             WHERE aa.curso_id = ? AND aa.year_escolar_id = ? AND aa.estado = 'activo'
             ORDER BY u.nombre";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cursoSeleccionado, $yearEscolarId);
    $stmt->execute();
    $res  = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) $alumnos[] = $fila;
    $stmt->close();
}

// 8) ASISTENCIA YA GUARDADA
$asistencias    = [];
$motivosPrevios = [];
if ($alumnos) {
    $dniList  = implode(',', array_map('intval', array_column($alumnos, 'dni')));
    $desde    = sprintf('%04d-%02d-01', $anioSeleccionado, $mesSeleccionado);
    $hasta    = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $totalDias);

    $sql  = "SELECT alumno_dni, fecha, estado, motivo_justificado FROM asistencia
             WHERE alumno_dni IN ($dniList) AND fecha BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $desde, $hasta);
    $stmt->execute();
    $res  = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $dia = (int)date('j', strtotime($fila['fecha']));
        $asistencias[(int)$fila['alumno_dni']][$dia] = $fila['estado'];
        if (!empty($fila['motivo_justificado'])) {
            $motivosPrevios[$fila['alumno_dni'] . '_' . $dia] = $fila['motivo_justificado'];
        }
    }
    $stmt->close();
}

// ── MÓDULO 4: Totales de asistencias por alumno ────────────────────────
$totalesPorAlumno = []; // [dni] => [presentes, ausentes, tardanzas, justificados, total, porcentaje]
foreach ($alumnos as $al) {
    $dni  = (int)$al['dni'];
    $data = $asistencias[$dni] ?? [];
    $p = $a = $t = $j = 0;
    foreach ($data as $dia => $est) {
        // Solo días laborables (lun-vie)
        $fechaDia = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $dia);
        $dow = (int)date('w', strtotime($fechaDia));
        if ($dow === 0 || $dow === 6) continue;
        if ($est === 'presente')    $p++;
        elseif ($est === 'ausente') $a++;
        elseif ($est === 'tarde')   $t++;
        elseif ($est === 'justificado') $j++;
    }
    $total = $p + $a + $t + $j;
    $totalesPorAlumno[$dni] = [
        'presentes'    => $p,
        'ausentes'     => $a,
        'tardanzas'    => $t,
        'justificados' => $j,
        'total'        => $total,
        'porcentaje'   => $total > 0 ? round(($p + $j) / $total * 100, 1) : 0,
    ];
}

// Días laborables del mes (para el resumen general)
$diasLaborablesDelMes = 0;
for ($d = 1; $d <= $totalDias; $d++) {
    $dow = (int)date('w', strtotime(sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $d)));
    if ($dow !== 0 && $dow !== 6) $diasLaborablesDelMes++;
}

$nombreUsuario = $_SESSION['usuario'] ?? 'Usuario';
$meses = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// Columnas a mostrar (filtro por día o mes completo)
$diasAMostrar = [];
if ($diaFiltro > 0) {
    $diasAMostrar = [$diaFiltro];
} else {
    for ($d = 1; $d <= $totalDias; $d++) $diasAMostrar[] = $d;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Sistema de Asistencia - S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #d8d7d7; }
    header { background-color: #0f172a; color: #fff; }
    .logo, .logo2 { width: 100px; height: auto; }
    .title-box { text-align: center; flex-grow: 1; }
    .title-box h1 { margin: 0; font-size: 28px; }
    .title-box h2 { margin: 0; font-size: 16px; font-weight: normal; }

    main { padding: 20px; }
    .contenedor {
      max-width: 1200px; margin: 20px auto;
      background: #fff; padding: 20px;
      border-radius: 15px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    /* ── Filtros ── */
    .filtros-superior {
      display: flex; gap: 16px; align-items: center; justify-content: center;
      margin-bottom: 16px; flex-wrap: wrap;
    }
    .filtros-superior label { font-weight: bold; font-size: 13px; }
    .filtros-superior select, .filtros-superior input[type="number"] {
      padding: 5px 8px; border-radius: 6px; border: 1px solid #999; font-size: 13px;
    }
    /* ── MÓDULO 3: botón filtrar día ── */
    .btn-filtrar {
      background: #1d4ed8; color: white; border: none;
      border-radius: 6px; padding: 6px 14px; cursor: pointer; font-size: 13px;
    }
    .btn-filtrar:hover { background: #1e40af; }
    .btn-limpiar-dia {
      background: #6b7280; color: white; border: none;
      border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 13px;
    }
    .btn-limpiar-dia:hover { background: #4b5563; }

    /* ── Badge día único ── */
    .badge-dia-unico {
      display: inline-block; background: #dbeafe; color: #1d4ed8;
      border: 1px solid #93c5fd; border-radius: 20px;
      padding: 3px 12px; font-size: 13px; font-weight: bold;
      margin-bottom: 8px;
    }

    /* ── Widget presentes hoy (Módulo 7) ── */
    .widget-hoy {
      display: flex; gap: 10px; flex-wrap: wrap;
      margin-bottom: 18px; align-items: stretch;
    }
    .widget-hoy .wcard {
      flex: 1; min-width: 90px;
      background: #fff; border-radius: 10px;
      padding: 10px 8px; text-align: center;
      box-shadow: 0 2px 6px rgba(0,0,0,.08);
      border-top: 4px solid #e2e8f0;
    }
    .widget-hoy .wcard .wnum { font-size: 28px; font-weight: bold; }
    .widget-hoy .wcard .wlbl { font-size: 11px; color: #64748b; margin-top: 2px; }
    .wcard-tot  { border-color: #0f172a !important; }  .wcard-tot  .wnum { color: #0f172a; }
    .wcard-pres { border-color: #16a34a !important; }  .wcard-pres .wnum { color: #16a34a; }
    .wcard-aus  { border-color: #dc2626 !important; }  .wcard-aus  .wnum { color: #dc2626; }
    .wcard-tard { border-color: #d97706 !important; }  .wcard-tard .wnum { color: #d97706; }
    .wcard-just { border-color: #2563eb !important; }  .wcard-just .wnum { color: #2563eb; }
    .widget-hoy-titulo {
      font-size: 12px; font-weight: bold; color: #64748b;
      text-transform: uppercase; letter-spacing: .5px;
      margin-bottom: 6px;
    }

    h3.titulo-asistencia { text-align:center; margin-bottom:10px; font-size:22px; text-transform:uppercase; }
    .subtitulo-mes { text-align:center; font-size:18px; margin-bottom:15px; }

    table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:13px; }
    th, td { border:1px solid #333; padding:4px; text-align:center; word-wrap:break-word; }
    th.alumnos-col { width:160px; text-align:left; background:#f3f4f6; }
    th.dia-col { background:#e5e7eb; }

    /* ── MÓDULO 4: columnas de totales ── */
    th.total-col { background:#0f172a; color:#fff; font-size:12px; width:46px; }
    td.total-num { font-size:12px; font-weight:bold; }
    td.total-p   { background:#dcfce7; color:#166534; }
    td.total-a   { background:#fee2e2; color:#991b1b; }
    td.total-t   { background:#fef9c3; color:#854d0e; }
    td.total-j   { background:#dbeafe; color:#1d4ed8; }
    td.total-pct { font-weight:bold; }
    td.total-pct.pct-ok  { color:#166534; }
    td.total-pct.pct-med { color:#854d0e; }
    td.total-pct.pct-mal { color:#991b1b; background:#fee2e2; }

    .celda-estado { cursor:pointer; user-select:none; font-weight:bold; position:relative; }
    .celda-estado span.letra { display:block; width:100%; }
    .estado-presente    { background-color:#c8f7c5; color:#0a7511; }
    .estado-ausente     { background-color:#f7c5c5; color:#a00000; }
    .estado-tarde       { background-color:#fde68a; color:#92400e; }
    .estado-justificado { background-color:#dbeafe; color:#1d4ed8; }
    .dia-no-laborable   { background:#f3f4f6; color:#9ca3af; text-decoration:line-through; text-decoration-thickness:2px; opacity:.85; }
    .celda-estado.no-laborable { cursor:not-allowed; background:#f3f4f6; color:#9ca3af; text-decoration:line-through; opacity:.85; }

    /* ── MÓDULO 4: resumen al pie ── */
    .resumen-totales {
      margin-top: 20px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 16px 20px;
    }
    .resumen-totales h4 { margin: 0 0 12px; font-size: 15px; color: #0f172a; }
    .resumen-grid {
      display: flex; gap: 12px; flex-wrap: wrap;
    }
    .resumen-card {
      flex: 1; min-width: 120px;
      border-radius: 8px; padding: 12px 16px; text-align: center;
    }
    .resumen-card .rc-num { font-size: 28px; font-weight: bold; }
    .resumen-card .rc-lbl { font-size: 12px; margin-top: 2px; }
    .rc-dias  { background:#f1f5f9; color:#334155; }
    .rc-pres  { background:#dcfce7; color:#166534; }
    .rc-aus   { background:#fee2e2; color:#991b1b; }
    .rc-tard  { background:#fef9c3; color:#854d0e; }
    .rc-just  { background:#dbeafe; color:#1d4ed8; }
    .rc-prom  { background:#f3e8ff; color:#6b21a8; }

    .botones { text-align:center; margin-top:15px; }
    .botones button {
      background:#0f172a; color:white; border:none; border-radius:8px;
      padding:10px 20px; cursor:pointer; margin:5px; font-size:14px;
    }
    .botones button:hover { background:#1e293b; }

    .alerta-ok {
      text-align:center; background:#16a34a; color:white;
      padding:8px; border-radius:8px; margin-bottom:10px;
      display:<?php echo isset($mensajeOK) ? 'block' : 'none'; ?>;
    }

    @media (max-width:768px) {
      .logo { display:none; }
      table { font-size:11px; }
      th.alumnos-col { width:110px; }
      th.total-col { width:34px; }
    }

    /* ── Modal justificado ── */
    #modalJustificado { display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.45); align-items:center; justify-content:center; }
    #modalJustificado.activo { display:flex; }
    .modal-box { background:#fff; border-radius:14px; padding:28px 32px; width:100%; max-width:420px; box-shadow:0 8px 32px rgba(0,0,0,.22); position:relative; }
    .modal-box h3 { margin:0 0 6px; font-size:18px; color:#0f172a; }
    .modal-meta { font-size:13px; color:#555; margin-bottom:16px; }
    .modal-meta span { font-weight:bold; color:#1d4ed8; }
    .modal-box label { display:block; font-size:13px; font-weight:bold; margin-bottom:6px; color:#374151; }
    .modal-box textarea { width:100%; box-sizing:border-box; border:1px solid #94a3b8; border-radius:8px; padding:10px; font-size:14px; resize:vertical; min-height:90px; }
    .modal-box textarea:focus { outline:none; border-color:#1d4ed8; box-shadow:0 0 0 2px #bfdbfe; }
    .modal-botones { display:flex; gap:10px; margin-top:16px; justify-content:flex-end; }
    .modal-botones button { border:none; border-radius:8px; padding:9px 20px; cursor:pointer; font-size:14px; font-weight:bold; }
    .btn-cancelar-modal { background:#e2e8f0; color:#374151; }
    .btn-cancelar-modal:hover { background:#cbd5e1; }
    .btn-guardar-modal { background:#1d4ed8; color:#fff; }
    .btn-guardar-modal:hover { background:#1e40af; }
    .modal-error { color:#dc2626; font-size:12px; margin-top:6px; display:none; }
  </style>
</head>
<body>
<header>
  <div class="navbar">
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
    <div class="alerta-ok" id="alertaJustificadoOK" style="display:none;">✔ Justificado guardado correctamente.</div>

    <?php if (in_array($rol, ['preceptor','directivo'])): ?>
    <!-- ── Módulo 7: Presentes hoy ── -->
    <div class="widget-hoy-titulo" id="widgetTitulo">📅
      <?php if ($diaFiltro > 0): ?>
        <?php echo $diaFiltro . ' de ' . $meses[$mesSeleccionado] . ' ' . $anioSeleccionado; ?>
      <?php else: ?>
        Asistencia de hoy — <?php echo date('d/m/Y'); ?>
      <?php endif; ?>
    </div>
    <div class="widget-hoy" id="widgetHoy">
      <div class="wcard wcard-tot"><div class="wnum" id="whTot">…</div><div class="wlbl">Total</div></div>
      <div class="wcard wcard-pres"><div class="wnum" id="whPre">…</div><div class="wlbl">Presentes</div></div>
      <div class="wcard wcard-aus"><div class="wnum" id="whAus">…</div><div class="wlbl">Ausentes</div></div>
      <div class="wcard wcard-tard"><div class="wnum" id="whTar">…</div><div class="wlbl">Tardanzas</div></div>
      <div class="wcard wcard-just"><div class="wnum" id="whJus">…</div><div class="wlbl">Justificados</div></div>
    </div>
    <?php endif; ?>

    <h3 class="titulo-asistencia">Lista de Asistencia</h3>

    <!-- ════════════════════════════════════════════════════════════
         FILTROS: Curso / Mes / Año / DÍA (Módulo 3)
    ════════════════════════════════════════════════════════════ -->
    <form method="GET" style="margin:0 0 10px 0;" id="formFiltros">
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
            <?php foreach ($meses as $num => $nom): ?>
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

        <!-- ── Módulo 3: Filtro por día ── -->
        <div style="display:flex;align-items:center;gap:6px;border-left:2px solid #e2e8f0;padding-left:16px;">
          <label>Ver día:</label>
          <input type="number" name="dia" id="inputDia" min="1" max="<?php echo $totalDias; ?>"
                 value="<?php echo $diaFiltro > 0 ? $diaFiltro : ''; ?>"
                 placeholder="dd" style="width:60px;">
          <button type="submit" class="btn-filtrar">Filtrar</button>
          <?php if ($diaFiltro > 0): ?>
            <a href="?curso_id=<?php echo $cursoSeleccionado; ?>&mes=<?php echo $mesSeleccionado; ?>&anio=<?php echo $anioSeleccionado; ?>">
              <button type="button" class="btn-limpiar-dia">✕ Ver mes completo</button>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <div class="subtitulo-mes">
      <?php if ($diaFiltro > 0): ?>
        <span class="badge-dia-unico">
          📅 Mostrando solo: <?php echo $diaFiltro . ' de ' . $meses[$mesSeleccionado] . ' ' . $anioSeleccionado; ?>
        </span>
      <?php else: ?>
        MES: <?php echo $meses[$mesSeleccionado]; ?> <?php echo $anioSeleccionado; ?>
      <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         TABLA DE ASISTENCIA (con columnas de totales - Módulo 4)
    ════════════════════════════════════════════════════════════ -->
    <form method="POST">
      <input type="hidden" name="curso_id" value="<?php echo $cursoSeleccionado; ?>">
      <input type="hidden" name="mes"      value="<?php echo $mesSeleccionado; ?>">
      <input type="hidden" name="anio"     value="<?php echo $anioSeleccionado; ?>">
      <?php if ($diaFiltro > 0): ?>
        <input type="hidden" name="dia" value="<?php echo $diaFiltro; ?>">
      <?php endif; ?>

      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th class="alumnos-col">Alumnos</th>
            <?php foreach ($diasAMostrar as $d):
              $fechaDia      = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $d);
              $esFinDeSemana = in_array((int)date('w', strtotime($fechaDia)), [0, 6]);
            ?>
              <th class="dia-col<?php echo $esFinDeSemana ? ' dia-no-laborable' : ''; ?>">
                <?php echo $d; ?>
              </th>
            <?php endforeach; ?>
            <!-- Módulo 4: encabezados de totales -->
            <?php if ($diaFiltro === 0): ?>
            <th class="total-col">P</th>
            <th class="total-col">A</th>
            <th class="total-col">T</th>
            <th class="total-col">J</th>
            <th class="total-col">%</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$alumnos): ?>
          <tr><td colspan="<?php echo count($diasAMostrar) + ($diaFiltro === 0 ? 6 : 1); ?>">No hay alumnos asignados a este curso.</td></tr>
        <?php else: ?>
          <?php foreach ($alumnos as $al):
                $dniAl = (int)$al['dni']; ?>
            <tr>
              <td style="text-align:left;"><?php echo htmlspecialchars($al['nombre']); ?></td>
              <?php foreach ($diasAMostrar as $d):
                    $fechaDia      = sprintf('%04d-%02d-%02d', $anioSeleccionado, $mesSeleccionado, $d);
                    $esFinDeSemana = in_array((int)date('w', strtotime($fechaDia)), [0, 6]);
                    $valor = $asistencias[$dniAl][$d] ?? '';
                    $clase = $letra = '';
                    if ($valor === 'presente')    { $clase = 'estado-presente';    $letra = 'P'; }
                    elseif ($valor === 'ausente') { $clase = 'estado-ausente';     $letra = 'A'; }
                    elseif ($valor === 'tarde')   { $clase = 'estado-tarde';       $letra = 'T'; }
                    elseif ($valor === 'justificado') { $clase = 'estado-justificado'; $letra = 'J'; }
                    if ($esFinDeSemana) $clase .= ' no-laborable';
              ?>
                <td class="celda-estado <?php echo trim($clase); ?>"
                    data-dni="<?php echo $dniAl; ?>"
                    data-dia="<?php echo $d; ?>"
                    data-valor="<?php echo $valor; ?>"
                    data-no-laborable="<?php echo $esFinDeSemana ? '1' : '0'; ?>">
                  <span class="letra"><?php echo $letra; ?></span>
                  <input type="hidden" name="estado[<?php echo $dniAl; ?>][<?php echo $d; ?>]" value="<?php echo $valor; ?>">
                </td>
              <?php endforeach; ?>

              <!-- Módulo 4: totales por alumno -->
              <?php if ($diaFiltro === 0):
                    $tot = $totalesPorAlumno[$dniAl] ?? ['presentes'=>0,'ausentes'=>0,'tardanzas'=>0,'justificados'=>0,'porcentaje'=>0];
                    $pct = $tot['porcentaje'];
                    $pctClass = $pct >= 85 ? 'pct-ok' : ($pct >= 70 ? 'pct-med' : 'pct-mal');
              ?>
              <td class="total-num total-p"><?php echo $tot['presentes'];    ?></td>
              <td class="total-num total-a"><?php echo $tot['ausentes'];     ?></td>
              <td class="total-num total-t"><?php echo $tot['tardanzas'];    ?></td>
              <td class="total-num total-j"><?php echo $tot['justificados']; ?></td>
              <td class="total-num total-pct <?php echo $pctClass; ?>"><?php echo $pct; ?>%</td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      </div>

      <div class="botones">
        <button type="submit" name="guardar">💾 Guardar</button>
      </div>
    </form>

    <!-- ════════════════════════════════════════════════════════════
         MÓDULO 4: Resumen general del mes
    ════════════════════════════════════════════════════════════ -->
    <?php if ($alumnos && $diaFiltro === 0):
      $totP = $totA = $totT = $totJ = 0;
      foreach ($totalesPorAlumno as $t) {
          $totP += $t['presentes']; $totA += $t['ausentes'];
          $totT += $t['tardanzas']; $totJ += $t['justificados'];
      }
      $totalReg      = $totP + $totA + $totT + $totJ;
      $promAsistencia = $totalReg > 0 ? round(($totP + $totJ) / $totalReg * 100, 1) : 0;
    ?>
    <div class="resumen-totales">
      <h4>📊 Resumen del mes — <?php echo $meses[$mesSeleccionado] . ' ' . $anioSeleccionado; ?></h4>
      <div class="resumen-grid">
        <div class="resumen-card rc-dias">
          <div class="rc-num"><?php echo $diasLaborablesDelMes; ?></div>
          <div class="rc-lbl">Días hábiles</div>
        </div>
        <div class="resumen-card rc-pres">
          <div class="rc-num"><?php echo $totP; ?></div>
          <div class="rc-lbl">Presentes</div>
        </div>
        <div class="resumen-card rc-aus">
          <div class="rc-num"><?php echo $totA; ?></div>
          <div class="rc-lbl">Ausentes</div>
        </div>
        <div class="resumen-card rc-tard">
          <div class="rc-num"><?php echo $totT; ?></div>
          <div class="rc-lbl">Tardanzas</div>
        </div>
        <div class="resumen-card rc-just">
          <div class="rc-num"><?php echo $totJ; ?></div>
          <div class="rc-lbl">Justificados</div>
        </div>
        <div class="resumen-card rc-prom">
          <div class="rc-num"><?php echo $promAsistencia; ?>%</div>
          <div class="rc-lbl">Prom. asistencia</div>
        </div>
      </div>
      <p style="font-size:12px;color:#64748b;margin:10px 0 0;">
        Leyenda columnas: <strong>P</strong> Presentes · <strong>A</strong> Ausentes ·
        <strong>T</strong> Tardanzas · <strong>J</strong> Justificados · <strong>%</strong> % asistencia.
        Se resalta en rojo el alumno con menos del 70&nbsp;% de asistencia.
      </p>
    </div>
    <?php endif; ?>

  </div><!-- /contenedor -->
</main>

<!-- Modal justificado (idéntico al original) -->
<div id="modalJustificado" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
  <div class="modal-box">
    <h3 id="modalTitulo">📋 Registrar Justificado</h3>
    <p class="modal-meta">
      Alumno: <span id="modalNombreAlumno">—</span><br>
      Fecha:  <span id="modalFechaTexto">—</span>
    </p>
    <label for="modalMotivo">Motivo del justificado:</label>
    <textarea id="modalMotivo" placeholder="Ej: Certificado médico, trámite familiar…" maxlength="500"></textarea>
    <div class="modal-error" id="modalError">Por favor ingresá el motivo antes de guardar.</div>
    <div class="modal-botones">
      <button class="btn-cancelar-modal" id="btnCancelarModal">Cancelar</button>
      <button class="btn-guardar-modal"  id="btnGuardarModal">💾 Guardar</button>
    </div>
  </div>
</div>

<script src="js/main.js"></script>
<script>
window.APP_USER_NAME = "<?= htmlspecialchars($_SESSION['usuario'] ?? 'Usuario'); ?>";

const alumnosData = {
  <?php foreach ($alumnos as $al): ?>
  <?php echo (int)$al['dni']; ?>: <?php echo json_encode(htmlspecialchars($al['nombre'])); ?>,
  <?php endforeach; ?>
};
const mesActual  = <?php echo $mesSeleccionado; ?>;
const anioActual = <?php echo $anioSeleccionado; ?>;
let celdaPendiente = null;
let motivosPrevios = <?php echo json_encode($motivosPrevios ?? []); ?>;

function abrirModal(celda) {
  const dni = celda.dataset.dni, dia = celda.dataset.dia;
  const key = `${dni}_${dia}`;
  const fecha = `${anioActual}-${String(mesActual).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
  document.getElementById('modalNombreAlumno').textContent = alumnosData[dni] ?? `DNI ${dni}`;
  document.getElementById('modalFechaTexto').textContent   = fecha;
  document.getElementById('modalMotivo').value             = motivosPrevios[key] ?? '';
  document.getElementById('modalError').style.display      = 'none';
  celdaPendiente = celda;
  document.getElementById('modalJustificado').classList.add('activo');
  document.getElementById('modalMotivo').focus();
}

function cerrarModal(revertir = true) {
  document.getElementById('modalJustificado').classList.remove('activo');
  if (revertir && celdaPendiente) {
    aplicarEstado(celdaPendiente, '');
    celdaPendiente.dataset.valor = '';
    celdaPendiente.querySelector('input[type="hidden"]').value = '';
  }
  celdaPendiente = null;
}

function aplicarEstado(celda, valor) {
  const span = celda.querySelector('span.letra');
  celda.classList.remove('estado-presente','estado-ausente','estado-tarde','estado-justificado');
  const mapa = {
    presente:    ['estado-presente',    'P'],
    ausente:     ['estado-ausente',     'A'],
    tarde:       ['estado-tarde',       'T'],
    justificado: ['estado-justificado', 'J'],
    '':          [null, '']
  };
  const [cls, letra] = mapa[valor] ?? [null, ''];
  if (cls) celda.classList.add(cls);
  span.textContent = letra;
}

document.querySelectorAll('.celda-estado').forEach(celda => {
  celda.addEventListener('click', () => {
    if (celda.dataset.noLaborable === '1') return;
    let valor = celda.dataset.valor || '';
    if (valor === '')             valor = 'presente';
    else if (valor === 'presente')   valor = 'ausente';
    else if (valor === 'ausente')    valor = 'tarde';
    else if (valor === 'tarde')      valor = 'justificado';
    else                             valor = '';
    celda.dataset.valor = valor;
    celda.querySelector('input[type="hidden"]').value = valor;
    aplicarEstado(celda, valor);
    if (valor === 'justificado') abrirModal(celda);
  });
});

document.getElementById('btnCancelarModal').addEventListener('click', () => cerrarModal(true));
document.getElementById('modalJustificado').addEventListener('click', e => {
  if (e.target === document.getElementById('modalJustificado')) cerrarModal(true);
});

document.getElementById('btnGuardarModal').addEventListener('click', async () => {
  const motivo = document.getElementById('modalMotivo').value.trim();
  if (!motivo) { document.getElementById('modalError').style.display = 'block'; return; }
  document.getElementById('modalError').style.display = 'none';
  const celda = celdaPendiente, dni = celda.dataset.dni, dia = celda.dataset.dia;
  const fecha = `${anioActual}-${String(mesActual).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
  const key   = `${dni}_${dia}`;
  const btn   = document.getElementById('btnGuardarModal');
  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    const body = new URLSearchParams({ alumno_dni: dni, fecha, motivo });
    const resp = await fetch('api_guardar_justificado.php', { method: 'POST', body });
    const data = await resp.json();
    if (data.ok) {
      motivosPrevios[key] = motivo;
      cerrarModal(false);
      const alerta = document.getElementById('alertaJustificadoOK');
      alerta.style.display = 'block';
      alerta.scrollIntoView({ behavior:'smooth', block:'center' });
      setTimeout(() => { alerta.style.display = 'none'; }, 3500);
    } else { alert('Error al guardar: ' + (data.error ?? 'Desconocido')); }
  } catch { alert('Error de red.'); }
  finally { btn.disabled = false; btn.textContent = '💾 Guardar'; }
});

// ── Módulo 7: widget de presentes hoy ─────────────────────────────
function actualizarWidget(data) {
  if (!data || data.error) { console.warn('Widget presentes hoy:', data?.error); return; }
  const g = data.general;
  document.getElementById('whTot').textContent = g.total        ?? 0;
  document.getElementById('whPre').textContent = g.presentes    ?? 0;
  document.getElementById('whAus').textContent = g.ausentes     ?? 0;
  document.getElementById('whTar').textContent = g.tardanzas    ?? 0;
  document.getElementById('whJus').textContent = g.justificados ?? 0;
}

// Fecha del filtro activo (si hay uno) o hoy
const diaFiltroActivo = <?php echo $diaFiltro > 0 ? $diaFiltro : 0; ?>;
const mesFiltro       = <?php echo $mesSeleccionado; ?>;
const anioFiltro      = <?php echo $anioSeleccionado; ?>;

function getFechaWidget() {
  if (diaFiltroActivo > 0) {
    return `${anioFiltro}-${String(mesFiltro).padStart(2,'0')}-${String(diaFiltroActivo).padStart(2,'0')}`;
  }
  return 'hoy';
}

async function cargarWidgetHoy() {
  if (!document.getElementById('widgetHoy')) return;
  try {
    const fecha = getFechaWidget();
    const url   = fecha === 'hoy' ? 'api_presentes_hoy.php' : `api_presentes_hoy.php?fecha=${fecha}`;
    const resp  = await fetch(url);
    if (!resp.ok) { console.warn('Widget: HTTP', resp.status); return; }
    const data  = await resp.json();
    actualizarWidget(data);
  } catch(e) { console.warn('Widget error:', e); }
}

cargarWidgetHoy();
setInterval(cargarWidgetHoy, 5 * 60 * 1000);
</script>
</body>
</html>