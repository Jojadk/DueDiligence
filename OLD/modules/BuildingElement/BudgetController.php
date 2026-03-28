<?php
namespace Modules\BuildingElement;

/**
 * Handles all budget operations for building elements
 * - Get budget items
 * - Save budget items
 * - Search price catalog
 */
class BudgetController extends BaseElementController
{
    /**
     * Get budget items for an element
     * GET: element_id
     */
    public function get()
    {
        $elementId = $_GET['element_id'] ?? $_GET['id'] ?? null;

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        $this->db->query('SELECT bi.*, pc.description as catalog_name, pc.unit as catalog_unit
                         FROM budget_items bi
                         LEFT JOIN price_catalogs pc ON bi.price_catalog_id = pc.id
                         WHERE bi.element_id = :eid
                         ORDER BY bi.id ASC');
        $this->db->bind(':eid', $elementId);
        $items = $this->db->resultSet();

        // Calculate totals
        $totals = [
            'amount_0_1' => 0,
            'amount_1_2' => 0,
            'amount_3_5' => 0,
            'amount_6_10' => 0,
            'total' => 0
        ];

        foreach ($items as $item) {
            $totals['amount_0_1'] += $item['amount_0_1'] ?? 0;
            $totals['amount_1_2'] += $item['amount_1_2'] ?? 0;
            $totals['amount_3_5'] += $item['amount_3_5'] ?? 0;
            $totals['amount_6_10'] += $item['amount_5_10'] ?? 0;
            $totals['total'] += $item['total_calculated'] ?? 0;
        }

        $this->jsonSuccess([
            'items' => $items,
            'totals' => $totals
        ]);
    }

    /**
     * Save budget items for an element
     * POST JSON: { element_id, items: [...] }
     */
    public function save()
    {
        $this->requirePost();
        $this->requireCsrf();

        $input = $this->getJsonInput();

        $elementId = $input['element_id'] ?? $input['id'] ?? null;
        $items = $input['items'] ?? [];

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        // Verify element exists
        $this->getElementOrFail($elementId);

        $this->db->beginTransaction();
        try {
            // Delete existing items
            $this->db->query('DELETE FROM budget_items WHERE element_id = :eid');
            $this->db->bind(':eid', $elementId);
            $this->db->execute();

            // Insert new items
            foreach ($items as $item) {
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $quantity = floatval($item['quantity'] ?? 0);
                $total = $unitPrice * $quantity;

                // Get amount distribution directly from input
                $amount01 = floatval($item['amount_0_1'] ?? 0);
                $amount12 = floatval($item['amount_1_2'] ?? 0);
                $amount35 = floatval($item['amount_3_5'] ?? 0);
                $amount510 = floatval($item['amount_5_10'] ?? 0);

                $this->db->query('INSERT INTO budget_items 
                    (element_id, description, quantity, unit, unit_price, 
                     amount_0_1, amount_1_2, amount_3_5, amount_5_10, 
                     total_calculated, price_catalog_id) 
                    VALUES 
                    (:eid, :desc, :qty, :unit, :price, 
                     :a01, :a12, :a35, :a510, 
                     :total, :pcid)');

                $this->db->bind(':eid', $elementId);
                $this->db->bind(':desc', $item['description'] ?? '');
                $this->db->bind(':qty', $quantity);
                $this->db->bind(':unit', $item['unit'] ?? 'stk');
                $this->db->bind(':price', $unitPrice);
                $this->db->bind(':a01', $amount01);
                $this->db->bind(':a12', $amount12);
                $this->db->bind(':a35', $amount35);
                $this->db->bind(':a510', $amount510);
                $this->db->bind(':total', $total);
                $this->db->bind(':pcid', $item['price_catalog_id'] ?? null);
                $this->db->execute();
            }

            $this->db->commit();
            $this->jsonSuccess(null, 'Budget saved');

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logError('Budget save failed: ' . $e->getMessage(), 'error', [
                'element_id' => $elementId
            ]);
            $this->jsonError('Failed to save budget', 500);
        }
    }

    /**
     * Search price catalog
     * GET: q (search query)
     */
    public function searchCatalog()
    {
        $query = $_GET['q'] ?? '';

        if (strlen($query) < 2) {
            $this->jsonSuccess([]);
        }

        $this->db->query("SELECT *, description as name FROM price_catalogs 
                         WHERE description ILIKE :q OR item_code ILIKE :q 
                         ORDER BY description 
                         LIMIT 20");
        $this->db->bind(':q', '%' . $query . '%');
        $results = $this->db->resultSet();

        $this->jsonSuccess($results);
    }

    /**
     * Get single price catalog item
     * GET: id
     */
    public function getCatalogItem()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            $this->jsonError('Catalog ID required', 400);
        }

        $this->db->query('SELECT * FROM price_catalogs WHERE id = :id');
        $this->db->bind(':id', $id);
        $item = $this->db->single();

        if (!$item) {
            $this->jsonError('Item not found', 404);
        }

        $this->jsonSuccess($item);
    }
}
