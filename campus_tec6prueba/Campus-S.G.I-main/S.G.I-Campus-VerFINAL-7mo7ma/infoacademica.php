<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/config.php';
require __DIR__.'/auth.php';
require_once __DIR__ . '/auth.php';
requirePage('notas');

$pdo = db();
$rol = strtolower(trim($_SESSION['rol'] ?? ''));

// quién puede editar
$soloLectura = true;

// El ciclo actual es el más reciente registrado en year_escolar.
$years = $pdo->query("SELECT id, `year` FROM year_escolar ORDER BY `year` DESC")->fetchAll(PDO::FETCH_ASSOC);
$yearActual = $years[0] ?? null;
if (!$yearActual) { die('⚠️ Configurá year_escolar (no hay año activo)'); }

$yearActualId = (int)$yearActual['id'];
$year_id = isset($_GET['year_id']) ? (int)$_GET['year_id'] : $yearActualId;
$yearRow = null;
foreach ($years as $year) {
  if ((int)$year['id'] === $year_id) {
    $yearRow = $year;
    break;
  }
}
if (!$yearRow) {
  $yearRow = $yearActual;
  $year_id = $yearActualId;
}
$esCicloActual = $year_id === $yearActualId;
$soloLectura = !in_array($rol, ROLES_NOTAS, true) || !$esCicloActual;

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

    .intens-box {
      display: inline-block;
      padding: 5px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: bold;
      background: #e5e7eb;
      color: #374151;
      min-width: 110px;
      text-align: center;
    }
    .intens-box.aprobado {
      background: #dcfce7;
      color: #166534;
    }
    .intens-box.desaprobado {
      background: #fee2e2;
      color: #991b1b;
    }
    .recursar-badge {
      display: inline-block;
      margin-left: 8px;
      padding: 3px 8px;
      border-radius: 999px;
      background: #fef3c7;
      color: #92400e;
      font-size: 12px;
      font-weight: bold;
    }
    .seguimiento-panel {
      margin: 22px 0;
      padding: 18px 20px;
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-top: 4px solid #0f172a;
      border-radius: 4px;
      box-shadow: 0 2px 8px rgba(15,23,42,.08);
    }
    .seguimiento-panel[hidden] { display: none; }
    .seguimiento-panel h2 { margin: 0 0 6px; color: #0f172a; font-size: 20px; }
    .seguimiento-intro { margin: 0 0 16px; color: #475569; font-size: 14px; }
    .seguimiento-tabs { display: flex; gap: 8px; margin: 0 0 14px; border-bottom: 1px solid #cbd5e1; }
    .seguimiento-tab { padding: 9px 14px; border: 0; border-bottom: 3px solid transparent; background: transparent; color: #475569; cursor: pointer; font-weight: 600; }
    .seguimiento-tab[aria-selected="true"] { border-bottom-color: #166534; color: #166534; }
    .recursadas-table-wrap { overflow-x: auto; }
    .recursadas-table { width: 100%; border-collapse: collapse; background: #fff; }
    .recursadas-table th, .recursadas-table td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
    .recursadas-table th { color: #334155; font-size: 12px; white-space: nowrap; }
    .recursada-estado { padding: 7px 9px; border: 1px solid #94a3b8; border-radius: 4px; background: #fff; color: #0f172a; }
    .recursada-badge { display: inline-block; padding: 5px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .recursada-badge.aprobada { background: #dcfce7; color: #166534; }
    .recursada-badge.no-aprobada { background: #fee2e2; color: #991b1b; }
    .recursada-guardar { padding: 7px 10px; border: 0; border-radius: 4px; background: #166534; color: #fff; cursor: pointer; }
    .recursada-guardar:disabled { opacity: .55; cursor: wait; }
    .seguimiento-alumno { margin: 18px 0 0; padding: 14px; background: #fff; border: 1px solid #dbe3ee; border-radius: 4px; }
    .seguimiento-alumno h3 { margin: 0 0 8px; color: #0f172a; font-size: 16px; }
    .seguimiento-materia { display: grid; grid-template-columns: minmax(240px, 1fr) minmax(180px, .6fr) minmax(320px, 1.4fr); gap: 14px; align-items: start; padding: 12px 0; border-top: 1px solid #e2e8f0; }
    .seguimiento-clasificacion { width: 100%; max-width: 220px; padding: 8px 10px; border: 1px solid #94a3b8; border-radius: 4px; background: #fff; color: #0f172a; }
    .seguimiento-intento { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .seguimiento-intento input, .seguimiento-intento select { max-width: 190px; padding: 7px 9px; border: 1px solid #94a3b8; border-radius: 4px; }
    .seguimiento-intento button, .seguimiento-guardar { padding: 8px 12px; border: 0; border-radius: 4px; background: #0f172a; color: #fff; cursor: pointer; }
    .seguimiento-deshacer { margin-left: 8px; padding: 4px 8px; border: 1px solid #b91c1c; border-radius: 4px; background: #fff; color: #991b1b; cursor: pointer; font-size: 12px; }
    .seguimiento-guardar { margin-top: 14px; background: #166534; }
    .seguimiento-estado { margin-top: 5px; color: #64748b; font-size: 12px; }
    .seguimiento-aviso { min-height: 20px; margin: 8px 0 0; color: #92400e; font-size: 13px; }
    @media (max-width: 900px) { .seguimiento-materia { grid-template-columns: 1fr; } }

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
        <h2>Ciclo Lectivo <?= htmlspecialchars((string)$yearRow['year']) ?></h2>

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
        <label for="selYear" style="align-self:center;font-weight:bold;">Ciclo lectivo</label>
        <select id="selYear" style="padding:6px 12px;" aria-label="Elegir ciclo lectivo">
          <?php foreach ($years as $year): ?>
            <option value="<?= (int)$year['id'] ?>" <?= (int)$year['id'] === $year_id ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$year['year']) ?><?= (int)$year['id'] === $yearActualId ? ' (actual)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>

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
          <?php if (!$esCicloActual): ?><div style="margin-top:8px;color:#92400e;">Ciclo anterior: consulta de solo lectura.</div><?php endif; ?>
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
        <th>Instancia de intensificación</th>
        <th>Observaciones</th>
      </tr>
    </thead>
    <tbody id="tbodyNotas">
      <tr><td colspan="9">Seleccioná curso y materia…</td></tr>
    </tbody>
  </table>
</div>
      </div>

      <?php if ($esCicloActual && in_array($rol, ['preceptor','directivo','admin','root'], true)): ?>
      <section id="panelSeguimiento" class="seguimiento-panel" hidden>
        <h2>Materias pendientes</h2>
        <p class="seguimiento-intro">Se incluyen materias del ciclo actual y de años anteriores que todavía no se aprobaron. La nota de cada fila corresponde al ciclo de origen. Se permiten hasta cinco intensificaciones; recursar requiere cursar el ciclo completo.</p>
        <div class="seguimiento-tabs" role="tablist" aria-label="Seguimiento de materias pendientes">
          <button type="button" class="seguimiento-tab" role="tab" aria-selected="true" data-seguimiento-vista="intensificar">Intensificar</button>
          <button type="button" class="seguimiento-tab" role="tab" aria-selected="false" data-seguimiento-vista="recursar">Recursar</button>
        </div>
        <div id="vistaIntensificar" role="tabpanel">
          <div id="listaIntensificaciones"><p>Seleccioná un curso para consultar sus alumnos.</p></div>
        </div>
        <div id="vistaRecursar" role="tabpanel" hidden>
          <p class="seguimiento-intro">Marcá cuándo el alumno queda inscripto cursando esta materia. El cierre aprobado o no aprobado se obtiene de las notas del curso.</p>
          <div id="listaRecursadas"><p>Seleccioná un curso para consultar recursadas.</p></div>
        </div>
      </section>
      <?php endif; ?>

    </section>
    <footer><p>&copy; S.G.I.</p></footer>
  </main>

  <!-- Chat flotante -->
  <a href="msg.php"><button id="boton-flotante">💬</button></a>

 <script src="js/main.js"></script>
<script>
window.APP_USER_NAME = "<?=htmlspecialchars($_SESSION['usuario'] ?? 'Usuario');?>";

const SOLO_LECTURA = <?= $soloLectura ? 'true' : 'false' ?>;
const YEAR_ID      = <?= (int)$year_id ?>;
const YEAR_ACTUAL_ID = <?= (int)$yearActualId ?>;

const bloquearBtn   = document.getElementById("bloquearBtn");
const guardarBtn    = document.getElementById("guardarBtn");
const tabla         = document.getElementById("tablaNotas");
const tbodyNotas    = document.getElementById("tbodyNotas");
const contTabla     = document.getElementById("contenedorTabla");
const selCurso      = document.getElementById("selCurso");
const selMateria    = document.getElementById("selMateria");
const selYear       = document.getElementById("selYear");
const alerta        = document.getElementById("alerta");
const panelNotas    = document.getElementById("panelNotas");
const queryParams   = new URLSearchParams(window.location.search);
const initialCursoId = queryParams.get('curso_id');
const initialMateriaId = queryParams.get('materia_id');
    
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
    tbodyNotas.innerHTML = `<tr><td colspan="9">Sin alumnos</td></tr>`;
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
    const nombreText = document.createElement('span');
    nombreText.textContent = a.nombre || '';
    tdNom.appendChild(nombreText);
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

    // El resultado de intensificación se carga desde el seguimiento académico.
    const tdIntensificacion = document.createElement('td');
    tdIntensificacion.textContent = a.instancia_intensificacion || '—';
    tr.appendChild(tdIntensificacion);

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
  tbodyNotas.innerHTML   = `<tr><td colspan="9">Seleccioná curso y materia…</td></tr>`;
  if (panelNotas) panelNotas.style.display = 'none';

  if (!cursoId) {
    selMateria.innerHTML = `<option value="">Elegí materia</option>`;
    return;
  }

  try{
    const r = await fetch(`api_listar_materias.php?curso_id=${encodeURIComponent(cursoId)}&year_id=${YEAR_ID}`, {
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
    tbodyNotas.innerHTML = `<tr><td colspan="9">Seleccioná curso y materia…</td></tr>`;
    return;
  }

  tbodyNotas.innerHTML = `<tr><td colspan="9">Cargando…</td></tr>`;
  contTabla.style.display = 'block';
  if (panelNotas) panelNotas.style.display = 'block';

  try{
    const r = await fetch(
      `api_listar_alumnos_notas.php?curso_id=${encodeURIComponent(cursoId)}&materia_id=${encodeURIComponent(materiaId)}&year_id=${YEAR_ID}`,
      { credentials: 'same-origin' }
    );
    const j = await r.json();
    if (!j.ok){
      alert(j.msg || 'No se pudieron cargar alumnos/notas');
      tbodyNotas.innerHTML = `<tr><td colspan="9">Error al cargar</td></tr>`;
      return;
    }
    pintarAlumnosNotas(j.alumnos || []);
    lockUnlockTable();
  }catch(e){
    console.error(e);
    alert('Error de red al listar alumnos/notas');
    tbodyNotas.innerHTML = `<tr><td colspan="9">Error de red</td></tr>`;
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
    if (tds.length < 9) return;

    const dni = parseInt((tds[0].textContent || '').trim(), 10) || 0;
    if (!dni) return;

    rows.push({
      dni,
      c1_val:    tds[2].querySelector('select')?.value || null,
      c1_num:    tds[3].querySelector('input')?.value || null,
      c2_val:    tds[4].querySelector('select')?.value || null,
      c2_num:    tds[5].querySelector('input')?.value || null,
      final_num: tds[6].querySelector('input')?.value || null,
      obs:       tds[8].querySelector('input')?.value || null
    });
  });

  try{
    const r = await fetch('api_guardar_notas_detalle.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode(csrfToken()) ?> },
      body: JSON.stringify({
        curso_id: parseInt(cursoId, 10),
        materia_id: parseInt(materiaId, 10),
        year_id: YEAR_ID,
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
    await cargarIntensificaciones();
    if (vistaSeguimientoActual === 'recursar') await cargarRecursadas();
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
    tbodyNotas.innerHTML = `<tr><td colspan="9">Seleccioná curso y materia…</td></tr>`;
    if (v) cargarMaterias(v);
    cargarIntensificaciones();
    if (vistaSeguimientoActual === 'recursar') cargarRecursadas();
  });
}
if (selMateria){
  selMateria.addEventListener('change', cargarAlumnosNotas);
}
if (selYear){
  selYear.addEventListener('change', () => {
    window.location.href = `infoacademica.php?year_id=${encodeURIComponent(selYear.value)}`;
  });
}

const panelSeguimiento = document.getElementById('panelSeguimiento');
const listaIntensificaciones = document.getElementById('listaIntensificaciones');
const listaRecursadas = document.getElementById('listaRecursadas');
const vistaIntensificar = document.getElementById('vistaIntensificar');
const vistaRecursar = document.getElementById('vistaRecursar');
const csrfIntensificacion = <?= json_encode(csrfToken()) ?>;
let vistaSeguimientoActual = 'intensificar';

document.querySelectorAll('[data-seguimiento-vista]').forEach(boton => {
  boton.addEventListener('click', () => {
    vistaSeguimientoActual = boton.dataset.seguimientoVista;
    document.querySelectorAll('[data-seguimiento-vista]').forEach(tab => {
      tab.setAttribute('aria-selected', String(tab === boton));
    });
    vistaIntensificar.hidden = vistaSeguimientoActual !== 'intensificar';
    vistaRecursar.hidden = vistaSeguimientoActual !== 'recursar';
    if (vistaSeguimientoActual === 'recursar') cargarRecursadas();
  });
});

function crearTexto(tag, texto, className = '') {
  const elemento = document.createElement(tag);
  elemento.textContent = texto;
  if (className) elemento.className = className;
  return elemento;
}

async function cargarIntensificaciones() {
  if (!panelSeguimiento || !listaIntensificaciones) return;
  const cursoId = selCurso.value;
  panelSeguimiento.hidden = true;
  listaIntensificaciones.replaceChildren();
  if (!cursoId) {
    listaIntensificaciones.appendChild(crearTexto('p', 'Seleccioná un curso para consultar sus alumnos.'));
    return;
  }

  listaIntensificaciones.appendChild(crearTexto('p', 'Cargando materias pendientes...'));
  try {
    const response = await fetch(`api_listar_intensificaciones.php?curso_id=${encodeURIComponent(cursoId)}`, {credentials: 'same-origin'});
    const result = await response.json();
    if (!result.ok) throw new Error(result.msg || 'No se pudieron cargar las materias.');
    if (result.habilitado === false) return;
    panelSeguimiento.hidden = false;
    listaIntensificaciones.replaceChildren();
    if (!result.alumnos.length) {
      listaIntensificaciones.appendChild(crearTexto('p', 'No hay alumnos con materias pendientes en este curso.'));
      return;
    }

    result.alumnos.forEach(alumno => {
      const panelAlumno = document.createElement('div');
      panelAlumno.className = 'seguimiento-alumno';
      panelAlumno.appendChild(crearTexto('h3', `${alumno.nombre} — DNI ${alumno.dni}`));
      const formulario = document.createElement('div');
      formulario.dataset.alumno = String(alumno.dni);
      const aviso = crearTexto('p', '', 'seguimiento-aviso');
      const actualizarLimiteIntensificaciones = () => {
        const selectores = Array.from(formulario.querySelectorAll('[data-clasificacion]'));
        const elegibles = selectores.filter(selector => selector.dataset.elegible === 'true');
        const cantidad = elegibles.filter(selector => selector.value === 'intensificar').length;
        selectores.forEach(selector => {
          if (selector.dataset.elegible !== 'true') return;
          const opcionIntensificar = selector.querySelector('option[value="intensificar"]');
          if (cantidad >= 5 && selector.value !== 'intensificar' && !selector.disabled) {
            opcionIntensificar.disabled = true;
            if (selector.value === 'sin_clasificar') selector.value = 'recursar';
          } else {
            opcionIntensificar.disabled = false;
          }
          const materiaFila = selector.closest('.seguimiento-materia');
          const formularioIntento = materiaFila.querySelector('.seguimiento-intento');
          const seguimiento = alumno.materias.find(item => String(item.materia_id) === selector.dataset.materia
            && String(item.year_id) === selector.dataset.yearOrigen);
          formularioIntento.hidden = !(selector.value === 'intensificar' && seguimiento?.seguimiento_id);
        });
        aviso.textContent = cantidad >= 5
          ? 'Límite alcanzado: las demás materias quedan para recursar el ciclo completo.'
          : '';
      };

      alumno.materias.forEach(materia => {
        const fila = document.createElement('div');
        fila.className = 'seguimiento-materia';
        const datos = document.createElement('div');
        datos.appendChild(crearTexto('strong', materia.materia));
        const gradoLabel = materia.grado || 'grado no identificado';
        datos.appendChild(crearTexto('div', `${gradoLabel} · Ciclo ${materia.year_origen} · Nota/estado: ${materia.nota_origen}`, 'seguimiento-estado'));

        const selectorClasificacion = document.createElement('select');
        selectorClasificacion.className = 'seguimiento-clasificacion';
        selectorClasificacion.dataset.clasificacion = 'true';
        selectorClasificacion.dataset.elegible = materia.habilita_recuperacion ? 'true' : 'false';
        selectorClasificacion.dataset.materia = String(materia.materia_id);
        selectorClasificacion.dataset.yearOrigen = String(materia.year_id);
        selectorClasificacion.add(new Option('Elegí una opción', 'sin_clasificar'));
        selectorClasificacion.add(new Option('Intensificar', 'intensificar'));
        selectorClasificacion.add(new Option('Recursar el ciclo completo', 'recursar'));
        selectorClasificacion.value = materia.clasificacion || 'sin_clasificar';
        selectorClasificacion.disabled = !materia.habilita_recuperacion
          || materia.tiene_intentos_ciclo_actual;
        if (!materia.habilita_recuperacion) {
          selectorClasificacion.value = 'sin_clasificar';
          selectorClasificacion.querySelector('option[value="intensificar"]').disabled = true;
          selectorClasificacion.querySelector('option[value="recursar"]').disabled = true;
        }
        datos.appendChild(selectorClasificacion);

        const estadoActual = !materia.habilita_recuperacion
          ? (materia.nivel === 1 ? 'En 1.º año no se habilita intensificación ni recursado.' : 'No se pudo identificar el grado; no se habilitan acciones.')
          : (!materia.intentos.length
            ? (materia.clasificacion === 'intensificar'
              ? 'Seleccionada para intensificar'
              : (materia.clasificacion === 'recursar' ? 'Debe cursar la materia durante el año completo' : 'Pendiente de clasificación'))
            : 'Instancias registradas:');
        datos.appendChild(crearTexto('div', estadoActual, 'seguimiento-estado'));
        materia.intentos.forEach(intento => {
          const filaIntento = crearTexto('div',
            `${intento.instancia} ${intento.year}: ${intento.estado.replace('_', ' ')}${intento.nota !== null ? ` (${intento.nota})` : ''}${intento.nota_valorativa ? ` ${intento.nota_valorativa}` : ''}`,
            'seguimiento-estado');
          if (intento.year_id === YEAR_ACTUAL_ID && materia.clasificacion === 'intensificar') {
            const deshacer = document.createElement('button');
            deshacer.type = 'button';
            deshacer.className = 'seguimiento-deshacer';
            deshacer.textContent = 'Deshacer resultado';
            deshacer.addEventListener('click', async () => {
              if (!window.confirm(`¿Deshacer la instancia ${intento.instancia} de ${materia.materia}?`)) return;
              await guardarIntensificacion({
                accion: 'deshacer_intento',
                alumno_dni: alumno.dni,
                curso_id: cursoId,
                materia_id: materia.materia_id,
                year_origen_id: materia.year_id,
                instancia: intento.instancia
              });
            });
            filaIntento.appendChild(deshacer);
          }
          datos.appendChild(filaIntento);
        });

        const intentoForm = document.createElement('form');
        intentoForm.className = 'seguimiento-intento';
        intentoForm.hidden = !materia.habilita_recuperacion || materia.clasificacion !== 'intensificar'
          || !materia.seguimiento_id || materia.resuelta;
        const instancia = document.createElement('input');
        instancia.type = 'text';
        instancia.name = 'instancia';
        instancia.maxLength = 80;
        instancia.placeholder = 'Instancia (texto libre)';
        instancia.required = true;
        const nota = document.createElement('input');
        nota.type = 'number';
        nota.name = 'nota';
        nota.min = '1';
        nota.max = '10';
        nota.step = '0.01';
        nota.placeholder = 'Nota';
        const valorativa = document.createElement('select');
        valorativa.name = 'nota_valorativa';
        valorativa.add(new Option('Sin estado', ''));
        ['TEP', 'TEA', 'TED'].forEach(opcion => valorativa.add(new Option(opcion, opcion)));
        const observaciones = document.createElement('input');
        observaciones.type = 'text';
        observaciones.name = 'observaciones';
        observaciones.placeholder = 'Observaciones';
        const guardarIntento = document.createElement('button');
        guardarIntento.type = 'submit';
        guardarIntento.textContent = 'Registrar instancia';
        intentoForm.append(instancia, nota, valorativa, observaciones, guardarIntento);
        intentoForm.addEventListener('submit', async event => {
          event.preventDefault();
          const campos = new FormData(intentoForm);
          await guardarIntensificacion({
            accion: 'registrar_intento',
            alumno_dni: alumno.dni,
            curso_id: cursoId,
            materia_id: materia.materia_id,
            year_origen_id: materia.year_id,
            instancia: campos.get('instancia'),
            nota: campos.get('nota'),
            nota_valorativa: campos.get('nota_valorativa'),
            observaciones: campos.get('observaciones')
          });
        });

        selectorClasificacion.addEventListener('change', () => {
          const intensificables = Array.from(formulario.querySelectorAll('[data-clasificacion]'))
            .filter(selector => selector.value === 'intensificar');
          if (intensificables.length > 5) {
            selectorClasificacion.value = 'sin_clasificar';
            aviso.textContent = 'El máximo permitido es cinco materias por alumno.';
            return;
          }
          actualizarLimiteIntensificaciones();
        });
        fila.append(datos, intentoForm);
        formulario.appendChild(fila);
      });
      actualizarLimiteIntensificaciones();

      const guardarSeleccion = document.createElement('button');
      guardarSeleccion.type = 'button';
      guardarSeleccion.className = 'seguimiento-guardar';
      guardarSeleccion.textContent = 'Guardar clasificación';
      guardarSeleccion.addEventListener('click', async () => {
        const clasificaciones = Array.from(formulario.querySelectorAll('[data-clasificacion]'))
          .filter(selector => selector.dataset.elegible === 'true')
          .map(selector => ({
          materia_id: Number(selector.dataset.materia),
          year_origen_id: Number(selector.dataset.yearOrigen),
          clasificacion: selector.value
          }));
        await guardarIntensificacion({
          accion: 'guardar_clasificacion',
          alumno_dni: alumno.dni,
          curso_id: cursoId,
          clasificaciones
        });
      });
      panelAlumno.append(formulario, aviso, guardarSeleccion);
      listaIntensificaciones.appendChild(panelAlumno);
    });
  } catch (error) {
    console.error(error);
    listaIntensificaciones.replaceChildren(crearTexto('p', error.message, 'text-danger'));
  }
}

async function cargarRecursadas() {
  if (!panelSeguimiento || !listaRecursadas) return;
  const cursoId = selCurso.value;
  listaRecursadas.replaceChildren();
  if (!cursoId) {
    listaRecursadas.appendChild(crearTexto('p', 'Seleccioná un curso para consultar recursadas.'));
    return;
  }

  listaRecursadas.appendChild(crearTexto('p', 'Cargando recursadas...'));
  try {
    const response = await fetch(`api_listar_recursadas.php?curso_id=${encodeURIComponent(cursoId)}`, {credentials: 'same-origin'});
    const result = await response.json();
    if (!result.ok) throw new Error(result.msg || 'No se pudieron cargar las recursadas.');
    if (result.habilitado === false) {
      listaRecursadas.replaceChildren(crearTexto('p', 'El seguimiento de recursadas está habilitado desde segundo año.'));
      return;
    }
    panelSeguimiento.hidden = false;
    listaRecursadas.replaceChildren();
    if (!result.alumnos.length) {
      listaRecursadas.appendChild(crearTexto('p', 'No hay materias clasificadas para recursar en este curso.'));
      return;
    }

    result.alumnos.forEach(alumno => {
      const panelAlumno = document.createElement('div');
      panelAlumno.className = 'seguimiento-alumno';
      panelAlumno.appendChild(crearTexto('h3', `${alumno.nombre} — DNI ${alumno.dni}`));
      const envoltorio = document.createElement('div');
      envoltorio.className = 'recursadas-table-wrap';
      const tabla = document.createElement('table');
      tabla.className = 'recursadas-table';
      const cabecera = document.createElement('thead');
      const filaCabecera = document.createElement('tr');
      ['Materia y origen', 'Curso de recursada', 'Estado', 'Acción'].forEach(titulo => {
        filaCabecera.appendChild(crearTexto('th', titulo));
      });
      cabecera.appendChild(filaCabecera);
      const cuerpo = document.createElement('tbody');

      alumno.materias.forEach(materia => {
        const fila = document.createElement('tr');
        const origen = `${materia.grado || 'Nivel sin identificar'} · Ciclo ${materia.year_origen} · Nota ${materia.nota_origen}`;
        const celdaMateria = crearTexto('td', `${materia.materia}\n${origen}`);
        celdaMateria.style.whiteSpace = 'pre-line';
        fila.appendChild(celdaMateria);
        const destino = materia.curso
          ? `${materia.curso} · ${materia.year_recursada}`
          : 'Pendiente de inscripción';
        fila.appendChild(crearTexto('td', destino));

        const celdaEstado = document.createElement('td');
        const estadoFinal = ['aprobada', 'no_aprobada'].includes(materia.estado);
        if (estadoFinal) {
          const etiqueta = crearTexto('span', materia.estado === 'aprobada' ? 'Aprobada' : 'No aprobada',
            `recursada-badge ${materia.estado === 'aprobada' ? 'aprobada' : 'no-aprobada'}`);
          celdaEstado.appendChild(etiqueta);
        } else {
          const selector = document.createElement('select');
          selector.className = 'recursada-estado';
          selector.add(new Option('Pendiente de inscripción', 'pendiente_inscripcion'));
          selector.add(new Option('Cursando', 'cursando'));
          selector.value = materia.estado_guardado || 'pendiente_inscripcion';
          celdaEstado.appendChild(selector);
          materia.selectorEstado = selector;
        }
        fila.appendChild(celdaEstado);

        const celdaAccion = document.createElement('td');
        if (!estadoFinal) {
          const guardar = document.createElement('button');
          guardar.type = 'button';
          guardar.className = 'recursada-guardar';
          guardar.textContent = 'Guardar';
          guardar.disabled = materia.estado_guardado === (materia.selectorEstado?.value || 'pendiente_inscripcion');
          materia.selectorEstado?.addEventListener('change', () => {
            guardar.disabled = materia.estado_guardado === materia.selectorEstado.value;
          });
          guardar.addEventListener('click', async () => {
            guardar.disabled = true;
            const guardado = await guardarEstadoRecursada({
              alumno_dni: alumno.dni,
              curso_id: Number(cursoId),
              materia_id: materia.materia_id,
              year_origen_id: materia.year_origen_id,
              estado: materia.selectorEstado.value
            });
            if (!guardado) guardar.disabled = false;
          });
          celdaAccion.appendChild(guardar);
        } else {
          celdaAccion.textContent = `Ciclo ${materia.year_recursada || ''}`;
        }
        fila.appendChild(celdaAccion);
        cuerpo.appendChild(fila);
      });

      tabla.append(cabecera, cuerpo);
      envoltorio.appendChild(tabla);
      panelAlumno.appendChild(envoltorio);
      listaRecursadas.appendChild(panelAlumno);
    });
  } catch (error) {
    console.error(error);
    listaRecursadas.replaceChildren(crearTexto('p', error.message, 'text-danger'));
  }
}

async function guardarEstadoRecursada(payload) {
  try {
    const response = await fetch('api_actualizar_recursada.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfIntensificacion},
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!result.ok) throw new Error(result.msg || 'No se pudo guardar el seguimiento.');
    await cargarRecursadas();
    return true;
  } catch (error) {
    console.error(error);
    alert(error.message || 'Error de red al guardar la recursada.');
    return false;
  }
}

async function guardarIntensificacion(payload) {
  try {
    const response = await fetch('api_guardar_intensificacion.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfIntensificacion},
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!result.ok) throw new Error(result.msg || 'No se pudo guardar.');
    if (payload.accion === 'registrar_intento' && String(selMateria.value) === String(payload.materia_id)) {
      await cargarAlumnosNotas();
    }
    await cargarIntensificaciones();
  } catch (error) {
    console.error(error);
    alert(error.message || 'Error de red al guardar la intensificación.');
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  lockUnlockTable(); // aplica estado bloqueado inicial

  if (initialCursoId) {
    selCurso.value = initialCursoId;
    await cargarMaterias(initialCursoId);
    await cargarIntensificaciones();
    if (initialMateriaId) {
      selMateria.value = initialMateriaId;
      await cargarAlumnosNotas();
    }
  }
});
</script>

</body>
</html>
