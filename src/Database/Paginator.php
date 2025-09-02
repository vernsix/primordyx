<?php
/**
 * File: /vendor/vernsix/primordyx/src/Paginator.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Paginator.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

/**
 * Pagination metadata container with navigation URL generation and integration support
 *
 * Comprehensive pagination solution providing metadata storage, navigation logic, and URL
 * generation for paginated data sets. Designed as immutable data container with helper methods
 * for seamless integration with Model, QueryBuilder, and web application pagination interfaces.
 *
 * ## Pagination Architecture
 * - **Immutable Data Container**: Stores pagination metadata without modification after construction
 * - **URL Generation**: Creates navigation URLs with query parameter preservation
 * - **Framework Integration**: Seamless integration with Model and QueryBuilder pagination methods
 * - **Boundary Handling**: Proper null returns for invalid navigation scenarios
 * - **Flexible Construction**: Support for automatic or manual page count calculation
 *
 * ## Data Storage Model
 * The Paginator maintains complete pagination state through public properties:
 * - Current page data subset (arrays or model instances)
 * - Total item count across all pages
 * - Items per page configuration
 * - Current page number (1-based indexing)
 * - Total page count (calculated or provided)
 *
 * ## Integration Patterns
 * Paginator instances are typically created by:
 * - **Model::paginate()**: Raw data pagination from database queries
 * - **Model::paginateAsModels()**: Model instance pagination with object conversion
 * - **QueryBuilder::paginate()**: Direct query builder pagination support
 * - **Manual Construction**: Custom pagination scenarios with external data sources
 *
 * ## Navigation Logic
 * Navigation methods provide safe boundary checking:
 * - Next/Previous URL generation with automatic boundary detection
 * - Query parameter preservation for search filters and sorting
 * - Null returns for invalid navigation attempts (first/last page boundaries)
 * - Consistent URL format with 'page' and 'limit' parameters
 *
 * ## Page Calculation Algorithm
 * Total pages calculated using: `ceil(total_items / items_per_page)`
 * - Handles edge cases: empty results, single page, fractional pages
 * - Supports override for custom page calculation scenarios
 * - 1-based page numbering throughout the system
 *
 * @since 1.0.0
 *
 * @example Model Integration - Raw Data
 * ```php
 * $paginator = (new User())
 *     ->where('active', 1)
 *     ->orderBy('name', 'ASC')
 *     ->paginate(20, 2); // Page 2, 20 items per page
 *
 * echo "Showing {$paginator->currentPage} of {$paginator->pages} pages\n";
 * echo "Total users: {$paginator->total}\n";
 *
 * foreach ($paginator->data as $userData) {
 *     echo "User: {$userData['name']}\n";
 * }
 * ```
 *
 * @example Model Integration - Object Data
 * ```php
 * $userPaginator = (new User())
 *     ->where('role', 'customer')
 *     ->paginateAsModels(15, 1);
 *
 * foreach ($userPaginator->data as $user) {
 *     echo "Customer: {$user->name} - {$user->email}\n";
 *     $user->updateLastViewed(); // Model methods available
 * }
 * ```
 *
 * @example Navigation URL Generation
 * ```php
 * // Generate navigation URLs with preserved search parameters
 * $searchParams = ['q' => $_GET['search'], 'category' => $_GET['category']];
 * $baseUrl = '/products';
 *
 * $nextUrl = $paginator->nextUrl($baseUrl, $searchParams);
 * $prevUrl = $paginator->prevUrl($baseUrl, $searchParams);
 *
 * if ($prevUrl) echo "<a href='$prevUrl'>Previous</a> ";
 * echo "Page {$paginator->currentPage} of {$paginator->pages}";
 * if ($nextUrl) echo " <a href='$nextUrl'>Next</a>";
 * ```
 *
 * @example Complete Pagination Interface
 * ```php
 * function renderPagination(Paginator $p, string $baseUrl, array $params = []): string {
 *     $html = "<div class='pagination'>";
 *
 *     // Previous link
 *     if ($prevUrl = $p->prevUrl($baseUrl, $params)) {
 *         $html .= "<a href='$prevUrl' class='btn'>Previous</a>";
 *     }
 *
 *     // Page info
 *     $html .= "<span>Page {$p->currentPage} of {$p->pages} ";
 *     $html .= "({$p->total} total items)</span>";
 *
 *     // Next link
 *     if ($p->hasMore()) {
 *         $nextUrl = $p->nextUrl($baseUrl, $params);
 *         $html .= "<a href='$nextUrl' class='btn'>Next</a>";
 *     }
 *
 *     return $html . "</div>";
 * }
 * ```
 *
 * @example Manual Construction for External APIs
 * ```php
 * // Paginate external API results
 * $apiResponse = callExternalAPI($endpoint, $page);
 * $paginator = new Paginator(
 *     $apiResponse['results'],     // Current page data
 *     $apiResponse['total_count'], // Total available items
 *     50,                         // Items per page
 *     $page,                      // Current page number
 *     $apiResponse['page_count']   // Total pages from API
 * );
 *
 * displayResults($paginator);
 * ```
 *
 * @see Model::paginate() For database result pagination
 * @see Model::paginateAsModels() For model instance pagination
 * @see QueryBuilder::paginate() For query-level pagination
 */
class Paginator
{
    /**
     * Current page data subset as array of records or model instances
     *
     * Contains the actual data items for the current page, either as associative arrays
     * (from raw database results) or as Model instances (from paginateAsModels).
     * The exact data structure depends on the originating pagination method.
     *
     * ## Data Types
     * - **Raw Arrays**: From Model::paginate() and QueryBuilder::paginate()
     * - **Model Instances**: From Model::paginateAsModels()
     * - **Mixed Data**: From manual Paginator construction
     *
     * ## Empty Page Handling
     * Empty array when no results exist for current page or when total results
     * are zero. Safe for foreach iteration and array operations.
     *
     * @var array Current page data items (arrays or objects)
     * @since 1.0.0
     */
    public array $data;

    /**
     * Total number of items across all pages in complete dataset
     *
     * Represents full result count before pagination limits are applied. Used for
     * calculating total page count and providing result statistics to users.
     * Essential for navigation logic and pagination interface rendering.
     *
     * ## Calculation Source
     * - **Database Count**: From COUNT(*) queries in Model/QueryBuilder pagination
     * - **External APIs**: From API response total count fields
     * - **Manual Construction**: Provided explicitly during instantiation
     *
     * ## Usage in Templates
     * Display total results: "Showing 21-40 of 157 results"
     * Calculate result ranges and pagination statistics
     *
     * @var int Total items across all pages (0 for empty results)
     * @since 1.0.0
     */
    public int $total;

    /**
     * Maximum number of items displayed per page
     *
     * Defines page size for pagination calculations and navigation logic.
     * Determines how many items are included in each page and affects total
     * page count calculation. Consistent across all pages in pagination sequence.
     *
     * ## Usage in Calculations
     * - **Page Count**: `ceil(total / perPage)` for total pages
     * - **Offset Calculation**: `(currentPage - 1) * perPage` for database queries
     * - **Range Display**: "Items 21-40" calculations
     *
     * ## URL Generation
     * Included as 'limit' parameter in generated navigation URLs to maintain
     * consistent page sizing across navigation actions.
     *
     * @var int Items per page (positive integer)
     * @since 1.0.0
     */
    public int $perPage;

    /**
     * Current page number using 1-based indexing
     *
     * Represents the currently displayed page within the pagination sequence.
     * Uses 1-based numbering for human-friendly display and URL parameters.
     * Essential for navigation boundary checking and URL generation logic.
     *
     * ## Index Convention
     * - **First Page**: 1 (not 0)
     * - **Page Numbers**: Sequential integers starting from 1
     * - **URL Parameters**: Matches 'page' parameter in generated URLs
     *
     * ## Boundary Conditions
     * - **Minimum**: 1 (first page)
     * - **Maximum**: $pages (last available page)
     * - **Navigation**: Used to determine next/previous availability
     *
     * @var int Current page number (1-based indexing)
     * @since 1.0.0
     */
    public int $currentPage;

    /**
     * Total number of pages in complete pagination sequence
     *
     * Calculated total of pages needed to display all items based on items per page
     * configuration. Used for navigation boundary checking and pagination interface
     * rendering. Can be manually overridden during construction for custom scenarios.
     *
     * ## Calculation Method
     * Default: `ceil(total / perPage)` for automatic calculation
     * - **Empty Results**: 0 pages when total is 0
     * - **Partial Pages**: Rounds up fractional pages (e.g., 2.3 pages → 3 pages)
     * - **Single Page**: 1 when total ≤ perPage
     *
     * ## Override Support
     * Manual override available in constructor for external pagination systems
     * that provide pre-calculated page counts (APIs, cached results).
     *
     * ## Navigation Logic
     * Used by hasMore() and URL generation methods to prevent invalid navigation:
     * - Next page available when currentPage < pages
     * - Previous page available when currentPage > 1
     *
     * @var int Total pages in pagination sequence (0 for empty results)
     * @since 1.0.0
     */
    public int $pages;

    /**
     * Initialize pagination container with data and metadata calculation
     *
     * Creates immutable pagination instance with provided data and metadata.
     * Performs automatic page count calculation unless explicitly overridden.
     * Establishes complete pagination state for navigation and display operations.
     *
     * ## Automatic Calculations
     * - **Page Count**: `ceil(total / perPage)` when pages parameter is null
     * - **Boundary Validation**: Ensures sensible defaults for edge cases
     * - **Immutable State**: All properties set during construction, no later modification
     *
     * ## Constructor Flexibility
     * - **Manual Page Count**: Override automatic calculation for external systems
     * - **Mixed Data Types**: Supports arrays, objects, or mixed data structures
     * - **Zero Results**: Handles empty datasets gracefully
     *
     * ## Integration Patterns
     * Typically called by framework pagination methods rather than directly:
     * - Model::paginate() provides raw array data
     * - Model::paginateAsModels() provides model instance data
     * - Custom construction for external APIs or cached results
     *
     * @param array $data Current page data subset (arrays or objects)
     * @param int $total Total number of items across all pages
     * @param int $perPage Maximum items per page for pagination calculation
     * @param int $currentPage Current page number (1-based indexing)
     * @param int|null $pages Optional override for total pages (calculated if null)
     * @since 1.0.0
     *
     * @example Framework Integration (Typical)
     * ```php
     * // Called internally by Model::paginate()
     * [$rows, $total, $pages] = $queryBuilder->paginate(20, 2, $db);
     * $paginator = new Paginator($rows, $total, 20, 2, $pages);
     * ```
     *
     * @example Manual Construction for API Data
     * ```php
     * $apiResponse = [
     *     'items' => [['name' => 'Product 1'], ['name' => 'Product 2']],
     *     'total' => 150,
     *     'page_count' => 8
     * ];
     *
     * $paginator = new Paginator(
     *     $apiResponse['items'],    // Current page data
     *     $apiResponse['total'],    // Total items
     *     20,                       // Items per page
     *     2,                        // Current page
     *     $apiResponse['page_count'] // Explicit page count
     * );
     * ```
     *
     * @example Automatic Page Calculation
     * ```php
     * // Constructor calculates: ceil(47 / 10) = 5 pages
     * $paginator = new Paginator(
     *     $currentPageData,  // 10 items for current page
     *     47,               // Total items across all pages
     *     10,               // Items per page
     *     3                 // Current page (null for pages = auto-calculate)
     * );
     * // $paginator->pages === 5
     * ```
     */
    public function __construct(array $data, int $total, int $perPage, int $currentPage, ?int $pages = null)
    {
        $this->data = $data;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->pages = $pages ?? (int) ceil($total / $perPage);
    }

    /**
     * Generate URL for next page with query parameter preservation
     *
     * Creates complete URL for next page navigation including preserved query parameters
     * and pagination metadata. Returns null when already on last page to prevent
     * invalid navigation attempts. Merges provided parameters with pagination parameters.
     *
     * ## URL Construction
     * - **Base URL**: Provided base URL without modification
     * - **Query Parameters**: Merged additional params + 'page' + 'limit'
     * - **Parameter Priority**: Additional params override pagination params if conflicting
     * - **URL Format**: Standard HTTP query string format via http_build_query()
     *
     * ## Boundary Checking
     * Performs automatic boundary validation:
     * - Returns null when currentPage >= pages (already on last page)
     * - Next page calculated as currentPage + 1
     * - Safe navigation without manual boundary checking
     *
     * ## Parameter Preservation
     * Maintains search, filter, and sorting parameters across navigation:
     * - Existing query parameters preserved in URL
     * - Search terms, filters, and sort options maintained
     * - Enables consistent user experience during navigation
     *
     * @param string $baseUrl Base URL for pagination (without query parameters)
     * @param array<string, mixed> $params Additional query parameters to preserve
     * @return string|null Complete URL for next page or null if on last page
     * @since 1.0.0
     *
     * @example Basic Next Page Navigation
     * ```php
     * $nextUrl = $paginator->nextUrl('/products');
     * // Result: '/products?page=3&limit=20' (if on page 2)
     *
     * if ($nextUrl) {
     *     echo "<a href='$nextUrl'>Next Page</a>";
     * }
     * ```
     *
     * @example Preserved Search Parameters
     * ```php
     * $searchParams = [
     *     'q' => 'laptop',
     *     'category' => 'electronics',
     *     'sort' => 'price_asc'
     * ];
     *
     * $nextUrl = $paginator->nextUrl('/search', $searchParams);
     * // Result: '/search?q=laptop&category=electronics&sort=price_asc&page=3&limit=20'
     * ```
     *
     * @example Navigation Menu with Boundary Handling
     * ```php
     * function renderNavigation(Paginator $p, string $baseUrl, array $params = []): string {
     *     $nav = "<nav class='pagination'>";
     *
     *     // Previous link
     *     if ($prevUrl = $p->prevUrl($baseUrl, $params)) {
     *         $nav .= "<a href='$prevUrl' class='prev'>« Previous</a>";
     *     }
     *
     *     // Page indicator
     *     $nav .= "<span class='info'>Page {$p->currentPage} of {$p->pages}</span>";
     *
     *     // Next link
     *     if ($nextUrl = $p->nextUrl($baseUrl, $params)) {
     *         $nav .= "<a href='$nextUrl' class='next'>Next »</a>";
     *     }
     *
     *     return $nav . "</nav>";
     * }
     * ```
     *
     * @see prevUrl() For previous page navigation
     * @see hasMore() For boolean next page availability
     */
    public function nextUrl(string $baseUrl, array $params = []): ?string
    {
        if ($this->currentPage >= $this->pages) return null;
        $params = array_merge($params, ['page' => $this->currentPage + 1, 'limit' => $this->perPage]);
        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Generate URL for previous page with query parameter preservation
     *
     * Creates complete URL for previous page navigation with preserved query parameters
     * and pagination metadata. Returns null when already on first page to prevent
     * invalid navigation attempts. Maintains consistent URL format with nextUrl().
     *
     * ## URL Construction Process
     * - **Base URL**: Uses provided base URL as foundation
     * - **Query Merging**: Combines additional params with pagination parameters
     * - **Page Calculation**: Previous page = currentPage - 1
     * - **URL Encoding**: Proper query string encoding via http_build_query()
     *
     * ## First Page Boundary
     * Automatic boundary checking prevents invalid navigation:
     * - Returns null when currentPage <= 1 (already on first page)
     * - No need for manual boundary validation in templates
     * - Consistent behavior with nextUrl() boundary handling
     *
     * ## Search and Filter Preservation
     * Maintains user's current search context during navigation:
     * - Search queries preserved across page changes
     * - Filter selections maintained
     * - Sort preferences carried forward
     *
     * @param string $baseUrl Base URL for pagination (without query parameters)
     * @param array<string, mixed> $params Additional query parameters to preserve
     * @return string|null Complete URL for previous page or null if on first page
     * @since 1.0.0
     *
     * @example Basic Previous Page Navigation
     * ```php
     * $prevUrl = $paginator->prevUrl('/users');
     * // Result: '/users?page=1&limit=25' (if on page 2)
     *
     * if ($prevUrl) {
     *     echo "<a href='$prevUrl' class='btn-prev'>« Previous</a>";
     * } else {
     *     echo "<span class='btn-prev disabled'>« Previous</span>";
     * }
     * ```
     *
     * @example Complex Parameter Preservation
     * ```php
     * // Current URL: /reports?department=sales&date_from=2024-01-01&status=active&page=5
     * $filterParams = [
     *     'department' => $_GET['department'],
     *     'date_from' => $_GET['date_from'],
     *     'status' => $_GET['status']
     * ];
     *
     * $prevUrl = $paginator->prevUrl('/reports', $filterParams);
     * // Result: '/reports?department=sales&date_from=2024-01-01&status=active&page=4&limit=20'
     * ```
     *
     * @example Complete Pagination Controls
     * ```php
     * function buildPaginationLinks(Paginator $p, string $url, array $params): array {
     *     return [
     *         'first' => $p->currentPage > 1 ? $url . '?' . http_build_query(array_merge($params, ['page' => 1, 'limit' => $p->perPage])) : null,
     *         'prev' => $p->prevUrl($url, $params),
     *         'current' => $p->currentPage,
     *         'next' => $p->nextUrl($url, $params),
     *         'last' => $p->currentPage < $p->pages ? $url . '?' . http_build_query(array_merge($params, ['page' => $p->pages, 'limit' => $p->perPage])) : null,
     *         'total_pages' => $p->pages
     *     ];
     * }
     * ```
     *
     * @see nextUrl() For next page navigation
     * @see hasMore() For checking additional page availability
     */
    public function prevUrl(string $baseUrl, array $params = []): ?string
    {
        if ($this->currentPage <= 1) return null;
        $params = array_merge($params, ['page' => $this->currentPage - 1, 'limit' => $this->perPage]);
        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Check if additional pages exist beyond the current page
     *
     * Boolean convenience method for determining next page availability without
     * generating URLs. Useful for conditional logic, navigation enabling/disabling,
     * and pagination interface state management.
     *
     * ## Comparison Logic
     * Returns true when currentPage < pages, indicating:
     * - More pages available for navigation
     * - Next page button should be enabled
     * - Additional data exists beyond current page
     *
     * ## Use Cases
     * - **Conditional Navigation**: Show/hide next page controls
     * - **JavaScript Integration**: Enable/disable navigation buttons
     * - **Template Logic**: Conditional rendering without URL generation overhead
     * - **API Responses**: Include hasMore flag in pagination metadata
     *
     * ## Performance Advantage
     * Lightweight boolean check without URL generation overhead when only
     * availability status is needed, not actual navigation URLs.
     *
     * @return bool True if more pages exist after current page, false otherwise
     * @since 1.0.0
     *
     * @example Conditional Navigation Controls
     * ```php
     * echo "<div class='pagination-controls'>";
     *
     * // Previous button with state management
     * if ($paginator->currentPage > 1) {
     *     echo "<button onclick=\"goToPage({$paginator->currentPage - 1})\">Previous</button>";
     * } else {
     *     echo "<button disabled>Previous</button>";
     * }
     *
     * echo " Page {$paginator->currentPage} of {$paginator->pages} ";
     *
     * // Next button with hasMore() check
     * if ($paginator->hasMore()) {
     *     echo "<button onclick=\"goToPage({$paginator->currentPage + 1})\">Next</button>";
     * } else {
     *     echo "<button disabled>Next</button>";
     * }
     *
     * echo "</div>";
     * ```
     *
     * @example API Response Metadata
     * ```php
     * function paginationApiResponse(Paginator $p): array {
     *     return [
     *         'data' => $p->data,
     *         'pagination' => [
     *             'current_page' => $p->currentPage,
     *             'per_page' => $p->perPage,
     *             'total' => $p->total,
     *             'total_pages' => $p->pages,
     *             'has_more' => $p->hasMore(),
     *             'has_previous' => $p->currentPage > 1
     *         ]
     *     ];
     * }
     * ```
     *
     * @example Template Helper Integration
     * ```php
     * // In template helper or view component
     * class PaginationHelper {
     *     public static function renderLoadMoreButton(Paginator $p, string $targetUrl): string {
     *         if (!$p->hasMore()) {
     *             return '<p class="end-of-results">No more results to load</p>';
     *         }
     *
     *         $nextPage = $p->currentPage + 1;
     *         return "<button class='load-more' data-page='$nextPage' data-url='$targetUrl'>
 *                    Load More Results ({$p->perPage} more items)
     *                </button>";
 *     }
     * }
     * ```
     *
     * @example Infinite Scroll Integration
     * ```php
     * // JavaScript integration data
     * $scrollConfig = [
     *     'hasMore' => $paginator->hasMore(),
     *     'nextPage' => $paginator->hasMore() ? $paginator->currentPage + 1 : null,
     *     'totalPages' => $paginator->pages,
     *     'loadUrl' => '/api/load-more'
     * ];
     *
     * echo "<script>window.paginationConfig = " . json_encode($scrollConfig) . ";</script>";
     * ```
     *
     * @see nextUrl() For actual next page URL generation
     * @see prevUrl() For previous page navigation
     */
    public function hasMore(): bool
    {
        return $this->currentPage < $this->pages;
    }
}
