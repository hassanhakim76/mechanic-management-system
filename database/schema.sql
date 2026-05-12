
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `precision` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `precision`;
DROP TABLE IF EXISTS `customer_letter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_letter` (
  `clid` int NOT NULL AUTO_INCREMENT,
  `customerid` int NOT NULL,
  `tid` int NOT NULL,
  `sentdate` datetime DEFAULT NULL,
  PRIMARY KEY (`clid`),
  KEY `idx_cl_customer` (`customerid`),
  KEY `idx_cl_tid` (`tid`),
  KEY `idx_cl_sentdate` (`sentdate`),
  CONSTRAINT `fk_cl_customer` FOREIGN KEY (`customerid`) REFERENCES `customers` (`CustomerID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cl_template` FOREIGN KEY (`tid`) REFERENCES `letter_template` (`tid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_vehicle` (
  `CVID` int NOT NULL AUTO_INCREMENT,
  `CustomerID` int NOT NULL,
  `Plate` varchar(20) DEFAULT NULL,
  `VIN` varchar(80) DEFAULT NULL,
  `Make` varchar(40) DEFAULT NULL,
  `Model` varchar(40) DEFAULT NULL,
  `Year` varchar(4) DEFAULT NULL,
  `Color` varchar(30) DEFAULT NULL,
  `Status` varchar(1) DEFAULT NULL,
  `Engine` varchar(50) DEFAULT NULL,
  `Detail` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`CVID`),
  KEY `idx_cv_customer` (`CustomerID`),
  CONSTRAINT `fk_cv_customer` FOREIGN KEY (`CustomerID`) REFERENCES `customers` (`CustomerID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_customer_vehicle_require_min_fields_ins` BEFORE INSERT ON `customer_vehicle` FOR EACH ROW BEGIN
    IF TRIM(COALESCE(NEW.Plate, '')) = '' AND TRIM(COALESCE(NEW.VIN, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle plate or VIN is required for permanent records';
    END IF;

    IF TRIM(COALESCE(NEW.Make, '')) = '' OR TRIM(COALESCE(NEW.Model, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle make and model are required for permanent records';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `CustomerID` int NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(30) DEFAULT NULL,
  `LastName` varchar(30) DEFAULT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Cell` varchar(15) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `Address` varchar(100) DEFAULT NULL,
  `City` varchar(60) DEFAULT NULL,
  `Province` varchar(40) DEFAULT NULL,
  `PostalCode` varchar(7) DEFAULT NULL,
  `PhoneExt` int DEFAULT NULL,
  `subscribe` bit(1) DEFAULT NULL,
  PRIMARY KEY (`CustomerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_customers_require_min_fields_ins` BEFORE INSERT ON `customers` FOR EACH ROW BEGIN
    IF TRIM(COALESCE(NEW.FirstName, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer first name is required for permanent records';
    END IF;

    IF TRIM(COALESCE(NEW.Phone, '')) = '' AND TRIM(COALESCE(NEW.Cell, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer phone or cell is required for permanent records';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `draft_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `draft_customers` (
  `draft_customer_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cell` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_ext` int DEFAULT NULL,
  `subscribe` tinyint(1) DEFAULT NULL,
  `notes` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','approved','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_customer_id` int DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`draft_customer_id`),
  KEY `idx_draft_customers_status_created` (`status`,`created_at`),
  KEY `idx_draft_customers_phone` (`phone`),
  KEY `idx_draft_customers_cell` (`cell`),
  KEY `idx_draft_customers_approved_customer` (`approved_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `draft_promotion_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `draft_promotion_log` (
  `promotion_id` int NOT NULL AUTO_INCREMENT,
  `draft_customer_id` int NOT NULL,
  `draft_vehicle_id` int DEFAULT NULL,
  `draft_wo_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `cvid` int DEFAULT NULL,
  `woid` int DEFAULT NULL,
  `action` enum('approved','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by_user_id` int unsigned DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`promotion_id`),
  KEY `idx_promo_drafts` (`draft_customer_id`,`draft_vehicle_id`,`draft_wo_id`),
  KEY `idx_promo_real` (`customer_id`,`cvid`,`woid`),
  KEY `idx_promo_action_time` (`action`,`performed_at`),
  KEY `fk_promo_draft_vehicle` (`draft_vehicle_id`),
  KEY `fk_promo_draft_wo` (`draft_wo_id`),
  CONSTRAINT `fk_promo_draft_customer` FOREIGN KEY (`draft_customer_id`) REFERENCES `draft_customers` (`draft_customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_promo_draft_vehicle` FOREIGN KEY (`draft_vehicle_id`) REFERENCES `draft_vehicles` (`draft_vehicle_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_promo_draft_wo` FOREIGN KEY (`draft_wo_id`) REFERENCES `draft_work_orders` (`draft_wo_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `draft_status_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `draft_status_log` (
  `draft_status_log_id` bigint NOT NULL AUTO_INCREMENT,
  `draft_wo_id` int NOT NULL,
  `old_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by_user_id` int unsigned NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`draft_status_log_id`),
  KEY `idx_draft_status_log_wo_created` (`draft_wo_id`,`created_at`),
  KEY `idx_draft_status_log_action` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `draft_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `draft_vehicles` (
  `draft_vehicle_id` int NOT NULL AUTO_INCREMENT,
  `draft_customer_id` int NOT NULL,
  `plate` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vin` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `make` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engine` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','approved','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_cvid` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`draft_vehicle_id`),
  KEY `idx_draft_veh_customer` (`draft_customer_id`),
  KEY `idx_draft_veh_plate` (`plate`),
  KEY `idx_draft_veh_vin` (`vin`),
  KEY `idx_draft_veh_approved_cvid` (`approved_cvid`),
  CONSTRAINT `fk_draft_vehicle_draft_customer` FOREIGN KEY (`draft_customer_id`) REFERENCES `draft_customers` (`draft_customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `draft_work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `draft_work_orders` (
  `draft_wo_id` int NOT NULL AUTO_INCREMENT,
  `draft_customer_id` int NOT NULL,
  `draft_vehicle_id` int DEFAULT NULL,
  `mileage` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_date` datetime DEFAULT NULL,
  `wo_req1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_req2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_req3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_req4` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_req5` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_status` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wo_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mechanic_note` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `req1` int DEFAULT NULL,
  `req2` int DEFAULT NULL,
  `req3` int DEFAULT NULL,
  `req4` int DEFAULT NULL,
  `req5` int DEFAULT NULL,
  `priority` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mechanic` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `testdrive` int DEFAULT NULL,
  `checksum` int DEFAULT NULL,
  `status` enum('draft','approved','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `readiness_state` enum('incomplete','ready') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'incomplete',
  `missing_reasons` text COLLATE utf8mb4_unicode_ci,
  `escalation_level` enum('none','warning','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `last_validated_at` datetime DEFAULT NULL,
  `last_validated_by_user_id` int unsigned DEFAULT NULL,
  `approved_woid` int DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`draft_wo_id`),
  KEY `idx_draft_wo_status_created` (`status`,`created_at`),
  KEY `idx_draft_wo_customer` (`draft_customer_id`),
  KEY `idx_draft_wo_vehicle` (`draft_vehicle_id`),
  KEY `idx_draft_wo_approved_woid` (`approved_woid`),
  KEY `idx_draft_wo_ready_escalation` (`status`,`readiness_state`,`escalation_level`,`created_at`),
  CONSTRAINT `fk_draft_wo_draft_customer` FOREIGN KEY (`draft_customer_id`) REFERENCES `draft_customers` (`draft_customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_draft_wo_draft_vehicle` FOREIGN KEY (`draft_vehicle_id`) REFERENCES `draft_vehicles` (`draft_vehicle_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `EmployeeID` int NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(30) DEFAULT NULL,
  `LastName` varchar(30) DEFAULT NULL,
  `Display` varchar(40) DEFAULT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Cell` varchar(15) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `Address` varchar(100) DEFAULT NULL,
  `City` varchar(50) DEFAULT NULL,
  `Province` varchar(40) DEFAULT NULL,
  `PostalCode` varchar(7) DEFAULT NULL,
  `Position` varchar(30) DEFAULT NULL,
  `Status` varchar(1) DEFAULT NULL,
  PRIMARY KEY (`EmployeeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `imagelibrary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `imagelibrary` (
  `imageid` int NOT NULL,
  `woid` int DEFAULT NULL,
  `imagetype` varchar(10) DEFAULT NULL,
  `imagedate` datetime DEFAULT NULL,
  `imageblob` mediumblob,
  PRIMARY KEY (`imageid`),
  KEY `idx_img_woid` (`woid`),
  KEY `idx_img_date` (`imagedate`),
  CONSTRAINT `fk_img_workorder` FOREIGN KEY (`woid`) REFERENCES `work_order` (`WOID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inspection_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_categories` (
  `category_id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_code` int NOT NULL DEFAULT '0',
  `category_name` varchar(80) NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_inspection_categories_name` (`category_name`),
  UNIQUE KEY `uq_inspection_categories_code` (`category_code`),
  KEY `idx_inspection_categories_active_order` (`active`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inspection_item_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_item_master` (
  `master_item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `item_code` int NOT NULL DEFAULT '0',
  `item_number` int NOT NULL,
  `item_label` varchar(120) NOT NULL,
  `check_description` varchar(255) DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`master_item_id`),
  UNIQUE KEY `uq_inspection_item_master_number` (`item_number`),
  UNIQUE KEY `uq_inspection_item_master_category_code` (`category_id`,`item_code`),
  KEY `idx_inspection_item_master_category_order` (`category_id`,`active`,`display_order`),
  CONSTRAINT `fk_iim_category` FOREIGN KEY (`category_id`) REFERENCES `inspection_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `letter_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `letter_template` (
  `tid` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `subject` varchar(50) DEFAULT NULL,
  `content` mediumtext,
  `status` varchar(1) DEFAULT NULL,
  PRIMARY KEY (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `role_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(30) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ucda_inspection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ucda_inspection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `woid` int DEFAULT NULL,
  `inspection_year` int DEFAULT NULL,
  `inspection_month` int DEFAULT NULL,
  `inspection_day` int DEFAULT NULL,
  `unitofmeasurementused` varchar(4) DEFAULT NULL,
  `licenceeinfo` varchar(255) DEFAULT NULL,
  `vehicleyear` int DEFAULT NULL,
  `vehiclemake` varchar(30) DEFAULT NULL,
  `vehiclemodel` varchar(30) DEFAULT NULL,
  `vehiclevin` varchar(20) DEFAULT NULL,
  `odometerreading` int DEFAULT NULL,
  `odometertype` varchar(4) DEFAULT NULL,
  `mechanicname` varchar(50) DEFAULT NULL,
  `tradecertificatenumber` varchar(20) DEFAULT NULL,
  `certificatenumber` varchar(20) DEFAULT NULL,
  `inspectionresultpass` bit(1) DEFAULT NULL,
  `secondinspectionyesno` bit(1) DEFAULT NULL,
  `inspectionreportdetails` mediumtext,
  `gastanklevel` int DEFAULT NULL,
  `frontrotorthickleft` varchar(6) DEFAULT NULL,
  `frontrotorthickright` varchar(6) DEFAULT NULL,
  `frontinnerpadthickleft` varchar(6) DEFAULT NULL,
  `frontinnerpadthickright` varchar(6) DEFAULT NULL,
  `frontouterpadthickleft` varchar(6) DEFAULT NULL,
  `frontouterpadthickright` varchar(6) DEFAULT NULL,
  `frontdrumshoethickleft` varchar(7) DEFAULT NULL,
  `frontdrumshoethickright` varchar(7) DEFAULT NULL,
  `frontdrumdiameterleft` varchar(7) DEFAULT NULL,
  `frontdrumdiameterright` varchar(7) DEFAULT NULL,
  `rearrotorthickleft` varchar(6) DEFAULT NULL,
  `rearrotorthickright` varchar(6) DEFAULT NULL,
  `rearinnerpadthickleft` varchar(6) DEFAULT NULL,
  `rearinnerpadthickright` varchar(6) DEFAULT NULL,
  `rearouterpadthickleft` varchar(6) DEFAULT NULL,
  `rearouterpadthickright` varchar(6) DEFAULT NULL,
  `reardrumshoethickleft` varchar(7) DEFAULT NULL,
  `reardrumshoethickright` varchar(7) DEFAULT NULL,
  `reardrumdiameterleft` varchar(7) DEFAULT NULL,
  `reardrumdiameterright` varchar(7) DEFAULT NULL,
  `frontlefttirethreaddepth` varchar(6) DEFAULT NULL,
  `frontrighttirethreaddepth` varchar(6) DEFAULT NULL,
  `rearlefttirethreaddepth` varchar(6) DEFAULT NULL,
  `rearrighttirethreaddepth` varchar(6) DEFAULT NULL,
  `frontlefttireinflationpressure` int DEFAULT NULL,
  `frontrighttireinflationpressure` int DEFAULT NULL,
  `frontlefttireinflationpressureinitial` int DEFAULT NULL,
  `frontrighttireinflationpressureinitial` int DEFAULT NULL,
  `frontlefttireinflationpressurefinal` int DEFAULT NULL,
  `frontrighttireinflationpressurefinal` int DEFAULT NULL,
  `rearlefttireinflationpressure` int DEFAULT NULL,
  `rearrighttireinflationpressure` int DEFAULT NULL,
  `rearlefttireinflationpressureinitial` int DEFAULT NULL,
  `rearrighttireinflationpressureinitial` int DEFAULT NULL,
  `rearlefttireinflationpressurefinal` int DEFAULT NULL,
  `rearrighttireinflationpressurefinal` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ucda_woid` (`woid`),
  CONSTRAINT `fk_ucda_workorder` FOREIGN KEY (`woid`) REFERENCES `work_order` (`WOID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint unsigned NOT NULL,
  `employee_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_employee_id` (`employee_id`),
  CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_inspection_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_inspection_items` (
  `inspection_item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `inspection_id` int unsigned NOT NULL,
  `master_item_id` int unsigned DEFAULT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `category_code` int NOT NULL DEFAULT '0',
  `item_code` int NOT NULL DEFAULT '0',
  `category_name` varchar(80) NOT NULL,
  `item_number` int NOT NULL,
  `item_label` varchar(120) NOT NULL,
  `check_description` varchar(255) DEFAULT NULL,
  `rating` enum('good','watch','repair','na') DEFAULT NULL,
  `note` text,
  `display_order` int NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`inspection_item_id`),
  UNIQUE KEY `uq_vii_inspection_item_number` (`inspection_id`,`item_number`),
  KEY `idx_vii_inspection_category_order` (`inspection_id`,`category_name`,`display_order`),
  KEY `idx_vii_rating` (`inspection_id`,`rating`),
  KEY `fk_vii_master_item` (`master_item_id`),
  KEY `idx_vii_inspection_code_order` (`inspection_id`,`category_code`,`display_order`,`item_code`),
  CONSTRAINT `fk_vii_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `vehicle_inspections` (`inspection_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vii_master_item` FOREIGN KEY (`master_item_id`) REFERENCES `inspection_item_master` (`master_item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_inspection_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_inspection_photos` (
  `photo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `inspection_id` int unsigned NOT NULL,
  `inspection_item_id` int unsigned DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(80) DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `show_on_customer_pdf` tinyint(1) NOT NULL DEFAULT '1',
  `uploaded_by` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`photo_id`),
  KEY `idx_vip_inspection_item` (`inspection_id`,`inspection_item_id`),
  KEY `idx_vip_customer_pdf` (`inspection_id`,`show_on_customer_pdf`),
  KEY `fk_vip_item` (`inspection_item_id`),
  CONSTRAINT `fk_vip_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `vehicle_inspections` (`inspection_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vip_item` FOREIGN KEY (`inspection_item_id`) REFERENCES `vehicle_inspection_items` (`inspection_item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_inspections` (
  `inspection_id` int unsigned NOT NULL AUTO_INCREMENT,
  `WOID` int NOT NULL,
  `CVID` int DEFAULT NULL,
  `CustomerID` int DEFAULT NULL,
  `mechanic` varchar(80) DEFAULT NULL,
  `mileage_at_inspect` varchar(40) DEFAULT NULL,
  `status` enum('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `overall_notes` text,
  `created_by` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`inspection_id`),
  UNIQUE KEY `uq_vehicle_inspection_woid` (`WOID`),
  KEY `idx_vehicle_inspections_cvid_created` (`CVID`,`created_at`),
  KEY `idx_vehicle_inspections_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_vehicle_inspection_work_order` FOREIGN KEY (`WOID`) REFERENCES `work_order` (`WOID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `work_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order` (
  `WOID` int NOT NULL AUTO_INCREMENT,
  `CustomerID` int NOT NULL,
  `CVID` int DEFAULT NULL,
  `Mileage` varchar(20) DEFAULT NULL,
  `WO_Date` datetime DEFAULT NULL,
  `WO_Req1` varchar(100) DEFAULT NULL,
  `WO_Req2` varchar(100) DEFAULT NULL,
  `WO_Req3` varchar(100) DEFAULT NULL,
  `WO_Req4` varchar(100) DEFAULT NULL,
  `WO_Req5` varchar(100) DEFAULT NULL,
  `WO_Status` varchar(15) DEFAULT NULL,
  `WO_Note` varchar(255) DEFAULT NULL,
  `Customer_Note` varchar(255) DEFAULT NULL,
  `Admin_Note` mediumtext,
  `Mechanic_Note` mediumtext,
  `Req1` int DEFAULT NULL,
  `WO_Action1` varchar(255) DEFAULT NULL,
  `Req2` int DEFAULT NULL,
  `WO_Action2` varchar(255) DEFAULT NULL,
  `Req3` int DEFAULT NULL,
  `WO_Action3` varchar(255) DEFAULT NULL,
  `Req4` int DEFAULT NULL,
  `WO_Action4` varchar(255) DEFAULT NULL,
  `Req5` int DEFAULT NULL,
  `WO_Action5` varchar(255) DEFAULT NULL,
  `Priority` varchar(40) DEFAULT NULL,
  `Mechanic` varchar(40) DEFAULT NULL,
  `Admin` varchar(40) DEFAULT NULL,
  `TestDrive` int DEFAULT NULL,
  `checksum` int DEFAULT NULL,
  PRIMARY KEY (`WOID`),
  KEY `idx_wo_customer` (`CustomerID`),
  KEY `idx_wo_cvid` (`CVID`),
  KEY `idx_wo_date` (`WO_Date`),
  KEY `idx_wo_status` (`WO_Status`),
  CONSTRAINT `fk_wo_customer` FOREIGN KEY (`CustomerID`) REFERENCES `customers` (`CustomerID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_wo_vehicle` FOREIGN KEY (`CVID`) REFERENCES `customer_vehicle` (`CVID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_work_order_require_min_fields_ins` BEFORE INSERT ON `work_order` FOR EACH ROW BEGIN
    IF TRIM(COALESCE(NEW.WO_Status, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order status is required for permanent records';
    END IF;

    IF TRIM(COALESCE(NEW.Priority, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order priority is required for permanent records';
    END IF;

    IF TRIM(COALESCE(NEW.WO_Note, '')) = ''
       AND TRIM(COALESCE(NEW.WO_Req1, '')) = ''
       AND TRIM(COALESCE(NEW.WO_Req2, '')) = ''
       AND TRIM(COALESCE(NEW.WO_Req3, '')) = ''
       AND TRIM(COALESCE(NEW.WO_Req4, '')) = ''
       AND TRIM(COALESCE(NEW.WO_Req5, '')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order complaint/request is required for permanent records';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `work_order_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_photos` (
  `photo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `WOID` int NOT NULL,
  `work_item_index` tinyint unsigned DEFAULT NULL,
  `stage` enum('before','during','after','inspection','internal') NOT NULL DEFAULT 'before',
  `category` varchar(40) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(80) DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `show_on_customer_pdf` tinyint(1) NOT NULL DEFAULT '0',
  `uploaded_by` varchar(40) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`photo_id`),
  KEY `idx_wop_woid_item` (`WOID`,`work_item_index`),
  KEY `idx_wop_customer_pdf` (`WOID`,`show_on_customer_pdf`,`stage`),
  KEY `idx_wop_general_category` (`WOID`,`work_item_index`,`category`),
  CONSTRAINT `fk_wop_work_order` FOREIGN KEY (`WOID`) REFERENCES `work_order` (`WOID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_wop_work_item_index` CHECK (((`work_item_index` is null) or (`work_item_index` between 1 and 5)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 DROP PROCEDURE IF EXISTS `approve_draft_intake` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `approve_draft_intake`(
  IN p_draft_customer_id INT,
  IN p_draft_vehicle_id INT,
  IN p_draft_wo_id INT,
  IN p_existing_customer_id INT,
  IN p_existing_cvid INT,
  IN p_performed_by_user_id INT UNSIGNED,
  IN p_duplicate_override TINYINT(1),
  IN p_allow_vehicle_transfer TINYINT(1)
)
BEGIN
  DECLARE v_not_found TINYINT DEFAULT 0;

  DECLARE v_customer_id INT;
  DECLARE v_cvid INT;
  DECLARE v_woid INT;
  DECLARE v_promotion_id INT;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_admin_username VARCHAR(40);
  DECLARE v_duplicate_hits INT DEFAULT 0;
  DECLARE v_existing_vehicle_customer_id INT DEFAULT NULL;
  DECLARE v_existing_vehicle_status VARCHAR(1);

  DECLARE v_dc_status VARCHAR(20);
  DECLARE v_dv_status VARCHAR(20);
  DECLARE v_dwo_status VARCHAR(20);

  DECLARE v_first VARCHAR(30);
  DECLARE v_last  VARCHAR(30);
  DECLARE v_phone VARCHAR(15);
  DECLARE v_cell  VARCHAR(15);
  DECLARE v_email VARCHAR(50);
  DECLARE v_addr  VARCHAR(100);
  DECLARE v_city  VARCHAR(60);
  DECLARE v_prov  VARCHAR(40);
  DECLARE v_post  VARCHAR(7);
  DECLARE v_ext   INT;
  DECLARE v_sub   TINYINT(1);

  DECLARE v_plate VARCHAR(20);
  DECLARE v_vin VARCHAR(80);
  DECLARE v_make VARCHAR(40);
  DECLARE v_model VARCHAR(40);
  DECLARE v_year VARCHAR(4);
  DECLARE v_color VARCHAR(30);
  DECLARE v_engine VARCHAR(50);
  DECLARE v_detail VARCHAR(200);

  DECLARE v_mileage VARCHAR(20);
  DECLARE v_wodate DATETIME;
  DECLARE v_wostatus VARCHAR(15);
  DECLARE v_priority VARCHAR(40);
  DECLARE v_wo_note VARCHAR(255);
  DECLARE v_cnote VARCHAR(255);
  DECLARE v_admin_note MEDIUMTEXT;
  DECLARE v_mech_note MEDIUMTEXT;
  DECLARE v_req1_txt VARCHAR(100);
  DECLARE v_req2_txt VARCHAR(100);
  DECLARE v_req3_txt VARCHAR(100);
  DECLARE v_req4_txt VARCHAR(100);
  DECLARE v_req5_txt VARCHAR(100);
  DECLARE v_req1 INT;
  DECLARE v_req2 INT;
  DECLARE v_req3 INT;
  DECLARE v_req4 INT;
  DECLARE v_req5 INT;
  DECLARE v_testdrive INT;
  DECLARE v_checksum INT;
  DECLARE v_mechanic VARCHAR(40);
  DECLARE v_admin VARCHAR(40);

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_not_found = 1;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  SELECT COUNT(*) INTO v_exists
  FROM users
  WHERE user_id = p_performed_by_user_id AND is_active = 1;

  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid performed_by_user_id';
  END IF;

  SELECT username INTO v_admin_username
  FROM users
  WHERE user_id = p_performed_by_user_id
  LIMIT 1;

  IF p_draft_vehicle_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle is required before approval';
  END IF;

  SET v_not_found = 0;
  SELECT status, first_name, last_name, phone, cell, email, address, city, province, postal_code, phone_ext, subscribe
    INTO v_dc_status, v_first, v_last, v_phone, v_cell, v_email, v_addr, v_city, v_prov, v_post, v_ext, v_sub
  FROM draft_customers
  WHERE draft_customer_id = p_draft_customer_id
  FOR UPDATE;

  IF v_not_found = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft customer not found';
  END IF;

  IF v_dc_status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft customer is not approvable';
  END IF;

  IF p_existing_customer_id IS NULL THEN
    IF TRIM(COALESCE(v_first, '')) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer first name is required before approval';
    END IF;

    IF TRIM(COALESCE(v_phone, '')) = '' AND TRIM(COALESCE(v_cell, '')) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer phone or cell is required before approval';
    END IF;
  END IF;

  SET v_not_found = 0;
  SELECT status, plate, vin, make, model, year, color, engine, detail
    INTO v_dv_status, v_plate, v_vin, v_make, v_model, v_year, v_color, v_engine, v_detail
  FROM draft_vehicles
  WHERE draft_vehicle_id = p_draft_vehicle_id
    AND draft_customer_id = p_draft_customer_id
  FOR UPDATE;

  IF v_not_found = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle not found or does not belong to draft customer';
  END IF;

  IF v_dv_status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle is not approvable';
  END IF;

  IF p_existing_cvid IS NULL THEN
    IF TRIM(COALESCE(v_plate, '')) = '' AND TRIM(COALESCE(v_vin, '')) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle plate or VIN is required before approval';
    END IF;

    IF TRIM(COALESCE(v_make, '')) = '' OR TRIM(COALESCE(v_model, '')) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle make and model are required before approval';
    END IF;
  END IF;

  SET v_not_found = 0;
  SELECT status, mileage, wo_date, wo_status, priority, wo_note, customer_note, admin_note, mechanic_note,
         wo_req1, wo_req2, wo_req3, wo_req4, wo_req5,
         req1, req2, req3, req4, req5,
         testdrive, checksum, mechanic, admin
    INTO v_dwo_status, v_mileage, v_wodate, v_wostatus, v_priority, v_wo_note, v_cnote, v_admin_note, v_mech_note,
         v_req1_txt, v_req2_txt, v_req3_txt, v_req4_txt, v_req5_txt,
         v_req1, v_req2, v_req3, v_req4, v_req5,
         v_testdrive, v_checksum, v_mechanic, v_admin
  FROM draft_work_orders
  WHERE draft_wo_id = p_draft_wo_id
    AND draft_customer_id = p_draft_customer_id
    AND draft_vehicle_id = p_draft_vehicle_id
  FOR UPDATE;

  IF v_not_found = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft work order not found or link mismatch';
  END IF;

  IF v_dwo_status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft work order is not approvable';
  END IF;

  IF TRIM(COALESCE(v_wostatus, '')) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order status is required before approval';
  END IF;

  IF TRIM(COALESCE(v_priority, '')) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order priority is required before approval';
  END IF;

  IF TRIM(COALESCE(v_mileage, '')) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order mileage is required before approval';
  END IF;

  IF TRIM(COALESCE(v_wo_note, '')) = ''
     AND TRIM(COALESCE(v_req1_txt, '')) = ''
     AND TRIM(COALESCE(v_req2_txt, '')) = ''
     AND TRIM(COALESCE(v_req3_txt, '')) = ''
     AND TRIM(COALESCE(v_req4_txt, '')) = ''
     AND TRIM(COALESCE(v_req5_txt, '')) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Work order complaint/request is required before approval';
  END IF;

  IF p_existing_customer_id IS NULL THEN
    SELECT COUNT(*) INTO v_duplicate_hits
    FROM customers c
    WHERE (TRIM(COALESCE(v_phone, '')) <> '' AND c.Phone = v_phone)
       OR (TRIM(COALESCE(v_cell, '')) <> '' AND c.Cell = v_cell);

    IF v_duplicate_hits > 0 AND COALESCE(p_duplicate_override, 0) = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Potential duplicate customer detected; choose existing customer or confirm override';
    END IF;
  END IF;

  IF p_existing_customer_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_exists
    FROM customers
    WHERE CustomerID = p_existing_customer_id;

    IF v_exists = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'existing_customer_id not found';
    END IF;

    SET v_customer_id = p_existing_customer_id;
  ELSE
    INSERT INTO customers
      (FirstName, LastName, Phone, Cell, Email, Address, City, Province, PostalCode, PhoneExt, subscribe)
    VALUES
      (v_first, COALESCE(v_last,''), v_phone, COALESCE(v_cell,''), COALESCE(v_email,''),
       COALESCE(v_addr,''), COALESCE(v_city,''), COALESCE(v_prov,''), COALESCE(v_post,''), v_ext, COALESCE(v_sub,0));

    SET v_customer_id = LAST_INSERT_ID();
  END IF;

  IF p_existing_cvid IS NULL THEN
    SELECT COUNT(*) INTO v_duplicate_hits
    FROM customer_vehicle cv
    WHERE cv.Status = 'A'
      AND (
            (TRIM(COALESCE(v_vin, '')) <> '' AND UPPER(cv.VIN) = UPPER(v_vin))
         OR (TRIM(COALESCE(v_plate, '')) <> '' AND UPPER(cv.Plate) = UPPER(v_plate))
      );

    IF v_duplicate_hits > 0 AND COALESCE(p_duplicate_override, 0) = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Potential duplicate vehicle detected; choose existing CVID or confirm override';
    END IF;
  END IF;

  IF p_existing_cvid IS NOT NULL THEN
    SET v_not_found = 0;
    SELECT CustomerID, Status, Plate, VIN, Make, Model, Year, Color, Engine, Detail
      INTO v_existing_vehicle_customer_id, v_existing_vehicle_status,
           v_plate, v_vin, v_make, v_model, v_year, v_color, v_engine, v_detail
    FROM customer_vehicle
    WHERE CVID = p_existing_cvid
    FOR UPDATE;

    IF v_not_found = 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'existing_cvid not found';
    END IF;

    IF COALESCE(v_existing_vehicle_status, '') <> 'A' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'existing_cvid is inactive';
    END IF;

    IF v_existing_vehicle_customer_id = v_customer_id THEN
      SET v_cvid = p_existing_cvid;
    ELSE
      IF COALESCE(p_allow_vehicle_transfer, 0) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle belongs to another customer; confirm transfer to continue';
      END IF;

      INSERT INTO customer_vehicle
        (CustomerID, Plate, VIN, Make, Model, Year, Color, Status, Engine, Detail)
      VALUES
        (v_customer_id, COALESCE(v_plate,''), COALESCE(v_vin,''), COALESCE(v_make,''), COALESCE(v_model,''),
         COALESCE(v_year,''), COALESCE(v_color,''), 'A', COALESCE(v_engine,''), COALESCE(v_detail,''));

      SET v_cvid = LAST_INSERT_ID();

      UPDATE customer_vehicle
      SET Status = 'I'
      WHERE CVID = p_existing_cvid
        AND Status = 'A';

      IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vehicle transfer failed due to concurrent status change';
      END IF;
    END IF;
  ELSE
    INSERT INTO customer_vehicle
      (CustomerID, Plate, VIN, Make, Model, Year, Color, Status, Engine, Detail)
    VALUES
      (v_customer_id, v_plate, COALESCE(v_vin,''), v_make, v_model,
       COALESCE(v_year,''), COALESCE(v_color,''), 'A', COALESCE(v_engine,''), COALESCE(v_detail,''));

    SET v_cvid = LAST_INSERT_ID();
  END IF;

  IF v_wodate IS NULL THEN SET v_wodate = NOW(); END IF;
  IF v_wostatus IS NULL OR v_wostatus = '' OR UPPER(v_wostatus) = 'OPEN' THEN SET v_wostatus = 'NEW'; END IF;
  IF v_priority IS NULL OR v_priority = '' THEN SET v_priority = 'NORMAL'; END IF;

  INSERT INTO work_order
    (CustomerID, CVID, Mileage, WO_Date, WO_Status, Priority,
     WO_Req1, WO_Req2, WO_Req3, WO_Req4, WO_Req5, WO_Note, Customer_Note,
     Admin_Note, Mechanic_Note, Mechanic, Admin, TestDrive, checksum,
     Req1, Req2, Req3, Req4, Req5)
  VALUES
    (v_customer_id, v_cvid, COALESCE(v_mileage,''), v_wodate, v_wostatus, v_priority,
     COALESCE(v_req1_txt,''), COALESCE(v_req2_txt,''), COALESCE(v_req3_txt,''), COALESCE(v_req4_txt,''), COALESCE(v_req5_txt,''),
     COALESCE(v_wo_note,''), COALESCE(v_cnote,''),
     COALESCE(v_admin_note,''), COALESCE(v_mech_note,''), COALESCE(v_mechanic,''), COALESCE(v_admin, v_admin_username),
     COALESCE(v_testdrive,0), COALESCE(v_checksum,0),
     COALESCE(v_req1,0), COALESCE(v_req2,0), COALESCE(v_req3,0), COALESCE(v_req4,0), COALESCE(v_req5,0));

  SET v_woid = LAST_INSERT_ID();

  UPDATE draft_customers
  SET status = 'approved',
      approved_customer_id = v_customer_id,
      approved_at = NOW()
  WHERE draft_customer_id = p_draft_customer_id
    AND status = 'draft';

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft customer state changed during approval';
  END IF;

  UPDATE draft_vehicles
  SET status = 'approved',
      approved_cvid = v_cvid,
      approved_at = NOW()
  WHERE draft_vehicle_id = p_draft_vehicle_id
    AND status = 'draft';

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle state changed during approval';
  END IF;

  UPDATE draft_work_orders
  SET status = 'approved',
      readiness_state = 'ready',
      missing_reasons = NULL,
      escalation_level = 'none',
      approved_woid = v_woid,
      approved_at = NOW(),
      last_validated_at = NOW(),
      last_validated_by_user_id = p_performed_by_user_id
  WHERE draft_wo_id = p_draft_wo_id
    AND status = 'draft';

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft work order state changed during approval';
  END IF;

  INSERT INTO draft_promotion_log
    (draft_customer_id, draft_vehicle_id, draft_wo_id, customer_id, cvid, woid,
     action, performed_by_user_id, notes)
  VALUES
    (p_draft_customer_id, p_draft_vehicle_id, p_draft_wo_id,
     v_customer_id, v_cvid, v_woid,
     'approved', p_performed_by_user_id, 'Approved via approve_draft_intake');

  SET v_promotion_id = LAST_INSERT_ID();

  INSERT INTO draft_status_log
    (draft_wo_id, old_status, new_status, action, performed_by_user_id, notes, payload_json)
  VALUES
    (p_draft_wo_id, v_dwo_status, 'approved', 'approved', p_performed_by_user_id,
     'Draft approved and promoted',
     JSON_OBJECT('customer_id', v_customer_id, 'cvid', v_cvid, 'woid', v_woid, 'duplicate_override', COALESCE(p_duplicate_override,0), 'allow_vehicle_transfer', COALESCE(p_allow_vehicle_transfer,0)));

  COMMIT;

  SELECT v_customer_id AS CustomerID,
         v_cvid AS CVID,
         v_woid AS WOID,
         v_promotion_id AS PromotionID;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `cancel_draft_intake` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `cancel_draft_intake`(
  IN p_draft_customer_id INT,
  IN p_draft_vehicle_id INT,
  IN p_draft_wo_id INT,
  IN p_performed_by_user_id INT UNSIGNED,
  IN p_notes VARCHAR(255)
)
BEGIN
  DECLARE v_not_found TINYINT DEFAULT 0;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_dc_status VARCHAR(20);
  DECLARE v_dv_status VARCHAR(20);
  DECLARE v_dwo_status VARCHAR(20);
  DECLARE v_promotion_id INT;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_not_found = 1;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  SELECT COUNT(*)
    INTO v_exists
  FROM users
  WHERE user_id = p_performed_by_user_id
    AND is_active = 1;

  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid performed_by_user_id';
  END IF;

  SET v_not_found = 0;
  SELECT status
    INTO v_dc_status
  FROM draft_customers
  WHERE draft_customer_id = p_draft_customer_id
  FOR UPDATE;

  IF v_not_found = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft customer not found';
  END IF;

  IF v_dc_status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft customer is not cancellable';
  END IF;

  IF p_draft_vehicle_id IS NOT NULL THEN
    SET v_not_found = 0;
    SELECT status
      INTO v_dv_status
    FROM draft_vehicles
    WHERE draft_vehicle_id = p_draft_vehicle_id
      AND draft_customer_id = p_draft_customer_id
    FOR UPDATE;

    IF v_not_found = 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle not found or does not belong to draft customer';
    END IF;

    IF v_dv_status <> 'draft' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle is not cancellable';
    END IF;
  END IF;

  SET v_not_found = 0;
  SELECT status
    INTO v_dwo_status
  FROM draft_work_orders
  WHERE draft_wo_id = p_draft_wo_id
    AND draft_customer_id = p_draft_customer_id
    AND (p_draft_vehicle_id IS NULL OR draft_vehicle_id = p_draft_vehicle_id)
  FOR UPDATE;

  IF v_not_found = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft work order not found or link mismatch';
  END IF;

  IF v_dwo_status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft work order is not cancellable';
  END IF;

  UPDATE draft_customers
  SET status = 'cancelled',
      cancelled_at = NOW()
  WHERE draft_customer_id = p_draft_customer_id
    AND status = 'draft';

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft customer state changed during cancellation';
  END IF;

  IF p_draft_vehicle_id IS NOT NULL THEN
    UPDATE draft_vehicles
    SET status = 'cancelled',
        cancelled_at = NOW()
    WHERE draft_vehicle_id = p_draft_vehicle_id
      AND status = 'draft';

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft vehicle state changed during cancellation';
    END IF;
  END IF;

  UPDATE draft_work_orders
  SET status = 'cancelled',
      readiness_state = 'incomplete',
      missing_reasons = NULL,
      escalation_level = 'none',
      cancelled_at = NOW(),
      last_validated_at = NOW(),
      last_validated_by_user_id = p_performed_by_user_id
  WHERE draft_wo_id = p_draft_wo_id
    AND status = 'draft';

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Draft work order state changed during cancellation';
  END IF;

  INSERT INTO draft_promotion_log
    (draft_customer_id, draft_vehicle_id, draft_wo_id,
     customer_id, cvid, woid,
     action, performed_by_user_id, notes)
  VALUES
    (p_draft_customer_id, p_draft_vehicle_id, p_draft_wo_id,
     NULL, NULL, NULL,
     'cancelled', p_performed_by_user_id, COALESCE(p_notes, 'Cancelled via cancel_draft_intake'));

  SET v_promotion_id = LAST_INSERT_ID();

  INSERT INTO draft_status_log
    (draft_wo_id, old_status, new_status, action, performed_by_user_id, notes, payload_json)
  VALUES
    (p_draft_wo_id, v_dwo_status, 'cancelled', 'cancelled', p_performed_by_user_id,
     COALESCE(p_notes, 'Cancelled via cancel_draft_intake'),
     JSON_OBJECT('draft_customer_id', p_draft_customer_id, 'draft_vehicle_id', p_draft_vehicle_id));

  COMMIT;

  SELECT p_draft_customer_id AS DraftCustomerID,
         p_draft_vehicle_id AS DraftVehicleID,
         p_draft_wo_id AS DraftWOID,
         v_promotion_id AS PromotionID;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


