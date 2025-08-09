// Global variables
let selectedInstitutions = new Set();

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeInstitutionManagement();
});

function initializeInstitutionManagement() {
    // Initialize event listeners
    initializeEventListeners();

    // Initialize checkboxes
    initializeCheckboxes();

    // Update selected count
    updateSelectedCount();
}

function initializeEventListeners() {
    // Add Institution Form
    const addForm = document.getElementById('addInstitutionForm');
    if (addForm) {
        addForm.addEventListener('submit', handleAddInstitution);
    }

    // Edit Institution Form
    const editForm = document.getElementById('editInstitutionForm');
    if (editForm) {
        editForm.addEventListener('submit', handleEditInstitution);
    }

    // Bulk Delete Button
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', handleBulkDelete);
    }

    // Select All Checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAll);
    }

    // Individual checkboxes
    const checkboxes = document.querySelectorAll('.institution-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', handleCheckboxChange);
    });

    // Modal close events
    window.addEventListener('click', function(event) {
        const detailsModal = document.getElementById('institutionDetailsModal');
        const editModal = document.getElementById('editInstitutionModal');

        if (event.target === detailsModal) {
            closeModal();
        }
        if (event.target === editModal) {
            closeEditModal();
        }
    });
}

function initializeCheckboxes() {
    selectedInstitutions.clear();
    const checkboxes = document.querySelectorAll('.institution-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAll').checked = false;
}

// Add Institution Handler
async function handleAddInstitution(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    formData.append('action', 'add_institution');

    try {
        const response = await fetch('?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            event.target.reset();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while adding the institution.');
        console.error('Error:', error);
    }
}

// Edit Institution Handler
async function handleEditInstitution(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    formData.append('action', 'update_institution');

    try {
        const response = await fetch('?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            closeEditModal();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while updating the institution.');
        console.error('Error:', error);
    }
}

// View Institution Details
async function viewInstitutionDetails(institutionId) {
    try {
        const response = await fetch(`?ajax=1&action=get_institution_details&institution_id=${institutionId}`);
        const result = await response.json();

        if (result.success) {
            const institution = result.institution;
            document.getElementById('modalTitle').textContent = `Institution Details - ${institution.name}`;

            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="institution-details">
                    <div class="detail-row">
                        <label>Name:</label>
                        <span>${escapeHtml(institution.name)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Type:</label>
                        <span class="type-badge type-${institution.type.toLowerCase()}">${escapeHtml(institution.type)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Category:</label>
                        <span>${escapeHtml(institution.category_name)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Total Users:</label>
                        <span class="badge">${institution.user_count}</span>
                    </div>
                    <div class="detail-row">
                        <label>Created:</label>
                        <span>${new Date(institution.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
            `;

            document.getElementById('institutionDetailsModal').style.display = 'block';
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while fetching institution details.');
        console.error('Error:', error);
    }
}

// Edit Institution
async function editInstitution(institutionId) {
    try {
        const response = await fetch(`?ajax=1&action=get_institution_details&institution_id=${institutionId}`);
        const result = await response.json();

        if (result.success) {
            const institution = result.institution;

            // Populate form fields
            document.getElementById('edit_institution_id').value = institution.id;
            document.getElementById('edit_category_id').value = institution.category_id;
            document.getElementById('edit_name').value = institution.name;
            document.getElementById('edit_type').value = institution.type;

            // Show modal
            document.getElementById('editInstitutionModal').style.display = 'block';
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while fetching institution details.');
        console.error('Error:', error);
    }
}

// Delete Institution
async function deleteInstitution(institutionId) {
    if (!confirm('Are you sure you want to delete this institution? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_institution');
    formData.append('institution_id', institutionId);

    try {
        const response = await fetch('?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while deleting the institution.');
        console.error('Error:', error);
    }
}

// Handle Select All
function handleSelectAll(event) {
    const isChecked = event.target.checked;
    const checkboxes = document.querySelectorAll('.institution-checkbox');

    selectedInstitutions.clear();

    checkboxes.forEach(checkbox => {
        checkbox.checked = isChecked;
        if (isChecked) {
            selectedInstitutions.add(parseInt(checkbox.value));
        }
    });

    updateSelectedCount();
}

// Handle Individual Checkbox Change
function handleCheckboxChange(event) {
    const institutionId = parseInt(event.target.value);

    if (event.target.checked) {
        selectedInstitutions.add(institutionId);
    } else {
        selectedInstitutions.delete(institutionId);
    }

    // Update select all checkbox
    const totalCheckboxes = document.querySelectorAll('.institution-checkbox').length;
    const selectAllCheckbox = document.getElementById('selectAll');

    if (selectedInstitutions.size === totalCheckboxes) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else if (selectedInstitutions.size === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }

    updateSelectedCount();
}

// Update Selected Count
function updateSelectedCount() {
    const count = selectedInstitutions.size;
    const countElement = document.getElementById('selectedCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    if (countElement) {
        countElement.textContent = `${count} institution${count !== 1 ? 's' : ''} selected`;
    }

    if (bulkDeleteBtn) {
        bulkDeleteBtn.disabled = count === 0;
    }
}

// Handle Bulk Delete
async function handleBulkDelete() {
    if (selectedInstitutions.size === 0) {
        showAlert('warning', 'Please select institutions to delete.');
        return;
    }

    const count = selectedInstitutions.size;
    if (!confirm(`Are you sure you want to delete ${count} institution${count !== 1 ? 's' : ''}? This action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'bulk_delete');
    formData.append('institution_ids', JSON.stringify([...selectedInstitutions]));

    try {
        const response = await fetch('?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'An error occurred while deleting institutions.');
        console.error('Error:', error);
    }
}

// Modal Functions
function closeModal() {
    document.getElementById('institutionDetailsModal').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editInstitutionModal').style.display = 'none';
}

// Clear Filters
function clearFilters() {
    // Reset form fields
    document.getElementById('category_id').value = '';
    document.getElementById('type').value = '';
    document.getElementById('search').value = '';

    // Submit form to reload without filters
    document.getElementById('filterForm').submit();
}

// Alert System
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;

    // Remove existing alerts
    alertContainer.innerHTML = '';

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${escapeHtml(message)}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    `;

    alertContainer.appendChild(alertDiv);

    // Auto-remove success alerts after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Utility Functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Loading state management
function setLoading(element, isLoading) {
    if (isLoading) {
        element.disabled = true;
        element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeInstitutionManagement);
} else {
    initializeInstitutionManagement();
}