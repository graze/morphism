-- test - Add primary key
create table t (a int);
create table t (a int primary key);
ALTER TABLE `t`
MODIFY COLUMN `a` int NOT NULL,
ADD PRIMARY KEY (`a`)

-- test - Remove primary key
create table t (a int primary key);
create table t (a int);
ALTER TABLE `t`
MODIFY COLUMN `a` int DEFAULT NULL,
DROP PRIMARY KEY

-- test - Add a named index
create table t (
    a int
);
create table t (
    a int,
    key foo (a)
);
ALTER TABLE `t`
ADD KEY `foo` (`a`)

-- test - Remove a named index
create table t (
    a int,
    key foo (a)
);
create table t (
    a int
);
ALTER TABLE `t`
DROP KEY `foo`

-- test - Rename an index
create table t (
    a int,
    key foo (a)
);
create table t (
    a int,
    key bar (a)
);
ALTER TABLE `t`
DROP KEY `foo`,
ADD KEY `bar` (`a`)

-- test - Move a named index to a different column
create table t (
    a int,
    b int,
    key foo (a)
);
create table t (
    a int,
    b int,
    key foo (b)
);
ALTER TABLE `t`
DROP KEY `foo`,
ADD KEY `foo` (`b`)
