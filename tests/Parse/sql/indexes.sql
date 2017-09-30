-- test -----------
CREATE TABLE x (a int, b int, key k (a), key k (b));
exception RuntimeException "Duplicate key name 'k'"

-- test -----------
CREATE TABLE x (a int, b int, key k (a), unique key k (b));
exception RuntimeException "Duplicate key name 'k'"

-- test -----------
CREATE TABLE x (a int, b int, key (c));
exception RuntimeException "Key column 'c' doesn't exist in table"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT KEY (a));
exception RuntimeException "Bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT INDEX (a));
exception RuntimeException "Bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT FULLTEXT (a));
exception RuntimeException "Bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT con KEY (a));
exception RuntimeException "Bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int primary key, b int primary key);
exception RuntimeException "Multiple PRIMARY KEYs defined"

