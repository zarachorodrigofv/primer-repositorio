-- Aplicar después de migracion_turnos_intensificaciones.sql.
ALTER TABLE alumno_materia_seguimiento
    ADD COLUMN estado_recursada ENUM('pendiente_inscripcion', 'cursando') NOT NULL DEFAULT 'pendiente_inscripcion' AFTER clasificacion,
    ADD COLUMN curso_recursada_id INT(11) DEFAULT NULL AFTER estado_recursada,
    ADD COLUMN year_recursada_id INT(11) DEFAULT NULL AFTER curso_recursada_id,
    ADD COLUMN recursada_actualizado_por INT(10) UNSIGNED DEFAULT NULL AFTER year_recursada_id,
    ADD KEY idx_seguimiento_recursada_curso (curso_recursada_id, year_recursada_id);