Estos son exports del hosting infinityfree<br>
usar el NODATA para tener una base sin ningun dato (los datos que contienen son todos de prueba)

Para bases que ya tienen el seguimiento de intensificaciones, aplicar `sql-schema/migracion_seguimiento_recursadas.sql` después de `sql-schema/migracion_turnos_intensificaciones.sql`.

Al instalar `SCHEMASQL-EXPORT-CAMPUS-SGI-NODATA.sql`, ejecutar primero `sql-schema/migracion_turnos_intensificaciones.sql` y después `sql-schema/migracion_seguimiento_recursadas.sql`.

