/**
 * Database Plugin
 * Main frontend script for tag selection and filtering
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    applyTagSelectionFromUrl();
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
            console.log('Tag clicked:', tagValue);
            console.log('Tag element classes:', e.target.className);
            console.log('Tag is selected:', e.target.classList.contains('selected'));
            
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
    
    // Log for debugging
    console.log('Toggle tag:', tagValue);
    console.log('Current tags:', currentTags);
    console.log('Raw URL params:', window.location.search);
    
    // Special handling for LGBTQ+ tag (+ is a special character in URLs)
    // We need to decode it properly as "+" gets converted to space in URL parameters
    const normalizedCurrentTags = currentTags.map(tag => {
        // Replace any spaces that might have been "+" in the original tag
        return decodeURIComponent(tag).replace(/\s+/g, '+');
    });
    
    console.log('Normalized tags:', normalizedCurrentTags);
    
    // Handle special case for LGBTQ+ tag
    const normalizedTagValue = tagValue.includes('LGBTQ') ? tagValue.replace(/\s+/g, '+') : tagValue;
    console.log('Normalized tag value:', normalizedTagValue);
    
    // Check using case-insensitive comparison
    const tagIndex = normalizedCurrentTags.findIndex(
        tag => tag.toLowerCase() === normalizedTagValue.toLowerCase()
    );
    console.log('Tag index:', tagIndex);
    
    if (tagIndex > -1) {
        // Remove tag if already selected
        console.log('Removing tag...');
        const newTags = currentTags.filter((tag, index) => index !== tagIndex);
        urlParams.delete('tags');
        newTags.forEach(tag => urlParams.append('tags', tag));
    } else {
        // Add tag if not selected
        console.log('Adding tag...');
        urlParams.append('tags', tagValue);
    }
    
    const searchInput = document.getElementById('resourceSearch');
    if (searchInput && searchInput.value.trim()) {
        const sanitizedValue = encodeURIComponent(searchInput.value.trim());
        urlParams.set('kw', sanitizedValue);
    }
    
    // Remove page parameter to go back to first page
    urlParams.delete('pg');
    
    // Preserve the mode parameter if it exists
    const mode = document.querySelector('.mode-button.active');
    if (mode && mode.textContent.toLowerCase().includes('database')) {
        urlParams.set('mode', 'db');
    }
    
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
    
    // Preserve the mode parameter if it exists
    const mode = document.querySelector('.mode-button.active');
    if (mode && mode.textContent.toLowerCase().includes('database')) {
        urlParams.set('mode', 'db');
    }
    
    // Update URL
    const updatedUrl = `${window.location.pathname}?${urlParams.toString()}`;
    window.location.href = updatedUrl;
}

/**
 * Reset all filters
 * 
 * This function resets all applied filters but preserves the current mode.
 * It removes search keywords, selected tags, and pagination states, but keeps
 * the database/CSV mode selection.
 */
function resetFilters() {
    const mode = document.querySelector('.mode-button.active');
    if (mode && mode.textContent.toLowerCase().includes('database')) {
        window.location.href = window.location.pathname + '?mode=db';
    } else {
        window.location.href = window.location.pathname;
    }
}

/**
 * Change page function for pagination
 * 
 * Sets the page parameter in the URL and navigates to the new page
 * while preserving other query parameters like search terms, tags, and display mode.
 */
function changePage(pageNum) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('pg', pageNum);
    
    // Ensure mode is preserved if we're in database mode
    if (!urlParams.has('mode')) {
        const mode = document.querySelector('.mode-button.active');
        if (mode && mode.textContent.toLowerCase().includes('database')) {
            urlParams.set('mode', 'db');
        }
    }
    
    const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
    window.location.href = newUrl;
}

/**
 * Apply tag selection from URL
 * 
 * This function explicitly applies the 'selected' class to tag elements
 * based on the tags parameter in the URL. This ensures tags are visually
 * highlighted when they're active in the filtering.
 */
function applyTagSelectionFromUrl() {
    // Get selected tags from URL
    const urlParams = new URLSearchParams(window.location.search);
    const selectedTags = urlParams.getAll('tags');
    
    if (!selectedTags || selectedTags.length === 0) {
        return; // No tags selected
    }
    
    console.log('Selected tags from URL:', selectedTags);
    
    // Process each tag in the DOM
    const allTagElements = document.querySelectorAll('.tag, .table-tag');
    
    allTagElements.forEach(tagElement => {
        const elementTagValue = tagElement.getAttribute('data-tag');
        if (!elementTagValue) return;
        
        // Special case for LGBTQ+
        const isLgbtqTag = elementTagValue.toLowerCase().includes('lgbtq');
        
        // Check if this tag is in the selected tags list
        const isSelected = selectedTags.some(urlTag => {
            const decodedUrlTag = decodeURIComponent(urlTag);
            
            // Special handling for LGBTQ+
            if (isLgbtqTag) {
                console.log('Comparing LGBTQ+ tag:', 
                    elementTagValue, '==', decodedUrlTag,
                    'Result:', elementTagValue.toLowerCase() === decodedUrlTag.toLowerCase().replace(' ', '+'));
                return elementTagValue.toLowerCase() === decodedUrlTag.toLowerCase().replace(' ', '+');
            }
            
            // Standard case-insensitive comparison for other tags
            return elementTagValue.toLowerCase() === decodedUrlTag.toLowerCase();
        });
        
        // Apply or remove 'selected' class
        if (isSelected) {
            tagElement.classList.add('selected');
            console.log('Applied selected class to tag:', elementTagValue);
        } else {
            tagElement.classList.remove('selected');
        }
    });
}