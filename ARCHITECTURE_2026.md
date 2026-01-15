# DueDiligence v2.0 - Modern Architecture
**Date:** January 15, 2026
**Status:** Design Phase

---

## Executive Summary

Complete rewrite of DueDiligence project management system with focus on:
- Modern PHP 8.1+ architecture
- Clean separation of concerns
- RESTful API design
- Extensibility through custom fields (inspired by QDPM)
- Performance and scalability
- Security best practices
- Maintainability and testability

---

## System Requirements Analysis

### Core Features (From OLD system)

1. **Project Management**
   - Create, update, delete projects
   - Project metadata (BBR number, area, construction year, etc.)
   - Project status tracking
   - Project team members with roles
   - Project snapshots/versioning

2. **Building Element Management**
   - Hierarchical structure (parent/child relationships)
   - Drag and drop reordering
   - Element types and categories
   - Budget tracking per element
   - Media attachments with annotations
   - Field locking for concurrent editing
   - Auto-save functionality

3. **Customer Management**
   - Customer profiles
   - Link customers to projects
   - Customer contact information

4. **Custom Fields System** (Like QDPM Extra Fields)
   - Dynamic field definitions
   - Multiple field types (text, number, date, select, multi-select)
   - Entity-specific fields (project, element, customer)
   - Global vs project-scoped fields
   - Field validation rules

5. **Budget Management**
   - Budget items per building element
   - Multiple time horizons (0-1, 1-2, 3-5, 5-10 years)
   - Price catalog integration
   - Budget calculations and summaries

6. **Media Management**
   - Image uploads
   - Canvas annotations on images
   - Image captions
   - Multiple images per element
   - Image ordering

7. **Reporting**
   - Excel exports
   - PDF reports
   - Custom report templates
   - Report configuration per project

8. **User Management**
   - User authentication
   - Role-based access control (RBAC)
   - User profiles
   - Permission system
   - Activity logging

9. **System Features**
   - Internationalization (i18n)
   - Rate limiting
   - CSRF protection
   - Input validation
   - Error logging
   - Security audit logs

---

## New Architecture Design

### Technology Stack

**Backend:**
- PHP 8.1+
- PostgreSQL 14+
- Composer for dependency management

**Frontend:**
- Modern JavaScript (ES6+)
- No jQuery
- Fetch API for AJAX
- CSS Grid & Flexbox

**Development:**
- PSR-4 autoloading
- PSR-12 coding standards
- PHPUnit for testing
- Docker for development environment

---

### Directory Structure

```
DueDiligence/
├── app/
│   ├── Config/
│   │   ├── Database.php
│   │   ├── App.php
│   │   └── Security.php
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── ProjectController.php
│   │   │   ├── BuildingElementController.php
│   │   │   ├── CustomFieldController.php
│   │   │   └── ...
│   │   └── Web/
│   │       ├── DashboardController.php
│   │       ├── ProjectController.php
│   │       └── ...
│   ├── Models/
│   │   ├── User.php
│   │   ├── Project.php
│   │   ├── BuildingElement.php
│   │   ├── CustomField.php
│   │   └── ...
│   ├── Repositories/
│   │   ├── Interfaces/
│   │   │   ├── RepositoryInterface.php
│   │   │   ├── ProjectRepositoryInterface.php
│   │   │   └── ...
│   │   ├── BaseRepository.php
│   │   ├── ProjectRepository.php
│   │   ├── BuildingElementRepository.php
│   │   └── ...
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── ProjectService.php
│   │   ├── CustomFieldService.php
│   │   ├── BudgetCalculationService.php
│   │   ├── ValidationService.php
│   │   └── ...
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   └── PermissionMiddleware.php
│   ├── Core/
│   │   ├── Application.php
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Container.php (DI Container)
│   │   ├── Database.php
│   │   └── View.php
│   ├── Exceptions/
│   │   ├── NotFoundException.php
│   │   ├── UnauthorizedException.php
│   │   ├── ValidationException.php
│   │   └── ...
│   └── Helpers/
│       ├── functions.php
│       └── ...
├── config/
│   ├── app.php
│   ├── database.php
│   ├── auth.php
│   └── routes.php
├── database/
│   ├── migrations/
│   │   ├── 001_create_users_table.sql
│   │   ├── 002_create_projects_table.sql
│   │   ├── 003_create_building_elements_table.sql
│   │   ├── 004_create_custom_fields_table.sql
│   │   └── ...
│   ├── seeds/
│   │   ├── UsersSeeder.php
│   │   ├── RolesSeeder.php
│   │   └── ...
│   └── schema.sql (generated)
├── public/
│   ├── index.php (entry point)
│   ├── assets/
│   │   ├── css/
│   │   │   ├── app.css
│   │   │   └── ...
│   │   ├── js/
│   │   │   ├── app.js
│   │   │   ├── api.js
│   │   │   ├── components/
│   │   │   │   ├── Modal.js
│   │   │   │   ├── TreeView.js
│   │   │   │   └── CustomField.js
│   │   │   └── modules/
│   │   │       ├── project.js
│   │   │       ├── buildingElement.js
│   │   │       └── ...
│   │   └── uploads/ (user uploads)
│   └── favicon.ico
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── app.php
│       │   ├── header.php
│       │   └── footer.php
│       ├── auth/
│       │   ├── login.php
│       │   └── register.php
│       ├── projects/
│       │   ├── index.php
│       │   ├── show.php
│       │   └── form.php
│       └── ...
├── storage/
│   ├── logs/
│   │   ├── app.log
│   │   ├── error.log
│   │   └── security.log
│   ├── cache/
│   └── sessions/
├── tests/
│   ├── Unit/
│   │   ├── Services/
│   │   │   ├── ProjectServiceTest.php
│   │   │   └── ...
│   │   └── Repositories/
│   │       └── ...
│   ├── Integration/
│   │   ├── Controllers/
│   │   │   └── ...
│   │   └── Database/
│   │       └── ...
│   └── Feature/
│       └── ...
├── .env.example
├── .env
├── .gitignore
├── composer.json
├── composer.lock
├── README.md
└── OLD/ (old codebase backup)
```

---

## Core Components Design

### 1. Dependency Injection Container

```php
<?php
namespace App\Core;

class Container {
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bind($abstract, $concrete);
        // Mark as singleton
        $this->instances[$abstract] = null;
    }

    public function make(string $abstract): mixed
    {
        // Resolve from container
        if (isset($this->instances[$abstract]) && $this->instances[$abstract] !== null) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            // Try to auto-resolve
            return $this->resolve($abstract);
        }

        $concrete = $this->bindings[$abstract];

        if (is_callable($concrete)) {
            $object = $concrete($this);
        } else {
            $object = $this->resolve($concrete);
        }

        // Store singleton instance
        if (array_key_exists($abstract, $this->instances)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function resolve(string $class): object
    {
        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$class} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve {$parameter->getName()}");
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
```

### 2. Router System

```php
<?php
namespace App\Core;

class Router {
    private array $routes = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $uri, array|callable $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, array|callable $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, array|callable $action): void
    {
        $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, array|callable $action): void
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    private function addRoute(string $method, string $uri, array|callable $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertToRegex($route['uri']);

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match

                return $this->callAction($route['action'], $matches);
            }
        }

        throw new NotFoundException("Route not found: {$method} {$uri}");
    }

    private function convertToRegex(string $uri): string
    {
        // Convert {id} to named regex groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    private function callAction(array|callable $action, array $params): Response
    {
        if (is_callable($action)) {
            return $action($params);
        }

        [$controllerClass, $method] = $action;

        $controller = $this->container->make($controllerClass);

        return $controller->$method(...array_values($params));
    }
}
```

### 3. Base Repository

```php
<?php
namespace App\Repositories;

use App\Core\Database;

abstract class BaseRepository {
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function all(array $conditions = [], array $orderBy = [], int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $where[] = "{$col} = :{$col}";
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $col => $dir) {
                $orderClauses[] = "{$col} {$dir}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "{$col} = :{$col}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) .
               " WHERE {$this->primaryKey} = :id";

        $data['id'] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
```

### 4. Custom Fields System (QDPM-inspired)

```php
<?php
namespace App\Services;

use App\Repositories\CustomFieldRepository;

class CustomFieldService {
    public function __construct(
        private CustomFieldRepository $customFieldRepo
    ) {}

    /**
     * Get all custom field definitions for an entity type
     */
    public function getFieldsForEntity(string $entityType, ?int $projectId = null): array
    {
        $conditions = ['entity_type' => $entityType, 'active' => true];

        $fields = $this->customFieldRepo->all($conditions, ['sort_order' => 'ASC']);

        // Filter by scope (global or project-specific)
        if ($projectId) {
            $fields = array_filter($fields, function($field) use ($projectId) {
                return $field['scope'] === 'global' ||
                       ($field['scope'] === 'project' && $field['project_id'] == $projectId);
            });
        }

        return $fields;
    }

    /**
     * Get custom field values for an entity instance
     */
    public function getValuesForEntity(int $entityId, string $entityType): array
    {
        // Implementation
    }

    /**
     * Save custom field values for an entity
     */
    public function saveValues(int $entityId, string $entityType, array $values): void
    {
        // Validate against field definitions
        // Save to custom_field_values table
    }

    /**
     * Validate field value against field definition
     */
    private function validateFieldValue(array $field, mixed $value): bool
    {
        switch ($field['field_type']) {
            case 'text':
                return is_string($value) && strlen($value) <= ($field['max_length'] ?? 255);
            case 'number':
                return is_numeric($value);
            case 'date':
                return strtotime($value) !== false;
            case 'select':
                $options = json_decode($field['options'], true);
                return in_array($value, array_column($options, 'value'));
            // ... more types
        }

        return true;
    }
}
```

---

## Database Schema Design

### Core Tables

```sql
-- Users and Authentication
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    role_id INT REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE permissions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL
);

CREATE TABLE role_permissions (
    role_id INT REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- Projects
CREATE TABLE customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    customer_id INT REFERENCES customers(id),
    status VARCHAR(50) DEFAULT 'planning',
    bbr_number VARCHAR(50),
    area_m2 NUMERIC(10,2),
    construction_year INT,
    renovation_year INT,
    heating_type VARCHAR(100),
    usage_type VARCHAR(100),
    inspection_date DATE,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_projects_customer ON projects(customer_id);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_deleted ON projects(deleted_at);

-- Building Elements (Hierarchical)
CREATE TABLE building_elements (
    id SERIAL PRIMARY KEY,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    parent_id INT REFERENCES building_elements(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    element_type VARCHAR(100),
    description TEXT,
    location VARCHAR(255),
    urgency VARCHAR(50),
    time_horizon VARCHAR(50),
    capex NUMERIC(12,2),
    replacement_value NUMERIC(12,2),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_elements_project ON building_elements(project_id);
CREATE INDEX idx_elements_parent ON building_elements(parent_id);
CREATE INDEX idx_elements_sort ON building_elements(project_id, sort_order);

-- Custom Fields (QDPM Extra Fields inspired)
CREATE TABLE custom_field_definitions (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- 'project', 'building_element', 'customer'
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    field_type VARCHAR(50) NOT NULL, -- 'text', 'number', 'date', 'select', 'multi_select', 'checkbox'
    options JSONB, -- For select/multi-select: [{"value":"val1","label":"Label 1"}]
    default_value TEXT,
    required BOOLEAN DEFAULT false,
    max_length INT,
    min_value NUMERIC,
    max_value NUMERIC,
    validation_regex VARCHAR(255),
    scope VARCHAR(20) DEFAULT 'global', -- 'global' or 'project'
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    sort_order INT DEFAULT 0,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(entity_type, field_name, scope, project_id)
);

CREATE INDEX idx_custom_fields_entity ON custom_field_definitions(entity_type, active);
CREATE INDEX idx_custom_fields_project ON custom_field_definitions(project_id);

CREATE TABLE custom_field_values (
    id SERIAL PRIMARY KEY,
    definition_id INT REFERENCES custom_field_definitions(id) ON DELETE CASCADE,
    entity_id INT NOT NULL,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(definition_id, entity_id)
);

CREATE INDEX idx_custom_values_entity ON custom_field_values(entity_id);
CREATE INDEX idx_custom_values_definition ON custom_field_values(definition_id);

-- Budget Management
CREATE TABLE budget_items (
    id SERIAL PRIMARY KEY,
    element_id INT REFERENCES building_elements(id) ON DELETE CASCADE,
    description TEXT,
    quantity NUMERIC(10,2),
    unit VARCHAR(50),
    unit_price NUMERIC(10,2),
    time_horizon VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_budget_element ON budget_items(element_id);

-- Media Management
CREATE TABLE element_media (
    id SERIAL PRIMARY KEY,
    element_id INT REFERENCES building_elements(id) ON DELETE CASCADE,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    caption TEXT,
    annotation_data JSONB, -- Canvas annotations
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_media_element ON element_media(element_id, sort_order);

-- Activity Log
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id),
    entity_type VARCHAR(50),
    entity_id INT,
    action VARCHAR(50),
    changes JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_activity_user ON activity_logs(user_id, created_at DESC);
CREATE INDEX idx_activity_entity ON activity_logs(entity_type, entity_id, created_at DESC);
```

---

## API Design

### RESTful API Endpoints

```
Authentication:
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/refresh
GET    /api/auth/user

Projects:
GET    /api/projects              List all projects
GET    /api/projects/{id}          Get project details
POST   /api/projects              Create project
PUT    /api/projects/{id}          Update project
DELETE /api/projects/{id}          Delete project
GET    /api/projects/{id}/elements Get project's building elements tree
GET    /api/projects/{id}/budget   Get project budget summary

Building Elements:
GET    /api/elements              List elements
GET    /api/elements/{id}          Get element details
POST   /api/elements              Create element
PUT    /api/elements/{id}          Update element
DELETE /api/elements/{id}          Delete element
POST   /api/elements/{id}/reorder  Update sort order
GET    /api/elements/{id}/media    Get element media
POST   /api/elements/{id}/media    Upload media

Custom Fields:
GET    /api/custom-fields                      List field definitions
POST   /api/custom-fields                      Create field definition
PUT    /api/custom-fields/{id}                 Update field definition
DELETE /api/custom-fields/{id}                 Delete field definition
GET    /api/custom-fields/entity/{type}/{id}   Get entity's custom field values
PUT    /api/custom-fields/entity/{type}/{id}   Save entity's custom field values

Budget:
GET    /api/budget/{elementId}    Get element budget
PUT    /api/budget/{elementId}    Update element budget

Customers:
GET    /api/customers             List customers
POST   /api/customers             Create customer
PUT    /api/customers/{id}        Update customer
DELETE /api/customers/{id}        Delete customer
```

---

## Security Implementation

### 1. Authentication
- JWT tokens for API
- Session-based for web
- Password hashing with bcrypt (cost factor 12)
- Password reset with secure tokens

### 2. Authorization
- RBAC (Role-Based Access Control)
- Permission middleware on routes
- Ownership checks (user can only edit their own resources)

### 3. Input Validation
- Validation service with rules
- Sanitization before storage
- Type casting
- SQL injection prevention (prepared statements)

### 4. CSRF Protection
- Token generation and validation
- Double-submit cookie pattern
- SameSite cookie attribute

### 5. Rate Limiting
- Per-user and per-IP limits
- Configurable limits per endpoint
- Redis-based counters (optional)

### 6. XSS Prevention
- Output escaping in views
- Content Security Policy headers
- Sanitize HTML input

---

## Performance Considerations

### 1. Database Optimization
- Proper indexing (shown in schema)
- Query optimization
- Connection pooling
- Prepared statement caching

### 2. Caching Strategy
- Result caching for expensive queries
- View caching
- Redis/Memcached support

### 3. Asset Optimization
- JavaScript bundling and minification
- CSS minification
- Image optimization
- CDN support

### 4. Lazy Loading
- Paginated lists
- On-demand tree node loading
- Infinite scroll

---

## Testing Strategy

### 1. Unit Tests
- Service layer tests
- Repository tests
- Helper function tests
- 80% code coverage target

### 2. Integration Tests
- API endpoint tests
- Database interaction tests
- Authentication flow tests

### 3. Feature Tests
- Complete user workflows
- Project creation to report generation
- Custom fields system

---

## Deployment Strategy

### 1. Environment Configuration
- .env file for secrets
- Environment-specific configs
- Feature flags

### 2. Database Migrations
- Version-controlled migrations
- Rollback support
- Seed data for development

### 3. CI/CD Pipeline
- Automated testing
- Code quality checks (PHPStan, Psalm)
- Automated deployment

### 4. Monitoring
- Error tracking (Sentry)
- Performance monitoring
- Log aggregation

---

## Migration from OLD System

### Data Migration Steps

1. **User Data**
   - Export users from old system
   - Hash passwords with new algorithm
   - Import into new users table

2. **Projects**
   - Map old project fields to new schema
   - Preserve project IDs if possible
   - Migrate custom fields to new system

3. **Building Elements**
   - Preserve hierarchical structure
   - Migrate element data
   - Update foreign keys

4. **Budget Data**
   - Migrate budget items
   - Recalculate summaries

5. **Media Files**
   - Copy files to new upload directory
   - Update file paths in database
   - Preserve annotations

### Migration Script

```php
<?php
// migrate.php
// This will be a comprehensive migration script
// to move data from OLD system to new structure

require 'vendor/autoload.php';

$oldDb = new PDO(/* old database connection */);
$newDb = new PDO(/* new database connection */);

// Migration logic here...
```

---

## Timeline Estimate

### Phase 1: Foundation (Week 1)
- Setup project structure
- Implement core framework components
- Database setup and migrations
- Basic authentication

### Phase 2: Core Features (Week 2-3)
- Project management
- Building element CRUD
- Custom fields system
- Budget management

### Phase 3: Advanced Features (Week 4)
- Media management
- Reporting
- Advanced permissions
- Activity logging

### Phase 4: Polish & Testing (Week 5)
- UI/UX refinements
- Performance optimization
- Testing
- Bug fixes

### Phase 5: Migration & Deployment (Week 6)
- Data migration from OLD system
- Documentation
- Deployment
- Training

---

## Next Steps

1. **Get approval on architecture**
2. **Setup development environment**
3. **Initialize composer project**
4. **Create database migrations**
5. **Implement core framework**
6. **Start building features iteratively**

---

**Document Version:** 1.0
**Last Updated:** January 15, 2026
**Status:** Awaiting Approval
