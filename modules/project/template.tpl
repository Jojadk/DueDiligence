<?php
/**
 * Project Module Template
 */
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekter - DueDiligence v2.0</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="page-header">
            <div class="header-left">
                <h1>
                    <?= echo_icon('folder', 32) ?>
                    Projekter
                </h1>
                <p class="subtitle">Administrer dine projekter</p>
            </div>
            <div class="header-right">
                <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                    <?= echo_icon('plus', 20) ?>
                    Nyt Projekt
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
                <input type="hidden" name="module" value="project">
                <?php if ($customerId): ?>
                    <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                <?php endif; ?>
                <div class="search-input-group">
                    <?= echo_icon('search', 20) ?>
                    <input
                        type="text"
                        name="search"
                        placeholder="Søg efter projektnavn, adresse, by eller kunde..."
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
                <span class="stat-label">Total antal projekter:</span>
                <span class="stat-value"><?= number_format($totalProjects, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Project Table -->
        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <?= echo_icon('folder', 48) ?>
                <h3>Ingen projekter fundet</h3>
                <p>
                    <?php if ($searchTerm): ?>
                        Prøv en anden søgning
                    <?php else: ?>
                        Kom i gang ved at oprette dit første projekt
                    <?php endif; ?>
                </p>
                <?php if (!$searchTerm): ?>
                    <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                        <?= echo_icon('plus', 20) ?>
                        Opret Projekt
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Projekt</th>
                            <th>Kunde</th>
                            <th>Adresse</th>
                            <th>Bygninger</th>
                            <th>Elementer</th>
                            <th>Status</th>
                            <th class="actions-column">Handlinger</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr data-id="<?= $project['id'] ?>">
                                <td class="font-semibold">
                                    <a href="/?module=building&project_id=<?= $project['id'] ?>" class="link">
                                        <?= esc_html($project['name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="/?module=customer" class="link-muted">
                                        <?= esc_html($project['customer_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= esc_html($project['address']) ?>,
                                    <?= esc_html($project['postal_code']) ?>
                                    <?= esc_html($project['city']) ?>
                                </td>
                                <td>
                                    <?php if ($project['building_count'] > 0): ?>
                                        <a href="/?module=building&project_id=<?= $project['id'] ?>" class="badge badge-info">
                                            <?= $project['building_count'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($project['element_count'] > 0): ?>
                                        <span class="badge badge-secondary">
                                            <?= $project['element_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'planning' => 'Planlægning',
                                        'active' => 'Aktiv',
                                        'on_hold' => 'På vent',
                                        'completed' => 'Afsluttet',
                                        'archived' => 'Arkiveret'
                                    ];
                                    $statusColors = [
                                        'planning' => 'badge-warning',
                                        'active' => 'badge-success',
                                        'on_hold' => 'badge-secondary',
                                        'completed' => 'badge-info',
                                        'archived' => 'badge-muted'
                                    ];
                                    $status = $project['status'] ?? 'planning';
                                    ?>
                                    <span class="badge <?= $statusColors[$status] ?? 'badge-secondary' ?>">
                                        <?= $statusLabels[$status] ?? $status ?>
                                    </span>
                                </td>
                                <td class="actions-column">
                                    <div class="action-buttons">
                                        <button
                                            type="button"
                                            class="btn-icon"
                                            onclick="openEditModal(<?= $project['id'] ?>)"
                                            title="Rediger"
                                        >
                                            <?= echo_icon('edit', 18) ?>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-icon btn-icon-danger"
                                            onclick="confirmDelete(<?= $project['id'] ?>, '<?= esc_js($project['name']) ?>')"
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
                        <a href="/?module=project&page=<?= $page - 1 ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $customerId ? '&customer_id=' . $customerId : '' ?>" class="btn btn-secondary">
                            <?= echo_icon('chevron-left', 16) ?>
                            Forrige
                        </a>
                    <?php endif; ?>

                    <span class="pagination-info">
                        Side <?= $page ?> af <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="/?module=project&page=<?= $page + 1 ?><?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?><?= $customerId ? '&customer_id=' . $customerId : '' ?>" class="btn btn-secondary">
                            Næste
                            <?= echo_icon('chevron-right', 16) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="modalTitle">Nyt Projekt</h2>
                <button type="button" class="btn-close" onclick="closeModal()">
                    <?= echo_icon('x', 24) ?>
                </button>
            </div>
            <form id="projectForm" method="POST" action="/?module=project">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="projectId" value="">

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
                <p>Er du sikker på, at du vil slette projektet <strong id="deleteProjectName"></strong>?</p>
                <p class="text-muted">Denne handling kan ikke fortrydes.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Annuller</button>
                <button type="button" class="btn btn-danger" onclick="deleteProject()">
                    <?= echo_icon('trash', 20) ?>
                    Slet
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/searchable-select.js"></script>
    <script>
        let deleteProjectId = null;
        let customerSelect = null;

        // Open create modal
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Nyt Projekt';
            document.getElementById('formAction').value = 'create';
            document.getElementById('projectId').value = '';
            document.getElementById('formErrors').style.display = 'none';

            // Load empty form
            loadForm({});

            document.getElementById('projectModal').classList.add('active');
        }

        // Open edit modal
        async function openEditModal(id) {
            document.getElementById('modalTitle').textContent = 'Rediger Projekt';
            document.getElementById('formAction').value = 'update';
            document.getElementById('projectId').value = id;
            document.getElementById('formErrors').style.display = 'none';

            // Fetch project data
            try {
                const response = await fetch(`/?module=project&action=get&id=${id}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();

                if (result.success) {
                    loadForm(result.data);
                    document.getElementById('projectModal').classList.add('active');
                } else {
                    alert('Fejl: ' + (result.error || 'Kunne ikke hente projekt'));
                }
            } catch (error) {
                console.error('Error fetching project:', error);
                alert('Der opstod en fejl ved hentning af projekt');
            }
        }

        // Load form with data
        function loadForm(data) {
            const formContainer = document.getElementById('formContainer');
            formContainer.innerHTML = `
                <div class="form-group">
                    <label for="name" class="required">Projekt Navn</label>
                    <input type="text" id="name" name="name" value="${escapeHtml(data.name || '')}" required maxlength="255" placeholder="Indtast projektnavn" class="form-control">
                </div>

                <div class="form-group">
                    <label for="customer_id" class="required">Kunde</label>
                    <div id="customerSelectContainer"></div>
                </div>

                <div class="form-group">
                    <label for="address" class="required">Adresse</label>
                    <input type="text" id="address" name="address" value="${escapeHtml(data.address || '')}" required maxlength="255" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code" class="required">Postnummer</label>
                        <input type="text" id="postal_code" name="postal_code" value="${escapeHtml(data.postal_code || '')}" required maxlength="10" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="city" class="required">By</label>
                        <input type="text" id="city" name="city" value="${escapeHtml(data.city || '')}" required maxlength="100" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bbr_number">BBR Nummer</label>
                        <input type="text" id="bbr_number" name="bbr_number" value="${escapeHtml(data.bbr_number || '')}" maxlength="50" class="form-control">
                        <small class="form-help">Bygnings- og Boligregistret nummer</small>
                    </div>
                    <div class="form-group">
                        <label for="inspection_date">Besigtigelsesdato</label>
                        <input type="date" id="inspection_date" name="inspection_date" value="${escapeHtml(data.inspection_date || '')}" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="status" class="required">Status</label>
                    <select id="status" name="status" required class="form-control">
                        <option value="planning" ${data.status === 'planning' || !data.status ? 'selected' : ''}>Planlægning</option>
                        <option value="active" ${data.status === 'active' ? 'selected' : ''}>Aktiv</option>
                        <option value="on_hold" ${data.status === 'on_hold' ? 'selected' : ''}>På vent</option>
                        <option value="completed" ${data.status === 'completed' ? 'selected' : ''}>Afsluttet</option>
                        <option value="archived" ${data.status === 'archived' ? 'selected' : ''}>Arkiveret</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Beskrivelse</label>
                    <textarea id="description" name="description" rows="5" maxlength="2000" class="form-control">${escapeHtml(data.description || '')}</textarea>
                </div>
            `;

            // Initialize searchable select for customer
            customerSelect = new SearchableSelect({
                container: document.getElementById('customerSelectContainer'),
                name: 'customer_id',
                placeholder: 'Søg og vælg kunde...',
                searchUrl: '/?module=project&action=search_customers',
                required: true,
                value: data.customer_id || null,
                label: data.customer_label || null
            });
        }

        // Close modal
        function closeModal() {
            document.getElementById('projectModal').classList.remove('active');
            if (customerSelect) {
                customerSelect.destroy();
                customerSelect = null;
            }
        }

        // Confirm delete
        function confirmDelete(id, name) {
            deleteProjectId = id;
            document.getElementById('deleteProjectName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }

        // Close delete modal
        function closeDeleteModal() {
            deleteProjectId = null;
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Delete project
        async function deleteProject() {
            if (!deleteProjectId) return;

            try {
                const formData = new FormData();
                formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= csrf_token() ?>');

                const response = await fetch(`/?module=project&action=delete&id=${deleteProjectId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = '/?module=project&success=deleted';
                } else {
                    alert('Fejl: ' + (result.error || 'Kunne ikke slette projekt'));
                    closeDeleteModal();
                }
            } catch (error) {
                console.error('Error deleting project:', error);
                alert('Der opstod en fejl ved sletning af projekt');
                closeDeleteModal();
            }
        }

        // Handle form submission
        document.getElementById('projectForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('/?module=project', {
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
                    window.location.href = `/?module=project&success=${successType}`;
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
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
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
        document.getElementById('projectModal').addEventListener('click', function(e) {
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
