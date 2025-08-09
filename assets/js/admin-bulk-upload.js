// Admin Bulk Upload JavaScript
class BulkUploadManager {
    constructor() {
        this.initializeEventListeners();
        this.setupFileInputHandlers();
        this.currentUploadType = null;
    }

    initializeEventListeners() {
        // Upload option buttons
        document.querySelectorAll('.upload-option-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const dataType = e.currentTarget.dataset.type;
                this.showUploadForm(dataType);
            });
        });

        // Back buttons
        document.querySelectorAll('.back-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const formType = e.currentTarget.dataset.form;
                this.hideUploadForm(formType);
            });
        });

        // Form submissions
        document.getElementById('heBulkForm').addEventListener('submit', (e) => {
            this.handleFormSubmit(e, 'he', 'upload_bulk_he_data');
        });

        document.getElementById('eczBulkForm').addEventListener('submit', (e) => {
            this.handleFormSubmit(e, 'ecz', 'upload_bulk_ecz_data');
        });

        document.getElementById('tevetaBulkForm').addEventListener('submit', (e) => {
            this.handleFormSubmit(e, 'teveta', 'upload_bulk_teveta_data');
        });

        // Clear results button
        document.getElementById('clearResults').addEventListener('click', () => {
            this.clearResults();
        });
    }

    setupFileInputHandlers() {
        const fileInputs = document.querySelectorAll('.file-input');

        fileInputs.forEach(input => {
            const wrapper = input.closest('.file-input-wrapper');
            const infoDiv = wrapper.querySelector('.file-input-info');
            const textSpan = infoDiv.querySelector('.file-text');

            // File selection change
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    textSpan.textContent = file.name;
                    wrapper.classList.add('has-file');
                    this.validateFile(file, input);
                } else {
                    textSpan.textContent = 'Choose Excel file or drag and drop';
                    wrapper.classList.remove('has-file');
                }
            });

            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                wrapper.addEventListener(eventName, this.preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                wrapper.addEventListener(eventName, () => {
                    wrapper.classList.add('drag-over');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                wrapper.addEventListener(eventName, () => {
                    wrapper.classList.remove('drag-over');
                }, false);
            });

            wrapper.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    const changeEvent = new Event('change', { bubbles: true });
                    input.dispatchEvent(changeEvent);
                }
            }, false);
        });
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    validateFile(file, input) {
        const errors = [];
        const maxSize = 50 * 1024 * 1024; // 50MB
        const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        const allowedExtensions = ['.xlsx', '.xls'];

        // Check file size
        if (file.size > maxSize) {
            errors.push('File size exceeds 50MB limit');
        }

        // Check file type
        const fileName = file.name.toLowerCase();
        const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));

        if (!hasValidExtension && !allowedTypes.includes(file.type)) {
            errors.push('Please upload an Excel file (.xlsx or .xls)');
        }

        if (errors.length > 0) {
            this.showToast('File validation failed: ' + errors.join(', '), 'error');
            input.value = '';
            const wrapper = input.closest('.file-input-wrapper');
            wrapper.classList.remove('has-file');
            wrapper.querySelector('.file-text').textContent = 'Choose Excel file or drag and drop';
            return false;
        }

        return true;
    }

    showUploadForm(dataType) {
        // Hide all upload forms first
        this.hideAllUploadForms();

        // Show the selected form
        const formId = `${dataType}UploadForm`;
        const formElement = document.getElementById(formId);

        if (formElement) {
            formElement.style.display = 'block';
            formElement.scrollIntoView({ behavior: 'smooth' });
            this.currentUploadType = dataType;
        }
    }

    hideUploadForm(dataType) {
        const formId = `${dataType}UploadForm`;
        const formElement = document.getElementById(formId);

        if (formElement) {
            formElement.style.display = 'none';
            this.resetForm(dataType);
        }

        this.currentUploadType = null;
    }

    hideAllUploadForms() {
        const forms = ['heUploadForm', 'eczUploadForm', 'tevetaUploadForm'];
        forms.forEach(formId => {
            const element = document.getElementById(formId);
            if (element) {
                element.style.display = 'none';
            }
        });
    }

    resetForm(dataType) {
        const form = document.getElementById(`${dataType}BulkForm`);
        if (form) {
            form.reset();

            // Reset file input styling
            const wrapper = form.querySelector('.file-input-wrapper');
            if (wrapper) {
                wrapper.classList.remove('has-file');
                const textSpan = wrapper.querySelector('.file-text');
                if (textSpan) {
                    textSpan.textContent = 'Choose Excel file or drag and drop';
                }
            }
        }
    }

    async handleFormSubmit(event, dataType, action) {
        event.preventDefault();

        const form = event.target;
        const progressElement = document.getElementById(`${dataType}Progress`);
        const submitButton = form.querySelector('button[type="submit"]');

        try {
            // Validate form
            if (!this.validateForm(form)) {
                return;
            }

            // Show progress
            this.showProgress(progressElement, submitButton);

            // Prepare form data
            const formData = new FormData(form);
            formData.append('action', action);

            // Make the upload request
            const response = await this.makeUploadRequest(formData);

            if (response.success) {
                this.handleUploadSuccess(response);
                this.resetForm(dataType);
            } else {
                this.handleUploadError(response);
            }

        } catch (error) {
            this.handleUploadError({ message: error.message || 'Upload failed due to network error' });
        } finally {
            // Hide progress
            this.hideProgress(progressElement, submitButton);
        }
    }

    validateForm(form) {
        const institutionSelect = form.querySelector('select[name="institution_id"]');
        const fileInput = form.querySelector('input[name="excel_file"]');

        if (!institutionSelect.value) {
            this.showToast('Please select an institution', 'error');
            institutionSelect.focus();
            return false;
        }

        if (!fileInput.files[0]) {
            this.showToast('Please select an Excel file', 'error');
            fileInput.focus();
            return false;
        }

        return true;
    }

    async makeUploadRequest(formData) {
        const response = await fetch(window.location.href + '?ajax=1', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    handleUploadSuccess(response) {
        this.showToast(response.message, 'success');
        this.displayResults(response.details);
        this.hideAllUploadForms();
    }

    handleUploadError(response) {
        const message = response.message || 'Upload failed';
        this.showToast(message, 'error');

        if (response.error_details) {
            console.error('Upload error details:', response.error_details);
        }
    }

    showProgress(progressElement, submitButton) {
        if (progressElement) {
            progressElement.style.display = 'flex';
            const progressFill = progressElement.querySelector('.progress-fill');
            if (progressFill) {
                progressFill.style.width = '0%';
                this.animateProgress(progressFill);
            }
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        }
    }

    hideProgress(progressElement, submitButton) {
        if (progressElement) {
            progressElement.style.display = 'none';
        }

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-upload"></i> Upload Records';
        }
    }

    animateProgress(progressFill) {
        let width = 0;
        const interval = setInterval(() => {
            width += Math.random() * 15;
            if (width >= 90) {
                clearInterval(interval);
                width = 90;
            }
            progressFill.style.width = width + '%';
        }, 200);

        return interval;
    }

    displayResults(details) {
        const resultsContainer = document.getElementById('uploadResults');
        const resultsContent = document.getElementById('resultsContent');

        if (!resultsContainer || !resultsContent) return;

        const html = `
            <div class="results-summary">
                <div class="summary-stats">
                    <div class="stat-item success">
                        <i class="fas fa-check-circle"></i>
                        <div class="stat-info">
                            <span class="stat-number">${details.success_count}</span>
                            <span class="stat-label">Successful</span>
                        </div>
                    </div>
                    <div class="stat-item error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="stat-info">
                            <span class="stat-number">${details.error_count}</span>
                            <span class="stat-label">Errors</span>
                        </div>
                    </div>
                    <div class="stat-item warning">
                        <i class="fas fa-copy"></i>
                        <div class="stat-info">
                            <span class="stat-number">${details.duplicate_count}</span>
                            <span class="stat-label">Duplicates</span>
                        </div>
                    </div>
                    <div class="stat-item info">
                        <i class="fas fa-database"></i>
                        <div class="stat-info">
                            <span class="stat-number">${details.total_processed || 0}</span>
                            <span class="stat-label">Total Processed</span>
                        </div>
                    </div>
                </div>
                
                <div class="institution-info">
                    <h4><i class="fas fa-university"></i> ${details.institution}</h4>
                    <p>Category: ${details.category}</p>
                </div>
            </div>
            
            ${details.errors && details.errors.length > 0 ? this.generateErrorsList(details.errors) : ''}
        `;

        resultsContent.innerHTML = html;
        resultsContainer.style.display = 'block';
        resultsContainer.scrollIntoView({ behavior: 'smooth' });
    }

    generateErrorsList(errors) {
        if (errors.length === 0) return '';

        const errorItems = errors.slice(0, 20).map(error =>
            `<li class="error-item">${this.escapeHtml(error)}</li>`
        ).join('');

        const showingText = errors.length > 20 ?
            `<p class="error-note">Showing first 20 errors of ${errors.length} total.</p>` : '';

        return `
            <div class="errors-section">
                <h4><i class="fas fa-exclamation-triangle"></i> Errors Details</h4>
                ${showingText}
                <ul class="errors-list">
                    ${errorItems}
                </ul>
            </div>
        `;
    }

    clearResults() {
        const resultsContainer = document.getElementById('uploadResults');
        if (resultsContainer) {
            resultsContainer.style.display = 'none';
        }
    }

    showToast(message, type = 'info', duration = 5000) {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icon = this.getToastIcon(type);

        toast.innerHTML = `
            <div class="toast-content">
                <i class="${icon}"></i>
                <span class="toast-message">${this.escapeHtml(message)}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        toastContainer.appendChild(toast);

        // Auto remove after duration
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duration);

        // Add click to dismiss
        toast.addEventListener('click', () => toast.remove());
    }

    getToastIcon(type) {
        const icons = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize the upload manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new BulkUploadManager();
});

// Global functions for backward compatibility
function showUploadForm(dataType) {
    if (window.bulkUploadManager) {
        window.bulkUploadManager.showUploadForm(dataType);
    }
}

function hideUploadForms() {
    if (window.bulkUploadManager) {
        window.bulkUploadManager.hideAllUploadForms();
    }
}

// Store instance globally for debugging
window.addEventListener('load', () => {
    if (!window.bulkUploadManager) {
        window.bulkUploadManager = new BulkUploadManager();
    }
});