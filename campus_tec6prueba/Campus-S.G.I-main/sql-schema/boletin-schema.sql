-- ==================TABLAS PRINCIPALES================================
CREATE TABLE year_escolar( 
  id INT PRIMARY KEY AUTO_INCREMENT,
  year INT UNIQUE NOT NULL
) ENGINE=InnoDB;

CREATE TABLE curso_year(
  id INT PRIMARY KEY AUTO_INCREMENT,
  year VARCHAR(20) NOT NULL COMMENT 'ej: "1ro", "2da", etc'
)ENGINE=InnoDB;

CREATE TABLE curso_division(
  id INT PRIMARY KEY AUTO_INCREMENT,
  division VARCHAR(20) NOT NULL COMMENT 'ej: 7ma, 6to, A, etc'
) ENGINE=InnoDB;

CREATE TABLE modalidad(
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL COMMENT 'ej: informatica'
) ENGINE=InnoDB;

-- ==== COMBINACION DE CURSO_YEAR Y CURSO_DIVISION = CURSO ====
CREATE TABLE curso(
  id INT PRIMARY KEY AUTO_INCREMENT,
  curso_year_id INT NOT NULL,
  curso_division_id INT NOT NULL,
  modalidad_id INT COMMENT 'NULL = Ciclo basico/ninguna modalidad',
  FOREIGN KEY (curso_year_id) REFERENCES curso_year(id),
  FOREIGN KEY (curso_division_id) REFERENCES curso_division(id),
  FOREIGN KEY (modalidad_id) REFERENCES modalidad(id),
  UNIQUE(curso_year_id, curso_division_id, modalidad_id)
) ENGINE=InnoDB COMMENT 'CURSO_YEAR + CURSO_DIVISION = CURSO';

CREATE TABLE materias(
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL COMMENT 'ej: matematicas, fisica, etc'
) ENGINE=InnoDB;

-- ==============================================
-- materias por curso_year ej: 3ro ya sabemos las materias que tienen. 
-- para no duplicar ej: 4 terceros con las mismas materias si ya sabemos las materias que tienen todo ciclo basico.
CREATE TABLE materias_year( 
  id INT PRIMARY KEY AUTO_INCREMENT,
  materias_id INT NOT NULL,
  curso_year_id INT NOT NULL,
  modalidad_id INT COMMENT 'NULL = CICLO BASICO',
  year_escolar_id INT NOT NULL COMMENT 'año escolar ej: 2025, 2026',
  FOREIGN KEY (materias_id) REFERENCES materias(id),
  FOREIGN KEY (curso_year_id) REFERENCES curso_year(id),
  FOREIGN KEY (modalidad_id) REFERENCES modalidad(id),
  FOREIGN KEY (year_escolar_id) REFERENCES year_escolar(id),
  UNIQUE(materias_id, curso_year_id, modalidad_id, year_escolar_id)
) ENGINE=InnoDB;

-- ====== ASIGNACION A USUARIOS DEPENDIENDO DE SU ROL ======

-- ====== 1. PROFESORES ======
-- ASIGNACION DE PROFESORES a materias (profesor dicta X materia para X grado en X año escolar)
CREATE TABLE asignado_profesor(
  id INT PRIMARY KEY AUTO_INCREMENT,
  maestro_dni VARCHAR(20) NOT NULL,
  materias_year_id INT NOT NULL,
  FOREIGN KEY (maestro_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (materias_year_id) REFERENCES materias_year(id),
  UNIQUE(maestro_dni, materias_year_id)
) ENGINE=InnoDB;

-- ====== 2. Preceptor ======
CREATE TABLE asignado_preceptor(
  id INT PRIMARY KEY AUTO_INCREMENT,
  preceptor_dni VARCHAR(20) NOT NULL,
  curso_id INT NOT NULL,
  year_escolar_id INT NOT NULL,
  FOREIGN KEY (preceptor_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (curso_id) REFERENCES curso(id),
  FOREIGN KEY (year_escolar_id) REFERENCES year_escolar(id)
) ENGINE=InnoDB;

-- ====== 3. ALUMNO ======
CREATE TABLE asignado_alumno(
  id INT PRIMARY KEY AUTO_INCREMENT,
  alumno_dni VARCHAR(20) NOT NULL,
  curso_id INT NOT NULL,
  year_escolar_id INT NOT NULL,
  estado ENUM('activo', 'repetidor', 'egresado', 'retirado', 'cambio_curso', 'desconocido') DEFAULT 'activo',
  fecha_inscripcion DATE,
  fecha_baja DATE,
  motivo_baja TEXT,
  FOREIGN KEY (alumno_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (curso_id) REFERENCES curso(id),
  FOREIGN KEY (year_escolar_id) REFERENCES year_escolar(id),
  UNIQUE(alumno_dni,curso_id, year_escolar_id)
) ENGINE=InnoDB;

-- ====== 4. FAMILIA ======
CREATE TABLE asignado_familia(
  id INT PRIMARY KEY AUTO_INCREMENT,
  familia_dni VARCHAR(20)  NOT NULL,
  alumno_dni VARCHAR(20) NOT NULL,
  curso_id INT NOT NULL,
  relacion ENUM('madre', 'padre', 'tutor', 'abuelo', 'hermano', 'tio', 'otro') NOT NULL,
  FOREIGN KEY (familia_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (alumno_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (curso_id) REFERENCES curso(id),
  UNIQUE(familia_dni, alumno_dni)
) ENGINE=InnoDB;


-- ==========NOTAS DEL BOLETIN===========
CREATE TABLE boletin_notas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  alumno_nombre VARCHAR(100) NOT NULL,
  alumno_dni VARCHAR(20) NOT NULL,
  asignado_profesor_id INT NOT NULL,
  nota_c1 DECIMAL(4,2) CHECK (nota_c1 BETWEEN 1 AND 10) COMMENT 'Primer cuatrimestre',
  valoracion_pedagogica_c1 ENUM('TEP','TEA','TED') COMMENT 'Primer cuatrimestre',
  intensificacion_c1 ENUM('TEP','TEA','TED') COMMENT 'Primer cuatrimestre',
  nota_c2 DECIMAL(4,2) CHECK (nota_c2 BETWEEN 1 AND 10) COMMENT 'Segundo cuatrimestre',
  valoracion_pedagogica_c2 ENUM('TEP','TEA','TED') COMMENT 'Segundo cuatrimestre',
  nota_final DECIMAL(4,2) CHECK (nota_final BETWEEN 1 AND 10) COMMENT 'Cierre anual',
  intensificacion_dic ENUM('TEP','TEA','TED') COMMENT 'Recuperatio',
  intensificacion_feb ENUM('TEP','TEA','TED') COMMENT 'Recuperatio',
  ampliatoria_mar ENUM('TEP','TEA','TED') COMMENT 'Recuperatio',
  informe_final TEXT,
  fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (alumno_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (alumno_nombre) REFERENCES usuarios(nombre),
  FOREIGN KEY (asignado_profesor_id) REFERENCES asignado_profesor(id),
  UNIQUE(alumno_dni, asignado_profesor_id)
) ENGINE=InnoDB;
