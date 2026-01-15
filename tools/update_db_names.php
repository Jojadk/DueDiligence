<?php
// Define Constants
define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');

require_once __DIR__ . '/core/Database.php';

use Core\Database;

$translations = [
    'std.1' => 'Udendørsarealer (Terræn)',
    'std.1.1' => 'Belægninger (Vej, sti, fortov, pladser)',
    'std.1.2' => 'Grønne områder (Beplantning, græsarealer)',
    'std.1.3' => 'Hegn, porte og støttemure',
    'std.1.4' => 'Udvendig belysning',
    'std.1.5' => 'Afvanding af terræn (Brønde, render)',
    'std.1.6' => 'Skilte og inventar (Bænke, cykelstativer)',

    'std.2' => 'Bygningsskal (Klimaskærm)',
    'std.2.1' => 'Fundamenter og terrændæk',
    'std.2.2' => 'Facader (Murværk, beton, lette facader)',
    'std.2.3' => 'Vinduer og yderdøre',
    'std.2.4' => 'Tage (Tagdækning, ovenlys, tagbrønde)',
    'std.2.5' => 'Altaner og udvendige trapper',
    'std.2.6' => 'Porte og ramper (f.eks. til vareindlevering)',

    'std.3' => 'Indvendige bygningsdele',
    'std.3.1' => 'Indvendige vægge og skillevægge',
    'std.3.2' => 'Indvendige døre og partier',
    'std.3.3' => 'Gulve og gulvbelægninger',
    'std.3.4' => 'Lofter (Systemlofter, faste lofter)',
    'std.3.5' => 'Indvendige trapper',
    'std.3.6' => 'Inventar (Køkkener, toiletter, faste skabe)',

    'std.4' => 'Tekniske installationer',
    'std.4.1' => 'Vand (Brugsvand, sanitet)',
    'std.4.2' => 'Afløb (Spildevand, regnvand indvendigt)',
    'std.4.3' => 'Varme (Radiatorer, gulvvarme, fjernvarmeunits)',
    'std.4.4' => 'Køling (Køleflader, serverrumskøling)',
    'std.4.5' => 'Ventilation (Aggregater, kanaler, styring)',
    'std.4.6' => 'El-grundinstallationer (Tavler, føringsveje)',
    'std.4.7' => 'Belysning (Indvendig)',
    'std.4.8' => 'Elevatorer og løfteplatforme',
    'std.4.9' => 'Brandsikring (ABA, varsling, sprinkler, røgudluftning)',
    'std.4.10' => 'CTS / Bygningsautomation'
];

try {
    $db = Database::getInstance();
    echo "Starting update...\n";

    foreach ($translations as $key => $text) {
        // Generate new name with number prefix
        // std.1 -> 1. Text
        // std.1.1 -> 1.1 Text
        $prefix = str_replace('std.', '', $key);
        $newName = $prefix . ' ' . $text;

        // Ensure we update only those that still have the 'std.' name
        // Or forcefully update even if they changed?
        // User provided logic implies he wants these keys mapped to these Texts.
        // We will look for 'name' = $key.

        $sql = "UPDATE building_elements SET name = :new_name WHERE name = :old_key";
        $db->query($sql);
        $db->bind(':new_name', $newName);
        $db->bind(':old_key', $key);
        $db->execute();

        // Also update if location matches parent key?
        // No, location is text based.

        if ($db->rowCount() > 0) {
            echo "Updated '$key' to '$newName' ({$db->rowCount()} rows)\n";
        }
    }

    // Also, user mentioned: "std.1.2 = 2.1 Grønne områder" earlier.
    // But the list provided here is consistent: std.1 -> 1, std.2 -> 2.
    // I will stick to the list provided in THIS prompt.
    // std.1 -> 1. Udendørsarealer

    echo "Update complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>