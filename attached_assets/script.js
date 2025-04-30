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
            const tagValue = button.getAttribute('data-tag');
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
    const decodedTags = currentTags.map(tag => decodeURIComponent(tag));
    const tagIndex = decodedTags.findIndex(tag => tag === tagValue);
    
    if (tagIndex > -1) {
        // Remove tag if already selected
        const newTags = currentTags.filter((tag, index) => index !== tagIndex);
        urlParams.delete('tags');
        newTags.forEach(tag => urlParams.append('tags', tag));
    } else {
        // Add tag if not selected
        urlParams.append('tags', tagValue);
    }
    
    const searchInput = document.getElementById('resourceSearch');
    if (searchInput && searchInput.value.trim()) {
        const sanitizedValue = encodeURIComponent(searchInput.value.trim());
        urlParams.set('kw', sanitizedValue);
    }
    
    // Remove page parameter to go back to first page
    urlParams.delete('pg');
    // Update URL without reloading
    const newUpdatedUrl = `${window.location.pathname}?${urlParams.toString()}`;
    history.pushState(null, '', newUpdatedUrl);
    window.location.href = newUpdatedUrl;
}

/**
 * Submit search with current filters
 */
function submitSearch() {
    const urlParams = new URLSearchParams(window.location.search);
    
    const searchInput = document.getElementById('resourceSearch');
    if (searchInput) {
        if (searchInput.value.trim()) {
            const sanitizedValue = encodeURIComponent(searchValue);
            urlParams.set('kw', sanitizedValue);
        } else {
            urlParams.delete('kw');
        }
    }
    
    // Keep tag parameters
    const currentTags = urlParams.getAll('tags');
    
    // Remove page parameter to go back to first page
    urlParams.delete('pg');
    
    // Update URL
    const updatedUrl = `${window.location.pathname}?${urlParams.toString()}`;
    window.location.href = updatedUrl;
}

/**
 * Reset all filters
 * 
 * This function resets all applied filters by navigating to the base URL of the current page.
 * It removes any query parameters and hash fragments from the URL, effectively clearing
 * all search keywords, selected tags, and pagination states. The page is reloaded to reflect
 * the reset state.
 */
function resetFilters() {
    window.location.href = window.location.pathname;
}