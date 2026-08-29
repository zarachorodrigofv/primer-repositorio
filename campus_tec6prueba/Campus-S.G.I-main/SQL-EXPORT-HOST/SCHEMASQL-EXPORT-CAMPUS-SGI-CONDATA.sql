-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2026 at 10:56 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `campus`
--

-- --------------------------------------------------------

--
-- Table structure for table `alumnos`
--

CREATE TABLE `alumnos` (
  `alumno_dni` varchar(20) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ausente` int(11) DEFAULT 0,
  `presente` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumnos`
--

INSERT INTO `alumnos` (`alumno_dni`, `telefono`, `direccion`, `ausente`, `presente`) VALUES
('111222', '1112345678', 'Av.Falsa 123', 0, 0),
('12345678', '1187654321', 'Ok 123', 6, 12),
('23456789', '1234567891', 'lala 123', 2, 10),
('44555666', NULL, NULL, 0, 0),
('45262626', '', '', 0, 0),
('98765432', '1122223333', 'OK 123', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `asignado_alumno`
--

CREATE TABLE `asignado_alumno` (
  `id` int(10) UNSIGNED NOT NULL,
  `alumno_dni` varchar(20) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `year_escolar_id` int(11) NOT NULL,
  `estado` enum('activo','baja') DEFAULT 'activo',
  `fecha_inscripcion` date DEFAULT curdate(),
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asignado_alumno`
--

INSERT INTO `asignado_alumno` (`id`, `alumno_dni`, `curso_id`, `year_escolar_id`, `estado`, `fecha_inscripcion`, `fecha_baja`, `motivo_baja`) VALUES
(3, '98765432', 1, 1, 'activo', '2025-11-17', NULL, NULL),
(4, '23456789', 1, 1, 'activo', '2025-11-27', NULL, NULL),
(5, '12345678', 2, 1, 'activo', '2025-11-27', NULL, NULL),
(6, '45262626', 21, 1, 'activo', '2026-06-10', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `asignado_profesor`
--

CREATE TABLE `asignado_profesor` (
  `id` int(10) UNSIGNED NOT NULL,
  `maestro_dni` varchar(20) NOT NULL,
  `materias_year_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia`
--

CREATE TABLE `asistencia` (
  `id` int(11) NOT NULL,
  `alumno_dni` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('presente','ausente','tarde','justificado') NOT NULL,
  `motivo_justificado` text DEFAULT NULL COMMENT 'Motivo cuando estado = justificado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asistencia`
--

INSERT INTO `asistencia` (`id`, `alumno_dni`, `fecha`, `estado`, `motivo_justificado`) VALUES
(4, '44555666', '2025-03-01', 'presente', NULL),
(5, '44555666', '2025-03-02', 'presente', NULL),
(6, '44555666', '2025-03-03', 'ausente', NULL),
(7, '44555666', '2025-01-01', 'presente', NULL),
(60, '44555666', '2025-11-01', 'presente', NULL),
(61, '44555666', '2025-11-02', 'ausente', NULL),
(62, '44555666', '2025-11-03', 'ausente', NULL),
(63, '44555666', '2025-11-05', 'presente', NULL),
(64, '44555666', '2025-11-06', 'ausente', NULL),
(65, '44555666', '2025-11-07', 'presente', NULL),
(66, '44555666', '2025-11-09', 'ausente', NULL),
(67, '44555666', '2025-11-18', 'ausente', NULL),
(69, '98765432', '2025-11-01', 'presente', NULL),
(82, '23456789', '2026-06-01', 'justificado', 'hhoolaa'),
(83, '98765432', '2026-06-01', 'tarde', NULL),
(271, '23456789', '2026-03-31', 'presente', NULL),
(272, '98765432', '2026-03-31', 'presente', NULL),
(410, '23456789', '2026-04-08', 'presente', NULL),
(411, '12345678', '2026-07-08', 'justificado', 'k'),
(412, '45262626', '2026-07-07', 'presente', NULL),
(413, '45262626', '2026-07-08', 'presente', NULL),
(502, '23456789', '2026-07-01', 'ausente', NULL),
(503, '23456789', '2026-07-06', 'ausente', NULL),
(504, '23456789', '2026-07-07', 'presente', NULL),
(505, '23456789', '2026-07-08', 'ausente', NULL),
(506, '23456789', '2026-07-09', 'presente', NULL),
(507, '23456789', '2026-07-10', 'justificado', 'k'),
(508, '23456789', '2026-07-13', 'presente', NULL),
(509, '23456789', '2026-07-14', 'presente', NULL),
(510, '23456789', '2026-07-15', 'presente', NULL),
(511, '23456789', '2026-07-16', 'ausente', NULL),
(512, '23456789', '2026-07-17', 'presente', NULL),
(513, '23456789', '2026-07-20', 'presente', NULL),
(514, '23456789', '2026-07-21', 'presente', NULL),
(515, '23456789', '2026-07-22', 'presente', NULL),
(516, '23456789', '2026-07-23', 'presente', NULL),
(517, '23456789', '2026-07-24', 'tarde', NULL),
(518, '23456789', '2026-07-27', 'tarde', NULL),
(519, '23456789', '2026-07-28', 'presente', NULL),
(520, '23456789', '2026-07-29', 'presente', NULL),
(521, '23456789', '2026-07-30', 'presente', NULL),
(522, '23456789', '2026-07-31', 'presente', NULL),
(523, '98765432', '2026-07-01', 'ausente', NULL),
(524, '98765432', '2026-07-02', 'presente', NULL),
(525, '98765432', '2026-07-03', 'tarde', NULL),
(526, '98765432', '2026-07-06', 'presente', NULL),
(527, '98765432', '2026-07-07', 'presente', NULL),
(528, '98765432', '2026-07-08', 'presente', NULL),
(529, '98765432', '2026-07-09', 'ausente', NULL),
(530, '98765432', '2026-07-10', 'presente', NULL),
(531, '98765432', '2026-07-13', 'presente', NULL),
(532, '98765432', '2026-07-14', 'ausente', NULL),
(533, '98765432', '2026-07-15', 'ausente', NULL),
(534, '98765432', '2026-07-16', 'presente', NULL),
(535, '98765432', '2026-07-17', 'presente', NULL),
(536, '98765432', '2026-07-20', 'presente', NULL),
(537, '98765432', '2026-07-21', 'presente', NULL),
(538, '98765432', '2026-07-22', 'ausente', NULL),
(539, '98765432', '2026-07-23', 'ausente', NULL),
(540, '98765432', '2026-07-24', 'ausente', NULL),
(541, '98765432', '2026-07-27', 'tarde', NULL),
(542, '98765432', '2026-07-28', 'presente', NULL),
(543, '98765432', '2026-07-29', 'presente', NULL),
(544, '98765432', '2026-07-30', 'presente', NULL),
(545, '98765432', '2026-07-31', 'presente', NULL),
(547, '23456789', '2026-04-22', 'presente', NULL),
(548, '98765432', '2026-04-22', 'ausente', NULL),
(549, '23456789', '2026-04-23', 'ausente', NULL),
(565, '23456789', '2026-08-03', 'presente', NULL),
(566, '23456789', '2026-08-04', 'presente', NULL),
(567, '23456789', '2026-08-05', 'presente', NULL),
(568, '23456789', '2026-08-07', 'presente', NULL),
(569, '23456789', '2026-08-10', 'presente', NULL),
(570, '23456789', '2026-08-13', 'presente', NULL),
(571, '23456789', '2026-08-18', 'presente', NULL),
(572, '23456789', '2026-08-19', 'presente', NULL),
(573, '98765432', '2026-08-05', 'presente', NULL),
(574, '98765432', '2026-08-06', 'presente', NULL),
(575, '98765432', '2026-08-12', 'presente', NULL),
(576, '98765432', '2026-08-14', 'presente', NULL),
(577, '98765432', '2026-08-17', 'presente', NULL),
(578, '98765432', '2026-08-18', 'presente', NULL),
(579, '98765432', '2026-08-19', 'presente', NULL);

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

--
-- Dumping data for table `curso`
--

INSERT INTO `curso` (`id`, `curso_year_id`, `curso_division_id`, `modalidad_id`) VALUES
(1, 1, 1, NULL),
(2, 1, 2, NULL),
(15, 1, 3, NULL),
(18, 1, 4, NULL),
(21, 1, 5, NULL),
(24, 1, 6, NULL),
(27, 1, 7, NULL),
(10, 2, 1, NULL),
(13, 2, 2, NULL),
(16, 2, 3, NULL),
(19, 2, 4, NULL),
(22, 2, 5, NULL),
(25, 2, 6, NULL),
(28, 2, 7, NULL),
(4, 3, 1, NULL),
(14, 3, 2, NULL),
(17, 3, 3, NULL),
(20, 3, 4, NULL),
(23, 3, 5, NULL),
(26, 3, 6, NULL),
(29, 3, 7, NULL),
(40, 4, 1, 4),
(44, 4, 2, 4),
(79, 4, 3, 5),
(83, 4, 4, 5),
(87, 4, 5, 5),
(60, 4, 6, 4),
(64, 4, 7, 4),
(41, 5, 1, 4),
(76, 5, 2, 5),
(49, 5, 3, 4),
(84, 5, 4, 5),
(57, 5, 5, 4),
(92, 5, 6, 5),
(65, 5, 7, 4),
(73, 6, 1, 5),
(46, 6, 2, 4),
(81, 6, 3, 5),
(85, 6, 4, 5),
(89, 6, 5, 5),
(62, 6, 6, 4),
(66, 6, 7, 4),
(74, 7, 1, 5),
(78, 7, 2, 5),
(82, 7, 3, 5),
(55, 7, 4, 4),
(59, 7, 5, 4),
(94, 7, 6, 5),
(98, 7, 7, 5);

-- --------------------------------------------------------

--
-- Table structure for table `curso_division`
--

CREATE TABLE `curso_division` (
  `id` int(11) NOT NULL,
  `division` varchar(20) NOT NULL COMMENT 'ej: 7ma, 6to, A, etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `curso_division`
--

INSERT INTO `curso_division` (`id`, `division`) VALUES
(1, '1ra'),
(2, '2da'),
(3, '3ra'),
(4, '4ta'),
(5, '5ta'),
(6, '6ta'),
(7, '7ma');

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

--
-- Dumping data for table `curso_materia`
--

INSERT INTO `curso_materia` (`id`, `curso_id`, `materia_id`, `year_escolar_id`) VALUES
(2, 1, 85, 1),
(3, 2, 86, 1);

-- --------------------------------------------------------

--
-- Table structure for table `curso_year`
--

CREATE TABLE `curso_year` (
  `id` int(11) NOT NULL,
  `year` varchar(20) NOT NULL COMMENT 'ej: "1ro", "2da", etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `curso_year`
--

INSERT INTO `curso_year` (`id`, `year`) VALUES
(1, '1ro'),
(2, '2do'),
(3, '3ro'),
(4, '4to'),
(5, '5to'),
(6, '6to'),
(7, '7mo');

-- --------------------------------------------------------

--
-- Table structure for table `docente_materia_curso`
--

CREATE TABLE `docente_materia_curso` (
  `id` int(10) UNSIGNED NOT NULL,
  `maestro_dni` int(10) UNSIGNED NOT NULL,
  `curso_materia_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `docente_materia_curso`
--

INSERT INTO `docente_materia_curso` (`id`, `maestro_dni`, `curso_materia_id`) VALUES
(1, 33222111, 2),
(2, 33222111, 3);

-- --------------------------------------------------------

--
-- Table structure for table `familia_alumno`
--

CREATE TABLE `familia_alumno` (
  `id` int(11) NOT NULL,
  `familia_dni` int(11) NOT NULL,
  `alumno_dni` int(11) NOT NULL,
  `parentesco` varchar(60) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `foro`
--

INSERT INTO `foro` (`id`, `titulo`, `contenido`, `imagen`, `fecha`, `fecha_edicion`, `editado`, `autor_dni`, `destino_tipo`, `destino_valor`, `archivo_nombre_original`) VALUES
(1, 'wqe', 'qwe', NULL, '2025-11-17 22:03:36', NULL, 0, '44555666', 'general', NULL, NULL),
(2, 'Hola hoy no hay clases', 'Debido a un paro no hay clases, porfavor avisar a sus compañeros y familia.\r\n\r\nDirectivo.', NULL, '2025-11-17 22:10:12', NULL, 0, '44555666', 'general', NULL, NULL),
(3, 'Libro para Literatura de 7mo!', 'Vayan consiguendolo para le primer cuatrimestre.', 'uploads/1763446301_1024px-The_Catcher_in_the_Rye_(1951,_first_edition_cover).jpg', '2025-11-17 22:11:41', NULL, 0, '44555666', 'general', NULL, NULL),
(4, 'Probando!', 'HOLA ESTO ES DE PRUEBA', NULL, '2025-11-23 22:39:36', NULL, 0, '44555666', 'general', NULL, NULL),
(5, 'Hola alumnos (prueba)', 'Hola alumnos (prueba)', NULL, '2025-11-24 12:02:34', NULL, 0, '44555666', 'rol', 'alumno', NULL),
(6, 'Hola Profesores (prueba)', 'Hola Profesores(prueba)', NULL, '2025-11-24 12:02:55', NULL, 0, '44555666', 'rol', 'profesor', NULL),
(7, 'Hola preceptores', 'Hola preceptores', NULL, '2025-11-24 12:03:09', NULL, 0, '44555666', 'rol', 'preceptor', NULL),
(8, 'Hola familia', 'HOla familia', NULL, '2025-11-24 12:03:23', NULL, 0, '44555666', 'rol', 'familia', NULL),
(9, 'Hola alumnos desde preceptor test', 'Hola alumnos desde preceptor test', NULL, '2025-11-24 12:23:13', NULL, 0, '99888777', 'rol', 'alumno', NULL),
(10, 'Hola familia desde preceptor test', 'Hola familia desde preceptor test', NULL, '2025-11-24 12:24:23', NULL, 0, '99888777', 'rol', 'familia', NULL),
(11, 'Probando Edición', 'Funciona?', NULL, '2025-11-24 12:27:32', '2025-11-26 21:15:32', 1, '99888777', 'general', NULL, NULL),
(12, 'Hola desde Profesor Test a Profesores', 'Hola desde Profesor Test a Profeso', NULL, '2025-11-24 12:43:54', '2025-11-27 09:41:43', 1, '33222111', 'rol', 'profesor', NULL),
(13, 'Hola desde Profesor Test a Alumnos', 'Hola desde Profesor Test a Alumnos', NULL, '2025-11-24 12:44:06', '2025-11-27 08:57:54', 1, '33222111', 'rol', 'alumno', NULL),
(17, 'Prueba Descarga', 'Probando descarga de pdf', 'uploads_foro/1764224691_81207bb5.pdf', '2025-11-26 22:24:51', '2025-11-26 22:25:22', 1, '99888777', 'general', '', 'El-Principitocompleto.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `materias`
--

CREATE TABLE `materias` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL COMMENT 'ej: matematicas, fisica, etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materias`
--

INSERT INTO `materias` (`id`, `nombre`) VALUES
(1, 'Matemática'),
(85, 'Biología'),
(86, 'Ciencias Naturales'),
(87, 'Ciencias Sociales'),
(88, 'Construcción Ciudadana'),
(89, 'Construcción de Ciudadanía'),
(90, 'Educación Artística'),
(91, 'Educación Física'),
(92, 'Físico Química'),
(93, 'Geografía'),
(94, 'Historia'),
(95, 'Inglés'),
(96, 'Matemática'),
(97, 'Prácticas del Lenguaje'),
(98, 'Procedimientos Técnicos'),
(99, 'Lenguajes Tecnológicos'),
(100, 'Sistemas Tecnológicos');

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

--
-- Dumping data for table `materias_year`
--

INSERT INTO `materias_year` (`id`, `materia_id`, `materias_id`, `curso_year_id`, `modalidad_id`, `year_escolar_id`) VALUES
(1, 0, 1, 1, NULL, 1);

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

--
-- Dumping data for table `mensajes`
--

INSERT INTO `mensajes` (`id`, `remitente`, `destinatario`, `mensaje`, `fecha`) VALUES
(76, 'Zozaya', 'Director Bruno', 'holaaa', '2025-10-09 10:31:40'),
(77, 'Director Bruno', 'Zozaya', 'Hola profe', '2025-10-09 10:36:40'),
(78, 'Director Bruno', 'Zozaya', 'Hola profe', '2025-10-09 10:36:40'),
(79, 'Zozaya', 'Director Bruno', 'muy bien ', '2025-10-09 10:36:46'),
(80, 'Directivo Test', 'Familia Test', 'hola como estas? maÃ±ana no hay clases\r\n', '2025-11-13 08:25:32'),
(81, 'Alumno Test', 'Directivo Test', 'hola direee\r\n', '2025-11-27 09:34:34'),
(82, 'Directivo Test', 'Alumno Test', 'hola pa\r\n', '2025-11-27 09:34:49'),
(83, 'Alumno Test', 'Directivo Test', 'otro mensaje', '2025-11-27 09:35:41'),
(84, 'Directivo Test', 'Juan Perez', 'hola pa\r\n', '2026-06-10 12:28:12'),
(85, 'Directivo Test', 'franco mrak', 'putito', '2026-06-10 12:28:36');

-- --------------------------------------------------------

--
-- Table structure for table `modalidad`
--

CREATE TABLE `modalidad` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL COMMENT 'ej: informatica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modalidad`
--

INSERT INTO `modalidad` (`id`, `nombre`) VALUES
(3, 'Ciclo Básico'),
(4, 'Técnico en Madera'),
(5, 'Técnico Mecánico');

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

--
-- Dumping data for table `notas`
--

INSERT INTO `notas` (`id`, `alumno_dni`, `year_escolar_id`, `vp_c1`, `int_c1`, `vp_c2`, `cierre_anual`, `int_dic`, `int_feb`, `amp_mar`, `informe_final`, `fecha_actualizacion`) VALUES
(1, 111222, 1, 'TEA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 07:53:50');

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
  `observaciones` text DEFAULT NULL,
  `nota_concepto` varchar(255) DEFAULT NULL,
  `nota_tp` varchar(255) DEFAULT NULL,
  `nota_examen` varchar(255) DEFAULT NULL,
  `intens_diciembre` varchar(255) DEFAULT NULL,
  `intens_febrero` varchar(255) DEFAULT NULL,
  `intens_marzo` varchar(255) DEFAULT NULL,
  `nota_final` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notas_detalle`
--

INSERT INTO `notas_detalle` (`id`, `alumno_dni`, `materia_id`, `year_escolar_id`, `cuatrimestre`, `nota_valorativa`, `nota_numerica`, `observaciones`, `intens_diciembre`, `intens_febrero`, `intens_marzo`, `nota_final`) VALUES
(135, 98765432, 1, 1, '1', 'TEA', 7.00, NULL, NULL, NULL, NULL, NULL),
(136, 98765432, 1, 1, '2', 'TEA', 7.00, NULL, NULL, NULL, NULL, NULL),
(147, 98765432, 85, 1, '1', 'TEA', 8.00, 'Buen Alumno', NULL, NULL, NULL, NULL),
(148, 98765432, 85, 1, '2', 'TEA', 10.00, 'Buen Alumno', NULL, NULL, NULL, '9'),
(151, 23456789, 85, 1, '1', 'TEA', 10.00, 'xd', 'TEA', 'TEA', 'TEA', NULL),
(152, 23456789, 85, 1, '2', 'TEA', 8.00, 'xd', 'TEA', 'TEA', 'TEA', '9');

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

--
-- Dumping data for table `preceptor_curso`
--

INSERT INTO `preceptor_curso` (`id`, `preceptor_dni`, `curso_id`, `year_escolar_id`) VALUES
(1, 99888777, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `dni` int(10) UNSIGNED NOT NULL,
  `password` varchar(100) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `rol` enum('root','admin','directivo','jefe_preceptores','jefe_area','jefe_taller','jefe_departamento','preceptor','profesor','familia') NOT NULL DEFAULT 'familia',
  `password_changed` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`dni`, `password`, `nombre`, `telefono`, `rol`, `password_changed`) VALUES
(0, '$2y$10$U5KOvgOlOpqyzApmHkDAUOS4Zts0qJbDgnQe9JKLC3u1upzk3/uSa', 'Administrador Root', NULL, 'root', 1),
(111222, '$2y$10$05E4O6PLhtyGkrWFekaqk.cT4N3kEXn4LfKnzov3AHxHiz3.oRpQ2', 'Juan Perez', NULL, '', 1),
(11222333, '$2y$10$T0D20A88QBWOP6I0tKVawu/PmnOXeIorDjVTzuto5x2ad0XmE3E7u', 'Alumno Test', NULL, '', 1),
(12345678, '$2y$10$psRHcBiA4drXMhPsWD7RY.AdXJaHXR4QF9qE65v7jZm5um5333tLa', 'Luis Gimenez', NULL, '', 1),
(23456789, '$2y$10$WOcyXTIr.iMxg8/nyfQuz.XNqLnGfVqe6V.SkI11ZPKZP2/XuV0a2', 'Juan Perez', NULL, '', 1),
(33222111, '$2y$10$aCPn3UXPEmpxzTRLplOyAOMExmo/8EfrA8gwzSyIhGUI0lTOn36qa', 'Profesor Test', NULL, 'profesor', 1),
(44444444, '$2y$10$C8ZLC0649JEqC074nhyWQ.w4IFahUxs5CIDDDM4MfTyKPrILGjfIu', '', NULL, '', 1),
(44555666, '$2y$10$BoT4.zI3hGDhXijdLo42VO29hIaPo1eBSDfaagcIEMMpBOMGs7khW', 'Directivo Test', '113234546', 'directivo', 1),
(45262626, '$2y$10$StpIFFbMY7c1Uxh2Q4nENu5DLQMInn1kdXBsDGvzCwrJCM/U1Pw8S', 'franco mrak', NULL, '', 1),
(45968685, '$2y$10$RWNsRiClG7ghVFZNdHK/c.SNdQFwW4UIxCHRgtU5NSOZi9HE3MkPW', 'azzaro y duka', NULL, 'admin', 1),
(45968687, '$2y$10$RCCq1zJo5F2D408KioLpGeLBC7k5h6/JgpCve/haSkjFJAQ9iMnw2', 'luquita rodrigue', NULL, 'directivo', 1),
(48062924, '$2y$10$xVm9X83NXFaCfOgvo9iUrOQZU4xHtDa7xd9aSmeS/UIY3dOch2zRC', '', NULL, '', 1),
(77888999, '$2y$10$/27m5P6CxPk.sMBC5G3g3.5Afuw/ZV3V2ZEDigET3p75BHThevkW.', 'Familia Test', NULL, 'familia', 1),
(98765432, '$2y$10$G/yYSdutYigg.Ih5AyMcZ.YGE0Ieow6jD3F2dGXuu5ocrnWGYMllW', 'Ramon Cruz', NULL, '', 1),
(99888777, '$2y$10$jNeDlZHraV2.bwVXIoyAu.x0Mrr.CfUXY5Ek/luKUZI6Bm4MzaOm.', 'Preceptor Test', NULL, 'preceptor', 1),
(4294967295, '$2y$10$EOwGBHyU1LBGqIzncw.nAeoeWHu5jYUjl2vLwBrAYVkF/KX3FCW.a', 'gfagadfahgah', NULL, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `year_escolar`
--

CREATE TABLE `year_escolar` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `year_escolar`
--

INSERT INTO `year_escolar` (`id`, `year`) VALUES
(1, 2025);

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
  ADD UNIQUE KEY `uq_fa` (`familia_dni`,`alumno_dni`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `asignado_profesor`
--
ALTER TABLE `asignado_profesor`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=580;

--
-- AUTO_INCREMENT for table `curso`
--
ALTER TABLE `curso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `curso_division`
--
ALTER TABLE `curso_division`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `curso_materia`
--
ALTER TABLE `curso_materia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `curso_year`
--
ALTER TABLE `curso_year`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `docente_materia_curso`
--
ALTER TABLE `docente_materia_curso`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `familia_alumno`
--
ALTER TABLE `familia_alumno`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `foro`
--
ALTER TABLE `foro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `materias_year`
--
ALTER TABLE `materias_year`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `modalidad`
--
ALTER TABLE `modalidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notas_detalle`
--
ALTER TABLE `notas_detalle`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `preceptor_curso`
--
ALTER TABLE `preceptor_curso`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `year_escolar`
--
ALTER TABLE `year_escolar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `asignado_alumno`
--
ALTER TABLE `asignado_alumno`
  ADD CONSTRAINT `asignado_alumno_ibfk_2` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`),
  ADD CONSTRAINT `asignado_alumno_ibfk_3` FOREIGN KEY (`year_escolar_id`) REFERENCES `year_escolar` (`id`);

--
-- Constraints for table `asignado_profesor`
--
ALTER TABLE `asignado_profesor`
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
