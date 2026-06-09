<?php
session_set_cookie_params([
  'path' => '/',
  'domain' => '.sistemagi.ct.ws',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

$rol = $_SESSION['rol']; 
if (!isset($_SESSION['usuario'])) {
  header("Location: index.html");
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Página Principal</title>
  <link rel="stylesheet" href="style-main.css" />
  <link rel="icon" href="imagenes/logo_sgi.png" type="image/png">
</head>
<body>

  <header class="header">
    <h1>Bienvenido/a, <span style="color:#facc15;"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>!</h1>
    <a href="logout.php" class="btn-logout" title="Cerrar sesión">Cerrar sesión</a>
  </header>

  <main class="login-container" style="max-width: 600px;">
    <section class="role-access">
      <?php
      switch ($rol) {
        case 'directivo':
          $mensaje = "Acceso a reportes institucionales, configuración y estadísticas.";
          break;
        case 'preceptor':
          $mensaje = "Acceso a control de asistencia, conducta y seguimiento de cursos.";
          break;
        case 'familia':
          $mensaje = "Acceso a boletines y seguimiento académico de sus hijos.";
          break;
        case 'alumno':
          $mensaje = "Acceso a materias, clases virtuales y tareas.";
          break;
        default:
          $mensaje = "Rol no identificado.";
      }
      ?>
      <div class="card">
        <p><?php echo $mensaje; ?></p>
      </div>
    </section>
  </main>

  <a href="msg.php" title="Abrir chat" class="chat-btn">💬</a>

 <button id="modoToggle" style="
  position: fixed;
  bottom: 20px;
  left: 20px;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background-color: white;
  border: 1px solid gray;
  font-size: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
">🌙</button>

<script>
  const toggle = document.getElementById('modoToggle');
  toggle.addEventListener('click', () => {
    document.body.classList.toggle('dark');

    // Cambia el ícono
    toggle.textContent = document.body.classList.contains('dark') ? '☀️' : '🌙';
  });
</script>

  <style>
    /* Botón logout */
    .btn-logout {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #c00;
      color: white;
      padding: 10px 15px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: bold;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      transition: background 0.3s ease;
      z-index: 100;
    }
    .btn-logout:hover {
      background: #a00;
    }

    /* Tarjeta de mensaje */
    .card {
      background: #eff6ff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 8px 15px rgba(59, 130, 246, 0.2);
      font-size: 1.1rem;
      color: #1e40af;
      text-align: center;
      margin-top: 20px;
    }

    /* Botón chat flotante */
    .chat-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #004080;
      color: white;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.2);
      text-decoration: none;
      transition: background 0.3s ease;
      z-index: 100;
    }
    .chat-btn:hover {
      background: #0059b3;
    }

    /* Header estilo */
    .header {
      background-color: #3b82f6;
      color: white;
      padding: 15px 20px;
      text-align: center;
      position: relative;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    /* Responsive */
    @media (max-width: 480px) {
      .card {
        font-size: 1rem;
        padding: 15px;
      }
      .btn-logout {
        padding: 8px 12px;
        font-size: 14px;
      }
      .chat-btn {
        width: 50px;
        height: 50px;
        font-size: 24px;
      }
    }
  </style>

</body>
</html>
