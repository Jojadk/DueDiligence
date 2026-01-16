/**
 * Searchable Select Component
 * Provides dropdown with search functionality for large datasets
 */

class SearchableSelect {
    constructor(options) {
        this.options = {
            container: null,         // Container element
            name: '',               // Input name
            placeholder: 'Søg...',  // Placeholder text
            searchUrl: '',          // URL to fetch data
            required: false,        // Required field
            value: null,            // Initial value
            label: null,            // Initial label
            minChars: 0,            // Minimum characters to search
            debounce: 300,          // Debounce delay
            onChange: null,         // Change callback
            ...options
        };

        this.isOpen = false;
        this.selectedValue = this.options.value;
        this.selectedLabel = this.options.label;
        this.results = [];

        this.init();
    }

    init() {
        if (!this.options.container) {
            console.error('SearchableSelect: container is required');
            return;
        }

        this.render();
        this.bindEvents();

        // Load initial value if provided
        if (this.selectedValue && !this.selectedLabel) {
            this.loadInitialValue();
        }
    }

    render() {
        const { container, name, placeholder, required } = this.options;

        container.innerHTML = `
            <div class="searchable-select">
                <input
                    type="hidden"
                    name="${escapeHtml(name)}"
                    value="${escapeHtml(this.selectedValue || '')}"
                    ${required ? 'required' : ''}
                    class="searchable-select-value"
                >
                <div class="searchable-select-trigger">
                    <span class="searchable-select-label">
                        ${this.selectedLabel ? escapeHtml(this.selectedLabel) : `<span class="placeholder">${escapeHtml(placeholder)}</span>`}
                    </span>
                    <svg class="searchable-select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
                <div class="searchable-select-dropdown">
                    <div class="searchable-select-search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input
                            type="text"
                            class="searchable-select-input"
                            placeholder="${escapeHtml(placeholder)}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="searchable-select-results">
                        <div class="searchable-select-loading" style="display: none;">
                            <div class="spinner-small"></div>
                            Søger...
                        </div>
                        <div class="searchable-select-empty" style="display: none;">
                            Ingen resultater
                        </div>
                        <div class="searchable-select-list"></div>
                    </div>
                </div>
            </div>
        `;

        this.elements = {
            container: container.querySelector('.searchable-select'),
            trigger: container.querySelector('.searchable-select-trigger'),
            label: container.querySelector('.searchable-select-label'),
            dropdown: container.querySelector('.searchable-select-dropdown'),
            search: container.querySelector('.searchable-select-input'),
            results: container.querySelector('.searchable-select-list'),
            loading: container.querySelector('.searchable-select-loading'),
            empty: container.querySelector('.searchable-select-empty'),
            hiddenInput: container.querySelector('.searchable-select-value')
        };
    }

    bindEvents() {
        // Open dropdown
        this.elements.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Search input
        this.elements.search.addEventListener('input', debounce((e) => {
            this.search(e.target.value);
        }, this.options.debounce));

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!this.elements.container.contains(e.target)) {
                this.close();
            }
        });

        // Keyboard navigation
        this.elements.search.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        });
    }

    async loadInitialValue() {
        // This would need an endpoint to fetch a single item by ID
        // For now, just keep the value
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.elements.dropdown.classList.add('active');
        this.elements.search.focus();

        // Trigger initial search if no min chars requirement
        if (this.options.minChars === 0) {
            this.search('');
        }
    }

    close() {
        this.isOpen = false;
        this.elements.dropdown.classList.remove('active');
        this.elements.search.value = '';
    }

    async search(query) {
        const { searchUrl, minChars } = this.options;

        if (query.length < minChars && query.length > 0) {
            return;
        }

        this.showLoading();

        try {
            const url = `${searchUrl}${searchUrl.includes('?') ? '&' : '?'}q=${encodeURIComponent(query)}`;
            const response = await API.get(url);

            if (response.success && response.data) {
                this.results = response.data;
                this.renderResults();
            } else {
                this.results = [];
                this.showEmpty();
            }
        } catch (error) {
            console.error('Search error:', error);
            this.results = [];
            this.showEmpty();
        }
    }

    renderResults() {
        if (this.results.length === 0) {
            this.showEmpty();
            return;
        }

        this.hideLoading();
        this.hideEmpty();

        this.elements.results.innerHTML = this.results.map(item => `
            <div class="searchable-select-item" data-value="${escapeHtml(item.id)}">
                ${escapeHtml(item.label)}
            </div>
        `).join('');

        // Bind click events
        this.elements.results.querySelectorAll('.searchable-select-item').forEach(item => {
            item.addEventListener('click', () => {
                this.select(item.dataset.value, item.textContent);
            });
        });
    }

    select(value, label) {
        this.selectedValue = value;
        this.selectedLabel = label;

        // Update UI
        this.elements.label.textContent = label;
        this.elements.hiddenInput.value = value;

        // Trigger change event
        if (this.options.onChange) {
            this.options.onChange(value, label);
        }

        // Dispatch native change event
        this.elements.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

        this.close();
    }

    clear() {
        this.selectedValue = null;
        this.selectedLabel = null;
        this.elements.label.innerHTML = `<span class="placeholder">${escapeHtml(this.options.placeholder)}</span>`;
        this.elements.hiddenInput.value = '';

        if (this.options.onChange) {
            this.options.onChange(null, null);
        }
    }

    setValue(value, label) {
        this.select(value, label);
    }

    getValue() {
        return this.selectedValue;
    }

    getLabel() {
        return this.selectedLabel;
    }

    showLoading() {
        this.elements.loading.style.display = 'flex';
        this.elements.empty.style.display = 'none';
        this.elements.results.innerHTML = '';
    }

    hideLoading() {
        this.elements.loading.style.display = 'none';
    }

    showEmpty() {
        this.hideLoading();
        this.elements.empty.style.display = 'flex';
        this.elements.results.innerHTML = '';
    }

    hideEmpty() {
        this.elements.empty.style.display = 'none';
    }

    destroy() {
        if (this.elements.container) {
            this.elements.container.innerHTML = '';
        }
    }
}

// Export SearchableSelect globally
window.SearchableSelect = SearchableSelect;
