-- test - Add a column
create table t (
    a int
);
create table t (
    a int,
    b int
);
ALTER TABLE `t`
ADD COLUMN `b` int(11) DEFAULT NULL

-- test - Remove a column
create table t (
    a int,
    b int
);
create table t (
    a int
);
ALTER TABLE `t`
DROP COLUMN `b`

-- test - Modify a column type
create table t (a int);
create table t (a char(10));
ALTER TABLE `t`
MODIFY COLUMN `a` char(10) DEFAULT NULL
