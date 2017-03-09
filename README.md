# morphism

<img src="http://i.imgur.com/FuzIxpl.jpg" alt="Keep Moving" align="right" width="240"/>

This package provides a set of tools for parsing, extracting, and diffing mysqldump
files.

A typical application of this is for managing schema changes during application
development (keeping schemas in sync with code when switching branches), and during
deployment (migrating the schema to match the deployed code).

We were previously using an internally developed database migration tool ("migrant")
which relied on 'up' and 'down' scripts to manage migration between schema versions.
This had a number of issues however. In particular it assumes that schema evolution
is linear - you can only ever move forward in time to a newer version, or back to
an older version. In practice, modern development is such that you may be working
on several different possible versions of the future state of the schema in
parallel as you switch between different development branches.

We decide to develop a tool that
would allow us to store the complete database schema in the repository. When a branch
requires a schema update to work properly, you should edit your checkout's schema
and run the new tool to figure out the necessary `ALTER` / `CREATE` / `DROP` statements
to run, and apply them. Similarly, when switching branches you can simply run the
tool and it will apply the necessary changes automatically.

This has the additional benefit that the complete history of the schema is stored
under version control, instead of a series of incremental change scripts. If more
than one party changes the same table, git will merge the changes automatically,
or generate a conflict for manual merging where it cannot. All the usual git tools
become useful - e.g. a simple `git annotate schema/catalog/product.sql` can tell
you who added a redundant index on `pr_name`.

## Unit tests

Execute ```make test``` from a shell prompt to run the unit test suite.

## Tools

### morphism-extract
```
Usage: morphism-extract [OPTIONS] [MYSQL-DUMP-FILE]
Extracts schema definition(s) from a mysqldump file. Multiple databases may
be defined in the dump, and they will be extracted to separate directories.
You might use this tool when initialising the schema directory from a dump
created on a production server with 'mysqldump --no-data'.

OPTIONS
  -h, -help, --help   display this message, and exit
  --[no-]quote-names  [do not] quote names with `...`; default: no
  --schema-path=PATH  location of schemas; default: ./schema
  --database=NAME     name of database if not specified in dump
  --[no-]write        write schema files to schema path; default: no
```

### morphism-dump
```
Usage: morphism-dump [OPTIONS] CONFIG-FILE CONN [CONN ...]
Dumps database schemas for named connections. This tool is considerably faster
than mysqldump, especially for large schemas. You might use this tool to
(re-)initalise your project's schema directory from a local database.

OPTIONS
  -h, -help, --help   display this message, and exit
  --[no-]quote-names  [do not] quote names with `...`; default: no
  --[no-]write        write schema files to schema path; default: no

CONFIG-FILE
A YAML file mapping connection names to parameters. See the morphism project's
README.md file for detailed information.
```

### morphism-lint
```
Usage: morphism-lint [OPTIONS] PATH ...
Checks all schema files below the specified paths for correctness. If no PATH
is given, checks standard input. By default output is only produced if errors
are detected.

OPTIONS
  -h, -help, --help   display this message, and exit
  --[no-]verbose      include valid files in output; default: no

EXIT STATUS
The exit status will be 1 if any errors were detected, or 0 otherwise.
```

### morphism-diff
```
Usage: morphism-diff [OPTION] CONFIG-FILE [CONN] ...
Extracts schema definitions from the named connections, and outputs the
necessary ALTER TABLE statements to transform them into what is defined
under the schema path. If no connections are specified, all connections
in the config with 'morphism: enable: true' will be used.

GENERAL OPTIONS:
  -h, -help, --help      display this message, and exit
  --engine=ENGINE        set the default database engine
  --collation=COLLATION  set the default collation
  --[no-]quote-names     quote names with `...`; default: yes
  --[no-]create-table    output CREATE TABLE statements; default: yes
  --[no-]drop-table      output DROP TABLE statements; default: yes
  --[no-]alter-engine    output ALTER TABLE ... ENGINE=...; default: yes
  --apply-changes=WHEN   apply changes (yes/no/confirm); default: no
  --log-dir=DIR          log applied changes to DIR - one log file will be
                         created per connection; default: none
  --[no-]log-skipped     log skipped queries (commented out); default: yes

CONFIG-FILE
A YAML file mapping connection names to parameters. See the morphism project's
README.md file for detailed information.
```

## Config File

The config file used by some of morphism's tools uses yaml format, as follows:

```
# All connection definitions appear under the 'databases' key
databases:
    # name of connection
    catalog:
        # Connection details - this is just an example, you may want to specify
        # different properties, e.g. if connecting to a remote server. You are
        # advised to refer to the 'pdo' documentation for further details.
        user: 'my-user'
        password: 'my-password'
        host: 'localhost'
        driver: 'pdo_mysql'
        unix_socket: '/var/lib/mysql/catalog.sock'
        # morphism specific options
        morphism:
            # morphism-diff only operates on connections with 'enable: true'
            enable: true
            # you may optionally specify one or more regexes matching tables
            # to exclude (any changes, creation or deletion of matching tables
            # will be ignored). The regex must match the entire table name, i.e.
            # it is implicitly anchored with ^...$
            exclude:
                - temp_.*
                - page_load_\d{4}-\d{2}-\d{2}
            # similarly, you may optionally specify tables for explicit inclusion.
            include:
                ...
    # you may specify more connections
    ...
# other top level keys are ignored
...
```

## Example Usage

```
(master) $ # create a baseline for the schema
(master) $ mkdir schema
(master) $ vendor/bin/morphism-dump --write config.yml catalog
(master) $ git add schema/catalog
(master) $ git commit -m "initial checkin of catalog schema"
(master) $
(master) $ # start work on changes to the catalog...
(master) $ git checkout -b catalog-fixes
(catalog-fixes) $ vi schema/catalog/product.sql             # edit table definition
(catalog-fixes) $ vi schema/catalog/product_dimensions.sql  # add new table
(catalog-fixes) $ vendor/bin/morphism-lint schema/catalog   # check syntax
ERROR schema/catalog/product_dimensions.sql, line 2: unknown datatype 'intt'
1: CREATE TABLE product_dimensions (
2:   `pd_id` intt<<HERE>>(10) unsigned NOT NULL AUTO_INCREMENT,
(catalog-fixes) $ vi schema/catalog/product_dimensions.sql  # fix table definition
(catalog-fixes) $ vendor/bin/morphism-lint schema/catalog   # check syntax
(catalog-fixes) $ git add schema/catalog
(catalog-fixes) $ git rm schema/catalog/discontinued.sql    # delete a table
(catalog-fixes) $ git commit -m "various changes to catalog schema"
(catalog-fixes) $ # alter the database to match the schema files
(catalog-fixes) $ vendor/bin/morphism-diff --apply-changes=confirm config.yml catalog
-- --------------------------------
--   Connection: catalog
-- --------------------------------
DROP TABLE IF EXISTS `discontinued`;

ALTER TABLE `product`
MODIFY COLUMN `pr_name` varchar(255) NOT NULL,
MODIFY COLUMN `pr_description` text NOT NULL,
ADD COLUMN `pr_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `pr_description`;

CREATE TABLE `product_dimensions` (
  `pd_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pd_width` decimal(10,2) NOT NULL,
  `pd_height` decimal(10,2) NOT NULL,
  `pd_depth` decimal(10,2) NOT NULL,
  PRIMARY KEY (`pd_id`)
) ENGINE=InnoDB;

-- Confirm changes to catalog:

DROP TABLE IF EXISTS `discontinued`;

-- Apply this change? [y]es [n]o [a]ll [q]uit: y

ALTER TABLE `product`
MODIFY COLUMN `pr_name` varchar(255) NOT NULL,
MODIFY COLUMN `pr_description` text NOT NULL,
ADD COLUMN `pr_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `pr_description`;

-- Apply this change? [y]es [n]o [a]ll [q]uit: y

CREATE TABLE `product_dimensions` (
  `pd_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pd_width` decimal(10,2) NOT NULL,
  `pd_height` decimal(10,2) NOT NULL,
  `pd_depth` decimal(10,2) NOT NULL,
  PRIMARY KEY (`pd_id`)
) ENGINE=InnoDB;

-- Apply this change? [y]es [n]o [a]ll [q]uit: y
(catalog-fixes) $ # hack hack hack
(catalog-fixes) $ ...
(catalog-fixes) $ # do some work back on master...
(catalog-fixes) $ git checkout master
(master) $ # restore schema to previous state
(master) $ vendor/bin/morphism-diff --apply-changes=yes config.yml catalog
```


## License
The content of this library is released under the **MIT License** by **Nature Delivered Ltd**.<br/>
You can find a copy of this license at http://www.opensource.org/licenses/mit or in [`LICENSE`][license]


<!-- Links -->
[license]: /LICENSE
