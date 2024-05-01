
-- test ----------------------------------------
create table t (
    id int unsigned auto_increment primary key,
    x text
);
CREATE TABLE `t` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `x` text,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int primary key
); 
CREATE TABLE `t` (
    `x` int NOT NULL,
    PRIMARY KEY (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int not null primary key
);
CREATE TABLE `t` (
    `x` int NOT NULL,
    PRIMARY KEY (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    primary key (x)
);
CREATE TABLE `t` (
    `x` int NOT NULL DEFAULT '0',
    PRIMARY KEY (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int not null,
    primary key(x)
);
CREATE TABLE `t` (
    `x` int NOT NULL,
    PRIMARY KEY (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    y int,
    primary key (x,y) 
);
CREATE TABLE `t` (
    `x` int NOT NULL DEFAULT '0',
    `y` int NOT NULL DEFAULT '0',
    PRIMARY KEY (`x`,`y`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    constraint primary key (x)
);
CREATE TABLE `t` (
    `x` int NOT NULL DEFAULT '0',
    PRIMARY KEY (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x int,
    constraint con primary key (x)
);
CREATE TABLE `t` (
    `x` int NOT NULL DEFAULT '0',
    PRIMARY KEY (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x bit(3),
    primary key (x) 
);
CREATE TABLE `t` (
    `x` bit(3) NOT NULL DEFAULT b'0',
    PRIMARY KEY (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x bit(3) primary key 
);
CREATE TABLE `t` (
    `x` bit(3) NOT NULL,
    PRIMARY KEY (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x set('a','b','c'),
    primary key (x
) );
CREATE TABLE `t` (
    `x` set('a','b','c') NOT NULL DEFAULT '',
    PRIMARY KEY (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x enum('a','b','c'),
    primary key (x)
);
CREATE TABLE `t` (
    `x` enum('a','b','c') NOT NULL DEFAULT 'a',
    PRIMARY KEY (`x`) 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x decimal(8,3),
    primary key (x)
);
CREATE TABLE `t` (
    `x` decimal(8,3) NOT NULL DEFAULT '0.000',
    PRIMARY KEY (`x`) 
) ENGINE=InnoDB;
