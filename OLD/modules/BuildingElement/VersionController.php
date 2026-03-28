<?php
namespace Modules\BuildingElement;

/**
 * Handles version control for building elements
 * - Save versions/snapshots
 * - List versions
 * - Restore versions
 * - Compare versions
 */
class VersionController extends BaseElementController
{
    /**
     * Save a new version of an element
     * POST: element_id, version_title
     */
    public function save()
    {
        $this->requirePost();
        $this->requireCsrf();

        $elementId = $_POST['element_id'] ?? $_POST['id'] ?? null;
        $title = $_POST['version_title'] ?? 'Snapshot ' . date('Y-m-d H:i');

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        // Get current element data
        $element = $this->getElementOrFail($elementId);

        // Get media
        $this->db->query('SELECT * FROM element_media WHERE element_id = :eid ORDER BY sort_order');
        $this->db->bind(':eid', $elementId);
        $media = $this->db->resultSet();

        // Get budget items
        $this->db->query('SELECT * FROM budget_items WHERE element_id = :eid');
        $this->db->bind(':eid', $elementId);
        $budgetItems = $this->db->resultSet();

        // Get custom field values
        $this->db->query('SELECT * FROM custom_field_values WHERE entity_id = :eid');
        $this->db->bind(':eid', $elementId);
        $customValues = $this->db->resultSet();

        // Create version snapshot
        $versionData = [
            'element' => $element,
            'media' => $media,
            'budget_items' => $budgetItems,
            'custom_values' => $customValues
        ];

        $this->db->query('INSERT INTO element_versions 
                         (element_id, version_data, created_by, title) 
                         VALUES (:eid, :data, :uid, :title)');
        $this->db->bind(':eid', $elementId);
        $this->db->bind(':data', json_encode($versionData));
        $this->db->bind(':uid', $this->userId());
        $this->db->bind(':title', $title);
        $this->db->execute();

        $versionId = $this->db->lastInsertId();

        $this->jsonSuccess([
            'version_id' => $versionId
        ], 'Version saved');
    }

    /**
     * List all versions for an element
     * GET: element_id
     */
    public function list()
    {
        $elementId = $_GET['element_id'] ?? $_GET['id'] ?? null;

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        $this->db->query('SELECT ev.*, u.username as created_by_name
                         FROM element_versions ev
                         LEFT JOIN users u ON ev.created_by = u.id
                         WHERE ev.element_id = :eid
                         ORDER BY ev.created_at DESC');
        $this->db->bind(':eid', $elementId);
        $versions = $this->db->resultSet();

        $this->jsonSuccess($versions);
    }

    /**
     * Restore element to a previous version
     * POST: version_id
     */
    public function restore()
    {
        $this->requirePost();
        $this->requireCsrf();

        $versionId = $_POST['version_id'] ?? null;

        if (!$versionId) {
            $this->jsonError('Version ID required', 400);
        }

        // Get version
        $this->db->query('SELECT * FROM element_versions WHERE id = :id');
        $this->db->bind(':id', $versionId);
        $version = $this->db->single();

        if (!$version) {
            $this->jsonError('Version not found', 404);
        }

        $versionData = json_decode($version['version_data'], true);
        $elementId = $version['element_id'];

        // First, save current state as a backup
        $this->saveBackup($elementId);

        $this->db->beginTransaction();
        try {
            // Restore element fields
            if (isset($versionData['element'])) {
                $el = $versionData['element'];
                $this->db->query('UPDATE building_elements SET 
                    name = :name,
                    description = :desc,
                    recommendation = :rec,
                    risk_level = :risk,
                    condition_rating = :cond,
                    updated_at = NOW()
                    WHERE id = :id');
                $this->db->bind(':name', $el['name'] ?? '');
                $this->db->bind(':desc', $el['description'] ?? '');
                $this->db->bind(':rec', $el['recommendation'] ?? '');
                $this->db->bind(':risk', $el['risk_level'] ?? '');
                $this->db->bind(':cond', $el['condition_rating'] ?? '');
                $this->db->bind(':id', $elementId);
                $this->db->execute();
            }

            // Note: We don't restore media/budget by default to avoid data loss
            // That would require more complex handling

            $this->db->commit();

            $this->jsonSuccess([
                'element_id' => $elementId
            ], 'Version restored');

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logError('Version restore failed: ' . $e->getMessage(), 'error', [
                'version_id' => $versionId
            ]);
            $this->jsonError('Failed to restore version', 500);
        }
    }

    /**
     * Get version details
     * GET: version_id
     */
    public function get()
    {
        $versionId = $_GET['version_id'] ?? null;

        if (!$versionId) {
            $this->jsonError('Version ID required', 400);
        }

        $this->db->query('SELECT ev.*, u.username as created_by_name
                         FROM element_versions ev
                         LEFT JOIN users u ON ev.created_by = u.id
                         WHERE ev.id = :id');
        $this->db->bind(':id', $versionId);
        $version = $this->db->single();

        if (!$version) {
            $this->jsonError('Version not found', 404);
        }

        // Parse JSON data
        $version['version_data'] = json_decode($version['version_data'], true);

        $this->jsonSuccess($version);
    }

    /**
     * Save backup before restore
     */
    private function saveBackup($elementId)
    {
        // Get current element data
        $this->db->query('SELECT * FROM building_elements WHERE id = :id');
        $this->db->bind(':id', $elementId);
        $element = $this->db->single();

        if (!$element)
            return;

        $this->db->query('INSERT INTO element_versions 
                         (element_id, version_data, created_by, title) 
                         VALUES (:eid, :data, :uid, :title)');
        $this->db->bind(':eid', $elementId);
        $this->db->bind(':data', json_encode(['element' => $element]));
        $this->db->bind(':uid', $this->userId());
        $this->db->bind(':title', 'Auto-backup before restore');
        $this->db->execute();
    }
}
