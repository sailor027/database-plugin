<?php
namespace DatabasePlugin;

class AjaxHandler {
    /**
     * Resource Handler instance
     * @var ResourceHandler
     */
    private $resourceHandler;
    
    /**
     * Constructor
     * @param ResourceHandler $resourceHandler
     */
    public function __construct($resourceHandler) {
        $this->resourceHandler = $resourceHandler;
    }
    
    /**
     * Handle AJAX request to get resources
     */
    public function handleGetResources() {
        // Verify nonce for security
        if (!check_ajax_referer('dbPlugin_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => 'Security check failed'
            ]);
            return;
        }
        
        // Get resources
        $result = $this->resourceHandler->getAllResources();
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }
        
        // Extract search and filter parameters
        $searchQuery = isset($_GET['kw']) ? sanitize_text_field($_GET['kw']) : '';
        $selectedTags = isset($_GET['tags']) ? $this->resourceHandler->sanitizeTagArray($_GET['tags']) : [];
        
        // Filter resources
        $filteredResources = $this->resourceHandler->filterResources($result['resources'], $searchQuery, $selectedTags);
        
        // Return success response
        wp_send_json_success([
            'resources' => $filteredResources,
            'totalResources' => count($filteredResources),
            'allTags' => $result['tags']
        ]);
    }
}