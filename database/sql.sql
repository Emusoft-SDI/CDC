-- Idempotent schema definition for NATCODEV database
-- This script ensures tables and columns exist without overwriting existing data.
-- Target: MariaDB 10.11+ / MySQL compatible syntax.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Example: Creating a table if it doesn't exist
CREATE TABLE IF NOT EXISTS `agent_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` int(11) DEFAULT NULL,
  `battery_level` int(11) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `agent_id` (`agent_id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Example: Adding a column if it doesn't exist
ALTER TABLE `applications`
  ADD COLUMN IF NOT EXISTS `state_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `lga_id` int(11) DEFAULT NULL;

-- ... Repeat for other tables and columns as needed ...

COMMIT;
