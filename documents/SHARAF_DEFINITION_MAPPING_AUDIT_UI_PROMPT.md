# Sharaf Definition Mapping Audit Logs UI

## Overview
Create a dedicated UI for viewing and analyzing audit logs of sharaf definition mapping shift operations. This provides administrators with a complete history and detailed information about all shift operations.

## Main Features

### 1. Audit Logs List View
- Display all audit logs in a table or timeline view
- Filter options:
  - By mapping (select specific mapping)
  - By date range (from/to)
  - By shifted_by_its (user who executed the shift)
  - By event (source or target event)
- Sort options:
  - Date (newest first / oldest first)
  - Shifted by user
  - Number of sharafs shifted
- Columns/Fields to display:
  - Date/Time of shift
  - Mapping (source → target with event names)
  - Shifted By (ITS number, with user name if available)
  - Summary (counts: sharafs, members, clearances, payments)
  - Status badge (success/error if applicable)
  - Actions: View Details

### 2. Audit Log Detail View
- Show comprehensive details of a single shift operation:
  - **Header Section:**
    - Mapping information (source and target definitions with events)
    - Shift date and time
    - Shifted by (ITS and user name if available)
  
  - **Summary Section:**
    - Total sharafs shifted
    - Total members shifted
    - Total clearances shifted
    - Total payments shifted
  
  - **Rank Changes Section:**
    - Table showing:
      - Sharaf ID
      - Old Rank
      - New Rank
      - HOF ITS (if available)
      - HOF Name (if available)
    - Sortable columns
    - Option to export rank changes as CSV
  
  - **Position Mappings Used Section:**
    - List of position mappings that were applied
    - Show: Source Position → Target Position
    - Count of members affected by each mapping
  
  - **Payment Definition Mappings Used Section:**
    - List of payment definition mappings that were applied
    - Show: Source Payment Definition → Target Payment Definition
    - Count of payments affected by each mapping
  
  - **Shifted Sharafs Section:**
    - List of all sharaf IDs that were shifted
    - Clickable links to view individual sharaf details
    - Option to view all shifted sharafs in target definition
  
  - **Raw Data Section (Optional/Advanced):**
    - Expandable section showing full JSON data from audit log
    - Useful for debugging or detailed analysis

### 3. Audit Log Timeline View (Alternative/Additional)
- Visual timeline showing shift operations chronologically
- Group by:
  - Date
  - Mapping
  - User
- Show summary cards with key information
- Click to expand and see details

### 4. Statistics Dashboard (Optional Enhancement)
- Overview cards showing:
  - Total shifts performed
  - Total sharafs shifted (all time)
  - Most active mappings
  - Most active users
  - Shifts by date range (chart)
- Filters for date range

### 5. Export Functionality
- Export audit logs as:
  - CSV (for Excel analysis)
  - PDF (formatted report)
- Options:
  - Export current filtered view
  - Export specific log entry
  - Export all logs for a mapping

## UI/UX Requirements

### Design
- Clean, professional interface suitable for audit/reporting
- Use color coding:
  - Success indicators for completed shifts
  - Warning indicators for any issues
- Print-friendly layouts for reports
- Responsive design

### User Experience
- Easy filtering and searching
- Clear visual hierarchy
- Expandable/collapsible sections for detailed views
- Breadcrumbs for navigation
- Quick actions (view sharaf, view mapping, etc.)
- Keyboard shortcuts for power users

### Data Presentation
- Format dates/times in user-friendly format
- Show relative time (e.g., "2 hours ago") with absolute time on hover
- Format large numbers with commas
- Show percentages or ratios where relevant
- Use tables for structured data
- Use cards/sections for grouped information

### Performance
- Paginate large lists (e.g., 50 items per page)
- Lazy load detailed information
- Cache frequently accessed data
- Show loading states

## API Integration

### Primary Endpoint
- `GET /api/sharaf-definition-mappings/{id}/audit-logs` - Get audit logs for a mapping

### Additional Endpoints for Context
- `GET /api/sharaf-definition-mappings` - Get mapping details
- `GET /api/sharafs/{sharaf_id}` - Get sharaf details (for links)
- `GET /api/users/its/{its_no}` - Get user details by ITS (for shifted_by_its)

### Data Structure
Each audit log entry contains:
- `id` - Audit log ID
- `sharaf_definition_mapping_id` - Mapping ID
- `shifted_by_its` - ITS of user who executed shift
- `shift_summary` - JSON object with counts
- `sharaf_ids` - JSON array of shifted sharaf IDs
- `position_mappings_used` - JSON array of position mappings
- `payment_mappings_used` - JSON array of payment definition mappings
- `rank_changes` - JSON array of rank change objects
- `shifted_at` - Timestamp
- `created_at` - Timestamp

## Error Handling
- Handle cases where audit log data is incomplete
- Show appropriate messages for missing data
- Handle API errors gracefully

## Use Cases

### Primary Use Cases
1. **Review Shift History**
   - Administrator wants to see all shifts performed for a specific mapping
   - Filter by date range and user

2. **Investigate Issues**
   - Administrator needs to understand what happened during a specific shift
   - View detailed rank changes and affected data

3. **Compliance/Audit**
   - Generate reports of all shift operations
   - Export data for external audit

4. **Troubleshooting**
   - Developer/admin needs to debug a shift operation
   - View raw JSON data and detailed logs

## Additional Features (Optional)

### Comparison View
- Compare two shift operations side by side
- Highlight differences

### Search Functionality
- Search by sharaf ID
- Search by HOF ITS
- Search by position/payment definition names

### Notifications
- Alert when shifts are performed (if real-time updates are needed)
- Email notifications for important shifts (configurable)

### Permissions
- View-only access for auditors
- Full access for administrators
- Role-based visibility of certain fields
