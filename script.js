/**
 * Database Plugin
 * Main frontend script for tag selection and filtering
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    // Mark the selected tags based on URL parameters
    applyTagSelectionFromUrl();
    
    // Special case for LGBTQ+ tag - handle it directly
    handleLgbtqTagSelection();
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
    let currentTags = [];
    
    // Special handling for LGBTQ+ tag to ensure it's always treated consistently
    const isLgbtqTag = tagValue.toUpperCase().includes('LGBTQ');
    let normalizedValue = tagValue;
    
    // Force LGBTQ+ tag value to be consistent
    if (isLgbtqTag) {
        normalizedValue = 'LGBTQ+';
    }
    
    // Extract all current tags from URL
    try {
        for (const [key, value] of urlParams.entries()) {
            if (key === 'tags') {
                currentTags.push(value);
            }
        }
    } catch(e) {
        console.error('Error parsing URL parameters', e);
        currentTags = [];
    }
    
    // Log for debugging
    console.log('Toggle tag:', normalizedValue);
    console.log('Current tags:', currentTags);
    console.log('Raw URL params:', window.location.search);
    
    // Remove all tags from URL
    urlParams.delete('tags');
    
    // Check if the tag is already selected
    let tagFound = false;
    let newTags = [];
    
    // Process existing tags
    for (const tag of currentTags) {
        let normalizedCurrentTag = tag;
        
        // Special handling for LGBTQ+ tag
        if (normalizedCurrentTag.toUpperCase().includes('LGBTQ')) {
            normalizedCurrentTag = 'LGBTQ+';
        }
        
        // If this is the tag we're toggling, mark it as found but don't add it to new tags
        if (normalizedCurrentTag.toLowerCase() === normalizedValue.toLowerCase()) {
            tagFound = true;
        } else {
            newTags.push(normalizedCurrentTag);
        }
    }
    
    // If tag wasn't found in current tags, add it
    if (!tagFound) {
        newTags.push(normalizedValue);
    }
    
    // Add all tags back to URL
    for (const tag of newTags) {
        urlParams.append('tags', tag);
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
            // Also set a data attribute as a fallback mechanism
            tagElement.setAttribute('data-selected', 'true');
            console.log('Applied selected class to tag:', elementTagValue);
        } else {
            tagElement.classList.remove('selected');
            tagElement.removeAttribute('data-selected');
        }
    });
}

/**
 * Special handler for LGBTQ+ tag
 * 
 * This function directly looks for the LGBTQ+ tag in the URL and
 * applies highlighting to any matching tags in the DOM.
 */
function handleLgbtqTagSelection() {
    const url = window.location.href;
    const hasLgbtqTag = url.includes('LGBTQ%2B') || url.includes('lgbtq%2B');
    
    if (!hasLgbtqTag) {
        console.log('No LGBTQ+ tag in URL');
        return;
    }
    
    console.log('LGBTQ+ tag found in URL, applying direct selection');
    
    // Find all LGBTQ+ tag elements
    const allTagElements = document.querySelectorAll('.tag, .table-tag');
    
    allTagElements.forEach(tagElement => {
        const tagValue = tagElement.getAttribute('data-tag');
        if (!tagValue) return;
        
        if (tagValue.toUpperCase() === 'LGBTQ+') {
            console.log('Found LGBTQ+ tag element, forcing selection state');
            
            // Apply both class and data attribute for maximum compatibility
            tagElement.classList.add('selected');
            tagElement.setAttribute('data-selected', 'true');
            
            // Also add direct inline style for additional force
            tagElement.style.backgroundColor = 'var(--grn2)';
            tagElement.style.color = 'white';
            tagElement.style.fontWeight = 'bold';
            tagElement.style.border = '2px solid var(--org)';
            tagElement.style.transform = 'translateY(-2px)';
            tagElement.style.boxShadow = '0 3px 8px rgba(0,0,0,0.15)';
        }
    });
    
    // Add a small delay to ensure styles are applied after page load
    setTimeout(() => {
        const lgbtqTags = document.querySelectorAll('[data-tag="LGBTQ+"]');
        lgbtqTags.forEach(tag => {
            console.log('Re-applying styles to LGBTQ+ tag');
            tag.classList.add('selected');
            tag.setAttribute('data-selected', 'true');
        });
    }, 500);
}