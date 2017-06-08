# Change Log

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased](https://github.com/graze/morphism/compare/v2.0.0...HEAD)

- README and other documents cleaned up in preparation for open sourcing.
- Docker-ised the development and testing.
- Added basic support for multiple PHP versions.
- Added the ability to test the commands locally via docker.
- `schemaDefinitionPath` now defaults to `schema/<connectionName>`.

## [v2.0.0](https://github.com/graze/morphism/compare/v1.1.6...v2.0.0)

- 	Added optional `schemaDefinitionPath ` directive to config.
-  	Fastdump now obeys `schemaDefinitionPath ` and `dbname` directives.
-  Enabled creation of databases.
-  	Removed redundant `schema-path` argument
-  Removed `version` from `composer.json` and rely on tags.

## [v1.1.6](https://github.com/graze/morphism/compare/v1.1.5...v1.1.6)

- 	`morphism-dump` now honours the `exclude` config.
-  Only actual tables are dumped now. Views are ignored.

## [v1.1.5](https://github.com/graze/morphism/compare/v1.1.4...v1.1.5)
- Minor `composer.json` updates.

## [v1.1.4](https://github.com/graze/morphism/tree/v1.1.4)
- Initial work. No earlier version tags available.

