-- test -----------
CREATE TABLE x (a int, b int, key k (a), key k (b));
exception RuntimeException "duplicate key name 'k'"

-- test -----------
CREATE TABLE x (a int, b int, key k (a), unique key k (b));
exception RuntimeException "duplicate key name 'k'"

-- test -----------
CREATE TABLE x (a int, b int, key (c));
exception RuntimeException "key column 'c' doesn't exist in table"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT KEY (a));
exception RuntimeException "bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT INDEX (a));
exception RuntimeException "bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT FULLTEXT (a));
exception RuntimeException "bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int, CONSTRAINT con KEY (a));
exception RuntimeException "bad CONSTRAINT"

-- test -----------
CREATE TABLE x (a int primary key, b int primary key);
exception RuntimeException "multiple PRIMARY KEYs defined"

