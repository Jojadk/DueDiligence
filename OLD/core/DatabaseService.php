<?php
/**
 * Database Service Layer
 * Provides easy access to database views and stored procedures
 * 
 * @package Core
 * @version 2.0
 */

namespace Core;

class DatabaseService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ========================================
    // VIEWS - Read Operations
    // ========================================

    /**
     * Get project overview with statistics
     * Uses v_project_overview view
     * 
     * @param int|null $projectId Optional specific project
     * @return array Project overview data
     */
    public function getProjectOverview($projectId = null)
    {
        if ($projectId) {
            $this->db->query("SELECT * FROM v_project_overview WHERE id = :id");
            $this->db->bind(':id', $projectId);
            return $this->db->single();
        }

        $this->db->query("SELECT * FROM v_project_overview ORDER BY updated_at DESC");
        return $this->db->resultSet();
    }

    /**
     * Get building elements with hierarchy
     * Uses v_building_elements_hierarchy view
     * 
     * @param int $projectId
     * @return array Elements with level and path info
     */
    public function getElementsHierarchy($projectId)
    {
        $this->db->query("SELECT * FROM v_building_elements_hierarchy WHERE project_id = :project_id");
        $this->db->bind(':project_id', $projectId);
        return $this->db->resultSet();
    }

    /**
     * Get budget summary
     * Uses v_budget_summary view
     * 
     * @param int $projectId
     * @return array Budget summary by element
     */
    public function getBudgetSummary($projectId)
    {
        $this->db->query("SELECT * FROM v_budget_summary WHERE project_id = :project_id");
        $this->db->bind(':project_id', $projectId);
        return $this->db->resultSet();
    }

    /**
     * Get media overview
     * Uses v_media_overview view
     * 
     * @param int|null $projectId Optional filter by project
     * @return array Media files with context
     */
    public function getMediaOverview($projectId = null)
    {
        if ($projectId) {
            $this->db->query("SELECT * FROM v_media_overview WHERE project_id = :project_id ORDER BY created_at DESC");
            $this->db->bind(':project_id', $projectId);
        } else {
            $this->db->query("SELECT * FROM v_media_overview ORDER BY created_at DESC");
        }
        return $this->db->resultSet();
    }

    /**
     * Get active locks
     * Uses v_active_locks view
     * 
     * @param int|null $userId Optional filter by user
     * @return array Currently active locks
     */
    public function getActiveLocks($userId = null)
    {
        if ($userId) {
            $this->db->query("SELECT * FROM v_active_locks WHERE user_id = :user_id");
            $this->db->bind(':user_id', $userId);
        } else {
            $this->db->query("SELECT * FROM v_active_locks ORDER BY locked_at DESC");
        }
        return $this->db->resultSet();
    }

    /**
     * Get recent activity across all entities
     * Uses v_recent_activity view
     * 
     * @param int $limit Number of results
     * @return array Recent changes
     */
    public function getRecentActivity($limit = 50)
    {
        $this->db->query("SELECT * FROM v_recent_activity LIMIT :limit");
        $this->db->bind(':limit', $limit);
        return $this->db->resultSet();
    }

    // ========================================
    // STORED PROCEDURES - Complex Operations
    // ========================================

    /**
     * Clone a project with all elements and budget items
     * Uses sp_clone_project stored procedure
     * 
     * @param int $sourceProjectId Source project to clone
     * @param string $newProjectName Name for cloned project
     * @param int|null $userId User performing the clone
     * @return int New project ID
     */
    public function cloneProject($sourceProjectId, $newProjectName, $userId = null)
    {
        $this->db->query("SELECT sp_clone_project(:source_id, :new_name, :user_id) as new_project_id");
        $this->db->bind(':source_id', $sourceProjectId);
        $this->db->bind(':new_name', $newProjectName);
        $this->db->bind(':user_id', $userId);

        $result = $this->db->single();
        return $result['new_project_id'] ?? null;
    }

    /**
     * Calculate project totals
     * Uses sp_calculate_project_totals stored procedure
     * 
     * @param int $projectId
     * @return array Totals (elements, capex, budget, urgency counts)
     */
    public function calculateProjectTotals($projectId)
    {
        $this->db->query("SELECT * FROM sp_calculate_project_totals(:project_id)");
        $this->db->bind(':project_id', $projectId);
        return $this->db->single();
    }

    /**
     * Bulk update building elements
     * Uses sp_bulk_update_elements stored procedure
     * 
     * @param array $elementIds Array of element IDs
     * @param string $field Field to update (urgency, time_horizon, status, is_bcl, element_type)
     * @param mixed $value New value
     * @return int Number of updated rows
     */
    public function bulkUpdateElements($elementIds, $field, $value)
    {
        // Convert PHP array to PostgreSQL array format
        $pgArray = '{' . implode(',', array_map('intval', $elementIds)) . '}';

        $this->db->query("SELECT sp_bulk_update_elements(:ids::int[], :field, :value) as updated_count");
        $this->db->bind(':ids', $pgArray);
        $this->db->bind(':field', $field);
        $this->db->bind(':value', $value);

        $result = $this->db->single();
        return $result['updated_count'] ?? 0;
    }

    /**
     * Clean expired locks
     * Uses sp_clean_expired_locks stored procedure
     * 
     * @return int Number of deleted locks
     */
    public function cleanExpiredLocks()
    {
        $this->db->query("SELECT sp_clean_expired_locks() as deleted_count");
        $result = $this->db->single();
        return $result['deleted_count'] ?? 0;
    }

    /**
     * Get element hierarchy path
     * Uses sp_get_element_path stored procedure
     * 
     * @param int $elementId
     * @return string Full path (e.g., "Parent > Child > Element")
     */
    public function getElementPath($elementId)
    {
        $this->db->query("SELECT sp_get_element_path(:element_id) as path");
        $this->db->bind(':element_id', $elementId);
        $result = $this->db->single();
        return $result['path'] ?? '';
    }

    /**
     * Archive old projects
     * Uses sp_archive_old_projects stored procedure
     * 
     * @param int $daysOld Days since last update to consider old (default: 365)
     * @return int Number of archived projects
     */
    public function archiveOldProjects($daysOld = 365)
    {
        $this->db->query("SELECT sp_archive_old_projects(:days_old) as archived_count");
        $this->db->bind(':days_old', $daysOld);
        $result = $this->db->single();
        return $result['archived_count'] ?? 0;
    }

    /**
     * Recalculate sort order for project elements
     * Uses sp_recalculate_sort_order stored procedure
     * 
     * @param int $projectId
     * @return bool Success
     */
    public function recalculateSortOrder($projectId)
    {
        try {
            $this->db->query("SELECT sp_recalculate_sort_order(:project_id)");
            $this->db->bind(':project_id', $projectId);
            $this->db->execute();
            return true;
        } catch (\Exception $e) {
            error_log("Failed to recalculate sort order: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    /**
     * Execute raw SQL (use with caution)
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return mixed Query result
     */
    public function executeRaw($sql, $params = [])
    {
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        return $this->db->resultSet();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        $this->db->rollback();
    }

    // ========================================
    // HELPER METHODS FOR COMMON QUERIES
    // ========================================

    /**
     * Get project with full context (overview + totals)
     * 
     * @param int $projectId
     * @return array Complete project data
     */
    public function getProjectComplete($projectId)
    {
        $overview = $this->getProjectOverview($projectId);
        $totals = $this->calculateProjectTotals($projectId);
        $elements = $this->getElementsHierarchy($projectId);
        $budget = $this->getBudgetSummary($projectId);

        return [
            'project' => $overview,
            'totals' => $totals,
            'elements' => $elements,
            'budget' => $budget
        ];
    }

    /**
     * Get dashboard statistics
     * 
     * @param int|null $userId Optional filter by user
     * @return array Dashboard data
     */
    public function getDashboardStats($userId = null)
    {
        $projects = $this->getProjectOverview();
        $recentActivity = $this->getRecentActivity(10);
        $activeLocks = $this->getActiveLocks($userId);

        // Calculate totals
        $totalProjects = count($projects);
        $totalElements = array_sum(array_column($projects, 'element_count'));
        $totalCapex = array_sum(array_column($projects, 'total_capex'));

        return [
            'total_projects' => $totalProjects,
            'total_elements' => $totalElements,
            'total_capex' => $totalCapex,
            'recent_projects' => array_slice($projects, 0, 5),
            'recent_activity' => $recentActivity,
            'active_locks' => $activeLocks
        ];
    }
}
