<?php
/*
Plugin Name: Database Plugin
Plugin URI: https://github.com/sailor027/thrive-lifeline/tree/main/wordpress/dbPlugin
Description: WP plugin to read a CSV file and display its contents as a filterable resource database
Version: 2.10.0
Author: Ko Horiuchi
License: MIT
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DBPLUGIN_DIR', plugin_dir_path(__FILE__));
define('DBPLUGIN_URL', plugin_dir_url(__FILE__));
define('DBPLUGIN_FILE', __FILE__);
define('RESOURCE_FILE', DBPLUGIN_DIR . 'crisisResources.csv');

/**
 * Plugin activation hook
 */
function dbPlugin_activate() {
    // Check if resource file exists and is readable
    if (!file_exists(RESOURCE_FILE) || !is_readable(RESOURCE_FILE)) {
        wp_die(
            'Database Plugin Error: Resource file not found or not readable. ' . 
            'Please ensure that crisisResources.csv exists in the plugin directory.',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
}
register_activation_hook(__FILE__, 'dbPlugin_activate');

/**
 * Enqueue plugin styles and scripts
 */
function dbPlugin_enqueue_assets() {
    wp_enqueue_style(
        'dbPlugin-styles', 
        DBPLUGIN_URL . 'style.css',
        [],
        filemtime(DBPLUGIN_DIR . 'style.css')
    );
    
    wp_enqueue_script(
        'dbPlugin-script', 
        DBPLUGIN_URL . 'script.js', 
        ['jquery'],
        filemtime(DBPLUGIN_DIR . 'script.js'),
        true
    );
    
    wp_localize_script(
        'dbPlugin-script',
        'dbPluginData',
        [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dbPlugin_nonce')
        ]
    );
}
add_action('wp_enqueue_scripts', 'dbPlugin_enqueue_assets');

/**
 * Sanitize and decode tag array
 */
function dbPlugin_sanitize_tag_array($tags) {
    if (!is_array($tags)) {
        return [];
    }
    
    return array_map(function($tag) {
        return sanitize_text_field(urldecode($tag));
    }, $tags);
}

/**
 * Display resources shortcode handler
 */
function dbPlugin_display_resources($atts = []) {
    // Check file access
    if (!file_exists(RESOURCE_FILE) || !is_readable(RESOURCE_FILE)) {
        return '<div class="notice notice-error">Resource file not found or not readable</div>';
    }

    // Handle search and filtering
    $searchQuery = isset($_GET['kw']) ? sanitize_text_field($_GET['kw']) : '';
    $searchTerms = array_filter(explode(' ', $searchQuery));
    $selectedTags = isset($_GET['tags']) ? dbPlugin_sanitize_tag_array($_GET['tags']) : [];

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
    } catch (Exception $e) {
        fclose($fileHandle);
        return '<div class="notice notice-error">Error processing data: ' . esc_html($e->getMessage()) . '</div>';
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
    echo '<input type="text" id="resourceSearch" name="kw" placeholder="Search database..." value="' . esc_attr($searchQuery) . '">';
    echo '<button type="submit" class="search-button" aria-label="Search">';
    
    // Search icon
    echo '<img src="' . esc_url(DBPLUGIN_URL . 'media/search.svg') . '" alt="Search">';
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
        $keywords = isset($row[3]) ? array_map('trim', explode(',', $row[3])) : [];
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
            $phoneIconHtml = '<img src="' . esc_url(DBPLUGIN_URL . 'media/phone.svg') . '" alt="Phone" class="phone-icon">';
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
add_shortcode('displayResources', 'dbPlugin_display_resources');

/**
 * Set up admin menu
 */
function dbPlugin_plugin_menu() {
    $hook = add_menu_page(
        'Database Plugin Instructions',
        'Database Plugin',
        'manage_options',
        'dbPlugin',
        'dbPlugin_display_instructions',
        'dashicons-database'
    );
    
    add_action("load-$hook", 'dbPlugin_add_help_tab');
}
add_action('admin_menu', 'dbPlugin_plugin_menu');

/**
 * Display plugin instructions
 */
function dbPlugin_display_instructions() {
    $docsFile = DBPLUGIN_DIR . 'README.md';
    
    if (!file_exists($docsFile) || !is_readable($docsFile)) {
        echo '<div class="notice notice-error is-dismissible">Documentation file not found or not readable</div>';
        return;
    }
    
    $fileContents = file_get_contents($docsFile);
    
    if ($fileContents !== false) {
        echo '<div class="wrap">';
        echo '<h1>Database Plugin Documentation</h1>';
        echo wp_kses_post($fileContents);
        echo '</div>';
    } else {
        echo '<div class="notice notice-error is-dismissible">Error opening documentation file</div>';
    }
}

/**
 * Add help tab to admin page
 */
function dbPlugin_add_help_tab() {
    $screen = get_current_screen();
    
    $screen->add_help_tab([
        'id'      => 'dbPlugin_help',
        'title'   => 'Plugin Usage',
        'content' => '
            <h2>Database Plugin Help</h2>
            <p>Use the shortcode <code>[displayResources]</code> to show the database table on any page or post.</p>
            <h3>Troubleshooting</h3>
            <ul>
                <li>If the table doesn\'t appear, check that the CSV file exists and is readable.</li>
                <li>If search doesn\'t work, make sure JavaScript is enabled in your browser.</li>
                <li>If styles aren\'t applying, try clearing your WordPress cache.</li>
            </ul>
        '
    ]);
}

/**
 * JavaScript functions for front-end
 */
function dbPlugin_footer_js() {
    ?>
    <script>
    // Change page function for pagination
    function changePage(pageNum) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('pg', pageNum);
        const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
        window.location.href = newUrl;
    }
    
    // Reset filters function
    function resetFilters() {
        window.location.href = window.location.pathname;
    }
    
    // Toggle tag filter function
    function toggleTagFilter(tagValue) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentTags = urlParams.getAll('tags');
        
        // Check if tag is already selected
        const tagIndex = currentTags.indexOf(tagValue);
        
        if (tagIndex > -1) {
            // Remove tag if already selected
            const newTags = currentTags.filter(tag => tag !== tagValue);
            urlParams.delete('tags');
            newTags.forEach(tag => urlParams.append('tags', tag));
        } else {
            // Add tag if not selected
            urlParams.append('tags', tagValue);
        }
        
        // Update URL
        const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
        window.location.href = newUrl;
    }
    </script>
    <?php
}
add_action('wp_footer', 'dbPlugin_footer_js');