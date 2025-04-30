<?php
/**
 * Database Handler for Resource Database Plugin
 * Handles all database operations
 */

class ResourceDatabaseHandler {
    private $conn;
    private $initialized = false;
    
    /**
     * Constructor - establishes database connection using environment variables
     */
    public function __construct() {
        $host = getenv('PGHOST');
        $port = getenv('PGPORT');
        $dbname = getenv('PGDATABASE');
        $user = getenv('PGUSER');
        $password = getenv('PGPASSWORD');
        
        try {
            $this->conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initialized = true;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->initialized = false;
        }
    }
    
    /**
     * Initialize the database tables if they don't exist
     */
    public function initializeTables() {
        if (!$this->initialized) {
            return false;
        }
        
        try {
            // Create resources table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS resources (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    phone VARCHAR(100),
                    description TEXT NOT NULL,
                    website VARCHAR(255)
                )
            ");
            
            // Create tags table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS tags (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE
                )
            ");
            
            // Create resource_tags junction table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS resource_tags (
                    resource_id INTEGER REFERENCES resources(id) ON DELETE CASCADE,
                    tag_id INTEGER REFERENCES tags(id) ON DELETE CASCADE,
                    PRIMARY KEY (resource_id, tag_id)
                )
            ");
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to initialize tables: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Import data from CSV file to database
     */
    public function importFromCsv($csvFile) {
        if (!$this->initialized) {
            return [
                'success' => false,
                'message' => 'Database not initialized'
            ];
        }
        
        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            return [
                'success' => false,
                'message' => 'CSV file not found or not readable'
            ];
        }
        
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Clear existing data
            $this->conn->exec("TRUNCATE resource_tags CASCADE");
            $this->conn->exec("TRUNCATE tags CASCADE");
            $this->conn->exec("TRUNCATE resources CASCADE");
            
            // Read CSV file
            $rows = [];
            $headers = [];
            $fileHandle = fopen($csvFile, 'r');
            
            if ($fileHandle === false) {
                throw new Exception("Failed to open CSV file");
            }
            
            // Read headers
            $headers = fgetcsv($fileHandle);
            
            // Process data rows
            $resourcesAdded = 0;
            $tagsAdded = 0;
            
            while (($row = fgetcsv($fileHandle)) !== false) {
                // Skip commented rows
                if (isset($row[0]) && strpos($row[0], '#') === 0) {
                    continue;
                }
                
                $resourceName = isset($row[0]) ? trim($row[0]) : '';
                $phoneNum = isset($row[1]) ? trim($row[1]) : '';
                $description = isset($row[2]) ? trim($row[2]) : '';
                $tagsString = isset($row[3]) ? trim($row[3]) : '';
                $website = isset($row[4]) ? trim($row[4]) : '';
                
                if (empty($resourceName) || empty($description)) {
                    continue; // Skip rows without essential data
                }
                
                // Insert resource
                $stmt = $this->conn->prepare("
                    INSERT INTO resources (name, phone, description, website) 
                    VALUES (:name, :phone, :description, :website)
                    RETURNING id
                ");
                
                $stmt->bindParam(':name', $resourceName);
                $stmt->bindParam(':phone', $phoneNum);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':website', $website);
                $stmt->execute();
                
                $resourceId = $stmt->fetchColumn();
                $resourcesAdded++;
                
                // Process tags
                if (!empty($tagsString)) {
                    $tagsList = array_map('trim', explode(',', $tagsString));
                    
                    foreach ($tagsList as $tag) {
                        if (empty($tag)) continue;
                        
                        // Check if tag exists
                        $tagId = $this->getOrCreateTag($tag);
                        
                        // Link tag to resource
                        $stmt = $this->conn->prepare("
                            INSERT INTO resource_tags (resource_id, tag_id)
                            VALUES (:resource_id, :tag_id)
                            ON CONFLICT DO NOTHING
                        ");
                        
                        $stmt->bindParam(':resource_id', $resourceId);
                        $stmt->bindParam(':tag_id', $tagId);
                        $stmt->execute();
                    }
                }
            }
            
            fclose($fileHandle);
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully imported $resourcesAdded resources to database"
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            error_log("CSV import failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Import failed: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get or create a tag and return its ID
     */
    private function getOrCreateTag($tagName) {
        // Check if tag exists
        $stmt = $this->conn->prepare("
            SELECT id FROM tags WHERE name = :name
        ");
        $stmt->bindParam(':name', $tagName);
        $stmt->execute();
        
        $tagId = $stmt->fetchColumn();
        
        if (!$tagId) {
            // Create new tag
            $stmt = $this->conn->prepare("
                INSERT INTO tags (name) 
                VALUES (:name)
                RETURNING id
            ");
            $stmt->bindParam(':name', $tagName);
            $stmt->execute();
            $tagId = $stmt->fetchColumn();
        }
        
        return $tagId;
    }
    
    /**
     * Get resources with optional search and tag filtering
     */
    public function getResources($searchTerms = [], $selectedTags = [], $page = 1, $itemsPerPage = 10) {
        if (!$this->initialized) {
            return [
                'success' => false,
                'message' => 'Database not initialized',
                'resources' => [],
                'total' => 0,
                'tags' => []
            ];
        }
        
        try {
            // Get all tags for filter display
            $stmt = $this->conn->query("SELECT name FROM tags ORDER BY name");
            $allTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Debug - log all available tags
            error_log("All available tags in database: " . implode(", ", $allTags));
            
            // Build query
            $baseQuery = "
                SELECT DISTINCT r.id, r.name, r.phone, r.description, r.website
                FROM resources r
            ";
            
            $countQuery = "
                SELECT COUNT(DISTINCT r.id)
                FROM resources r
            ";
            
            $params = [];
            $whereClauses = [];
            
            // Add tag filtering
            if (!empty($selectedTags)) {
                $baseQuery .= "
                    JOIN resource_tags rt ON r.id = rt.resource_id
                    JOIN tags t ON rt.tag_id = t.id
                ";
                $countQuery .= "
                    JOIN resource_tags rt ON r.id = rt.resource_id
                    JOIN tags t ON rt.tag_id = t.id
                ";
                
                $tagPlaceholders = [];
                $hasLgbtqTag = false;
                
                // Prepare normalized tag values and check for LGBTQ+ tag
                for ($i = 0; $i < count($selectedTags); $i++) {
                    // Convert to lowercase for case-insensitive comparison in SQL
                    $normalizedTag = strtolower(str_replace(' ', '+', $selectedTags[$i]));
                    
                    // Special handling for LGBTQ+ tag
                    if (stripos($normalizedTag, 'lgbtq') !== false) {
                        $hasLgbtqTag = true;
                        error_log("DB Query - LGBTQ+ tag detected in filter: " . $selectedTags[$i]);
                    }
                    
                    $tagPlaceholders[] = ":tag$i";
                    $params["tag$i"] = $normalizedTag;
                }
                
                // Note: We don't need the first IN clause which would match ANY of the tags
                // $whereClauses[] = "t.name IN (" . implode(", ", $tagPlaceholders) . ")";
                
                // Always include resources that have ALL the selected tags, not just any of them
                if ($hasLgbtqTag) {
                    // Special SQL for LGBTQ+ case - we'll add custom handling
                    $specialTagSubqueries = [];
                    
                    for ($i = 0; $i < count($selectedTags); $i++) {
                        $tagValue = $selectedTags[$i];
                        if (stripos($tagValue, 'lgbtq') !== false) {
                            // For LGBTQ+ tags, use ILIKE for partial matching
                            $specialTagSubqueries[] = "
                                EXISTS (
                                    SELECT 1 FROM resource_tags rt_special 
                                    JOIN tags t_special ON rt_special.tag_id = t_special.id 
                                    WHERE rt_special.resource_id = r.id 
                                    AND (
                                        LOWER(t_special.name) ILIKE '%lgbtq%' 
                                        OR LOWER(t_special.name) = 'lgbtq+'
                                    )
                                )
                            ";
                        } else {
                            // For regular tags, use exact matching 
                            $specialTagSubqueries[] = "
                                EXISTS (
                                    SELECT 1 FROM resource_tags rt_special 
                                    JOIN tags t_special ON rt_special.tag_id = t_special.id 
                                    WHERE rt_special.resource_id = r.id 
                                    AND LOWER(t_special.name) = :special_tag$i
                                )
                            ";
                            $params["special_tag$i"] = strtolower($tagValue);
                        }
                    }
                    
                    // Combine all conditions with AND
                    $whereClauses[] = "(" . implode(" AND ", $specialTagSubqueries) . ")";
                    
                    // Debug
                    error_log("DB Query - Using special LGBTQ+ handling with subqueries");
                } else {
                    // Regular tag filtering
                    $tagCountSubquery = "
                        SELECT resource_id 
                        FROM resource_tags rt2 
                        JOIN tags t2 ON rt2.tag_id = t2.id 
                        WHERE LOWER(t2.name) IN (" . implode(", ", $tagPlaceholders) . ")
                        GROUP BY resource_id 
                        HAVING COUNT(DISTINCT LOWER(t2.name)) = " . count($selectedTags);
                        
                    $whereClauses[] = "r.id IN ($tagCountSubquery)";
                }
                
                // Debug - log the SQL query and the tag values
                error_log("Selected tags: " . implode(", ", $selectedTags));
                if (isset($tagCountSubquery)) {
                    error_log("Tag count subquery: " . $tagCountSubquery);
                } else {
                    error_log("Using special tag handling for LGBTQ+");
                }
                error_log("Total tags selected: " . count($selectedTags));
            }
            
            // Add search filtering
            if (!empty($searchTerms)) {
                $searchClauses = [];
                
                for ($i = 0; $i < count($searchTerms); $i++) {
                    $searchClauses[] = "(
                        r.name ILIKE :search$i OR 
                        r.description ILIKE :search$i OR 
                        r.phone ILIKE :search$i OR
                        r.website ILIKE :search$i OR
                        EXISTS (
                            SELECT 1 FROM resource_tags rt3 
                            JOIN tags t3 ON rt3.tag_id = t3.id 
                            WHERE rt3.resource_id = r.id AND t3.name ILIKE :search$i
                        )
                    )";
                    
                    $params["search$i"] = '%' . $searchTerms[$i] . '%';
                }
                
                if (!empty($searchClauses)) {
                    $whereClauses[] = '(' . implode(' AND ', $searchClauses) . ')';
                }
            }
            
            // Build final query with WHERE and ORDER clause
            if (!empty($whereClauses)) {
                $baseQuery .= " WHERE " . implode(' AND ', $whereClauses);
                $countQuery .= " WHERE " . implode(' AND ', $whereClauses);
            }
            
            $baseQuery .= " ORDER BY r.name";
            
            // Get total count
            $stmt = $this->conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $totalResources = $stmt->fetchColumn();
            
            // Add pagination
            $offset = ($page - 1) * $itemsPerPage;
            $baseQuery .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $itemsPerPage;
            $params['offset'] = $offset;
            
            // Get paginated resources
            $stmt = $this->conn->prepare($baseQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get tags for each resource
            $resourcesWithTags = [];
            foreach ($resources as $resource) {
                $stmt = $this->conn->prepare("
                    SELECT t.name 
                    FROM tags t
                    JOIN resource_tags rt ON t.id = rt.tag_id
                    WHERE rt.resource_id = :resource_id
                    ORDER BY t.name
                ");
                $stmt->bindValue(':resource_id', $resource['id']);
                $stmt->execute();
                $resourceTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $resource['tags'] = $resourceTags;
                $resourcesWithTags[] = $resource;
            }
            
            return [
                'success' => true,
                'resources' => $resourcesWithTags,
                'total' => $totalResources,
                'tags' => $allTags,
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
                'totalPages' => ceil($totalResources / $itemsPerPage)
            ];
            
        } catch (PDOException $e) {
            error_log("Get resources failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Database error: " . $e->getMessage(),
                'resources' => [],
                'total' => 0,
                'tags' => []
            ];
        }
    }
    
    /**
     * Check if database connection is initialized
     */
    public function isInitialized() {
        return $this->initialized;
    }
}