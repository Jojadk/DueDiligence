<?php
/**
 * Customer Module Template
 */
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kunder - DueDiligence v2.0</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="page-header">
            <div class="header-left">
                <h1>
                    <?= echo_icon('user', 32) ?>
                    Kunder
                </h1>
                <p class="subtitle">Administrer dine kunder</p>
            </div>
            <div class="header-right">
                <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                    <?= echo_icon('plus', 20) ?>
                    Ny Kunde
                </button>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?= echo_icon('check', 20) ?>
                <?= esc_html($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <?= echo_icon('alert', 20) ?>
                <?= esc_html($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="/" class="search-form">
                <input type="hidden" name="module" value="customer">
                <div class="search-input-group">
                    <?= echo_icon('search', 20) ?>
                    <input
                        type="text"
                        name="search"
                        placeholder="Søg efter navn, CVR, kontaktperson eller email..."
                        value="<?= esc_attr($searchTerm) ?>"
                        class="search-input"
                    >
                    <?php if ($searchTerm): ?>
                        <button type="button" onclick="clearSearch()" class="btn-clear">
                            <?= echo_icon('x', 16) ?>
                        </button>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-secondary">Søg</button>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-bar">
            <div class="stat">
                <span class="stat-label">Total antal kunder:</span>
                <span class="stat-value"><?= number_format($totalCustomers, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Customer Table -->
        <?php if (empty($customers)): ?>
            <div class="empty-state">
                <?= echo_icon('user', 48) ?>
                <h3>Ingen kunder fundet</h3>
                <p>
                    <?php if ($searchTerm): ?>
                        Prøv en anden søgning
                    <?php else: ?>
                        Kom i gang ved at oprette din første kunde
                    <?php endif; ?>
                </p>
                <?php if (!$searchTerm): ?>
                    <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                        <?= echo_icon('plus', 20) ?>
                        Opret Kunde
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Firmanavn</th>
                            <th>CVR Nummer</th>
                            <th>Kontaktperson</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Projekter</th>
                            <th class="actions-column">Handlinger</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr data-id="<?= $customer['id'] ?>">
                                <td class="font-semibold"><?= esc_html($customer['name']) ?></td>
                                <td><?= esc_html($customer['cvr_number'] ?: '-') ?></td>
                                <td><?= esc_html($customer['contact_person'] ?: '-') ?></td>
                                <td><?= esc_html($customer['email'] ?: '-') ?></td>
                                <td><?= esc_html($customer['phone'] ?: '-') ?></td>
                                <td>
                                    <?php if ($customer['project_count'] > 0): ?>
                                        <a href="/?module=project&customer_id=<?= $customer['id'] ?>" class="badge badge-info">
                                            <?= $customer['project_count'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-column">
                                    <div class="action-buttons">
                                        <button
                                            type="button"
                                            class="btn-icon"
                                            onclick="openEditModal(<?= $customer['id'] ?>)"
                                            title="Rediger"
                                        >
                                            <?= echo_icon('edit', 18) ?>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-icon btn-icon-danger"
                                            onclick="confirmDelete(<?= $customer['id'] ?>, '<?= esc_js($customer['name']) ?>')"
                                            title="Slet"
                                        >
                                            <?= echo_icon('trash', 18) ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="/?module=customer&page=<?= $page - 1 ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>" class="btn btn-secondary">
                            <?= echo_icon('chevron-left', 16) ?>
                            Forrige
                        </a>
                    <?php endif; ?>

                    <span class="pagination-info">
                        Side <?= $page ?> af <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="/?module=customer&page=<?= $page + 1 ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>" class="btn btn-secondary">
                            Næste
                            <?= echo_icon('chevron-right', 16) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Ny Kunde</h2>
                <button type="button" class="btn-close" onclick="closeModal()">
                    <?= echo_icon('x', 24) ?>
                </button>
            </div>
            <form id="customerForm" method="POST" action="/?module=customer">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="customerId" value="">

                <div class="modal-body">
                    <div id="formErrors" class="alert alert-error" style="display: none;"></div>

                    <div id="formContainer">
                        <!-- Form will be loaded here -->
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuller</button>
                    <button type="submit" class="btn btn-primary">
                        <?= echo_icon('save', 20) ?>
                        Gem
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h2>Bekræft Sletning</h2>
                <button type="button" class="btn-close" onclick="closeDeleteModal()">
                    <?= echo_icon('x', 24) ?>
                </button>
            </div>
            <div class="modal-body">
                <p>Er du sikker på, at du vil slette kunden <strong id="deleteCustomerName"></strong>?</p>
                <p class="text-muted">Denne handling kan ikke fortrydes.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuller</button>
                <button type="button" class="btn btn-danger" onclick="deleteCustomer()">
                    <?= echo_icon('trash', 20) ?>
                    Slet
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script>
        let deleteCustomerId = null;

        // Open create modal
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Ny Kunde';
            document.getElementById('formAction').value = 'create';
            document.getElementById('customerId').value = '';
            document.getElementById('formErrors').style.display = 'none';

            // Load empty form
            loadForm({});

            document.getElementById('customerModal').classList.add('active');
        }

        // Open edit modal
        async function openEditModal(id) {
            document.getElementById('modalTitle').textContent = 'Rediger Kunde';
            document.getElementById('formAction').value = 'update';
            document.getElementById('customerId').value = id;
            document.getElementById('formErrors').style.display = 'none';

            // Fetch customer data
            try {
                const response = await fetch(`/?module=customer&action=get&id=${id}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();

                if (result.success) {
                    loadForm(result.data);
                    document.getElementById('customerModal').classList.add('active');
                } else {
                    alert('Fejl: ' + (result.error || 'Kunne ikke hente kunde'));
                }
            } catch (error) {
                console.error('Error fetching customer:', error);
                alert('Der opstod en fejl ved hentning af kunde');
            }
        }

        // Load form with data
        function loadForm(data) {
            const formContainer = document.getElementById('formContainer');
            formContainer.innerHTML = `
                <div class="form-group">
                    <label for="name" class="required">Firmanavn</label>
                    <input type="text" id="name" name="name" value="${escapeHtml(data.name || '')}" required maxlength="255" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cvr_number">CVR Nummer</label>
                        <input type="text" id="cvr_number" name="cvr_number" value="${escapeHtml(data.cvr_number || '')}" maxlength="50" pattern="^[0-9]{8}$" class="form-control">
                        <small class="form-help">8 cifre</small>
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Kontaktperson</label>
                        <input type="text" id="contact_person" name="contact_person" value="${escapeHtml(data.contact_person || '')}" maxlength="255" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="${escapeHtml(data.email || '')}" maxlength="255" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="phone">Telefon</label>
                        <input type="tel" id="phone" name="phone" value="${escapeHtml(data.phone || '')}" maxlength="50" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Adresse</label>
                    <input type="text" id="address" name="address" value="${escapeHtml(data.address || '')}" maxlength="255" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">Postnummer</label>
                        <input type="text" id="postal_code" name="postal_code" value="${escapeHtml(data.postal_code || '')}" maxlength="10" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="city">By</label>
                        <input type="text" id="city" name="city" value="${escapeHtml(data.city || '')}" maxlength="100" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Noter</label>
                    <textarea id="notes" name="notes" rows="5" maxlength="2000" class="form-control">${escapeHtml(data.notes || '')}</textarea>
                </div>
            `;
        }

        // Close modal
        function closeModal() {
            document.getElementById('customerModal').classList.remove('active');
        }

        // Confirm delete
        function confirmDelete(id, name) {
            deleteCustomerId = id;
            document.getElementById('deleteCustomerName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }

        // Close delete modal
        function closeDeleteModal() {
            deleteCustomerId = null;
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Delete customer
        async function deleteCustomer() {
            if (!deleteCustomerId) return;

            try {
                const formData = new FormData();
                formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= csrf_token() ?>');

                const response = await fetch(`/?module=customer&action=delete&id=${deleteCustomerId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = '/?module=customer&success=deleted';
                } else {
                    alert('Fejl: ' + (result.error || 'Kunne ikke slette kunde'));
                    closeDeleteModal();
                }
            } catch (error) {
                console.error('Error deleting customer:', error);
                alert('Der opstod en fejl ved sletning af kunde');
                closeDeleteModal();
            }
        }

        // Handle form submission
        document.getElementById('customerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('/?module=customer', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    const action = formData.get('action');
                    const successType = action === 'create' ? 'created' : 'updated';
                    window.location.href = `/?module=customer&success=${successType}`;
                } else {
                    // Show errors
                    const errorsDiv = document.getElementById('formErrors');
                    errorsDiv.innerHTML = result.errors.map(err => `<p>${escapeHtml(err)}</p>`).join('');
                    errorsDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                alert('Der opstod en fejl ved lagring');
            }
        });

        // Clear search
        function clearSearch() {
            window.location.href = '/?module=customer';
        }

        // Utility function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteModal();
            }
        });

        // Close modal on background click
        document.getElementById('customerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
