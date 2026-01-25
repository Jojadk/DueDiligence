/**
 * TableManager - Centraliseret tabel håndtering med sort, filter og paging
 *
 * Features:
 * - Column sorting (ascending/descending)
 * - Search/filter functionality
 * - Client-side og server-side pagination
 * - Responsive design support
 *
 * Usage:
 * const tableManager = new TableManager('myTableId', {
 *     sortable: true,
 *     filterable: true,
 *     pagination: { pageSize: 25, mode: 'client' },
 *     serverSide: false
 * });
 */

class TableManager {
    constructor(tableId, options = {}) {
        this.table = document.getElementById(tableId);
        if (!this.table) {
            console.error(`Table with id "${tableId}" not found`);
            return;
        }

        this.options = {
            sortable: options.sortable !== false, // Default true
            filterable: options.filterable !== false, // Default true
            pagination: {
                enabled: options.pagination?.enabled !== false, // Default true
                pageSize: options.pagination?.pageSize || 25,
                mode: options.pagination?.mode || 'client' // 'client' or 'server'
            },
            searchDelay: options.searchDelay || 300, // Debounce delay
            serverSide: options.serverSide || false,
            callbacks: {
                onSort: options.onSort || null,
                onFilter: options.onFilter || null,
                onPageChange: options.onPageChange || null
            }
        };

        this.currentPage = 1;
        this.currentSort = { column: null, direction: 'asc' };
        this.currentFilter = '';
        this.allRows = [];
        this.filteredRows = [];
        this.searchTimeout = null;

        this.init();
    }

    init() {
        // Store original rows
        this.storeRows();

        // Initialize sortable headers
        if (this.options.sortable) {
            this.initSorting();
        }

        // Initialize filter/search
        if (this.options.filterable) {
            this.initFiltering();
        }

        // Initialize pagination
        if (this.options.pagination.enabled) {
            this.initPagination();
        }

        // Initial render
        this.render();
    }

    storeRows() {
        const tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        // Store all rows
        this.allRows = Array.from(tbody.querySelectorAll('tr'));
        this.filteredRows = [...this.allRows];
    }

    initSorting() {
        const headers = this.table.querySelectorAll('thead th[data-sortable]');

        headers.forEach(header => {
            // Add sortable class and icon
            header.classList.add('sortable');
            if (!header.querySelector('.sort-icon')) {
                const sortIcon = document.createElement('span');
                sortIcon.className = 'sort-icon';
                sortIcon.innerHTML = this.getSortIcon(null);
                header.appendChild(sortIcon);
            }

            // Add click handler
            header.addEventListener('click', () => {
                this.handleSort(header);
            });
        });
    }

    initFiltering() {
        // Check if filter input exists
        let filterInput = document.getElementById(`${this.table.id}-filter`);

        // Create filter input if it doesn't exist
        if (!filterInput) {
            const filterContainer = document.createElement('div');
            filterContainer.className = 'table-filter';
            filterContainer.innerHTML = `
                <input
                    type="text"
                    id="${this.table.id}-filter"
                    class="filter-input"
                    placeholder="Søg i tabel..."
                    autocomplete="off"
                />
            `;

            // Insert before table container
            const tableContainer = this.table.closest('.table-container') || this.table.parentElement;
            tableContainer.parentElement.insertBefore(filterContainer, tableContainer);

            filterInput = document.getElementById(`${this.table.id}-filter`);
        }

        // Add input handler with debounce
        filterInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.handleFilter(e.target.value);
            }, this.options.searchDelay);
        });
    }

    initPagination() {
        // Check if pagination exists
        let paginationContainer = document.getElementById(`${this.table.id}-pagination`);

        // Create pagination container if it doesn't exist
        if (!paginationContainer) {
            paginationContainer = document.createElement('div');
            paginationContainer.id = `${this.table.id}-pagination`;
            paginationContainer.className = 'table-pagination';

            // Insert after table container
            const tableContainer = this.table.closest('.table-container') || this.table.parentElement;
            tableContainer.parentElement.insertBefore(
                paginationContainer,
                tableContainer.nextSibling
            );
        }

        this.paginationContainer = paginationContainer;
    }

    handleSort(header) {
        const column = header.dataset.sortable;
        const columnIndex = Array.from(header.parentElement.children).indexOf(header);

        // Update sort direction
        if (this.currentSort.column === column) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort.column = column;
            this.currentSort.direction = 'asc';
        }

        // Update header icons
        this.table.querySelectorAll('thead th[data-sortable]').forEach(h => {
            const icon = h.querySelector('.sort-icon');
            if (icon) {
                icon.innerHTML = this.getSortIcon(
                    h.dataset.sortable === column ? this.currentSort.direction : null
                );
            }
        });

        // Server-side sorting
        if (this.options.serverSide && this.options.callbacks.onSort) {
            this.options.callbacks.onSort(column, this.currentSort.direction);
            return;
        }

        // Client-side sorting
        this.filteredRows.sort((a, b) => {
            const aCell = a.children[columnIndex];
            const bCell = b.children[columnIndex];

            if (!aCell || !bCell) return 0;

            // Get sort value (from data-sort attribute or text content)
            let aValue = aCell.dataset.sort || aCell.textContent.trim();
            let bValue = bCell.dataset.sort || bCell.textContent.trim();

            // Try to parse as numbers
            const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return this.currentSort.direction === 'asc' ? aNum - bNum : bNum - aNum;
            }

            // String comparison
            return this.currentSort.direction === 'asc'
                ? aValue.localeCompare(bValue, 'da-DK')
                : bValue.localeCompare(aValue, 'da-DK');
        });

        this.currentPage = 1; // Reset to first page
        this.render();
    }

    handleFilter(query) {
        this.currentFilter = query.toLowerCase();

        // Server-side filtering
        if (this.options.serverSide && this.options.callbacks.onFilter) {
            this.options.callbacks.onFilter(query);
            return;
        }

        // Client-side filtering
        if (!query) {
            this.filteredRows = [...this.allRows];
        } else {
            this.filteredRows = this.allRows.filter(row => {
                const text = row.textContent.toLowerCase();
                return text.includes(this.currentFilter);
            });
        }

        this.currentPage = 1; // Reset to first page
        this.render();
    }

    handlePageChange(page) {
        if (page < 1 || page > this.getTotalPages()) return;

        this.currentPage = page;

        // Server-side pagination
        if (this.options.serverSide && this.options.callbacks.onPageChange) {
            this.options.callbacks.onPageChange(page);
            return;
        }

        // Client-side pagination
        this.render();
    }

    getTotalPages() {
        return Math.ceil(this.filteredRows.length / this.options.pagination.pageSize);
    }

    render() {
        const tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        // Calculate pagination
        const pageSize = this.options.pagination.pageSize;
        const startIdx = (this.currentPage - 1) * pageSize;
        const endIdx = startIdx + pageSize;
        const pageRows = this.filteredRows.slice(startIdx, endIdx);

        // Clear tbody
        tbody.innerHTML = '';

        // Add filtered and paginated rows
        if (pageRows.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
                <td colspan="100" style="text-align: center; padding: 2rem; color: var(--color-gray-500);">
                    Ingen resultater fundet
                </td>
            `;
            tbody.appendChild(emptyRow);
        } else {
            pageRows.forEach(row => tbody.appendChild(row));
        }

        // Update pagination controls
        if (this.options.pagination.enabled) {
            this.renderPagination();
        }
    }

    renderPagination() {
        if (!this.paginationContainer) return;

        const totalPages = this.getTotalPages();
        const currentPage = this.currentPage;

        if (totalPages <= 1) {
            this.paginationContainer.innerHTML = '';
            return;
        }

        let paginationHTML = '<div class="pagination">';

        // Previous button
        paginationHTML += `
            <button
                class="pagination-btn ${currentPage === 1 ? 'disabled' : ''}"
                ${currentPage === 1 ? 'disabled' : ''}
                onclick="this.closest('.table-pagination').tableManager.handlePageChange(${currentPage - 1})"
            >
                ${icon('chevron-left', 16)}
                <span class="btn-text">Forrige</span>
            </button>
        `;

        // Page info
        paginationHTML += `
            <span class="pagination-info">
                Side ${currentPage} af ${totalPages}
                <span class="pagination-count">(${this.filteredRows.length} resultater)</span>
            </span>
        `;

        // Next button
        paginationHTML += `
            <button
                class="pagination-btn ${currentPage === totalPages ? 'disabled' : ''}"
                ${currentPage === totalPages ? 'disabled' : ''}
                onclick="this.closest('.table-pagination').tableManager.handlePageChange(${currentPage + 1})"
            >
                <span class="btn-text">Næste</span>
                ${icon('chevron-right', 16)}
            </button>
        `;

        paginationHTML += '</div>';

        this.paginationContainer.innerHTML = paginationHTML;

        // Store reference for onclick handlers
        this.paginationContainer.tableManager = this;
    }

    getSortIcon(direction) {
        if (!direction) {
            return icon('chevrons-up-down', 14);
        }
        return direction === 'asc'
            ? icon('chevron-up', 14)
            : icon('chevron-down', 14);
    }

    // Public methods for manual control
    refresh() {
        this.storeRows();
        this.render();
    }

    setFilter(query) {
        const filterInput = document.getElementById(`${this.table.id}-filter`);
        if (filterInput) {
            filterInput.value = query;
        }
        this.handleFilter(query);
    }

    clearFilter() {
        this.setFilter('');
    }

    destroy() {
        // Clean up event listeners and DOM modifications
        const filterInput = document.getElementById(`${this.table.id}-filter`);
        if (filterInput) {
            filterInput.parentElement.remove();
        }

        if (this.paginationContainer) {
            this.paginationContainer.remove();
        }

        // Remove sort handlers
        this.table.querySelectorAll('thead th[data-sortable]').forEach(header => {
            header.classList.remove('sortable');
            const icon = header.querySelector('.sort-icon');
            if (icon) icon.remove();
        });
    }
}

// Make globally available
window.TableManager = TableManager;
