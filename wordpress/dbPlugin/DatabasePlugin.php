<?php
/*
Plugin Name: Database Plugin
Plugin URI: https://github.com/sailor027/thrive-lifeline/tree/main/wordpress/dbPlugin
Description: WP plugin to read a CSV file and display its contents in PHP.
Version: 2.9.6
Date: 2025.01.20
Author: Ko Horiuchi
License: MIT
*/

use DatabasePlugin\DisplayResources;
use DatabasePlugin\Utils;

register_activation_hook(__FILE__, 'dbPlugin_activate');


// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

add_action('wp_enqueue_styles', 'dbPlugin_enqueueStyles');
add_action('wp_enqueue_scripts', 'dbPlugin_enqueueScript');

//--------------------------------------------------------------------------------------------
// Enqueue custom styles and scripts
function dbPlugin_enqueueStyles() {
    wp_enqueue_style(
        'dbPlugin-styles', 
        plugin_dir_url(DBPLUGIN_FILE) . 'style.css',
        array()
    );
}


    function dbPlugin_enqueueScript() {
        wp_enqueue_script(
            'dbPlugin-script', 
            plugin_dir_url(DBPLUGIN_FILE) . 'script.js', 
            array('jquery'), 
            true
        );
        wp_localize_script(
            'dbPlugin-script',
            'dbPluginData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dbPlugin_nonce')
            )
        );
    }

    //--------------------------------------------------------------------------------------------
    // AJAX handlers
    add_action('wp_ajax_get_resources', 'handle_get_resources');
    add_action('wp_ajax_nopriv_get_resources', 'handle_get_resources');

    function handle_get_resources() {
        check_ajax_referer('dbPlugin_nonce', 'nonce');
        
        global $resourcesFile;
        
        if (!file_exists($resourcesFile)) {
            wp_send_json_error('Resource file not found');
            return;
        }
        
        if (!is_readable($resourcesFile)) {
            wp_send_json_error('Resource file is not readable');
            return;
        }
        
        $resources = array();
        $handle = fopen($resourcesFile, 'r');
        
        if ($handle !== false) {
            try {
                $headers = fgetcsv($handle);
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (isset($row[0]) && strpos($row[0], '#') === 0) {
                        continue;
                    }
                    if (count($row) === count($headers)) {
                        $resource = array_combine($headers, $row);
                        $resources[] = $resource;
                    }
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

    //--------------------------------------------------------------------------------------------
    // Register shortcode
    add_shortcode('displayResources', 'displayResourcesShortcode');

    // Admin menu functions
    add_action('admin_menu', 'dbPlugin_pluginMenu');

    function dbPlugin_pluginMenu() {
        $hook = add_menu_page(
            'Database Plugin Instructions',
            'Database Plugin',
            'manage_options',
            'dbPlugin',
            'dbPlugin_displayInstructions'
        );
        add_action("load-$hook", 'dbPlugin_add_help_tab');
    }

    function dbPlugin_displayInstructions() {
        global $docsFile;
        $fileContents = file_get_contents($docsFile);
        if ($fileContents !== false) {
            echo wp_kses_post($fileContents);
        } else {
            echo '<div class="notice notice-error is-dismissible">Error opening ' . esc_html($docsFile) . '</div>';
        }
    }

    function dbPlugin_add_help_tab() {
        $screen = get_current_screen();
        $screen->add_help_tab(array(
            'id'       => 'dbPlugin_help',
            'title'    => 'Plugin Usage',
            'content'  => '<p>Use the shortcode [displayResources] to show the database table on any page.</p>'
        ));
    }

?>