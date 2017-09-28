-- test - Add a column at the end
create table t (
    a int
);
create table t (
    a int,
    b int
);
ALTER TABLE `t`
ADD COLUMN `b` int(11) DEFAULT NULL

-- test - Add a column at the beginning
create table t (
    a int
);
create table t (
    b int,
    a int
);
ALTER TABLE `t`
ADD COLUMN `b` int(11) DEFAULT NULL FIRST

-- test - Add a column at somewhere in the middle
create table t (
    a int,
    b int
);
create table t (
    a int,
    c int,
    b int
);
ALTER TABLE `t`
ADD COLUMN `c` int(11) DEFAULT NULL AFTER `a`

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

-- test - Make a column not null
create table t (a int);
create table t (a int not null);
ALTER TABLE `t`
MODIFY COLUMN `a` int(11) NOT NULL

-- test - Make a column nullable
create table t (a int not null);
create table t (a int);
ALTER TABLE `t`
MODIFY COLUMN `a` int(11) DEFAULT NULL

-- test - Redordering columns: Move a column to the start
create table t (
    a int,
    b int,
    c int
);
create table t (
    c int,
    a int,
    b int
);
ALTER TABLE `t`
MODIFY COLUMN `c` int(11) DEFAULT NULL FIRST

-- test - Redordering columns: Move a column to the end
create table t (
    a int,
    b int,
    c int
);
create table t (
    b int,
    c int,
    a int
);
ALTER TABLE `t`
MODIFY COLUMN `b` int(11) DEFAULT NULL FIRST,
MODIFY COLUMN `c` int(11) DEFAULT NULL AFTER `b`

-- test - Redordering columns: Move a column in the middle
create table t (
    a int,
    b int,
    c int
);
create table t (
    a int,
    c int,
    b int
);
ALTER TABLE `t`
MODIFY COLUMN `c` int(11) DEFAULT NULL AFTER `a`

