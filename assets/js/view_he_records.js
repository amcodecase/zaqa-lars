// HE Records JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Search form validation
    const searchForm = document.querySelector('.search-form');
    const searchType = document.getElementById('search_type');
    const searchTerm = document.getElementById('search_term');

    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            if (!validateSearch()) {
                e.preventDefault();
            }
        });
    }

    // Real-time search validation
    if (searchTerm && searchType) {
        searchTerm.addEventListener('input', validateSearchInput);
        searchType.addEventListener('change', validateSearchInput);
    }

    function validateSearch() {
        const type = searchType.value;
        const term = searchTerm.value.trim();

        if (!type) {
            alert('Please select a search criteria');
            return false;
        }

        if (!term) {
            alert('Please enter a search term');
            return false;
        }

        // Special validation for NRC
        if (type === 'nrc') {
            const nrcDigits = term.replace(/[^0-9]/g, '');
            if (nrcDigits.length < 6) {
                alert('NRC must contain at least 6 digits');
                return false;
            }
        }

        return true;
    }

    function validateSearchInput() {
        const type = searchType.value;
        const term = searchTerm.value.trim();

        // Clear any previous styling
        searchTerm.classList.remove('invalid-input');

        if (type === 'nrc' && term) {
            const nrcDigits = term.replace(/[^0-9]/g, '');
            if (nrcDigits.length > 0 && nrcDigits.length < 6) {
                searchTerm.classList.add('invalid-input');
                searchTerm.title = 'NRC must contain at least 6 digits';
            } else {
                searchTerm.removeAttribute('title');
            }
        }
    }
});

// Screenshot functionality
function takeScreenshot(button) {
    const recordCard = button.closest('.record-card');
    const loadingIndicator = document.getElementById('loadingIndicator');

    if (!recordCard) {
        alert('Error: Could not find record card');
        return;
    }

    // Show loading indicator
    if (loadingIndicator) {
        loadingIndicator.style.display = 'block';
    }

    // Temporarily hide the screenshot button for cleaner image
    const screenshotBtn = recordCard.querySelector('.screenshot-btn');
    if (screenshotBtn) {
        screenshotBtn.style.display = 'none';
    }

    // Configure html2canvas options
    const options = {
        backgroundColor: '#ffffff',
        scale: 2, // Higher quality
        useCORS: true,
        allowTaint: true,
        scrollX: 0,
        scrollY: 0,
        width: recordCard.offsetWidth,
        height: recordCard.offsetHeight
    };

    html2canvas(recordCard, options).then(function(canvas) {
        // Show the button again
        if (screenshotBtn) {
            screenshotBtn.style.display = '';
        }

        // Hide loading indicator
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }

        // Create download link
        const link = document.createElement('a');

        // Get record details for filename
        const recordId = recordCard.getAttribute('data-record-id');
        const nameElement = recordCard.querySelector('.record-header h4');
        const studentIdElement = recordCard.querySelector('.detail-row:nth-child(1) .value');

        let filename = 'he-record';
        if (recordId) {
            filename += `-${recordId}`;
        }
        if (studentIdElement) {
            const studentId = studentIdElement.textContent.trim();
            filename += `-${studentId}`;
        }
        if (nameElement) {
            const name = nameElement.textContent.trim().replace(/[^a-zA-Z0-9]/g, '-');
            filename += `-${name}`;
        }
        filename += `-${new Date().getTime()}.png`;

        // Set up download
        link.download = filename;
        link.href = canvas.toDataURL('image/png');

        // Trigger download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Show success message
        showMessage('Screenshot saved successfully!', 'success');

    }).catch(function(error) {
        console.error('Screenshot error:', error);

        // Show the button again
        if (screenshotBtn) {
            screenshotBtn.style.display = '';
        }

        // Hide loading indicator
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }

        alert('Error taking screenshot. Please try again.');
    });
}

// Show message function
function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type} floating-message`;
    messageDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> ${message}`;

    // Add to page
    document.body.appendChild(messageDiv);

    // Position it
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.minWidth = '300px';
    messageDiv.style.opacity = '0';
    messageDiv.style.transform = 'translateY(-20px)';
    messageDiv.style.transition = 'all 0.3s ease';

    // Animate in
    setTimeout(() => {
        messageDiv.style.opacity = '1';
        messageDiv.style.transform = 'translateY(0)';
    }, 100);

    // Remove after 3 seconds
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 300);
    }, 3000);
}

// Record card hover effects
document.addEventListener('DOMContentLoaded', function() {
    const recordCards = document.querySelectorAll('.record-card');

    recordCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            this.style.transition = 'all 0.3s ease';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
});