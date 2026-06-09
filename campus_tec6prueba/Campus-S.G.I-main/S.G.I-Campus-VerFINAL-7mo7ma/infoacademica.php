<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/config.php';
require __DIR__.'/auth.php';
requireLogin();

$pdo = db();
$rol = strtolower(trim($_SESSION['rol'] ?? ''));

// quién puede editar
$soloLectura = !in_array($rol, ['profesor','preceptor','directivo'], true);

// año lectivo activo (el más alto)
$yearRow = $pdo->query("SELECT id, `year` FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetch();
$year_id = (int)($yearRow['id'] ?? 0);
$year_actual = (int)($yearRow['year'] ?? date('Y'));
if (!$year_id) { die('⚠️ Configurá year_escolar (no hay año activo)'); }

// =========================
// Cursos según rol
// =========================
$cursos = [];

// cursos para el selector según rol
$dniUsuario = (int)($_SESSION['dni'] ?? 0);

if ($rol === 'profesor') {
    // Cursos donde el profe tiene materias asignadas (docente_materia_curso + curso_materia)
    $sql = "
      SELECT DISTINCT
        c.id,
        CONCAT(cy.year,' ', cd.division,
               IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
      FROM docente_materia_curso dmc
      JOIN curso_materia cm ON cm.id = dmc.curso_materia_id
      JOIN curso c          ON c.id = cm.curso_id
      JOIN curso_year cy    ON cy.id = c.curso_year_id
      JOIN curso_division cd ON cd.id = c.curso_division_id
      LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
      WHERE dmc.maestro_dni = :dni
        AND cm.year_escolar_id = :year
      ORDER BY cy.year, cd.division, mo.nombre";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':dni'  => $dniUsuario,
        ':year' => $year_id
    ]);
    $cursos = $st->fetchAll(PDO::FETCH_ASSOC);

} elseif ($rol === 'preceptor') {
    // Cursos a cargo del preceptor
    $sql = "
      SELECT DISTINCT
        c.id,
        CONCAT(cy.year,' ', cd.division,
               IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
      FROM preceptor_curso pc
      JOIN curso c           ON c.id = pc.curso_id
      JOIN curso_year cy     ON cy.id = c.curso_year_id
      JOIN curso_division cd ON cd.id = c.curso_division_id
      LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
      WHERE pc.preceptor_dni = :dni
        AND pc.year_escolar_id = :year
      ORDER BY cy.year, cd.division, mo.nombre";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':dni'  => $dniUsuario,
        ':year' => $year_id
    ]);
    $cursos = $st->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Directivo u otros: todos los cursos
    $cursos = $pdo->query("
      SELECT c.id,
             CONCAT(cy.year, ' ', cd.division,
                    IF(mo.nombre IS NULL,'', CONCAT(' - ', mo.nombre))) AS nombre
      FROM curso c
      JOIN curso_year cy ON cy.id = c.curso_year_id
      JOIN curso_division cd ON cd.id = c.curso_division_id
      LEFT JOIN modalidad mo ON mo.id = c.modalidad_id
      ORDER BY cy.year, cd.division, mo.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Boletín de Calificaciones</title>
  <link rel="icon" href="imagenes/icono-sgi.png" type="image/x-icon" />
  <!-- STYLES -->
  <link rel="stylesheet" href="css/menuHamburguesa.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/avatar.css">
  <link rel="stylesheet" href="css/ChatFlotante.css">
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #d8d7d7;
    }
    .logo, .logo2{
      width: 100px;
      height: auto;
    }
    .title-box {
      text-align: center;
      flex-grow: 1;
    }
    .title-box h1 { margin: 0; font-size: 30px; }
    .title-box h2 { margin: 0; font-size: 20px; font-weight: normal; }

    main { padding: 20px; }
    section { margin-bottom: 40px; }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
    }
    th, td {
      border: 1px solid #000000;
      padding: 8px;
      text-align: center;
      font-size: 14px;
    }
    th { background: #e2e8f0; font-weight: bold; }

    select, input {
      font-size: 14px;
      padding: 4px;
    }
    input[type="number"] { width: 70px; text-align: center; }
    input[type="text"]   { width: 100%; }

    footer {
      text-align: center;
      padding: 10px;
      margin-top: 20px;
      font-weight: bold;
      font-size: 16px;
    }

    .alerta {
      display: none;
      text-align: center;
      background: #4caf50;
      color: white;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    /* TELEFONO ESCONDER LOGOS */
    @media (max-width: 768px) {
      .logo{ 
        display: none;
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="navbar">
      <button class="menu-icon" aria-label="Abrir menú" onclick="openMenu()">☰</button>
      <a href="SGI.php">
        <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2">
      </a>
      <div class="title-box">
        <h1>Boletín de Calificaciones</h1>
        <h2>Ciclo Lectivo <?= htmlspecialchars($yearRow['year']) ?></h2>

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

    <div class="alerta" id="alerta">✅ notas guardadas correctamente</div>

    <!-- MENÚ HAMBURGUESA LATERAL -->
    <div class="overlay" id="overlay" onclick="closeMenu(event)">
      <nav class="menu-panel" onclick="event.stopPropagation()">
        <button class="close-btn" aria-label="Cerrar menú" onclick="closeMenu()">×</button>
        <div class="menu-top">
          <a href="SGI.php">
            <img src="imagenes/newlogo1.webp" alt="logo SGI" class="logo2">
          </a>
          <h1>S.G.I</h1>
          <h2>Sistema De Gestión Institucional</h2>
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
          <div class="avatar" id="avatarMenuInitials"></div>
        </div>
      </nav>
    </div>
  </header>

  <main>
    <section>
      <!-- Filtros -->
      <div style="margin: 15px 0; text-align:center; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
        <select id="selCurso" style="padding:6px 12px;">
          <option value="">Elegí curso</option>
          <?php foreach ($cursos as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="selMateria" style="padding:6px 12px;" disabled>
          <option value="">Elegí materia</option>
        </select>
      </div>

      <!-- Panel de notas: oculto hasta elegir curso+materia -->
      <div id="panelNotas" style="display:none;">
        <div style="margin-bottom: 15px; text-align: center;">
          <button id="bloquearBtn" title="Bloquear" style="font-size:20px; cursor:pointer; margin-right:10px;">🔒</button>
          <button id="guardarBtn"  title="Guardar"  style="font-size:20px; cursor:pointer;">💾</button>
        </div>

<div id="contenedorTabla" style="display:none;">
  <table id="tablaNotas">
    <thead>
      <tr>
        <th>Documento</th>
        <th>Nombre</th>
        <th>Valorativa C1</th>
        <th>Numérica C1</th>
        <th>Valorativa C2</th>
        <th>Numérica C2</th>
        <th>Nota Final</th>
        <th>Observaciones</th>
      </tr>
    </thead>
    <tbody id="tbodyNotas">
      <tr><td colspan="8">Seleccioná curso y materia…</td></tr>
    </tbody>
  </table>
</div>

    </section>
    <footer><p>&copy; S.G.I.</p></footer>
  </main>

  <!-- Chat flotante -->
  <a href="msg.php"><button id="boton-flotante">💬</button></a>

 <script src="/js/main.js"></script>
<script>
window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? 'Usuario');?>";

const SOLO_LECTURA = <?= $soloLectura ? 'true' : 'false' ?>;
const YEAR_ACTUAL  = <?= (int)$yearRow['year'] ?>;

const bloquearBtn   = document.getElementById("bloquearBtn");
const guardarBtn    = document.getElementById("guardarBtn");
const tabla         = document.getElementById("tablaNotas");
const tbodyNotas    = document.getElementById("tbodyNotas");
const contTabla     = document.getElementById("contenedorTabla");
const selCurso      = document.getElementById("selCurso");
const selMateria    = document.getElementById("selMateria");
const alerta        = document.getElementById("alerta");
const panelNotas    = document.getElementById("panelNotas");
    
let bloqueado = SOLO_LECTURA;          // alumno/familia: lectura; prof/preceptor/directivo: editable
if (bloquearBtn) bloquearBtn.textContent = bloqueado ? "🔒" : "🔓";
if (guardarBtn)  guardarBtn.style.display = SOLO_LECTURA ? "none" : "inline-block";

/* ===== Helpers ===== */
function teSelect(value = '', disabled = true){
  const s = document.createElement('select');
  const opts = ['','TEP','TEA','TED'];
  for (const v of opts){
    const op = document.createElement('option');
    op.value = v;
    op.textContent = v === '' ? 'select' : v;
    if (v === (value || '')) op.selected = true;
    s.appendChild(op);
  }
  s.disabled = disabled;
  return s;
}
function numInput(value = '', disabled = true){
  const i = document.createElement('input');
  i.type  = 'number';
  i.min   = '1';
  i.max   = '10';
  i.step  = '0.01';
  i.value = (value == null ? '' : value);
  i.disabled = disabled;
  i.style.width = '70px';
  return i;
}
function textInput(value = '', disabled = true){
  const i = document.createElement('input');
  i.type  = 'text';
  i.value = (value || '');
  i.disabled = disabled;
  i.style.width = '100%';
  return i;
}
function lockUnlockTable(){
  if (!tbodyNotas) return;
  tbodyNotas.querySelectorAll('input, select').forEach(el => el.disabled = bloqueado);
  if (bloquearBtn) bloquearBtn.textContent = bloqueado ? '🔒' : '🔓';
}

/* ===== Pintar alumnos + notas ===== */
function pintarAlumnosNotas(lista){
  tbodyNotas.innerHTML = '';

  if (!lista || !lista.length){
    tbodyNotas.innerHTML = `<tr><td colspan="8">Sin alumnos</td></tr>`;
    contTabla.style.display = 'block';
    return;
  }

  for (const a of lista){
    const tr = document.createElement('tr');

    // DNI
    const tdDni = document.createElement('td');
    tdDni.textContent = a.dni || '';
    tr.appendChild(tdDni);

    // Nombre
    const tdNom = document.createElement('td');
    tdNom.textContent = a.nombre || '';
    tr.appendChild(tdNom);

    // C1 valorativa
    const tdC1v = document.createElement('td');
    const selC1v = teSelect(a.c1_val, bloqueado);
    tdC1v.appendChild(selC1v);
    tr.appendChild(tdC1v);

    // C1 numérica
    const tdC1n = document.createElement('td');
    const inC1n = numInput(a.c1_num ?? '', bloqueado);
    tdC1n.appendChild(inC1n);
    tr.appendChild(tdC1n);

    // C2 valorativa
    const tdC2v = document.createElement('td');
    const selC2v = teSelect(a.c2_val, bloqueado);
    tdC2v.appendChild(selC2v);
    tr.appendChild(tdC2v);

    // C2 numérica
    const tdC2n = document.createElement('td');
    const inC2n = numInput(a.c2_num ?? '', bloqueado);
    tdC2n.appendChild(inC2n);
    tr.appendChild(tdC2n);

    // Nota final (solo lectura)
    const tdFinal = document.createElement('td');
    const inFinal = numInput(a.final_num ?? '', true);
    tdFinal.appendChild(inFinal);
    tr.appendChild(tdFinal);

    // Observaciones
    const tdObs = document.createElement('td');
    const inObs = textInput(a.obs ?? '', bloqueado);
    tdObs.appendChild(inObs);
    tr.appendChild(tdObs);

    function recalcularFinal(){
      const v1 = parseFloat(inC1n.value);
      const v2 = parseFloat(inC2n.value);
      if (isNaN(v1) || isNaN(v2)) {
        inFinal.value = '';
        return;
      }
      inFinal.value = Math.floor((v1 + v2) / 2);
    }
    if (!SOLO_LECTURA){
      inC1n.addEventListener('input', recalcularFinal);
      inC2n.addEventListener('input', recalcularFinal);
    }

    tbodyNotas.appendChild(tr);
  }

  contTabla.style.display = 'block';
}

/* ===== Cargar materias para un curso ===== */
async function cargarMaterias(cursoId){
  selMateria.innerHTML = `<option value="">Cargando...</option>`;
  selMateria.disabled = true;
  contTabla.style.display = 'none';
  tbodyNotas.innerHTML   = `<tr><td colspan="8">Seleccioná curso y materia…</td></tr>`;
  if (panelNotas) panelNotas.style.display = 'none';

  if (!cursoId) {
    selMateria.innerHTML = `<option value="">Elegí materia</option>`;
    return;
  }

  try{
    const r = await fetch(`api_listar_materias.php?curso_id=${encodeURIComponent(cursoId)}`, {
      credentials: 'same-origin'
    });
    const j = await r.json();
    if (!j.ok){
      alert(j.msg || 'No se pudieron cargar materias');
      selMateria.innerHTML = `<option value="">Elegí materia</option>`;
      return;
    }
    selMateria.innerHTML = `<option value="">Elegí materia</option>` +
      j.materias.map(m => `<option value="${m.id}">${m.nombre}</option>`).join('');
    selMateria.disabled = false;
  }catch(e){
    console.error(e);
    alert('Error de red al listar materias');
    selMateria.innerHTML = `<option value="">Elegí materia</option>`;
  }
}

/* ===== Cargar alumnos + notas para curso + materia ===== */
async function cargarAlumnosNotas(){
  const cursoId   = selCurso.value;
  const materiaId = selMateria.value;

  if (!cursoId || !materiaId){
    contTabla.style.display = 'none';
    if (panelNotas) panelNotas.style.display = 'none';
    tbodyNotas.innerHTML = `<tr><td colspan="8">Seleccioná curso y materia…</td></tr>`;
    return;
  }

  tbodyNotas.innerHTML = `<tr><td colspan="8">Cargando…</td></tr>`;
  contTabla.style.display = 'block';
  if (panelNotas) panelNotas.style.display = 'block';

  try{
    const r = await fetch(
      `api_listar_alumnos_notas.php?curso_id=${encodeURIComponent(cursoId)}&materia_id=${encodeURIComponent(materiaId)}`,
      { credentials: 'same-origin' }
    );
    const j = await r.json();
    if (!j.ok){
      alert(j.msg || 'No se pudieron cargar alumnos/notas');
      tbodyNotas.innerHTML = `<tr><td colspan="8">Error al cargar</td></tr>`;
      return;
    }
    pintarAlumnosNotas(j.alumnos || []);
    lockUnlockTable();
  }catch(e){
    console.error(e);
    alert('Error de red al listar alumnos/notas');
    tbodyNotas.innerHTML = `<tr><td colspan="8">Error de red</td></tr>`;
  }
}

/* ===== Guardar notas (api_guardar_notas_detalle.php) ===== */
async function guardarNotas(){
  if (bloqueado || SOLO_LECTURA){
    alert('No autorizado para guardar.');
    return;
  }

  const materiaId = selMateria.value;
  const cursoId   = selCurso.value;
  if (!cursoId || !materiaId){
    alert('Elegí curso y materia');
    return;
  }

  const rows = [];
  tbodyNotas.querySelectorAll('tr').forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 8) return;

    const dni = parseInt((tds[0].textContent || '').trim(), 10) || 0;
    if (!dni) return;

    rows.push({
      dni,
      c1_val:    tds[2].querySelector('select')?.value || null,
      c1_num:    tds[3].querySelector('input')?.value || null,
      c2_val:    tds[4].querySelector('select')?.value || null,
      c2_num:    tds[5].querySelector('input')?.value || null,
      final_num: tds[6].querySelector('input')?.value || null,
      obs:       tds[7].querySelector('input')?.value || null
    });
  });

  try{
    const r = await fetch('api_guardar_notas_detalle.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        materia_id: parseInt(materiaId, 10),
        data: rows
      })
    });
    const j = await r.json();
    if (!j.ok){
      alert(j.msg || 'No se pudo guardar');
      return;
    }

    if (alerta){
      alerta.textContent = "✅ notas guardadas correctamente";
      alerta.style.display = "block";
      setTimeout(() => alerta.style.display = "none", 2000);
    }
  }catch(e){
    console.error(e);
    alert("Error de red al guardar notas");
  }
}

/* ===== Eventos UI ===== */
if (bloquearBtn){
  bloquearBtn.addEventListener('click', () => {
    if (SOLO_LECTURA) return;
    bloqueado = !bloqueado;
    lockUnlockTable();
  });
}
if (guardarBtn){
  guardarBtn.addEventListener('click', () => {
    if (bloqueado){
      alert("⚠️ No se puede guardar mientras está bloqueado.");
      return;
    }
    guardarNotas();
  });
}

if (selCurso){
  selCurso.addEventListener('change', e => {
    const v = e.target.value;
    selMateria.innerHTML = `<option value="">Elegí materia</option>`;
    selMateria.disabled = !v;
    contTabla.style.display = 'none';
    tbodyNotas.innerHTML = `<tr><td colspan="8">Seleccioná curso y materia…</td></tr>`;
    if (v) cargarMaterias(v);
  });
}
if (selMateria){
  selMateria.addEventListener('change', cargarAlumnosNotas);
}

document.addEventListener('DOMContentLoaded', () => {
  lockUnlockTable(); // aplica estado bloqueado inicial
});
</script>

</body>
</html>

