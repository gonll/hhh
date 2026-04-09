-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: BGHInmobiliaria
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accesos`
--

DROP TABLE IF EXISTS `accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(80) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `nivel_acceso` tinyint(4) NOT NULL DEFAULT 2,
  `creado_por_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_nivel` (`nivel_acceso`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accesos`
--

LOCK TABLES `accesos` WRITE;
/*!40000 ALTER TABLE `accesos` DISABLE KEYS */;
INSERT INTO `accesos` VALUES (10,'sofia','$2y$10$DDXO321T7QdLJXKeRuJtHunkBR/Xpye5VtQ.UR0QmqcnhkCeN2zhO',3,NULL,'2026-04-01 16:43:06');
/*!40000 ALTER TABLE `accesos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alquileres`
--

DROP TABLE IF EXISTS `alquileres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alquileres` (
  `alquiler_id` int(11) NOT NULL AUTO_INCREMENT,
  `propiedad_id` int(11) NOT NULL,
  `inquilino1_id` int(11) NOT NULL,
  `inquilino2_id` int(11) DEFAULT NULL,
  `codeudor1_id` int(11) NOT NULL,
  `codeudor2_id` int(11) DEFAULT NULL,
  `plazo_meses` int(11) DEFAULT NULL,
  `incremento_alquiler_meses` tinyint(3) unsigned NOT NULL DEFAULT 2 COMMENT 'Cada cuántos meses se actualiza el alquiler (1-6)',
  `destino` varchar(50) DEFAULT 'VIVIENDA',
  `modelo_contrato` varchar(16) NOT NULL DEFAULT 'BGH' COMMENT 'BGH plantilla actual; HYLL clausula tercera ICL+1.5%',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `precio_convenido` decimal(10,2) DEFAULT NULL,
  `fecha_firma` date DEFAULT NULL,
  `monto_deposito` decimal(10,2) DEFAULT NULL,
  `estado` enum('VIGENTE','BAJA') DEFAULT 'VIGENTE',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`alquiler_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alquileres`
--

LOCK TABLES `alquileres` WRITE;
/*!40000 ALTER TABLE `alquileres` DISABLE KEYS */;
INSERT INTO `alquileres` VALUES (1,1,88,NULL,89,NULL,36,2,'OFICINA','BGH','2026-04-02','2029-03-31',100000.00,'2026-04-02',150000.00,'BAJA','2026-04-02 21:27:49'),(2,1,88,NULL,89,NULL,24,2,'VIVIENDA','BGH','2026-02-01','2028-01-31',200000.00,'2026-04-03',300000.00,'BAJA','2026-04-02 22:50:18'),(3,1,88,NULL,89,NULL,24,2,'VIVIENDA','BGH','2026-02-01','2028-01-31',100000.00,'2026-04-03',150000.00,'BAJA','2026-04-02 22:56:19'),(4,1,88,NULL,89,NULL,24,3,'VIVIENDA','BGH','2025-11-01','2027-10-31',100000.00,'2026-04-03',150000.00,'BAJA','2026-04-02 22:58:18'),(5,1,88,NULL,89,NULL,24,2,'VIVIENDA','BGH','2026-02-01','2028-01-31',100000.00,'2026-04-03',150000.00,'BAJA','2026-04-02 23:02:16'),(6,2,93,NULL,94,NULL,24,3,'VIVIENDA','BGH','2026-03-20','2028-03-19',780000.00,'2026-03-20',1170000.00,'VIGENTE','2026-04-06 22:35:39'),(7,3,95,NULL,95,NULL,24,2,'VIVIENDA','BGH','2026-04-01','2026-11-14',1110918.00,'2026-04-01',1666377.00,'VIGENTE','2026-04-06 23:07:48'),(8,4,98,NULL,99,NULL,12,3,'VIVIENDA','BGH','2026-03-06','2027-03-05',360202.00,'2026-04-07',580000.00,'VIGENTE','2026-04-07 21:01:47');
/*!40000 ALTER TABLE `alquileres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arriendos`
--

DROP TABLE IF EXISTS `arriendos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arriendos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propietario_id` int(11) NOT NULL,
  `apoderado_id` int(11) NOT NULL,
  `arrendatario_id` int(11) NOT NULL,
  `padron` varchar(20) DEFAULT NULL,
  `descripcion_finca` text DEFAULT NULL,
  `fecha_cobro_1` date DEFAULT NULL,
  `fecha_cobro_2` date DEFAULT NULL,
  `kilos_fecha_1` decimal(12,2) DEFAULT NULL,
  `kilos_fecha_2` decimal(12,2) DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT 21.00,
  `descontar_iva` tinyint(1) NOT NULL DEFAULT 0,
  `monto_descuentos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paga_comunal` tinyint(1) NOT NULL DEFAULT 0,
  `paga_provincial` tinyint(1) NOT NULL DEFAULT 0,
  `porcentaje_otros` decimal(5,2) DEFAULT NULL,
  `fecha_vencimiento_contrato` date DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_arriendo_propietario` (`propietario_id`),
  KEY `fk_arriendo_apoderado` (`apoderado_id`),
  KEY `fk_arriendo_arrendatario` (`arrendatario_id`),
  CONSTRAINT `fk_arriendo_apoderado` FOREIGN KEY (`apoderado_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_arriendo_arrendatario` FOREIGN KEY (`arrendatario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_arriendo_propietario` FOREIGN KEY (`propietario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `arriendos`
--

LOCK TABLES `arriendos` WRITE;
/*!40000 ALTER TABLE `arriendos` DISABLE KEYS */;
/*!40000 ALTER TABLE `arriendos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config` (
  `clave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` VALUES ('clave_borrado','2510');
/*!40000 ALTER TABLE `config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas`
--

DROP TABLE IF EXISTS `cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuentas` (
  `movimiento_id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `comprobante` varchar(50) DEFAULT NULL,
  `referencia` varchar(50) DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `arriendo_id` int(11) DEFAULT NULL,
  `arriendo_fecha` tinyint(4) DEFAULT NULL,
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`movimiento_id`),
  KEY `fk_usuario_cuentas` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas`
--

LOCK TABLES `cuentas` WRITE;
/*!40000 ALTER TABLE `cuentas` DISABLE KEYS */;
INSERT INTO `cuentas` VALUES (1556,89,'2026-04-01','BORRAR','EFVO','BORRAR',1.00,NULL,NULL,0.00),(1561,90,'2026-04-03','COBRO EXPENSA LOCAL COMERCIAL,(CONSULTORIOS PB A LA CALLE), ESTADOS UNIDOS 101 03/2026 - PAGÓ HERRERA HECTOR HUGO','EXP/TRANSF','03/2026',23.00,NULL,NULL,0.00),(1563,89,'2026-04-03','COBRO EXPENSA LOCAL COMERCIAL,(CONSULTORIOS PB A LA CALLE), ESTADOS UNIDOS 101 03/2026 - PAGÓ HERRERA HECTOR HUGO','EXP/TRANSF','03/2026',23.00,NULL,NULL,0.00),(1564,90,'2026-04-03','COBRO EXPENSA LOCAL COMERCIAL,(CONSULTORIOS PB A LA CALLE), ESTADOS UNIDOS 101 03/2026 - PAGÓ HERRERA HECTOR HUGO','EXP/EFVO','03/2026',11.00,NULL,NULL,0.00),(1566,89,'2026-04-03','COBRO EXPENSA LOCAL COMERCIAL,(CONSULTORIOS PB A LA CALLE), ESTADOS UNIDOS 101 03/2026 - PAGÓ HERRERA HECTOR HUGO','EXP/EFVO','03/2026',11.00,NULL,NULL,0.00),(1568,90,'2026-04-03','COBRO EXPENSA LOCAL COMERCIAL,(CONSULTORIOS PB A LA CALLE), ESTADOS UNIDOS 101 03/2026 - PAGÓ HERRERA HECTOR HUGO','EXP/TRANSF','03/2026',34.00,NULL,NULL,0.00),(1570,89,'2026-04-03','COBRO EXPENSA LOCAL COMERCIAL,(CONSULTORIOS PB A LA CALLE), ESTADOS UNIDOS 101 03/2026 - PAGÓ HERRERA HECTOR HUGO','EXP/TRANSF','03/2026',34.00,NULL,NULL,0.00),(1586,93,'2026-04-07','INICIO DEL CONTRATO DEPARTAMENTO AV SALTA 87, PISO 1 º C (87)','DEPÓSITO','GARANTÍA',-1170000.00,NULL,NULL,0.00),(1587,93,'2026-03-20','ALQUILER DEPARTAMENTO AV SALTA 87, PISO 1 º C (87) PROPORCIONAL 12 DíAS','LIQ ALQUILER','03/2026',-301935.48,NULL,NULL,0.00),(1588,93,'2026-04-01','ALQUILER - DEPARTAMENTO AV SALTA 87, PISO 1 º C','ALQUILER','04/2026',-780000.00,NULL,NULL,0.00),(1593,93,'2026-04-06','COBRO DE: ALQUILER - DEPARTAMENTO AV SALTA 87, PISO 1 º C','TRANSFERENCIA','03/2026',301935.48,NULL,NULL,0.00),(1594,91,'2026-04-06','JORGE LUIS ROSSI - COBRO DE: ALQUILER - DEPARTAMENTO AV SALTA 87, PISO 1 º C','TRANSFERENCIA','03/2026',301935.48,NULL,NULL,0.00),(1595,93,'2026-04-06','COBRO DE: ALQUILER - DEPARTAMENTO AV SALTA 87, PISO 1 º C','TRANSFERENCIA','GARANTIA',1170000.00,NULL,NULL,0.00),(1596,91,'2026-04-06','JORGE LUIS ROSSI - COBRO DE: ALQUILER - DEPARTAMENTO AV SALTA 87, PISO 1 º C','TRANSFERENCIA','GARANTIA',1170000.00,NULL,NULL,0.00),(1598,95,'2026-04-01','ALQUILER CASA COUNTRY VERA TERRA LOTE D11 (SHUJM) PROPORCIONAL 30 DíAS','LIQ ALQUILER','04/2026',-1110918.00,NULL,NULL,0.00),(1599,93,'2026-04-06','PAGO SAT 20.369','TRANSFERENCIA','2/26',0.01,NULL,NULL,0.00),(1600,91,'2026-04-06','JORGE LUIS ROSSI - PAGO SAT 20.369','TRANSFERENCIA','2/26',0.01,NULL,NULL,0.00),(1601,98,'2026-04-07','INICIO DEL CONTRATO DEPARTAMENTO ENTRE RIOS 746, 1º C (ALICI)','DEPÓSITO','GARANTÍA',-580000.00,NULL,NULL,0.00),(1602,98,'2026-03-06','ALQUILER DEPARTAMENTO ENTRE RIOS 746, 1º C (ALICI) PROPORCIONAL 26 DíAS','LIQ ALQUILER','03/2026',-302104.90,NULL,NULL,0.00),(1603,98,'2026-04-01','ALQUILER - DEPARTAMENTO ENTRE RIOS 746, 1º C','ALQUILER','04/2026',-360202.00,NULL,NULL,0.00);
/*!40000 ALTER TABLE `cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `indices`
--

DROP TABLE IF EXISTS `indices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `indices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acceso_creador_id` int(11) NOT NULL DEFAULT 0 COMMENT '0=sistema principal',
  `fecha` date NOT NULL,
  `valor` decimal(10,4) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_indices_fecha_tipo_ambito` (`fecha`,`tipo`,`acceso_creador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `indices`
--

LOCK TABLES `indices` WRITE;
/*!40000 ALTER TABLE `indices` DISABLE KEYS */;
INSERT INTO `indices` VALUES (1,10,'2025-11-01',2.3000,'IPC','2026-04-02 22:59:36'),(2,10,'2025-12-01',2.8000,'IPC','2026-04-02 23:00:04'),(3,10,'2026-01-01',2.9000,'IPC','2026-04-02 23:00:33'),(4,10,'2026-02-01',2.9000,'IPC','2026-04-02 23:01:02');
/*!40000 ALTER TABLE `indices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `propiedades`
--

DROP TABLE IF EXISTS `propiedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `propiedades` (
  `propiedad_id` int(11) NOT NULL AUTO_INCREMENT,
  `acceso_creador_id` int(11) DEFAULT NULL COMMENT 'NULL=sistema principal; id accesos=ámbito sofia',
  `propiedad` varchar(255) NOT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `padron` varchar(12) NOT NULL,
  `detalle` text NOT NULL,
  `ubicacion_mapa` text DEFAULT NULL COMMENT 'URL o iframe Google Maps',
  `fotos_json` text DEFAULT NULL COMMENT 'JSON rutas relativas fotos',
  `consorcio` varchar(5) NOT NULL,
  `porcentaje` decimal(6,3) DEFAULT NULL,
  `propietario_id` int(11) NOT NULL,
  `alquiler` int(11) DEFAULT NULL,
  PRIMARY KEY (`propiedad_id`),
  UNIQUE KEY `padron` (`padron`),
  KEY `fk_propietario` (`propietario_id`),
  KEY `idx_prop_acceso_creador` (`acceso_creador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `propiedades`
--

LOCK TABLES `propiedades` WRITE;
/*!40000 ALTER TABLE `propiedades` DISABLE KEYS */;
INSERT INTO `propiedades` VALUES (2,NULL,'DEPARTAMENTO AV SALTA 87, PISO 1 º C','S.M. TUCUMAN, TUCUMAN','111','DEPARTAMENTO EN 1ER PISO CON ACCESO CON PUERTA BLINDADA, LIVING COMEDOR AMPLIO, Tres dormitorios con placares completoS, DOS baño completoS, cocina  CON BARRA Y ALACENAS en buen estado de conservación, SECTOR lavadero CON BACHA EN PATIO INTERNO CON TECHO CORREDIZO, BALCON CONTRAFRENTE, todo recién pintado, EN BUEN estado de conservación.',NULL,NULL,'87',100.000,92,1),(3,NULL,'CASA COUNTRY VERA TERRA LOTE D11','YERBA BUENA, TUCUMAN','4677107','CLiving comedor amplio con puertas corredizas de vidrio, toilette, cocina comedor amplia con alacenas, y bajo mesadas de melamina, mesada de granito, y horno, lavadero con termotanque y baño de servicio. Dormitorio principal con baño en suite, dos placares con interiores. Dos dormitorios con placares e interiores. Los tres dormitorios cuentan con aire acondicionado Split (3 unidades en funcionamiento y con sus respectivos controles).  Baño completo en pasillo de distribución. Placar en pasillo sin interior, todo en buen estado y correcto funcionamiento. Jardín con lona divisoria recién colocada en todo el perímetro alambrado.\\r\\nLos ambientes se encuentran bien mantenidos, y en buen estado de conservación y recién pintados de color blanco.',NULL,NULL,'SHUJM',100.000,96,1),(4,NULL,'DEPARTAMENTO ENTRE RIOS 746, 1º C','SAN MIGUEL DE TUCUMAN, TUCUMAN','109944','Dos dormitorios con placares completoS, baño completo EN BUENAS condiciones, cocina EN FUNCIONAMIENTO,Y en buen estado de conservación, BALCON CON BACHA DE LAVADERO, todo recién pintado, EN BUEN estado de conservación.',NULL,NULL,'ALICI',100.000,97,1),(5,NULL,'OFICINA N º 5,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','5','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PLANTA BAJA, CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,97,NULL),(11,NULL,'OFICINA N º 6,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','6','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PLANTA BAJA, CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(12,NULL,'OFICINA 7,8,9,10','YERBA BUENA, TUCUMAN','78910','OFICINA DE 88 METROS2 A ESTRENAR, EN PLANTA BAJA, CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(13,NULL,'OFICINA N º 11,12,13  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','111213','Oficina DE 66 METROS2 EN PRIMER PISO, CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(14,NULL,'OFICINA N º 14,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','14','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PRIMER PISO CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(15,NULL,'OFICINA N º 15,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','15','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, PLANTA BAJA CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(16,NULL,'OFICINA N º 16,17,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','1617','Oficina A ESTRENAR CON 44 METROS2 PROPIOS, EN PRIMER PISO CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(17,NULL,'OFICINA N º 18,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','18','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PRIMER PISO CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(18,NULL,'OFICINA N º 1,2,3,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','123','Oficina A ESTRENAR CON 44 METROS2 PROPIOS, EN PRIMER PISO CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(19,NULL,'OFICINA N º 4,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','4','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PLANTA BAJA CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(20,NULL,'OFICINA N º 19,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','19','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PRIMER PISO CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(21,NULL,'OFICINA N º 20,  SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','20','Oficina A ESTRENAR CON 22 METROS2 PROPIOS, EN PRIMER PISO CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO, carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(22,NULL,'LOCAL 1 CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','1','LOCAL A ESTRENAR CON 30,85 METROS2 PROPIOS, EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE Y ACCESO A LAS GALERIAS, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(23,NULL,'CASA LOTE 89, BARRIO PRIVADO EL PORTILLO','YERBA BUENA, TUCUMAN','4778521','Casa A ESTRENAR, EN 2 plantaS, 3 DORMITORIOS CON PLACARES EN PLANTA ALTA, EL PRINCIPAL EN SUITE, baño completo, HABITACION LAVADERO. EN PLANTA BAJA : cocina SEMI INTEGRADA CON ISLA, LIVING COMEDOR AMPLIO CON DOBLE ALTURA, HABITACION DE USO MULTIPLE EN PLANTA BAJA CON BAÑO. aberturas y cerramientos en EXCELENTE estado, JARIDN CON PILETA, COCHERA TECHADA PARA 2 AUTOS, GALERIA CON ASADOR, todo recién pintado, en PERFECTO estado de conservación.',NULL,NULL,'TEO B',1.000,102,NULL),(24,NULL,'LOCAL 2, CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','2','LOCAL A ESTRENAR CON 30,85 METROS2 PROPIOS, EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE Y ACCESO A LAS GALERIAS, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,102,NULL),(25,NULL,'LOCAL 3, CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','3','LOCAL A ESTRENAR CON 50,4 METROS2 PROPIOS, EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE DE LAS GALERIAS, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  PISO FLOTANTE, cielorraso, instalación eléctrica KITCHENTE COMPLETA APTA PARA SU USO, Y SE ENCUENTRA RECIEN PINTADO DE BLANCO.',NULL,NULL,'FGH',1.000,100,NULL),(28,NULL,'LOCAL 4, CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','400','LOCAL A ESTRENAR CON 17,60 METROS2 PROPIOS, EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(30,NULL,'LOCAL 5, CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','500','LOCAL A ESTRENAR CON 18 METROS2 PROPIOS, EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE Y ACCESO A LAS GALERIAS, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL),(31,NULL,'LOCAL 6, CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','0','LOCAL A ESTRENAR CON 57,10 METROS2 PROPIOS, Y GALERIA SEMICUBIERTA INTERNA CON 54,90 METROS2 EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE Y ACCESO A LAS GALERIAS, INTERIOR AL PATIO CENTRAL, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',NULL,100,NULL),(32,NULL,'LOCAL 7, CASONA SALAS Y VALDEZ 1050','YERBA BUENA, TUCUMAN','700','LOCAL A ESTRENAR CON 50,50 METROS2 PROPIOS, EN CASONA CENTRAL, CON VISTA Y FACHADA AL FRENTE Y ACCESO A LAS GALERIAS, CUENTA  CON CARPINTERIAS DE ALUMINIO, PUERTA DE VIDRIO,  carpeta lisa para colocación de piso flotante, cielorraso, instalación eléctrica básica y revoque fino.',NULL,NULL,'FGH',1.000,100,NULL);
/*!40000 ALTER TABLE `propiedades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acceso_creador_id` int(11) DEFAULT NULL COMMENT 'NULL=sistema principal; id accesos=ámbito sofia',
  `apellido` varchar(150) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `cuit` varchar(25) DEFAULT NULL,
  `domicilio` varchar(200) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `consorcio` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_acceso_creador` (`acceso_creador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,NULL,'CAJA CENTRAL','0','0','OFICINA',NULL,NULL,NULL),(88,10,'HERRERA HECTOR HUGO','14480618','20144806183','BASCARY 1200, COUNTRY PRADERAS, LOTE 260, YERBA BUENA , TUCUMAN (4107)','hectorhugoherrera@gmail.com','3816407608',NULL),(89,10,'HERRERA LLOBETA SOFIA NAZARENA','31842185','23318421854','BARRIO PRIVADO, EL PORTILLO, LOTE 78, YERBA BUENA, TUCUMAN','sofiaherrerallobeta@hotmail.com','3812459560',NULL),(90,10,'CONSORCIO BASCARY 1200','1111','11111','SWFWF','','','999'),(91,NULL,'TRANSFERENCIAS','0','0','BANCO',NULL,NULL,NULL),(92,NULL,'INES GONZALEZ ALVO','29.738.577','29.738.577','MONTEAGUDO Nº 685','','3816455299',NULL),(93,NULL,'JORGE LUIS ROSSI','36.039.845','36.039.845','AV SALTA 87, PISO 1 º C, SAN MIGUEL DE TUCUMAN','','3865668306',NULL),(94,NULL,'FLORENCIA ALDANA ALVAREZ ESCALANTE','38.754.914','27-38.754.914-0','SAN LUIS 312, 8 D 0, SAN MIGUEL DE TUCUMAN','','',NULL),(95,NULL,'CASTILLO CASAÑAS AMALIA','34.880.739','34.880.739','COUNTRY VERATERRA LOTE D11, YERBA BUENA, TUCUMáN','','3814629084',NULL),(96,NULL,'MARIA NOEMI ARGAÑARAZ','14.225.238 ','14.225.238 ','SANTA FE 155 PISO 4, DE SAN MIGUEL DE TUCUMáN, TUCUMAN','','3815156322',NULL),(97,NULL,'GLORIA NELLY GUZMAN','3.682.028  ','3.682.028  ','25 DE MAYO Nº 950, PISO 7MO DEPARTAMENTO A, TORRE D, S.M.TUCUMáN,','','3812005386',NULL),(98,NULL,'ROMANO YOHANA BRIGITTE','37.502.270','37.502.270','ENTRE RIOS 746 PISO 1, DEPTO C,   SAN MIGUEL DE TUCUMáN','','3813346126',NULL),(99,NULL,'ROMANO SONIA JESSICA','32.200.455','32.200.455','SANTO DOMINGO 637, Bº CAPITáN CANDELARIA, BANDA DEL RIO SALI, CRUZ ALTA, TUCUMáN.','','0',NULL),(100,NULL,'FEDERICO GARCIA HAMILTON','16.691.840 ','16.691.840 ','VIAMONTE 395, SAN MIGUEL DE TUCUMáN, TUCUMAN','','3814747542',NULL),(101,NULL,'FIGUEROA PAMELA','33.818.672','33.818.672','MUÑECAS 730, PISO 5 TO A, SAN MIGUEL DE TUCUMAN , TUCUMAN','','3815060904',NULL),(102,NULL,'LILIANA PEDICONE','23.984.432','23.984.432','BARRIO PRIVADO EL PORTILLO LOTE 87, YERBA BUENA, TUCUMAN ','','3815120135',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'BGHInmobiliaria'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-09 19:44:18
