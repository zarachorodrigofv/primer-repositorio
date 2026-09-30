-- Turnos predeterminados y seguimiento de intensificaciones.
-- Ejecutable más de una vez. Hacer respaldo de la base antes de aplicarlo.
SET NAMES utf8mb4;

DELETE aat
FROM asignado_alumno_turno aat
JOIN curso_turno ct ON ct.id = aat.turno_id
WHERE ct.turno = '';

DELETE FROM curso_turno
WHERE turno = '';

INSERT IGNORE INTO curso_turno (curso_id, turno)
SELECT c.id, turnos.turno
FROM curso c
CROSS JOIN (
    SELECT 'mañana' AS turno
    UNION ALL SELECT 'tarde'
    UNION ALL SELECT 'vespertino'
) AS turnos
WHERE c.id > 0;

INSERT IGNORE INTO asignado_alumno_turno (alumno_dni, curso_id, year_escolar_id, turno_id)
SELECT aa.alumno_dni, aa.curso_id, aa.year_escolar_id, ct.id
FROM asignado_alumno aa
JOIN curso_turno ct ON ct.curso_id = aa.curso_id
WHERE aa.estado = 'activo';

CREATE TABLE IF NOT EXISTS alumno_materia_seguimiento (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    alumno_dni INT UNSIGNED NOT NULL,
    materia_id INT UNSIGNED NOT NULL,
    year_origen_id INT NOT NULL,
    year_seguimiento_id INT NOT NULL,
    clasificacion ENUM('intensificar', 'recursar') NOT NULL DEFAULT 'recursar',
    asignado_por INT UNSIGNED DEFAULT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seguimiento_alumno_materia_ciclos (alumno_dni, materia_id, year_origen_id, year_seguimiento_id),
    KEY idx_seguimiento_alumno_ciclo (alumno_dni, year_seguimiento_id),
    CONSTRAINT fk_seguimiento_alumno FOREIGN KEY (alumno_dni) REFERENCES usuarios (dni) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seguimiento_materia FOREIGN KEY (materia_id) REFERENCES materias (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seguimiento_year_origen FOREIGN KEY (year_origen_id) REFERENCES year_escolar (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seguimiento_year_actual FOREIGN KEY (year_seguimiento_id) REFERENCES year_escolar (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seguimiento_asignador FOREIGN KEY (asignado_por) REFERENCES usuarios (dni) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alumno_materia_intensificacion (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seguimiento_id INT UNSIGNED NOT NULL,
    instancia ENUM('diciembre', 'febrero', 'marzo') NOT NULL,
    estado ENUM('presentada', 'aprobada', 'no_aprobada') NOT NULL DEFAULT 'presentada',
    nota DECIMAL(4,2) DEFAULT NULL,
    nota_valorativa ENUM('TEP', 'TEA', 'TED') DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    registrado_por INT UNSIGNED DEFAULT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_intensificacion_instancia (seguimiento_id, instancia),
    CONSTRAINT fk_intensificacion_seguimiento FOREIGN KEY (seguimiento_id) REFERENCES alumno_materia_seguimiento (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intensificacion_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios (dni) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE alumno_materia_seguimiento
    MODIFY clasificacion ENUM('sin_clasificar', 'intensificar', 'recursar') NOT NULL DEFAULT 'sin_clasificar';

ALTER TABLE alumno_materia_intensificacion
    MODIFY instancia VARCHAR(80) NOT NULL;

UPDATE alumno_materia_seguimiento s
SET s.clasificacion = 'sin_clasificar'
WHERE s.clasificacion = 'recursar'
    AND NOT EXISTS (
            SELECT 1
            FROM alumno_materia_intensificacion i
            WHERE i.seguimiento_id = s.id
    );
