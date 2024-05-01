
-- test ----------------------------------------
create table t (
    x text,
    y int
);
CREATE TABLE `t` (
    `x` text,
    `y` int DEFAULT NULL
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    a int null,
    b int null default null,
    c int not null,
    d int not null default 0
);
CREATE TABLE `t` (
    `a` int DEFAULT NULL,
    `b` int DEFAULT NULL,
    `c` int NOT NULL,
    `d` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB;
