<?php
/**
 * Database Admin Page for Resource Database Plugin
 */

// Include the database handler
require_once('database-handler.php');

// Set up a database handler
$dbHandler = new ResourceDatabaseHandler();

// Check database connection
$isDbInitialized = $dbHandler->isInitialized();

// Handle database actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize tables
    if (isset($_POST['action']) && $_POST['action'] === 'initialize') {
        if ($dbHandler->initializeTables()) {
            $message = 'Database tables created successfully.';
            $messageType = 'success';
        } else {
            $message = 'Failed to create database tables.';
            $messageType = 'error';
        }
    }
    
    // Import CSV to database
    if (isset($_POST['action']) && $_POST['action'] === 'import') {
        $csvFile = isset($_POST['csv_file']) ? $_POST['csv_file'] : 'crisisResources.csv';
        $result = $dbHandler->importFromCsv($csvFile);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }
}

// Get database status
if ($isDbInitialized) {
    // Get total resources count
    $resourcesData = $dbHandler->getResources([], [], 1, 1);
    $totalResources = $resourcesData['total'];
    $allTags = $resourcesData['tags'];
} else {
    $totalResources = 0;
    $allTags = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Admin - Resource Database Plugin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: var(--grn2);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-good {
            background-color: #4CAF50;
        }
        .status-bad {
            background-color: #F44336;
        }
        .status-container {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .actions {
            margin-top: 20px;
        }
        .button {
            background-color: var(--grn2);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .button:hover {
            background-color: var(--brn);
        }
        .button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #DFF2BF;
            color: #4F8A10;
        }
        .error {
            background-color: #FFBABA;
            color: #D8000C;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .tag-item {
            background-color: var(--grn);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .csv-input {
            margin-bottom: 10px;
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Database Admin - Resource Database Plugin</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Database Status</h2>
            <div class="status-container">
                <span class="status-indicator <?php echo $isDbInitialized ? 'status-good' : 'status-bad'; ?>"></span>
                <span>Database Connection: <?php echo $isDbInitialized ? 'Connected' : 'Not Connected'; ?></span>
            </div>
            
            <?php if ($isDbInitialized): ?>
                <div>
                    <p><strong>Resources in database:</strong> <?php echo $totalResources; ?></p>
                    <p><strong>Tags in database:</strong> <?php echo count($allTags); ?></p>
                    
                    <?php if (!empty($allTags)): ?>
                        <div class="tag-list">
                            <?php foreach ($allTags as $tag): ?>
                                <span class="tag-item"><?php echo htmlspecialchars($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="actions">
                <form method="post">
                    <input type="hidden" name="action" value="initialize">
                    <button type="submit" class="button" <?php echo !$isDbInitialized ? 'disabled' : ''; ?>>
                        Initialize/Reset Database Tables
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <h2>Import Data from CSV</h2>
            <form method="post">
                <input type="hidden" name="action" value="import">
                
                <label for="csv_file">CSV File Path:</label>
                <input type="text" id="csv_file" name="csv_file" class="csv-input" value="crisisResources.csv">
                
                <button type="submit" class="button" <?php echo !$isDbInitialized ? 'disabled' : ''; ?>>
                    Import CSV Data
                </button>
            </form>
            
            <p><strong>Note:</strong> This will replace all existing data in the database.</p>
        </div>
        
        <div class="card">
            <h2>Database Preview</h2>
            
            <?php if ($isDbInitialized && $totalResources > 0): 
                // Get a sample of resources for preview
                $previewData = $dbHandler->getResources([], [], 1, 5);
                $previewResources = $previewData['resources'];
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Description</th>
                            <th>Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewResources as $resource): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($resource['name']); ?>
                                    <?php if (!empty($resource['phone'])): ?>
                                        <div><small><?php echo htmlspecialchars($resource['phone']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($resource['description']); ?></td>
                                <td>
                                    <div class="tag-list">
                                        <?php foreach ($resource['tags'] as $tag): ?>
                                            <span class="tag-item"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p><small>Showing 5 of <?php echo $totalResources; ?> resources.</small></p>
                
            <?php else: ?>
                <p>No data available. Import data from CSV first.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>