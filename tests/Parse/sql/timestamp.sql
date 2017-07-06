
-- test ----------------------------------------
create table t (
    ts1 timestamp 
);
CREATE TABLE `t` (
    `ts1` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp not null 
);
CREATE TABLE `t` (
    `ts1` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp null 
);
CREATE TABLE `t` (
    `ts1` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp default '1970-08-12 00:00:00' 
);
CREATE TABLE `t` (
    `ts1` timestamp NOT NULL DEFAULT '1970-08-12 00:00:00'
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp on update now(
) );
CREATE TABLE `t` (
    `ts1` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp default now()
);
CREATE TABLE `t` (
    `ts1` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp,
    ts2 timestamp 
);
CREATE TABLE `t` (
    `ts1` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `ts2` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp null,
    ts2 timestamp default now()
);
CREATE TABLE `t` (
    `ts1` timestamp NULL DEFAULT NULL,
    `ts2` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ts1 timestamp null default now() on update now()
);
CREATE TABLE `t` (
    `ts1` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
CREATE TABLE x (
    t1 timestamp default current_timestamp,
    t2 timestamp default current_timestamp
);
CREATE TABLE `x` (
    `t1` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `t2` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
CREATE TABLE x (
    t1 timestamp on update current_timestamp,
    t2 timestamp default current_timestamp
);
CREATE TABLE `x` (
    `t1` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
    `t2` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- test ----------------------------------------
CREATE TABLE `x` (
    `t1` timestamp NOT NULL on update current_timestamp,
    `t2` timestamp NOT NULL on update current_timestamp
);
CREATE TABLE `x` (
    `t1` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
    `t2` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


