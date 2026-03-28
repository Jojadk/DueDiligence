<?php
namespace Modules\PriceCatalog;

use Core\Controller;
use Core\Database;
use Core\Auth;

class PriceController extends Controller
{

    public function __construct()
    {
        if (!Auth::check() || !Auth::hasPermission('admin_access')) {
            $this->redirect('?module=Auth');
        }
    }

    public function index()
    {
        $db = Database::getInstance();
        $db->query("SELECT * FROM price_catalogs ORDER BY item_code ASC");
        $items = $db->resultSet();
        $this->view('PriceCatalog/index', ['items' => $items]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = $_POST['item_code'];
            $desc = $_POST['description'];
            $unit = $_POST['unit'];
            $price = $_POST['unit_price'];

            $db = Database::getInstance();
            $db->query("INSERT INTO price_catalogs (item_code, description, unit, unit_price) VALUES (:code, :desc, :unit, :price)");
            $db->bind(':code', $code);
            $db->bind(':desc', $desc);
            $db->bind(':unit', $unit);
            $db->bind(':price', $price);
            $db->execute();

            $this->redirect('?module=Price&action=index');
        }
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            // Validate CSRF
            if (!\Core\Security::validateCSRFToken()) {
                $this->json(['status' => 'error', 'message' => 'Invalid CSRF token']);
                return;
            }

            $id = $_POST['id'];
            $db = Database::getInstance();
            $db->query("DELETE FROM price_catalogs WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();

            if (isset($_POST['ajax'])) {
                $this->json(['status' => 'success', 'message' => 'Item deleted']);
                return;
            }
        }
        $this->redirect('?module=Price&action=index');
    }
}
