// admin-manage-users.js
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    updateSelectedCount();
});

// Initialize all event listeners
function initializeEventListeners() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    // Individual checkbox listeners
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            updateSelectAllState();
        });
    });

    // Status select listeners
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const status = this.value;
            updateStatus(userId, status);
        });
    });

    // Category select listeners
    document.querySelectorAll('.category-select').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const categoryId = this.value;
            const currentCategory = this.dataset.current;

            if (categoryId !== currentCategory) {
                updateCategory(userId, categoryId);
            }
        });
    });

    // Role select listeners
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const roleId = this.value;
            const currentRole = this.dataset.current;

            if (roleId !== currentRole) {
                updateRole(userId, roleId);
            }
        });
    });

    // Bulk action button
    const bulkActionBtn = document.getElementById('applyBulkAction');
    if (bulkActionBtn) {
        bulkActionBtn.addEventListener('click', applyBulkAction);
    }

    // Modal close functionality
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('userDetailsModal');
        if (event.target === modal) {
            closeModal();
        }
    });

    // ESC key to close modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
}

// Update user status
function updateStatus(userId, status) {
    if (!userId || !status) {
        showAlert('Invalid parameters', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('user_id', userId);
    formData.append('status', status);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                updateUserRowStatus(userId, status);
            } else {
                showAlert(data.message, 'error');
                // Revert the select to previous value
                const select = document.querySelector(`[data-user-id="${userId}"].status-select`);
                if (select) {
                    select.value = select.dataset.previous || 'pending';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error occurred', 'error');
        });
}

// Verify user email
function verifyEmail(userId) {
    if (!userId) {
        showAlert('Invalid user ID', 'error');
        return;
    }

    if (!confirm('Are you sure you want to verify this user\'s email?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'verify_email');
    formData.append('user_id', userId);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                updateUserEmailStatus(userId, true);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error occurred', 'error');
        });
}

// Update user role
function updateRole(userId, roleId) {
    if (!userId || !roleId) {
        showAlert('Invalid parameters', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_role');
    formData.append('user_id', userId);
    formData.append('role_id', roleId);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Update the data-current attribute
                const select = document.querySelector(`[data-user-id="${userId}"].role-select`);
                if (select) {
                    select.dataset.current = roleId;
                }
            } else {
                showAlert(data.message, 'error');
                // Revert the select to previous value
                const select = document.querySelector(`[data-user-id="${userId}"].role-select`);
                if (select) {
                    select.value = select.dataset.current;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error occurred', 'error');
        });
}

// Update user category
function updateCategory(userId, categoryId) {
    if (!userId || !categoryId) {
        showAlert('Invalid parameters', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_category');
    formData.append('user_id', userId);
    formData.append('category_id', categoryId);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Update the data-current attribute
                const select = document.querySelector(`[data-user-id="${userId}"].category-select`);
                if (select) {
                    select.dataset.current = categoryId;
                }
                // Refresh roles for this category
                updateRolesForCategory(userId, categoryId);
            } else {
                showAlert(data.message, 'error');
                // Revert the select to previous value
                const select = document.querySelector(`[data-user-id="${userId}"].category-select`);
                if (select) {
                    select.value = select.dataset.current;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error occurred', 'error');
        });
}

// Update roles dropdown when category changes
function updateRolesForCategory(userId, categoryId) {
    fetch(`?ajax=1&action=get_roles&category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const roleSelect = document.querySelector(`[data-user-id="${userId}"].role-select`);
                if (roleSelect) {
                    roleSelect.innerHTML = '';
                    data.roles.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.id;
                        option.textContent = role.name;
                        roleSelect.appendChild(option);
                    });
                    // Select the first role by default
                    if (data.roles.length > 0) {
                        roleSelect.value = data.roles[0].id;
                        roleSelect.dataset.current = data.roles[0].id;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error fetching roles:', error);
        });
}

// View user details in modal
function viewUserDetails(userId) {
    if (!userId) {
        showAlert('Invalid user ID', 'error');
        return;
    }

    // Show loading in modal
    const modal = document.getElementById('userDetailsModal');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');

    modalTitle.textContent = 'Loading...';
    modalBody.innerHTML = '<div class="loading">Loading user details...</div>';
    modal.style.display = 'block';

    fetch(`?ajax=1&action=get_user_details&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserDetails(data.user);
            } else {
                modalBody.innerHTML = `<div class="error">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="error">Network error occurred</div>';
        });
}

// Display user details in modal
function displayUserDetails(user) {
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');

    modalTitle.textContent = `User Details: ${user.first_name} ${user.last_name}`;

    const approvedBy = user.approver_first_name
        ? `${user.approver_first_name} ${user.approver_last_name}`
        : 'N/A';

    const approvedAt = user.approved_at
        ? new Date(user.approved_at).toLocaleString()
        : 'N/A';

    modalBody.innerHTML = `
        <div class="user-details">
            <div class="detail-section">
                <h4>Personal Information</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Full Name:</label>
                        <span>${user.first_name} ${user.last_name} ${user.other_names || ''}</span>
                    </div>
                    <div class="detail-item">
                        <label>Email:</label>
                        <span>${user.email}</span>
                    </div>
                    <div class="detail-item">
                        <label>Phone:</label>
                        <span>${user.phone}</span>
                    </div>
                    <div class="detail-item">
                        <label>NRC:</label>
                        <span>${user.nrc || 'N/A'}</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Account Information</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Category:</label>
                        <span>${user.category_name}</span>
                    </div>
                    <div class="detail-item">
                        <label>Role:</label>
                        <span>${user.role_name}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status:</label>
                        <span class="status-badge status-${user.status}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span>
                    </div>
                    <div class="detail-item">
                        <label>Email Verified:</label>
                        <span class="status-badge ${user.email_verified ? 'status-active' : 'status-rejected'}">
                            ${user.email_verified ? 'Verified' : 'Unverified'}
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Account History</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Created At:</label>
                        <span>${new Date(user.created_at).toLocaleString()}</span>
                    </div>
                    <div class="detail-item">
                        <label>Updated At:</label>
                        <span>${new Date(user.updated_at).toLocaleString()}</span>
                    </div>
                    <div class="detail-item">
                        <label>Approved By:</label>
                        <span>${approvedBy}</span>
                    </div>
                    <div class="detail-item">
                        <label>Approved At:</label>
                        <span>${approvedAt}</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-actions">
                <button class="btn btn-primary" onclick="editUser(${user.id})">
                    <i class="fas fa-edit"></i> Edit User
                </button>
                ${!user.email_verified ? `
                    <button class="btn btn-info" onclick="verifyEmail(${user.id}); closeModal();">
                        <i class="fas fa-envelope-check"></i> Verify Email
                    </button>
                ` : ''}
                ${user.status === 'pending' ? `
                    <button class="btn btn-success" onclick="updateStatus(${user.id}, 'active'); closeModal();">
                        <i class="fas fa-check"></i> Approve User
                    </button>
                ` : ''}
            </div>
        </div>
    `;
}

// Edit user (placeholder - can be expanded)
function editUser(userId) {
    closeModal();
    showAlert('Edit functionality coming soon', 'info');
    // You can implement a full edit modal here
}

// Apply bulk actions
function applyBulkAction() {
    const selectedUsers = getSelectedUsers();
    const bulkAction = document.getElementById('bulkAction').value;

    if (selectedUsers.length === 0) {
        showAlert('Please select users first', 'warning');
        return;
    }

    if (!bulkAction) {
        showAlert('Please select an action', 'warning');
        return;
    }

    const actionText = {
        'activate': 'activate',
        'suspend': 'suspend',
        'verify_emails': 'verify emails for'
    };

    if (!confirm(`Are you sure you want to ${actionText[bulkAction]} ${selectedUsers.length} user(s)?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'bulk_action');
    formData.append('user_ids', JSON.stringify(selectedUsers));
    formData.append('bulk_action', bulkAction);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Refresh the page or update the table
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Network error occurred', 'error');
        });
}

// Get selected user IDs
function getSelectedUsers() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

// Update selected count display
function updateSelectedCount() {
    const selectedCount = getSelectedUsers().length;
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = `${selectedCount} user${selectedCount !== 1 ? 's' : ''} selected`;
    }
}

// Update select all state
function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const checkedCheckboxes = document.querySelectorAll('.user-checkbox:checked');

    if (selectAllCheckbox) {
        if (checkedCheckboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedCheckboxes.length === userCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
            selectAllCheckbox.checked = false;
        }
    }
}

// Update user row status visually
function updateUserRowStatus(userId, status) {
    const userRow = document.querySelector(`[data-user-id="${userId}"]`);
    if (userRow) {
        const statusSelect = userRow.querySelector('.status-select');
        if (statusSelect) {
            statusSelect.value = status;
        }
    }
}

// Update user email status visually
function updateUserEmailStatus(userId, verified) {
    const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (userRow) {
        const emailCell = userRow.cells[7]; // Email verified column
        if (emailCell) {
            emailCell.innerHTML = verified
                ? '<span class="status-badge status-active">Verified</span>'
                : '<span class="status-badge status-rejected">Unverified</span>';
        }
    }
}

// Show alert messages
function showAlert(message, type = 'info') {
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

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// Close modal
function closeModal() {
    const modal = document.getElementById('userDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Clear all filters
function clearFilters() {
    const form = document.getElementById('filterForm');
    if (form) {
        form.reset();
        // Redirect to clear URL parameters
        window.location.href = window.location.pathname;
    }
}

// Utility function to format dates
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
}

// Utility function to capitalize first letter
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}