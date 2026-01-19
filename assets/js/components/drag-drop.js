/**
 * Drag-and-Drop Sortering System
 * Med backend permission validation
 */

const DragDrop = {
    /**
     * Initialize sortable list
     */
    init(containerId, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Drag-drop container not found:', containerId);
            return;
        }

        const {
            handle = '.drag-handle',
            onUpdate = null,
            buildingId = null
        } = options;

        this.makeSortable(container, handle, async (items) => {
            // Send updated order to backend
            const itemIds = items.map(item => item.dataset.id);

            try {
                const formData = new FormData();
                formData.append('action', 'update_order');
                formData.append('items', JSON.stringify(itemIds));
                if (buildingId) {
                    formData.append('building_id', buildingId);
                }

                const response = await API.post('/api.php', formData, true);

                if (response.success) {
                    Toast.success('Rækkefølge opdateret');
                    if (onUpdate) onUpdate(itemIds);
                } else {
                    Toast.error(response.error || 'Kunne ikke opdatere rækkefølge');
                    // Revert order
                    this.revertOrder(container);
                }
            } catch (error) {
                console.error('Order update error:', error);
                Toast.error('Fejl ved opdatering af rækkefølge');
                this.revertOrder(container);
            }
        });
    },

    /**
     * Make container sortable
     */
    makeSortable(container, handleSelector, onUpdate) {
        let draggedItem = null;
        let originalOrder = null;

        const items = Array.from(container.querySelectorAll('[data-id]'));
        
        items.forEach(item => {
            const handle = item.querySelector(handleSelector) || item;
            handle.setAttribute('draggable', 'true');
            handle.style.cursor = 'move';

            handle.addEventListener('dragstart', (e) => {
                draggedItem = item;
                originalOrder = items.map(i => i.dataset.id);
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', (e) => {
                item.classList.remove('dragging');
                
                // Check if order changed
                const currentOrder = Array.from(container.querySelectorAll('[data-id]'));
                const newOrder = currentOrder.map(i => i.dataset.id);
                
                if (JSON.stringify(originalOrder) !== JSON.stringify(newOrder)) {
                    if (onUpdate) onUpdate(currentOrder);
                }
                
                draggedItem = null;
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                
                if (draggedItem === item) return;

                const afterElement = this.getDragAfterElement(container, e.clientY);
                
                if (afterElement == null) {
                    container.appendChild(draggedItem);
                } else {
                    container.insertBefore(draggedItem, afterElement);
                }
            });
        });

        // Store original order for revert
        container.dataset.originalOrder = JSON.stringify(items.map(i => i.dataset.id));
    },

    /**
     * Get element after drag position
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('[data-id]:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },

    /**
     * Revert to original order
     */
    revertOrder(container) {
        const originalOrder = JSON.parse(container.dataset.originalOrder || '[]');
        const items = container.querySelectorAll('[data-id]');
        
        // Create a map of items by ID
        const itemMap = {};
        items.forEach(item => {
            itemMap[item.dataset.id] = item;
        });

        // Reorder based on original order
        originalOrder.forEach(id => {
            if (itemMap[id]) {
                container.appendChild(itemMap[id]);
            }
        });
    }
};

// Export globally
window.DragDrop = DragDrop;
