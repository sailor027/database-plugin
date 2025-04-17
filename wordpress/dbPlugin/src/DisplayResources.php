<?php
namespace DatabasePlugin;

use DatabasePlugin\Utils;

class DisplayResources {
    const NUM_ROWS = 10; // number of rows/resources per page

    /**
     * Display resources shortcode handler
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function displayResourcesShortcode($atts = array()) {
        // Get resource file path
        $resourceFile = Utils::getResourceFile();
        
        // Check file access
        if (!Utils::isFileReadable($resourceFile)) {
            return '<div class="notice notice-error">Resource file not found or not readable: ' . esc_html($resourceFile) . '</div>';
        }

        // Handle search and filtering
        $searchQuery = isset($_GET['kw']) ? sanitize_text_field($_GET['kw']) : '';
        $searchTerms = array_filter(explode(' ', $searchQuery));
        $selectedTags = isset($_GET['tags']) ? Utils::sanitizeTagArray($_GET['tags']) : array();

        // Initialize counters and arrays
        $totalRows = 0;
        $filteredRows = array();
        $allTags = array();

        // Read and process data
        $fileHandle = fopen($resourceFile, 'r');

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

                // Check if row matches search terms and selected tags
                $matchesSearch = empty($searchTerms) || array_reduce($searchTerms, function($carry, $term) use ($row) {
                    return $carry && stripos(implode(' ', $row), $term) !== false;
                }, true);
                
                $matchesTags = empty($selectedTags);
                if (!empty($selectedTags) && isset($row[3])) {
                    $rowTags = array_map('trim', explode(',', $row[3]));
                    $matchesTags = count(array_intersect($selectedTags, $rowTags)) === count($selectedTags);
                }
                
                // Add matching rows to filtered results
                if ($matchesSearch && $matchesTags) {
                    $filteredRows[] = $row;
                    $totalRows++;
                }
            }
        } catch (\Exception $e) {
            fclose($fileHandle);
            Utils::logError('Error processing CSV: ' . $e->getMessage());
            return '<div class="notice notice-error">Error processing data: ' . esc_html($e->getMessage()) . '</div>';
        }
        
        fclose($fileHandle);
        
        // Sort tags alphabetically
        sort($allTags);

        // Set up pagination
        $totalPages = max(1, ceil($totalRows / self::NUM_ROWS));
        $currentPage = isset($_GET['pg']) ? min(max(1, intval($_GET['pg'])), $totalPages) : 1;
        $startRow = ($currentPage - 1) * self::NUM_ROWS;
        
        // Get rows for current page
        $paginatedRows = array_slice($filteredRows, $startRow, self::NUM_ROWS);

        // Start output buffering
        ob_start();

        // Display search form
        $this->displaySearchForm($searchQuery);

        // Results count message
        $countMessage = ($totalRows === count($filteredRows)) 
            ? sprintf('Showing all %d resources', $totalRows)
            : sprintf('Showing %d filtered resources', $totalRows);
        echo '<div class="result-count">' . esc_html($countMessage) . '</div>';

        // Display filter tags
        echo '<div class="tags-container" id="filterTags">';
        foreach ($allTags as $tag) {
            if (!empty($tag)) {
                $isSelected = in_array($tag, $selectedTags) ? 'selected' : '';
                printf(
                    '<button type="button" class="tag %s" data-tag="%s">%s</button>',
                    esc_attr($isSelected),
                    esc_attr($tag),
                    esc_html($tag)
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
            $keywords = isset($row[3]) ? array_map('trim', explode(',', $row[3])) : array();
            $website = isset($row[4]) ? $row[4] : '';

            echo '<tr>';
            
            // Resource column with optional link
            echo '<td>';
            if (!empty($website)) {
                echo '<a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferrer">' . 
                    esc_html($resource) . '</a>';
            } else {
                echo esc_html($resource);
            }
            echo '</td>';
            
            // Description column with phone number
            echo '<td>';
            if (!empty($phoneNum)) {
                $phoneIconUrl = plugin_dir_url(Utils::getPluginFile()) . 'media/phone.svg';
                $phoneIconHtml = '<img src="' . esc_url($phoneIconUrl) . '" alt="Phone" class="phone-icon">';
                $phoneNumHtml = '<div class="phone-num-container">' . $phoneIconHtml . 
                          '<span class="phone-num">' . esc_html($phoneNum) . '</span></div>';
                echo $phoneNumHtml;
            }
            echo '<div class="description">' . esc_html($description) . '</div>';
            echo '</td>';
            
            // Keywords column with tags
            echo '<td><div class="tag-container">';
            foreach ($keywords as $keyword) {
                if (!empty($keyword)) {
                    $isSelected = in_array($keyword, $selectedTags) ? 'selected' : '';
                    printf(
                        '<button type="button" class="table-tag %s" data-tag="%s">%s</button>',
                        esc_attr($isSelected),
                        esc_attr($keyword),
                        esc_html($keyword)
                    );
                }
            }
            echo '</div></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Pagination controls
        if ($totalRows > self::NUM_ROWS) {
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

    /**
     * Display search form
     * @param string $query Current search query
     */
    private function displaySearchForm($query) {
        echo '<div class="resources-search-container">';
        echo '<div class="search-controls">';
        
        // Search form
        echo '<form class="search-wrapper">';
        echo '<input type="text" id="resourceSearch" name="kw" placeholder="Search database..." value="' . esc_attr($query) . '">';
        echo '<button type="submit" class="search-button" aria-label="Search">';
        
        // Search icon
        $searchIconUrl = plugin_dir_url(Utils::getPluginFile()) . 'media/search.svg';
        echo '<img src="' . esc_url($searchIconUrl) . '" alt="Search">';
        echo '</button>';
        echo '</form>';
        
        // Reset button
        echo '<button type="button" class="reset-button" onclick="resetFilters()">';
        echo '<span>×</span> Reset Filters';
        echo '</button>';
        echo '</div>'; // Close search-controls
    }
}