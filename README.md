# Graze\morphism #

<img src="http://i.imgur.com/FuzIxpl.jpg" alt="Keep Moving" align="right" width="240"/>

This package provides a set of tools for parsing, extracting, and diffing mysqldump 
files.

A typical application of this is for managing schema changes during application
development (keeping schemas in sync with code when switching branches), and during
deployment (migrating the schema to match the deployed code).

We were previously using an internally developed database migration tool ("migrant")
which relies on 'up' and 'down' scripts to manage migration between schema versions.
This has a number of issues however. In particular it assumes that schema evolution
is linear - you can only ever move forward in time to a newer version, or back to
an older version. In practice, modern development is such that you may be working
on several different possible versions of the future state of the schema in
parallel as you switch between different development branches.

After some discussion with interested parties, we agreed to develop a tool that
would allow us to store the complete database schema in the repo. When a branch 
requires a schema update to work properly, you should edit your checkout's schema
and run the new tool to figure out the necessary ALTER / CREATE / DROP statements
to run, and apply them. Similarly, when switching branches you can simply run the
tool and it will apply the necessary changes automatically.

This has the additional benefit that the complete history of the schema is stored
under version control, instead of a series of incremental change scripts. If more 
than one party changes the same table, git will merge the changes automatically, 
or generate a conflict for manual merging where it cannot. All the usual git tools
become useful - e.g. a simple "git annotate schema/live_web/product.sql" can tell
you who added a redundant index on 'pr\_name'.

### Example Usage ###

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
(catalog-fixes) $ vendor/bin/morphism-lint schema/live_web # check syntax
ERROR schema/catalog/product_dimensions.sql, line 2: unknown datatype 'intt'
1: CREATE TABLE product_dimensions (
2:   `pd_id` intt<<HERE>>(10) unsigned NOT NULL AUTO_INCREMENT,
(catalog-fixes) $ vi schema/catalog/product_dimensions.sql  # fix table definition
(catalog-fixes) $ vendor/bin/morphism-lint schema/live_web # check syntax
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


### License ###
The content of this library is released under the **MIT License** by **Nature Delivered Ltd**.<br/>
You can find a copy of this license at http://www.opensource.org/licenses/mit or in [`LICENSE`][license]


<!-- Links -->
[license]: /LICENSE
