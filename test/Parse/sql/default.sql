
-- test ----------------------------------------
create table t (
    x text,
    y int
);
CREATE TABLE `t` (
    `x` text,
    `y` int(11) DEFAULT NULL
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    a int null,
    b int null default null,
    c int not null,
    d int not null default 0
);
CREATE TABLE `t` (
    `a` int(11) DEFAULT NULL,
    `b` int(11) DEFAULT NULL,
    `c` int(11) NOT NULL,
    `d` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB;
