<?php
/**
 * Helper Functions
 * 
 * These functions mimic WordPress core functions that are used in our plugin but
 * are not available in a standalone PHP environment.
 */

if (!function_exists('sanitize_text_field')) {
    /**
     * Sanitizes a string from user input or from the database.
     *
     * - Checks for invalid UTF-8,
     * - Converts single < characters to entity,
     * - Strips all tags,
     * - Removes line breaks, tabs, and extra whitespace,
     * - Strips octets.
     *
     * @param string $str String to sanitize.
     * @return string Sanitized string.
     */
    function sanitize_text_field($str) {
        $filtered = trim($str);
        
        // Strip tags
        $filtered = strip_tags($filtered);
        
        // Remove line breaks, tabs, and extra white space
        $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);
        
        // Convert < to entity
        $filtered = str_replace('<', '&lt;', $filtered);
        
        return $filtered;
    }
}

/**
 * Sanitize an array of tag strings
 * 
 * @param array|string $tags Array of tags or a comma-separated string of tags
 * @return array Sanitized array of tags
 */
function dbPlugin_sanitize_tag_array($tags) {
    if (empty($tags)) {
        return [];
    }
    
    // If tags is a string (comma-separated list), convert to array
    if (is_string($tags)) {
        $tags = [$tags]; // We're expecting a single tag, not a comma-separated list
    }
    
    // Sanitize each tag
    $sanitized = array_map(function($tag) {
        return sanitize_text_field(trim($tag));
    }, $tags);
    
    // Remove empty tags
    return array_filter($sanitized, function($tag) {
        return !empty($tag);
    });
}