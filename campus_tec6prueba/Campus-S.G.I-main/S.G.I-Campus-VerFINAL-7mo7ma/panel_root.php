<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireLogin();

if (!esRoot()) {
    header("Location: SGI.php");
    exit;
}

$conn = new mysqli("localhost","root","","campus");
$conn->set_charset("utf8mb4");
$mensaje = '';

// Crear directivo o admin
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='crear_usuario') {
    requireCsrf();
    $nuevo_dni    = trim($_POST['nuevo_dni']    ?? '');
    $nuevo_nombre = trim($_POST['nuevo_nombre'] ?? '');
    $nuevo_pass   = $_POST['nuevo_password']    ?? '';
    $nuevo_rol    = $_POST['nuevo_rol']         ?? '';

    if (!in_array($nuevo_rol, ['directivo','admin'], true)) {
        $mensaje = '❌ Rol inválido. Solo podés crear directivo o admin.';
    } elseif ($nuevo_dni==='' || $nuevo_nombre==='' || $nuevo_pass==='') {
        $mensaje = '❌ Completá todos los campos.';
    } else {
        $hash = password_hash($nuevo_pass, PASSWORD_DEFAULT);
        $check = $conn->prepare("SELECT dni FROM usuarios WHERE dni=?");
        $check->bind_param("s",$nuevo_dni); $check->execute();
        $check->store_result();
        if ($check->num_rows>0) {
            $mensaje = '❌ Ya existe un usuario con ese DNI.';
        } else {
            $ins = $conn->prepare("INSERT INTO usuarios (dni,nombre,password,rol,password_changed) VALUES (?,?,?,?,1)");
            $ins->bind_param("ssss",$nuevo_dni,$nuevo_nombre,$hash,$nuevo_rol);
            if ($ins->execute()) $mensaje = '✅ Usuario "'.$nuevo_nombre.'" creado como '.$nuevo_rol.' correctamente.';
            else $mensaje = '❌ Error al crear el usuario.';
            $ins->close();
        }
        $check->close();
    }
}

// Listar directivos y admins
$listaAltos = [];
$res = $conn->query("SELECT dni, nombre, rol FROM usuarios WHERE rol IN ('directivo','admin') ORDER BY rol, nombre");
while ($f = $res->fetch_assoc()) $listaAltos[] = $f;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Root — S.G.I</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family:Arial,sans-serif; margin:0; background:#0f172a; color:#fff; min-height:100vh; }
    header { background:#020617; padding:16px 24px; display:flex; align-items:center; gap:16px; border-bottom:1px solid #1e293b; }
    header h1 { margin:0; font-size:20px; }
    .badge-root { background:#dc2626; color:#fff; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:bold; }
    main { max-width:700px; margin:30px auto; padding:0 20px; }
    .card { background:#1e293b; border-radius:14px; padding:24px; margin-bottom:20px; border:1px solid #334155; }
    .card h2 { margin:0 0 16px; font-size:17px; color:#e2e8f0; }
    label { display:block; font-size:13px; color:#94a3b8; margin-bottom:4px; margin-top:10px; }
    input, select { width:100%; box-sizing:border-box; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#e2e8f0; font-size:14px; }
    .btn { margin-top:14px; width:100%; padding:10px; background:#dc2626; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:bold; cursor:pointer; }
    .btn:hover { background:#b91c1c; }
    .mensaje { padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:14px;
               background:<?php echo str_contains($mensaje,'✅')?'#14532d':'#7f1d1d'; ?>;
               display:<?php echo $mensaje?'block':'none'; ?>; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th { background:#0f172a; color:#94a3b8; padding:8px; text-align:left; border-bottom:1px solid #334155; }
    td { padding:8px; border-bottom:1px solid #1e293b; color:#e2e8f0; }
    .badge-dir { background:#1d4ed8; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; }
    .badge-adm { background:#7c3aed; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; }
    .logout { color:#94a3b8; text-decoration:none; font-size:13px; margin-left:auto; }
    .logout:hover { color:#e2e8f0; }
  </style>
</head>
<body>
<header>
  <span style="font-size:24px;">🔐</span>
  <h1>Panel Root — S.G.I</h1>
  <span class="badge-root">ROOT</span>
  <a href="logout.php" class="logout">Cerrar sesión →</a>
</header>
<main>
  <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>

  <div class="card">
    <h2>➕ Crear directivo o admin</h2>
    <form method="POST">
      <input type="hidden" name="accion" value="crear_usuario">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
      <label>DNI o identificador</label>
      <input type="text" name="nuevo_dni" placeholder="Ej: 30123456" required>
      <label>Nombre completo</label>
      <input type="text" name="nuevo_nombre" placeholder="Ej: María González" required>
      <label>Contraseña inicial</label>
      <input type="password" name="nuevo_password" placeholder="Mínimo 6 caracteres" required>
      <label>Rol a asignar</label>
      <select name="nuevo_rol" required>
        <option value="">-- Elegí el rol --</option>
        <option value="directivo">Directivo (director institucional)</option>
        <option value="admin">Admin (soporte técnico)</option>
      </select>
      <button type="submit" class="btn">Crear usuario</button>
    </form>
  </div>

  <div class="card">
    <h2>👥 Directivos y Admins existentes</h2>
    <?php if (!$listaAltos): ?>
      <p style="color:#64748b;">Todavía no hay directivos ni admins creados.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>DNI</th><th>Nombre</th><th>Rol</th></tr></thead>
      <tbody>
        <?php foreach ($listaAltos as $u): ?>
          <tr>
            <td><?php echo htmlspecialchars($u['dni']); ?></td>
            <td><?php echo htmlspecialchars($u['nombre']); ?></td>
            <td><span class="<?php echo $u['rol']==='directivo'?'badge-dir':'badge-adm'; ?>">
              <?php echo $u['rol']; ?>
            </span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
