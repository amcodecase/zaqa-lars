/**
 * Category & Role Management JavaScript
 * Handles all frontend interactions for the admin category and role management system
 */

// Global variables
let currentCategoryId = null;
let currentRoleId = null;

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    loadInitialData();
});

/**
 * Initialize all event listeners
 */
function initializeEventListeners() {
    // Modal close events
    setupModalCloseEvents();

    // Form submission events
    setupFormSubmissionEvents();

    // Keyboard events
    setupKeyboardEvents();

    // Window click events for modal closing
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };
}

/**
 * Setup modal close events
 */
function setupModalCloseEvents() {
    const modals = ['addCategoryModal', 'editCategoryModal', 'addRoleModal', 'editRoleModal', 'categoryStatsModal', 'categoryRolesModal'];

    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            const closeBtn = modal.querySelector('.close');
            if (closeBtn) {
                closeBtn.onclick = () => closeModal(modalId);
            }
        }
    });
}

/**
 * Setup form submission events
 */
function setupFormSubmissionEvents() {
    // Add Category Form
    const addCategoryForm = document.getElementById('addCategoryForm');
    if (addCategoryForm) {
        addCategoryForm.onsubmit = handleAddCategory;
    }

    // Edit Category Form
    const editCategoryForm = document.getElementById('editCategoryForm');
    if (editCategoryForm) {
        editCategoryForm.onsubmit = handleEditCategory;
    }

    // Add Role Form
    const addRoleForm = document.getElementById('addRoleForm');
    if (addRoleForm) {
        addRoleForm.onsubmit = handleAddRole;
    }

    // Edit Role Form
    const editRoleForm = document.getElementById('editRoleForm');
    if (editRoleForm) {
        editRoleForm.onsubmit = handleEditRole;
    }
}

/**
 * Setup keyboard events
 */
function setupKeyboardEvents() {
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
}

/**
 * Load initial data
 */
function loadInitialData() {
    // Any initial data loading can be done here
    console.log('Category & Role Management initialized');
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info', duration = 5000) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${message}</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    alertContainer.appendChild(alertDiv);

    // Auto remove after duration
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, duration);
}

/**
 * Show loading spinner
 */
function showLoading(element) {
    if (element) {
        element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        element.disabled = true;
    }
}

/**
 * Hide loading spinner
 */
function hideLoading(element, originalText) {
    if (element) {
        element.innerHTML = originalText;
        element.disabled = false;
    }
}

/**
 * Make AJAX request
 */
async function makeAjaxRequest(url, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        };

        if (data && method !== 'GET') {
            options.body = new URLSearchParams(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        return result;
    } catch (error) {
        console.error('AJAX Error:', error);
        throw new Error('Network error occurred');
    }
}

/**
 * Modal Functions
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // Focus on first input
        const firstInput = modal.querySelector('input[type="text"], input[type="email"], select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        // Reset form if exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

/**
 * Category Functions
 */

// Show add category modal
function showAddCategoryModal() {
    showModal('addCategoryModal');
}

// Handle add category form submission
async function handleAddCategory(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    showLoading(submitBtn);

    try {
        const formData = new FormData(form);
        formData.append('action', 'add_category');

        const result = await makeAjaxRequest('?ajax=1', 'POST', formData);

        if (result.success) {
            showAlert(result.message, 'success');
            closeModal('addCategoryModal');
            refreshCategoriesTable();
            updateStatistics();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error adding category: ' + error.message, 'error');
    } finally {
        hideLoading(submitBtn, originalText);
    }
}

// Edit category
function editCategory(categoryId, categoryName) {
    currentCategoryId = categoryId;
    document.getElementById('editCategoryId').value = categoryId;
    document.getElementById('editCategoryName').value = categoryName;
    showModal('editCategoryModal');
}

// Handle edit category form submission
async function handleEditCategory(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    showLoading(submitBtn);

    try {
        const formData = new FormData(form);
        formData.append('action', 'update_category');

        const result = await makeAjaxRequest('?ajax=1', 'POST', formData);

        if (result.success) {
            showAlert(result.message, 'success');
            closeModal('editCategoryModal');
            refreshCategoriesTable();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error updating category: ' + error.message, 'error');
    } finally {
        hideLoading(submitBtn, originalText);
    }
}

// Delete category
function deleteCategory(categoryId, categoryName) {
    if (confirm(`Are you sure you want to delete the category "${categoryName}"?\n\nThis action cannot be undone.`)) {
        performDeleteCategory(categoryId);
    }
}

// Perform category deletion
async function performDeleteCategory(categoryId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_category');
        formData.append('category_id', categoryId);

        const result = await makeAjaxRequest('?ajax=1', 'POST', formData);

        if (result.success) {
            showAlert(result.message, 'success');
            refreshCategoriesTable();
            refreshRolesTable();
            updateStatistics();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error deleting category: ' + error.message, 'error');
    }
}

/**
 * Role Functions
 */

// Show add role modal
function showAddRoleModal(categoryId, categoryName) {
    currentCategoryId = categoryId;
    document.getElementById('roleCategoryId').value = categoryId;
    document.getElementById('roleCategoryName').value = categoryName;
    showModal('addRoleModal');
}

// Handle add role form submission
async function handleAddRole(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    showLoading(submitBtn);

    try {
        const formData = new FormData(form);
        formData.append('action', 'add_role');

        const result = await makeAjaxRequest('?ajax=1', 'POST', formData);

        if (result.success) {
            showAlert(result.message, 'success');
            closeModal('addRoleModal');
            refreshCategoriesTable();
            refreshRolesTable();
            updateStatistics();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error adding role: ' + error.message, 'error');
    } finally {
        hideLoading(submitBtn, originalText);
    }
}

// Edit role
function editRole(roleId, roleName, categoryId) {
    currentRoleId = roleId;
    document.getElementById('editRoleId').value = roleId;
    document.getElementById('editRoleName').value = roleName;
    document.getElementById('editRoleCategoryId').value = categoryId;
    showModal('editRoleModal');
}

// Handle edit role form submission
async function handleEditRole(event) {
    event.preventDefault();

    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    showLoading(submitBtn);

    try {
        const formData = new FormData(form);
        formData.append('action', 'update_role');

        const result = await makeAjaxRequest('?ajax=1', 'POST', formData);

        if (result.success) {
            showAlert(result.message, 'success');
            closeModal('editRoleModal');
            refreshCategoriesTable();
            refreshRolesTable();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error updating role: ' + error.message, 'error');
    } finally {
        hideLoading(submitBtn, originalText);
    }
}

// Delete role
function deleteRole(roleId, roleName) {
    if (confirm(`Are you sure you want to delete the role "${roleName}"?\n\nThis action cannot be undone.`)) {
        performDeleteRole(roleId);
    }
}

// Perform role deletion
async function performDeleteRole(roleId) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_role');
        formData.append('role_id', roleId);

        const result = await makeAjaxRequest('?ajax=1', 'POST', formData);

        if (result.success) {
            showAlert(result.message, 'success');
            refreshCategoriesTable();
            refreshRolesTable();
            updateStatistics();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error deleting role: ' + error.message, 'error');
    }
}

/**
 * Statistics and Data Display Functions
 */

// View category statistics
async function viewCategoryStats(categoryId) {
    try {
        const result = await makeAjaxRequest(`?ajax=1&action=get_category_stats&category_id=${categoryId}`);

        if (result.success) {
            displayCategoryStats(result);
            showModal('categoryStatsModal');
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error fetching category statistics: ' + error.message, 'error');
    }
}

// Display category statistics
function displayCategoryStats(data) {
    const { category, roles_count, users_count, status_breakdown } = data;

    document.getElementById('statsModalTitle').textContent = `${category.name} - Statistics`;

    let statusHtml = '';
    if (status_breakdown && status_breakdown.length > 0) {
        statusHtml = status_breakdown.map(stat => `
            <div class="stat-item">
                <span class="status-badge status-${stat.status}">${stat.status}</span>
                <span class="count">${stat.count}</span>
            </div>
        `).join('');
    } else {
        statusHtml = '<p>No users found in this category.</p>';
    }

    const statsHtml = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div class="stat-info">
                    <h4>${roles_count}</h4>
                    <p>Total Roles</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h4>${users_count}</h4>
                    <p>Total Users</p>
                </div>
            </div>
        </div>
        
        <div class="status-breakdown">
            <h4>User Status Breakdown</h4>
            <div class="status-list">
                ${statusHtml}
            </div>
        </div>
        
        <div class="category-details">
            <h4>Category Details</h4>
            <table class="table">
                <tr>
                    <td><strong>Category ID:</strong></td>
                    <td>${category.id}</td>
                </tr>
                <tr>
                    <td><strong>Category Name:</strong></td>
                    <td>${category.name}</td>
                </tr>
                <tr>
                    <td><strong>Total Roles:</strong></td>
                    <td>${roles_count}</td>
                </tr>
                <tr>
                    <td><strong>Total Users:</strong></td>
                    <td>${users_count}</td>
                </tr>
            </table>
        </div>
    `;

    document.getElementById('categoryStatsBody').innerHTML = statsHtml;
}

// View category roles
async function viewCategoryRoles(categoryId) {
    try {
        const result = await makeAjaxRequest(`?ajax=1&action=get_roles_by_category&category_id=${categoryId}`);

        if (result.success) {
            displayCategoryRoles(result.roles, categoryId);
            showModal('categoryRolesModal');
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Error fetching category roles: ' + error.message, 'error');
    }
}

// Display category roles
function displayCategoryRoles(roles, categoryId) {
    const categoryName = document.querySelector(`[data-category-id="${categoryId}"] td:first-child strong`);
    const categoryTitle = categoryName ? categoryName.textContent : 'Category';

    document.getElementById('rolesModalTitle').textContent = `${categoryTitle} - Roles`;

    let rolesHtml = '';

    if (roles && roles.length > 0) {
        rolesHtml = `
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Role Name</th>
                            <th>Users Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${roles.map(role => `
                            <tr>
                                <td><strong>${escapeHtml(role.name)}</strong></td>
                                <td>
                                    <span class="badge">${role.user_count}</span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editRole(${role.id}, '${escapeHtml(role.name)}', ${role.category_id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    ${role.user_count == 0 ? `
                                        <button class="btn btn-sm btn-danger" onclick="deleteRole(${role.id}, '${escapeHtml(role.name)}')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    ` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } else {
        rolesHtml = `
            <div class="empty-state">
                <i class="fas fa-user-tag fa-3x"></i>
                <h3>No Roles Found</h3>
                <p>This category doesn't have any roles yet.</p>
                <button class="btn btn-primary" onclick="closeModal('categoryRolesModal'); showAddRoleModal(${categoryId}, '${categoryTitle}');">
                    <i class="fas fa-plus"></i> Add First Role
                </button>
            </div>
        `;
    }

    document.getElementById('categoryRolesBody').innerHTML = rolesHtml;
}

/**
 * Table Refresh Functions
 */

// Refresh categories table
function refreshCategoriesTable() {
    // Since we're using server-side rendering, we'll reload the page
    // In a more advanced implementation, this would make an AJAX call to get updated data
    location.reload();
}

// Refresh roles table
function refreshRolesTable() {
    // Since we're using server-side rendering, we'll reload the page
    // In a more advanced implementation, this would make an AJAX call to get updated data
    location.reload();
}

// Update statistics
function updateStatistics() {
    // This would typically make an AJAX call to update the statistics
    // For now, we'll rely on page reload
}

/**
 * Utility Functions
 */

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Validate form data
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });

    return isValid;
}

// Show confirmation dialog
function showConfirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Search and Filter Functions
 */

// Search categories
function searchCategories(searchTerm) {
    const rows = document.querySelectorAll('#categoriesTableBody tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        const categoryName = row.querySelector('td:first-child').textContent.toLowerCase();
        if (categoryName.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Search roles
function searchRoles(searchTerm) {
    const rows = document.querySelectorAll('#rolesTableBody tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        const roleName = row.querySelector('td:first-child').textContent.toLowerCase();
        const categoryName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();

        if (roleName.includes(term) || categoryName.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * Export/Import Functions (for future enhancement)
 */

// Export categories and roles data
function exportData() {
    // This would export the data to CSV or Excel format
    showAlert('Export functionality coming soon!', 'info');
}

// Import categories and roles data
function importData() {
    // This would allow importing data from CSV or Excel format
    showAlert('Import functionality coming soon!', 'info');
}

/**
 * Keyboard Shortcuts
 */
document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + N = New Category
    if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
        event.preventDefault();
        showAddCategoryModal();
    }

    // Escape = Close modals
    if (event.key === 'Escape') {
        closeAllModals();
    }
});

/**
 * Accessibility Improvements
 */

// Add ARIA labels and improve keyboard navigation
function improveAccessibility() {
    // Add proper ARIA labels to buttons and modals
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        if (!button.getAttribute('aria-label') && button.title) {
            button.setAttribute('aria-label', button.title);
        }
    });

    // Ensure modals have proper ARIA attributes
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
    });
}

// Initialize accessibility improvements when DOM is ready
document.addEventListener('DOMContentLoaded', improveAccessibility);

// Auto-save form data to prevent data loss
function setupAutoSave() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');

        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const key = `autosave_${form.id}_${input.name}`;
                localStorage.setItem(key, input.value);
            });
        });
    });
}

// Restore auto-saved form data
function restoreAutoSavedData() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');

        inputs.forEach(input => {
            const key = `autosave_${form.id}_${input.name}`;
            const savedValue = localStorage.getItem(key);

            if (savedValue && input.type !== 'hidden') {
                input.value = savedValue;
            }
        });
    });
}

// Clear auto-saved data after successful form submission
function clearAutoSavedData(formId) {
    const form = document.getElementById(formId);
    if (form) {
        const inputs = form.querySelectorAll('input, textarea, select');

        inputs.forEach(input => {
            const key = `autosave_${formId}_${input.name}`;
            localStorage.removeItem(key);
        });
    }
}

// Initialize auto-save functionality
document.addEventListener('DOMContentLoaded', function() {
    setupAutoSave();
    restoreAutoSavedData();
});

console.log('Category & Role Management System loaded successfully!');