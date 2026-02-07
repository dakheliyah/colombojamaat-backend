# Reporting API - Report Builder UI Prompt

## Overview
Create a React component for building and configuring reports using the Reporting API. This component allows users to select entity types, configure filters, choose fields, set sorting options, and preview reports before exporting.

## API Endpoints

### 1. Get Available Entity Types
```
GET /api/reports/entities
```
**Response:**
```json
{
  "success": true,
  "data": [
    { "type": "census", "description": "Census records" },
    { "type": "wajebaat", "description": "Wajebaat (Takhmeen) records" },
    { "type": "sharafs", "description": "Sharaf records" },
    { "type": "sharaf-members", "description": "Sharaf member records" },
    { "type": "sharaf-payments", "description": "Sharaf payment records" },
    { "type": "events", "description": "Event records" },
    { "type": "miqaat-checks", "description": "Miqaat check records" }
  ]
}
```

### 2. Get Available Fields
```
GET /api/reports/{entity_type}/fields
```
**Response:**
```json
{
  "success": true,
  "data": {
    "fields": ["its_id", "name", "city", "jamaat", "created_at"],
    "relationships": ["hof", "members"]
  }
}
```

### 3. Get Available Filters
```
GET /api/reports/{entity_type}/filters
```
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "name": "city",
      "type": "string",
      "operators": ["like", "equals"]
    },
    {
      "name": "status",
      "type": "boolean",
      "operators": ["equals"]
    },
    {
      "name": "amount_min",
      "type": "decimal",
      "operators": ["gte"]
    },
    {
      "name": "gender",
      "type": "enum",
      "values": ["male", "female"],
      "operators": ["equals"]
    }
  ]
}
```

### 4. Query Data (JSON Preview)
```
GET /api/reports/{entity_type}?filters...&format=json&page=1&per_page=15&sort_by=name&sort_order=asc&include=hof,members
```

### 5. Export to CSV
```
GET /api/reports/{entity_type}?filters...&format=csv&fields=its_id,name,amount&sort_by=name
```
**Response:** CSV file download (StreamedResponse)

## UI Component Structure

### Main Component: `ReportBuilder`

**Props:**
- `onReportGenerated?: (reportData: any) => void` - Callback when report is generated
- `initialEntityType?: string` - Pre-select an entity type
- `onExport?: (exportUrl: string) => void` - Callback for CSV export

**State Management:**
```typescript
interface ReportBuilderState {
  // Entity selection
  selectedEntityType: string | null;
  availableEntities: EntityType[];
  
  // Filter configuration
  filters: FilterConfig[];
  availableFilters: FilterDefinition[];
  
  // Field selection
  selectedFields: string[];
  availableFields: string[];
  availableRelationships: string[];
  
  // Sorting
  sortBy: string | null;
  sortOrder: 'asc' | 'desc';
  
  // Preview
  previewData: any[] | null;
  previewPagination: PaginationInfo | null;
  isLoadingPreview: boolean;
  
  // Format
  format: 'json' | 'csv';
  
  // UI state
  isLoadingMetadata: boolean;
  errors: string[];
}
```

## UI/UX Requirements

### 1. Entity Type Selection
- **Dropdown/Select** to choose entity type
- Display description for each entity type
- When entity type changes:
  - Clear all filters
  - Reset field selection
  - Load available filters and fields from API
  - Show loading state while fetching metadata

### 2. Filter Builder Section
- **Dynamic filter builder** that adapts based on selected entity type
- For each filter:
  - **Filter name** (from available filters)
  - **Operator selector** (based on filter type):
    - String: "equals", "like" (contains)
    - Number/Decimal: "equals", "gte" (≥), "lte" (≤)
    - Boolean: "equals"
    - Enum: "equals", "in" (multiple values)
  - **Value input** (type depends on filter type):
    - Text input for strings
    - Number input for numbers
    - Checkbox for booleans
    - Select/Dropdown for enums
    - Multi-select for "in" operator
  - **Remove button** to delete filter
- **Add Filter button** to add new filters
- Support for:
  - **Date range filters**: `date_from`, `date_to` (use date picker)
  - **Multi-value filters**: Comma-separated or array (e.g., `miqaat_id=1,2,3`)
  - **Range filters**: `amount_min`, `amount_max` (use two number inputs)

### 3. Field Selection Section
- **Checkbox list** or **multi-select dropdown** for available fields
- Show both direct fields and relationships
- **Select All / Deselect All** buttons
- For CSV export, selected fields determine CSV columns
- Display field count: "X fields selected"

### 4. Sorting Configuration
- **Sort by** dropdown (populated with available fields)
- **Sort order** toggle/select (asc/desc)
- Optional: Allow multiple sort fields (if API supports it in future)

### 5. Format Selection
- **Radio buttons** or **Toggle**: JSON (Preview) vs CSV (Export)
- When JSON is selected:
  - Show pagination controls
  - Enable relationship eager loading (`include` parameter)
- When CSV is selected:
  - Hide pagination
  - Show field selection (required for CSV columns)

### 6. Preview Section (JSON Format)
- **Table/Grid** displaying preview data
- **Pagination controls**:
  - Page number input
  - Per page selector (15, 25, 50, 100)
  - Previous/Next buttons
  - Display: "Showing X to Y of Z results"
- **Column sorting** (click column header to sort)
- **Loading state** while fetching preview
- **Empty state** when no results
- **Error state** for API errors

### 7. Action Buttons
- **Preview Report** button (JSON format)
  - Fetches first page of results
  - Updates preview table
- **Export to CSV** button (CSV format)
  - Triggers CSV download
  - Shows loading state during export
  - Handles large file downloads gracefully
- **Clear All** button
  - Resets all filters, fields, sorting
  - Clears preview
- **Save Report** button (optional, for future saved reports feature)

## Component Breakdown

### Sub-components:

1. **EntityTypeSelector**
   - Dropdown to select entity type
   - Shows loading state while fetching entities

2. **FilterBuilder**
   - Dynamic form for adding/removing filters
   - FilterRow component for each filter
   - Handles different input types based on filter definition

3. **FieldSelector**
   - Multi-select or checkbox list
   - Search/filter field list
   - Group by: Direct Fields vs Relationships

4. **SortingControls**
   - Sort field dropdown
   - Sort order toggle

5. **FormatSelector**
   - Radio buttons: JSON / CSV

6. **PreviewTable**
   - Data table with pagination
   - Column sorting
   - Responsive design

7. **ExportButton**
   - Handles CSV download
   - Shows progress for large exports

## State Management Example

```typescript
// Using React hooks
const [entityType, setEntityType] = useState<string | null>(null);
const [filters, setFilters] = useState<FilterConfig[]>([]);
const [selectedFields, setSelectedFields] = useState<string[]>([]);
const [sortBy, setSortBy] = useState<string | null>(null);
const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
const [format, setFormat] = useState<'json' | 'csv'>('json');
const [previewData, setPreviewData] = useState<any[]>([]);
const [pagination, setPagination] = useState<PaginationInfo | null>(null);

// Load metadata when entity type changes
useEffect(() => {
  if (entityType) {
    loadFilters(entityType);
    loadFields(entityType);
  }
}, [entityType]);

// Build query URL
const buildQueryUrl = () => {
  const params = new URLSearchParams();
  
  // Add filters
  filters.forEach(filter => {
    if (filter.value !== null && filter.value !== '') {
      params.append(filter.name, filter.value);
    }
  });
  
  // Add sorting
  if (sortBy) {
    params.append('sort_by', sortBy);
    params.append('sort_order', sortOrder);
  }
  
  // Add format
  params.append('format', format);
  
  // Add pagination (JSON only)
  if (format === 'json') {
    params.append('page', currentPage.toString());
    params.append('per_page', perPage.toString());
  }
  
  // Add fields (CSV only)
  if (format === 'csv' && selectedFields.length > 0) {
    params.append('fields', selectedFields.join(','));
  }
  
  return `/api/reports/${entityType}?${params.toString()}`;
};
```

## API Integration Example

```typescript
// Fetch available entities
const fetchEntities = async () => {
  const response = await fetch('/api/reports/entities');
  const data = await response.json();
  if (data.success) {
    setAvailableEntities(data.data);
  }
};

// Fetch available filters
const fetchFilters = async (entityType: string) => {
  const response = await fetch(`/api/reports/${entityType}/filters`);
  const data = await response.json();
  if (data.success) {
    setAvailableFilters(data.data);
  }
};

// Fetch available fields
const fetchFields = async (entityType: string) => {
  const response = await fetch(`/api/reports/${entityType}/fields`);
  const data = await response.json();
  if (data.success) {
    setAvailableFields(data.data.fields);
    setAvailableRelationships(data.data.relationships);
  }
};

// Preview report (JSON)
const previewReport = async () => {
  setIsLoadingPreview(true);
  try {
    const url = buildQueryUrl();
    const response = await fetch(url);
    const data = await response.json();
    if (data.success) {
      setPreviewData(data.data.data || data.data);
      setPagination(data.data.pagination || null);
    }
  } catch (error) {
    setErrors([error.message]);
  } finally {
    setIsLoadingPreview(false);
  }
};

// Export to CSV
const exportToCsv = async () => {
  setIsExporting(true);
  try {
    const url = buildQueryUrl();
    const response = await fetch(url);
    const blob = await response.blob();
    
    // Create download link
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = `${entityType}_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);
  } catch (error) {
    setErrors([error.message]);
  } finally {
    setIsExporting(false);
  }
};
```

## Filter Examples

### String Filter (like operator)
```typescript
{
  name: "city",
  operator: "like",
  value: "Mumbai"
}
// Query: ?city=Mumbai
```

### Number Range Filter
```typescript
{
  name: "amount_min",
  operator: "gte",
  value: 1000
},
{
  name: "amount_max",
  operator: "lte",
  value: 10000
}
// Query: ?amount_min=1000&amount_max=10000
```

### Multi-value Filter
```typescript
{
  name: "miqaat_id",
  operator: "in",
  value: [1, 2, 3]
}
// Query: ?miqaat_id=1,2,3
```

### Boolean Filter
```typescript
{
  name: "status",
  operator: "equals",
  value: true
}
// Query: ?status=1 (or true)
```

### Date Range Filter
```typescript
{
  name: "date_from",
  operator: "gte",
  value: "2024-01-01"
},
{
  name: "date_to",
  operator: "lte",
  value: "2024-12-31"
}
// Query: ?date_from=2024-01-01&date_to=2024-12-31
```

## UI Design Guidelines

1. **Layout:**
   - Left sidebar or top section: Entity selection, filters, field selection
   - Main area: Preview table (when JSON format)
   - Bottom: Action buttons (Preview, Export, Clear)

2. **Responsive Design:**
   - Mobile: Stack sections vertically
   - Tablet: Two-column layout
   - Desktop: Sidebar + main content

3. **Loading States:**
   - Show skeleton loaders for preview table
   - Disable buttons during API calls
   - Show progress indicator for CSV export

4. **Error Handling:**
   - Display validation errors inline
   - Show API errors in a toast/alert
   - Handle network errors gracefully

5. **Accessibility:**
   - Keyboard navigation support
   - ARIA labels for form controls
   - Screen reader friendly

## Validation Rules

1. **Entity Type:** Required before building query
2. **Filters:** Validate based on filter type:
   - String: Non-empty
   - Number: Valid number format
   - Date: Valid date format
   - Enum: Value must be in allowed values
3. **Fields (CSV):** At least one field must be selected
4. **Sort By:** Field must exist in available fields

## Future Enhancements (Optional)

1. **Saved Reports:**
   - Save report configurations
   - Load saved reports
   - Share reports with other users

2. **Advanced Features:**
   - Multiple sort fields
   - Custom field aliases for CSV
   - Report scheduling
   - Email export

3. **UI Improvements:**
   - Drag-and-drop filter ordering
   - Filter presets/templates
   - Export format options (PDF, Excel)

## Testing Considerations

- Test with all entity types
- Test filter combinations
- Test CSV export with large datasets
- Test pagination
- Test error scenarios (invalid filters, network errors)
- Test responsive design
