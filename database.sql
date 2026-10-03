-- ===========================================================================
--  TWITTPAY - Smart SMM Panel v4
--  Adds the deposit method row. Import this once, in phpMyAdmin, into your
--  panel's database. Nothing else in the database is touched.
--
--  The Brand Key are left empty on purpose - fill them in from
--  the panel's admin area after importing.
-- ===========================================================================

INSERT INTO `payments` (`id`, `type`, `name`, `min`, `max`, `sort`, `new_users`, `status`, `params`) VALUES (NULL, 'twittpay', 'Bkash/Nagad/Rocket/Upay', '1', '100', NULL, '1', '1', '{\"type\":\"twittpay\",\"name\":\"Bkash\\/Nagad\\/Rocket\\/Upay\",\"min\":\"1\",\"max\":\"100\",\"new_users\":\"1\",\"status\":\"1\",\"option\":{\"tnx_fee\":\"0\",\"api_key\":\"\",\"currency_rate\":\"120\"}}');
