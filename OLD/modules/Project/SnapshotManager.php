<?php
namespace Modules\Project;

use Core\Database;

class SnapshotManager
{
    private $db;
    private $baseUploadDir;
    private $snapshotBaseDir;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->baseUploadDir = __DIR__ . '/../../assets/uploads/';
        $this->snapshotBaseDir = __DIR__ . '/../../assets/snapshots/';
    }

    public function create($projectId, $title, $description, $userId)
    {
        // 1. Fetch All Data
        $data = $this->gatherProjectData($projectId);

        // 2. Prepare Snapshot Directory
        $timestamp = time();
        $snapDirName = $projectId . '/' . $timestamp;
        $fullSnapDir = $this->snapshotBaseDir . $snapDirName . '/';

        if (!is_dir($fullSnapDir)) {
            mkdir($fullSnapDir, 0777, true);
        }

        // 3. Copy Files & Build Manifest
        $fileManifest = []; // original_rel_path => snapshot_filename

        // Project Cover
        if (!empty($data['project']['cover_image'])) {
            $this->copyFile($data['project']['cover_image'], $fullSnapDir, $fileManifest);
        }

        // Element Media
        foreach ($data['media_list'] as $media) {
            if (!empty($media['file_path'])) {
                $this->copyFile($media['file_path'], $fullSnapDir, $fileManifest);
            }
            if (!empty($media['original_file_path'])) {
                $this->copyFile($media['original_file_path'], $fullSnapDir, $fileManifest);
            }
        }

        // 4. Calculate Stats
        $stats = $this->calculateStats($data);
        $totalPrice = $stats['total_capex'];

        // 5. Serialize
        $dump = json_encode([
            'project' => $data['project'],
            'elements' => $data['elements'],
            'budget_items' => $data['budget_items'],
            'element_media' => $data['media_list'],
            'custom_values' => $data['custom_values'],
            'file_manifest' => $fileManifest
        ]);

        // 6. Insert Record
        $this->db->query("INSERT INTO project_snapshots 
            (project_id, created_at, created_by, title, description, total_price, stats_summary, db_dump, files_path)
            VALUES (:pid, NOW(), :uid, :title, :desc, :price, :stats, :dump, :fpath)");

        $this->db->bind(':pid', $projectId);
        $this->db->bind(':uid', $userId);
        $this->db->bind(':title', $title);
        $this->db->bind(':desc', $description);
        $this->db->bind(':price', $totalPrice);
        $this->db->bind(':stats', json_encode($stats));
        $this->db->bind(':dump', $dump);
        $this->db->bind(':fpath', $snapDirName);

        $this->db->execute();
        return $this->db->lastInsertId();
    }

    public function restore($snapshotId, $userId, $targetProjectId = null)
    {
        // 1. Get Snapshot
        $this->db->query("SELECT * FROM project_snapshots WHERE id = :id");
        $this->db->bind(':id', $snapshotId);
        $snap = $this->db->single();
        if (!$snap)
            throw new \Exception("Snapshot not found");

        $projectId = $targetProjectId ?? $snap['project_id'];
        $content = json_decode($snap['db_dump'], true);
        if (!$content)
            throw new \Exception("Invalid snapshot data");

        // 2. Begin Transaction
        $this->db->query("BEGIN");
        try {
            // 3. Wipe Current Data
            // Get all element IDs to delete children first
            $this->db->query("SELECT id FROM building_elements WHERE project_id = :pid");
            $this->db->bind(':pid', $projectId);
            $existingEls = $this->db->resultSet();
            $eids = array_column($existingEls, 'id');

            if (!empty($eids)) {
                $eidStr = implode(',', $eids);
                $this->db->query("DELETE FROM budget_items WHERE element_id IN ($eidStr)");
                $this->db->execute();

                $this->db->query("DELETE FROM element_media WHERE element_id IN ($eidStr)");
                $this->db->execute();

                $this->db->query("DELETE FROM custom_field_values WHERE entity_id IN ($eidStr)");
                $this->db->execute();
            }

            $this->db->query("DELETE FROM building_elements WHERE project_id = :pid");
            $this->db->bind(':pid', $projectId);
            $this->db->execute();

            // 4. Restore Files
            $snapDir = $this->snapshotBaseDir . $snap['files_path'] . '/';
            $manifest = $content['file_manifest'] ?? [];
            foreach ($manifest as $origPath => $snapFilename) {
                // Determine target path
                // origPath is relative to logic (e.g. 'foo.jpg')
                $source = $snapDir . $snapFilename;

                // Clean path just in case
                $cleanRel = str_replace(['assets/uploads/', '\\'], ['', '/'], $origPath);
                $cleanRel = ltrim($cleanRel, '/');
                $target = $this->baseUploadDir . $cleanRel;

                if (file_exists($source)) {
                    $dir = dirname($target);
                    if (!is_dir($dir))
                        mkdir($dir, 0777, true);
                    copy($source, $target);
                }
            }

            // 5. Restore Project Record
            $p = $content['project'];
            $this->db->query("UPDATE projects SET 
                name=:name, cover_image=:cover, status=:status, 
                start_date=:start, bbr_number=:bbr, area_m2=:area, 
                heating_type=:heating, usage_type=:usage, 
                construction_year=:const, renovation_year=:renov, 
                inspection_date=:insp 
                WHERE id=:id");
            $this->db->bind(':name', $p['name']);
            $this->db->bind(':cover', $p['cover_image']);
            $this->db->bind(':status', $p['status']);
            $this->db->bind(':start', $p['start_date']);
            $this->db->bind(':bbr', $p['bbr_number'] ?? null);
            $this->db->bind(':area', $p['area_m2'] ?? null);
            $this->db->bind(':heating', $p['heating_type'] ?? null);
            $this->db->bind(':usage', $p['usage_type'] ?? null);
            $this->db->bind(':const', $p['construction_year'] ?? null);
            $this->db->bind(':renov', $p['renovation_year'] ?? null);
            $this->db->bind(':insp', $p['inspection_date'] ?? null);
            $this->db->bind(':id', $projectId);
            $this->db->execute();

            // 6. Restore Elements (ID Mapping)
            $oldToNew = [];
            $elements = $content['elements'];

            // Sort by hierarchy (no parent first)
            usort($elements, function ($a, $b) {
                return ($a['parent_id'] ?? 0) <=> ($b['parent_id'] ?? 0);
            });

            // Pass 1: Insert all without parents initially
            foreach ($elements as $el) {
                $oldId = $el['id'];
                $sql = "INSERT INTO building_elements 
                    (project_id, parent_id, name, description, quantity, unit, unit_price, price_catalog_id, 
                    condition_rating, risk_level, recommendation, observation, sort_order)
                    VALUES (:pid, NULL, :name, :desc, :qty, :unit, :price, :price_id, 
                    :cond, :risk, :rec, :obs, :sort) RETURNING id";

                $this->db->query($sql);
                $this->db->bind(':pid', $projectId);
                $this->db->bind(':name', $el['name']);
                $this->db->bind(':desc', $el['description']);
                $this->db->bind(':qty', $el['quantity'] ?? 1);
                $this->db->bind(':unit', $el['unit'] ?? 'stk');
                $this->db->bind(':price', $el['unit_price'] ?? 0);
                $this->db->bind(':price_id', $el['price_catalog_id'] ?? null);
                $this->db->bind(':cond', $el['condition_rating'] ?? 'Rimelig');
                $this->db->bind(':risk', $el['risk_level'] ?? 'Ikke relevant');
                $this->db->bind(':rec', $el['recommendation'] ?? '');
                $this->db->bind(':obs', $el['observation'] ?? '');
                $this->db->bind(':sort', $el['sort_order'] ?? 0);

                $res = $this->db->single();
                $newId = $res['id'];
                $oldToNew[$oldId] = $newId;
            }

            // Pass 2: Update Parents
            foreach ($elements as $el) {
                if (!empty($el['parent_id']) && isset($oldToNew[$el['parent_id']])) {
                    $this->db->query("UPDATE building_elements SET parent_id = :pid WHERE id = :id");
                    $this->db->bind(':pid', $oldToNew[$el['parent_id']]);
                    $this->db->bind(':id', $oldToNew[$el['id']]);
                    $this->db->execute();
                }
            }

            // Pass 3: Restore Children (Budget, Media, Custom)
            $budgetItems = $content['budget_items'] ?? [];
            foreach ($budgetItems as $bi) {
                if (isset($oldToNew[$bi['element_id']])) {
                    $newEid = $oldToNew[$bi['element_id']];
                    $this->db->query("INSERT INTO budget_items 
                        (element_id, description, quantity, unit, unit_price, total_calculated, 
                        amount_0_1, amount_1_2, amount_3_5, amount_5_10)
                        VALUES (:eid, :desc, :qty, :unit, :price, :total, :a1, :a2, :a3, :a4)");
                    $this->db->bind(':eid', $newEid);
                    $this->db->bind(':desc', $bi['description']);
                    $this->db->bind(':qty', $bi['quantity']);
                    $this->db->bind(':unit', $bi['unit']);
                    $this->db->bind(':price', $bi['unit_price']);
                    $this->db->bind(':total', $bi['total_calculated']);
                    $this->db->bind(':a1', $bi['amount_0_1'] ?? 0);
                    $this->db->bind(':a2', $bi['amount_1_2'] ?? 0);
                    $this->db->bind(':a3', $bi['amount_3_5'] ?? 0);
                    $this->db->bind(':a4', $bi['amount_5_10'] ?? 0);
                    $this->db->execute();
                }
            }

            $mediaList = $content['element_media'] ?? [];
            foreach ($mediaList as $m) {
                if (isset($oldToNew[$m['element_id']])) {
                    $newEid = $oldToNew[$m['element_id']];
                    $this->db->query("INSERT INTO element_media 
                        (element_id, file_path, file_type, caption, sort_order, original_file_path, annotations)
                        VALUES (:eid, :path, :type, :cap, :sort, :orig, :anno)");
                    $this->db->bind(':eid', $newEid);
                    $this->db->bind(':path', $m['file_path']);
                    $this->db->bind(':type', $m['file_type'] ?? 'image/jpeg');
                    $this->db->bind(':cap', $m['caption'] ?? '');
                    $this->db->bind(':sort', $m['sort_order'] ?? 0);
                    $this->db->bind(':orig', $m['original_file_path'] ?? null);
                    $this->db->bind(':anno', $m['annotations'] ?? null);
                    $this->db->execute();
                }
            }

            $cVals = $content['custom_values'] ?? [];
            foreach ($cVals as $cv) {
                $targetEntId = null;
                if ($cv['entity_id'] == $content['project']['id']) {
                    // Project level
                    $targetEntId = $projectId;
                } elseif (isset($oldToNew[$cv['entity_id']])) {
                    // Element level
                    $targetEntId = $oldToNew[$cv['entity_id']];
                }

                if ($targetEntId) {
                    $this->db->query("INSERT INTO custom_field_values (entity_id, definition_id, value) 
                        VALUES (:eid, :def, :val)");
                    $this->db->bind(':eid', $targetEntId);
                    $this->db->bind(':def', $cv['definition_id']);
                    $this->db->bind(':val', $cv['value']);
                    $this->db->execute();
                }
            }

            $this->db->query("COMMIT");
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }

    private function copyFile($relPath, $destDir, &$manifest)
    {
        $cleanPath = str_replace('assets/uploads/', '', $relPath);
        $cleanPath = ltrim($cleanPath, '/');

        $source = $this->baseUploadDir . $cleanPath;
        if (file_exists($source)) {
            $target = $destDir . $cleanPath;
            $dir = dirname($target);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            copy($source, $target);
            $manifest[$cleanPath] = $cleanPath;
        }
    }

    private function gatherProjectData($pid)
    {
        $data = [];
        $this->db->query("SELECT * FROM projects WHERE id = :pid");
        $this->db->bind(':pid', $pid);
        $data['project'] = $this->db->single();

        $this->db->query("SELECT * FROM building_elements WHERE project_id = :pid");
        $this->db->bind(':pid', $pid);
        $data['elements'] = $this->db->resultSet();

        $eids = array_column($data['elements'], 'id');
        $idList = !empty($eids) ? implode(',', $eids) : '0';

        $this->db->query("SELECT * FROM budget_items WHERE element_id IN ($idList)");
        $data['budget_items'] = $this->db->resultSet();

        $this->db->query("SELECT * FROM element_media WHERE element_id IN ($idList)");
        $data['media_list'] = $this->db->resultSet();

        $this->db->query("SELECT * FROM custom_field_values WHERE entity_id IN ($idList) OR entity_id = :pid");
        $this->db->bind(':pid', $pid);
        $data['custom_values'] = $this->db->resultSet();

        return $data;
    }

    private function calculateStats($data)
    {
        $stats = ['total_capex' => 0, 'risk_counts' => []];
        foreach ($data['budget_items'] as $b) {
            $stats['total_capex'] += ($b['total_calculated'] ?? 0);
        }
        foreach ($data['elements'] as $el) {
            $r = $el['risk_level'] ?? 'Unknown';
            $r = explode(' ', $r)[0];
            if (!isset($stats['risk_counts'][$r]))
                $stats['risk_counts'][$r] = 0;
            $stats['risk_counts'][$r]++;
        }
        return $stats;
    }
}
