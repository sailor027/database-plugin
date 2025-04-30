/**
 * Database Plugin
 * Main frontend script for tag selection and filtering
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
});

/**
 * Initialize all event listeners
 * This function adds event listeners to all interactive elements
 */
function initializeEventListeners() {
    // Initialize tag selection - use event delegation for better performance
    document.addEventListener('click', function(e) {
        // Check if clicked element is a tag button
        if (e.target && (e.target.classList.contains('tag') || e.target.classList.contains('table-tag'))) {
            const tagValue = e.target.getAttribute('data-tag');
            if (tagValue) {
                toggleTagFilter(tagValue);
            }
        }
    });
    
    // Search form submission
    const searchForm = document.querySelector('.search-wrapper');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSearch();
        });
    }
}

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
    // Update URL and reload
    const newUpdatedUrl = `${window.location.pathname}?${urlParams.toString()}`;
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
            const sanitizedValue = encodeURIComponent(searchInput.value.trim());
            urlParams.set('kw', sanitizedValue);
        } else {
            urlParams.delete('kw');
        }
    }
    
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

/**
 * Change page function for pagination
 * 
 * Sets the page parameter in the URL and navigates to the new page
 * while preserving other query parameters like search terms and tags.
 */
function changePage(pageNum) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('pg', pageNum);
    const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
    window.location.href = newUrl;
}