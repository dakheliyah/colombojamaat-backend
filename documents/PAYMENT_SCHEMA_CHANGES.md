# Payment Schema Changes - Frontend Migration Guide

## 🎯 Overview

The payment tracking system for Sharafs has been restructured from boolean fields in the `sharafs` table to a normalized structure with two new tables: `payment_definitions` and `sharaf_payments`.

**⚠️ BREAKING CHANGES:** This update requires frontend code changes. Payment fields (`lagat_paid`, `najwa_ada_paid`) have been removed from the Sharaf model and replaced with a relationship-based structure.

## Database Schema Changes

### New Tables

#### 1. `payment_definitions`
Stores payment type definitions for each sharaf definition.

**Fields:**
- `id` (bigint, primary key)
- `sharaf_definition_id` (foreign key to `sharaf_definitions`, cascade delete)
- `name` (string, e.g., "lagat", "najwa_ada")
- `description` (text, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Unique Constraint:** `[sharaf_definition_id, name]` - ensures unique payment names per sharaf definition

#### 2. `sharaf_payments`
Stores actual payment records for each sharaf.

**Fields:**
- `id` (bigint, primary key)
- `sharaf_id` (foreign key to `sharafs`, cascade delete)
- `payment_definition_id` (foreign key to `payment_definitions`, cascade delete)
- `payment_amount` (decimal(10,2), default 0)
- `payment_status` (tinyint, 0=unpaid, 1=paid, default 0)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Unique Constraint:** `[sharaf_id, payment_definition_id]` - ensures one payment record per payment type per sharaf

### Removed Fields

The following fields have been removed from the `sharafs` table:
- `lagat_paid` (boolean)
- `najwa_ada_paid` (boolean)

## API Changes

### Sharaf Creation/Update

**Before:**
```json
{
  "sharaf_definition_id": 1,
  "rank": 1,
  "capacity": 10,
  "hof_its": "123456",
  "lagat_paid": false,
  "najwa_ada_paid": false
}
```

**After:**
```json
{
  "sharaf_definition_id": 1,
  "rank": 1,
  "capacity": 10,
  "hof_its": "123456"
}
```

Payment fields are no longer accepted during sharaf creation. Payments must be managed separately through the payment endpoints.

### Sharaf Response

**Before:**
```json
{
  "id": 1,
  "sharaf_definition_id": 1,
  "rank": 1,
  "capacity": 10,
  "hof_its": "123456",
  "lagat_paid": false,
  "najwa_ada_paid": false,
  ...
}
```

**After:**
```json
{
  "id": 1,
  "sharaf_definition_id": 1,
  "rank": 1,
  "capacity": 10,
  "hof_its": "123456",
  "sharaf_payments": [
    {
      "id": 1,
      "payment_definition_id": 1,
      "payment_amount": "0.00",
      "payment_status": false,
      "payment_definition": {
        "id": 1,
        "name": "lagat",
        "description": "Lagat payment"
      }
    },
    {
      "id": 2,
      "payment_definition_id": 2,
      "payment_amount": "0.00",
      "payment_status": true,
      "payment_definition": {
        "id": 2,
        "name": "najwa_ada",
        "description": "Najwa ada payment"
      }
    }
  ],
  ...
}
```

## Payment Management

### Existing Payment Endpoints

The existing payment endpoints remain functional:
- `POST /sharafs/{sharaf_id}/payments/lagat` - Toggle lagat payment
- `POST /sharafs/{sharaf_id}/payments/najwa` - Toggle najwa ada payment

These endpoints now work with the new `sharaf_payments` table structure internally.

### Request Format (unchanged)
```json
{
  "paid": true
}
```

## Confirmation Logic Changes

### Before
A sharaf could be confirmed when:
- Clearance for the sharaf's HOF is complete
- `lagat_paid = true`
- `najwa_ada_paid = true`

### After
A sharaf can be confirmed when:
- Clearance for the sharaf's HOF is complete
- All required payment definitions for the sharaf's definition have corresponding paid records in `sharaf_payments`

This means:
- If a sharaf definition has payment definitions defined, ALL of them must be paid
- If no payment definitions exist for a sharaf definition, only clearance is required
- The system is now flexible to support any number of payment types per sharaf definition

## Migration Notes

1. **No Data Migration**: Existing payment data (`lagat_paid`, `najwa_ada_paid`) is NOT automatically migrated. Existing sharafs will need payments to be set up manually through the payment endpoints.

2. **Payment Definitions**: Payment definitions need to be created for each sharaf definition that requires payments. The system will auto-create them when using the payment toggle endpoints (lagat/najwa), but they can also be created manually.

3. **Backward Compatibility**: The payment toggle endpoints (`/lagat` and `/najwa`) remain functional and will automatically create payment definitions if they don't exist.

## 🚀 Frontend Action Items

### Critical Changes (Must Do)

1. **✅ Remove payment fields from Sharaf forms**
   - Remove `lagat_paid` and `najwa_ada_paid` from sharaf creation/update forms
   - These fields are no longer accepted by the API

2. **✅ Update TypeScript/JavaScript interfaces**
   - Remove `lagat_paid?: boolean` and `najwa_ada_paid?: boolean` from Sharaf interface
   - Add `sharaf_payments?: SharafPayment[]` to Sharaf interface

3. **✅ Update payment status checks**
   - Replace direct field checks (`sharaf.lagat_paid`) with relationship checks
   - Use the new `sharaf_payments` array to determine payment status

4. **✅ Update confirmation logic**
   - Check that all payment definitions have paid records
   - Handle cases where no payment definitions exist

### Recommended Enhancements

5. **💡 Enhanced Payment UI**
   - Display all payment definitions dynamically (not hardcoded)
   - Show payment amounts alongside status
   - Add visual indicators for payment completion
   - Consider a dedicated payment management section

6. **💡 Payment Status Display**
   - Show payment definitions with their names and descriptions
   - Display payment amounts
   - Show payment status with clear visual feedback (icons, colors, etc.)

## 📝 TypeScript Interface Examples

### Updated Sharaf Interface

```typescript
// ❌ OLD - Remove these fields
interface Sharaf {
  id: number;
  sharaf_definition_id: number;
  rank: number;
  capacity: number;
  status: string;
  hof_its: string;
  lagat_paid?: boolean;        // ❌ REMOVE
  najwa_ada_paid?: boolean;    // ❌ REMOVE
  // ...
}

// ✅ NEW - Use this structure
interface Sharaf {
  id: number;
  sharaf_definition_id: number;
  rank: number;
  capacity: number;
  status: string;
  hof_its: string;
  sharaf_payments?: SharafPayment[];  // ✅ ADD THIS
  sharaf_definition?: SharafDefinition;
  sharaf_members?: SharafMember[];
  sharaf_clearances?: SharafClearance[];
  // ...
}

// ✅ NEW - Add these interfaces
interface SharafPayment {
  id: number;
  sharaf_id: number;
  payment_definition_id: number;
  payment_amount: string;  // Decimal as string from API
  payment_status: boolean; // 0 = false (unpaid), 1 = true (paid)
  payment_definition: PaymentDefinition;
  created_at?: string;
  updated_at?: string;
}

interface PaymentDefinition {
  id: number;
  sharaf_definition_id: number;
  name: string;  // e.g., "lagat", "najwa_ada"
  description: string | null;
  created_at?: string;
  updated_at?: string;
}
```

## 💻 Code Examples

### Checking Payment Status

**❌ OLD Way:**
```typescript
// Direct field access - NO LONGER WORKS
if (sharaf.lagat_paid && sharaf.najwa_ada_paid) {
  console.log('All payments paid');
}

const lagatPaid = sharaf.lagat_paid;
const najwaPaid = sharaf.najwa_ada_paid;
```

**✅ NEW Way:**
```typescript
// Check if all payments are paid
const allPaymentsPaid = sharaf.sharaf_payments?.every(
  payment => payment.payment_status === true
) ?? false;

// Check specific payment by name
const lagatPaid = sharaf.sharaf_payments?.find(
  payment => payment.payment_definition.name === 'lagat'
)?.payment_status === true;

const najwaPaid = sharaf.sharaf_payments?.find(
  payment => payment.payment_definition.name === 'najwa_ada'
)?.payment_status === true;

// Get payment amount
const lagatAmount = sharaf.sharaf_payments?.find(
  payment => payment.payment_definition.name === 'lagat'
)?.payment_amount ?? '0.00';
```

### Checking Confirmation Eligibility

**❌ OLD Way:**
```typescript
const canConfirm = clearance?.is_cleared && 
                   sharaf.lagat_paid && 
                   sharaf.najwa_ada_paid;
```

**✅ NEW Way:**
```typescript
// Get all payment definitions for this sharaf's definition
const paymentDefinitions = sharaf.sharaf_definition?.payment_definitions ?? [];

// Check if clearance is complete
const clearanceComplete = clearance?.is_cleared ?? false;

// Check if all payments are paid
const allPaymentsPaid = paymentDefinitions.length === 0 || 
  paymentDefinitions.every(paymentDef => {
    const payment = sharaf.sharaf_payments?.find(
      p => p.payment_definition_id === paymentDef.id && p.payment_status
    );
    return payment !== undefined;
  });

const canConfirm = clearanceComplete && allPaymentsPaid;
```

### Displaying Payments in UI

**✅ Example React Component:**
```tsx
interface PaymentStatusProps {
  sharaf: Sharaf;
}

const PaymentStatus: React.FC<PaymentStatusProps> = ({ sharaf }) => {
  const payments = sharaf.sharaf_payments ?? [];
  
  if (payments.length === 0) {
    return <div>No payments required</div>;
  }
  
  return (
    <div className="payment-status">
      <h3>Payment Status</h3>
      {payments.map(payment => (
        <div key={payment.id} className="payment-item">
          <span className="payment-name">
            {payment.payment_definition.name}
          </span>
          <span className="payment-description">
            {payment.payment_definition.description}
          </span>
          <span className={`payment-status ${payment.payment_status ? 'paid' : 'unpaid'}`}>
            {payment.payment_status ? '✓ Paid' : '✗ Unpaid'}
          </span>
          <span className="payment-amount">
            ${payment.payment_amount}
          </span>
        </div>
      ))}
    </div>
  );
};
```

### Form Updates

**❌ OLD Form (Remove payment fields):**
```tsx
<form onSubmit={handleSubmit}>
  <input name="sharaf_definition_id" />
  <input name="rank" />
  <input name="capacity" />
  <input name="hof_its" />
  <input type="checkbox" name="lagat_paid" />  {/* ❌ REMOVE */}
  <input type="checkbox" name="najwa_ada_paid" />  {/* ❌ REMOVE */}
  <button type="submit">Create Sharaf</button>
</form>
```

**✅ NEW Form (No payment fields):**
```tsx
<form onSubmit={handleSubmit}>
  <input name="sharaf_definition_id" />
  <input name="rank" />
  <input name="capacity" />
  <input name="hof_its" />
  {/* Payment fields removed - manage separately via payment endpoints */}
  <button type="submit">Create Sharaf</button>
</form>
```

## 🔄 Migration Checklist

Use this checklist to ensure all changes are implemented:

### Forms & Inputs
- [ ] Remove `lagat_paid` field from sharaf creation form
- [ ] Remove `najwa_ada_paid` field from sharaf creation form
- [ ] Remove payment fields from sharaf update/edit forms
- [ ] Verify API calls don't include payment fields in request body

### Type Definitions
- [ ] Update Sharaf TypeScript/JavaScript interface
- [ ] Remove `lagat_paid` and `najwa_ada_paid` properties
- [ ] Add `sharaf_payments` array property
- [ ] Create `SharafPayment` interface
- [ ] Create `PaymentDefinition` interface

### Payment Status Logic
- [ ] Replace `sharaf.lagat_paid` checks with relationship queries
- [ ] Replace `sharaf.najwa_ada_paid` checks with relationship queries
- [ ] Update "all payments paid" logic
- [ ] Update confirmation eligibility checks
- [ ] Handle cases where `sharaf_payments` is undefined or empty

### UI Components
- [ ] Update payment status display components
- [ ] Update sharaf detail/view pages
- [ ] Update sharaf list/card components
- [ ] Update confirmation status indicators
- [ ] Add payment amount display (if needed)
- [ ] Update payment badges/indicators

### API Integration
- [ ] Verify payment endpoints still work (`/lagat`, `/najwa`)
- [ ] Update API response handling for new structure
- [ ] Test payment toggle functionality
- [ ] Verify sharaf creation without payment fields

### Testing
- [ ] Test sharaf creation
- [ ] Test sharaf update
- [ ] Test payment status display
- [ ] Test confirmation logic
- [ ] Test payment toggle endpoints
- [ ] Test with sharafs that have no payments
- [ ] Test with sharafs that have multiple payment types

## 📋 API Endpoints Reference

### Unchanged Endpoints (Still Work)
- `POST /api/sharafs/{sharaf_id}/lagat` - Toggle lagat payment
- `POST /api/sharafs/{sharaf_id}/najwa` - Toggle najwa ada payment

**Request Body:**
```json
{
  "paid": true
}
```

### Updated Response Structure
All sharaf endpoints now return `sharaf_payments` array instead of boolean fields.

## ⚠️ Important Notes

1. **No Data Migration**: Existing payment data is NOT automatically migrated. You may need to manually set payments for existing sharafs using the payment endpoints.

2. **Backward Compatibility**: Payment toggle endpoints (`/lagat` and `/najwa`) still work and will auto-create payment definitions if they don't exist.

3. **Flexible Payment Types**: The system now supports any number of payment types per sharaf definition, not just "lagat" and "najwa_ada". Your UI should be flexible enough to handle dynamic payment types.

4. **Null Safety**: Always check if `sharaf_payments` exists before accessing it, as it may be undefined or empty.

## 🆘 Support

If you have any questions about these schema changes or need assistance updating your frontend code, please contact the backend team.

**Migration Date:** January 28, 2026  
**API Version:** Check your API versioning scheme
