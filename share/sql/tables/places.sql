DROP TABLE IF EXISTS `places`;

CREATE TABLE IF NOT EXISTS `places` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  `good-for` TEXT NOT NULL,
  `location` TEXT NOT NULL
);
