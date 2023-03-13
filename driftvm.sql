-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 13, 2023 at 02:28 AM
-- Server version: 10.5.18-MariaDB-0+deb11u1
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `driftvm`
--

-- --------------------------------------------------------

--
-- Table structure for table `BannedIPs`
--

CREATE TABLE `BannedIPs` (
  `ID` int(11) NOT NULL,
  `TimeStamp` int(11) NOT NULL,
  `IP` varchar(255) NOT NULL,
  `Expires` int(11) NOT NULL DEFAULT 0,
  `Reason` varchar(255) NOT NULL DEFAULT 'Unknown',
  `Data` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `GeoIP`
--

CREATE TABLE `GeoIP` (
  `ID` int(11) NOT NULL,
  `IP` varchar(255) NOT NULL DEFAULT '',
  `CountryCode` varchar(255) NOT NULL DEFAULT '',
  `CountryName` varchar(255) NOT NULL DEFAULT '',
  `RegionCode` varchar(255) NOT NULL DEFAULT '',
  `RegionName` varchar(255) NOT NULL DEFAULT '',
  `City` varchar(255) NOT NULL DEFAULT '',
  `ZipCode` varchar(255) NOT NULL DEFAULT '',
  `TimeZone` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `LoginHistory`
--

CREATE TABLE `LoginHistory` (
  `ID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL DEFAULT 0,
  `TimeStamp` int(11) NOT NULL DEFAULT 0,
  `IP` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Machines`
--

CREATE TABLE `Machines` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Status` tinyint(4) NOT NULL DEFAULT 0,
  `LastError` varchar(255) NOT NULL DEFAULT '',
  `Type` varchar(255) NOT NULL DEFAULT '',
  `Network` varchar(255) NOT NULL DEFAULT '',
  `IP` varchar(16) DEFAULT NULL,
  `CreateOptions` text NOT NULL DEFAULT '',
  `BindUpdate` tinyint(4) NOT NULL DEFAULT 0,
  `Extra` text NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Networks`
--

CREATE TABLE `Networks` (
  `ID` int(11) NOT NULL,
  `Device` varchar(16) NOT NULL DEFAULT '',
  `NormalStatus` tinyint(4) NOT NULL DEFAULT 0,
  `IP` varchar(16) NOT NULL DEFAULT '',
  `Netmask` varchar(16) NOT NULL DEFAULT '',
  `Type` tinyint(4) NOT NULL DEFAULT 0,
  `Interface` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `PortForwards`
--

CREATE TABLE `PortForwards` (
  `ID` int(11) NOT NULL,
  `MachineID` int(11) NOT NULL DEFAULT 0,
  `Type` tinyint(4) NOT NULL DEFAULT 0,
  `InternalPort` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `ExternalPort` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `Comment` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Settings`
--

CREATE TABLE `Settings` (
  `ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Value` text NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Users`
--

CREATE TABLE `Users` (
  `ID` int(11) NOT NULL,
  `Username` varchar(255) NOT NULL DEFAULT '',
  `Password` varchar(255) NOT NULL DEFAULT '',
  `Email` varchar(255) DEFAULT NULL,
  `Status` tinyint(4) NOT NULL DEFAULT 0,
  `LastLogin` int(11) NOT NULL DEFAULT 0,
  `LastLogout` int(11) NOT NULL DEFAULT 0,
  `LastSeen` int(11) NOT NULL DEFAULT 0,
  `LastIP` varchar(255) NOT NULL DEFAULT '',
  `Joined` int(11) NOT NULL DEFAULT 0,
  `TimeZone` varchar(255) NOT NULL DEFAULT '',
  `DateFormat` varchar(16) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `BannedIPs`
--
ALTER TABLE `BannedIPs`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `IP` (`IP`);

--
-- Indexes for table `GeoIP`
--
ALTER TABLE `GeoIP`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `IP` (`IP`),
  ADD KEY `CountryCode` (`CountryCode`,`RegionCode`);

--
-- Indexes for table `LoginHistory`
--
ALTER TABLE `LoginHistory`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `UserID_2` (`UserID`,`IP`),
  ADD KEY `IP` (`IP`);

--
-- Indexes for table `Machines`
--
ALTER TABLE `Machines`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Name` (`Name`),
  ADD UNIQUE KEY `Network` (`Network`,`IP`),
  ADD KEY `Network_2` (`Network`),
  ADD KEY `BindUpdate` (`BindUpdate`);

--
-- Indexes for table `Networks`
--
ALTER TABLE `Networks`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Device` (`Device`);

--
-- Indexes for table `PortForwards`
--
ALTER TABLE `PortForwards`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MachineID` (`MachineID`);

--
-- Indexes for table `Settings`
--
ALTER TABLE `Settings`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Name` (`Name`);

--
-- Indexes for table `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `BannedIPs`
--
ALTER TABLE `BannedIPs`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `GeoIP`
--
ALTER TABLE `GeoIP`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `LoginHistory`
--
ALTER TABLE `LoginHistory`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Machines`
--
ALTER TABLE `Machines`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Networks`
--
ALTER TABLE `Networks`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PortForwards`
--
ALTER TABLE `PortForwards`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Settings`
--
ALTER TABLE `Settings`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Users`
--
ALTER TABLE `Users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
