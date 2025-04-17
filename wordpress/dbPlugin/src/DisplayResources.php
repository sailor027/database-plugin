<?php
namespace DatabasePlugin;

use DatabasePlugin\Utils;

class DisplayResources {
    const NUM_ROWS = 10; // number of rows/resources per page

    /**
     * Sanitize and decode tag array
     * @param $tags array of filter tags
     * @return $array
     */
    private function sanitize_tag_array($tags) {
        if (!is_array($tags)) {
            return array();
        }
        return array_map(function($tag) {
            return sanitize_text_field(urldecode($tag));
        }, $tags);
    }

    /**
     * Check if the resource file is accessible
     * @param $file string path to the resource file
     * @return $string error message
     */
    private function checkFileAccess($file) {
        if (!file_exists($file) || !is_readable($file)) {
            return '<div class="notice notice-error">Resource file not found or not readable: ' . esc_html($file) . '</div>';
        }
    }

    /**
     * Read the resource file
     * @param $file string path to the resource file
     * @return $fileHandle
     */
    private function readFile($file) {
        $fileHandle = fopen($file, 'r');

        if ($fileHandle === false) {
            return '<div class="notice notice-error">Failed to open resource file</div>';
        }
        
        try {
            $headers = fgetcsv($fileHandle);
            
            while (($row = fgetcsv($fileHandle)) !== false) {
                // Skip commented rows
                if (isset($row[0]) && strpos($row[0], '#') === 0) {
                    continue;
                }
                
                // If row is not null, collect tags and clean them
                if (isset($row[3])) {
                    $rowTags = array_map('trim', explode(',', $row[3]));
                    foreach ($rowTags as $tag) {
                        if (!empty($tag) && !in_array($tag, $allTags)) {
                            $allTags[] = $tag;
                        }
                    }
                }

                // Check if row matches selected filters
                // AND operator for search terms
                $matchesSearch = empty($searchTerms) || array_reduce($searchTerms, function($carry, $term) use ($row) {
                    return $carry && stripos(implode(' ', $row), $term) !== false;
                }, true);
                
                
                $matchesTags = empty($selectedTags); // boolean if there are results that match the tags
                // if (!empty($selectedTags) && isset($row[3])) {
                //     $rowTags = array_map('trim', explode(',', $row[3]));
                //     $matchesTags = true;
                //     foreach ($selectedTags as $tag) {
                //         if (!in_array($tag, $rowTags)) {
                //             $matchesTags = false;
                //             break;
                //         }
                //     }
                // }
                if (!empty($selectedTags) && isset($row[3])) {
                    $rowTags = array_map('trim', explode(',', $row[3]));
                    $matchesTags = count(array_intersect($selectedTags, $rowTags)) === count($selectedTags);
                }
                
                if ($matchesSearch && $matchesTags) {
                    $filteredRows[] = $row;
                    $totalRows++;
                }
            }
        } catch (\Exception $e) {
            fclose($fileHandle);
            return '<div class="notice notice-error">Error processing data: ' . esc_html($e->getMessage()) . '</div>';
        }
        
        fclose($fileHandle);
    }

    function displayResourcesShortcode($atts = array()) {
        $this->checkFileAccess(Utils::PLUGIN_DIR);

        // Handle search and filtering
        $searchQuery = isset($_GET['kw']) ? sanitize_text_field($_GET['kw']) : '';
        $searchTerms = array_filter(explode(' ', $searchQuery));
        $selectedTags = isset($_GET['tags']) ? $this->sanitize_tag_array($_GET['tags']) : array();

        // Initialize counters and arrays
        $totalRows = 0;
        $filteredRows = array();
        $allTags = array();

        // Read and process data
        $fileHandle = $this->readFile(Utils::PLUGIN_DIR);

        
        sort($allTags);

        // Set up pagination
        $totalPages = max(1, ceil($totalRows / DisplayResources::NUM_ROWS));
        $currentPage = isset($_GET['pg']) ? min(max(1, intval($_GET['pg'])), $totalPages) : 1;
        $startRow = ($currentPage - 1) * DisplayResources::NUM_ROWS;
        
        // Get rows for current page
        $paginatedRows = array_slice($filteredRows, $startRow, DisplayResources::NUM_ROWS);

        ob_start();

        $this->displaySearchForm($searchQuery);

        // Updated results count message
        $countMessage = ($totalRows === count($filteredRows)) 
            ? sprintf('Showing all %d resources', $totalRows)
            : sprintf('Showing %d filtered resources', $totalRows);
        echo '<div class="result-count">' . esc_html($countMessage) . '</div>';

        // Display tags
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

        // Display table
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
            echo '<td>';
            if (!empty($website)) {
                echo '<a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferrer">' . 
                    esc_html($resource) . '</a>';
            } else {
                echo esc_html($resource);
            }
            echo '</td>';
            
            echo '<td>';
            if (!empty($phoneNum)) {
                $phoneIconHtml = '<img src="' . esc_url(plugin_dir_url(DBPLUGIN_FILE) . 'media/phone.svg') . 
                                '" alt="Phone" class="phone-icon">';
                $phoneNumHtml = '<div class="phone-num-container">' . $phoneIconHtml . 
                            '<span class="phone-num">' . esc_html($phoneNum) . '</span></div>';
                echo $phoneNumHtml;
            }
            echo '<div class="description">' . esc_html($description) . '</div>';
            echo '</td>';
            
            echo '<td><div class="tag-container">';
            foreach ($keywords as $keyword) {
                if (!empty($keyword)) {
                    $isSelected = in_array($keyword, $selectedTags) ? 'selected' : '';
                    printf(
                        '<button type="button" class="table-tag %s" onclick="toggleTagFilter(\'%s\')" data-tag="%s">%s</button>',
                        esc_attr($isSelected),
                        esc_attr($keyword),
                        esc_attr($keyword),
                        esc_html($keyword)
                    );
                }
            }
            echo '</div></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Add pagination controls
        if ($totalRows > DisplayResources::NUM_ROWS) {
            echo '<div class="pagination" role="navigation" aria-label="Resource list pagination">';
            
            if ($currentPage > 1) {
                printf(
                    '<button type="button" class="page-np" onclick="changePage(%d)" aria-label="Go to previous page">&lt;</button>',
                    $currentPage - 1
                );
            }
            
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

    private function displaySearchForm($query) {
         echo '<div class="resources-search-container">';
         echo '<div class="search-controls">';
         echo '<form class="search-wrapper">';  // Removed onsubmit="return false;"
         echo '<input type="text" id="resourceSearch" name="kw" placeholder="Search database..." value="' . esc_attr($query) . '">';
         echo '<button type="submit" class="search-button" aria-label="Search">';
         echo '<img src="' . esc_url(plugin_dir_url(DBPLUGIN_FILE) . 'media/search.svg') . '" alt="Search">';
         echo '</button>';
         echo '</form>';
         echo '<button type="button" class="reset-button" onclick="resetFilters()">';
         echo '<span>×</span> Reset Filters';
         echo '</button>';
         echo '</div>';
    }
}