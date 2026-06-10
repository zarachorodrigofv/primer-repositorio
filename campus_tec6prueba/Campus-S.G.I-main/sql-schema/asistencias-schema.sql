-- ================== ASISTENCIAS ====================================
CREATE TABLE asistencia(
  id INT PRIMARY KEY AUTO_INCREMENT,
  alumno_dni INT NOT NULL,
  curso_id INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('presente', 'ausente', 'tarde', 'justificado', 'retiro') NOT NULL,
  motivo_justificado TEXT DEFAULT NULL COMMENT 'Motivo cuando estado = justificado',
  hora_1 ENUM('presente', 'ausente', 'justificado', 'tarde', 'retiro') NOT NULL,
  hora_2 ENUM('presente', 'ausente', 'justificado', 'tarde', 'retiro') NOT NULL,
  hora_3 ENUM('presente', 'ausente', 'justificado', 'tarde', 'retiro') NOT NULL,
  observaciones TEXT,
  registrado_por INT COMMENT 'dni precepto', 
  retirado_por INT COMMENT 'dni familiar',
  FOREIGN KEY (alumno_dni) REFERENCES usuarios(dni),
  FOREIGN KEY (curso_id) REFERENCES curso(id),
  FOREIGN KEY (registrado_por) REFERENCES usuarios(dni),
  FOREIGN KEY (retirado_por) REFERENCES usuarios(dni)
) ENGINE=InnoDB;
