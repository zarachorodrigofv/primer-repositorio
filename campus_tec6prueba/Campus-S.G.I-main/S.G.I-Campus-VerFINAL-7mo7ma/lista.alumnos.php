<?php
// ===== Acceso estricto  =====
require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();

// Detectar rol desde sesión/helper y normalizar
$rolSesion  = strtolower(trim($_SESSION['rol'] ?? ''));
$rolHelper  = strtolower(trim(currentRole() ?? ''));
$rol        = $rolSesion ?: $rolHelper;

// Normalizar alias: si en la BD usás "docente", lo tratamos como "profesor"
if ($rol === 'docente') {
    $rol = 'profesor';
}

// Permisos
$puedeVer     = in_array($rol, ['profesor','preceptor','directivo'], true);
$puedeAgregar = in_array($rol, ['preceptor','directivo'], true);

if (!$puedeVer) {
  header('Location: SGI.php');
  exit;
}

$pdo = db();

// ========= Año lectivo activo =========
$yearRow = $pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);

// ========= Cursos visibles según rol =========
if ($rol === 'directivo') {
    // Director ve todos los cursos
    $stmt = $pdo->query("
      SELECT c.id,
             CONCAT(cy.year, ' ', cd.division,
                    IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
      FROM curso c
      JOIN curso_year cy ON cy.id = c.curso_year_id
      JOIN curso_division cd ON cd.id = c.curso_division_id
      LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
      ORDER BY cy.year, cd.division, mo.nombre
    ");
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'preceptor') {
    // Preceptor: solo cursos a cargo este año
    $dni = (int)($_SESSION['dni'] ?? 0);
    $stmt = $pdo->prepare("
      SELECT c.id,
             CONCAT(cy.year, ' ', cd.division,
                    IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
      FROM preceptor_curso pc
      JOIN curso c           ON c.id = pc.curso_id
      JOIN curso_year cy     ON cy.id = c.curso_year_id
      JOIN curso_division cd ON cd.id = c.curso_division_id
      LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
      WHERE pc.preceptor_dni = :dni
        AND pc.year_escolar_id = :year
      ORDER BY cy.year, cd.division, mo.nombre
    ");
    $stmt->execute([':dni' => $dni, ':year' => $year_id]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'profesor') {
    // Profesor: cursos donde dicta materias este año
    $dni = (int)($_SESSION['dni'] ?? 0);
    $stmt = $pdo->prepare("
      SELECT DISTINCT c.id,
             CONCAT(cy.year, ' ', cd.division,
                    IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
      FROM docente_materia_curso dmc
      JOIN curso_materia cm   ON cm.id = dmc.curso_materia_id
      JOIN curso c            ON c.id = cm.curso_id
      JOIN curso_year cy      ON cy.id = c.curso_year_id
      JOIN curso_division cd  ON cd.id = c.curso_division_id
      LEFT JOIN modalidad mo  ON mo.id = c.modalidad_id
      WHERE dmc.maestro_dni = :dni
        AND cm.year_escolar_id = :year
      ORDER BY cy.year, cd.division, mo.nombre
    ");
    $stmt->execute([':dni' => $dni, ':year' => $year_id]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Cualquier otro rol no debería estar acá
    $cursos = [];
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SGI - Información Académica</title>
<link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- STYLES -->
<link rel="stylesheet" href="css/menuHamburguesa.css">
<link rel="stylesheet" href="css/avatar.css">
<link rel="stylesheet" href="css/navbar.css">
<link rel="stylesheet" href="css/ChatFlotante.css">
<style>
/* ================================
   ESTILOS GENERALES
================================ */
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin:0;
    padding:0;
    background:#d8d7d7;
    color:#333;
    overflow-x:hidden;
}

/* ================================
   HEADER Y NAVEGACIÓN
================================ */
header { background:#0f172a; color:#fff; }
.logo { width:100px; height:auto; }
.logo2 { width:100px; height:auto; }
.title-box { text-align:center; flex-grow:1; }
.title-box h1 { margin:0; font-size:30px; }
.title-box h2 { margin:0; font-size:18px; font-weight:normal; }

 h1, h2, h3 {
      text-align: center;
      background: #0f172a;
      color: white;
      margin: 5px;
      padding: 15px;
    }
    h1#tit {
      font-size: 2em;
      border: 5px solid white;
    }
h6{
    text-align: center;
      background: #80848f;
      color: rgb(255, 0, 0);
      margin: 10px;
      padding: 15px;
      font-size: 18px;
}
/* BUSCADOR */
.busqueda-contenedor {
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
    margin:10px 0 10px;
}
#buscador {
    padding:6px 12px;
    font-size:14px;
    border:1px solid #313131;
    border-radius:4px;
}

/* BOTÓN AGREGAR ALUMNO */
#btnAgregar {
    display:block;
    margin:15px auto;
    padding:10px 20px;
    font-size:14px;
    background:#3b82f6;
    color:#fff;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
#btnAgregar:hover { background:#2563eb; }

/* FORMULARIO AGREGAR ALUMNO */
#formulario-alumno {
    margin:15px auto;
    background:#fff;
    padding:15px;
    border-radius:6px;
    border:1px solid #ddd;
    display:none;
    flex-wrap:wrap;
    gap:10px;
    max-width:1000px;
}
#formulario-alumno input {
    padding:8px;
    font-size:14px;
    width:calc(25% - 12px);
    border:1px solid #ccc;
    border-radius:4px;
}
#formulario-alumno button {
    padding:8px 15px;
    font-size:14px;
    cursor:pointer;
    background:#10b981;
    color:#fff;
    border:none;
    border-radius:5px;
}
#formulario-alumno button:hover { background:#059669; }

/* TABLA */
main { padding:20px; max-width:1200px; margin:auto; }
table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0px 2px 5px rgba(0,0,0,0.05); }
th, td { border:1px solid #e2e8f0; padding:8px; font-size:17px; text-align:left; }
th { background:#1e293b; color:#fff; }
tr.extra td { background:#f9fafb; padding:0; }
.extra-contenido { display:flex; flex-wrap:wrap; gap:10px; font-size:17px; overflow:hidden; max-height:0; transition:max-height 0.3s ease; }
.extra-contenido p { flex:1 1 30%; margin:5px; }
td.acciones { text-align:center; }
td.acciones button {
    background:#f4f4f4;
    border:none;
    cursor:pointer;
    font-size:14px;
    padding:2px 5px;
    margin-left:3px;
    border-radius:4px;
    box-shadow:0 1px 2px rgba(0,0,0,0.1);
}
td.acciones button i.fa-edit { color:#3b82f6; }
td.acciones button i.fa-trash { color:#ef4444; }
td.acciones button i.fa-chevron-down, td.acciones button i.fa-chevron-up { color:#555; }
td.acciones button:hover i.fa-edit { color:#2563eb; }
td.acciones button:hover i.fa-trash { color:#b91c1c; }

/* RESALTADO BÚSQUEDA */
tr.resaltado td { background:#3b82f6; color:#fff; }

/* FOOTER */
footer { text-align:center; padding:10px; margin-top:20px; font-weight:bold; font-size:16px; }
 /* TELEFONO */
@media (max-width: 768px) {
    .navbar {
        padding: 20px; 
    }
    
    .logo{
        display: none; 
    }
    
    .title-box h1 {
        font-size: 18px;
    }
    
    .title-box h2 {
        font-size: 14px;
    }
    
    main {
        padding: 10px;
    }
    
    .table-container {
        border-radius: 6px; }
    th, td {
        padding: 8px 6px;
        font-size: 12px;
    }
    td.acciones button {
        padding: 6px;
        margin: 1px;
        min-width: 32px;
        min-height: 32px;
        font-size: 12px;
    }
}
    
    .busqueda-contenedor {
        margin: 10px;
        gap: 8px;
    }
    
    #formulario-alumno {
        margin: 10px;
        padding: 12px;
    }
/* Telefono */ 
@media (max-width: 768px) {
    }

</style>
</head>
<body>

<header>

  <div class="navbar">
    <button class="menu-icon" aria-label="Abrir menú" onclick="openMenu()">☰</button>
    <a href="SGI.php"> <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
    <div class="title-box">
      <h1>Información Académica</h1>
      <h2>Gestión 2025</h2>
    </div>
    <img src="imagenes/logotecn6.webp" alt="E.E.S.T N°6" class="logo">
    <div class="account" id="accountBtn">
      <div class="avatar" id="avatarInitials"></div>
      <div class="account-menu" id="accountMenu">
        <a href="usuario.php">Perfil</a>
        <a href="changepassword.html">Cambiar Contraseña</a>
        <a href="index.html">Cerrar sesión</a>
      </div>
    </div>
  </div>

  <!-- Menú hamburguesa -->
  <div class="overlay" id="overlay" onclick="closeMenu(event)">
    <nav class="menu-panel" onclick="event.stopPropagation()">
      <button class="close-btn" aria-label="Cerrar menú" onclick="closeMenu()">×</button>

      <div class="menu-top">
        <a href="SGI.php"> <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2"> </a>
        <h1>S.G.I</h1>
        <h2>Sistema De Gestion Institucional</h2>
      </div>

      <div class="menu-links">
        <a href="SGI.php" onclick="closeMenu()">Inicio</a>
        <a href="lista.alumnos.php" onclick="closeMenu()">Lista de alumnos</a>
        <a href="infoacademica.php" onclick="closeMenu()">Información académica</a>
        <a href="materias.php" onclick="closeMenu()">Materias</a>
        <a href="asistencia.php" onclick="closeMenu()">Asistencia</a>
        <a href="foro.php" onclick="closeMenu()">Foro</a>
        <a href="contactos.php" onclick="closeMenu()">Contactos</a>
      </div>

      <div class="menu-bottom">
        <div class="avatar" id="menuAvatarInitials"></div>
      </div>
    </nav>
  </div>
</header>
<h6>¡¡¡ATENCION: TODA LA INFORMACION AGREGADA A LA PAGINA SE DEBE PRESENTAR EN FORMATO PAPEL PARA SU RESPECTIVO LEGAJO!!!</h6>
  <div class="busqueda-contenedor">
  <h4>Tabla de Información de Alumnos:</h4>
  <select id="filtro_curso" style="padding:6px 12px;border:1px solid #313131;border-radius:4px;">
    <option value="">Todos los cursos</option>
    <?php foreach ($cursos as $c): ?>
      <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" id="buscador" placeholder="Buscar...">
</div>

<?php if ($puedeAgregar): ?>
<button id="btnAgregar" onclick="toggleForm()">➕ Agregar Alumno</button>
<?php endif; ?>

<?php if ($puedeAgregar): ?>
<div id="formulario-alumno">
  <select id="curso_id" style="width:calc(25% - 12px);padding:8px;border:1px solid #ccc;border-radius:4px;">
    <option value="" selected disabled>Elegí un curso</option>
    <?php foreach ($cursos as $c): ?>
      <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="text" id="nombre" placeholder="Apellido y Nombre">
  <input type="text" id="dni" placeholder="DNI">
  <input type="text" id="telefono" placeholder="Teléfono">
  <input type="text" id="direccion" placeholder="Dirección">
  <input type="text" id="ausente" placeholder="Ausente">
  <input type="text" id="presente" placeholder="Presente">
  <button onclick="agregarAlumno()">Agregar</button>
</div>
<?php endif; ?>



<main>
<table id="mitabla">
<thead>
<tr>
<th>Apellido y Nombre</th>
<th>DNI</th>
<th>Ausente</th>
<th>Presente</th>
<th>Dirección</th>
<th>Teléfono</th>
<th>Acciones</th>
</tr>
</thead>
<tbody id="cuerpoTabla"></tbody>
</table>
</main>
 <!-- 🟦 BOTÓN Y VENTANA DE MENSAJES -->
   <a href="msg.php"><button id="boton-flotante" >💬</button></a>
<footer>
<p>&copy; SGI</p>
</footer>
<script src="/js/main.js"></script>
<script>
window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? $user['nombre'] ?? 'Usuario');?>"; // Iniciales

// Mostrar/ocultar formulario
function toggleForm(){
    const f=document.getElementById("formulario-alumno");
    f.style.display=(f.style.display==="none" || f.style.display==="")?"flex":"none";
}

// Crear fila alumno
function crearFilaAlumno(datos){
    const tbody=document.getElementById("cuerpoTabla");
    const fila=document.createElement("tr");
    fila.innerHTML=`
        <td>${datos.nombre}</td>
        <td>${datos.dni}</td>
        <td>${datos.ausente}</td>
        <td>${datos.presente}</td>
        <td>${datos.direccion}</td>
        <td>${datos.telefono}</td>
        <td class="acciones">
            <button onclick="editarFila(this)"><i class="fas fa-edit"></i></button>
            <button onclick="eliminarFila(this)"><i class="fas fa-trash"></i></button>
            <button onclick="toggleExtra(this)"><i class="fas fa-chevron-down"></i></button>
        </td>`;
    tbody.appendChild(fila);

    const filaExtra=document.createElement("tr");
    filaExtra.className="extra";
    filaExtra.innerHTML=`
    <td colspan="7">
        <div class="extra-contenido">
            <p><strong>Tutor:</strong> ${datos.tutor}</p>
            <p><strong>Enfermedades:</strong> ${datos.enfermedades}</p>
            <p><strong>Comentarios:</strong> ${datos.comentarios}</p>
        </div>
    </td>`;
    tbody.appendChild(filaExtra);
}

function agregarAlumno(){
  const datos = {
    nombre:     document.getElementById("nombre").value.trim(),
    dni:        document.getElementById("dni").value.trim(),
    telefono:   document.getElementById("telefono").value.trim(),
    direccion:  document.getElementById("direccion").value.trim(),
    ausente:    document.getElementById("ausente").value.trim(),
    presente:   document.getElementById("presente").value.trim(),
  };
  if (!datos.nombre || !datos.dni) {
    alert("Falta completar nombre y DNI");
    return;
  }

  // 👇 Tomar el curso elegido del <select>
  const sel = document.getElementById('curso_id');
  if (!sel || !sel.value) {
    alert("Elegí un curso");
    return;
  }
  const curso_id = sel.value;

  // Armamos el FormData para el POST
  const form = new FormData();
  for (const k in datos) form.append(k, datos[k]);
  form.append('curso_id', curso_id); // también por POST (por si lo usás después)


  fetch('api_agregar_alumno.php?curso_id=' + encodeURIComponent(curso_id), {
      method: 'POST',
      body: form
  })
    .then(r => r.json())
    .then(j => {
      if (!j.ok) {
        alert(j.msg || 'Error al guardar');
        return;
      }

      if (j.temp_password) {
        alert('Alumno creado correctamente.\n\nUsuario: ' + j.alumno.dni + '\nContraseña temporal: ' + j.temp_password);
      }

      // Dibujamos el alumno que devolvió la API
      crearFilaAlumno(j.alumno);

      // Limpiamos el formulario
      document.querySelectorAll("#formulario-alumno input").forEach(i => i.value = "");
      sel.value = '';

      // Cerramos el formulario
      toggleForm();

      // Volvemos a cargar desde la base (por si cambió algo más)
      cargarAlumnos();
    })
    .catch(() => alert('Error de red'));
}



// Eliminar fila
function eliminarFila(boton){
    if (!confirm("¿Seguro que querés eliminar este alumno?")) return;

    const fila = boton.closest("tr");
    const celdas = fila.querySelectorAll("td");

    // Tomar el DNI mostrado (luego se usa como alumno_dni)
    const dni = (celdas[1]?.textContent || '').trim();
    if (!dni) { alert("No se encontró DNI"); return; }

    const selCurso = document.getElementById('filtro_curso');
    const curso_id = selCurso && selCurso.value ? selCurso.value : '';

    const form = new FormData();
    form.append('dni', dni);       // <- alumno_dni
    if (curso_id) form.append('curso_id', curso_id);

    fetch('api_eliminar_alumno.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(j => {
        if (!j.ok) { alert(j.msg || "Error al eliminar"); return; }

        // Borrar visualmente
        const extra = fila.nextElementSibling;
        if (extra && extra.classList.contains('extra')) extra.remove();
        fila.remove();

        // Refrescar tabla desde BD
        cargarAlumnos();
    })
    .catch(err => {
        console.error(err);
        alert("Error de red");
    });
}



// Editar fila
function editarFila(boton){
    const fila  = boton.closest("tr");
    const extra = fila.nextElementSibling;
    const icono = boton.querySelector("i");
    const celdas = fila.querySelectorAll("td");

    // Índices de columnas:
    // 0: nombre
    // 1: DNI (NO se edita)
    // 2: ausente
    // 3: presente
    // 4: dirección
    // 5: teléfono
    // 6: acciones

    if (icono.classList.contains("fa-edit")) {
        // === MODO EDICIÓN ===
        // Nombre
        let valNom = celdas[0].textContent.trim();
        celdas[0].innerHTML = `<input type="text" value="${valNom}">`;

        // AUSENTE
        let valAus = celdas[2].textContent.trim();
        celdas[2].innerHTML = `<input type="text" value="${valAus}">`;

        // PRESENTE
        let valPre = celdas[3].textContent.trim();
        celdas[3].innerHTML = `<input type="text" value="${valPre}">`;

        // DIRECCIÓN
        let valDir = celdas[4].textContent.trim();
        celdas[4].innerHTML = `<input type="text" value="${valDir}">`;

        // TELÉFONO
        let valTel = celdas[5].textContent.trim();
        celdas[5].innerHTML = `<input type="text" value="${valTel}">`;

        icono.classList.replace("fa-edit","fa-save");
    } else {
        // === MODO GUARDAR ===
        const dni = celdas[1].textContent.trim(); // No editable

        const nuevoNombre = celdas[0].querySelector("input").value.trim();
        const nuevoAus    = celdas[2].querySelector("input").value.trim();
        const nuevoPre    = celdas[3].querySelector("input").value.trim();
        const nuevoDir    = celdas[4].querySelector("input").value.trim();
        const nuevoTel    = celdas[5].querySelector("input").value.trim();

        const form = new FormData();
        form.append('dni', dni);
        form.append('nombre', nuevoNombre);
        form.append('ausente', nuevoAus);
        form.append('presente', nuevoPre);
        form.append('direccion', nuevoDir);
        form.append('telefono', nuevoTel);

        fetch('api_actualizar_alumno.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) {
                alert(j.msg || "Error al actualizar");
                return;
            }

            // Actualizar celdas con el valor "limpio"
            celdas[0].textContent = j.alumno.nombre;
            // celdas[1] = DNI se mantiene
            celdas[2].textContent = j.alumno.ausente;
            celdas[3].textContent = j.alumno.presente;
            celdas[4].textContent = j.alumno.direccion;
            celdas[5].textContent = j.alumno.telefono;

            // Cerrar extra si estaba abierto
            if (extra && extra.classList.contains("extra")) {
                const contenido = extra.querySelector(".extra-contenido");
                if (contenido) contenido.style.maxHeight = "0px";
            }

            icono.classList.replace("fa-save","fa-edit");
        })
        .catch(err => {
            console.error(err);
            alert("Error de red al actualizar");
        });
    }
}



// Mostrar/ocultar fila extra
function toggleExtra(boton){
    const fila=boton.closest("tr");
    const extra=fila.nextElementSibling;
    if(extra && extra.classList.contains("extra")){
        const contenido=extra.querySelector(".extra-contenido");
        if(contenido.style.maxHeight && contenido.style.maxHeight!="0px"){
            contenido.style.maxHeight="0px";
            boton.querySelector("i").classList.replace("fa-chevron-up","fa-chevron-down");
        }else{
            contenido.style.maxHeight=contenido.scrollHeight+"px";
            boton.querySelector("i").classList.replace("fa-chevron-down","fa-chevron-up");
        }
    }
}

// Buscar y resaltar
document.getElementById("buscador").addEventListener("input", function(){
    const texto=this.value.toLowerCase();
    const filas=document.querySelectorAll("#cuerpoTabla tr:not(.extra)");
    filas.forEach(fila=>{
        fila.classList.remove("resaltado");
        const celdas=fila.querySelectorAll("td");
        celdas.forEach(td=>{
            if(td.textContent.toLowerCase().includes(texto) && texto!=""){
                fila.classList.add("resaltado");
            }
        });
    });
});
 function pintarAlumnos(lista){
  const tbody = document.getElementById('cuerpoTabla');
  tbody.innerHTML = '';
  for (const a of lista) {
    crearFilaAlumno({
      nombre: a.nombre,
      dni: a.dni,
      ausente: a.ausente || '',
      presente: a.presente || '',
      direccion: a.direccion || '',
      telefono: a.telefono || '',
      tutor: '',
      enfermedades: '',
      comentarios: ''
    });
  }
}
function cargarAlumnos(){
  const sel = document.getElementById('filtro_curso');
  const params = sel && sel.value ? ('?curso_id='+encodeURIComponent(sel.value)) : '';

  fetch('api_listar_alumnos.php'+params, {
    credentials: 'same-origin' // envía cookies/sesión
  })
  .then(async (r) => {
    const text = await r.text();
    if (!r.ok) {
      console.error('HTTP error', r.status, text);
      throw new Error('HTTP '+r.status);
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Respuesta no JSON:', text);
      throw new Error('Respuesta no JSON');
    }
  })
  .then(j => {
    if (!j.ok) {
      alert(j.msg || 'No se pudieron cargar alumnos');
      return;
    }
    pintarAlumnos(j.alumnos || []);
  })
  .catch(err => {
    console.error('Fetch falló:', err);
    alert('Error de red al listar alumnos');
  });
}

// Al entrar a la página, cargá alumnos:
document.addEventListener('DOMContentLoaded', function(){
  cargarAlumnos();
  const sel = document.getElementById('filtro_curso');
  if (sel) sel.addEventListener('change', cargarAlumnos);
});

</script>

</body>
</html>
