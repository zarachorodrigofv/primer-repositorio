<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();

$user_id = $_SESSION['dni'];
$pdo = db();

// ── Guardar teléfono ────────────────────────────────────
$mensajeTelefono = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_telefono'])) {
    $tel = preg_replace('/[^0-9+\- ]/', '', $_POST['telefono'] ?? '');
    $tel = substr(trim($tel), 0, 30);
    $upd = $pdo->prepare("UPDATE usuarios SET telefono = ? WHERE dni = ?");
    $upd->execute([$tel !== '' ? $tel : null, $user_id]);
    $mensajeTelefono = 'ok';
}

// Incluir teléfono en la query
$stmt = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(nombre), ''), CONCAT('DNI ', dni)) AS nombre, dni, rol, telefono FROM usuarios WHERE dni = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Usuario-S.G.I</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />
  <link rel="stylesheet" href="css/menuHamburguesa.css">
  <link rel="stylesheet" href="css/usuario.css">
  <style>
    body { 
        background: #f8f9fa; 
      }
    h1, h2, h3 {
      text-align: center;
      background: #0f172a;
      color: white;
      margin: 30px;
      padding: 10px;
    }
    h1#tit {
      font-size: 2em;
      border: 5px solid white;
    }
  </style>
</head>
<body>

<!-- Menú hamburguesa -->
<header>
  <button class="menu-icon" aria-label="Abrir menú" onclick="openMenu()">☰</button>
  <div class="overlay" id="overlay" onclick="closeMenu(event)">
    <nav class="menu-panel" onclick="event.stopPropagation()">
      <button class="close-btn" aria-label="Cerrar menú" onclick="closeMenu()">×</button>
  

      <!-- Logo y nombre -->
      <div class="menu-top">
        <a href="SGI.php"> <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
        <h1>S.G.I</h1>
        <h2>Sistema de Gestión Institucional</h2>
      

      <!-- Links -->
      <div class="menu-links">
        <a href="SGI.php" onclick="closeMenu()">Inicio</a>
        <a href="lista.alumnos.php" onclick="closeMenu()">Lista de alumnos</a>
        <a href="infoacademica.php" onclick="closeMenu()">Información académica</a>
        <a href="materias.php" onclick="closeMenu()">Materias</a>
        <a href="asistencia.php" onclick="closeMenu()">Asistencia</a>
        <a href="contactos.php" onclick="closeMenu()">Contactos</a>
      </div>

      <!-- Avatar inferior -->
      <div class="menu-bottom">
        <div class="avatar" id="avatarMenu"></div>
      </div>
    </nav>
  </div>
</header>

<!-- Perfil -->
<div class="container mt-5 d-flex justify-content-center">
  <div class="d-flex align-items-center justify-content-between border rounded shadow p-3 w-100">

    <!-- Usuario -->
    <div class="d-flex align-items-center">
      <div id="profileContainer" class="me-3"></div>
      <div>
        <div class="text-muted">  <?php echo $user['rol']; ?></div>
        <h5 class="mb-0"> <?php echo $user['nombre']; ?> </h5>
        <!-- <button class="btn btn-sm btn-outline-primary mt-2" onclick="document.getElementById('userImageInput').click()">Cambiar foto</button>
 			<input type="file" id="userImageInput" accept="image/*" hidden>
		Para hacer funcionar el boton de subir imagen habriamos que crear en la db una tabla de imagen de perfil en usuarios. 
		Podria hacerlo pero al mismo tiempo deberiamos hacer una forma del director de bloquear la funcion por si un alumno pone una imagen que no 			corresponde. creo que por ahora es mejor no dar esa opcion.
-->
        
      </div>
    </div>

    <!-- Imagen fija derecha -->
    <div>
      <img id="extraImage" src="imagenes/logotecn6.webp" class="extra-image" alt="Imagen fija">
    </div>

  </div>
</div>

<!-- Pestañas -->
<div class="container mt-5">
  <ul class="nav nav-tabs justify-content-center" id="myTab" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#datos">Datos Personales</button></li>
    <!--<li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#calificaciones">Calificaciones</button></li>-->
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#boletines">Boletines</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#asistencia">Asistencia</button></li>
  </ul>
  <div class="tab-content mt-4">

    <!-- Datos personales -->
    <div id="datos" class="tab-pane active show">
      <?php if ($mensajeTelefono === 'ok'): ?>
        <div class="alert alert-success text-center">✅ Teléfono guardado correctamente.</div>
      <?php endif; ?>
      <ul class="list-unstyled list-border">
        <!--<li><strong>Nombre:</strong> <?php echo $user['nombre']; ?></li>-->
        <li><strong>DNI:</strong> <?php echo htmlspecialchars($user['dni']); ?></li>
        <li><strong>Rol:</strong> <?php echo htmlspecialchars(ucfirst($user['rol'])); ?></li>
        <li><strong>Colegio:</strong> E.E.S.T N°6 Berazategui</li>
        <li><strong>Email:</strong></li>
        <!-- Módulo 11: Teléfono editable -->
        <li>
          <strong>Teléfono:</strong>
          <?php if (!empty($user['telefono'])): ?>
            <span id="telMostrado"><?php echo htmlspecialchars($user['telefono']); ?></span>
          <?php else: ?>
            <span id="telMostrado" class="text-muted">Sin registrar</span>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btnEditarTel"
                  onclick="document.getElementById('formTelefono').style.display='block';this.style.display='none';">
            ✏️ Editar
          </button>
          <form id="formTelefono" method="POST" style="display:none;margin-top:8px;">
            <div class="input-group" style="max-width:320px;">
              <input type="tel" name="telefono" class="form-control form-control-sm"
                     placeholder="Ej: 11 2345 6789"
                     value="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>"
                     maxlength="30">
              <button type="submit" name="guardar_telefono" class="btn btn-sm btn-primary">Guardar</button>
              <button type="button" class="btn btn-sm btn-secondary"
                      onclick="document.getElementById('formTelefono').style.display='none';
                               document.getElementById('btnEditarTel').style.display='';">
                Cancelar
              </button>
            </div>
          </form>
        </li>
      </ul>
    </div>

    <!-- Calificaciones -->
    <!--<div id="calificaciones" class="tab-pane">
      <p>Aquí se mostrarán las calificaciones...</p>
    </div> -->

    <!-- Boletines -->
    <div id="boletines" class="tab-pane">
      <section>
          <p>Aquí se mostrarán pdfs de los boletines...</p>
      </section>
    </div>

    <!-- Asistencia -->
    <div id="asistencia" class="tab-pane">
      <div class="row text-center">
        <div class="col-md-4">
          <div class="border rounded shadow p-3 bg-white">
            <h6>Faltas Justificadas</h6> <!-- conectar con la bd -->
            <p>0</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded shadow p-3 bg-white">
            <h6>Inasistencias</h6> <!-- conectar con la bd -->
            <p>0</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded shadow p-3 bg-white">
            <h6>Presentes</h6> <!-- conectar con la bd -->
            <p>0</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // --- Menú hamburguesa ---
  window.openMenu = () => {
    const overlay = document.getElementById('overlay');
    if (overlay) overlay.classList.add('show');
  };
  
  window.closeMenu = (event) => {
    if (!event || event.target.id === 'overlay') {
      const overlay = document.getElementById('overlay');
      if (overlay) overlay.classList.remove('show');
    }
  };

  // --- Usuario (iniciales/foto) ---
  const userName = "<?php echo $user['nombre']; ?>";
  const container = document.getElementById("profileContainer");

  function getInitials(name){ 
    return name.split(" ").map(w => w[0]).join("").toUpperCase(); 
  }

  function showInitials(){ 
    if (container) container.innerHTML = `<div class="avatar-placeholder">${getInitials(userName)}</div>`; 
  }

  // Inicializar avatar
  showInitials();

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>