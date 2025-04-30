<?php
/**
 * Database-driven version of the Resource Database Plugin
 * Retrieves data from PostgreSQL database instead of CSV
 */

// Include the database handler
require_once('database-handler.php');

// Note: Using dbPlugin_sanitize_tag_array() from helper-functions.php

/**
 * Display resources function using database
 */
function dbPlugin_display_resources_db($atts = []) {
    // Create database handler
    $dbHandler = new ResourceDatabaseHandler();
    
    // Check database connection
    if (!$dbHandler->isInitialized()) {
        return '<div class="notice notice-error">
            Database connection failed. Please check your database configuration.
            <p><a href="database-admin.php">Go to Database Admin</a></p>
        </div>';
    }
    
    // Handle search and filtering
    $searchQuery = isset($_GET['kw']) ? htmlspecialchars($_GET['kw']) : '';
    $searchTerms = array_filter(explode(' ', $searchQuery));
    
    // Handle tags from URL - handle both array and string formats
    $hasLgbtqTag = false;
    $rawTags = [];
    
    // Check if tags is a string (single tag) or array (multiple tags)
    if (isset($_GET['tags'])) {
        if (is_array($_GET['tags'])) {
            $rawTags = $_GET['tags']; 
        } else {
            // Single tag passed as string
            $rawTags = [$_GET['tags']];
        }
        
        error_log("DB Mode - Raw tags from URL before processing: " . print_r($rawTags, true));
        
        // Check for LGBTQ+ tag
        foreach ($rawTags as $tag) {
            if (stripos($tag, 'lgbtq') !== false) {
                $hasLgbtqTag = true;
                error_log("DB Mode - Found LGBTQ tag in URL: '$tag'");
            }
        }
    }
    
    // Normalize tags and handle LGBTQ+ special case
    $selectedTags = [];
    if (!empty($rawTags)) {
        $selectedTags = array_map(function($tag) {
            // Special handling for LGBTQ+ tag
            if (stripos($tag, 'lgbtq') !== false) {
                error_log("DB Mode - Normalizing LGBTQ+ tag: " . $tag);
                return 'LGBTQ+'; // Force exact format
            }
            
            return sanitize_text_field(urldecode($tag));
        }, $rawTags);
    }
    
    error_log("DB Mode - Tags after normalization: " . print_r($selectedTags, true));
    
    // Debug log tag selection
    error_log("Selected tags from URL: " . print_r($selectedTags, true));
    error_log("DB Mode - Raw URL params: " . print_r($_GET, true));
    
    // Set up pagination
    $itemsPerPage = 10;
    $currentPage = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
    
    // Get data from database
    $result = $dbHandler->getResources($searchTerms, $selectedTags, $currentPage, $itemsPerPage);
    
    if (!$result['success']) {
        return '<div class="notice notice-error">' . $result['message'] . '</div>';
    }
    
    $resources = $result['resources'];
    $totalRows = $result['total'];
    $allTags = $result['tags'];
    $totalPages = $result['totalPages'];
    
    // Start output buffering
    ob_start();

    // Display search form
    echo '<div class="resources-search-container">';
    echo '<div class="search-controls">';
    
    // Search form
    echo '<form class="search-wrapper">';
    echo '<input type="text" id="resourceSearch" name="kw" placeholder="Search database..." value="' . htmlspecialchars($searchQuery) . '">';
    echo '<button type="submit" class="search-button" aria-label="Search">';
    
    // Search icon
    echo '<img src="media/search.svg" alt="Search">';
    echo '</button>';
    echo '</form>';
    
    // Reset button
    echo '<button type="button" class="reset-button" onclick="resetFilters()">';
    echo '<span>×</span> Reset Filters';
    echo '</button>';
    echo '</div>'; // Close search-controls

    // Results count message
    $countMessage = $totalRows === count($resources) 
        ? sprintf('Showing all %d resources', $totalRows)
        : sprintf('Showing %d filtered resources (%d total)', count($resources), $totalRows);
    echo '<div class="result-count">' . htmlspecialchars($countMessage) . '</div>';

    // Display filter tags
    echo '<div class="tags-container" id="filterTags">';
    foreach ($allTags as $tag) {
        if (!empty($tag)) {
            // Check if tag is in selected tags using case-insensitive comparison
            $tagLower = strtolower($tag);
            $selectedTagsLower = array_map('strtolower', $selectedTags);
            
            // Special handling for LGBTQ+ tag
            if (stripos($tag, 'LGBTQ') !== false) {
                $isSelected = false;
                foreach ($selectedTagsLower as $selectedTag) {
                    // Compare ignoring spaces and case, but preserving the + character
                    if (str_replace(' ', '+', $tagLower) === str_replace(' ', '+', $selectedTag)) {
                        $isSelected = true;
                        break;
                    }
                }
                error_log("LGBTQ+ tag check: Tag = '$tag', isSelected = " . ($isSelected ? 'true' : 'false'));
                error_log("Selected tags (lowercase): " . implode(', ', $selectedTagsLower));
            } else {
                // Normal case for other tags
                $isSelected = in_array($tagLower, $selectedTagsLower);
            }
            
            printf(
                '<button type="button" class="tag%s" data-tag="%s" title="Click to %s filter">%s</button>',
                $isSelected ? ' selected' : '',
                htmlspecialchars($tag),
                $isSelected ? 'remove from' : 'add to',
                htmlspecialchars($tag)
            );
        }
    }
    echo '</div>'; // Close tags-container
    echo '</div>'; // Close resources-search-container

    // Display resource table
    echo '<div id="resourceTableContainer">';
    echo '<table class="csv-table">';
    echo '<thead><tr><th>Resource</th><th>Resource Description</th><th>Keywords</th></tr></thead>';
    echo '<tbody id="resourceTableBody">';

    foreach ($resources as $resource) {
        $resourceName = $resource['name'];
        $phoneNum = $resource['phone'];
        $description = $resource['description'];
        $website = $resource['website'];
        $tags = $resource['tags'];

        echo '<tr>';
        
        // Resource column with optional link
        echo '<td>';
        if (!empty($website)) {
            echo '<a href="' . htmlspecialchars($website) . '" target="_blank" rel="noopener noreferrer">' . 
                htmlspecialchars($resourceName) . '</a>';
        } else {
            echo htmlspecialchars($resourceName);
        }
        echo '</td>';
        
        // Description column with phone number
        echo '<td>';
        if (!empty($phoneNum)) {
            $phoneIconHtml = '<img src="media/phone.svg" alt="Phone" class="phone-icon">';
            $phoneNumHtml = '<div class="phone-num-container">' . $phoneIconHtml . 
                      '<span class="phone-num">' . htmlspecialchars($phoneNum) . '</span></div>';
            echo $phoneNumHtml;
        }
        echo '<div class="description">' . htmlspecialchars($description) . '</div>';
        echo '</td>';
        
        // Keywords column with tags
        echo '<td><div class="tag-container">';
        foreach ($tags as $tag) {
            if (!empty($tag)) {
                // Check if tag is in selected tags using case-insensitive comparison
                $tagLower = strtolower($tag);
                $selectedTagsLower = array_map('strtolower', $selectedTags);
                
                // Special handling for LGBTQ+ tag in the table display
                if (stripos($tag, 'LGBTQ') !== false) {
                    $isSelected = false;
                    foreach ($selectedTagsLower as $selectedTag) {
                        // Compare ignoring spaces and case, but preserving the + character
                        if (str_replace(' ', '+', $tagLower) === str_replace(' ', '+', $selectedTag)) {
                            $isSelected = true;
                            break;
                        }
                    }
                } else {
                    // Normal case for other tags
                    $isSelected = in_array($tagLower, $selectedTagsLower);
                }
                
                printf(
                    '<button type="button" class="table-tag%s" data-tag="%s" title="Click to %s filter">%s</button>',
                    $isSelected ? ' selected' : '',
                    htmlspecialchars($tag),
                    $isSelected ? 'remove from' : 'add to',
                    htmlspecialchars($tag)
                );
            }
        }
        echo '</div></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    // Display "No results" message if no resources found
    if (empty($resources)) {
        echo '<div class="no-results">No resources found matching your criteria.</div>';
    }
    
    // Pagination controls
    if ($totalPages > 1) {
        echo '<div class="pagination" role="navigation" aria-label="Resource list pagination">';
        
        // Previous page button
        if ($currentPage > 1) {
            printf(
                '<button type="button" class="page-np" onclick="changePage(%d)" aria-label="Go to previous page">&lt;</button>',
                $currentPage - 1
            );
        }
        
        // Page numbers
        $paginationRange = 2;
        for ($i = max(1, $currentPage - $paginationRange); 
            $i <= min($totalPages, $currentPage + $paginationRange); $i++) {
            if ($i == $currentPage) {
                printf(
                    '<span class="current-page" aria-current="page">%d</span>',
                    $i
                );
            } else {
                printf(
                    '<button type="button" class="page-n" onclick="changePage(%d)" aria-label="Go to page %d">%d</button>',
                    $i, $i, $i
                );
            }
        }
        
        // Next page button
        if ($currentPage < $totalPages) {
            printf(
                '<button type="button" class="page-np" onclick="changePage(%d)" aria-label="Go to next page">&gt;</button>',
                $currentPage + 1
            );
        }
        
        echo '</div>';
    }
    
    echo '</div>'; // Close resourceTableContainer
    
    return ob_get_clean();
}