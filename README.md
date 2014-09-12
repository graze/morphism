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
become useful - e.g. a simple "git annotate schema/catalog/product.sql" can tell
you who added a redundant index on 'pr\_name'.

### Example Usage ###

```
(master) $ # create a baseline for the schema
(master) $ mkdir schema
(master) $ mysqldump --no-data catalog |
    vendor/graze/morphism/bin/normalise --output=schema/catalog
(master) $ git add schema
(master) $ git commit -m "initial schema checkin"
(master) $ 
(master) $ # start work on changes to the catalog...
(master) $ git checkout -b catalog-fixes
(catalog-fixes) $ vi schema/catalog/product.sql             # edit table definition
(catalog-fixes) $ vi schema/catalog/product_dimensions.sql  # add new table 
(catalog-fixes) $ git add schema/catalog
(catalog-fixes) $ git rm schema/catalog/discontinued.sql    # delete a table
(catalog-fixes) $ git commit -m "various changes to catalog schema"
(catalog-fixes) $ # alter the database to match the schema files
(catalog-fixes) $ mysqldump --no-data catalog |
    vendor/graze/morphism/bin/diff - schema/catalog | mysql catalog
(catalog-fixes) $ # hack hack hack
(catalog-fixes) $ ...
(catalog-fixes) $ # do some work back on master...
(catalog-fixes) $ git checkout master
(master) $ # restore schema to previous state
(master) $ mysqldump --no-data catalog | 
    vendor/graze/morphism/bin/diff - schema/catalog | 
    mysql catalog
```


### License ###
The content of this library is released under the **MIT License** by **Nature Delivered Ltd**.<br/>
You can find a copy of this license at http://www.opensource.org/licenses/mit or in [`LICENSE`][license]


<!-- Links -->
[license]: /LICENSE
