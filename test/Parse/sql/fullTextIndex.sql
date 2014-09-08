
-- test ----------------------------------------
create table t (
    x text,
    fulltext (x
) );
CREATE TABLE `t` (
    `x` text, 
    FULLTEXT KEY `x` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x text,
    fulltext key (x)
);
CREATE TABLE `t` (
    `x` text, 
    FULLTEXT KEY `x` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    x text,
    fulltext index (x)
);
CREATE TABLE `t` (
    `x` text,
    FULLTEXT KEY `x` (`x`)
) ENGINE=InnoDB;
