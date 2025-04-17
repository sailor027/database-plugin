<?php
namespace DatabasePlugin;

class AdminHandler {
    /**
     * Set up admin menu
     */
    public function setupMenu() {
        $hook = add_menu_page(
            'Database Plugin Instructions',
            'Database Plugin',
            'manage_options',
            'dbPlugin',
            [$this, 'displayInstructions'],
            'dashicons-database'
        );
        
        add_action("load-$hook", [$this, 'addHelpTab']);
    }
    
    /**
     * Display plugin instructions
     */
    public function displayInstructions() {
        $docsFile = Utils::getDocsFile();
        
        if (!Utils::isFileReadable($docsFile)) {
            echo '<div class="notice notice-error is-dismissible">Error: Documentation file not found or not readable</div>';
            return;
        }
        
        $fileContents = file_get_contents($docsFile);
        
        if ($fileContents === false) {
            echo '<div class="notice notice-error is-dismissible">Error opening documentation file</div>';
            return;
        }
        
        // Parse markdown to HTML (simple parsing)
        $html = $this->parseMarkdown($fileContents);
        
        echo '<div class="wrap">';
        echo '<h1>Database Plugin Documentation</h1>';
        echo wp_kses_post($html);
        echo '</div>';
    }
    
    /**
     * Add help tab to admin page
     */
    public function addHelpTab() {
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
        
        $screen->set_help_sidebar('
            <p><strong>For more information:</strong></p>
            <p><a href="https://github.com/sailor027/thrive-lifeline/tree/main/wordpress/dbPlugin" target="_blank">GitHub Repository</a></p>
        ');
    }
    
    /**
     * Simple markdown parser
     * @param string $markdown Markdown text
     * @return string HTML
     */
    private function parseMarkdown($markdown) {
        // This is a basic markdown parser - in production, consider using a proper library
        
        // Headers
        $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $markdown);
        $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
        
        // Lists
        $html = preg_replace('/^- \[(x|X)\] (.*?)$/m', '<li class="checked">$2</li>', $html);
        $html = preg_replace('/^- \[ \] (.*?)$/m', '<li class="unchecked">$1</li>', $html);
        $html = preg_replace('/^- (.*?)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(\<li\>.*?\<\/li\>)(?!<li>)/s', '<ul>$1</ul>', $html);
        
        // Code
        $html = preg_replace('/`(.*?)`/s', '<code>$1</code>', $html);
        
        // Horizontal rule
        $html = preg_replace('/^\*\*\*$/m', '<hr />', $html);
        
        // Links
        $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $html);
        
        return $html;
    }
}