<?php
namespace DatabasePlugin;

class PaginationHandler {
    /**
     * Default number of items per page
     * @var int
     */
    const DEFAULT_ITEMS_PER_PAGE = 10;
    
    /**
     * Current page number
     * @var int
     */
    private $currentPage = 1;
    
    /**
     * Total number of pages
     * @var int
     */
    private $totalPages = 1;
    
    /**
     * Number of items per page
     * @var int
     */
    private $itemsPerPage;
    
    /**
     * Total number of items
     * @var int
     */
    private $totalItems = 0;
    
    /**
     * Constructor
     * @param int $itemsPerPage Number of items per page (optional)
     */
    public function __construct($itemsPerPage = null) {
        $this->itemsPerPage = $itemsPerPage ?? self::DEFAULT_ITEMS_PER_PAGE;
        $this->currentPage = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
    }
    
    /**
     * Set up pagination
     * @param int $totalItems Total number of items
     */
    public function setup($totalItems) {
        $this->totalItems = max(0, intval($totalItems));
        $this->totalPages = max(1, ceil($this->totalItems / $this->itemsPerPage));
        $this->currentPage = min($this->currentPage, $this->totalPages);
    }
    
    /**
     * Get paginated items
     * @param array $items Array of all items
     * @return array Paginated subset of items
     */
    public function getPaginatedItems($items) {
        $startIndex = ($this->currentPage - 1) * $this->itemsPerPage;
        return array_slice($items, $startIndex, $this->itemsPerPage);
    }
    
    /**
     * Render pagination controls
     * @return string HTML for pagination controls
     */
    public function renderControls() {
        if ($this->totalPages <= 1) {
            return '';
        }
        
        ob_start();
        
        echo '<div class="pagination" role="navigation" aria-label="Resource list pagination">';
        
        // Previous page button
        if ($this->currentPage > 1) {
            printf(
                '<button type="button" class="page-np" onclick="changePage(%d)" aria-label="Go to previous page">&lt;</button>',
                $this->currentPage - 1
            );
        }
        
        // Page numbers
        $paginationRange = 2; // Show 2 pages before and after current page
        for ($i = max(1, $this->currentPage - $paginationRange); 
             $i <= min($this->totalPages, $this->currentPage + $paginationRange); 
             $i++) {
            
            if ($i == $this->currentPage) {
                printf(
                    '<span class="current-page" aria-current="page">%d</span>',
                    $i
                );
            } else {
                printf(
                    '<button type="button" class="page-n" onclick="changePage(%d)" aria-label="Go to page %d">%d</button>',
                    $i, $i, $i
                );
            }
        }
        
        // Next page button
        if ($this->currentPage < $this->totalPages) {
            printf(
                '<button type="button" class="page-np" onclick="changePage(%d)" aria-label="Go to next page">&gt;</button>',
                $this->currentPage + 1
            );
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Get current page
     * @return int Current page number
     */
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    /**
     * Get total pages
     * @return int Total number of pages
     */
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    /**
     * Get items per page
     * @return int Number of items per page
     */
    public function getItemsPerPage() {
        return $this->itemsPerPage;
    }
    
    /**
     * Get total items
     * @return int Total number of items
     */
    public function getTotalItems() {
        return $this->totalItems;
    }
}