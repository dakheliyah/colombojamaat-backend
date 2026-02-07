# Reporting API - Report Results Viewer & Export UI Prompt

## Overview
Create a React component for displaying report results in a table/grid format with advanced features like column sorting, filtering, pagination, and CSV export. This component works in conjunction with the Report Builder to display and export query results.

## API Endpoints

### Query Report (JSON)
```
GET /api/reports/{entity_type}?filters...&format=json&page=1&per_page=15&sort_by=name&sort_order=asc&include=hof,members
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "its_id": "123456",
        "name": "John Doe",
        "city": "Mumbai",
        "amount": 5000.00,
        "status": true
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 150,
      "last_page": 10,
      "from": 1,
      "to": 15
    }
  }
}
```

### Export to CSV
```
GET /api/reports/{entity_type}?filters...&format=csv&fields=its_id,name,amount&sort_by=name
```
**Response:** CSV file download (StreamedResponse)

## UI Component Structure

### Main Component: `ReportResultsViewer`

**Props:**
```typescript
interface ReportResultsViewerProps {
  // Report configuration (from Report Builder)
  entityType: string;
  filters: Record<string, any>;
  selectedFields?: string[];
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
  
  // Display options
  showExportButton?: boolean;
  showColumnSelector?: boolean;
  showPagination?: boolean;
  defaultPerPage?: number;
  
  // Callbacks
  onRowClick?: (row: any) => void;
  onExport?: (format: 'csv' | 'json') => void;
  onSortChange?: (field: string, order: 'asc' | 'desc') => void;
  onPageChange?: (page: number) => void;
  
  // Initial data (optional, if data is pre-loaded)
  initialData?: any[];
  initialPagination?: PaginationInfo;
}
```

## UI/UX Requirements

### 1. Data Table/Grid
- **Responsive table** that works on all screen sizes
- **Column headers** with:
  - Column name (human-readable)
  - Sort indicator (↑ ↓) when sortable
  - Click to sort functionality
- **Row styling:**
  - Alternating row colors for readability
  - Hover effect
  - Optional: Row selection (checkbox)
- **Cell content:**
  - Format dates properly (YYYY-MM-DD HH:mm:ss)
  - Format numbers with commas (e.g., 5,000.00)
  - Display booleans as Yes/No or icons
  - Handle null/empty values gracefully
  - Truncate long text with ellipsis and tooltip

### 2. Column Management
- **Column visibility toggle:**
  - Show/hide columns dropdown or sidebar
  - Remember user preferences (localStorage)
  - "Select All" / "Deselect All" options
- **Column reordering** (optional, drag-and-drop)
- **Column width adjustment** (optional, resizable columns)
- **Frozen columns** (optional, for large tables - keep key columns visible)

### 3. Sorting
- **Client-side sorting** (for current page) or **server-side sorting** (via API)
- **Multi-column sorting** (optional, if API supports it)
- **Sort indicator:**
  - ↑ for ascending
  - ↓ for descending
  - ↕ for unsorted (default)
- **Click column header** to toggle sort order
- **Visual feedback** during sort operation

### 4. Pagination
- **Pagination controls:**
  - First / Previous / Next / Last buttons
  - Page number input (jump to page)
  - Per page selector (15, 25, 50, 100, 250, 500, 1000)
  - Display: "Showing X to Y of Z results"
  - Page info: "Page 1 of 10"
- **URL state management** (optional):
  - Update URL query params when page changes
  - Allow bookmarking/sharing of specific page
- **Loading state** during page change

### 5. Search/Filter (Client-side)
- **Global search** across all visible columns
- **Column-specific filters** (optional):
  - Text input for strings
  - Number range for numbers
  - Date range for dates
  - Dropdown for enums
- **Filter chips** showing active filters
- **Clear filters** button

### 6. Export Functionality
- **Export to CSV button:**
  - Icon: Download/Export
  - Shows loading state during export
  - Handles large file downloads
  - Progress indicator (optional)
- **Export options dropdown** (optional):
  - Export current page
  - Export all results
  - Export selected rows (if row selection enabled)
- **Export format options** (if multiple formats supported):
  - CSV
  - JSON (download as file)
  - Excel (if backend supports it)

### 7. Relationship Data Display
- **Expandable rows** for relationships:
  - Show relationship data in nested/expandable format
  - Toggle expand/collapse
  - Example: Show "hof" relationship data when row is expanded
- **Relationship columns:**
  - Flatten relationship fields (e.g., `hof.name` → "HOF Name")
  - Display as separate columns

### 8. Empty States
- **No results:**
  - Message: "No results found"
  - Suggestion: "Try adjusting your filters"
  - Button: "Clear Filters"
- **Loading state:**
  - Skeleton loaders or spinner
  - Show "Loading..." message

### 9. Error Handling
- **API errors:**
  - Display error message
  - Retry button
  - Error details (optional, in development mode)
- **Network errors:**
  - Show connection error message
  - Retry button

## Component Breakdown

### Sub-components:

1. **DataTable**
   - Main table component
   - Handles rendering of rows and columns
   - Column sorting
   - Row selection

2. **TableHeader**
   - Column headers with sort indicators
   - Click handlers for sorting
   - Column width management

3. **TableRow**
   - Individual row component
   - Cell rendering
   - Row actions (expand, select, etc.)

4. **TableCell**
   - Individual cell component
   - Data formatting
   - Tooltip for truncated content

5. **PaginationControls**
   - Page navigation
   - Per page selector
   - Page info display

6. **ColumnSelector**
   - Show/hide columns
   - Column reordering (optional)

7. **ExportButton**
   - Export functionality
   - Loading state
   - Format selection

8. **SearchBar**
   - Global search input
   - Filter chips

9. **EmptyState**
   - No results message
   - Action buttons

## State Management Example

```typescript
const [data, setData] = useState<any[]>([]);
const [pagination, setPagination] = useState<PaginationInfo | null>(null);
const [isLoading, setIsLoading] = useState(false);
const [sortBy, setSortBy] = useState<string | null>(null);
const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
const [currentPage, setCurrentPage] = useState(1);
const [perPage, setPerPage] = useState(15);
const [visibleColumns, setVisibleColumns] = useState<string[]>([]);
const [searchQuery, setSearchQuery] = useState('');
const [isExporting, setIsExporting] = useState(false);

// Fetch data when filters/pagination/sorting changes
useEffect(() => {
  fetchReportData();
}, [entityType, filters, currentPage, perPage, sortBy, sortOrder]);

// Build query URL
const buildQueryUrl = () => {
  const params = new URLSearchParams();
  
  // Add filters
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== '') {
      params.append(key, String(value));
    }
  });
  
  // Add pagination
  params.append('page', currentPage.toString());
  params.append('per_page', perPage.toString());
  
  // Add sorting
  if (sortBy) {
    params.append('sort_by', sortBy);
    params.append('sort_order', sortOrder);
  }
  
  // Add format
  params.append('format', 'json');
  
  // Add relationships (if needed)
  if (includeRelationships.length > 0) {
    params.append('include', includeRelationships.join(','));
  }
  
  return `/api/reports/${entityType}?${params.toString()}`;
};

// Fetch report data
const fetchReportData = async () => {
  setIsLoading(true);
  try {
    const url = buildQueryUrl();
    const response = await fetch(url);
    const result = await response.json();
    
    if (result.success) {
      setData(result.data.data || result.data);
      setPagination(result.data.pagination || null);
    } else {
      setErrors([result.message || 'Failed to fetch data']);
    }
  } catch (error) {
    setErrors([error.message]);
  } finally {
    setIsLoading(false);
  }
};

// Handle column sort
const handleSort = (column: string) => {
  if (sortBy === column) {
    // Toggle order
    setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
  } else {
    // New column
    setSortBy(column);
    setSortOrder('asc');
  }
  setCurrentPage(1); // Reset to first page
};

// Handle page change
const handlePageChange = (page: number) => {
  setCurrentPage(page);
  // Scroll to top of table
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Handle per page change
const handlePerPageChange = (newPerPage: number) => {
  setPerPage(newPerPage);
  setCurrentPage(1); // Reset to first page
};

// Export to CSV
const handleExport = async () => {
  setIsExporting(true);
  try {
    const params = new URLSearchParams();
    
    // Add filters
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== null && value !== '') {
        params.append(key, String(value));
      }
    });
    
    // Add sorting
    if (sortBy) {
      params.append('sort_by', sortBy);
      params.append('sort_order', sortOrder);
    }
    
    // Add format
    params.append('format', 'csv');
    
    // Add fields (if specified)
    if (selectedFields && selectedFields.length > 0) {
      params.append('fields', selectedFields.join(','));
    }
    
    const url = `/api/reports/${entityType}?${params.toString()}`;
    const response = await fetch(url);
    const blob = await response.blob();
    
    // Create download
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = `${entityType}_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);
    
    // Show success message
    showToast('Export completed successfully');
  } catch (error) {
    showToast('Export failed: ' + error.message, 'error');
  } finally {
    setIsExporting(false);
  }
};
```

## Data Formatting

### Date Formatting
```typescript
const formatDate = (dateString: string | null): string => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
};
```

### Number Formatting
```typescript
const formatNumber = (value: number | null, decimals: number = 2): string => {
  if (value === null || value === undefined) return '-';
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  }).format(value);
};
```

### Boolean Formatting
```typescript
const formatBoolean = (value: boolean | null): string => {
  if (value === null || value === undefined) return '-';
  return value ? 'Yes' : 'No';
  // Or use icons: return value ? <CheckIcon /> : <XIcon />;
};
```

### Relationship Formatting
```typescript
const formatRelationship = (relationship: any, field: string): string => {
  if (!relationship) return '-';
  return relationship[field] || '-';
};

// Usage in cell:
{formatRelationship(row.hof, 'name')}
```

## Responsive Design

### Mobile View
- **Horizontal scroll** for table
- **Card view** as alternative (one row = one card)
- **Stacked pagination** controls
- **Collapsible filters**

### Tablet View
- **Table with horizontal scroll**
- **Side-by-side pagination** controls
- **Compact column selector**

### Desktop View
- **Full table** with all features
- **Sidebar for column selector**
- **All controls visible**

## Performance Optimization

1. **Virtual Scrolling:**
   - Use `react-window` or `react-virtualized` for large datasets
   - Only render visible rows

2. **Memoization:**
   - Memoize formatted cell values
   - Memoize filtered/sorted data

3. **Debouncing:**
   - Debounce search input
   - Debounce column filter inputs

4. **Lazy Loading:**
   - Load relationships on demand (when row expanded)
   - Paginate large result sets

## Accessibility

1. **Keyboard Navigation:**
   - Tab through table cells
   - Arrow keys to navigate rows
   - Enter to expand/collapse rows
   - Space to select rows

2. **Screen Readers:**
   - ARIA labels for sortable columns
   - ARIA labels for pagination
   - Announce page changes

3. **Focus Management:**
   - Maintain focus after page change
   - Focus on first cell after sort

## Example Usage

```tsx
<ReportResultsViewer
  entityType="wajebaat"
  filters={{
    miqaat_id: 1,
    status: false
  }}
  selectedFields={['its_id', 'name', 'amount', 'currency', 'status']}
  sortBy="amount"
  sortOrder="desc"
  showExportButton={true}
  showColumnSelector={true}
  showPagination={true}
  defaultPerPage={25}
  onRowClick={(row) => {
    // Navigate to detail page
    navigate(`/wajebaat/${row.id}`);
  }}
  onExport={(format) => {
    console.log(`Exporting as ${format}`);
  }}
/>
```

## Advanced Features (Optional)

1. **Row Actions:**
   - Context menu on right-click
   - Actions: View, Edit, Delete, etc.

2. **Bulk Actions:**
   - Select multiple rows
   - Bulk operations (delete, update, etc.)

3. **Column Grouping:**
   - Group columns by category
   - Expand/collapse groups

4. **Data Visualization:**
   - Charts/graphs for numeric data
   - Summary statistics

5. **Print View:**
   - Print-friendly layout
   - Remove interactive elements

## Testing Considerations

- Test with various data types (strings, numbers, dates, booleans)
- Test pagination with large datasets
- Test sorting on all columns
- Test CSV export
- Test responsive design
- Test accessibility (keyboard navigation, screen readers)
- Test error scenarios (API errors, network failures)
- Test performance with 1000+ rows
