-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: restaurant_db1
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
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (2,'เมนูผัด',1),(3,'เมนูเส้น',1);
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item`
--

DROP TABLE IF EXISTS `item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(30) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `stock_qty` int(11) NOT NULL DEFAULT 50,
  `reorder_point` int(11) NOT NULL DEFAULT 5,
  `use_stock` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `stock_pool_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `sku_unique` (`sku`),
  KEY `category_id` (`category_id`),
  KEY `subcategory_id` (`subcategory_id`),
  KEY `fk_item_stock_pool` (`stock_pool_id`),
  CONSTRAINT `item_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE SET NULL,
  CONSTRAINT `item_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategory` (`subcategory_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_item_stock_pool` FOREIGN KEY (`stock_pool_id`) REFERENCES `stock_pool` (`pool_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item`
--

LOCK TABLES `item` WRITE;
/*!40000 ALTER TABLE `item` DISABLE KEYS */;
INSERT INTO `item` VALUES (1,'ITM-001','เมนูกระเพรา',35.00,2,NULL,NULL,'item_1787557931_6a8bf82bd689a.jpg',1,'2026-03-31 16:08:00',49,5,1,0,NULL),(2,'ITM-002','เมนูทอดกระเทียม',35.00,2,NULL,NULL,'item_1787557827_6a8bf7c3d5b30.jpg',1,'2026-08-24 14:50:27',50,5,1,0,NULL),(3,'ITM-003','เมนูข้าวผัด',35.00,2,NULL,NULL,'item_1787557920_6a8bf8202ebe4.jpg',1,'2026-08-24 14:52:00',49,5,1,0,NULL),(4,'ITM-004','สุกิ',35.00,3,NULL,NULL,'item_1787558229_6a8bf955d5847.jpg',1,'2026-08-24 14:56:15',50,5,1,0,NULL);
/*!40000 ALTER TABLE `item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `username` (`username`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `join_pin_attempts`
--

DROP TABLE IF EXISTS `join_pin_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `join_pin_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `table_id` (`table_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `join_pin_attempts`
--

LOCK TABLES `join_pin_attempts` WRITE;
/*!40000 ALTER TABLE `join_pin_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `join_pin_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_toppings`
--

DROP TABLE IF EXISTS `menu_toppings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_toppings` (
  `item_id` int(11) NOT NULL,
  `topping_id` int(11) NOT NULL,
  PRIMARY KEY (`item_id`,`topping_id`),
  KEY `topping_id` (`topping_id`),
  CONSTRAINT `menu_toppings_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`),
  CONSTRAINT `menu_toppings_ibfk_2` FOREIGN KEY (`topping_id`) REFERENCES `topping` (`topping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_toppings`
--

LOCK TABLES `menu_toppings` WRITE;
/*!40000 ALTER TABLE `menu_toppings` DISABLE KEYS */;
INSERT INTO `menu_toppings` VALUES (1,1),(1,2),(1,3),(2,1),(2,2),(2,3),(3,1),(3,2),(3,3),(4,2),(4,3);
/*!40000 ALTER TABLE `menu_toppings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orderdetail`
--

DROP TABLE IF EXISTS `orderdetail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orderdetail` (
  `order_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`order_detail_id`),
  KEY `order_id` (`order_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `orderdetail_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `orderdetail_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orderdetail`
--

LOCK TABLES `orderdetail` WRITE;
/*!40000 ALTER TABLE `orderdetail` DISABLE KEYS */;
INSERT INTO `orderdetail` VALUES (1,1,1,2,45.00,'','2026-04-12 19:45:08'),(2,2,1,1,35.00,'','2026-04-12 20:46:34'),(3,2,1,1,35.00,'','2026-04-12 20:46:34'),(9,8,1,3,35.00,'','2026-08-24 12:53:31'),(10,9,1,1,35.00,'','2026-08-24 13:51:05'),(11,10,1,1,35.00,'','2026-08-24 14:02:01'),(12,11,1,1,35.00,'','2026-08-24 14:05:02'),(13,12,1,3,35.00,'','2026-08-24 14:20:19'),(17,16,3,1,45.00,'','2026-08-24 15:15:58');
/*!40000 ALTER TABLE `orderdetail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orderdetail_topping`
--

DROP TABLE IF EXISTS `orderdetail_topping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orderdetail_topping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_detail_id` int(11) NOT NULL,
  `topping_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_detail_id` (`order_detail_id`),
  KEY `topping_id` (`topping_id`),
  CONSTRAINT `orderdetail_topping_ibfk_1` FOREIGN KEY (`order_detail_id`) REFERENCES `orderdetail` (`order_detail_id`) ON DELETE CASCADE,
  CONSTRAINT `orderdetail_topping_ibfk_2` FOREIGN KEY (`topping_id`) REFERENCES `topping` (`topping_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orderdetail_topping`
--

LOCK TABLES `orderdetail_topping` WRITE;
/*!40000 ALTER TABLE `orderdetail_topping` DISABLE KEYS */;
INSERT INTO `orderdetail_topping` VALUES (1,1,1,'2026-04-12 19:45:08'),(2,2,2,'2026-04-12 20:46:34'),(3,3,3,'2026-04-12 20:46:34'),(4,17,1,'2026-08-24 15:15:58');
/*!40000 ALTER TABLE `orderdetail_topping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `order_date` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `order_status` enum('pending','cooking','ready','served','canceled') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') DEFAULT 'unpaid',
  `order_type` enum('dine_in','takeaway') DEFAULT 'dine_in',
  `online_customer_name` varchar(100) DEFAULT NULL,
  `online_customer_phone` varchar(20) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `daily_order_no` int(11) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `slip_resubmitted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_id`),
  KEY `table_id` (`table_id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `restauranttable` (`table_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,NULL,90.00,'2026-04-12 19:45:08','2026-04-12 19:45:08','canceled','unpaid','takeaway',NULL,NULL,'',NULL,NULL,0),(2,NULL,70.00,'2026-04-12 20:46:34','2026-04-12 20:46:34','canceled','unpaid','takeaway',NULL,NULL,'',NULL,NULL,0),(8,1,999.00,'2026-08-24 12:53:31','2026-08-24 12:53:31','canceled','unpaid','dine_in','ทดสอบ','0812345678','',1,'?????????????????',0),(9,NULL,35.00,'2026-08-24 13:51:05','2026-08-24 13:51:05','served','paid','takeaway','SlipTest','0899999999','',2,NULL,0),(10,NULL,35.00,'2026-08-24 14:02:00','2026-08-24 14:02:00','served','paid','takeaway','Test2','0888888888','',3,NULL,1),(11,NULL,35.00,'2026-08-24 14:05:02','2026-08-24 14:05:02','served','paid','takeaway','RestoreTest','0877777777','',4,NULL,0),(12,1,105.00,'2026-08-24 14:20:19','2026-08-24 14:20:19','served','paid','takeaway','บาส','0850444647','',5,NULL,0),(16,1,45.00,'2026-08-24 15:15:58','2026-08-24 15:15:58','served','paid','takeaway','บาส','0850444647','',6,NULL,0);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_verify_attempts`
--

DROP TABLE IF EXISTS `otp_verify_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_verify_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `phone` (`phone`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_verify_attempts`
--

LOCK TABLES `otp_verify_attempts` WRITE;
/*!40000 ALTER TABLE `otp_verify_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `otp_verify_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owner`
--

DROP TABLE IF EXISTS `owner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner` (
  `owner_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT 'default_logo.png',
  `promptpay_qr` varchar(255) DEFAULT NULL,
  `bank_info` text DEFAULT NULL,
  `restaurant_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_shop_open` tinyint(1) DEFAULT 0,
  `is_dinein_open` tinyint(1) NOT NULL DEFAULT 1,
  `is_takeaway_open` tinyint(1) NOT NULL DEFAULT 1,
  `max_queue` int(11) DEFAULT 20,
  `close_reason` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`owner_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owner`
--

LOCK TABLES `owner` WRITE;
/*!40000 ALTER TABLE `owner` DISABLE KEYS */;
INSERT INTO `owner` VALUES (1,'admin','$2y$10$UiM54d5MG.AFw2QlFLxQNOOnqcebAq9LASSxfvAteOQkxQV2PtLpi',NULL,NULL,'0917967142','default_logo.png',NULL,'','RANNAIBAAN','',NULL,NULL,1,1,1,17,'','2026-03-31 04:01:38',NULL,1);
/*!40000 ALTER TABLE `owner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owner_trusted_devices`
-- เก็บอุปกรณ์ที่เจ้าของร้านเคยล็อกอินสำเร็จไว้ (ผูกกับ cookie ฝั่งเครื่องนั้น) ใช้แจ้งเตือนทางอีเมลเมื่อมีการ
-- ล็อกอินสำเร็จจากอุปกรณ์ที่ไม่เคยเห็นมาก่อน (ดู includes/device_login_check.php) เผื่อรหัสผ่านหลุด/มีคนอื่นรู้รหัส
--

DROP TABLE IF EXISTS `owner_trusted_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owner_trusted_devices` (
  `device_id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `device_token` varchar(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_seen_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`device_id`),
  UNIQUE KEY `device_token` (`device_token`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `owner_trusted_devices_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `owner` (`owner_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset`
--

DROP TABLE IF EXISTS `password_reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `owner` (`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset`
--

LOCK TABLES `password_reset` WRITE;
/*!40000 ALTER TABLE `password_reset` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `transaction_ref` varchar(255) DEFAULT NULL,
  `slip_image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
INSERT INTO `payment` VALUES (1,1,90.00,'โอนเงิน','failed',NULL,'slip_ORD1_1775997908.png','2026-04-12 19:45:08'),(2,2,70.00,'โอนเงิน','failed',NULL,'slip_ORD2_1776001594.png','2026-04-12 20:46:34'),(5,9,35.00,'cash','completed',NULL,'slip_order9_retry_1787554295_6a8be9f71a754.png','2026-08-24 13:51:05'),(6,10,35.00,'cash','completed',NULL,'slip_order10_retry_1787554921_6a8bec69296b0.png','2026-08-24 14:02:01'),(7,11,35.00,'cash','completed',NULL,'slip_order11_1787555102_6a8bed1e1fbfa.png','2026-08-24 14:05:02'),(10,12,105.00,'cash','completed',NULL,NULL,'2026-08-24 15:13:15'),(12,16,45.00,'cash','completed',NULL,NULL,'2026-08-24 15:15:58');
/*!40000 ALTER TABLE `payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restauranttable`
--

DROP TABLE IF EXISTS `restauranttable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restauranttable` (
  `table_id` int(11) NOT NULL AUTO_INCREMENT,
  `table_number` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'available',
  `created_at` datetime DEFAULT current_timestamp(),
  `join_code` varchar(10) DEFAULT NULL,
  `qr_token` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`table_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restauranttable`
--

LOCK TABLES `restauranttable` WRITE;
/*!40000 ALTER TABLE `restauranttable` DISABLE KEYS */;
INSERT INTO `restauranttable` VALUES (1,'A2','available','2026-03-31 04:40:10',NULL,'a1f3c9e7b2d84f60'),(2,'A9','available','2026-08-21 21:43:59',NULL,'6e0d5a2b9c4f1387');
/*!40000 ALTER TABLE `restauranttable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_pool`
-- "กลุ่มสต็อกร่วม" ให้หลายเมนู/ท็อปปิ้งที่ใช้วัตถุดิบตัวเดียวกันจริงหักสต็อกจากกองเดียวกัน (ดู owner/manage_stock.php)
-- pool_category เป็นชื่อหมวดหมู่แบบข้อความอิสระ (ไม่ใช่ FK ไปตารางแยก) ใช้จัดกลุ่มแสดงผลเป็น accordion เท่านั้น
--

DROP TABLE IF EXISTS `stock_pool`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_pool` (
  `pool_id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(30) DEFAULT NULL,
  `pool_name` varchar(100) NOT NULL,
  `pool_category` varchar(50) DEFAULT NULL,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `reorder_point` int(11) NOT NULL DEFAULT 5,
  PRIMARY KEY (`pool_id`),
  UNIQUE KEY `sku_unique` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_pool`
--

LOCK TABLES `stock_pool` WRITE;
/*!40000 ALTER TABLE `stock_pool` DISABLE KEYS */;
INSERT INTO `stock_pool` VALUES (3,'POOL-001','ไก่','เนื้อสัตว์',19,5),(5,'POOL-002','หมูกรอบ','เนื้อสัตว์',20,5),(6,'POOL-003','ไข่','เนื้อสัตว์',50,5),(7,'POOL-004','หมู','เนื้อสัตว์',20,5),(9,'POOL-005','กุ้ง','เนื้อสัตว์',20,5);
/*!40000 ALTER TABLE `stock_pool` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transactions`
-- บันทึกทุกครั้งที่จำนวนคงเหลือของเมนู/ท็อปปิ้ง/กลุ่มสต็อกร่วมเปลี่ยน (ตัดอัตโนมัติจากออเดอร์ หรือปรับมือ)
-- ดู includes/stock_log.php ที่เขียนลงตารางนี้ และหน้า owner/stock_transactions.php ที่แสดงผล
--

DROP TABLE IF EXISTS `stock_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `occurred_at` datetime DEFAULT current_timestamp(),
  `item_type` enum('item','topping','pool') NOT NULL,
  `item_id` int(11) NOT NULL,
  `sku` varchar(30) DEFAULT NULL,
  `item_name` varchar(150) NOT NULL,
  `qty_change` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `source_type` enum('order','manual') NOT NULL,
  `source_ref` varchar(50) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `occurred_at` (`occurred_at`),
  KEY `item_type_id` (`item_type`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subcategory`
--

DROP TABLE IF EXISTS `subcategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcategory` (
  `subcategory_id` int(11) NOT NULL AUTO_INCREMENT,
  `subcategory_name` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`subcategory_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `subcategory_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcategory`
--

LOCK TABLES `subcategory` WRITE;
/*!40000 ALTER TABLE `subcategory` DISABLE KEYS */;
/*!40000 ALTER TABLE `subcategory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topping`
--

DROP TABLE IF EXISTS `topping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topping` (
  `topping_id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(30) DEFAULT NULL,
  `topping_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `topping_cat_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `stock_qty` int(11) NOT NULL DEFAULT 50,
  `reorder_point` int(11) NOT NULL DEFAULT 5,
  `use_stock` tinyint(1) NOT NULL DEFAULT 1,
  `stock_pool_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`topping_id`),
  UNIQUE KEY `sku_unique` (`sku`),
  KEY `FK_Topping_cat` (`topping_cat_id`),
  KEY `fk_topping_stock_pool` (`stock_pool_id`),
  CONSTRAINT `FK_Topping_cat` FOREIGN KEY (`topping_cat_id`) REFERENCES `topping_categories` (`topping_cat_id`),
  CONSTRAINT `fk_topping_stock_pool` FOREIGN KEY (`stock_pool_id`) REFERENCES `stock_pool` (`pool_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topping`
--

LOCK TABLES `topping` WRITE;
/*!40000 ALTER TABLE `topping` DISABLE KEYS */;
INSERT INTO `topping` VALUES (1,'TOP-001','หมูกรอบ',10.00,1,1,'2026-03-31 13:48:18',50,5,1,NULL),(2,'TOP-002','ไก่สับ',0.00,1,1,'2026-03-31 13:51:29',50,5,1,NULL),(3,'TOP-003','หมูสับ',0.00,1,1,'2026-03-31 13:51:44',50,5,1,NULL),(4,'TOP-004','ทะเล',10.00,1,1,'2026-08-24 14:57:59',50,5,1,NULL),(5,'TOP-005','จานธรรมดาบ',0.00,2,1,'2026-08-24 14:58:34',50,5,1,NULL),(6,'TOP-006','จานพิเศษ',5.00,2,1,'2026-08-24 14:59:09',50,5,1,NULL);
/*!40000 ALTER TABLE `topping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topping_categories`
--

DROP TABLE IF EXISTS `topping_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `topping_categories` (
  `topping_cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `topping_cat_name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`topping_cat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topping_categories`
--

LOCK TABLES `topping_categories` WRITE;
/*!40000 ALTER TABLE `topping_categories` DISABLE KEYS */;
INSERT INTO `topping_categories` VALUES (1,'ประเภทเนื้อสัตว์',0),(2,'ขนาน',1),(3,'เส้น',2),(4,'ระดับความเผ็ด',3);
/*!40000 ALTER TABLE `topping_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'restaurant_db1'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-24 15:22:02
