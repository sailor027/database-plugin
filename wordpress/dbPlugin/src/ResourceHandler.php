<?php
namespace DatabasePlugin;

class ResourceHandler {
    /**
     * Get all resources from CSV file
     * @return array|WP_Error Array of resources or error
     */
    public function getAllResources() {
        $filePath = Utils::getResourceFile();
        
        // Check file exists and is readable
        if (!Utils::isFileReadable($filePath)) {
            return new \WP_Error('file_error', 'Resource file not found or not readable');
        }
        
        $resources = [];
        $headers = [];
        $allTags = [];
        
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            return new \WP_Error('file_error', 'Failed to open resource file');
        }
        
        try {
            // Get headers from first row
            $headers = fgetcsv($handle);
            
            if ($headers === false) {
                throw new \Exception('Failed to read CSV headers');
            }
            
            // Process data rows
            while (($row = fgetcsv($handle)) !== false) {
                // Skip commented rows
                if (isset($row[0]) && strpos($row[0], '#') === 0) {
                    continue;
                }
                
                // Skip rows that don't match header count
                if (count($row) !== count($headers)) {
                    continue;
                }
                
                // Convert row to associative array
                $resource = array_combine($headers, $row);
                
                // Extract tags from Keywords column (assuming index 3)
                if (isset($resource['Keywords'])) {
                    $tags = array_map('trim', explode(',', $resource['Keywords']));
                    $resource['TagsArray'] = $tags;
                    
                    // Collect unique tags
                    foreach ($tags as $tag) {
                        if (!empty($tag) && !in_array($tag, $allTags)) {
                            $allTags[] = $tag;
                        }
                    }
                } else {
                    $resource['TagsArray'] = [];
                }
                
                $resources[] = $resource;
            }
        } catch (\Exception $e) {
            fclose($handle);
            Utils::logError('Error processing CSV: ' . $e->getMessage());
            return new \WP_Error('parse_error', 'Error processing CSV: ' . $e->getMessage());
        }
        
        fclose($handle);
        
        // Sort tags alphabetically
        sort($allTags);
        
        return [
            'resources' => $resources,
            'headers' => $headers,
            'tags' => $allTags
        ];
    }
    
    /**
     * Filter resources based on search terms and tags
     * @param array $resources Resources to filter
     * @param string $searchQuery Search query
     * @param array $selectedTags Selected tags
     * @return array Filtered resources
     */
    public function filterResources($resources, $searchQuery = '', $selectedTags = []) {
        if (empty($resources)) {
            return [];
        }
        
        $searchTerms = array_filter(explode(' ', $searchQuery));
        $filteredResources = [];
        
        foreach ($resources as $resource) {
            // Check if resource matches search terms (AND logic)
            $matchesSearch = empty($searchTerms);
            
            if (!$matchesSearch) {
                // Convert resource to string for searching
                $resourceString = implode(' ', $resource);
                
                // Check if all search terms are present
                $matchesSearch = true;
                foreach ($searchTerms as $term) {
                    if (stripos($resourceString, $term) === false) {
                        $matchesSearch = false;
                        break;
                    }
                }
            }
            
            // Check if resource matches selected tags
            $matchesTags = empty($selectedTags);
            
            if (!$matchesTags && isset($resource['TagsArray'])) {
                // Resource must have ALL selected tags
                $matchesTags = count(array_intersect($selectedTags, $resource['TagsArray'])) === count($selectedTags);
            }
            
            // Add resource to filtered results if it matches both search and tags
            if ($matchesSearch && $matchesTags) {
                $filteredResources[] = $resource;
            }
        }
        
        return $filteredResources;
    }
    
    /**
     * Sanitize and decode tag array
     * @param array $tags Array of filter tags
     * @return array Sanitized tags
     */
    public function sanitizeTagArray($tags) {
        return Utils::sanitizeTagArray($tags);
    }
}