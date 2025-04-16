<?php
namespace DatabasePlugin;

class Utils{
    // Define plugin paths securely
    const PLUGIN_DIR = wp_normalize_path(plugin_dir_path(DBPLUGIN_FILE));
    const RESOURCE_FILE = PLUGIN_DIR . 'crisisResources.csv';
    const DOCS_FILE = PLUGIN_DIR . 'README.md';
    const SEARCH_IMG = PLUGIN_DIR . 'media/search.svg';
    const PHONE_IMG = PLUGIN_DIR . 'media/phone.svg';

    /**
     * Activate the plugin
     */
    public function dbPlugin_activate() {
        // Prevent direct file access
        if (!defined('ABSPATH')) {
            exit;
        }
    }
}
