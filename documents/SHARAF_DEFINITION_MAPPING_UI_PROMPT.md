# Sharaf Definition Mapping Management UI

## Overview
Create a comprehensive UI for managing sharaf definition mappings that allows administrators to map sharaf definitions from different events and shift sharaf instances between them.

## Main Features

### 1. Mapping List View
- Display all sharaf definition mappings in a table or card layout
- Show columns/cards: Source Definition (with event name), Target Definition (with event name), Status (Active/Inactive), Created By, Created Date, Notes
- Filter by:
  - Specific sharaf definition (show mappings where it's source or target)
  - Active/Inactive status
  - Event
- Actions per mapping:
  - View details
  - Edit (activate/deactivate, update notes)
  - Delete
  - Manage position mappings
  - Manage payment definition mappings
  - Validate completeness
  - Execute shift

### 2. Create Mapping Form
- Form fields:
  - Source Sharaf Definition (dropdown with search, grouped by event)
  - Target Sharaf Definition (dropdown with search, grouped by event)
  - Created By ITS (optional, auto-fill from session if available)
  - Notes (textarea, optional)
- Validation:
  - Source and target must be different
  - Source and target must be from different events
  - Show error if mapping already exists
  - Show error if creating mapping would create circular reference
- After creation: Redirect to mapping detail view

### 3. Mapping Detail View
- Show mapping information:
  - Source Definition (with event details)
  - Target Definition (with event details)
  - Status badge (Active/Inactive)
  - Created by and date
  - Notes
- Tabs or sections:
  - **Position Mappings Tab**
    - List of all position mappings
    - Show: Source Position → Target Position
    - Add new position mapping button
    - Remove position mapping action
  - **Payment Definition Mappings Tab**
    - List of all payment definition mappings
    - Show: Source Payment Definition → Target Payment Definition
    - Add new payment definition mapping button
    - Remove payment definition mapping action
  - **Validation Status**
    - Show validation result (complete/incomplete)
    - If incomplete, list missing position mappings and payment definition mappings
    - Show which sharafs would be affected
    - "Validate" button to refresh validation
  - **Shift Operation**
    - Show summary of what will be shifted (counts of sharafs, members, clearances, payments)
    - "Execute Shift" button (disabled if validation incomplete or mapping inactive)
    - Confirmation dialog before executing shift
    - Show progress/loading state during shift
    - Display shift result summary after completion
  - **Audit Logs Tab**
    - List of all shift operations for this mapping
    - Show: Date, Shifted By, Counts, Link to detailed log

### 4. Add Position Mapping Modal/Form
- Source Position dropdown (filtered to positions in source definition)
- Target Position dropdown (filtered to positions in target definition)
- Validation:
  - Positions must belong to correct definitions
  - Cannot map same position to itself
  - Cannot create duplicate mappings
- Show position details (name, display_name) in dropdowns

### 5. Add Payment Definition Mapping Modal/Form
- Source Payment Definition dropdown (filtered to payment definitions in source definition)
- Target Payment Definition dropdown (filtered to payment definitions in target definition)
- Validation:
  - Payment definitions must belong to correct definitions
  - Cannot map same payment definition to itself
  - Cannot create duplicate mappings
- Show payment definition details (name, description) in dropdowns

### 6. Validation View
- Display validation status prominently
- If incomplete:
  - List missing position mappings with:
    - Position ID and name
    - Which sharafs use this position
    - "Add Mapping" button for each
  - List missing payment definition mappings with:
    - Payment definition ID and name
    - Which sharafs use this payment definition
    - "Add Mapping" button for each
- If complete:
  - Show success message
  - Enable shift operation button

### 7. Shift Execution Flow
- Pre-shift confirmation dialog showing:
  - Number of sharafs to be shifted
  - Number of members, clearances, payments
  - Warning about rank changes
  - "Shifted By ITS" field (optional, auto-fill from session)
- During shift: Show loading spinner with progress message
- After shift: Display success message with:
  - Summary of shifted data
  - Rank changes (old rank → new rank for each sharaf)
  - Link to audit log entry
  - Option to view updated sharafs in target definition

### 8. Edit Mapping Form
- Fields:
  - Active/Inactive toggle
  - Notes (textarea)
- Save button
- Cancel button

## UI/UX Requirements

### Design
- Modern, clean interface consistent with existing application design
- Use status badges for Active/Inactive mappings
- Use color coding:
  - Green for complete/valid mappings
  - Yellow/Orange for incomplete mappings
  - Red for errors
- Responsive design (works on desktop and tablet)

### User Experience
- Clear navigation between views
- Breadcrumbs for navigation context
- Confirmation dialogs for destructive actions (delete, shift)
- Toast notifications for success/error messages
- Loading states for async operations
- Helpful error messages with actionable guidance
- Tooltips for complex concepts (e.g., "circular mapping", "bidirectional mapping")

### Data Loading
- Lazy load related data (positions, payment definitions) when needed
- Show skeleton loaders while data is loading
- Handle empty states gracefully (no mappings, no position mappings, etc.)

### Validation Feedback
- Real-time validation where possible
- Clear error messages next to form fields
- Disable submit buttons until form is valid
- Show validation status prominently in detail view

## API Integration

### Endpoints to Use
- `GET /api/sharaf-definition-mappings` - List mappings
- `POST /api/sharaf-definition-mappings` - Create mapping
- `GET /api/sharaf-definition-mappings/{id}` - Get mapping details
- `PUT /api/sharaf-definition-mappings/{id}` - Update mapping
- `DELETE /api/sharaf-definition-mappings/{id}` - Delete mapping
- `POST /api/sharaf-definition-mappings/{id}/position-mappings` - Add position mapping
- `DELETE /api/sharaf-definition-mappings/{id}/position-mappings/{positionMappingId}` - Remove position mapping
- `POST /api/sharaf-definition-mappings/{id}/payment-definition-mappings` - Add payment definition mapping
- `DELETE /api/sharaf-definition-mappings/{id}/payment-definition-mappings/{paymentMappingId}` - Remove payment definition mapping
- `GET /api/sharaf-definition-mappings/{id}/validate` - Validate mapping
- `POST /api/sharaf-definition-mappings/{id}/shift` - Execute shift
- `GET /api/sharaf-definition-mappings/{id}/audit-logs` - Get audit logs

### Additional Endpoints for Dropdowns
- `GET /api/events` - For event selection
- `GET /api/events/{event_id}/sharaf-definitions` - For sharaf definition selection
- `GET /api/sharaf-definitions/{id}/positions` - For position selection
- `GET /api/sharaf-definitions/{id}/payment-definitions` - For payment definition selection

## Error Handling
- Handle API errors gracefully
- Show user-friendly error messages
- For validation errors, highlight specific issues
- For shift operation errors, show what went wrong and what can be done

## Success Scenarios
- After creating mapping: Show success message, redirect to detail view
- After adding position/payment mapping: Show success, refresh list
- After validation: Update validation status display
- After shift: Show detailed success message with summary
