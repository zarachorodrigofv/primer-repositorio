-- Nuevas funcionalidades Campus S.G.I.
-- No modifica puertos ni configuración de conexión.
-- Ejecutar una sola vez sobre la base `campus`.

ALTER TABLE curso
  ADD COLUMN turno ENUM('mañana','tarde','vespertino') NULL AFTER modalidad_id;

-- Después de agregar la columna, asignar cada curso a su turno real.
-- Ejemplos (NO ejecutar si no corresponden a la institución):
-- UPDATE curso SET turno = 'mañana' WHERE id IN (1,2,3);
-- UPDATE curso SET turno = 'tarde' WHERE id IN (4,5,6);
-- UPDATE curso SET turno = 'vespertino' WHERE id IN (7,8,9);

-- Las materias pendientes de años anteriores no requieren una tabla nueva:
-- se obtienen desde notas_detalle + year_escolar + materias.
