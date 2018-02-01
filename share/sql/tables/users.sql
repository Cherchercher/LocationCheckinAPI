DROP TABLE IF EXISTS `users`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  `firstname` varchar(255) default NULL,
  `lastname` varchar(255) default NULL,
  `email` varchar(255) UNIQUE default NULL,
  `phone` varchar(100) default NULL,
  `based-location` varchar(255) default NULL,
  `current-location` varchar(255) default NULL,
  `yearly-spending` INTEGER default NULL,
);
