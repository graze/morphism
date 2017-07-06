
-- test ----------------------------------------
create table t (
    x int,
    unique (x)
);
CREATE TABLE `t` (
    `x` int(11) DEFAULT NULL,
    UNIQUE KEY `x` (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    constraint unique (x)
);
CREATE TABLE `t` (
    `x` int(11) DEFAULT NULL,
    UNIQUE KEY `x` (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    unique key (x)
);
CREATE TABLE `t` (
    `x` int(11) DEFAULT NULL,
    UNIQUE KEY `x` (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    unique index (x)
);
CREATE TABLE `t` (
    `x` int(11) DEFAULT NULL,
    UNIQUE KEY `x` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    constraint con unique key (x)
);
CREATE TABLE `t` (
    `x` int(11) DEFAULT NULL,
    UNIQUE KEY `con` (`x`)
) ENGINE=InnoDB;
