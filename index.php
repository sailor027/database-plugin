<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Database Plugin Demo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <h1>Resource Database Plugin Demo</h1>
        <p>This is a demonstration of the WordPress plugin that displays a searchable and filterable resource database from CSV data.</p>
        
        <div class="plugin-container">
            <?php
            // Set up WordPress-like environment constants for demo purposes
            define('ABSPATH', true);
            define('DBPLUGIN_DIR', './');
            define('DBPLUGIN_URL', './');
            define('DBPLUGIN_FILE', __FILE__);
            define('RESOURCE_FILE', './crisisResources.csv');

            // Include the main plugin file functions
            include_once('./database-plugin-demo.php');
            
            // Display the resources using the shortcode handler function
            echo dbPlugin_display_resources();
            ?>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>