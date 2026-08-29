<?php
// El registro público fue eliminado.
// Alumnos y familias solo pueden ser agregados por preceptor o superior.
header("Location: index.html?login=error&msg=" . urlencode("El registro público está deshabilitado. Contactá al preceptor o directivo de tu escuela."));
exit;
