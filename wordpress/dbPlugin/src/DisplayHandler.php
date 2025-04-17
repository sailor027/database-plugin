<?php
namespace DatabasePlugin;

class DisplayHandler {
    /**
     * Resource Handler instance
     * @var ResourceHandler
     */
    private $resourceHandler;
    
    /**
     * Pagination Handler instance
     * @var PaginationHandler
     */
    private $paginationHandler;
    
    /**
     * Constructor
     * @param ResourceHandler $resourceHandler
     * @param PaginationHandler $paginationHandler
     */
    public function __construct($resourceHandler, $paginationHandler) {
        $this->resourceHandler = $resourceHandler;
        $this->paginationHandler = $paginationHandler;
    }
    
    /**
     * Render shortcode content
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function renderShortcode($atts = []) {
        // Get all resources
        $result = $this->resourceHandler->getAllResources();
        
        if (is_wp_error($result)) {
            return '<div class="notice notice-error">' . esc_html($result->get_error_message()) . '</div>';
        }
        
        // Extract resource data
        $resources = $result['resources'];
        $allTags = $result['tags'];
        
        // Get search and filter parameters
        $searchQuery = isset($_GET['kw']) ? sanitize_text_field($_GET['kw']) : '';
        $selectedTags = isset($_GET['tags']) ? $this->resourceHandler->sanitizeTagArray($_GET['tags']) : [];
        
        // Filter resources
        $filteredResources = $this->resourceHandler->filterResources($resources, $searchQuery, $selectedTags);
        
        // Set up pagination
        $this->paginationHandler->setup(count($filteredResources));
        
        // Get paginated resources
        $paginatedResources = $this->paginationHandler->getPaginatedItems($filteredResources);
        
        // Start output buffering
        ob_start();
        
        // Render search form
        $this->renderSearchForm($searchQuery);
        
        // Render result count
        $totalResources = count($filteredResources);
        $countMessage = ($totalResources === count($resources)) 
            ? sprintf('Showing all %d resources', $totalResources)
            : sprintf('Showing %d filtered resources', $totalResources);
            
        echo '<div class="result-count">' . esc_html($countMessage) . '</div>';
        
        // Render filter tags
        $this->renderFilterTags($allTags, $selectedTags);
        
        // Render resource table
        $this->renderResourceTable($paginatedResources, $selectedTags);
        
        // Render pagination controls
        echo $this->paginationHandler->renderControls();
        
        // Return buffered output
        return ob_get_clean();
    }
    
    /**
     * Render search form
     * @param string $query Current search query
     */
    private function renderSearchForm($query) {
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
    
    /**
     * Render filter tags
     * @param array $allTags All available tags
     * @param array $selectedTags Currently selected tags
     */
    private function renderFilterTags($allTags, $selectedTags) {
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
    }
    
    /**
     * Render resource table
     * @param array $resources Resources to display
     * @param array $selectedTags Currently selected tags
     */
    private function renderResourceTable($resources, $selectedTags) {
        echo '<div id="resourceTableContainer">';
        echo '<table class="csv-table">';
        echo '<thead><tr><th>Resource</th><th>Resource Description</th><th>Keywords</th></tr></thead>';
        echo '<tbody id="resourceTableBody">';
        
        foreach ($resources as $resource) {
            // Get resource properties
            $resourceName = isset($resource['Resource']) ? $resource['Resource'] : '';
            $phoneNum = isset($resource['PhoneNumber']) ? $resource['PhoneNumber'] : '';
            $description = isset($resource['Description']) ? $resource['Description'] : '';
            $keywords = isset($resource['TagsArray']) ? $resource['TagsArray'] : [];
            $website = isset($resource['Website']) ? $resource['Website'] : '';
            
            echo '<tr>';
            
            // Resource column
            echo '<td>';
            if (!empty($website)) {
                echo '<a href="' . esc_url($website) . '" target="_blank" rel="noopener noreferrer">' . 
                    esc_html($resourceName) . '</a>';
            } else {
                echo esc_html($resourceName);
            }
            echo '</td>';
            
            // Description column
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
            
            // Keywords column
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
        echo '</div>'; // Close resourceTableContainer
    }
}