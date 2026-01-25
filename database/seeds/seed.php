#!/usr/bin/env php
<?php
/**
 * Database Seeder
 * Populates database with sample data for development and testing
 *
 * Usage: php database/seeds/seed.php [--force]
 *
 * @version 1.0.0
 */

require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/security.php';

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line');
}

// Parse arguments
$options = getopt('', ['force']);
$force = isset($options['force']);

echo "DueDiligence Database Seeder\n";
echo "============================\n\n";

if (!$force) {
    echo "WARNING: This will populate the database with sample data.\n";
    echo "Only run this on development environments!\n\n";
    echo "Run with --force to proceed: php database/seeds/seed.php --force\n";
    exit(0);
}

echo "Starting seed process...\n\n";

/**
 * Seed users
 */
function seedUsers(): void {
    echo "Seeding users...\n";

    $users = [
        [
            'username' => 'admin',
            'email' => 'admin@duediligence.dk',
            'password' => password_hash_secure('admin123'),
            'name' => 'Administrator',
            'role_id' => 1, // Admin role
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'username' => 'manager',
            'email' => 'manager@duediligence.dk',
            'password' => password_hash_secure('manager123'),
            'name' => 'Project Manager',
            'role_id' => 2, // Manager role
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'username' => 'user',
            'email' => 'user@duediligence.dk',
            'password' => password_hash_secure('user123'),
            'name' => 'Regular User',
            'role_id' => 3, // User role
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    foreach ($users as $user) {
        // Check if user exists
        $existing = db_fetch("SELECT id FROM users WHERE username = :username", ['username' => $user['username']]);
        if (!$existing) {
            db_insert('users', $user);
            echo "  ✓ Created user: {$user['username']}\n";
        } else {
            echo "  - User already exists: {$user['username']}\n";
        }
    }

    echo "\n";
}

/**
 * Seed customers
 */
function seedCustomers(): array {
    echo "Seeding customers...\n";

    $customers = [
        [
            'name' => 'Danske Boligselskab',
            'cvr_number' => '12345678',
            'email' => 'kontakt@danskebolig.dk',
            'phone' => '+45 12 34 56 78',
            'address' => 'Strandvejen 123',
            'city' => 'København',
            'zip' => '2100',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Erhvervsejendomme A/S',
            'cvr_number' => '87654321',
            'email' => 'info@erhvervsejendomme.dk',
            'phone' => '+45 98 76 54 32',
            'address' => 'Hovedgaden 45',
            'city' => 'Aarhus',
            'zip' => '8000',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Nordisk Ejendomsfond',
            'cvr_number' => '11223344',
            'email' => 'post@nordiskejendom.dk',
            'phone' => '+45 11 22 33 44',
            'address' => 'Vesterbrogade 78',
            'city' => 'København',
            'zip' => '1620',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    $customerIds = [];

    foreach ($customers as $customer) {
        // Check if customer exists
        $existing = db_fetch("SELECT id FROM customers WHERE cvr_number = :cvr", ['cvr' => $customer['cvr_number']]);
        if (!$existing) {
            $id = db_insert('customers', $customer);
            $customerIds[] = $id;
            echo "  ✓ Created customer: {$customer['name']}\n";
        } else {
            $customerIds[] = $existing['id'];
            echo "  - Customer already exists: {$customer['name']}\n";
        }
    }

    echo "\n";
    return $customerIds;
}

/**
 * Seed projects
 */
function seedProjects(array $customerIds): array {
    echo "Seeding projects...\n";

    $projects = [
        [
            'customer_id' => $customerIds[0] ?? 1,
            'name' => 'Solhøj Boligkompleks',
            'address' => 'Solhøjvej 12',
            'city' => 'København',
            'zip' => '2100',
            'bbr_number' => '1234567',
            'project_type' => 'due_diligence',
            'status' => 'active',
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'customer_id' => $customerIds[1] ?? 1,
            'name' => 'Erhvervspark Nord',
            'address' => 'Industrivej 45',
            'city' => 'Aarhus',
            'zip' => '8000',
            'bbr_number' => '7654321',
            'project_type' => 'condition_report',
            'status' => 'active',
            'start_date' => date('Y-m-d', strtotime('-60 days')),
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'customer_id' => $customerIds[2] ?? 1,
            'name' => 'Historisk Ejendom Centrum',
            'address' => 'Gammel Torv 1',
            'city' => 'København',
            'zip' => '1457',
            'bbr_number' => '9988776',
            'project_type' => 'maintenance_plan',
            'status' => 'active',
            'start_date' => date('Y-m-d', strtotime('-15 days')),
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    $projectIds = [];

    foreach ($projects as $project) {
        // Check if project exists
        $existing = db_fetch("SELECT id FROM projects WHERE name = :name", ['name' => $project['name']]);
        if (!$existing) {
            $id = db_insert('projects', $project);
            $projectIds[] = $id;
            echo "  ✓ Created project: {$project['name']}\n";
        } else {
            $projectIds[] = $existing['id'];
            echo "  - Project already exists: {$project['name']}\n";
        }
    }

    echo "\n";
    return $projectIds;
}

/**
 * Seed buildings
 */
function seedBuildings(array $projectIds): array {
    echo "Seeding buildings...\n";

    $buildings = [
        [
            'project_id' => $projectIds[0] ?? 1,
            'name' => 'Bygning A - Hovedbygning',
            'type' => 'residential',
            'year_built' => 2010,
            'total_area' => 2500.00,
            'floors' => 4,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'project_id' => $projectIds[0] ?? 1,
            'name' => 'Bygning B - Anneks',
            'type' => 'residential',
            'year_built' => 2012,
            'total_area' => 1200.00,
            'floors' => 3,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'project_id' => $projectIds[1] ?? 1,
            'name' => 'Kontorbygning',
            'type' => 'commercial',
            'year_built' => 2005,
            'total_area' => 3500.00,
            'floors' => 5,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'project_id' => $projectIds[2] ?? 1,
            'name' => 'Historisk Hovedbygning',
            'type' => 'heritage',
            'year_built' => 1890,
            'total_area' => 1800.00,
            'floors' => 3,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    $buildingIds = [];

    foreach ($buildings as $building) {
        // Check if building exists
        $existing = db_fetch(
            "SELECT id FROM buildings WHERE project_id = :pid AND name = :name",
            ['pid' => $building['project_id'], 'name' => $building['name']]
        );
        if (!$existing) {
            $id = db_insert('buildings', $building);
            $buildingIds[] = $id;
            echo "  ✓ Created building: {$building['name']}\n";
        } else {
            $buildingIds[] = $existing['id'];
            echo "  - Building already exists: {$building['name']}\n";
        }
    }

    echo "\n";
    return $buildingIds;
}

/**
 * Seed price catalog
 */
function seedPriceCatalog(): void {
    echo "Seeding price catalog...\n";

    $items = [
        ['category' => 'Tag', 'name' => 'Tagpap rulle', 'unit' => 'm²', 'price' => 450.00],
        ['category' => 'Tag', 'name' => 'Tagsten rød', 'unit' => 'm²', 'price' => 650.00],
        ['category' => 'Facade', 'name' => 'Maling facadefarvning', 'unit' => 'm²', 'price' => 125.00],
        ['category' => 'Facade', 'name' => 'Pudsopreparation', 'unit' => 'm²', 'price' => 350.00],
        ['category' => 'Vinduer', 'name' => 'Vindue 2-lags glas 120x150', 'unit' => 'stk', 'price' => 4500.00],
        ['category' => 'Vinduer', 'name' => 'Vindue 3-lags glas 120x150', 'unit' => 'stk', 'price' => 6500.00],
        ['category' => 'El', 'name' => 'Elinstallation standard', 'unit' => 'punkt', 'price' => 1200.00],
        ['category' => 'VVS', 'name' => 'Radiator standard', 'unit' => 'stk', 'price' => 3500.00],
        ['category' => 'Gulv', 'name' => 'Gulvbelægning vinyl', 'unit' => 'm²', 'price' => 350.00],
        ['category' => 'Gulv', 'name' => 'Gulvbelægning parket', 'unit' => 'm²', 'price' => 750.00]
    ];

    foreach ($items as $item) {
        // Check if item exists
        $existing = db_fetch(
            "SELECT id FROM price_catalog WHERE category = :cat AND name = :name",
            ['cat' => $item['category'], 'name' => $item['name']]
        );
        if (!$existing) {
            db_insert('price_catalog', array_merge($item, ['created_at' => date('Y-m-d H:i:s')]));
            echo "  ✓ Created price item: {$item['category']} - {$item['name']}\n";
        } else {
            echo "  - Price item already exists: {$item['category']} - {$item['name']}\n";
        }
    }

    echo "\n";
}

/**
 * Seed report templates
 */
function seedReportTemplates(): void {
    echo "Seeding report templates...\n";

    $templates = [
        [
            'name' => 'Standard Due Diligence Rapport',
            'template_scope' => 'global',
            'template_type' => 'full',
            'content' => file_get_contents(__DIR__ . '/templates/standard_report.tpl'),
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'name' => 'Kort Tilstandsrapport',
            'template_scope' => 'global',
            'template_type' => 'summary',
            'content' => file_get_contents(__DIR__ . '/templates/short_report.tpl'),
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];

    foreach ($templates as $template) {
        // Check if template exists
        $existing = db_fetch(
            "SELECT id FROM report_templates WHERE name = :name",
            ['name' => $template['name']]
        );
        if (!$existing) {
            db_insert('report_templates', $template);
            echo "  ✓ Created template: {$template['name']}\n";
        } else {
            echo "  - Template already exists: {$template['name']}\n";
        }
    }

    echo "\n";
}

// Run seeders
try {
    db()->beginTransaction();

    seedUsers();
    $customerIds = seedCustomers();
    $projectIds = seedProjects($customerIds);
    $buildingIds = seedBuildings($projectIds);
    seedPriceCatalog();
    // seedReportTemplates(); // Uncomment when template files exist

    db()->commit();

    echo "\n✓ Seeding completed successfully!\n\n";
    echo "Login credentials:\n";
    echo "  Admin:   username=admin,   password=admin123\n";
    echo "  Manager: username=manager, password=manager123\n";
    echo "  User:    username=user,    password=user123\n";
    echo "\n";

} catch (Exception $e) {
    db()->rollBack();
    echo "\n✗ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
