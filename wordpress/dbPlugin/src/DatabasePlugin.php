<?php
/*
Plugin Name: Database Plugin
Plugin URI: https://github.com/sailor027/thrive-lifeline/tree/main/wordpress/dbPlugin
Description: WP plugin to read a CSV file and display its contents as a filterable resource database
Version: 3.0.0
Date: 2025.04.17
Author: Ko Horiuchi
License: MIT
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin file constant
define('DBPLUGIN_FILE', __FILE__);

// Autoload classes
spl_autoload_register(function($class) {
    // Only load classes in our namespace
    if (strpos($class, 'DatabasePlugin\\') !== 0) {
        return;
    }
    
    $class_path = str_replace('DatabasePlugin\\', '', $class);
    $file = __DIR__ . '/' . $class_path . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Use statements for our classes
use DatabasePlugin\Utils;
use DatabasePlugin\DisplayResources;

/**
 * Plugin activation hook
 */
function dbPlugin_activate() {
    // Initialize Utils and call activate method
    $utils = new Utils();
    $utils->activate();
}
register_activation_hook(__FILE__, 'dbPlugin_activate');

/**
 * Enqueue plugin styles
 */
function dbPlugin_enqueueStyles() {
    wp_enqueue_style(
        'dbPlugin-styles', 
        plugin_dir_url(__FILE__) . 'style.css',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'style.css')
    );
}
add_action('wp_enqueue_scripts', 'dbPlugin_enqueueStyles');

/**
 * Enqueue plugin scripts
 */
function dbPlugin_enqueueScript() {
    wp_enqueue_script(
        'dbPlugin-script', 
        plugin_dir_url(__FILE__) . 'script.js', 
        ['jquery'],
        filemtime(plugin_dir_path(__FILE__) . 'script.js'),
        true
    );
    
    wp_localize_script(
        'dbPlugin-script',
        'dbPluginData',
        [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dbPlugin_nonce'),
            'resetUrl' => esc_url(remove_query_arg(['kw', 'tags', 'pg']))
        ]
    );
}
add_action('wp_enqueue_scripts', 'dbPlugin_enqueueScript');

/**
 * Handle AJAX request to get resources
 */
function handle_get_resources() {
    // Verify nonce
    check_ajax_referer('dbPlugin_nonce', 'nonce');
    
    $resourcesFile = Utils::getResourceFile();
    
    if (!Utils::isFileReadable($resourcesFile)) {
        wp_send_json_error('Resource file not found or not readable');
        return;
    }
    
    $resources = [];
    $handle = fopen($resourcesFile, 'r');
    
    if ($handle !== false) {
        try {
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                // Skip commented rows
                if (isset($row[0]) && strpos($row[0], '#') === 0) {
                    continue;
                }
                
                // Skip rows that don't match header count
                if (count($row) !== count($headers)) {
                    continue;
                }
                
                $resource = array_combine($headers, $row);
                $resources[] = $resource;
            }
        } catch (Exception $e) {
            wp_send_json_error('Error processing CSV: ' . $e->getMessage());
            return;
        } finally {
            fclose($handle);
        }
        
        wp_send_json_success($resources);
    } else {
        wp_send_json_error('Failed to open resource file');
    }
}
add_action('wp_ajax_get_resources', 'handle_get_resources');
add_action('wp_ajax_nopriv_get_resources', 'handle_get_resources');

/**
 * Register shortcode
 */
function displayResourcesShortcode($atts = []) {
    $displayResources = new DisplayResources();
    return $displayResources->displayResourcesShortcode($atts);
}
add_shortcode('displayResources', 'displayResourcesShortcode');

/**
 * Set up admin menu
 */
function dbPlugin_pluginMenu() {
    $hook = add_menu_page(
        'Database Plugin Instructions',
        'Database Plugin',
        'manage_options',
        'dbPlugin',
        'dbPlugin_displayInstructions',
        'dashicons-database'
    );
    
    add_action("load-$hook", 'dbPlugin_add_help_tab');
}
add_action('admin_menu', 'dbPlugin_pluginMenu');

/**
 * Display plugin instructions
 */
function dbPlugin_displayInstructions() {
    $docsFile = Utils::getDocsFile();
    
    if (!Utils::isFileReadable($docsFile)) {
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