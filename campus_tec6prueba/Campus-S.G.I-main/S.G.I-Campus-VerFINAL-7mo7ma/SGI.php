
<?php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

session_set_cookie_params([
  'path'     => '/',
  // 'domain' => '.sistemagi.ct.ws',
  'secure'   => $isHttps,          
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

if (empty($_SESSION['dni']) || empty($_SESSION['rol'])) {
  header("Location: index.html");
  exit;
}

// Variables disponibles para la UI
$usuario = $_SESSION['usuario'] ?? '';
$rol     = strtolower(trim($_SESSION['rol']));
$verListado = in_array($rol, ['profesor','preceptor','directivo'], true);
$verPanel = in_array($rol, ['preceptor','directivo'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>S.G.I - E.E.S.T. N°6 Berazategui</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />
  <!-- STYLES -->
   <link rel="stylesheet" href="css/menuHamburguesa.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <link rel="stylesheet" href="css/ChatFlotante.css">

  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #d8d7d7;
    }
    .sgi-title {
      margin-left: auto;
      font-weight: bold;
      padding: 8px 15px;
      font-size: 28px;
    }
    h1, h2, h3 {
      text-align: center;
      background: #0f172a;
      color: white;
      margin: 10px;
      padding: 15px;
    }
    h1#tit {
      font-size: 2em;
      border: 5px solid white;
    }
    aside {
      background: #494949;
      padding: 15px;
      margin-top: 20px;
      color: white;
         }
    img { 
      width: 100%;
      height: auto; 
    }
    .logo {
      position: absolute;
      top: 930px;
      right: 30px;
      width: 300px;
         }
  iframe{
    width:800px;
    height:500px ;
  }  

.menu{
  border-radius: 20px;
}
/* +
   MENU HAMBURGUESA PREMIUM
+ */

.overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1002;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(5px);
    justify-content: flex-start;
}

.overlay.show {
    display: flex;
}

/* 
   PANEL LATERAL
 */

.menu-panel {
    width: 320px;
    min-height: 100vh;
    position: relative;
    background: linear-gradient(180deg,#0f3d5e 0%,#164b73 45%,#1f5d8f 100%);
    padding: 30px 22px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    scrollbar-width: none;
    border-right: 1px solid rgba(255,255,255,.08);
    box-shadow:12px 0 35px rgba(0,0,0,.30);
    animation: slideIn .35s ease;
}
.menu-panel::-webkit-scrollbar {
    display: none;
}
/* Barra superior decorativa */
.menu-panel::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg,#4fc3f7,#81d4fa,#4fc3f7 );
}

/* Formas decorativas */

.menu-panel::after {
    content: "";
    position: absolute;
    top: -120px;
    right: -120px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    pointer-events: none;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-100%);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* 
   CABECERA */

.menu-top {
    text-align: center;
    color: white;
    margin-bottom: 35px;
}

.menu-top img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    padding: 10px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 20px;
    margin-bottom: 18px;
    box-shadow: 0 8px 20px rgba(0,0,0,.20);
    transition: .35s ease;
}

.menu-top img:hover {
    transform: scale(1.05);
}

.menu-top h1 {
    margin: 0;
    font-size: 25px;
    font-weight: 700;
    letter-spacing: .8px;
}

.menu-top h2 {
    margin-top: 10px;
    color: #d8e2ec;
    font-size: 13px;
    line-height: 1.6;
}

/* 
   LINKS
 */

.menu-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex-grow: 1;
}

.menu-links a {
    position: relative;
    display: flex;
    align-items: center;
    padding: 15px 18px;
    color: white;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    border-radius: 14px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.05);
    backdrop-filter: blur(4px);
    transition:
        transform .25s ease,
        background .25s ease,
        border-color .25s ease,
        box-shadow .25s ease;
}
.menu-links a:hover {
    transform: translateX(6px);
    background: rgba(255,255,255,.10);
    border-color: rgba(255,255,255,.15);
    box-shadow:0 6px 15px rgba(0,0,0,.15);
}

/* Link activo */

.menu-links a.active {
    background: linear-gradient(135deg,#2196f3,#42a5f5
    );
    border-left: 4px solid white;
    box-shadow: 0 8px 18px rgba(33,150,243,.35);
}

/* 
   PIE DEL MENU */

.menu-bottom {
    margin-top: 25px;
    padding: 22px;
    text-align: center;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 18px;
    backdrop-filter: blur(8px);
}
.menu-bottom .avatar {
    width: 75px;
    height: 75px;
    margin: 0 auto 15px;
    border-radius: 50%;
    background: linear-gradient(135deg,#ffffff,#eaf4fb);
    color: #0f3d5e;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    font-weight: 700;
    border: 4px solid rgba(255,255,255,.15);
    box-shadow:0 8px 20px rgba(0,0,0,.20);
}

.menu-bottom p {
    margin: 0;
    color: #d8e2ec;
    font-size: 13px;
    line-height: 1.6;
}

/* 
   BOTON CERRAR
 */

.close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 50%;
    background: rgba(255,255,255,.10);
    color: white;
    cursor: pointer;
    font-size: 20px;
    transition: .3s ease;
}

.close-btn:hover {
    background: rgba(255,255,255,.20);
    transform:
        rotate(90deg)
        scale(1.1);
}
/* TEMPORAL: PARA HACER QUE LA PAGINA SEA MAS USABLE EN CELULAR, ESCONDER LA BARRA DE NAVEGACION */

@media (max-width: 1500px) {
  .logo {
    display: none;
  }
  #tit {
    font-size: 1.5rem;
    text-align: center;
  }

    iframe {
    width: 100% !important;
    height: 400px !important; /* lo que quieras */
  }
}
aside {
  background: #f4f6f8;
  color: #1e293b;
  padding: 25px;
  margin-top: 30px;
  width: 100%;
  box-sizing: border-box;
  border-radius: 10px;
  border: 1px solid #d6dce5;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

aside h3 {
  background: transparent;
  color: #0f172a;
  margin: 0 0 20px 0;
  padding: 0;
  font-size: 1.5rem;
  font-weight: 600;
  border-bottom: 2px solid #cbd5e1;
  padding-bottom: 10px;
  text-align: left;
}

aside ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

aside ul li {
  background: #ffffff;
  margin-bottom: 12px;
  padding: 14px 18px 14px 45px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  position: relative;
  font-size: 0.98rem;
  line-height: 1.5;
}

aside ul li::before {
  content: "•";
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  font-size: 1.3rem;
  font-weight: bold;
}
aside ul li:hover {
  background: #f8fafc;
}
.op-menu{
  border-radius: 15px;
}
.op-menu:hover{
  transition: 1s;
}
  </style>
</head>
<body>

 <div class="navbar">
  <button class="menu-icon" aria-label="Abrir menú" onclick="openMenu()">☰</button>

  <div class="menu">
    <?php if ($verListado): ?>
      <a class="op-menu" href="asistencia.php" onclick="closeMenu()">Asistencia</a>
      <a class="op-menu" href="lista.alumnos.php">Lista de alumnos</a>
    <?php endif; ?>
    <?php if ($verPanel): ?>
      <a class="op-menu" href="panel_control.php">Panel de Control</a>
    <?php endif; ?>
    
    
    <a class="op-menu" href="infoacademica.php">Información académica</a>
    <a class="op-menu" href="materias.php">Materias</a>
    <a class="op-menu" href="foro.php" onclick="closeMenu()">Foro</a>
    <a class="op-menu" href="msg.php">Mensajería</a>
    <a class="op-menu" href="contactos.php">Contactos</a>
    
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


  <!-- ----------- MENÚ HAMBURGUESA (IGUAL AL DE ASISTENCIA) ----------- -->
  <div class="overlay" id="overlay" onclick="closeMenu(event)">
    <nav class="menu-panel" onclick="event.stopPropagation()">
      <button class="close-btn" aria-label="Cerrar menú" onclick="closeMenu()">×</button>

      <div class="menu-top">
        <a href="SGI.php"> <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
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
  		  <a class="cont-menu" href="infoacademica.php" onclick="closeMenu()">Información académica</a>
 	 	    <a class="cont-menu" href="materias.php" onclick="closeMenu()">Materias</a>
        <a class="cont-menu" href="foro.php" onclick="closeMenu()">Foro</a>
  		  <a class="cont-menu" href="contactos.php" onclick="closeMenu()">Contactos</a>
	</div>


      <div class="menu-bottom">
        <div class="avatar" id="avatarMenu">
        </div>
      </div>
    </nav>
  </div>

  <img src="imagenes/logotecn6.webp" alt="E.E.S.T N°6" class="logo" />
  <h1 id="tit">Escuela de Educación Técnica N°6</h1>
  <img src="imagenes/campus.webp" alt="Bienvenido" width="1000px" height="100px" />

  <section>
    <h2>Ubicación:</h2>
    <p style="text-align: center;">| Calle 150 | Berazategui | (CP1884) Buenos Aires | Argentina |</p>
    <center>
      <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3276.627561039006!2d-58.18048242499341!3d-34.790150167366725!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95a3279d25becd95%3A0xbc2358dd7c88981b!2sEscuela%20de%20Educaci%C3%B3n%20Secundaria%20T%C3%A9cnica%20(E.E.S.T.)%20N%C2%BA6!5e0!3m2!1ses-419!2sar!4v1759248000706!5m2!1ses-419!2sar" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </center>
  </section>

  <main>
    <aside>
      <h3>Consejos Útiles</h3>
      <ul>
        <li>No compartas tu cuenta.</li>
        <li>Horario de atencion 7:30hs y 22:00hs.</li>
        <li>Revisá que la información personal esté correctamente.</li>
        <li>Verificá las notificaciones del campus virtual constatemente.</li>
        <li>Toda la informacion que se encontra en la pagina debe presentarse en  formato papel</li>
      </ul>
    </aside>
  </main>
  <!-- 🟦 BOTÓN CHAT -->
  <a href="msg.php"><button id="boton-flotante" >💬</button></a>
  <script>
   window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? $user['nombre'] ?? 'Usuario');?>"; // Iniciales
  </script>
  <script src="js/main.js"></script>
</body>
</html>
