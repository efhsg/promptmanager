/**
 * DirectorySelector - Reusable directory autocomplete widget
 *
 * Usage:
 *   const selector = new DirectorySelector({
 *       inputElement: document.getElementById('my-input'),
 *       dropdownElement: document.getElementById('my-dropdown'),
 *       pathListUrl: '/field/path-list',
 *       onSelect: (path) => console.log('Selected:', path),
 *       onChange: (value) => console.log('Changed:', value)
 *   });
 *
 *   // Load directories for a project
 *   selector.load(projectId, 'directory');
 *
 *   // Get current value
 *   const path = selector.getValue();
 *
 *   // Set value programmatically
 *   selector.setValue('/some/path');
 *
 *   // Reset to initial state
 *   selector.reset();
 */
class DirectorySelector {
    /**
     * @param {Object} options
     * @param {HTMLInputElement} options.inputElement - The text input element
     * @param {HTMLElement} options.dropdownElement - The dropdown container element
     * @param {string} options.pathListUrl - URL to fetch path list from
     * @param {Function} [options.onSelect] - Callback when a path is selected from dropdown
     * @param {Function} [options.onChange] - Callback when input value changes
     * @param {number} [options.debounceMs=200] - Debounce delay in milliseconds
     * @param {number} [options.maxResults=10] - Maximum number of results to show
     * @param {string} [options.placeholder='Start typing to search...'] - Input placeholder
     * @param {string} [options.emptyText='No matches found'] - Text when no matches
     */
    constructor(options) {
        this.input = options.inputElement;
        this.dropdown = options.dropdownElement;
        this.pathListUrl = options.pathListUrl;
        this.onSelect = options.onSelect || (() => {});
        this.onChange = options.onChange || (() => {});
        this.debounceMs = options.debounceMs ?? 200;
        this.maxResults = options.maxResults ?? 10;
        this.placeholder = options.placeholder ?? 'Start typing to search...';
        this.emptyText = options.emptyText ?? 'No matches found';

        this.cache = [];
        this.debounceTimer = null;
        this.isLoading = false;

        this._bindEvents();
    }

    _bindEvents() {
        this.input.addEventListener('input', () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this._filterAndShow();
                this.onChange(this.input.value);
            }, this.debounceMs);
        });

        this.input.addEventListener('focus', () => {
            if (this.cache.length > 0) {
                this._filterAndShow();
            }
        });

        this.dropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.dropdown-item');
            if (item) {
                const value = item.dataset.value || item.textContent;
                this.input.value = value;
                this._hideDropdown();
                this.onSelect(value);
                this.onChange(value);
            }
        });

        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.dropdown.contains(e.target)) {
                this._hideDropdown();
            }
        });

        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this._hideDropdown();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this._focusNextItem(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this._focusNextItem(-1);
            } else if (e.key === 'Enter') {
                const active = this.dropdown.querySelector('.dropdown-item:focus, .dropdown-item.active');
                if (active) {
                    e.preventDefault();
                    active.click();
                }
            }
        });
    }

    _focusNextItem(direction) {
        const items = Array.from(this.dropdown.querySelectorAll('.dropdown-item'));
        if (items.length === 0) return;

        const current = this.dropdown.querySelector('.dropdown-item:focus');
        let index = current ? items.indexOf(current) + direction : (direction > 0 ? 0 : items.length - 1);

        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;

        items[index].focus();
    }

    _filterAndShow() {
        const query = this.input.value.trim().toLowerCase();
        const matches = this.cache
            .filter(p => p.toLowerCase().includes(query))
            .slice(0, this.maxResults);

        if (matches.length === 0 && query) {
            this.dropdown.innerHTML = `<span class="dropdown-item-text text-muted">${this.emptyText}</span>`;
            this._showDropdown();
            return;
        }

        if (matches.length === 0) {
            this._hideDropdown();
            return;
        }

        this.dropdown.innerHTML = matches.map(p =>
            `<button type="button" class="dropdown-item" data-value="${this._escapeHtml(p)}">${this._escapeHtml(p || '/')}</button>`
        ).join('');
        this._showDropdown();
    }

    _showDropdown() {
        this.dropdown.classList.add('show');
    }

    _hideDropdown() {
        this.dropdown.classList.remove('show');
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Load directories for a project
     * @param {string|number} projectId - The project ID
     * @param {string} [type='directory'] - Type of paths to load ('directory' or 'file')
     * @returns {Promise<string[]>} The loaded paths
     */
    async load(projectId, type = 'directory') {
        if (!projectId) {
            this.cache = [];
            return [];
        }

        this.isLoading = true;

        try {
            // Check if pathListUrl already has query params
            const separator = this.pathListUrl.includes('?') ? '&' : '?';
            const url = `${this.pathListUrl}${separator}projectId=${projectId}&type=${type}`;
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();
            if (data.success && Array.isArray(data.paths)) {
                this.cache = data.paths;
                return this.cache;
            }

            this.cache = [];
            return [];
        } catch (err) {
            this.cache = [];
            return [];
        } finally {
            this.isLoading = false;
        }
    }

    /**
     * Get current input value
     * @returns {string}
     */
    getValue() {
        return this.input.value.trim();
    }

    /**
     * Set input value
     * @param {string} value
     */
    setValue(value) {
        this.input.value = value || '';
        this.onChange(this.input.value);
    }

    /**
     * Reset to initial state
     * @param {string} [defaultValue='']
     */
    reset(defaultValue = '') {
        this.cache = [];
        this.input.value = defaultValue;
        this._hideDropdown();
    }

    /**
     * Check if paths are loaded
     * @returns {boolean}
     */
    hasData() {
        return this.cache.length > 0;
    }

    /**
     * Get cached paths
     * @returns {string[]}
     */
    getPaths() {
        return [...this.cache];
    }
}

// Export for module systems and make available globally
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DirectorySelector;
}
window.DirectorySelector = DirectorySelector;
