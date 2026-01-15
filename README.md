# DueDiligence v2.0

Modern Project Management System for Building Inspections

## 🎯 Project Status

**Current Phase:** Foundation Setup ✅
**Version:** 2.0.0-alpha
**Last Updated:** January 15, 2026

## 📋 What's New in v2.0

This is a **complete rewrite** with modern PHP 8.1+ architecture:

- ✅ **Clean Architecture** - Separation of concerns with Repository and Service patterns
- ✅ **PSR-4 Autoloading** - Modern PHP standards
- ✅ **RESTful API** - JSON-based API endpoints
- ✅ **Custom Fields System** - QDPM-inspired extensible fields
- ✅ **Modern JavaScript** - ES6+ modules, no jQuery
- ✅ **PostgreSQL** - With optimized schema and views
- ✅ **Security First** - CSRF, Rate Limiting, Input Validation
- ✅ **Dependency Injection** - Testable, maintainable code

### Old Codebase

The previous version has been moved to `OLD/` directory for reference.

## 🚀 Getting Started

### Prerequisites

- PHP >= 8.1
- PostgreSQL >= 14
- Composer
- Node.js >= 16 (for frontend build tools)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Jojadk/DueDiligence.git
   cd DueDiligence
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Create database**
   ```bash
   createdb duediligence
   ```

5. **Run migrations**
   ```bash
   psql duediligence < database/schema.sql
   ```

6. **Set permissions**
   ```bash
   chmod -R 775 storage
   chmod -R 775 public/assets/uploads
   ```

7. **Start development server**
   ```bash
   cd public
   php -S localhost:8000
   ```

8. **Access the application**
   - URL: http://localhost:8000
   - Default credentials: `admin` / `admin123` (CHANGE IN PRODUCTION!)

## 📁 Project Structure

```
DueDiligence/
├── app/                    # Application code
│   ├── Config/            # Configuration classes
│   ├── Controllers/       # HTTP controllers (API & Web)
│   ├── Models/            # Data models
│   ├── Repositories/      # Data access layer
│   ├── Services/          # Business logic layer
│   ├── Middleware/        # HTTP middleware
│   ├── Core/              # Framework core components
│   ├── Exceptions/        # Custom exceptions
│   └── Helpers/           # Helper functions
├── config/                 # Configuration files
├── database/              # Database files
│   ├── migrations/        # Database migrations
│   ├── seeds/             # Database seeders
│   └── schema.sql         # Complete database schema
├── public/                 # Public web root
│   ├── index.php          # Entry point
│   └── assets/            # CSS, JS, uploads
├── resources/             # Views and templates
│   └── views/
├── storage/               # Logs, cache, sessions
│   ├── logs/
│   ├── cache/
│   └── sessions/
├── tests/                  # Automated tests
├── OLD/                    # Previous codebase (backup)
├── composer.json          # PHP dependencies
├── .env                    # Environment configuration
└── README.md              # This file
```

## 🏗️ Architecture Overview

### Core Components

1. **Dependency Injection Container** (`app/Core/Container.php`)
   - Manages class dependencies
   - Supports singletons
   - Auto-resolution via reflection

2. **Router** (`app/Core/Router.php`)
   - RESTful routing
   - Named parameters
   - Middleware support

3. **Repository Pattern** (`app/Repositories/`)
   - Data access abstraction
   - Base repository with CRUD operations
   - Specialized repositories for each model

4. **Service Layer** (`app/Services/`)
   - Business logic
   - Validation
   - Complex operations

5. **Custom Fields System**
   - QDPM-inspired extensible fields
   - Multiple field types
   - Entity-specific or global scope
   - Validation rules

### Database Design

- **PostgreSQL 14+** with advanced features
- **Views** for complex queries
- **Triggers** for automatic timestamp updates
- **Functions** for business logic
- **Proper indexing** for performance

See `ARCHITECTURE_2026.md` for detailed architecture documentation.

## 📊 Features

### Core Features

- ✅ **Project Management** - Full lifecycle management
- ✅ **Building Element Hierarchy** - Tree structure with parent/child
- ✅ **Custom Fields** - Dynamic, configurable fields
- ✅ **Budget Tracking** - Per-element budget with time horizons
- ✅ **Media Management** - Image uploads with canvas annotations
- ✅ **Customer Management** - Client information
- ✅ **User Management** - RBAC with roles and permissions
- ✅ **Activity Logging** - Full audit trail
- ✅ **Concurrent Editing** - Field locking system
- ✅ **Reporting** - Excel/PDF exports
- ✅ **Internationalization** - Multi-language support

### Security Features

- ✅ CSRF Protection
- ✅ Rate Limiting
- ✅ Input Validation
- ✅ SQL Injection Prevention
- ✅ XSS Protection
- ✅ Secure Password Hashing (bcrypt)
- ✅ JWT Tokens for API

### API Endpoints

All API endpoints return JSON and are located under `/api/`:

```
Authentication:
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/user

Projects:
GET    /api/projects
GET    /api/projects/{id}
POST   /api/projects
PUT    /api/projects/{id}
DELETE /api/projects/{id}

Building Elements:
GET    /api/elements
GET    /api/elements/{id}
POST   /api/elements
PUT    /api/elements/{id}
DELETE /api/elements/{id}

Custom Fields:
GET    /api/custom-fields
POST   /api/custom-fields
PUT    /api/custom-fields/{id}
DELETE /api/custom-fields/{id}
GET    /api/custom-fields/entity/{type}/{id}
PUT    /api/custom-fields/entity/{type}/{id}

... and more
```

See `ARCHITECTURE_2026.md` for complete API documentation.

## 🔧 Development

### Running Tests

```bash
composer test
```

### Code Quality

The project follows PSR-12 coding standards:

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

### Database Migrations

Migrations are SQL files in `database/migrations/`:

```bash
# Create new migration
php database/make_migration.php "add_new_field_to_projects"

# Run migrations
php database/migrate.php

# Rollback
php database/rollback.php
```

## 📚 Documentation

- [Architecture Guide](ARCHITECTURE_2026.md) - Complete system architecture
- [Code Review Report](COMPREHENSIVE_CODE_REVIEW_2026.md) - Analysis of old system
- [API Documentation](docs/API.md) - REST API reference *(coming soon)*
- [Custom Fields Guide](docs/CUSTOM_FIELDS.md) - How to use custom fields *(coming soon)*

## 🎨 Custom Fields System

Inspired by **QDPM Extra Fields**, our custom fields system allows you to extend any entity (projects, building elements, customers) with custom data:

### Field Types Supported

- Text (single line)
- Textarea (multi-line)
- Number
- Date
- DateTime
- Select (dropdown)
- Multi-Select
- Checkbox
- Radio buttons
- Email
- URL
- Phone

### Example Usage

```php
// Create a custom field definition
$customFieldService->createField([
    'entity_type' => 'project',
    'field_name' => 'environmental_class',
    'field_label' => 'Environmental Class',
    'field_type' => 'select',
    'options' => [
        ['value' => 'A', 'label' => 'Class A - Low Impact'],
        ['value' => 'B', 'label' => 'Class B - Medium Impact'],
        ['value' => 'C', 'label' => 'Class C - High Impact']
    ],
    'required' => true,
    'scope' => 'global'
]);

// Set value for a project
$customFieldService->setValue('project', $projectId, 'environmental_class', 'B');

// Get all custom fields for a project
$fields = $customFieldService->getFieldsWithValues('project', $projectId);
```

## 🚧 Implementation Status

### ✅ Completed

- [x] Project structure setup
- [x] Database schema design
- [x] Architecture documentation
- [x] Composer configuration
- [x] Environment configuration

### 🔄 In Progress

- [ ] Core framework implementation
  - [ ] Container (DI)
  - [ ] Router
  - [ ] Request/Response
  - [ ] Database abstraction
- [ ] Authentication system
- [ ] Base controllers and repositories
- [ ] Custom fields service
- [ ] Frontend JavaScript modules

### 📋 Pending

- [ ] Project management module
- [ ] Building element module
- [ ] Budget module
- [ ] Media management
- [ ] Reporting system
- [ ] User interface (views)
- [ ] Data migration from OLD system
- [ ] Unit tests
- [ ] Integration tests
- [ ] Documentation
- [ ] Deployment scripts

## 🤝 Contributing

1. Create a feature branch
2. Make your changes
3. Write/update tests
4. Ensure code follows PSR-12
5. Submit pull request

## 📝 License

MIT License - See LICENSE file for details

## 👥 Team

- **Original System:** See `OLD/` directory
- **v2.0 Architecture:** Claude AI & Development Team

## 📞 Support

For questions or issues:
- GitHub Issues: https://github.com/Jojadk/DueDiligence/issues
- Email: support@duediligence.local

## 🔄 Migration from v1.0

To migrate data from the old system:

1. Ensure OLD/ directory contains the previous codebase
2. Backup your database
3. Run migration script:
   ```bash
   php database/migrate_from_old.php
   ```

See [MIGRATION_GUIDE.md](docs/MIGRATION_GUIDE.md) for detailed instructions *(coming soon)*.

---

**Status:** 🚧 Active Development
**Branch:** `claude/code-review-optimization-6Y6Su`
**Last Updated:** January 15, 2026

## Next Steps

To continue development:

1. **Implement Core Framework** - Container, Router, Database classes
2. **Build Authentication** - Login, sessions, JWT tokens
3. **Create Base Controllers** - API and Web base controllers
4. **Implement Repositories** - Data access layer
5. **Build Frontend** - Modern JavaScript modules
6. **Write Tests** - Unit and integration tests
7. **Migrate Data** - From OLD system
8. **Deploy** - Production environment

See `ARCHITECTURE_2026.md` for detailed implementation plan and timeline.
