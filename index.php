<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Database Plugin Demo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .mode-selector {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .mode-button {
            padding: 8px 16px;
            background-color: var(--grn);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .mode-button.active {
            background-color: var(--grn2);
            color: white;
        }
        .admin-link {
            margin-left: auto;
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--org);
            color: black;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .admin-link:hover {
            background-color: var(--brn);
            color: white;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Resource Database Plugin Demo</h1>
        <p>This is a demonstration of the WordPress plugin that displays a searchable and filterable resource database.</p>
        
        <div class="mode-selector">
            <?php
            $mode = isset($_GET['mode']) ? $_GET['mode'] : 'csv';
            $csvActive = $mode === 'csv' ? 'active' : '';
            $dbActive = $mode === 'db' ? 'active' : '';
            ?>
            <a href="?mode=csv" class="mode-button <?php echo $csvActive; ?>">CSV Mode</a>
            <a href="?mode=db" class="mode-button <?php echo $dbActive; ?>">Database Mode</a>
            <a href="database-admin.php" class="admin-link">Database Admin</a>
        </div>
        
        <div class="plugin-container">
            <?php
            // Set up WordPress-like environment constants for demo purposes
            define('ABSPATH', true);
            define('DBPLUGIN_DIR', './');
            define('DBPLUGIN_URL', './');
            define('DBPLUGIN_FILE', __FILE__);
            define('RESOURCE_FILE', './crisisResources.csv');

            if ($mode === 'db') {
                // Use database mode
                include_once('./database-plugin-db.php');
                echo dbPlugin_display_resources_db();
            } else {
                // Use CSV mode (default)
                include_once('./database-plugin-demo.php');
                echo dbPlugin_display_resources();
            }
            ?>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>