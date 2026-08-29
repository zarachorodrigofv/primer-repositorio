<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers_academico.php';
requireLogin();

if (!esFamilia()) {
    header("Location: SGI.php");
    exit;
}

$familiaDni = $_SESSION['dni'];
$pdo = db();

// Buscar alumnos vinculados a esta familia
$stmt = $pdo->prepare(
    "SELECT u.dni, u.nombre, fa.parentesco
     FROM familia_alumno fa
     JOIN usuarios u ON u.dni = fa.alumno_dni
     WHERE fa.familia_dni = ?
     ORDER BY u.nombre"
);
$stmt->execute([$familiaDni]);
$hijos = $stmt->fetchAll();

// Selección de hijo
$hijoDni = $_GET['alumno'] ?? ($hijos[0]['dni'] ?? null);

// Datos del hijo seleccionado
$alumnoInfo = null;
$notas      = [];
$asistencia = [];

if ($hijoDni) {
    // Verificar que el hijo pertenece a esta familia
    $valid = array_filter($hijos, fn($h) => $h['dni'] == $hijoDni);
    if (!$valid) { $hijoDni = null; }
}

if ($hijoDni) {
    $stmt = $pdo->prepare("SELECT dni, nombre FROM usuarios WHERE dni = ?");
    $stmt->execute([$hijoDni]);
    $alumnoInfo = $stmt->fetch();

    // Boletín: solo notas ya guardadas por la escuela.
    $yearIdFamilia = currentYearEscolarId($pdo);
    $stmt = $pdo->prepare(
        "SELECT m.nombre AS materia,
                MAX(CASE WHEN nd.cuatrimestre='1' THEN nd.nota_valorativa END) AS c1_val,
                MAX(CASE WHEN nd.cuatrimestre='1' THEN nd.nota_numerica END) AS c1_num,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_valorativa END) AS c2_val,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_numerica END) AS c2_num,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_final END) AS nota_final,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.observaciones END) AS observaciones
         FROM notas_detalle nd
         JOIN materias m ON m.id = nd.materia_id
         WHERE nd.alumno_dni = ?
           AND nd.year_escolar_id = ?
         GROUP BY nd.materia_id, m.nombre
         ORDER BY m.nombre"
    );
    $stmt->execute([$hijoDni, $yearIdFamilia]);
    $notas = $stmt->fetchAll();

    // Asistencia del mes actual
    $mesActual = date('Y-m');
    $stmt = $pdo->prepare(
        "SELECT fecha, estado FROM asistencia
         WHERE alumno_dni = ?
           AND DATE_FORMAT(fecha,'%Y-%m') = ?
         ORDER BY fecha"
    );
    $stmt->execute([$hijoDni, $mesActual]);
    $asistencia = $stmt->fetchAll();
}

$nombreUsuario = $_SESSION['usuario'] ?? 'Familia';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Familiar — S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <style>
    body { font-family:Arial,sans-serif; margin:0; background:#d8d7d7; }
    header { background:#0f172a; color:#fff; }
    .logo,.logo2 { width:100px; height:auto; }
    .title-box { text-align:center; flex-grow:1; }
    .title-box h1 { margin:0; font-size:22px; }
    main { padding:20px; max-width:900px; margin:20px auto; }

    /* Selector de hijo */
    .selector-hijos { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
    .btn-hijo {
      padding:10px 20px; border-radius:10px; border:2px solid #cbd5e1;
      background:#fff; cursor:pointer; font-size:14px; font-weight:500;
      transition:all .2s; color:#334155;
    }
    .btn-hijo.activo { background:#0f172a; color:#fff; border-color:#0f172a; }
    .btn-hijo:hover { border-color:#1d4ed8; }

    .card { background:#fff; border-radius:14px; padding:20px; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,.1); }
    .card h3 { margin:0 0 14px; font-size:16px; color:#0f172a; border-bottom:2px solid #e2e8f0; padding-bottom:8px; }

    /* Stats asistencia */
    .stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    .stat { flex:1; min-width:80px; border-radius:10px; padding:12px; text-align:center; }
    .stat .sn { font-size:26px; font-weight:bold; }
    .stat .sl { font-size:11px; margin-top:2px; }
    .st-p { background:#dcfce7; color:#166534; }
    .st-a { background:#fee2e2; color:#991b1b; }

    table { width:100%; border-collapse:collapse; font-size:13px; }
    th { background:#f1f5f9; padding:8px; text-align:left; border-bottom:2px solid #e2e8f0; }
    td { padding:7px 8px; border-bottom:1px solid #f8fafc; }
    .nota-aprobada { color:#166534; font-weight:bold; }
    .nota-desaprobada { color:#991b1b; font-weight:bold; }

    .sin-hijos { text-align:center; padding:40px; color:#64748b; font-size:15px; }

    @media(max-width:600px) { .logo { display:none; } }
  </style>
</head>
<body>
<header>
  <div class="navbar">
    <a href="SGI.php" class="back-link"><button class="menu-icon" type="button">⟵</button></a>
    <a href="SGI.php"><img src="imagenes/newlogo1.webp" class="logo2" alt="SGI"></a>
    <div class="title-box"><h1>Panel Familiar</h1></div>
    <img src="imagenes/logotecn6.webp" alt="E.E.S.T N°6" class="logo">
    <div class="account" id="accountBtn">
      <div class="avatar" id="avatarInitials"></div>
      <div class="account-menu" id="accountMenu">
        <a href="usuario.php">Perfil</a>
        <a href="logout.php">Cerrar sesión</a>
      </div>
    </div>
  </div>
</header>

<main>
  <?php if (!$hijos): ?>
    <div class="sin-hijos">
      <div style="font-size:40px;margin-bottom:12px;">👨‍👩‍👧</div>
      <p>Todavía no tenés ningún alumno vinculado a tu cuenta.</p>
      <p style="font-size:13px;color:#94a3b8;">Contactá al preceptor para que te vincule con tu hijo/a.</p>
    </div>

  <?php else: ?>

    <?php if (count($hijos) > 1): ?>
    <!-- Selector de hijo -->
    <div class="selector-hijos">
      <?php foreach ($hijos as $h): ?>
        <a href="?alumno=<?php echo urlencode($h['dni']); ?>" style="text-decoration:none;">
          <button class="btn-hijo <?php echo $hijoDni==$h['dni']?'activo':''; ?>">
            👤 <?php echo htmlspecialchars($h['nombre']); ?>
            <small style="display:block;font-size:11px;opacity:.7;"><?php echo htmlspecialchars($h['parentesco']); ?></small>
          </button>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($alumnoInfo): ?>

    <!-- Info del alumno -->
    <div class="card">
      <h3>👤 <?php echo htmlspecialchars($alumnoInfo['nombre']); ?></h3>
      <p style="margin:0;font-size:13px;color:#64748b;">DNI: <?php echo htmlspecialchars($alumnoInfo['dni']); ?></p>
    </div>

    <!-- Asistencia del mes -->
    <div class="card">
      <h3>📅 Asistencia — <?php echo date('F Y'); ?></h3>
      <?php
        $presentes = count(array_filter($asistencia, fn($a) => $a['estado']==='presente'));
        $ausentes  = count(array_filter($asistencia, fn($a) => $a['estado']==='ausente'));
      ?>
      <div class="stats">
        <div class="stat st-p"><div class="sn"><?php echo $presentes; ?></div><div class="sl">Presentes</div></div>
        <div class="stat st-a"><div class="sn"><?php echo $ausentes; ?></div><div class="sl">Ausentes</div></div>
      </div>
      <?php if ($asistencia): ?>
      <table>
        <thead><tr><th>Fecha</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($asistencia as $a): ?>
            <tr>
              <td><?php echo date('d/m/Y', strtotime($a['fecha'])); ?></td>
              <td style="color:<?php echo $a['estado']==='presente'?'#166534':'#991b1b'; ?>;font-weight:bold;">
                <?php echo ucfirst($a['estado']); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:#94a3b8;font-size:13px;">Sin registros este mes.</p>
      <?php endif; ?>
    </div>

    <!-- Notas -->
    <div class="card">
      <h3>📝 Boletín académico</h3>
      <?php if ($notas): ?>
      <table>
        <thead><tr><th>Materia</th><th>1° Cuatr.</th><th>2° Cuatr.</th><th>Final</th><th>Observaciones</th></tr></thead>
        <tbody>
          <?php foreach ($notas as $n): ?>
            <?php
              $c1 = $n['c1_num'] !== null ? $n['c1_num'] : ($n['c1_val'] ?? '—');
              $c2 = $n['c2_num'] !== null ? $n['c2_num'] : ($n['c2_val'] ?? '—');
              $fin = $n['nota_final'];
            ?>
            <tr>
              <td><?php echo htmlspecialchars($n['materia']); ?></td>
              <td><?php echo htmlspecialchars((string)$c1); ?></td>
              <td><?php echo htmlspecialchars((string)$c2); ?></td>
              <td class="<?php echo ($fin !== null && $fin >= 6) ? 'nota-aprobada' : (($fin !== null) ? 'nota-desaprobada' : ''); ?>">
                <?php echo $fin !== null ? htmlspecialchars((string)$fin) : '—'; ?>
              </td>
              <td><?php echo htmlspecialchars($n['observaciones'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p style="color:#94a3b8;font-size:13px;">Sin notas cargadas aún.</p>
      <?php endif; ?>
    </div>

    <?php endif; ?>
  <?php endif; ?>
</main>

<script src="js/main.js"></script>
<script>window.APP_USER_NAME = "<?= htmlspecialchars($nombreUsuario); ?>";</script>
</body>
</html>
