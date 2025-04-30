<?php
/**
 * Simplified version of the Database Plugin for demo purposes
 */

/**
 * Sanitize and decode tag array
 */
function dbPlugin_sanitize_tag_array($tags) {
    if (!is_array($tags)) {
        return [];
    }
    
    $result = array_map(function($tag) {
        // Decode the URL and handle special case for LGBTQ+
        $decoded = urldecode($tag);
        
        // Special handling for LGBTQ+ tag - spaces might be '+' in the original tag
        if (stripos($decoded, 'lgbtq') !== false) {
            // Convert spaces back to + for LGBTQ+ case
            $decoded = str_replace(' ', '+', $decoded);
            error_log("CSV Mode - Fixed LGBTQ+ tag: '$decoded'");
        }
        
        return $decoded;
    }, $tags);
    
    error_log("CSV Mode - Sanitized tags: " . implode(", ", $result));
    return $result;
}

/**
 * Display resources function (simplified for demo)
 */
function dbPlugin_display_resources($atts = []) {
    // Check file access
    if (!file_exists(RESOURCE_FILE) || !is_readable(RESOURCE_FILE)) {
        return '<div class="notice notice-error">Resource file not found or not readable</div>';
    }

    // Handle search and filtering
    $searchQuery = isset($_GET['kw']) ? htmlspecialchars($_GET['kw']) : '';
    $searchTerms = array_filter(explode(' ', $searchQuery));
    
    // Check if LGBTQ+ tag is in the URL
    $hasLgbtqTag = false;
    if (isset($_GET['tags']) && is_array($_GET['tags'])) {
        foreach ($_GET['tags'] as $tag) {
            if (stripos($tag, 'lgbtq') !== false) {
                $hasLgbtqTag = true;
                error_log("CSV Mode - Found LGBTQ tag in URL: '$tag'");
                
                // Ensure "+" is preserved if present
                $_GET['tags'] = array_map(function($t) {
                    if (stripos($t, 'lgbtq') !== false) {
                        return 'LGBTQ+'; // Force exact format
                    }
                    return $t;
                }, $_GET['tags']);
            }
        }
    }
    
    // Get selected tags
    $selectedTags = isset($_GET['tags']) ? dbPlugin_sanitize_tag_array($_GET['tags']) : [];
    
    // Debug log the selected tags
    error_log("CSV Mode - Selected tags from URL: " . print_r($selectedTags, true));
    error_log("CSV Mode - Raw URL params: " . print_r($_GET, true));

    // Initialize counters and arrays
    $totalRows = 0;
    $filteredRows = [];
    $allTags = [];

    // Read and process data
    $fileHandle = fopen(RESOURCE_FILE, 'r');

    if ($fileHandle === false) {
        return '<div class="notice notice-error">Failed to open resource file</div>';
    }
    
    try {
        // Read headers
        $headers = fgetcsv($fileHandle);
        
        // Process data rows
        while (($row = fgetcsv($fileHandle)) !== false) {
            // Skip commented rows
            if (isset($row[0]) && strpos($row[0], '#') === 0) {
                continue;
            }
            
            // Process tags if row has keywords
            if (isset($row[3])) {
                $rowTags = array_map('trim', explode(',', $row[3]));
                foreach ($rowTags as $tag) {
                    if (!empty($tag) && !in_array($tag, $allTags)) {
                        $allTags[] = $tag;
                    }
                }
            }

            // Check if row matches search terms - search all columns
            $matchesSearch = empty($searchTerms) || array_reduce($searchTerms, function($carry, $term) use ($row) {
                return $carry && stripos(implode(' ', $row), $term) !== false;
            }, true);
            
            // Check if row has ALL selected tags in the keywords column only (index 3)
            $matchesTags = empty($selectedTags);
            if (!empty($selectedTags) && isset($row[3])) {
                $rowTags = array_map('trim', explode(',', $row[3]));
                
                // Normalize tags for proper comparison
                $rowTagsNormalized = array_map(function($tag) {
                    return strtolower(str_replace(' ', '+', $tag));
                }, $rowTags);
                
                $selectedTagsNormalized = array_map(function($tag) {
                    return strtolower(str_replace(' ', '+', $tag));
                }, $selectedTags);
                
                // Debug log for LGBTQ+ tag
                if (stripos($row[3], 'LGBTQ') !== false) {
                    error_log("LGBTQ+ resource found: " . $row[0]);
                    error_log("Row tags normalized: " . implode(', ', $rowTagsNormalized));
                    error_log("Selected tags normalized: " . implode(', ', $selectedTagsNormalized));
                }
                
                // Check if ALL selected tags are in the row tags
                $matchCount = 0;
                foreach ($selectedTagsNormalized as $selectedTag) {
                    // Special handling for LGBTQ+
                    if (stripos($selectedTag, 'lgbtq') !== false) {
                        foreach ($rowTagsNormalized as $rowTag) {
                            if (stripos($rowTag, 'lgbtq') !== false) {
                                $matchCount++;
                                break;
                            }
                        }
                    } 
                    // Standard tag comparison
                    else if (in_array($selectedTag, $rowTagsNormalized)) {
                        $matchCount++;
                    }
                }
                
                $matchesTags = ($matchCount === count($selectedTagsNormalized));
                
                // Explicit debug for LGBTQ+ filtering
                if ($hasLgbtqTag && stripos($row[3], 'LGBTQ') !== false) {
                    error_log("LGBTQ+ resource match result: " . ($matchesTags ? 'TRUE' : 'FALSE') . " for " . $row[0]);
                }
            }
            
            // Add matching rows to filtered results
            if ($matchesSearch && $matchesTags) {
                $filteredRows[] = $row;
                $totalRows++;
            }
        }
    } catch (Exception $e) {
        fclose($fileHandle);
        return '<div class="notice notice-error">Error processing data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    fclose($fileHandle);
    
    // Sort tags alphabetically
    sort($allTags);

    // Set up pagination
    $itemsPerPage = 10;
    $totalPages = max(1, ceil($totalRows / $itemsPerPage));
    $currentPage = isset($_GET['pg']) ? min(max(1, intval($_GET['pg'])), $totalPages) : 1;
    $startRow = ($currentPage - 1) * $itemsPerPage;
    
    // Get rows for current page
    $paginatedRows = array_slice($filteredRows, $startRow, $itemsPerPage);

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
    $countMessage = ($totalRows === count($filteredRows)) 
        ? sprintf('Showing all %d resources', $totalRows)
        : sprintf('Showing %d filtered resources', $totalRows);
    echo '<div class="result-count">' . htmlspecialchars($countMessage) . '</div>';

    // Display filter tags
    echo '<div class="tags-container" id="filterTags">';
    foreach ($allTags as $tag) {
        if (!empty($tag)) {
            // Case-insensitive comparison for selected tags
            $tagLower = strtolower($tag);
            $selectedTagsLower = array_map('strtolower', $selectedTags);
            
            // Special handling for LGBTQ+ tag
            if (stripos($tag, 'LGBTQ') !== false) {
                $isSelected = false;
                foreach ($selectedTagsLower as $selectedTag) {
                    // Compare ignoring spaces and case
                    if (str_replace(' ', '+', $tagLower) === str_replace(' ', '+', $selectedTag)) {
                        $isSelected = true;
                        break;
                    }
                }
                error_log("LGBTQ+ filter tag: '$tag', isSelected: " . ($isSelected ? 'true' : 'false'));
                error_log("Selected tags for comparison: " . implode(', ', $selectedTagsLower));
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

    foreach ($paginatedRows as $row) {
        $resource = isset($row[0]) ? $row[0] : '';
        $phoneNum = isset($row[1]) ? $row[1] : '';
        $description = isset($row[2]) ? $row[2] : '';
        $keywords = isset($row[3]) ? array_map('trim', explode(',', $row[3])) : [];
        $website = isset($row[4]) ? $row[4] : '';

        echo '<tr>';
        
        // Resource column with optional link
        echo '<td>';
        if (!empty($website)) {
            echo '<a href="' . htmlspecialchars($website) . '" target="_blank" rel="noopener noreferrer">' . 
                htmlspecialchars($resource) . '</a>';
        } else {
            echo htmlspecialchars($resource);
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
        foreach ($keywords as $keyword) {
            if (!empty($keyword)) {
                // Case-insensitive comparison for selected tags
                $keywordLower = strtolower($keyword);
                $selectedTagsLower = array_map('strtolower', $selectedTags);
                
                // Special handling for LGBTQ+ tag in the table display
                if (stripos($keyword, 'LGBTQ') !== false) {
                    $isSelected = false;
                    foreach ($selectedTagsLower as $selectedTag) {
                        // Compare ignoring spaces and case, but preserving the + character
                        if (str_replace(' ', '+', $keywordLower) === str_replace(' ', '+', $selectedTag)) {
                            $isSelected = true;
                            break;
                        }
                    }
                    if ($isSelected) {
                        error_log("CSV Mode - LGBTQ+ tag selected in table display: '$keyword'");
                    }
                } else {
                    // Normal case for other tags
                    $isSelected = in_array($keywordLower, $selectedTagsLower);
                }
                
                printf(
                    '<button type="button" class="table-tag%s" data-tag="%s" title="Click to %s filter">%s</button>',
                    $isSelected ? ' selected' : '',
                    htmlspecialchars($keyword),
                    $isSelected ? 'remove from' : 'add to',
                    htmlspecialchars($keyword)
                );
            }
        }
        echo '</div></td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    // Pagination controls
    if ($totalRows > $itemsPerPage) {
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