# Drupal Architecture

## Purpose

Drupal is a content management framework and CMS built on PHP. It provides a fully object-oriented, service-container-based architecture enabling developers to build complex web applications with structured content, access control, routing, caching, and multilingual support.

## Directory Structure

```
core/
  lib/Drupal/Core/         # Core PHP framework classes (OOP, services, APIs)
    Entity/                # Entity API — content & config entity base classes
    Field/                 # Field API — typed, translatable field system
    Database/              # Database abstraction layer (PDO wrapper)
    Routing/               # Symfony-based HTTP routing with access checks
    Cache/                 # Pluggable cache backends with cache tags/contexts
    Config/                # Configuration management system (YAML-backed)
    Plugin/                # Plugin discovery and manager infrastructure
    DependencyInjection/   # Service container, service definitions
    Access/                # Access control policies and result objects
    Session/               # Session management and account context
    Language/              # Language negotiation and translation
  modules/                 # Bundled core modules (node, user, taxonomy, etc.)
  themes/                  # Core themes
  tests/                   # PHPUnit + Kernel + Functional test suites
modules/                   # Contributed and custom modules
profiles/                  # Installation profiles
sites/                     # Multi-site configuration
```

## Key Design Decisions

### Service Container (Dependency Injection)
All major Drupal subsystems are registered as services in `core.services.yml` and loaded through a compiled Symfony DI container. This enables complete testability and replaceability of any subsystem.

### Entity API
Two entity categories exist:
- **Content entities** (`ContentEntityBase`) — translatable, revisionable, field-based (nodes, users, taxonomy terms).
- **Config entities** — stored in config system as YAML, lightweight, no fields API.

Entity types are declared via PHP attributes/annotations and discovered automatically. Fields are defined as `BaseFieldDefinition` objects on the entity class or as configurable fields added by site administrators.

### Database Abstraction Layer
`Connection` wraps a PDO-like client and provides:
- Table-prefixed query execution via `{table}` syntax.
- Query builder objects (`Select`, `Insert`, `Update`, `Delete`, `Merge`) for driver-portable SQL.
- Prepared statement caching and argument expansion for `IN` clauses.
- SQL injection protection: semicolons and multi-statement queries are rejected by default.

### Routing
Routes are defined in `*.routing.yml` files. The `AccessAwareRouter` wraps Symfony's router to inject Drupal access-checking before dispatching. Access results are cacheable and attached to the HTTP response for Drupal's Page Cache.

### Plugin System
Drupal's plugin system enables extensible lists of implementations (field types, blocks, views handlers, etc.) discovered from code annotations/attributes or YAML. Plugin managers cache discoveries.

## Extension Points

- **Hooks**: `hook_entity_presave()`, `hook_form_alter()`, etc. — procedural callbacks invoked at defined points.
- **Event subscribers**: Symfony events dispatched by the kernel and Drupal modules.
- **Services**: Override any service by tagging a replacement in `*.services.yml`.
- **Plugins**: Implement a plugin interface and annotate/attribute the class; the plugin manager finds it automatically.
- **Entity type hooks**: `hook_entity_type_alter()` modifies entity definitions at bootstrap.

## Dependency Flow

```
HTTP Request
  └─> DrupalKernel (Symfony HttpKernelInterface)
        └─> AccessAwareRouter (route match + access check)
              └─> Controller (resolved via service container)
                    └─> Entity/Field/Config APIs
                          └─> Database Connection (PDO driver)
                                └─> Cache backends (memory, database, Redis, etc.)
```

## Coding Standards

- PHP 8.1+ required; strict types declared in all files.
- PSR-12 code style enforced via phpcs.
- Snake_Case class names (Drupal-specific convention in this fork).
- All public APIs must have full PHPDoc with `@param`, `@return`, `@throws`.
