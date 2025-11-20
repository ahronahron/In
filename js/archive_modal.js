/**
 * Archive Modal Component
 * This file contains all functionality for the archive modal
 * Include this script and the archive_modal.html component in your page
 */

(function() {
    'use strict';

    // API endpoints - relative to pages folder
    const ARCHIVE_API = '../php/get_archives.php';
    const PROCESS_EXPIRED_API = '../php/process_and_archive_expired.php';

    // Initialize archive modal
    function initArchiveModal() {
        // Check if modal HTML is already loaded
        if (document.getElementById('archiveModal')) {
            setupEventListeners();
            return;
        }

        // Load modal HTML
        loadArchiveModalHTML();
    }

    // Load archive modal HTML from component file
    async function loadArchiveModalHTML() {
        // Check if modal already exists
        if (document.getElementById('archiveModal')) {
            setupEventListeners();
            return;
        }

        try {
            const response = await fetch('../components/archive_modal.html');
            if (!response.ok) {
                throw new Error('Failed to load archive modal HTML');
            }
            const html = await response.text();
            
            // Insert before closing body tag or at end of body
            const body = document.body;
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Move the modal to body
            while (tempDiv.firstChild) {
                body.appendChild(tempDiv.firstChild);
            }
            
            // Setup event listeners after HTML is loaded
            setupEventListeners();
        } catch (error) {
            console.error('Error loading archive modal:', error);
            // If fetch fails, the HTML should be included directly in the page
            // Just setup event listeners
            setupEventListeners();
        }
    }

    // Fallback: create modal if HTML file can't be loaded
    function createArchiveModalFallback() {
        console.warn('Using fallback archive modal creation');
        // This would be a minimal version, but for now we'll just log
        // The HTML should be included directly in pages if fetch fails
    }

    // Setup all event listeners for archive modal
    function setupEventListeners() {
        // Close Archive Modal button
        const closeBtn = document.getElementById('closeArchiveModal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                document.getElementById('archiveModal').classList.add('hidden');
            });
        }

        // Close Archive modal on backdrop click
        const modal = document.getElementById('archiveModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target.id === 'archiveModal') {
                    document.getElementById('archiveModal').classList.add('hidden');
                }
            });
        }

        // Tab switching event listeners
        const tabExpired = document.getElementById('tabExpiredModal');
        const tabCancelled = document.getElementById('tabCancelledModal');
        const tabDeleted = document.getElementById('tabDeletedModal');
        const tabSuppliers = document.getElementById('tabSuppliersModal');

        if (tabExpired) {
            tabExpired.addEventListener('click', () => switchArchiveTab('Expired'));
        }
        if (tabCancelled) {
            tabCancelled.addEventListener('click', () => switchArchiveTab('Cancelled'));
        }
        if (tabDeleted) {
            tabDeleted.addEventListener('click', () => switchArchiveTab('Deleted'));
        }
        if (tabSuppliers) {
            tabSuppliers.addEventListener('click', () => switchArchiveTab('Suppliers'));
        }
    }

    // Escape HTML helper
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Escape HTML helper for archive modal
    function escapeHtmlArchive(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Tab switching for Archive Modal
    function switchArchiveTab(tabName) {
        document.querySelectorAll('.tab-content-modal').forEach(content => {
            content.classList.add('hidden');
        });
        document.querySelectorAll('.tab-button-modal').forEach(btn => {
            btn.classList.remove('active', 'border-primary', 'text-primary', 'dark:text-primary-400');
            btn.classList.add('border-transparent', 'text-text-secondary', 'dark:text-gray-400');
        });
        
        const content = document.getElementById(`content${tabName}Modal`);
        const activeTab = document.getElementById(`tab${tabName}Modal`);
        
        if (content) {
            content.classList.remove('hidden');
        }
        if (activeTab) {
            activeTab.classList.add('active', 'border-primary', 'text-primary', 'dark:text-primary-400');
            activeTab.classList.remove('border-transparent', 'text-text-secondary', 'dark:text-gray-400');
        }
    }

    // Load archive data
    async function loadArchivesModal() {
        try {
            // First, process and archive any expired items
            try {
                await fetch(PROCESS_EXPIRED_API);
            } catch (error) {
                console.error('Error processing expired items:', error);
                // Continue even if processing fails
            }

            // Then load archive data
            const response = await fetch(`${ARCHIVE_API}?type=all`);
            const result = await response.json();
            
            if (result.success && result.data) {
                // Update counts
                const expiredCount = document.getElementById('expiredCountModal');
                const cancelledCount = document.getElementById('cancelledCountModal');
                const deletedCount = document.getElementById('deletedCountModal');
                const suppliersCount = document.getElementById('suppliersCountModal');
                
                if (expiredCount) expiredCount.textContent = result.data.counts.expired || 0;
                if (cancelledCount) cancelledCount.textContent = result.data.counts.cancelled || 0;
                if (deletedCount) deletedCount.textContent = result.data.counts.deleted || 0;
                if (suppliersCount) suppliersCount.textContent = result.data.counts.suppliers || 0;
                
                // Render data
                renderExpiredItemsModal(result.data.items.expired || []);
                renderCancelledOrdersModal(result.data.items.cancelled || []);
                renderDeletedItemsModal(result.data.items.deleted || []);
                renderArchivedSuppliersModal(result.data.items.suppliers || []);
            }
        } catch (error) {
            console.error('Error loading archives:', error);
        }
    }

    // Render expired items
    function renderExpiredItemsModal(items) {
        const tbody = document.getElementById('expiredItemsTableModal');
        if (!tbody) return;
        
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-text-tertiary dark:text-gray-500">No expired items found</td></tr>';
            return;
        }
        
        tbody.innerHTML = items.map(item => `
            <tr class="border-b border-border-light dark:border-gray-700 hover:bg-surface-hover dark:hover:bg-gray-800">
                <td class="py-3 px-4">
                    <div>
                        <div class="font-medium text-text-primary dark:text-gray-100">${escapeHtml(item.medicine_name || 'N/A')}</div>
                        <div class="text-xs text-text-tertiary dark:text-gray-500">${escapeHtml(item.medicine_ndc || '')}</div>
                    </div>
                </td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(item.batch_number || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${item.quantity || 0}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${item.expiration_date || 'N/A'}</td>
                <td class="py-3 px-4">
                    <span class="px-2 py-1 bg-error-100 dark:bg-error-900 text-error-700 dark:text-error-300 rounded-full text-xs font-medium">
                        ${item.days_expired || 0} days
                    </span>
                </td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${item.expired_at ? new Date(item.expired_at).toLocaleDateString() : 'N/A'}</td>
            </tr>
        `).join('');
    }

    // Render cancelled orders
    function renderCancelledOrdersModal(orders) {
        const tbody = document.getElementById('cancelledOrdersTableModal');
        if (!tbody) return;
        
        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-text-tertiary dark:text-gray-500">No cancelled orders found</td></tr>';
            return;
        }
        
        tbody.innerHTML = orders.map(order => `
            <tr class="border-b border-border-light dark:border-gray-700 hover:bg-surface-hover dark:hover:bg-gray-800">
                <td class="py-3 px-4 font-medium text-text-primary dark:text-gray-100">#${order.original_id || order.id}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(order.supplier_name || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${order.order_date || 'N/A'}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">$${parseFloat(order.total_amount || 0).toFixed(2)}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${order.item_count || 0} items</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${order.cancelled_at ? new Date(order.cancelled_at).toLocaleDateString() : 'N/A'}</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${escapeHtml(order.cancellation_reason || 'N/A')}</td>
            </tr>
        `).join('');
    }

    // Render deleted items
    function renderDeletedItemsModal(items) {
        const tbody = document.getElementById('deletedItemsTableModal');
        if (!tbody) return;
        
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-text-tertiary dark:text-gray-500">No deleted items found</td></tr>';
            return;
        }
        
        tbody.innerHTML = items.map(item => `
            <tr class="border-b border-border-light dark:border-gray-700 hover:bg-surface-hover dark:hover:bg-gray-800">
                <td class="py-3 px-4 font-medium text-text-primary dark:text-gray-100">${escapeHtml(item.name || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(item.ndc || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(item.manufacturer || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(item.category || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${item.quantity || 0}</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${item.deleted_at ? new Date(item.deleted_at).toLocaleDateString() : 'N/A'}</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${escapeHtml(item.reason || 'N/A')}</td>
            </tr>
        `).join('');
    }

    // Render archived suppliers
    function renderArchivedSuppliersModal(suppliers) {
        const tbody = document.getElementById('archivedSuppliersTableModal');
        if (!tbody) return;
        
        if (suppliers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-text-tertiary dark:text-gray-500">No archived suppliers found</td></tr>';
            return;
        }

        tbody.innerHTML = suppliers.map(supplier => `
            <tr class="border-b border-border-light dark:border-gray-700 hover:bg-surface-hover dark:hover:bg-gray-800">
                <td class="py-3 px-4 font-medium text-text-primary dark:text-gray-100">${escapeHtmlArchive(supplier.name || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtmlArchive(supplier.contact || supplier.contact_person || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtmlArchive(supplier.email || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtmlArchive(supplier.phone || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtmlArchive(supplier.location || supplier.address || 'N/A')}</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${supplier.archived_at ? new Date(supplier.archived_at).toLocaleDateString() : 'N/A'}</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${escapeHtmlArchive(supplier.reason || 'N/A')}</td>
            </tr>
        `).join('');
    }

    // Open Archive Modal
    function openArchiveModal() {
        const modal = document.getElementById('archiveModal');
        if (!modal) {
            console.error('Archive modal not found. Make sure archive_modal.html is included.');
            return;
        }
        
        // Close settings modal if open
        const settingsModal = document.getElementById('settingsModal');
        if (settingsModal) {
            settingsModal.classList.add('hidden');
        }
        
        setTimeout(() => {
            modal.classList.remove('hidden');
            loadArchivesModal();
        }, settingsModal ? 150 : 0);
    }

    // Make functions globally available
    window.openArchiveModal = openArchiveModal;
    window.loadArchivesModal = loadArchivesModal;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initArchiveModal);
    } else {
        initArchiveModal();
    }

})();

