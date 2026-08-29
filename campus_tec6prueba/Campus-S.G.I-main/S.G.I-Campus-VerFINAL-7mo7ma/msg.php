<?php
session_start();
require_once __DIR__ . '/auth.php';
requirePage('mensajeria');

$usuario  = $_SESSION['usuario'];
$rol      = strtolower($_SESSION['rol']);
$contacto = $_GET['con'] ?? '';

// Conexión
$conn = new mysqli("localhost", "root", "", "campus");
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Año escolar actual (por si lo querés usar luego)
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

/* ==========================================================
   1) LISTAS DE CONTACTOS SEGÚN ROL
   ========================================================== */

$cursosAlumnos = [];   // Para directivo / preceptor / profesor
$destinatarios = [];   // Para alumno / familia / otros

if (in_array($rol, ['directivo','admin','root','preceptor'])) {
  $dniUsuario = isset($_SESSION['dni']) ? (int)$_SESSION['dni'] : 0;

  // ¿Existe preceptor_curso?
  $tienePreceptorCurso = false;
  $res = $conn->query("SHOW TABLES LIKE 'preceptor_curso'");
  if ($res && $res->num_rows > 0) {
    $tienePreceptorCurso = true;
  }

  if (in_array($rol, ['directivo','admin','root'], true)) {
    // TODOS los cursos con alumnos activos (da igual el año_escolar_id)
    $sql = "SELECT 
              c.id AS curso_id,
              CONCAT(cy.year,' ', cd.division,
                     IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS curso_nombre,
              u.dni,
              u.nombre
            FROM curso c
            JOIN curso_year cy      ON cy.id = c.curso_year_id
            JOIN curso_division cd  ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo  ON mo.id = c.modalidad_id
            JOIN asignado_alumno aa ON aa.curso_id = c.id
                 AND aa.estado = 'activo'
            JOIN usuarios u         ON u.dni = aa.alumno_dni
                 AND u.rol = 'alumno'
            ORDER BY cy.id, cd.id, u.nombre";
    $stmt = $conn->prepare($sql);

  } elseif ($rol === 'preceptor' && $tienePreceptorCurso) {
    // Sólo cursos a cargo del preceptor (preceptor_curso) + alumnos activos
    $sql = "SELECT 
              c.id AS curso_id,
              CONCAT(cy.year,' ', cd.division,
                     IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS curso_nombre,
              u.dni,
              u.nombre
            FROM preceptor_curso pc
            JOIN curso c           ON c.id = pc.curso_id
            JOIN curso_year cy     ON cy.id = c.curso_year_id
            JOIN curso_division cd ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
            JOIN asignado_alumno aa ON aa.curso_id = c.id
                 AND aa.estado = 'activo'
            JOIN usuarios u         ON u.dni = aa.alumno_dni
                 AND u.rol = 'alumno'
            WHERE pc.preceptor_dni = ?
            ORDER BY cy.id, cd.id, u.nombre";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dniUsuario);

  } elseif ($rol === 'preceptor' && !$tienePreceptorCurso) {
    // PRECEPTOR sin tabla preceptor_curso -> mismo comportamiento que directivo
    $sql = "SELECT 
              c.id AS curso_id,
              CONCAT(cy.year,' ', cd.division,
                     IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS curso_nombre,
              u.dni,
              u.nombre
            FROM curso c
            JOIN curso_year cy      ON cy.id = c.curso_year_id
            JOIN curso_division cd  ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo  ON mo.id = c.modalidad_id
            JOIN asignado_alumno aa ON aa.curso_id = c.id
                 AND aa.estado = 'activo'
            JOIN usuarios u         ON u.dni = aa.alumno_dni
                 AND u.rol = 'alumno'
            ORDER BY cy.id, cd.id, u.nombre";
    $stmt = $conn->prepare($sql);

  } else { // PROFESOR
    // Cursos donde el profe tiene materias asignadas + alumnos activos
    $sql = "SELECT DISTINCT
              c.id AS curso_id,
              CONCAT(cy.year,' ', cd.division,
                     IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS curso_nombre,
              u.dni,
              u.nombre
            FROM docente_materia_curso dmc
            JOIN curso_materia cm   ON cm.id = dmc.curso_materia_id
            JOIN curso c            ON c.id = cm.curso_id
            JOIN curso_year cy      ON cy.id = c.curso_year_id
            JOIN curso_division cd  ON cd.id = c.curso_division_id
            LEFT JOIN modalidad mo  ON mo.id = c.modalidad_id
            JOIN asignado_alumno aa ON aa.curso_id = c.id
                 AND aa.estado = 'activo'
            JOIN usuarios u         ON u.dni = aa.alumno_dni
                 AND u.rol = 'alumno'
            WHERE dmc.maestro_dni = ?
            ORDER BY cy.id, cd.id, u.nombre";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dniUsuario);
  }

  if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
      $cid = (int)$fila['curso_id'];
      if (!isset($cursosAlumnos[$cid])) {
        $cursosAlumnos[$cid] = [
          'nombre'  => $fila['curso_nombre'],
          'alumnos' => []
        ];
      }
      $cursosAlumnos[$cid]['alumnos'][] = [
        'dni'    => $fila['dni'],
        'nombre' => $fila['nombre']
      ];
    }
    $stmt->close();
  }

} else {
  // Alumno / familia / otros → lista simple de contactos como antes
  if ($rol === 'alumno' || $rol === 'familia') {
    $query = "SELECT nombre FROM usuarios 
              WHERE rol IN ('preceptor', 'directivo') AND nombre != ?";
  } else {
    $query = "SELECT nombre FROM usuarios WHERE nombre != ?";
  }
  $stmt = $conn->prepare($query);
  $stmt->bind_param("s", $usuario);
  $stmt->execute();
  $result = $stmt->get_result();
  $destinatarios = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

/* ==========================================================
   2) ENVÍO DE MENSAJE
   ========================================================== */

// Directivos, admins, root y preceptores pueden enviar mensajes
$puedeEnviar = in_array($rol, ['directivo','admin','root','preceptor'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$puedeEnviar) {
    // Alumno / familia intentando enviar por POST directo → denegar silenciosamente
    header("Location: msg.php?con=" . urlencode($_POST['destinatario'] ?? ''));
    exit;
  }

  $destinatarioPost = $_POST['destinatario'] ?? '';
  $mensaje = $_POST['mensaje'] ?? '';

  if ($destinatarioPost && $mensaje) {
    $stmt = $conn->prepare("INSERT INTO mensajes (remitente, destinatario, mensaje) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $usuario, $destinatarioPost, $mensaje);
    $stmt->execute();
    $stmt->close();
    header("Location: msg.php?con=" . urlencode($destinatarioPost));
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Chat</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />
  <style>
    body {
      margin: 0; font-family: Arial, sans-serif;
      height: 100vh;
      display: flex;
      background: #ece5dd;
    }
    #sidebar {
      width: 300px;
      background: #fff;
      border-right: 1px solid #ccc;
      display: flex;
      flex-direction: column;
    }
    #buscador {
      padding: 10px;
      border: none;
      border-bottom: 1px solid #ddd;
      font-size: 16px;
      outline: none;
    }
    #listaContactos {
      flex: 1;
      overflow-y: auto;
    }
    .contacto {
      padding: 8px 15px;
      border-bottom: 1px solid #eee;
      cursor: pointer;
      user-select: none;
      transition: background 0.2s;
      font-size: 14px;
    }
    .contacto:hover {
      background: #f5f5f5;
    }
    .contacto.activo {
      background: #d9fdd3;
      font-weight: bold;
    }

    .curso-block {
      border-bottom: 1px solid #ddd;
    }
    .curso-header {
      padding: 10px 15px;
      background: #1e40af;
      color: #fff;
      cursor: pointer;
      font-size: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .curso-header span { pointer-events:none; }
    .curso-alumnos { display: none; background: #f9fafb; }
    .curso-block.abierto .curso-alumnos { display: block; }
    .curso-block.abierto .curso-header { background: #1d4ed8; }
    .curso-toggle { font-size: 18px; }

    #main {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: #e5ddd5;
    }
    #headerChat {
      padding: 15px;
      background: #1e40af;
      color: white;
      font-weight: bold;
      font-size: 18px;
      border-bottom: 1px solid #ddd;
    }
    #contenedorMensajes {
      flex: 1;
      padding: 15px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 8px;
      background-repeat: repeat;
      background-position: center;
    }
    .burbuja {
      max-width: 70%;
      padding: 10px 15px;
      border-radius: 20px;
      font-size: 14px;
      position: relative;
      line-height: 1.3;
      word-wrap: break-word;
      box-shadow: 0 1px 1px rgba(0,0,0,0.1);
      white-space: pre-wrap;
    }
    .izquierda {
      background: #fff;
      align-self: flex-start;
      border-top-left-radius: 0;
    }
    .derecha {
      background: #8ca4f5;
      align-self: flex-end;
      border-top-right-radius: 0;
    }
    .burbuja small {
      font-size: 10px;
      color: #333333;
      display: block;
      margin-top: 5px;
      text-align: right;
    }
    form#formEnviar {
      display: flex;
      padding: 10px 15px;
      background: #f0f0f0;
      border-top: 1px solid #ccc;
    }
    form#formEnviar textarea {
      flex: 1;
      resize: none;
      border-radius: 20px;
      border: 1px solid #ccc;
      padding: 10px 15px;
      font-size: 14px;
      outline: none;
      font-family: Arial, sans-serif;
      height: 45px;
    }
    form#formEnviar button {
      background: #1e40af;
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      margin-left: 10px;
      cursor: pointer;
      font-weight: bold;
      font-size: 18px;
      transition: background 0.3s;
    }
    form#formEnviar button:hover {
      background: #3e57ab;
    }
    #alertaNuevoMensaje {
      position: fixed;
      bottom: 80px;
      right: 20px;
      background: #128c7e;
      color: white;
      padding: 10px 15px;
      border-radius: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      display: none;
      cursor: pointer;
      user-select: none;
      z-index: 999;
    }
    .btn-volver {
      display:inline-block;
      margin:10px;
      padding:10px 12px;
      background:#1e40af;
      color:#fff;
      border-radius:5px;
      text-decoration:none;
      cursor:pointer;
      font-size:14px;
    }
    @media (max-width: 768px) {
      #sidebar {
        width: 100%;
        height: 170px;
        overflow-x: auto;
        border-right: none;
        border-bottom: 1px solid #ccc;
      }
      #main {
        height: calc(100vh - 170px);
      }
      #contenedorMensajes {
        padding: 10px;
        background-position: top left;
      }
      form#formEnviar textarea {
        height: 40px;
      }
    }
    #baraSoloLectura {
      padding: 14px 20px;
      background: #f1f5f9;
      border-top: 1px solid #cbd5e1;
      color: #64748b;
      font-size: 14px;
      text-align: center;
      font-style: italic;
    }
  </style>
</head>
<body>

<audio id="notiSound" src="noti.mp3" preload="auto"></audio>

<div id="sidebar">
  <a href="SGI.php" class="btn-volver">Volver</a>
  <input type="text" id="buscador" placeholder="Buscar contacto..." autocomplete="off" />
  <div id="listaContactos">

    <?php if (in_array($rol, ['directivo','preceptor','profesor'])): ?>
      <?php if (!$cursosAlumnos): ?>
        <div style="padding:10px; font-size:14px;">No hay cursos con alumnos asignados.</div>
      <?php else: ?>
        <?php foreach ($cursosAlumnos as $cursoId => $info): ?>
          <div class="curso-block">
            <div class="curso-header">
              <span><?php echo htmlspecialchars($info['nombre']); ?></span>
              <span class="curso-toggle">▾</span>
            </div>
            <div class="curso-alumnos">
              <?php foreach ($info['alumnos'] as $al): 
                $nombreAl = $al['nombre'];
                $texto = $nombreAl . " (" . $al['dni'] . ")";
                $activo = ($nombreAl === $contacto) ? 'activo' : '';
              ?>
                <div class="contacto <?php echo $activo; ?>"
                     data-nombre="<?php echo htmlspecialchars($nombreAl); ?>">
                  <?php echo htmlspecialchars($texto); ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php else: ?>
      <?php foreach ($destinatarios as $d):
        $activo = ($d['nombre'] === $contacto) ? 'activo' : '';
      ?>
        <div class="contacto <?php echo $activo ?>" data-nombre="<?php echo htmlspecialchars($d['nombre']); ?>">
          <?php echo htmlspecialchars($d['nombre']); ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<div id="main">
  <div id="headerChat"><?php echo $contacto ? "Chat con " . htmlspecialchars($contacto) : "Seleccioná un contacto"; ?></div>
  <div id="contenedorMensajes"></div>

  <?php if ($contacto): ?>
    <?php if ($puedeEnviar): ?>
    <form id="formEnviar" method="POST">
      <input type="hidden" name="destinatario" value="<?php echo htmlspecialchars($contacto); ?>">
      <textarea name="mensaje" rows="1" placeholder="Escribí un mensaje..." required></textarea>
      <button type="submit">&#9658;</button>
    </form>
    <?php elseif ($rol === 'alumno'): ?>  
    <div id="baraSoloLectura">
      🔒 Los alumnos solo pueden leer mensajes. No tenés permiso para enviar.
    </div>
    <?php elseif ($rol === 'profesor'): ?>  
    <div id="baraSoloLectura">
      🔒 Los profesores solo pueden leer mensajes. No tenés permiso para enviar.
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div id="alertaNuevoMensaje" title="Nuevo mensaje de otro contacto">Nuevo mensaje de otro chat</div>

<script>
const notiSound = document.getElementById('notiSound');
const contenedorMensajes = document.getElementById('contenedorMensajes');
const alertaNuevoMensaje = document.getElementById('alertaNuevoMensaje');
const chatActual = "<?php echo addslashes($contacto); ?>";

let ultimoHTML = '';
let ultimoIdNotificado = null;

function cargarMensajes() {
  if (!chatActual) return;
  fetch("cargar_msg.php?con=" + encodeURIComponent(chatActual))
    .then(res => res.json())
    .then(data => {
      const html = data.html;
      if (html !== ultimoHTML) {
        contenedorMensajes.innerHTML = html;
        contenedorMensajes.scrollTop = contenedorMensajes.scrollHeight;
        if (ultimoHTML !== '') {
          notiSound.play();
        }
        ultimoHTML = html;
      }
    })
    .catch(err => {
      console.error("Error al cargar mensajes:", err);
    });
}

function verificarMensajesGlobales() {
  fetch("notificaciones_globales.php?chat=" + encodeURIComponent(chatActual))
    .then(res => res.json())
    .then(data => {
      const nuevoRemitente = data.nuevo_remitente;
      const idMensaje = data.id;

      if (!nuevoRemitente || nuevoRemitente === chatActual) {
        alertaNuevoMensaje.style.display = "none";
        return;
      }

      if (idMensaje && idMensaje !== ultimoIdNotificado) {
        ultimoIdNotificado = idMensaje;
        alertaNuevoMensaje.style.display = "block";
        alertaNuevoMensaje.dataset.remitente = nuevoRemitente;
        notiSound.play();
      }
    })
    .catch(err => {
      console.error("Error al verificar mensajes globales:", err);
    });
}

alertaNuevoMensaje.onclick = function() {
  if (!alertaNuevoMensaje.style.display || alertaNuevoMensaje.style.display === "none") return;
  window.location.href = "msg.php?con=" + encodeURIComponent(alertaNuevoMensaje.dataset.remitente);
};

document.getElementById('buscador').addEventListener('input', function() {
  const texto = this.value.toLowerCase();
  document.querySelectorAll('#listaContactos .contacto').forEach(div => {
    div.style.display = div.textContent.toLowerCase().includes(texto) ? 'block' : 'none';
  });
});

document.addEventListener('click', function(e) {
  const c = e.target.closest('.contacto');
  if (c && c.dataset.nombre) {
    window.location.href = "msg.php?con=" + encodeURIComponent(c.dataset.nombre);
  }
});

document.querySelectorAll('.curso-header').forEach(h => {
  h.addEventListener('click', () => {
    const block = h.parentElement;
    block.classList.toggle('abierto');
  });
});

setInterval(cargarMensajes, 2000);
setInterval(verificarMensajesGlobales, 5000);
cargarMensajes();
</script>

</body>
</html>