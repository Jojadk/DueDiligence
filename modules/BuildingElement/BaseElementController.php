<?php
namespace Modules\BuildingElement;

use Core\Controller;
use Core\Database;
use Core\Auth;

/**
 * Base controller for BuildingElement module
 * Contains shared functionality used by all BE controllers
 */
abstract class BaseElementController extends Controller
{
    /**
     * Get validated element or handle not found
     */
    protected function getElementOrFail($elementId): array
    {
        $this->db->query('SELECT * FROM building_elements WHERE id = :id');
        $this->db->bind(':id', $elementId);
        $element = $this->db->single();

        if (!$element) {
            $this->handleNotFound('Element not found');
        }

        return $element;
    }

    /**
     * Get validated project or handle not found
     */
    protected function getProjectOrFail($projectId): array
    {
        $this->db->query('SELECT * FROM projects WHERE id = :id');
        $this->db->bind(':id', $projectId);
        $project = $this->db->single();

        if (!$project) {
            $this->handleNotFound('Project not found');
        }

        return $project;
    }

    /**
     * Check if user can edit element
     */
    protected function canEdit($elementId): bool
    {
        // For now, any authenticated user can edit
        // TODO: Implement proper permission check
        return Auth::check();
    }

    /**
     * Require POST method or fail
     */
    protected function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid method', 405);
        }
    }

    /**
     * Get JSON input from request body
     */
    protected function getJsonInput(): array
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            return [];
        }
        return $input;
    }
}
