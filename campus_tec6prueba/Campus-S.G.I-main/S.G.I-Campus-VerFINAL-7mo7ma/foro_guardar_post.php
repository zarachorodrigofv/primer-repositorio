<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/config.php';
require __DIR__.'/auth.php';

requireLogin();
requireCsrf();

$pdo      = db();
$user_dni = (int)($_SESSION['dni'] ?? 0);
$rol      = $_SESSION['rol'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

// Solo estos roles pueden publicar
if (!in_array($rol, ROLES_FORO, true)) {
    http_response_code(403);
    echo "No autorizado";
    exit;
}

/* ===========================
   DATOS BÁSICOS DEL POST
   =========================== */
$titulo        = trim($_POST['titulo'] ?? '');
$contenido     = trim($_POST['contenido'] ?? '');
$destino_tipo  = trim($_POST['destino_tipo'] ?? 'general');
$destino_valor = trim($_POST['destino_valor'] ?? '');

if ($titulo === '' || $contenido === '') {
    echo "Falta título o contenido";
    exit;
}

// Normalizar destino_tipo
$tiposPermitidos = ['general','anio','curso','rol'];
if (!in_array($destino_tipo, $tiposPermitidos, true)) {
    $destino_tipo = 'general';
    $destino_valor = '';
}

// El destino recibido es un dato no confiable. Un preceptor solo puede usar
// general o los cursos/aÃ±os derivados de sus asignaciones vigentes.
if ($rol === 'preceptor' && $destino_tipo !== 'general') {
    $yearId = currentYearId($pdo);
    $permitido = false;
    if ($destino_tipo === 'curso') {
        $permitido = ctype_digit($destino_valor) && preceptorTieneCurso($pdo, $user_dni, (int)$destino_valor, $yearId);
    } elseif ($destino_tipo === 'anio') {
        $st = $pdo->prepare('SELECT 1 FROM preceptor_curso pc JOIN curso c ON c.id=pc.curso_id JOIN curso_year cy ON cy.id=c.curso_year_id WHERE pc.preceptor_dni=? AND pc.year_escolar_id=? AND cy.year=? LIMIT 1');
        $st->execute([$user_dni, $yearId, $destino_valor]);
        $permitido = (bool)$st->fetchColumn();
    }
    if (!$permitido) {
        http_response_code(403);
        echo 'Destino no autorizado';
        exit;
    }
}

// ===========================
// Manejo del archivo adjunto
// ===========================
$rutaArchivo    = '';
$nombreOriginal = '';

// extensiones permitidas
$extPermitidas = [
    'jpg','jpeg','png','gif','webp',
    'pdf','doc','docx','xls','xlsx','ppt','pptx'
];

// 👇 clave "archivo" para coincidir con formData.append("archivo", archivo)
if (!empty($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {

    $f = $_FILES['archivo'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        echo "Error al subir archivo (código {$f['error']})";
        exit;
    }

    // Máx 10 MB
    $maxBytes = 10 * 1024 * 1024;
    if ($f['size'] > $maxBytes) {
        echo "El archivo es muy pesado (máx 10 MB)";
        exit;
    }

    $nombreOriginal = $f['name'];
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if ($ext && !in_array($ext, $extPermitidas, true)) {
        echo "Tipo de archivo no permitido (.$ext)";
        exit;
    }

    $dirDestino = __DIR__ . '/uploads_foro';
    if (!is_dir($dirDestino)) {
        if (!mkdir($dirDestino, 0775, true)) {
            echo "No se pudo crear la carpeta de subida";
            exit;
        }
    }

    $nombreSeguro = time() . '_' . bin2hex(random_bytes(4));
    if ($ext) {
        $nombreSeguro .= '.' . $ext;
    }

    $rutaFisica  = $dirDestino . '/' . $nombreSeguro;
    $rutaPublica = 'uploads_foro/' . $nombreSeguro;

    if (!move_uploaded_file($f['tmp_name'], $rutaFisica)) {
        echo "No se pudo mover el archivo subido";
        exit;
    }

    $rutaArchivo = $rutaPublica;
}


/* ===========================
   Detectar si existe la columna
   archivo_nombre_original
   =========================== */
$hasNombreOriginal = false;
try {
    $st = $pdo->query("SHOW COLUMNS FROM foro LIKE 'archivo_nombre_original'");
    if ($st->fetch()) {
        $hasNombreOriginal = true;
    }
} catch (Throwable $e) {
    // si falla el SHOW COLUMNS, ignoramos y asumimos que no existe
    $hasNombreOriginal = false;
}

/* ===========================
   INSERT EN LA TABLA foro
   =========================== */
try {
    if ($hasNombreOriginal) {
        // versión con columna archivo_nombre_original
        $sql = "INSERT INTO foro
                (autor_dni, titulo, contenido, imagen, archivo_nombre_original,
                 fecha, destino_tipo, destino_valor, editado)
                VALUES
                (:autor, :tit, :cont, :img, :nom_orig,
                 NOW(), :dtipo, :dval, 0)";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':autor'    => $user_dni,
            ':tit'      => $titulo,
            ':cont'     => $contenido,
            ':img'      => $rutaArchivo,
            ':nom_orig' => $nombreOriginal,
            ':dtipo'    => $destino_tipo,
            ':dval'     => $destino_valor,
        ]);
    } else {
        // versión sin archivo_nombre_original (compatibilidad)
        $sql = "INSERT INTO foro
                (autor_dni, titulo, contenido, imagen,
                 fecha, destino_tipo, destino_valor, editado)
                VALUES
                (:autor, :tit, :cont, :img,
                 NOW(), :dtipo, :dval, 0)";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':autor' => $user_dni,
            ':tit'   => $titulo,
            ':cont'  => $contenido,
            ':img'   => $rutaArchivo,
            ':dtipo' => $destino_tipo,
            ':dval'  => $destino_valor,
        ]);
    }

    echo "ok";
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "Error SQL: " . $e->getMessage();
    exit;
}
