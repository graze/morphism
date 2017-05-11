CREATE TABLE `product_ingredient_map` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL REFERENCES product(id),
  `ingredient_id` int(11) NOT NULL REFERENCES ingredient(id),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
