
-- test ----------------------------------------
create table t (
    x int,
    index (x)
);
CREATE TABLE `t` ( 
    `x` int DEFAULT NULL,
    KEY `x` (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    key (x) 
);
CREATE TABLE `t` (
    `x` int DEFAULT NULL,
    KEY `x` (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    y int,
    key (x),
    key (x,y)
);
CREATE TABLE `t` (
    `x` int DEFAULT NULL,
    `y` int DEFAULT NULL,
    KEY `x` (`x`),
    KEY `x_2` (`x`,`y`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    y int,
    primary key (x),
    key (x,y)
);
CREATE TABLE `t` (
    `x` int NOT NULL DEFAULT '0',
    `y` int DEFAULT NULL,
    PRIMARY KEY (`x`),
    KEY `x` (`x`,`y`) 
) ENGINE=InnoDB;
