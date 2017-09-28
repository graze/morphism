-- test ----------------------------------------
create table t (
    ux int,
    foreign key (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    KEY `ux` (`ux`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test - named index
create table t (
    ux int,
    foreign key fk_t_u_x (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    KEY `fk_t_u_x` (`ux`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int, foreign key (ux) references u (x),
    vx int, foreign key (vx) references v (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    `vx` int(11) DEFAULT NULL,
    KEY `ux` (`ux`),
    KEY `vx` (`vx`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`),
    CONSTRAINT `t_ibfk_2` FOREIGN KEY (`vx`) REFERENCES `v` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int,
    constraint c1 foreign key (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    KEY `c1` (`ux`),
    CONSTRAINT `c1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int,
    key k1 (ux),
    foreign key (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    KEY `k1` (`ux`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int,
    ty int,
    key k1 (ux, ty),
    foreign key (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    `ty` int(11) DEFAULT NULL,
    KEY `k1` (`ux`,`ty`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int,
    ty int,
    key k1 (ty, ux),
    foreign key (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    `ty` int(11) DEFAULT NULL,
    KEY `k1` (`ty`,`ux`),
    KEY `ux` (`ux`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int,
    ty int,
    key k1 (ty),
    foreign key (ux) references u (x)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    `ty` int(11) DEFAULT NULL,
    KEY `k1` (`ty`),
    KEY `ux` (`ux`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;

-- test ----------------------------------------
create table t (
    ux int,
    ty int,
    foreign key (ux) references u (x),
    key k1 (ty)
);
CREATE TABLE `t` (
    `ux` int(11) DEFAULT NULL,
    `ty` int(11) DEFAULT NULL,
    KEY `ux` (`ux`),
    KEY `k1` (`ty`),
    CONSTRAINT `t_ibfk_1` FOREIGN KEY (`ux`) REFERENCES `u` (`x`)
) ENGINE=InnoDB;
