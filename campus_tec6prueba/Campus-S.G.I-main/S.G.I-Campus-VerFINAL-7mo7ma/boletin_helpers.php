<?php
/**
 * Fuente única para boletines académicos.
 * La lógicaoffline se centraliza aquí para evitar que distintas pantallas
 * calculen boletines con consultas distintas.
 */
function obtenerBoletinAlumno(PDO $pdo, int $alumnoDni, int $yearId): array {
    $stmt = $pdo->prepare(
        "SELECT m.nombre AS materia,
                MAX(CASE WHEN nd.cuatrimestre='1' THEN nd.nota_valorativa END) AS c1_val,
                MAX(CASE WHEN nd.cuatrimestre='1' THEN nd.nota_numerica END) AS c1_num,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_valorativa END) AS c2_val,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_numerica END) AS c2_num,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.nota_final END) AS nota_final,
                MAX(CASE WHEN nd.cuatrimestre='2' THEN nd.observaciones END) AS observaciones
         FROM notas_detalle nd
         JOIN materias m ON m.id = nd.materia_id
         WHERE nd.alumno_dni = :alumno_dni
           AND nd.year_escolar_id = :year_id
         GROUP BY nd.materia_id, m.nombre
         ORDER BY m.nombre"
    );
    $stmt->execute([
        ':alumno_dni' => $alumnoDni,
        ':year_id'    => $yearId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
