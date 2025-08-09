// Global variables
let currentTable = '';
let currentRecordId = null;

// DOM elements
const searchForm = document.getElementById('searchForm');
const tableSelect = document.getElementById('table_select');
const searchType = document.getElementById('search_type');
const searchValue = document.getElementById('search_value');
const clearSearchBtn = document.getElementById('clearSearch');
const resultsSection = document.getElementById('resultsSection');
const resultsTitle = document.getElementById('resultsTitle');
const resultsCount = document.getElementById('resultsCount');
const resultsTable = document.getElementById('resultsTable');
const resultsTableHead = document.getElementById('resultsTableHead');
const resultsTableBody = document.getElementById('resultsTableBody');
const noResults = document.getElementById('noResults');
const loadingIndicator = document.getElementById('loadingIndicator');

// Modal elements
const recordModal = document.getElementById('recordModal');
const modalTitle = document.getElementById('modalTitle');
const modalBody = document.getElementById('modalBody');
const editRecordBtn = document.getElementById('editRecordBtn');
const deleteRecordBtn = document.getElementById('deleteRecordBtn');

// Edit modal elements
const editModal = document.getElementById('editModal');
const editModalTitle = document.getElementById('editModalTitle');
const editFormFields = document.getElementById('editFormFields');
const saveRecordBtn = document.getElementById('saveRecordBtn');

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    updateSearchTypeOptions();
});

function setupEventListeners() {
    searchForm.addEventListener('submit', handleSearch);
    clearSearchBtn.addEventListener('click', clearSearch);
    tableSelect.addEventListener('change', updateSearchTypeOptions);
    editRecordBtn.addEventListener('click', showEditModal);
    deleteRecordBtn.addEventListener('click', deleteRecord);
    saveRecordBtn.addEventListener('click', saveRecord);

    // Modal click outside to close
    window.addEventListener('click', function(event) {
        if (event.target === recordModal) {
            closeModal();
        }
        if (event.target === editModal) {
            closeEditModal();
        }
    });
}

function updateSearchTypeOptions() {
    const table = tableSelect.value;
    const currentValue = searchType.value;

    // Clear current options except the first one
    searchType.innerHTML = '<option value="">Select Search Type</option>';

    if (table) {
        const baseOptions = [
            { value: 'certificate_no', text: 'Certificate Number' },
            { value: 'nrc_number', text: 'NRC Number' },
            { value: 'passport_no', text: 'Passport Number' },
            { value: 'name', text: 'Name (First/Last)' }
        ];

        // Add table-specific ID option
        if (table === 'he_data') {
            baseOptions.unshift({ value: 'student_id', text: 'Student ID' });
        } else {
            baseOptions.unshift({ value: 'candidate_id', text: 'Candidate ID' });
        }

        baseOptions.forEach(option => {
            const optElement = document.createElement('option');
            optElement.value = option.value;
            optElement.textContent = option.text;
            if (option.value === currentValue) {
                optElement.selected = true;
            }
            searchType.appendChild(optElement);
        });
    }
}

function handleSearch(event) {
    event.preventDefault();

    const formData = new FormData(searchForm);

    if (!formData.get('table') || !formData.get('search_type') || !formData.get('search_value')) {
        showAlert('Please fill in all required search fields', 'error');
        return;
    }

    searchRecords(formData);
}

function searchRecords(formData) {
    showLoading(true);
    hideResults();

    formData.append('action', 'search_records');

    fetch(window.location.href + '?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            showLoading(false);

            if (data.success) {
                currentTable = data.table;
                displayResults(data.records, data.table);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            showAlert('Search failed: ' + error.message, 'error');
        });
}

function displayResults(records, table) {
    if (records.length === 0) {
        showNoResults();
        return;
    }

    // Update results info
    resultsCount.textContent = `${records.length} record${records.length > 1 ? 's' : ''} found`;

    // Generate table headers based on table type
    const headers = getTableHeaders(table);
    resultsTableHead.innerHTML = '';

    const headerRow = document.createElement('tr');
    headers.forEach(header => {
        const th = document.createElement('th');
        th.textContent = header.label;
        headerRow.appendChild(th);
    });

    // Add actions column
    const actionsHeader = document.createElement('th');
    actionsHeader.textContent = 'Actions';
    actionsHeader.className = 'actions-column';
    headerRow.appendChild(actionsHeader);

    resultsTableHead.appendChild(headerRow);

    // Generate table body
    resultsTableBody.innerHTML = '';

    records.forEach(record => {
        const row = document.createElement('tr');

        headers.forEach(header => {
            const td = document.createElement('td');
            const value = record[header.field];

            if (header.field === 'created_at') {
                td.textContent = formatDateTime(value);
            } else if (header.field === 'added_by_name') {
                td.textContent = `${record.added_by_name} ${record.added_by_lastname}`;
            } else {
                td.textContent = value || '-';
            }

            row.appendChild(td);
        });

        // Add actions column
        const actionsCell = document.createElement('td');
        actionsCell.className = 'actions-column';
        actionsCell.innerHTML = `
            <button class="btn btn-sm btn-primary" onclick="viewRecord(${record.id})">
                <i class="fas fa-eye"></i> View
            </button>
        `;
        row.appendChild(actionsCell);

        resultsTableBody.appendChild(row);
    });

    showResults();
}

function getTableHeaders(table) {
    const baseHeaders = [
        { field: 'certificate_no', label: 'Certificate No' },
        { field: 'first_name', label: 'First Name' },
        { field: 'last_name', label: 'Last Name' },
        { field: 'gender', label: 'Gender' },
        { field: 'nrc_number', label: 'NRC Number' },
        { field: 'year_awarded', label: 'Year' },
        { field: 'institution_name', label: 'Institution' },
        { field: 'added_by_name', label: 'Added By' },
        { field: 'created_at', label: 'Created' }
    ];

    // Add table-specific first column
    if (table === 'he_data') {
        baseHeaders.unshift({ field: 'student_id', label: 'Student ID' });
    } else {
        baseHeaders.unshift({ field: 'candidate_id', label: 'Candidate ID' });
    }

    return baseHeaders;
}

function viewRecord(recordId) {
    if (!currentTable || !recordId) {
        showAlert('Invalid record selection', 'error');
        return;
    }

    currentRecordId = recordId;

    const formData = new FormData();
    formData.append('action', 'get_record_details');
    formData.append('table', currentTable);
    formData.append('record_id', recordId);

    fetch(window.location.href + '?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecordDetails(data.record);
                showModal();
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Failed to load record: ' + error.message, 'error');
        });
}

function displayRecordDetails(record) {
    const tableType = getTableTypeLabel(currentTable);
    modalTitle.textContent = `${tableType} Record Details`;

    let html = '<div class="record-details">';

    // Basic Information
    html += '<div class="detail-section">';
    html += '<h4><i class="fas fa-info-circle"></i> Basic Information</h4>';

    if (currentTable === 'he_data') {
        html += `<p><strong>Student ID:</strong> ${record.student_id || '-'}</p>`;
        html += `<p><strong>Programme of Study:</strong> ${record.programme_of_study || '-'}</p>`;
    } else {
        html += `<p><strong>Candidate ID:</strong> ${record.candidate_id || '-'}</p>`;
        html += `<p><strong>Programme:</strong> ${record.programme || '-'}</p>`;
        if (record.level) {
            html += `<p><strong>Level:</strong> ${record.level}</p>`;
        }
        if (currentTable === 'teveta_data') {
            if (record.trade_code) {
                html += `<p><strong>Trade Code:</strong> ${record.trade_code}</p>`;
            }
            if (record.sub_institution) {
                html += `<p><strong>Sub Institution:</strong> ${record.sub_institution}</p>`;
            }
        }
    }

    html += `<p><strong>Certificate Number:</strong> ${record.certificate_no || '-'}</p>`;
    html += `<p><strong>Year Awarded:</strong> ${record.year_awarded || '-'}</p>`;
    html += `<p><strong>Institution:</strong> ${record.institution_name || '-'}</p>`;
    html += '</div>';

    // Personal Information
    html += '<div class="detail-section">';
    html += '<h4><i class="fas fa-user"></i> Personal Information</h4>';
    html += `<p><strong>Full Name:</strong> ${record.first_name} ${record.last_name} ${record.other_names || ''}</p>`;
    html += `<p><strong>Gender:</strong> ${record.gender || '-'}</p>`;
    html += `<p><strong>NRC Number:</strong> ${record.nrc_number || '-'}</p>`;
    html += `<p><strong>Passport Number:</strong> ${record.passport_no || '-'}</p>`;
    html += '</div>';

    // System Information
    html += '<div class="detail-section">';
    html += '<h4><i class="fas fa-cog"></i> System Information</h4>';
    html += `<p><strong>Added By:</strong> ${record.added_by_name} ${record.added_by_lastname}</p>`;
    html += `<p><strong>Created:</strong> ${formatDateTime(record.created_at)}</p>`;
    html += `<p><strong>Record ID:</strong> #${record.id}</p>`;
    html += '</div>';

    html += '</div>';

    modalBody.innerHTML = html;

    // Show edit and delete buttons
    editRecordBtn.style.display = 'inline-block';
    deleteRecordBtn.style.display = 'inline-block';
}

function showEditModal() {
    if (!currentRecordId || !currentTable) {
        showAlert('No record selected for editing', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'get_record_details');
    formData.append('table', currentTable);
    formData.append('record_id', currentRecordId);

    fetch(window.location.href + '?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEditForm(data.record);
                closeModal();
                editModal.style.display = 'block';
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Failed to load record for editing: ' + error.message, 'error');
        });
}

function displayEditForm(record) {
    const tableType = getTableTypeLabel(currentTable);
    editModalTitle.textContent = `Edit ${tableType} Record`;

    let html = '<div class="edit-form-grid">';

    // Generate form fields based on table type
    const fields = getEditableFields(currentTable);

    fields.forEach(field => {
        html += '<div class="form-group">';
        html += `<label for="edit_${field.name}">${field.label}${field.required ? ' <span class="required">*</span>' : ''}</label>`;

        if (field.type === 'select') {
            html += `<select id="edit_${field.name}" name="${field.name}" class="form-control"${field.required ? ' required' : ''}>`;
            if (field.options) {
                field.options.forEach(option => {
                    const selected = record[field.name] === option.value ? ' selected' : '';
                    html += `<option value="${option.value}"${selected}>${option.text}</option>`;
                });
            }
            html += '</select>';
        } else if (field.type === 'year') {
            html += `<select id="edit_${field.name}" name="${field.name}" class="form-control"${field.required ? ' required' : ''}>`;
            html += '<option value="">Select Year</option>';
            for (let year = new Date().getFullYear(); year >= 1950; year--) {
                const selected = record[field.name] == year ? ' selected' : '';
                html += `<option value="${year}"${selected}>${year}</option>`;
            }
            html += '</select>';
        } else if (field.type === 'institution') {
            html += `<select id="edit_${field.name}" name="${field.name}" class="form-control"${field.required ? ' required' : ''}>`;
            html += '<option value="">Select Institution</option>';
            window.institutions.forEach(institution => {
                const selected = record[field.name] == institution.id ? ' selected' : '';
                html += `<option value="${institution.id}"${selected}>${institution.name}</option>`;
            });
            html += '</select>';
        } else {
            const value = record[field.name] || '';
            html += `<input type="${field.type || 'text'}" id="edit_${field.name}" name="${field.name}" 
                     class="form-control" value="${value}"${field.required ? ' required' : ''}>`;
        }

        html += '</div>';
    });

    html += '</div>';

    editFormFields.innerHTML = html;
}

function getEditableFields(table) {
    const baseFields = [
        { name: 'certificate_no', label: 'Certificate Number', required: true },
        { name: 'first_name', label: 'First Name', required: true },
        { name: 'last_name', label: 'Last Name', required: true },
        { name: 'other_names', label: 'Other Names' },
        {
            name: 'gender',
            label: 'Gender',
            type: 'select',
            required: true,
            options: [
                { value: '', text: 'Select Gender' },
                { value: 'Male', text: 'Male' },
                { value: 'Female', text: 'Female' }
            ]
        },
        { name: 'nrc_number', label: 'NRC Number' },
        { name: 'passport_no', label: 'Passport Number' },
        { name: 'year_awarded', label: 'Year Awarded', type: 'year', required: true },
        { name: 'institution_id', label: 'Institution', type: 'institution', required: true }
    ];

    // Add table-specific fields
    if (table === 'he_data') {
        baseFields.unshift(
            { name: 'student_id', label: 'Student ID', required: true },
        );
        baseFields.splice(-2, 0,
            { name: 'programme_of_study', label: 'Programme of Study', required: true }
        );
    } else {
        baseFields.unshift(
            { name: 'candidate_id', label: 'Candidate ID', required: true }
        );
        baseFields.splice(-2, 0,
            { name: 'programme', label: 'Programme', required: true }
        );

        if (table === 'ecz_data') {
            baseFields.splice(-2, 0, {
                name: 'level',
                label: 'Level',
                type: 'select',
                options: [
                    { value: '', text: 'Select Level' },
                    { value: 'Grade 7', text: 'Grade 7' },
                    { value: 'Grade 9', text: 'Grade 9' },
                    { value: 'Grade 12', text: 'Grade 12' }
                ]
            });
        } else if (table === 'teveta_data') {
            baseFields.splice(-2, 0,
                { name: 'level', label: 'Level' },
                { name: 'trade_code', label: 'Trade Code' },
                { name: 'sub_institution', label: 'Sub Institution' }
            );
        }
    }

    return baseFields;
}

function saveRecord() {
    const form = document.getElementById('editRecordForm');
    const formData = new FormData();

    // Collect form data
    const inputs = editFormFields.querySelectorAll('input, select');
    inputs.forEach(input => {
        formData.append(input.name, input.value);
    });

    formData.append('action', 'update_record');
    formData.append('table', currentTable);
    formData.append('record_id', currentRecordId);

    // Show loading state
    saveRecordBtn.disabled = true;
    saveRecordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch(window.location.href + '?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            saveRecordBtn.disabled = false;
            saveRecordBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';

            if (data.success) {
                showAlert(data.message, 'success');
                closeEditModal();
                // Refresh the search results
                if (searchForm.checkValidity()) {
                    handleSearch({ preventDefault: () => {} });
                }
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            saveRecordBtn.disabled = false;
            saveRecordBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            showAlert('Failed to save record: ' + error.message, 'error');
        });
}

function deleteRecord() {
    if (!currentRecordId || !currentTable) {
        showAlert('No record selected for deletion', 'error');
        return;
    }

    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_record');
    formData.append('table', currentTable);
    formData.append('record_id', currentRecordId);

    // Show loading state
    deleteRecordBtn.disabled = true;
    deleteRecordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    fetch(window.location.href + '?ajax=1', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            deleteRecordBtn.disabled = false;
            deleteRecordBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Record';

            if (data.success) {
                showAlert(data.message, 'success');
                closeModal();
                // Refresh the search results
                if (searchForm.checkValidity()) {
                    handleSearch({ preventDefault: () => {} });
                }
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            deleteRecordBtn.disabled = false;
            deleteRecordBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Record';
            showAlert('Failed to delete record: ' + error.message, 'error');
        });
}

function clearSearch() {
    searchForm.reset();
    hideResults();
    updateSearchTypeOptions();
    currentTable = '';
    currentRecordId = null;
}

function showModal() {
    recordModal.style.display = 'block';
}

function closeModal() {
    recordModal.style.display = 'none';
    currentRecordId = null;
}

function closeEditModal() {
    editModal.style.display = 'none';
}

function showResults() {
    resultsSection.style.display = 'block';
    noResults.style.display = 'none';
}

function hideResults() {
    resultsSection.style.display = 'none';
    noResults.style.display = 'none';
}

function showNoResults() {
    resultsSection.style.display = 'block';
    resultsTable.style.display = 'none';
    noResults.style.display = 'block';
    resultsCount.textContent = '0 records found';
}

function showLoading(show) {
    loadingIndicator.style.display = show ? 'block' : 'none';
}

function getTableTypeLabel(table) {
    switch (table) {
        case 'he_data': return 'Higher Education';
        case 'ecz_data': return 'ECZ';
        case 'teveta_data': return 'TEVETA';
        default: return 'Unknown';
    }
}

function formatDateTime(dateString) {
    if (!dateString) return '-';

    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();

    const alertClass = type === 'error' ? 'alert-danger' :
        type === 'success' ? 'alert-success' : 'alert-info';

    const alertHtml = `
        <div id="${alertId}" class="alert ${alertClass}">
            <span class="alert-message">${message}</span>
            <button type="button" class="alert-close" onclick="closeAlert('${alertId}')">&times;</button>
        </div>
    `;

    alertContainer.insertAdjacentHTML('beforeend', alertHtml);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        closeAlert(alertId);
    }, 5000);
}

function closeAlert(alertId) {
    const alert = document.getElementById(alertId);
    if (alert) {
        alert.remove();
    }
}