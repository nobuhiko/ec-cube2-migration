# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-31

### Added

- Initial stable release of EC-CUBE 2 Migration Tool
- Database-agnostic migration system supporting MySQL, PostgreSQL, and SQLite
- Fluent API for schema definition
  - `create()` - Create new tables
  - `table()` - Modify existing tables
  - `drop()` - Drop tables
- Column types: `serial`, `integer`, `smallInteger`, `bigInteger`, `string`, `text`, `boolean`, `decimal`, `timestamp`, `blob`
- Column modifiers: `notNull()`, `nullable()`, `default()`
- Index support: `index()`, `unique()`
- Raw SQL execution with database-specific variants via `rawSql()`
- CLI commands integrated with ec-cube2/cli
  - `migrate:create` - Create new migration files
  - `migrate` - Run pending migrations
  - `migrate:status` - Show migration status
  - `migrate:rollback` - Rollback migrations
- Automatic sequence/auto-increment handling per database platform
- Version tracking in `dtb_migration` table
- Comprehensive test suite (74 tests, 176 assertions)
- CI/CD with GitHub Actions testing PHP 7.4-8.4
- Integration tests for MySQL 8.4, PostgreSQL 16, and SQLite

### Fixed

- Version comparison for proper migration ordering
- Duplicate PRIMARY KEY constraint in PostgreSQL
- Duplicate NOT NULL constraint issues
- PostgreSQL sequence creation with IF NOT EXISTS
- MySQL inline PRIMARY KEY handling via `isPrimaryKeyInline()`
