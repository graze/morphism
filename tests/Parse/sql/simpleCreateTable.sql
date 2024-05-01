
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

-- test ----------------------------------------
CREATE TABLE x (a int, b int, a int);
exception RuntimeException "Duplicate column name 'a'"

-- test ----------------------------------------
CREATE TABLE x (a int, b int, A int);
exception RuntimeException "Duplicate column name 'A'"

-- test ----------------------------------------
foo bar create table x (a int);
exception RuntimeException "Expected CREATE TABLE"

-- test ----------------------------------------
create table x (a int;
exception RuntimeException "Expected ',' or '\)'"

-- test ----------------------------------------
create table x (a);
exception RuntimeException "Expected a datatype"

-- test ----------------------------------------
create table x (int int);
CREATE TABLE `x` (
    `int` int DEFAULT NULL
) ENGINE=InnoDB;

-- test ----------------------------------------
create table x (a a);
exception RuntimeException "Unknown datatype 'a'"

-- test ----------------------------------------
create table x ();
exception RuntimeException "Expected identifier"

-- test ----------------------------------------
create table `table` (a int);
CREATE TABLE `table` (
    `a` int DEFAULT NULL
) ENGINE=InnoDB;

-- test - Multiple auto increment columns
create table x (
    a int auto_increment primary key,
    b int auto_increment
);
exception RuntimeException "There can be only one AUTO_INCREMENT column"

-- test - Non-key auto increment column
create table x (
    a int auto_increment
);
exception RuntimeException "AUTO_INCREMENT column must be defined as a key"
