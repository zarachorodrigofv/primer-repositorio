<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/*
 * POLÍTICA CENTRAL DE PERMISOS
 * root/admin/directivo: acceso institucional completo.
 * Los demás roles tienen únicamente las secciones indicadas abajo.
 */
const ROLES_FULL = ['root','admin','directivo'];
const ROLES_ASIGNAR_PROFES = ['root','admin','directivo','jefe_area','jefe_taller','jefe_departamento'];
const ROLES_PANEL = ['root','admin','directivo','jefe_preceptores','jefe_area','jefe_taller','jefe_departamento', 'preceptor'];
const ROLES_ASISTENCIA = ['root','admin','directivo','preceptor'];
const ROLES_LISTADO = ['root','admin','directivo','preceptor'];
const ROLES_MENSAJERIA = ['root','admin','directivo','preceptor'];
const ROLES_FORO = ['root','admin','directivo','preceptor'];
const ROLES_MATERIAS = ['root','admin','directivo','profesor','preceptor'];
const ROLES_NOTAS = ['root','admin','directivo','profesor','preceptor'];
const ROLES_INFO = ['root','admin','directivo'];
const ROLES_CONTACTOS = ['root','admin','directivo'];

/* Capacidades de escritura. Las vistas pueden ocultar controles, pero estas
 * capacidades son la fuente de verdad para páginas y APIs. */
const CAP_GESTION_TOTAL = ['root','admin','directivo'];
const CAP_ASIGNAR_DOCENTES = ['root','admin','directivo','jefe_area','jefe_taller','jefe_departamento'];
const CAP_GESTION_PRECEPTORES = ['root','admin','directivo','jefe_preceptores'];
const CAP_GESTION_ALUMNOS = ['root','admin','directivo','preceptor'];
const CAP_NOTAS = ['root','admin','directivo','preceptor','profesor'];

function currentRole(): string {
    return strtolower(trim($_SESSION['rol'] ?? ''));
}

function hasRole(string ...$roles): bool {
    return in_array(currentRole(), $roles, true);
}

function isFullRole(): bool {
    return in_array(currentRole(), ROLES_FULL, true);
}

function requireLogin(): void {
    if (empty($_SESSION['dni']) || empty($_SESSION['rol'])) {
        header('Location: index.html?login=error&msg=' . urlencode('Debés iniciar sesión para continuar.'));
        exit;
    }

    if (!empty($_SESSION['must_change_password'])
        && !in_array(basename($_SERVER['PHP_SELF']), ['changepassword.html','changepass.php'])) {
        header('Location: changepassword.html');
        exit;
    }
}

/** 
 * Bloqueo centralizado de páginas. Nunca devuelve una página en blanco:
 * vuelve al campus con un aviso.
 */
function requireAnyRole(array $roles, string $mensaje = 'No tenés permisos para acceder a esta sección.'): void {
    requireLogin();

    if (!in_array(currentRole(), $roles, true)) {
        header('Location: SGI.php?acceso=denegado&msg=' . urlencode($mensaje));
        exit;
    }
}

function requirePage(string $page): void {
    $map = [
        'asistencia' => ROLES_ASISTENCIA,
        'lista'      => ROLES_LISTADO,
        'mensajeria' => ROLES_MENSAJERIA,
        'foro'       => ROLES_FORO,
        'materias'   => ROLES_MATERIAS,
        'notas'      => ROLES_NOTAS,
        'info'       => ROLES_INFO,
        'contactos'  => ROLES_CONTACTOS,
        'panel'      => ROLES_PANEL,
    ];

    requireAnyRole(
        $map[$page] ?? [],
        'Tu rol no tiene permisos para acceder a esta sección.'
    );
}

function requireCapability(array $roles, string $mensaje = 'No tenÃ©s permisos para realizar esta acciÃ³n.'): void {
    requireAnyRole($roles, $mensaje);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Solicitud invÃ¡lida. RecargÃ¡ la pÃ¡gina e intentÃ¡ nuevamente.']);
        exit;
    }
}

function currentYearId(PDO $pdo): int {
    return (int)($pdo->query("SELECT id FROM year_escolar ORDER BY `year` DESC LIMIT 1")->fetchColumn() ?: 0);
}

function preceptorTieneCurso(PDO $pdo, int $preceptorDni, int $cursoId, int $yearId): bool {
    $st = $pdo->prepare('SELECT 1 FROM preceptor_curso WHERE preceptor_dni=? AND curso_id=? AND year_escolar_id=? LIMIT 1');
    $st->execute([$preceptorDni, $cursoId, $yearId]);
    return (bool)$st->fetchColumn();
}

function alumnoEstaEnCurso(PDO $pdo, int $alumnoDni, int $cursoId, int $yearId): bool {
    $st = $pdo->prepare("SELECT 1 FROM asignado_alumno WHERE alumno_dni=? AND curso_id=? AND year_escolar_id=? AND estado='activo' LIMIT 1");
    $st->execute([$alumnoDni, $cursoId, $yearId]);
    return (bool)$st->fetchColumn();
}

function preceptorTieneAlumno(PDO $pdo, int $preceptorDni, int $alumnoDni, int $yearId, ?int $cursoId = null): bool {
    $sql = "SELECT 1 FROM asignado_alumno aa JOIN preceptor_curso pc ON pc.curso_id=aa.curso_id AND pc.year_escolar_id=aa.year_escolar_id WHERE aa.alumno_dni=? AND aa.year_escolar_id=? AND aa.estado='activo' AND pc.preceptor_dni=?";
    $params = [$alumnoDni, $yearId, $preceptorDni];
    if ($cursoId !== null) { $sql .= ' AND aa.curso_id=?'; $params[] = $cursoId; }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql); $st->execute($params);
    return (bool)$st->fetchColumn();
}

function profesorTieneMateriaCurso(PDO $pdo, int $profesorDni, int $materiaId, int $cursoId, int $yearId): bool {
    $st = $pdo->prepare('SELECT 1 FROM docente_materia_curso dmc JOIN curso_materia cm ON cm.id=dmc.curso_materia_id WHERE dmc.maestro_dni=? AND cm.materia_id=? AND cm.curso_id=? AND cm.year_escolar_id=? LIMIT 1');
    $st->execute([$profesorDni, $materiaId, $cursoId, $yearId]);
    return (bool)$st->fetchColumn();
}

function nivelRol(string $rol = ''): int {
    $niveles = [
        'familia'=>1, 'profesor'=>2, 'preceptor'=>3,
        'jefe_departamento'=>4, 'jefe_taller'=>4, 'jefe_area'=>4,
        'jefe_preceptores'=>5, 'directivo'=>6, 'admin'=>7, 'root'=>8
    ];
    return $niveles[$rol ?: currentRole()] ?? 0;
}

function puedeAsignarProfes(): bool {
    return in_array(currentRole(), ROLES_ASIGNAR_PROFES, true);
}
function tieneAccesoPanel(): bool { return in_array(currentRole(), ROLES_PANEL, true); }
function tieneAccesoAsistencia(): bool { return in_array(currentRole(), ROLES_ASISTENCIA, true); }
function tieneAccesoListado(): bool { return in_array(currentRole(), ROLES_LISTADO, true); }
function tieneAccesoMensajeria(): bool { return in_array(currentRole(), ROLES_MENSAJERIA, true); }
function tieneAccesoForo(): bool { return in_array(currentRole(), ROLES_FORO, true); }

function esRoot(): bool { return currentRole() === 'root'; }
function esAdmin(): bool { return in_array(currentRole(), ['admin','root'], true); }
function esDirectivo(): bool { return in_array(currentRole(), ['directivo','admin','root'], true); }
function esJefeArea(): bool { return in_array(currentRole(), ['jefe_area','jefe_taller','jefe_departamento'], true); }
function esJefePreceptores(): bool { return currentRole() === 'jefe_preceptores'; }
function esFamilia(): bool { return currentRole() === 'familia'; }
function esProfesor(): bool { return currentRole() === 'profesor'; }
function esPreceptor(): bool { return currentRole() === 'preceptor'; }
