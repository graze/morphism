
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
