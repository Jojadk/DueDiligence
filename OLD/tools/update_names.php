<?php
// Fix Element Names in Database
// Logic:
// std.1 (Outdoor) -> 1.
// std.2 (Envelope) -> 3. (Swapped)
// std.3 (Interior) -> 2. (Swapped)
// std.4 (Tech) -> 4.

define('ROOT_DIR', __DIR__);
define('CORE_DIR', ROOT_DIR . '/core');
define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');

require_once CORE_DIR . '/Database.php';

use Core\Database;

try {
    $db = Database::getInstance();

    // Fetch all std elements
    $db->query("SELECT id, location, name FROM building_elements WHERE location LIKE 'std.%'");
    $elements = $db->resultSet();

    echo "Updating elements...\n";

    foreach ($elements as $el) {
        $loc = $el['location'];
        $oldName = $el['name'];

        // Skip if already numbered (simple check)
        if (preg_match('/^\d+\./', $oldName)) {
            // strip existing numbering if we want to force re-number?
            // Let's assume we prepend. If already prefixed, we might double up.
            // Better to strip:
            $cleanName = preg_replace('/^\d+(\.\d+)*\s+/', '', $oldName);
        } else {
            $cleanName = $oldName;
        }

        $newPrefix = '';

        // Swap Logic
        if (strpos($loc, 'std.1') === 0) {
            // Outdoor -> 1
            $suffix = substr($loc, 5); // remove 'std.1'
            $newPrefix = '1' . $suffix;
        } elseif (strpos($loc, 'std.2') === 0) {
            // Envelope -> 3
            $suffix = substr($loc, 5);
            $newPrefix = '3' . $suffix;
        } elseif (strpos($loc, 'std.3') === 0) {
            // Interior -> 2
            $suffix = substr($loc, 5);
            $newPrefix = '2' . $suffix;
        } elseif (strpos($loc, 'std.4') === 0) {
            // Tech -> 4
            $suffix = substr($loc, 5);
            $newPrefix = '4' . $suffix;
        } else {
            // Other?
            continue;
        }

        // Construct new name
        // Handling parents like 'std.1' -> suffix is empty -> '1'
        // 'std.1.2' -> suffix is '.2' -> '1.2'

        $newName = $newPrefix . " " . $cleanName;

        // Update DB
        $db->query("UPDATE building_elements SET name = :nm WHERE id = :id");
        $db->bind(':nm', $newName);
        $db->bind(':id', $el['id']);
        $db->execute();

        echo "Updated: $loc | $oldName -> $newName\n";
    }

    echo "Done.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>