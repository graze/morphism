
-- test ----------------------------------------
create table t (
    x text
);
CREATE TABLE `t` (
    `x` text 
) ENGINE=InnoDB;

-- test ----------------------------------------
create table if not exists t (
    x text 
);
CREATE TABLE `t` (
    `x` text
) ENGINE=InnoDB;

-- test -----------
CREATE TABLE x (a int, b int, a int);
exception RuntimeException "duplicate column name 'a'"

-- test -----------
CREATE TABLE x (a int, b int, A int);
exception RuntimeException "duplicate column name 'A'"

