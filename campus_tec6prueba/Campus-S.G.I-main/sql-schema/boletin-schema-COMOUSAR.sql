-- === USO ===

INSERT INTO year_escolar (year) VALUES (2025), (2026);


INSERT INTO curso_year (year) VALUES ('1ro'), ('2do'), ('3ro'), ('4to'), ('5to'), ('6to'), ('7mo');

INSERT INTO curso_division (division) VALUES ('6ta'), ('10ma'), ('7ma');

-- == CURSOS ==
-- 1ro 6ta
INSERT INTO curso (curso_year_id, curso_division_id) VALUES (1, 1);
-- 2do 10ma
INSERT INTO curso (curso_year_id, curso_division_id) VALUES (1, 2);
-- 7mo 7ma
INSERT INTO curso (curso_year_id, curso_division_id) VALUES (7, 3);

-- MATERIAS
INSERT INTO materias (nombre) VALUES ('Matematicas'), ('Fisica'), ('Historia');

-- MODALIDADES
INSERT INTO modalidad (nombre) VALUES ('Informatica');

-- MATERIAS PARA LOS AÑOS ESCOLARES
INSERT INTO materias_year (materias_id, curso_id, curso_year_id, modalidad_id, year_escolar_id) 
VALUES 
(1, 3, NULL, 1), -- matematicas, 1ro, ciclo basico, 2025
(2, 2, 1, 1);    -- Fisica, 2do, modalidad informatica, 2025

-- ==== ASGINADOS ==== :VVVVVVVVVV

INSERT INTO asignado_maestro (maestro_dni, materias_year_id)
VALUES (44555666, 1); -- se le asgina matematicas de 1ro 6ta ciclo basico 2025

-- 
INSERT INTO asignado_familia (familia_dni, alumno_dni, curso_id, relacion)
VALUES (66777888, 11222333, 1, 'abuela')


INSERT INTO boletin_asignado_preceptor (preceptor_dni, curso_id, year_escolar_id)
VALUES (69067727, 1, 1);

INSERT INTO boletin_notas (alumno_dni, asignado_maestro_id, valoracion_pedagogica_c1)
VALUES (11222333, 44555666, 'TEA');


