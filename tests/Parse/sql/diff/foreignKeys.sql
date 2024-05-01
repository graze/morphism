-- test - Add unnamed foreign key
create table t (
    `ux` int DEFAULT NULL
);
create table t (
    `ux` int DEFAULT NULL,
    FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
ALTER TABLE `t`
ADD KEY `ux` (`ux`),
ADD CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)

-- test - Add named foreign key
create table t (
    `ux` int DEFAULT NULL
);
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
ALTER TABLE `t`
ADD KEY `forKey1` (`ux`),
ADD CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)

-- test - Key automatically gets added for foreign key as per MySQL
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
ALTER TABLE `t`
ADD KEY `forKey1` (`ux`)

-- test - Automatically added key already exists in the current table so no change is required
create table t (
    `ux` int DEFAULT NULL,
    KEY `forKey1` (`ux`),
    CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);

-- test - Drop unnamed foreign key
create table t (
    `ux` int DEFAULT NULL,
    FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
create table t (
    `ux` int DEFAULT NULL
);
ALTER TABLE `t`
DROP FOREIGN KEY `t_ibfk_1`

-- test - Drop named foreign key
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `forKey1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
create table t (
    `ux` int DEFAULT NULL
);
ALTER TABLE `t`
DROP FOREIGN KEY `forKey1`

-- test - Rename foreign key
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `foo` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
create table t (
    `ux` int DEFAULT NULL,
    CONSTRAINT `bar` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
);
ALTER TABLE `t`
ADD KEY `bar` (`ux`),
DROP FOREIGN KEY `foo`,
ADD CONSTRAINT `bar` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
