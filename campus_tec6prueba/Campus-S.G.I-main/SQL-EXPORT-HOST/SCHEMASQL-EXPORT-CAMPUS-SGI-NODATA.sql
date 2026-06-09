-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql103.infinityfree.com
-- Generation Time: Dec 23, 2025 at 12:54 PM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_39451587_campus`
--

-- --------------------------------------------------------

--
-- Table structure for table `alumnos`
--

CREATE TABLE `alumnos` (
  `alumno_dni` int(10) UNSIGNED NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ausente` int(11) DEFAULT 0,
  `presente` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asignado_alumno`
--

CREATE TABLE `asignado_alumno` (
  `id` int(10) UNSIGNED NOT NULL,
  `alumno_dni` int(10) UNSIGNED NOT NULL,
  `curso_id` int(11) NOT NULL,
  `year_escolar_id` int(11) NOT NULL,
  `estado` enum('activo','baja') DEFAULT 'activo',
  `fecha_inscripcion` date DEFAULT curdate(),
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asignado_profesor`
--

CREATE TABLE `asignado_profesor` (
  `id` int(10) UNSIGNED NOT NULL,
  `maestro_dni` int(10) UNSIGNED NOT NULL,
  `materias_year_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia`
--

CREATE TABLE `asistencia` (
  `id` int(11) NOT NULL,
  `alumno_dni` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('presente','ausente') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curso`
--

CREATE TABLE `curso` (
  `id` int(11) NOT NULL,
  `curso_year_id` int(11) NOT NULL,
  `curso_division_id` int(11) NOT NULL,
  `modalidad_id` int(11) DEFAULT NULL COMMENT 'NULL = Ciclo basico/ninguna modalidad'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CURSO_YEAR + CURSO_DIVISION = CURSO';

-- --------------------------------------------------------

--
-- Table structure for table `curso_division`
--

CREATE TABLE `curso_division` (
  `id` int(11) NOT NULL,
  `division` varchar(20) NOT NULL COMMENT 'ej: 7ma, 6to, A, etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curso_materia`
--

CREATE TABLE `curso_materia` (
  `id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(11) NOT NULL,
  `materia_id` int(10) UNSIGNED NOT NULL,
  `year_escolar_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curso_year`
--

CREATE TABLE `curso_year` (
  `id` int(11) NOT NULL,
  `year` varchar(20) NOT NULL COMMENT 'ej: "1ro", "2da", etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `docente_materia_curso`
--

CREATE TABLE `docente_materia_curso` (
  `id` int(10) UNSIGNED NOT NULL,
  `maestro_dni` int(10) UNSIGNED NOT NULL,
  `curso_materia_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `familia_alumno`
--

CREATE TABLE `familia_alumno` (
  `id` int(10) UNSIGNED NOT NULL,
  `familia_dni` int(10) UNSIGNED NOT NULL,
  `alumno_dni` int(10) UNSIGNED NOT NULL,
  `parentesco` varchar(50) DEFAULT NULL,
  `contacto_prioritario` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `foro`
--

CREATE TABLE `foro` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_edicion` datetime DEFAULT NULL,
  `editado` tinyint(1) NOT NULL DEFAULT 0,
  `autor_dni` varchar(50) NOT NULL,
  `destino_tipo` enum('general','curso','anio','rol') NOT NULL DEFAULT 'general',
  `destino_valor` varchar(50) DEFAULT NULL,
  `archivo_nombre_original` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materias`
--

CREATE TABLE `materias` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL COMMENT 'ej: matematicas, fisica, etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materias_year`
--

CREATE TABLE `materias_year` (
  `id` int(10) UNSIGNED NOT NULL,
  `materia_id` int(10) UNSIGNED NOT NULL,
  `materias_id` int(10) UNSIGNED NOT NULL,
  `curso_year_id` int(11) NOT NULL,
  `modalidad_id` int(11) DEFAULT NULL COMMENT 'NULL = CICLO BASICO',
  `year_escolar_id` int(11) NOT NULL COMMENT 'año escolar ej: 2025, 2026'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mensajes`
--

CREATE TABLE `mensajes` (
  `id` int(11) NOT NULL,
  `remitente` varchar(20) NOT NULL,
  `destinatario` varchar(20) NOT NULL,
  `mensaje` mediumtext NOT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modalidad`
--

CREATE TABLE `modalidad` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL COMMENT 'ej: informatica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notas`
--

CREATE TABLE `notas` (
  `id` int(10) UNSIGNED NOT NULL,
  `alumno_dni` int(10) UNSIGNED NOT NULL,
  `year_escolar_id` int(10) UNSIGNED NOT NULL,
  `vp_c1` varchar(10) DEFAULT NULL,
  `int_c1` varchar(10) DEFAULT NULL,
  `vp_c2` varchar(10) DEFAULT NULL,
  `cierre_anual` decimal(4,2) DEFAULT NULL,
  `int_dic` varchar(10) DEFAULT NULL,
  `int_feb` varchar(10) DEFAULT NULL,
  `amp_mar` varchar(10) DEFAULT NULL,
  `informe_final` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notas_detalle`
--

CREATE TABLE `notas_detalle` (
  `id` int(10) UNSIGNED NOT NULL,
  `alumno_dni` int(10) UNSIGNED NOT NULL,
  `materia_id` int(10) UNSIGNED NOT NULL,
  `year_escolar_id` int(11) NOT NULL,
  `cuatrimestre` enum('1','2','F') NOT NULL,
  `nota_valorativa` enum('TEP','TEA','TED') DEFAULT NULL,
  `nota_numerica` decimal(4,2) DEFAULT NULL,
  `nota_concepto` decimal(4,2) DEFAULT NULL,
  `nota_tp` decimal(4,2) DEFAULT NULL,
  `nota_examen` decimal(4,2) DEFAULT NULL,
  `nota_final` decimal(5,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `preceptor_curso`
--

CREATE TABLE `preceptor_curso` (
  `id` int(10) UNSIGNED NOT NULL,
  `preceptor_dni` int(10) UNSIGNED NOT NULL,
  `curso_id` int(11) NOT NULL,
  `year_escolar_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `dni` int(10) UNSIGNED NOT NULL,
  `password` varchar(100) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `rol` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `year_escolar`
--

CREATE TABLE `year_escolar` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`alumno_dni`);

--
-- Indexes for table `asignado_alumno`
--
ALTER TABLE `asignado_alumno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alumno_curso` (`alumno_dni`,`curso_id`,`year_escolar_id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `year_escolar_id` (`year_escolar_id`);

--
-- Indexes for table `asignado_profesor`
--
ALTER TABLE `asignado_profesor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `maestro_dni` (`maestro_dni`,`materias_year_id`),
  ADD KEY `fk_ap_my` (`materias_year_id`);

--
-- Indexes for table `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_asistencia_alumno_fecha` (`alumno_dni`,`fecha`);

--
-- Indexes for table `curso`
--
ALTER TABLE `curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `curso_year_id` (`curso_year_id`,`curso_division_id`,`modalidad_id`),
  ADD KEY `curso_division_id` (`curso_division_id`),
  ADD KEY `modalidad_id` (`modalidad_id`);

--
-- Indexes for table `curso_division`
--
ALTER TABLE `curso_division`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `curso_materia`
--
ALTER TABLE `curso_materia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_curso_materia` (`curso_id`,`materia_id`,`year_escolar_id`),
  ADD KEY `idx_cm_curso` (`curso_id`),
  ADD KEY `idx_cm_materia` (`materia_id`),
  ADD KEY `idx_cm_year` (`year_escolar_id`);

--
-- Indexes for table `curso_year`
--
ALTER TABLE `curso_year`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `docente_materia_curso`
--
ALTER TABLE `docente_materia_curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_docente_cm` (`maestro_dni`,`curso_materia_id`),
  ADD KEY `idx_dmc_cm` (`curso_materia_id`);

--
-- Indexes for table `familia_alumno`
--
ALTER TABLE `familia_alumno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_familia_alumno` (`familia_dni`,`alumno_dni`),
  ADD KEY `fk_fa_alumno` (`alumno_dni`);

--
-- Indexes for table `foro`
--
ALTER TABLE `foro`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materias_year`
--
ALTER TABLE `materias_year`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `materias_id` (`materias_id`,`curso_year_id`,`modalidad_id`,`year_escolar_id`),
  ADD KEY `curso_year_id` (`curso_year_id`),
  ADD KEY `year_escolar_id` (`year_escolar_id`),
  ADD KEY `fk_my_modalidad` (`modalidad_id`);

--
-- Indexes for table `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `remitente` (`remitente`),
  ADD KEY `destinatario` (`destinatario`);

--
-- Indexes for table `modalidad`
--
ALTER TABLE `modalidad`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alumno_year` (`alumno_dni`,`year_escolar_id`);

--
-- Indexes for table `notas_detalle`
--
ALTER TABLE `notas_detalle`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nota` (`alumno_dni`,`materia_id`,`year_escolar_id`,`cuatrimestre`),
  ADD KEY `fk_notas_materia` (`materia_id`),
  ADD KEY `fk_notas_year` (`year_escolar_id`);

--
-- Indexes for table `preceptor_curso`
--
ALTER TABLE `preceptor_curso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prec_curso` (`preceptor_dni`,`curso_id`,`year_escolar_id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `year_escolar_id` (`year_escolar_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`dni`),
  ADD UNIQUE KEY `uq_usuarios_dni` (`dni`),
  ADD UNIQUE KEY `uq_dni` (`dni`);

--
-- Indexes for table `year_escolar`
--
ALTER TABLE `year_escolar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year` (`year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `asignado_alumno`
--
ALTER TABLE `asignado_alumno`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asignado_profesor`
--
ALTER TABLE `asignado_profesor`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curso`
--
ALTER TABLE `curso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curso_division`
--
ALTER TABLE `curso_division`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curso_materia`
--
ALTER TABLE `curso_materia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curso_year`
--
ALTER TABLE `curso_year`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `docente_materia_curso`
--
ALTER TABLE `docente_materia_curso`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `familia_alumno`
--
ALTER TABLE `familia_alumno`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `foro`
--
ALTER TABLE `foro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materias_year`
--
ALTER TABLE `materias_year`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modalidad`
--
ALTER TABLE `modalidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notas_detalle`
--
ALTER TABLE `notas_detalle`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `preceptor_curso`
--
ALTER TABLE `preceptor_curso`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `year_escolar`
--
ALTER TABLE `year_escolar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `fk_alumnos_usuario` FOREIGN KEY (`alumno_dni`) REFERENCES `usuarios` (`dni`) ON DELETE CASCADE;

--
-- Constraints for table `asignado_alumno`
--
ALTER TABLE `asignado_alumno`
  ADD CONSTRAINT `asignado_alumno_ibfk_1` FOREIGN KEY (`alumno_dni`) REFERENCES `alumnos` (`alumno_dni`),
  ADD CONSTRAINT `asignado_alumno_ibfk_2` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`),
  ADD CONSTRAINT `asignado_alumno_ibfk_3` FOREIGN KEY (`year_escolar_id`) REFERENCES `year_escolar` (`id`);

--
-- Constraints for table `asignado_profesor`
--
ALTER TABLE `asignado_profesor`
  ADD CONSTRAINT `fk_ap_maestro` FOREIGN KEY (`maestro_dni`) REFERENCES `usuarios` (`dni`),
  ADD CONSTRAINT `fk_ap_my` FOREIGN KEY (`materias_year_id`) REFERENCES `materias_year` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `curso`
--
ALTER TABLE `curso`
  ADD CONSTRAINT `curso_ibfk_1` FOREIGN KEY (`curso_year_id`) REFERENCES `curso_year` (`id`),
  ADD CONSTRAINT `curso_ibfk_2` FOREIGN KEY (`curso_division_id`) REFERENCES `curso_division` (`id`),
  ADD CONSTRAINT `curso_ibfk_3` FOREIGN KEY (`modalidad_id`) REFERENCES `modalidad` (`id`);

--
-- Constraints for table `curso_materia`
--
ALTER TABLE `curso_materia`
  ADD CONSTRAINT `fk_cm_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cm_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cm_year` FOREIGN KEY (`year_escolar_id`) REFERENCES `year_escolar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `docente_materia_curso`
--
ALTER TABLE `docente_materia_curso`
  ADD CONSTRAINT `fk_dmc_cm` FOREIGN KEY (`curso_materia_id`) REFERENCES `curso_materia` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dmc_docente` FOREIGN KEY (`maestro_dni`) REFERENCES `usuarios` (`dni`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `familia_alumno`
--
ALTER TABLE `familia_alumno`
  ADD CONSTRAINT `fk_fa_alumno` FOREIGN KEY (`alumno_dni`) REFERENCES `usuarios` (`dni`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fa_familia` FOREIGN KEY (`familia_dni`) REFERENCES `usuarios` (`dni`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `materias_year`
--
ALTER TABLE `materias_year`
  ADD CONSTRAINT `fk_my_modalidad` FOREIGN KEY (`modalidad_id`) REFERENCES `modalidad` (`id`),
  ADD CONSTRAINT `materias_year_ibfk_1` FOREIGN KEY (`materias_id`) REFERENCES `materias` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `materias_year_ibfk_2` FOREIGN KEY (`curso_year_id`) REFERENCES `curso_year` (`id`),
  ADD CONSTRAINT `materias_year_ibfk_3` FOREIGN KEY (`modalidad_id`) REFERENCES `modalidad` (`id`),
  ADD CONSTRAINT `materias_year_ibfk_4` FOREIGN KEY (`year_escolar_id`) REFERENCES `year_escolar` (`id`);

--
-- Constraints for table `notas_detalle`
--
ALTER TABLE `notas_detalle`
  ADD CONSTRAINT `fk_notas_alumno` FOREIGN KEY (`alumno_dni`) REFERENCES `usuarios` (`dni`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notas_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notas_year` FOREIGN KEY (`year_escolar_id`) REFERENCES `year_escolar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
