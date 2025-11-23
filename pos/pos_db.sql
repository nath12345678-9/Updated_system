-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2025 at 02:52 PM
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
-- Database: `pos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `category`, `image`) VALUES
(1, 'Bolo de arroz', 20.00, 'Pastries', 'uploads/Bolo de arroz.jpg'),
(2, 'Croissant', 20.00, 'Pastries', 'uploads/Croissant.jpg'),
(3, 'Pao de deus', 10.00, 'Pastries', 'uploads/Pao de deus.jpg'),
(4, 'Pasteis de nata', 10.00, 'Pastries', 'uploads/Pasteis de nata.jpg'),
(5, 'Queijadas de sintra', 8.00, 'Pastries', 'uploads/Queijadas de sintra.jpg'),
(6, 'Travesseiros de sintra', 15.00, 'Pastries', 'uploads/Travesseiros de sintra.jpg'),
(7, 'Broa de milho', 8.00, 'Breads', 'uploads/Broa de milho.jpg'),
(8, 'Pao alentejano', 40.00, 'Breads', 'uploads/Pao alentejano.jpg'),
(9, 'Pao de centeio', 45.00, 'Breads', 'uploads/Pao de centeio.jpg'),
(10, 'Pao de mafra', 25.00, 'Breads', 'uploads/Pao de mafra.jpg'),
(11, 'Coxinha', 10.00, 'Savory Items', 'uploads/Coxinha.jpg'),
(12, 'Empada de galinha', 10.00, 'Savory Items', 'uploads/Empada de galinha.jpg'),
(13, 'Pao com chourico', 15.00, 'Savory Items', 'uploads/Pao com chourico.jpg'),
(14, 'Pastel de bacalhau', 15.00, 'Savory Items', 'uploads/Pastel de bacalhau.jpg'),
(15, 'Rissois de camarao', 25.00, 'Savory Items', 'uploads/Rissois de camarao.jpg'),
(16, 'Cafe com leite', 50.00, 'Beverages', 'uploads/Cafe com leite.jpg'),
(17, 'Cappuccino', 50.00, 'Beverages', 'uploads/cappuccino.jpg'),
(18, 'Espresso', 60.00, 'Beverages', 'uploads/Espresso.jpg'),
(19, 'Galao', 100.00, 'Beverages', 'uploads/Galao.jpg'),
(20, 'Iced Coffee', 130.00, 'Beverages', 'uploads/Iced Coffee.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `total`, `sale_date`) VALUES
(1, 946.00, '2025-11-20 09:18:42');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `price`) VALUES
(1, 1, 1, 20.00),
(2, 1, 2, 20.00),
(3, 1, 3, 10.00),
(4, 1, 4, 10.00),
(5, 1, 5, 8.00),
(6, 1, 6, 15.00),
(7, 1, 7, 8.00),
(8, 1, 8, 40.00),
(9, 1, 9, 45.00),
(10, 1, 10, 25.00),
(11, 1, 11, 10.00),
(12, 1, 12, 10.00),
(13, 1, 13, 15.00),
(14, 1, 14, 15.00),
(15, 1, 15, 25.00),
(16, 1, 16, 50.00),
(17, 1, 16, 50.00),
(18, 1, 17, 50.00),
(19, 1, 18, 60.00),
(20, 1, 19, 100.00),
(21, 1, 20, 130.00),
(22, 1, 19, 100.00),
(23, 1, 20, 130.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
