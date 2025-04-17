<?php
namespace DatabasePlugin;

class Utils {
    /**
     * Get plugin file path
     * @return string Plugin file path
     */
    public static function getPluginFile() {
        return dirname(__DIR__) . '/DatabasePlugin.php';
    }
    
    /**
     * Get plugin directory path
     * @return string Plugin directory path
     */
    public static function getPluginDir() {
        return plugin_dir_path(self::getPluginFile());
    }
    
    /**
     * Get resource file path
     * @return string Resource file path
     */
    public static function getResourceFile() {
        return self::getPluginDir() . 'crisisResources.csv';
    }
    
    /**
     * Get documentation file path
     * @return string Documentation file path
     */
    public static function getDocsFile() {
        return self::getPluginDir() . 'README.md';
    }
    
    /**
     * Check if file is readable
     * @param string $file File path
     * @return bool True if file exists and is readable
     */
    public static function isFileReadable($file) {
        return file_exists($file) && is_readable($file);
    }
    
    /**
     * Sanitize and decode tag array
     * @param array $tags Tags array
     * @return array Sanitized tags
     */
    public static function sanitizeTagArray($tags) {
        if (!is_array($tags)) {
            return [];
        }
        
        return array_map(function($tag) {
            return sanitize_text_field(urldecode($tag));
        }, $tags);
    }
    
    /**
     * Log error to WordPress debug log
     * @param string $message Error message
     */
    public static function logError($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[Database Plugin] ' . $message);
        }
    }
    
    /**
     * Activate the plugin
     */
    public function activate() {
        // Verify resource file exists and is readable
        $resourceFile = self::getResourceFile();
        
        if (!self::isFileReadable($resourceFile)) {
            wp_die(
                'Database Plugin Error: Resource file not found or not readable. ' . 
                'Please ensure that crisisResources.csv exists in the plugin directory.',
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
    }
}