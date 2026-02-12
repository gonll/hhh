-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sistemahhh26
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accesos`
--

LOCK TABLES `accesos` WRITE;
/*!40000 ALTER TABLE `accesos` DISABLE KEYS */;
INSERT INTO `accesos` VALUES (1,'adminhugo','$2y$10$bwPvLjqJf59zJy9DRiMHterQkedIpeCR70lmkuKFjsMDzcvHlp5e6',3,NULL,'2026-02-03 13:25:47'),(2,'silvana','$2y$10$7APW93WrRs5A6aW5QkGBau8WfSgazKIVE0PnKRe/Zz5wURQ9vXbCe',2,1,'2026-02-03 16:32:18'),(3,'usuario','$2y$10$il0R0KXa1ZjMgP2u14YXe.x2g1VskeE3HI9ddNrEmBAtiV8jHHzSS',1,1,'2026-02-03 16:48:17'),(5,'enrique','$2y$10$.CZxgVSQkKewMQvzE2jzUORgFW0sKrlmgcmEoAFJ/FcYdTp5kdB56',0,1,'2026-02-06 17:54:51');
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
  `destino` varchar(50) DEFAULT 'VIVIENDA',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `precio_convenido` decimal(10,2) DEFAULT NULL,
  `fecha_firma` date DEFAULT NULL,
  `monto_deposito` decimal(10,2) DEFAULT NULL,
  `estado` enum('VIGENTE','BAJA') DEFAULT 'VIGENTE',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`alquiler_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alquileres`
--

LOCK TABLES `alquileres` WRITE;
/*!40000 ALTER TABLE `alquileres` DISABLE KEYS */;
INSERT INTO `alquileres` VALUES (1,6,3,NULL,3,NULL,24,'VIVIENDA','2026-01-30','2028-01-31',450000.00,'2026-01-30',450000.00,'BAJA','2026-01-30 21:18:36'),(2,3,3,NULL,3,NULL,24,'VIVIENDA','2026-01-30','2028-01-31',500000.00,'2026-01-30',500000.00,'','2026-01-30 21:28:21'),(6,6,3,NULL,3,NULL,24,'VIVIENDA','2026-01-30','2028-01-31',500000.00,'2026-01-30',500000.00,'','2026-01-30 22:04:57'),(11,3,4,NULL,4,NULL,24,'VIVIENDA','2026-01-31','2028-01-31',123.00,'2026-01-31',123.00,'','2026-01-31 22:11:58'),(12,3,4,NULL,4,NULL,24,'VIVIENDA','2026-02-01','2028-02-29',500000.00,'2026-02-01',500000.00,'','2026-02-01 12:49:05'),(13,6,4,NULL,4,NULL,24,'VIVIENDA','2026-02-05','2028-02-29',500000.00,'2026-02-01',500000.00,'','2026-02-01 13:04:20'),(14,6,4,NULL,4,NULL,24,'VIVIENDA','2026-02-10','2028-02-29',500000.00,'2026-02-01',500000.00,'','2026-02-01 14:36:08'),(15,3,4,NULL,4,NULL,24,'VIVIENDA','2026-02-10','2028-02-29',500000.00,'2026-02-01',500000.00,'','2026-02-01 14:55:55'),(16,6,4,NULL,4,NULL,24,'VIVIENDA','2026-02-01','2028-02-29',123.00,'2026-02-01',123.00,'','2026-02-01 16:27:17'),(17,3,4,NULL,4,NULL,24,'VIVIENDA','2026-02-15','2028-02-29',300000.00,'2026-02-01',300000.00,'','2026-02-01 19:10:24'),(18,3,4,NULL,4,NULL,24,'VIVIENDA','2025-01-01','2027-01-31',100000.00,'2026-02-02',100000.00,'','2026-02-01 23:49:20'),(19,3,4,NULL,4,NULL,24,'VIVIENDA','2025-12-01','2027-12-31',100000.00,'2026-02-02',100000.00,'','2026-02-01 23:51:25'),(20,10,4,NULL,4,NULL,24,'VIVIENDA','2026-02-02','2028-02-29',400000.00,'2026-02-02',400000.00,'','2026-02-02 22:50:21'),(21,6,4,NULL,4,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',800000.00,'2026-02-03',800000.00,'','2026-02-02 23:51:20'),(22,3,4,NULL,4,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',500000.00,'2026-02-03',500000.00,'','2026-02-03 00:09:57'),(23,9,4,NULL,4,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',100000.00,'2026-02-03',100000.00,'','2026-02-03 00:12:24'),(24,17,4,NULL,4,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',50000.00,'2026-02-03',50000.00,'','2026-02-03 20:33:05'),(25,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',30000.00,'2026-02-03',30000.00,'','2026-02-03 20:40:01'),(26,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',40000.00,'2026-02-03',40000.00,'','2026-02-03 20:42:14'),(27,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',40000.00,'2026-02-03',40000.00,'','2026-02-03 20:45:58'),(28,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',60000.00,'2026-02-03',60000.00,'','2026-02-03 20:48:26'),(29,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',50000.00,'2026-02-03',50000.00,'','2026-02-03 20:50:31'),(30,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',50000.00,'2026-02-03',50000.00,'','2026-02-03 20:50:34'),(31,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',50000.00,'2026-02-03',50000.00,'','2026-02-03 20:50:35'),(32,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',50000.00,'2026-02-03',50000.00,'','2026-02-03 20:50:35'),(33,18,3,NULL,3,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',70000.00,'2026-02-03',70000.00,'','2026-02-03 20:55:09'),(34,17,4,NULL,4,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',40000.00,'2026-02-03',40000.00,'','2026-02-03 20:56:44'),(35,20,4,NULL,4,NULL,24,'VIVIENDA','2025-12-17','2027-12-31',80000.00,'2026-02-03',80000.00,'','2026-02-03 20:59:12'),(36,17,4,NULL,4,NULL,24,'VIVIENDA','2025-12-05','2027-12-31',100000.00,'2026-02-03',100000.00,'','2026-02-03 21:04:58'),(37,20,4,NULL,4,NULL,24,'VIVIENDA','2026-02-03','2028-02-29',50000.00,'2026-02-03',50000.00,'','2026-02-03 21:05:37'),(38,19,3,NULL,3,NULL,24,'VIVIENDA','2026-02-04','2028-02-29',100000.00,'2026-02-04',100000.00,'','2026-02-04 00:30:33'),(39,19,3,NULL,3,NULL,24,'VIVIENDA','2026-02-04','2028-02-29',200000.00,'2026-02-04',200000.00,'','2026-02-04 00:37:16'),(40,19,3,NULL,3,NULL,24,'VIVIENDA','2026-02-04','2028-02-29',500000.00,'2026-02-04',500000.00,'','2026-02-04 00:41:12');
/*!40000 ALTER TABLE `alquileres` ENABLE KEYS */;
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
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`movimiento_id`),
  KEY `fk_usuario_cuentas` (`usuario_id`),
  CONSTRAINT `fk_usuario_cuentas` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=186 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas`
--

LOCK TABLES `cuentas` WRITE;
/*!40000 ALTER TABLE `cuentas` DISABLE KEYS */;
INSERT INTO `cuentas` VALUES (184,6,'2026-02-10','LIQUIDACIÓN DE EXPENSAS Ordinarias 0,00 - Extraordinarias 0,00','LIQ EXPENSAS','02/2026',0.00,0.00),(185,5,'2026-02-10','LIQUIDACIÓN DE EXPENSAS Ordinarias 0,00 - Extraordinarias 0,00','LIQ EXPENSAS','02/2026',0.00,0.00);
/*!40000 ALTER TABLE `cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gasoil`
--

DROP TABLE IF EXISTS `gasoil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gasoil` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `cantidad` decimal(10,2) NOT NULL COMMENT '+ carga sisterna, - tractor',
  `concepto` varchar(255) DEFAULT NULL,
  `pdt_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_pdt` (`pdt_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gasoil`
--

LOCK TABLES `gasoil` WRITE;
/*!40000 ALTER TABLE `gasoil` DISABLE KEYS */;
/*!40000 ALTER TABLE `gasoil` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `indices`
--

DROP TABLE IF EXISTS `indices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `indices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `valor` decimal(10,4) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `fecha` (`fecha`,`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `indices`
--

LOCK TABLES `indices` WRITE;
/*!40000 ALTER TABLE `indices` DISABLE KEYS */;
INSERT INTO `indices` VALUES (1,'2025-10-01',2.3000,'IPC','2026-01-31 13:40:19'),(3,'2025-09-01',2.1000,'IPC','2026-01-31 13:41:27'),(4,'2025-11-01',2.3000,'IPC','2026-01-31 13:41:43'),(6,'2025-01-01',2.2000,'IPC','2026-01-31 13:44:55'),(7,'2025-02-01',2.4000,'IPC','2026-01-31 13:45:09'),(8,'2025-03-01',2.4000,'IPC','2026-01-31 13:45:24'),(9,'2025-04-01',2.8000,'IPC','2026-01-31 13:45:46'),(10,'2025-05-01',1.5000,'IPC','2026-01-31 13:46:10'),(11,'2025-06-01',1.6000,'IPC','2026-01-31 13:46:22'),(12,'2025-07-01',1.9000,'IPC','2026-01-31 13:46:37'),(13,'2025-08-01',1.9000,'IPC','2026-01-31 13:47:05'),(14,'2025-12-01',2.8000,'IPC','2026-02-01 16:29:58'),(15,'2026-01-01',2.9000,'IPC','2026-02-10 23:13:28');
/*!40000 ALTER TABLE `indices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pdt`
--

DROP TABLE IF EXISTS `pdt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pdt` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo_horas` enum('Horas tractos','Horas Comunes') NOT NULL DEFAULT 'Horas Comunes',
  `tractor` varchar(100) DEFAULT NULL,
  `fecha` date NOT NULL,
  `horas` decimal(5,2) DEFAULT 0.00,
  `cant_gasoil` decimal(6,2) DEFAULT NULL,
  `cambio_aceite` tinyint(1) DEFAULT 0,
  `en_cc` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `pdt_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pdt`
--

LOCK TABLES `pdt` WRITE;
/*!40000 ALTER TABLE `pdt` DISABLE KEYS */;
INSERT INTO `pdt` VALUES (1,3,'Horas Comunes',NULL,'2026-02-06',12.00,NULL,0,0,'','2026-02-11 22:23:40','2026-02-11 22:23:40'),(2,4,'Horas Comunes',NULL,'2026-02-06',2.00,NULL,0,0,'','2026-02-11 22:23:58','2026-02-11 22:23:58'),(3,3,'Horas Comunes',NULL,'2026-02-06',12.00,NULL,0,0,'','2026-02-11 22:24:18','2026-02-11 22:24:18');
/*!40000 ALTER TABLE `pdt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `propiedades`
--

DROP TABLE IF EXISTS `propiedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `propiedades` (
  `propiedad_id` int(11) NOT NULL AUTO_INCREMENT,
  `propiedad` varchar(255) NOT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `padron` varchar(12) NOT NULL,
  `detalle` text NOT NULL,
  `consorcio` varchar(5) NOT NULL,
  `porcentaje` decimal(6,3) DEFAULT NULL,
  `propietario_id` int(11) NOT NULL,
  `alquiler` int(11) DEFAULT NULL,
  PRIMARY KEY (`propiedad_id`),
  UNIQUE KEY `padron` (`padron`),
  KEY `fk_propietario` (`propietario_id`),
  CONSTRAINT `fk_propietario` FOREIGN KEY (`propietario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `propiedades`
--

LOCK TABLES `propiedades` WRITE;
/*!40000 ALTER TABLE `propiedades` DISABLE KEYS */;
INSERT INTO `propiedades` VALUES (21,'dpto 1° piso b, estados unidos 150','san miguel de tucuman , tucuman','831801','Tres dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','150',7.830,7,NULL),(24,'dpto 3° piso b, estados unidos 150','san miguel de tucuman , tucuman','831505','Tres dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','150',7.900,7,NULL),(26,'local comercial  (PB), laprida 430','san miguel de tucuman , tucuman','504422','local comercial a la calle','430',3.731,10,NULL),(27,'dpto pbc, (planta baja contrafrente), laprida 430','san miguel de tucuman , tucuman','504423','Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',3.505,7,NULL),(28,'dpto pbd, (planta baja contrafrente), laprida 430','san miguel de tucuman , tucuman','504424','Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',3.547,7,NULL),(29,'dpto 1° piso a, laprida 430','san miguel de tucuman , tucuman','504425','Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',5.308,13,NULL),(30,'dpto 1° piso b, laprida 430','san miguel de tucuman , tucuman','504426','Un dormitorio con placar completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con alacena en buen estado de conservación, bacha de acero inoxidable con monocomando, con cocina y lavadero marca Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',2.938,7,NULL),(31,'dpto 1° piso c, laprida 430','san miguel de tucuman , tucuman','504427','Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',3.490,15,NULL),(32,'dpto 1° piso d, laprida 430','san miguel de tucuman , tucuman','504428','Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando,  todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',3.537,7,NULL),(33,'dpto 2° piso a, laprida 430','san miguel de tucuman , tucuman','504429','Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',5.353,16,NULL),(34,'dpto 2° piso b, laprida 430','san miguel de tucuman , tucuman','504430','Un dormitorio con placar completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con alacena en buen estado de conservación, bacha de acero inoxidable con monocomando, con cocina y lavadero marca Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',2.634,7,NULL),(35,'dpto 2° piso c, laprida 430','san miguel de tucuman , tucuman','504431','Un dormitorio con placar completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con alacena en buen estado de conservación, bacha de acero inoxidable con monocomando, con cocina y lavadero marca Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.','430',3.883,7,NULL);
/*!40000 ALTER TABLE `propiedades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `linea` tinyint(4) NOT NULL DEFAULT 1,
  `articulo` varchar(255) NOT NULL DEFAULT '',
  `orden` int(11) NOT NULL DEFAULT 0,
  `cantidad` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposito` varchar(255) DEFAULT NULL,
  `fecha_vta` date DEFAULT NULL,
  `cant_vta` decimal(12,2) DEFAULT 0.00,
  `vendida_a_id` int(11) DEFAULT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `precio_vta` decimal(12,2) DEFAULT 0.00,
  `fecha_fact` date DEFAULT NULL,
  `cant_fact` decimal(12,2) DEFAULT 0.00,
  `facturada_a_id` int(11) DEFAULT NULL,
  `precio_fac` decimal(12,2) DEFAULT 0.00,
  `n_fact` varchar(50) DEFAULT NULL,
  `n_remt` varchar(50) DEFAULT NULL,
  `operacion` int(11) DEFAULT NULL,
  `venta_movimiento_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock`
--

LOCK TABLES `stock` WRITE;
/*!40000 ALTER TABLE `stock` DISABLE KEYS */;
INSERT INTO `stock` VALUES (1,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',45896,600.00,'cacsa',NULL,0.00,NULL,NULL,0.00,NULL,0.00,NULL,0.00,NULL,NULL,NULL,NULL,'2026-02-07 13:46:56'),(3,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',12,234.00,'cacsa',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0.00,NULL,NULL,NULL,NULL,'2026-02-07 13:57:59'),(6,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',24,123.00,'cacsa','2026-02-07',123.00,4,NULL,37000.00,NULL,0.00,NULL,0.00,NULL,NULL,2,179,'2026-02-07 14:11:09'),(7,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',56,234.00,'cacsa','2026-02-07',100.00,4,NULL,10000.00,NULL,0.00,NULL,0.00,NULL,NULL,5,182,'2026-02-07 14:11:16'),(8,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',87,345.00,'cacsa',NULL,0.00,NULL,NULL,0.00,NULL,0.00,NULL,0.00,NULL,NULL,NULL,NULL,'2026-02-07 14:11:24'),(9,'2026-02-07',1,'BOLSASDE 50 KGRS  DE AZUCAR BLANCO MARCA CONCEPCION',2,222.00,'CACSA','2026-02-07',222.00,4,3,20000.00,NULL,0.00,NULL,0.00,NULL,NULL,1,178,'2026-02-07 14:11:39'),(10,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',555,55.00,'cacsa',NULL,0.00,NULL,NULL,0.00,NULL,0.00,NULL,0.00,NULL,NULL,NULL,NULL,'2026-02-07 14:11:46'),(11,'2026-02-07',1,'bolsas de azucar blanco marca concepcion',34,22.00,'cacsa','2026-02-07',22.00,3,NULL,10000.00,NULL,0.00,NULL,0.00,NULL,NULL,3,183,'2026-02-07 14:14:46');
/*!40000 ALTER TABLE `stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tabla_salarial`
--

DROP TABLE IF EXISTS `tabla_salarial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tabla_salarial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `valor_hora_comun` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_hora_tractor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vigencia_desde` date DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vigencia` (`vigencia_desde`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tabla_salarial`
--

LOCK TABLES `tabla_salarial` WRITE;
/*!40000 ALTER TABLE `tabla_salarial` DISABLE KEYS */;
INSERT INTO `tabla_salarial` VALUES (1,35.23,36.23,'2026-01-01','','2026-02-06 19:35:15');
/*!40000 ALTER TABLE `tabla_salarial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apellido` varchar(150) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `cuit` varchar(25) DEFAULT NULL,
  `domicilio` varchar(200) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `consorcio` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'CAJA CENTRAL','0','0','OFICINA',NULL,NULL,NULL),(2,'HERRERA HECTOR HUGO','14480618','20144806183','BASCARY 1200, COUNTRY PRADERAS, LOTE 260, YERBA BUENA , TUCUMAN (4107)','hectorhugoherrera@gmail.com','3816407608',NULL),(3,'LLOBETA GLADYS NAZARENA','14553741','27145537415','BASCARY 1200, COUNTRY PRADERAS, LOTE 260, YERBA BUENA , TUCUMAN (4107)','nazarenallobeta@gmail.com','3814754206',NULL),(4,'HERRERA LLOBETA VIRGINIA','45126992','27451269920','BASCARY 1200, COUNTRY PRADERAS, LOTE 260, YERBA BUENA , TUCUMAN (4107)',NULL,NULL,NULL),(5,'CONSORCIO LAPRIDA 430','111','11111','LAPRIDA 430, SAN MIGUEL DE TUCUMAN, TUCUMAN','','','430'),(6,'CONSORCIO EE UU 101','212234','21223','ESTADOS UNIDOS 101, SAN MIGUEL DE TUCUMAN','','','101'),(7,'HERRERA Y LLOBETA SRL','30708875593','30708875593','LAMADRID 377, PISO 4° OFICINA D, SAN MIGUEL DE TUCUMAN, TUCUMAN','herrerayllobeta@gmail.com','3816407608',NULL),(8,'CUNEO ALBA MARINA','1','','','','',NULL),(9,'MIRANDA PABLO DAVID','1','','','','',NULL),(10,'METTOLA RODRIGO MARTIN','1','','','','',NULL),(11,'REYNOSO VALERIA NATALIA','1','','','','',NULL),(12,'LOPEZ INES MABEL','1','','','','',NULL),(13,'LOPEZ MARNERO LUCIA','1','','','','',NULL),(14,'BARCAT MIGUEL','1','','','','',NULL),(15,'ONTIVERO  EDITH','1','','','','',NULL),(16,'LUCIANI TOMAS','1','','','','',NULL),(17,'ALE MARIA NOELIA','1','','','','',NULL),(18,'CHOQUE ALEJANDRO RICARDO','1','','','','',NULL),(19,'FLORES ROXANA ELIZABETH','1','','','','',NULL),(20,'VEGA MARCELA','1','','','','',NULL),(21,'BUSTAMANTE ANA MARIA','1','','','','',NULL),(22,'MOLINA ALVARO JOSE','1','','','','',NULL),(23,'HILAL MARTIN','1','','','','',NULL),(24,'BERNACHI ABIGAIL SABRINA','1','','','','',NULL),(25,'LOPEZ PEÑA JORGE RAMON','1','','','','',NULL),(26,'ESPINOSA DIEGO','1','','','','',NULL),(27,'SILVA MANCO  MARIA JULIA','1','','','','',NULL),(28,'CORREA RODOLFO ANTONIO','1','','','','',NULL),(29,'AYBAR CARLOS VALENTIN','1','','','','',NULL),(30,'SOUBIE MARIA EMILIA','1','','','','',NULL),(31,'DIAS ESTELA EUGENIA','1','','','','',NULL),(32,'SUAREZ ARAUJO LUCIA LIDIA','1','','','','',NULL),(33,'MARIN CARLOS ARIEL RAMIRO','1','','','','',NULL),(34,'VENTURINO MARIA JAQUELINE','1','','','','',NULL),(35,'CHAVEZ RAMON ANSELMO','1','','','','',NULL),(36,'TERAN JORGE LUIS','1','','','','',NULL),(37,'ASTIGARRAGA MARIA INES','1','','','','',NULL),(38,'NAVARRO LIDIA CRISTINA','1','','','','',NULL),(39,'JAIME CARLOS NEIRO','1','','','','',NULL),(40,'ALONSO MARIA VALERIA','1','','','','',NULL),(41,'MARQUEZ DANIEL EDUARDO','1','','','','',NULL),(42,'SAFE CAPISANO YENIFER','1','','','','',NULL),(43,'VELIZ MARIA AGUSTINA','1','','','','',NULL),(44,'TANONI FLAVIO LUIS','1','','','','',NULL),(45,'BRITO CELINA YUDITH','1','','','','',NULL),(46,'MOLINA FEDERICO','1','','','','',NULL),(47,'CASTILLO MIGUEL FERNANDO','1','','','','',NULL),(48,'PONCE MATIAS RAMON','1','','','','',NULL),(49,'DORAO ANA MARIA','1','','','','',NULL),(50,'VILLEGA CARLOS FRANCISCO','1','','','','',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sistemahhh26'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-11 21:09:16
