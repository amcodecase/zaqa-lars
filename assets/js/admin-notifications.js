// Admin Notifications Management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    initializeNotifications();
    loadSentNotifications();
    updateSelectedCount();
});

// Initialize event listeners
function initializeNotifications() {
    // Notification form submission
    const notificationForm = document.getElementById('notificationForm');
    if (notificationForm) {
        notificationForm.addEventListener('submit', handleNotificationSubmission);
    }

    // Quick notification form
    const quickForm = document.getElementById('quickNotificationForm');
    if (quickForm) {
        quickForm.addEventListener('submit', handleQuickNotificationSubmission);
    }

    // Recipient type change
    const recipientType = document.getElementById('recipient_type');
    if (recipientType) {
        recipientType.addEventListener('change', handleRecipientTypeChange);
    }

    // Select all checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', handleSelectAll);
    }

    // Individual checkboxes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('user-checkbox')) {
            updateSelectedCount();
        }
    });

    // Bulk notification button
    const sendBulkBtn = document.getElementById('sendBulkNotification');
    if (sendBulkBtn) {
        sendBulkBtn.addEventListener('click', handleBulkNotification);
    }

    // Preview button
    const previewBtn = document.getElementById('previewBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', showPreview);
    }

    // Filter form
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            applyFilters();
        });
    }

    // Category and role filters for dynamic loading
    const categorySelect = document.getElementById('category_id');
    if (categorySelect) {
        categorySelect.addEventListener('change', loadRolesByCategory);
    }
}

// Handle notification form submission
function handleNotificationSubmission(e) {
    e.preventDefault();

    const recipientType = document.getElementById('recipient_type').value;
    const message = document.getElementById('message').value.trim();
    const priority = document.getElementById('priority').value;

    if (!message) {
        showAlert('Please enter a message', 'error');
        return;
    }

    if (message.length > 1000) {
        showAlert('Message must be less than 1000 characters', 'error');
        return;
    }

    let formData = new FormData();
    formData.append('action', 'send_notification');
    formData.append('message', message);
    formData.append('priority', priority);

    switch (recipientType) {
        case 'individual':
            const recipientId = document.getElementById('recipient_id').value;
            if (!recipientId) {
                showAlert('Please select a recipient', 'error');
                return;
            }
            formData.append('recipient_id', recipientId);
            sendSingleNotification(formData);
            break;

        case 'bulk':
            const selectedUsers = getSelectedUsers();
            if (selectedUsers.length === 0) {
                showAlert('Please select at least one user', 'error');
                return;
            }
            formData = new FormData();
            formData.append('action', 'send_bulk_notification');
            formData.append('message', message);
            formData.append('priority', priority);
            formData.append('user_ids', JSON.stringify(selectedUsers));
            sendBulkNotificationRequest(formData);
            break;

        case 'category':
        case 'role':
        case 'all':
            sendToGroup(recipientType, message, priority);
            break;
    }
}

// Handle quick notification form submission
function handleQuickNotificationSubmission(e) {
    e.preventDefault();

    const recipientId = document.getElementById('quickRecipientId').value;
    const message = document.getElementById('quickMessage').value.trim();
    const priority = document.getElementById('quickPriority').value;

    if (!message) {
        showAlert('Please enter a message', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'send_notification');
    formData.append('recipient_id', recipientId);
    formData.append('message', message);
    formData.append('priority', priority);

    sendSingleNotification(formData);
}

// Send single notification
function sendSingleNotification(formData) {
    showLoading(true);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                showAlert(data.message, 'success');
                resetNotificationForm();
                closeQuickModal();
                loadSentNotifications();
                updateNotificationStats();
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            showAlert('Error sending notification', 'error');
            console.error('Error:', error);
        });
}

// Send bulk notification
function sendBulkNotificationRequest(formData) {
    showLoading(true);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                showAlert(data.message, 'success');
                resetNotificationForm();
                clearSelectedUsers();
                loadSentNotifications();
                updateNotificationStats();
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            showAlert('Error sending bulk notification', 'error');
            console.error('Error:', error);
        });
}

// Send to group (category/role/all)
function sendToGroup(type, message, priority) {
    showLoading(true);

    // First get users based on type
    let params = new URLSearchParams();
    params.append('action', 'get_users_for_notification');

    if (type === 'category') {
        const categoryId = document.getElementById('category_id').value;
        if (!categoryId) {
            showAlert('Please select a category', 'error');
            showLoading(false);
            return;
        }
        params.append('category_id', categoryId);
    } else if (type === 'role') {
        const roleId = document.getElementById('role_id').value;
        if (!roleId) {
            showAlert('Please select a role', 'error');
            showLoading(false);
            return;
        }
        params.append('role_id', roleId);
    }

    fetch(`?ajax=1&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users.length > 0) {
                const userIds = data.users.map(user => user.id);

                const formData = new FormData();
                formData.append('action', 'send_bulk_notification');
                formData.append('message', message);
                formData.append('priority', priority);
                formData.append('user_ids', JSON.stringify(userIds));

                sendBulkNotificationRequest(formData);
            } else {
                showLoading(false);
                showAlert('No users found for the selected criteria', 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            showAlert('Error fetching users', 'error');
            console.error('Error:', error);
        });
}

// Handle recipient type change
function handleRecipientTypeChange() {
    const recipientType = document.getElementById('recipient_type').value;
    const recipientSelector = document.getElementById('recipientSelector');

    switch (recipientType) {
        case 'individual':
            recipientSelector.innerHTML = `
                <div class="dashboard-card">
                    <label for="recipient_id">Select Recipient</label>
                    <select id="recipient_id" class="form-control">
                        <option value="">Choose a user...</option>
                    </select>
                </div>
            `;
            loadUsersForSelect();
            break;

        case 'bulk':
            recipientSelector.innerHTML = `
                <div class="dashboard-card">
                    <p><i class="fas fa-info-circle"></i> Select users from the table below using checkboxes</p>
                </div>
            `;
            break;

        case 'category':
            recipientSelector.innerHTML = `
                <div class="dashboard-card">
                    <label for="target_category">Select Category</label>
                    <select id="target_category" class="form-control">
                        <option value="">Choose a category...</option>
                    </select>
                </div>
            `;
            loadCategoriesForSelect();
            break;

        case 'role':
            recipientSelector.innerHTML = `
                <div class="dashboard-card">
                    <label for="target_role">Select Role</label>
                    <select id="target_role" class="form-control">
                        <option value="">Choose a role...</option>
                    </select>
                </div>
            `;
            loadRolesForSelect();
            break;

        case 'all':
            recipientSelector.innerHTML = `
                <div class="dashboard-card">
                    <p><i class="fas fa-users"></i> This will send to all users in the system</p>
                </div>
            `;
            break;
    }
}

// Load users for select dropdown
function loadUsersForSelect() {
    fetch('?ajax=1&action=get_users_for_notification')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('recipient_id');
                if (select) {
                    select.innerHTML = '<option value="">Choose a user...</option>';
                    data.users.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = `${user.first_name} ${user.last_name} (${user.email})`;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
        });
}

// Load categories for select dropdown
function loadCategoriesForSelect() {
    const categorySelect = document.getElementById('category_id');
    const targetSelect = document.getElementById('target_category');

    if (categorySelect && targetSelect) {
        targetSelect.innerHTML = categorySelect.innerHTML;
    }
}

// Load roles for select dropdown
function loadRolesForSelect() {
    const roleSelect = document.getElementById('role_id');
    const targetSelect = document.getElementById('target_role');

    if (roleSelect && targetSelect) {
        targetSelect.innerHTML = roleSelect.innerHTML;
    }
}

// Handle select all checkbox
function handleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });

    updateSelectedCount();
}

// Update selected count
function updateSelectedCount() {
    const selected = getSelectedUsers();
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = `${selected.length} users selected`;
    }
}

// Get selected users
function getSelectedUsers() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.value));
}

// Clear selected users
function clearSelectedUsers() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });

    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = false;
    }

    updateSelectedCount();
}

// Handle bulk notification
function handleBulkNotification() {
    const selectedUsers = getSelectedUsers();

    if (selectedUsers.length === 0) {
        showAlert('Please select at least one user', 'error');
        return;
    }

    const message = prompt('Enter notification message:');
    if (!message || message.trim() === '') {
        return;
    }

    const priority = prompt('Enter priority (low/medium/high):', 'medium');
    if (!['low', 'medium', 'high'].includes(priority)) {
        showAlert('Invalid priority. Use: low, medium, or high', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'send_bulk_notification');
    formData.append('message', message.trim());
    formData.append('priority', priority);
    formData.append('user_ids', JSON.stringify(selectedUsers));

    sendBulkNotificationRequest(formData);
}

// Send quick notification
function sendQuickNotification(userId) {
    const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
    const userName = userRow.querySelector('td:nth-child(2) strong').textContent;

    document.getElementById('quickRecipientId').value = userId;
    document.getElementById('quickModalTitle').textContent = `Send Notification to ${userName}`;
    document.getElementById('quickNotificationModal').style.display = 'block';
}

// Close quick modal
function closeQuickModal() {
    document.getElementById('quickNotificationModal').style.display = 'none';
    document.getElementById('quickNotificationForm').reset();
}

// Show preview
function showPreview() {
    const message = document.getElementById('message').value.trim();
    const priority = document.getElementById('priority').value;
    const recipientType = document.getElementById('recipient_type').value;

    if (!message) {
        showAlert('Please enter a message to preview', 'error');
        return;
    }

    let recipientInfo = '';
    switch (recipientType) {
        case 'individual':
            const recipientSelect = document.getElementById('recipient_id');
            recipientInfo = recipientSelect.selectedOptions[0]?.textContent || 'No recipient selected';
            break;
        case 'bulk':
            recipientInfo = `${getSelectedUsers().length} selected users`;
            break;
        case 'category':
            recipientInfo = 'All users in selected category';
            break;
        case 'role':
            recipientInfo = 'All users with selected role';
            break;
        case 'all':
            recipientInfo = 'All users';
            break;
    }

    const previewContent = `
        <div class="notification-preview">
            <div class="preview-header">
                <span class="priority-badge priority-${priority}">${priority.toUpperCase()} PRIORITY</span>
            </div>
            <div class="preview-body">
                <h4>Message:</h4>
                <p>${message}</p>
                <h4>Recipients:</h4>
                <p>${recipientInfo}</p>
                <h4>Character Count:</h4>
                <p>${message.length}/1000</p>
            </div>
        </div>
    `;

    document.getElementById('previewModalBody').innerHTML = previewContent;
    document.getElementById('previewModal').style.display = 'block';
}

// Close preview modal
function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
}

// Load sent notifications
function loadSentNotifications() {
    fetch('?ajax=1&action=get_sent_notifications')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySentNotifications(data.notifications);
            } else {
                document.getElementById('sentNotificationsContainer').innerHTML = '<p>Error loading notifications</p>';
            }
        })
        .catch(error => {
            console.error('Error loading sent notifications:', error);
            document.getElementById('sentNotificationsContainer').innerHTML = '<p>Error loading notifications</p>';
        });
}

// Display sent notifications
function displaySentNotifications(notifications) {
    const container = document.getElementById('sentNotificationsContainer');

    if (notifications.length === 0) {
        container.innerHTML = '<p>No notifications sent yet.</p>';
        return;
    }

    let html = '<div class="notifications-list">';

    notifications.forEach(notification => {
        const sentDate = new Date(notification.sent_at).toLocaleString();
        html += `
            <div class="notification-item">
                <div class="notification-header">
                    <span class="priority-badge priority-${notification.priority}">${notification.priority.toUpperCase()}</span>
                    <span class="sent-date">${sentDate}</span>
                    <button class="btn btn-sm btn-danger" onclick="deleteNotification(${notification.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="notification-recipient">
                    <strong>To:</strong> ${notification.first_name} ${notification.last_name} (${notification.email})
                    <br><small>${notification.role_name} - ${notification.category_name}</small>
                </div>
                <div class="notification-message">
                    <strong>Message:</strong> ${notification.message}
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

// Delete notification
function deleteNotification(notificationId) {
    if (!confirm('Are you sure you want to delete this notification record?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_notification');
    formData.append('notification_id', notificationId);

    fetch('?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                loadSentNotifications();
                updateNotificationStats();
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Error deleting notification', 'error');
            console.error('Error:', error);
        });
}

// Update notification stats
function updateNotificationStats() {
    fetch('?ajax=1&action=get_notification_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.stats;
                document.querySelectorAll('.stat-number').forEach((element, index) => {
                    switch (index) {
                        case 0:
                            element.textContent = stats.total_sent || 0;
                            break;
                        case 1:
                            element.textContent = stats.sent_today || 0;
                            break;
                        case 2:
                            element.textContent = stats.sent_this_week || 0;
                            break;
                        case 3:
                            element.textContent = stats.high_priority || 0;
                            break;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error updating stats:', error);
        });
}

// Reset notification form
function resetNotificationForm() {
    document.getElementById('notificationForm').reset();
    document.getElementById('recipient_type').value = 'individual';
    handleRecipientTypeChange();
}

// Apply filters
function applyFilters() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    window.location.href = `?${params.toString()}`;
}

// Clear filters
function clearFilters() {
    window.location.href = window.location.pathname;
}

// Load roles by category
function loadRolesByCategory() {
    const categoryId = document.getElementById('category_id').value;
    const roleSelect = document.getElementById('role_id');

    if (!categoryId) {
        return;
    }

    fetch(`?ajax=1&action=get_roles&category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                roleSelect.innerHTML = '<option value="">All Roles</option>';
                data.roles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.name;
                    roleSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading roles:', error);
        });
}

// Show alert messages
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${message}</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    alertContainer.appendChild(alertDiv);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// Show loading indicator
function showLoading(show) {
    const loadingElements = document.querySelectorAll('.btn');
    loadingElements.forEach(btn => {
        if (show) {
            btn.disabled = true;
            if (btn.querySelector('i')) {
                btn.querySelector('i').className = 'fas fa-spinner fa-spin';
            }
        } else {
            btn.disabled = false;
            // Restore original icons
            const icons = btn.querySelectorAll('i');
            icons.forEach(icon => {
                icon.className = icon.className.replace('fa-spinner fa-spin', '');
            });
        }
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const quickModal = document.getElementById('quickNotificationModal');
    const previewModal = document.getElementById('previewModal');

    if (event.target === quickModal) {
        closeQuickModal();
    }
    if (event.target === previewModal) {
        closePreviewModal();
    }
}