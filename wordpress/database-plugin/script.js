/**
 * Database Plugin
 * Main frontend script for tag selection and filtering
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tag selection
    const tagButtons = document.querySelectorAll('.tag, .table-tag');
    
    // Add click event to tag buttons
    tagButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const tagValue = this.getAttribute('data-tag');
            toggleTagFilter(tagValue);
        });
    });
    
    // Search form submission
    const searchForm = document.querySelector('.search-wrapper');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSearch();
        });
    }
});

/**
 * Toggle tag selection and update URL
 */
function toggleTagFilter(tagValue) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentTags = urlParams.getAll('tags');
    
    // Check if tag is already selected
    const tagIndex = currentTags.findIndex(tag => decodeURIComponent(tag) === tagValue);
    
    if (tagIndex > -1) {
        // Remove tag if already selected
        const newTags = currentTags.filter((tag, index) => index !== tagIndex);
        urlParams.delete('tags');
        newTags.forEach(tag => urlParams.append('tags', tag));
    } else {
        // Add tag if not selected
        urlParams.append('tags', tagValue);
    }
    
    // Keep search parameter if present
    const searchInput = document.getElementById('resourceSearch');
    if (searchInput && searchInput.value.trim()) {
        urlParams.set('kw', searchInput.value.trim());
    }
    
    // Remove page parameter to go back to first page
    urlParams.delete('pg');
    
    // Update URL
    const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
    window.location.href = newUrl;
}

/**
 * Submit search with current filters
 */
function submitSearch() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Get search parameter
    const searchInput = document.getElementById('resourceSearch');
    if (searchInput) {
        const searchValue = searchInput.value.trim();
        if (searchValue) {
            urlParams.set('kw', searchValue);
        } else {
            urlParams.delete('kw');
        }
    }
    
    // Keep tag parameters
    const currentTags = urlParams.getAll('tags');
    
    // Remove page parameter to go back to first page
    urlParams.delete('pg');
    
    // Update URL
    const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
    window.location.href = newUrl;
}

/**
 * Reset all filters
 */
function resetFilters() {
    window.location.href = window.location.pathname;
}