// Global variables
let currentForm = null;

// DOM Content Loaded Event
document.addEventListener('DOMContentLoaded', function() {
    initializeFormHandlers();
    setupValidationHelpers();
});

// Initialize form handlers
function initializeFormHandlers() {
    // Higher Education Form
    const heForm = document.getElementById('heDataForm');
    if (heForm) {
        heForm.addEventListener('submit', handleHEFormSubmit);
    }

    // ECZ Form
    const eczForm = document.getElementById('eczDataForm');
    if (eczForm) {
        eczForm.addEventListener('submit', handleECZFormSubmit);
    }

    // TEVETA Form
    const tevetaForm = document.getElementById('tevetaDataForm');
    if (tevetaForm) {
        tevetaForm.addEventListener('submit', handleTEVETAFormSubmit);
    }

    // Setup NRC/Passport validation
    setupIdentificationValidation();
}

// Show upload form based on type
function showUploadForm(type) {
    hideUploadForms();
    currentForm = type;

    const formContainer = document.getElementById(type + 'UploadForm');
    if (formContainer) {
        formContainer.style.display = 'block';
        formContainer.scrollIntoView({ behavior: 'smooth' });
    }
}

// Hide all upload forms
function hideUploadForms() {
    const forms = ['heUploadForm', 'eczUploadForm', 'tevetaUploadForm'];
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.style.display = 'none';
        }
    });
    currentForm = null;
}

// Handle Higher Education form submission
async function handleHEFormSubmit(event) {
    event.preventDefault();

    if (!validateHEForm()) {
        return;
    }

    const formData = new FormData(event.target);
    formData.append('action', 'upload_he_data');

    try {
        showLoadingState('heDataForm');
        const response = await fetch(window.location.href + '?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            event.target.reset();
            clearValidationErrors('heDataForm');
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'Network error: ' + error.message);
    } finally {
        hideLoadingState('heDataForm');
    }
}

// Handle ECZ form submission
async function handleECZFormSubmit(event) {
    event.preventDefault();

    if (!validateECZForm()) {
        return;
    }

    const formData = new FormData(event.target);
    formData.append('action', 'upload_ecz_data');

    try {
        showLoadingState('eczDataForm');
        const response = await fetch(window.location.href + '?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            event.target.reset();
            clearValidationErrors('eczDataForm');
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'Network error: ' + error.message);
    } finally {
        hideLoadingState('eczDataForm');
    }
}

// Handle TEVETA form submission
async function handleTEVETAFormSubmit(event) {
    event.preventDefault();

    if (!validateTEVETAForm()) {
        return;
    }

    const formData = new FormData(event.target);
    formData.append('action', 'upload_teveta_data');

    try {
        showLoadingState('tevetaDataForm');
        const response = await fetch(window.location.href + '?ajax=1', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', result.message);
            event.target.reset();
            clearValidationErrors('tevetaDataForm');
        } else {
            showAlert('error', result.message);
        }
    } catch (error) {
        showAlert('error', 'Network error: ' + error.message);
    } finally {
        hideLoadingState('tevetaDataForm');
    }
}

// Validate Higher Education form
function validateHEForm() {
    const form = document.getElementById('heDataForm');
    const errors = [];

    // Required field validation
    const requiredFields = [
        'student_id', 'certificate_no', 'first_name', 'last_name',
        'gender', 'programme_of_study', 'year_awarded', 'institution_id'
    ];

    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            errors.push(field);
            showFieldError(input, 'This field is required');
        } else {
            clearFieldError(input);
        }
    });

    // NRC/Passport validation
    const nrcField = form.querySelector('[name="nrc_number"]');
    const passportField = form.querySelector('[name="passport_no"]');

    if (!nrcField.value.trim() && !passportField.value.trim()) {
        showFieldError(nrcField, 'Either NRC or Passport number is required');
        showFieldError(passportField, 'Either NRC or Passport number is required');
        errors.push('identification');
    } else {
        clearFieldError(nrcField);
        clearFieldError(passportField);
    }

    // NRC format validation
    if (nrcField.value.trim() && !validateNRCFormat(nrcField.value.trim())) {
        showFieldError(nrcField, 'Invalid NRC format (e.g., 123456/10/1)');
        errors.push('nrc_format');
    }

    return errors.length === 0;
}

// Validate ECZ form
function validateECZForm() {
    const form = document.getElementById('eczDataForm');
    const errors = [];

    // Required field validation
    const requiredFields = [
        'candidate_id', 'certificate_no', 'first_name', 'last_name',
        'gender', 'programme', 'year_awarded', 'institution_id'
    ];

    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            errors.push(field);
            showFieldError(input, 'This field is required');
        } else {
            clearFieldError(input);
        }
    });

    // NRC/Passport validation
    const nrcField = form.querySelector('[name="nrc_number"]');
    const passportField = form.querySelector('[name="passport_no"]');

    if (!nrcField.value.trim() && !passportField.value.trim()) {
        showFieldError(nrcField, 'Either NRC or Passport number is required');
        showFieldError(passportField, 'Either NRC or Passport number is required');
        errors.push('identification');
    } else {
        clearFieldError(nrcField);
        clearFieldError(passportField);
    }

    // NRC format validation
    if (nrcField.value.trim() && !validateNRCFormat(nrcField.value.trim())) {
        showFieldError(nrcField, 'Invalid NRC format (e.g., 123456/10/1)');
        errors.push('nrc_format');
    }

    return errors.length === 0;
}

// Validate TEVETA form
function validateTEVETAForm() {
    const form = document.getElementById('tevetaDataForm');
    const errors = [];

    // Required field validation
    const requiredFields = [
        'candidate_id', 'certificate_no', 'first_name', 'last_name',
        'gender', 'programme', 'year_awarded', 'institution_id'
    ];

    requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input.value.trim()) {
            errors.push(field);
            showFieldError(input, 'This field is required');
        } else {
            clearFieldError(input);
        }
    });

    // NRC/Passport validation
    const nrcField = form.querySelector('[name="nrc_number"]');
    const passportField = form.querySelector('[name="passport_no"]');

    if (!nrcField.value.trim() && !passportField.value.trim()) {
        showFieldError(nrcField, 'Either NRC or Passport number is required');
        showFieldError(passportField, 'Either NRC or Passport number is required');
        errors.push('identification');
    } else {
        clearFieldError(nrcField);
        clearFieldError(passportField);
    }

    // NRC format validation
    if (nrcField.value.trim() && !validateNRCFormat(nrcField.value.trim())) {
        showFieldError(nrcField, 'Invalid NRC format (e.g., 123456/10/1)');
        errors.push('nrc_format');
    }

    return errors.length === 0;
}

// Validate NRC format
function validateNRCFormat(nrc) {
    // Zambian NRC format: 123456/10/1
    const nrcPattern = /^\d{6}\/\d{2}\/\d{1}$/;
    return nrcPattern.test(nrc);
}

// Setup identification validation helpers
function setupIdentificationValidation() {
    const forms = ['heDataForm', 'eczDataForm', 'tevetaDataForm'];

    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (!form) return;

        const nrcField = form.querySelector('[name="nrc_number"]');
        const passportField = form.querySelector('[name="passport_no"]');

        if (nrcField && passportField) {
            nrcField.addEventListener('input', function() {
                if (this.value.trim()) {
                    clearFieldError(passportField);
                }
            });

            passportField.addEventListener('input', function() {
                if (this.value.trim()) {
                    clearFieldError(nrcField);
                }
            });

            nrcField.addEventListener('blur', function() {
                if (this.value.trim() && !validateNRCFormat(this.value.trim())) {
                    showFieldError(this, 'Invalid NRC format (e.g., 123456/10/1)');
                }
            });
        }
    });
}

// Setup additional validation helpers
function setupValidationHelpers() {
    // Real-time validation for specific fields
    const studentIdFields = document.querySelectorAll('[name="student_id"], [name="candidate_id"]');
    studentIdFields.forEach(field => {
        field.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9\/\-]/g, '');
        });
    });

    // Certificate number validation
    const certificateFields = document.querySelectorAll('[name="certificate_no"]');
    certificateFields.forEach(field => {
        field.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9\/\-]/g, '');
        });
    });

    // Name fields - only letters, spaces, hyphens, apostrophes
    const nameFields = document.querySelectorAll('[name="first_name"], [name="last_name"], [name="other_names"]');
    nameFields.forEach(field => {
        field.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '');
        });
    });
}

// Show field error
function showFieldError(field, message) {
    clearFieldError(field);

    field.classList.add('error');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;

    field.parentNode.appendChild(errorDiv);
}

// Clear field error
function clearFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Clear all validation errors in a form
function clearValidationErrors(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const errorFields = form.querySelectorAll('.error');
    errorFields.forEach(field => {
        field.classList.remove('error');
    });

    const errorMessages = form.querySelectorAll('.field-error');
    errorMessages.forEach(error => {
        error.remove();
    });
}

// Show loading state
function showLoadingState(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    }
}

// Hide loading state
function hideLoadingState(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Upload Record';
    }
}

// Show alert message
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;

    // Clear existing alerts
    alertContainer.innerHTML = '';

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;

    const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
    alertDiv.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    alertContainer.appendChild(alertDiv);

    // Auto-remove success alerts after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Scroll to alert
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Utility function to capitalize names
function capitalizeName(input) {
    return input.value.toLowerCase().split(' ').map(word =>
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
}

// Setup name capitalization
document.addEventListener('DOMContentLoaded', function() {
    const nameFields = document.querySelectorAll('[name="first_name"], [name="last_name"], [name="other_names"]');
    nameFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value.trim()) {
                this.value = capitalizeName(this);
            }
        });
    });
});

// Form reset confirmation
document.addEventListener('DOMContentLoaded', function() {
    const resetButtons = document.querySelectorAll('button[type="reset"]');
    resetButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                e.preventDefault();
            } else {
                // Clear validation errors when resetting
                const form = this.closest('form');
                if (form) {
                    clearValidationErrors(form.id);
                }
            }
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S to save current form
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        if (currentForm) {
            const activeForm = document.getElementById(currentForm + 'DataForm');
            if (activeForm) {
                const submitBtn = activeForm.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
        }
    }

    // Escape to hide forms
    if (e.key === 'Escape') {
        hideUploadForms();
    }
});

// Export functions for global access
window.showUploadForm = showUploadForm;
window.hideUploadForms = hideUploadForms;