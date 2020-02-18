CREATE TABLE `ingredient` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `is_active_binary` CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
