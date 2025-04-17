<?php
/*
Plugin Name: Database Plugin
Plugin URI: https://github.com/sailor027/thrive-lifeline/tree/main/wordpress/dbPlugin
Description: WP plugin to read a CSV file and display its contents in PHP.
Version: 3.0.0
Date: 2025.04.17
Author: Ko Horiuchi
License: MIT
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

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
use DatabasePlugin\ResourceHandler;
use DatabasePlugin\DisplayHandler;
use DatabasePlugin\PaginationHandler;
use DatabasePlugin\AdminHandler;
use DatabasePlugin\AjaxHandler;

class DatabasePlugin {
    /**
     * Plugin instance
     * @var DatabasePlugin
     */
    private static $instance = null;
    
    /**
     * Component instances
     */
    private $utils;
    private $resourceHandler;
    private $displayHandler;
    private $paginationHandler;
    private $adminHandler;
    private $ajaxHandler;
    
    /**
     * Constructor - initialize components and register hooks
     */
    private function __construct() {
        // Enable error reporting in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        }
        
        // Initialize components
        $this->utils = new Utils();
        $this->resourceHandler = new ResourceHandler();
        $this->paginationHandler = new PaginationHandler();
        $this->displayHandler = new DisplayHandler($this->resourceHandler, $this->paginationHandler);
        $this->adminHandler = new AdminHandler();
        $this->ajaxHandler = new AjaxHandler($this->resourceHandler);
        
        // Register activation hook
        register_activation_hook(__FILE__, [$this->utils, 'activate']);
        
        // Register actions and filters
        $this->registerHooks();
    }
    
    /**
     * Get plugin instance (Singleton pattern)
     * @return DatabasePlugin
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // Register shortcode
        add_shortcode('displayResources', [$this->displayHandler, 'renderShortcode']);
        
        // Set up admin menu
        add_action('admin_menu', [$this->adminHandler, 'setupMenu']);
        
        // Register AJAX handlers
        add_action('wp_ajax_get_resources', [$this->ajaxHandler, 'handleGetResources']);
        add_action('wp_ajax_nopriv_get_resources', [$this->ajaxHandler, 'handleGetResources']);
    }
    
    /**
     * Enqueue styles and scripts
     */
    public function enqueueAssets() {
        // Enqueue stylesheet
        wp_enqueue_style(
            'dbPlugin-styles', 
            plugin_dir_url(__FILE__) . 'style.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'style.css')
        );
        
        // Enqueue script
        wp_enqueue_script(
            'dbPlugin-script', 
            plugin_dir_url(__FILE__) . 'script.js', 
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . 'script.js'),
            true
        );
        
        // Localize script for AJAX
        wp_localize_script(
            'dbPlugin-script',
            'dbPluginData',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dbPlugin_nonce')
            ]
        );
    }
}

// Initialize the plugin using singleton pattern
DatabasePlugin::getInstance();