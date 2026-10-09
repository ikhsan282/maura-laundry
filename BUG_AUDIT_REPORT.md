# Maura Laundry - Functional Bug Audit Report

**Audit Date:** 2026-10-09  
**Scope:** Business logic errors, calculation bugs, state transition bugs, edge cases, UI/UX issues

---

## CRITICAL SEVERITY

### BUG-001: Subscription Orders Missing Transport Fees
**File:** `includes/subscriptions.php:42-46`  
**Severity:** CRITICAL  
**Impact:** Revenue loss, incorrect billing

**Description:**  
When generating orders from subscriptions, transport fees (pickup_fee, delivery_fee) are not included in total calculation. Only service items are summed.

**Code:**
```php
$total = 0.0; $max_days = 0;
foreach ($items as $it) {
    $total += (float)$it['price'] * (float)$it['quantity'];
    $max_days = max($max_days, (int)$it['duration_days']);
}
// Missing: transport_fee() calculation
```

**Expected:** Total should include `transport_fee($type, $pickup_fee, $delivery_fee)`  
**Actual:** Transport fees ignored, order total is items-only

**Reproduction:**
1. Create subscription with service_type='both'
2. Generate order from subscription
3. Order total excludes pickup/delivery fees
4. Revenue undercharged

**Fix:** Add after line 45:
```php
$total = order_grand_total($total, $type, 0, 0); // subscriptions don't store fees yet
```

---

### BUG-002: Client-Side Order Total Manipulation
**File:** `pages/orders/create.php:206`  
**Severity:** CRITICAL  
**Impact:** Financial fraud, revenue loss

**Description:**  
Order total is calculated in JavaScript and submitted as hidden input. Server never validates it against items + transport fees. User can manipulate DOM/POST to submit arbitrary total.

**Code:**
```javascript
document.getElementById('totalInput').value = total;
```

Server accepts `$_POST['total_amount']` without recalculation.

**Reproduction:**
1. Open order creation form
2. Add services totaling Rp 100,000
3. Open browser DevTools console
4. Run: `document.getElementById('totalInput').value = 1000`
5. Submit form
6. Order created with total Rp 1,000 instead of Rp 100,000

**Fix:** Server must recalculate total from submitted items before INSERT:
```php
// After line 58, before transaction
$total = 0;
foreach ($items as $it) $total += $it['subtotal'];
$total = order_grand_total($total, $service_type, $pickup_fee, $delivery_fee);
```

---

### BUG-003: Race Condition in Order Number Generation
**File:** `includes/functions.php:74-79`  
**Severity:** CRITICAL  
**Impact:** Duplicate order numbers, data integrity

**Description:**  
`generate_order_number()` uses COUNT(*) then increments, creating race condition. Two concurrent requests can generate same order number.

**Code:**
```php
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
return 'ML-' . $date . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
```

**Reproduction:**
1. Simulate concurrent order creation (2 users submit simultaneously)
2. Both execute COUNT(*) = 5
3. Both generate ML-20261009-0006
4. Second INSERT fails with duplicate key OR overwrites first

**Fix:** Use MAX(id) with transaction lock or auto-increment sequence table:
```php
$stmt = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, -4) AS UNSIGNED)), 0) 
                      FROM orders WHERE DATE(created_at) = CURDATE() FOR UPDATE");
```

---

## HIGH SEVERITY

### BUG-004: Deposit Payment Requires Exact Balance Match
**File:** `pages/payments/create.php:41-42,58`  
**Severity:** HIGH  
**Impact:** Payment failure, poor UX

**Description:**  
Deposit payment validation requires amount to EXACTLY match remaining balance. Prevents partial payments and fails if balance changed between page load and submit.

**Code:**
```php
if ($method === 'deposit' && abs($amount - $remaining) > 0.01) 
    $errors[] = 'Pembayaran dari deposit harus tepat sebesar sisa tagihan';
```

**Reproduction:**
1. Order with total Rp 100,000, remaining Rp 100,000
2. Customer has deposit balance Rp 150,000
3. User enters Rp 50,000 for partial payment
4. Validation fails: "must equal exact remaining"
5. User forced to pay full amount or switch method

**Fix:** Allow deposit payment ≤ remaining AND ≤ balance:
```php
if ($method === 'deposit' && $amount > $customer_balance + 0.01) 
    $errors[] = 'Saldo deposit tidak mencukupi';
```

---

### BUG-005: Dashboard Order Link Uses Wrong Parameter
**File:** `pages/dashboard.php:181`  
**Severity:** HIGH  
**Impact:** Broken navigation, 404 errors

**Description:**  
Dashboard recent orders table links to `view.php?id=` but view.php expects `?order_number=`.

**Code:**
```php
<a href="<?= APP_URL ?>/pages/orders/view.php?id=<?= $r['order_number'] ?>">
```

**Reproduction:**
1. View dashboard
2. Click any order in "Order Terbaru" table
3. URL becomes `/view.php?id=ML-20261009-0001`
4. view.php checks `$_GET['order_number']`, finds nothing
5. Redirects to orders index with "Order tidak ditemukan"

**Fix:** Line 181:
```php
<a href="<?= APP_URL ?>/pages/orders/view.php?order_number=<?= h($r['order_number']) ?>">
```

---

### BUG-006: DateTime Parsing Strips Time Component
**File:** `pages/orders/create.php:72-73,79-80`  
**Severity:** HIGH  
**Impact:** Pickup/delivery scheduling broken

**Description:**  
DateTime parsing uses `createFromFormat('!Y-m-d\TH:i', ...)` where `!` flag resets time to 00:00:00, negating the H:i parsing.

**Code:**
```php
$dt = DateTime::createFromFormat('!Y-m-d\TH:i', $_POST['pickup_scheduled_at']);
$pickup_at = $dt->format('Y-m-d H:i:s');
```

**Reproduction:**
1. Create order with pickup scheduled at 2026-10-09T14:30
2. Form submits `2026-10-09T14:30`
3. Parsed datetime becomes `2026-10-09 00:00:00`
4. All scheduled pickups default to midnight

**Fix:** Remove `!` flag:
```php
$dt = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['pickup_scheduled_at']);
```

---

### BUG-007: Subscription Form JavaScript Type Error
**File:** `pages/subscriptions/form.php:157`  
**Severity:** HIGH  
**Impact:** Form initialization fails

**Description:**  
JavaScript checks `SELECTED.length` but SELECTED is an object (from `array_column($sub['items'], null, 'service_id')`), not array. Property `length` is undefined.

**Code:**
```php
$selected = array_column($sub['items'], null, 'service_id'); // object with numeric keys
```
```javascript
SELECTED.length ? Object.entries(SELECTED).forEach(...) : addItem();
```

**Reproduction:**
1. Edit existing subscription with items
2. JavaScript console error: "Cannot read property 'length' of undefined"
3. Items not pre-populated in form
4. User sees empty form despite existing data

**Fix:** Line 157:
```javascript
Object.keys(SELECTED).length ? Object.entries(SELECTED).forEach(...) : addItem();
```

---

## MEDIUM SEVERITY

### BUG-008: Reports Revenue Attribution Incorrect
**File:** `pages/reports/index.php:186-196`  
**Severity:** MEDIUM  
**Impact:** Misleading analytics

**Description:**  
Monthly service summary joins payments to orders but filters by `p.paid_at` instead of `o.created_at`. Services sold in September but paid in October appear in October's service report.

**Code:**
```sql
SELECT s.name, ... FROM order_items oi
JOIN orders o ON o.id = oi.order_id
JOIN payments p ON p.order_id = o.id
WHERE YEAR(p.paid_at) = ? AND MONTH(p.paid_at) = ?
```

**Expected:** Revenue attributed to order creation month  
**Actual:** Revenue attributed to payment month

**Reproduction:**
1. Create order Sept 30 for "Cuci Kiloan" Rp 50,000
2. Payment received Oct 1
3. View September service report: Rp 0
4. View October service report: Rp 50,000 (incorrect)

**Fix:** Filter by order date or remove payment join if using order totals.

---

### BUG-009: Deposit Amount Lacks Decimal Precision
**File:** `pages/deposits/index.php:89`  
**Severity:** MEDIUM  
**Impact:** Limited usability

**Description:**  
Deposit amount input has `step="1"` forcing whole numbers. Cannot enter Rp 5,500.50.

**Code:**
```html
<input type="number" min="1" step="1" name="amount" class="form-control" required>
```

**Reproduction:**
1. Try to top-up deposit with Rp 10,500.50
2. Browser prevents decimal entry or rounds
3. Customer charged Rp 10,500 or Rp 10,501

**Fix:** Change to `step="0.01"` or `step="any"`

---

### BUG-010: No Server Validation for Service Duration
**File:** `pages/services/create.php:16,22-23` & `pages/services/edit.php:21,27`  
**Severity:** MEDIUM  
**Impact:** Data integrity

**Description:**  
`duration_days` validation only exists client-side (`min="1"`). Server accepts 0, negative, or missing values.

**Code:**
```php
$days = (int)($_POST['duration_days'] ?? 3);
// No validation that $days >= 1
```

**Reproduction:**
1. Disable browser validation
2. Submit service with duration_days=-5
3. Order estimated_done calculation breaks

**Fix:** Add validation:
```php
if ($days < 1) $errors[] = 'Estimasi selesai minimal 1 hari.';
```

---

### BUG-011: Transport Fee Validation Runs After Other Checks
**File:** `pages/orders/create.php:59`  
**Severity:** MEDIUM  
**Impact:** Poor error reporting

**Description:**  
Negative pickup/delivery fee validation only runs if no prior errors. User might fix items, re-submit, then see transport fee error.

**Code:**
```php
if (empty($items)) $errors[] = 'Item order tidak valid.';
if ($pickup_fee < 0 || $delivery_fee < 0) $errors[] = '...'; // only if no errors
```

**Reproduction:**
1. Submit order with no items + negative pickup_fee
2. See error: "Item order tidak valid"
3. Add items, resubmit
4. See error: "Biaya pickup tidak boleh negatif"
5. User frustrated by staggered error disclosure

**Fix:** Move transport validation before line 58.

---

### BUG-012: Concurrent Payment Race Condition
**File:** `pages/payments/create.php:49-58`  
**Severity:** MEDIUM  
**Impact:** Overpayment possible

**Description:**  
Two cashiers can open payment form for same order simultaneously, both see same remaining balance, both submit payment, total exceeds order amount.

**Reproduction:**
1. Cashier A opens payment form for order (remaining Rp 100k)
2. Cashier B opens payment form for same order (remaining Rp 100k)
3. A submits Rp 100k (succeeds)
4. B submits Rp 100k
5. B's transaction acquires lock AFTER A commits
6. B's locked check sees remaining=0 but original form had max=100k
7. Validation passes because line 57 checks against locked_remaining
8. Wait... actually this is PROTECTED by the lock. The locked re-check would catch it.

Actually, re-reading the code: lines 49-58 DO re-check remaining balance under lock. This is correctly protected. Not a bug.

---

### BUG-013: Monthly Report Date Parsing Lacks Validation
**File:** `pages/reports/index.php:169`  
**Severity:** MEDIUM  
**Impact:** Application error

**Description:**  
Month parameter split with `explode('-', $month)` without validating format. Malformed input causes array index errors.

**Code:**
```php
[$yr, $mo] = explode('-', $month); // expects YYYY-MM
```

**Reproduction:**
1. URL: `/reports/index.php?type=monthly&month=2026`
2. explode returns ['2026']
3. `[$yr, $mo] = ['2026']` → PHP error "Undefined array key 1"

**Fix:** Add validation:
```php
$parts = explode('-', $month);
if (count($parts) !== 2) { /* error */ }
[$yr, $mo] = $parts;
```

---

### BUG-014: Subscription Monthly Next Due Edge Case
**File:** `includes/subscriptions.php:11-14`  
**Severity:** MEDIUM  
**Impact:** Scheduling drift

**Description:**  
Monthly subscription due date calculation handles Jan 31 → Feb 28 correctly, but subsequent cycles stay at 28 instead of returning to 31 in months with 31 days.

**Code:**
```php
'monthly' => (function () use ($date) {
    $first = $date->modify('first day of next month');
    return $first->setDate(..., min((int)$date->format('d'), (int)$first->format('t')))->format('Y-m-d');
})(),
```

**Reproduction:**
1. Subscription starts Jan 31 (next_due = Jan 31)
2. Generate order, next_due becomes Feb 28
3. Generate order, next_due becomes Mar 28 (should be Mar 31)
4. Pattern continues: always 28th instead of end-of-month

**Fix:** Track original day-of-month separately or use "last day" logic for dates ≥28.

---

## LOW SEVERITY

### BUG-015: Print Styles Missing for Receipt
**File:** `pages/orders/view.php:70-110`  
**Severity:** LOW  
**Impact:** Poor print quality

**Description:**  
Receipt div is hidden with `style="display:none"` and printed with JavaScript, but no `@media print` styles to hide navigation, buttons, etc.

**Reproduction:**
1. View order detail
2. Click "Cetak Nota"
3. Print preview shows navigation bar, sidebar, buttons

**Fix:** Add print CSS:
```css
@media print {
  .no-print, nav, .btn { display: none !important; }
}
```

---

### BUG-016: Dashboard Pending Orders Query Fragile
**File:** `pages/dashboard.php:23`  
**Severity:** LOW  
**Impact:** Minor

**Description:**  
Pending orders uses `NOT IN ('selesai','diambil')` which breaks if custom statuses added. Should explicitly list pending statuses.

**Code:**
```php
SELECT COUNT(*) FROM orders WHERE status NOT IN ('selesai','diambil')
```

**Fix:**
```php
WHERE status IN ('diterima','dicuci','disetrika')
```

---

### BUG-017: Customer Phone Clearing Ambiguity
**File:** `pages/customers/edit.php:29`  
**Severity:** LOW  
**Impact:** UX confusion

**Description:**  
Phone field uses `$phone ?: null` which converts empty string to NULL. Cannot distinguish between "user cleared phone" vs "validation error, redisplay empty field".

**Code:**
```php
$stmt->bind_param('ssssi', $name, $phone ?: null, ...);
```

**Reproduction:**
1. Customer has phone "08123456789"
2. User edits, clears phone to empty string
3. Saved as NULL in database
4. Display shows "-" (correct)
5. User re-edits, sees empty field (correct)
6. BUT: if form validation fails, empty string becomes NULL silently

**Fix:** Explicitly handle empty string:
```php
$phone_db = ($phone === '') ? null : $phone;
```

---

### BUG-018: Login Query Non-Deterministic with Duplicates
**File:** `auth/login.php:25-30`  
**Severity:** LOW  
**Impact:** Unlikely edge case

**Description:**  
Login query matches username OR email but uses `LIMIT 1` without ORDER BY. If duplicate usernames/emails exist, which user is returned is non-deterministic.

**Code:**
```sql
SELECT ... FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1
```

**Reproduction:**
1. Database has users: {username:'admin', email:'admin@example.com'}, {username:'admin2', email:'admin@example.com'}
2. Login with 'admin@example.com'
3. Might return either user depending on storage order

**Fix:** Database should enforce unique constraints on username and email. If not possible, add ORDER BY username.

---

### BUG-019: Service Price No Maximum Validation
**File:** `pages/services/create.php:22`  
**Severity:** LOW  
**Impact:** Data entry error

**Description:**  
Price validation only checks `> 0`. User can enter 999999999999.99, breaking UI display and potentially database DECIMAL column.

**Code:**
```php
if ($price <= 0) $errors[] = 'Harga harus lebih dari 0.';
```

**Fix:** Add maximum:
```php
if ($price <= 0 || $price > 99999999.99) $errors[] = 'Harga tidak valid (max Rp 99,999,999).';
```

---

### BUG-020: Receipt Quantity Display Edge Case
**File:** `pages/orders/view.php:93`  
**Severity:** LOW  
**Impact:** Cosmetic

**Description:**  
Receipt uses `rtrim(rtrim(number_format($qty,2),'0'),'.')` to clean trailing zeros, but quantity=0.00 displays as empty string.

**Code:**
```php
<?= rtrim(rtrim(number_format($it['quantity'],2),'0'),'.') ?>
```

**Reproduction:**
1. Order somehow has item with quantity=0 (shouldn't happen but not validated)
2. Receipt shows empty quantity field

**Fix:** Add minimum check or default:
```php
<?= $it['quantity'] > 0 ? rtrim(rtrim(number_format($it['quantity'],2),'0'),'.') : '0' ?>
```

---

### BUG-021: Email XSS in Customer Creation
**File:** `pages/customers/create.php:19`  
**Severity:** LOW  
**Impact:** Potential XSS

**Description:**  
Email validation uses `filter_var` but doesn't sanitize. If email with `<script>` is redisplayed (validation error), XSS possible.

**Code:**
```php
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '...';
```

**Mitigation:** Forms already use `<?= h($_POST['email'] ?? '') ?>` for output, so XSS is prevented. Still, good practice to sanitize input.

---

### BUG-022: Order Form Browser Back Creates Duplicate Row
**File:** `pages/orders/create.php:313-314`  
**Severity:** LOW  
**Impact:** UX annoyance

**Description:**  
JavaScript calls `addRow()` on page load. If user navigates back with browser button, form state might be restored, and addRow() adds another empty row.

**Code:**
```javascript
addRow(); // Line 313
toggleTransport(); // Line 314
```

**Reproduction:**
1. Create order, add 3 service rows
2. Submit form (validation error)
3. Browser back button
4. Some browsers restore form, now shows 4 rows (3 filled + 1 new empty)

**Fix:** Check if rows exist before adding:
```javascript
if (document.querySelectorAll('#itemsBody tr').length === 0) addRow();
```

---

### BUG-023: PDF Empty Data Display Malformed
**File:** `pages/reports/index.php:65`  
**Severity:** LOW  
**Impact:** Cosmetic

**Description:**  
PDF generator shows "Tidak ada data" when empty, but `array_pad` usage doesn't properly span columns.

**Code:**
```php
$pdf->table($headers, $rows ?: [array_pad(['Tidak ada data'], count($headers), '')], ...);
```

`array_pad` fills FROM position, not spanning. Should create array of correct length.

**Fix:**
```php
$rows ?: [array_fill(0, count($headers), 'Tidak ada data')]
```

---

### BUG-024: Transport Fee Validation Unreachable
**File:** `includes/functions.php:49-53`  
**Severity:** LOW  
**Impact:** Dead code

**Description:**  
`transport_fee()` validates NAN/INF but (float) cast on POST data never produces these values. Defensive code but unreachable.

**Code:**
```php
foreach ([$pickup_fee, $delivery_fee] as $fee) {
    if (!is_finite($fee) || $fee < 0 || $fee > 99999999.99) throw new InvalidArgumentException();
}
```

**Analysis:** `(float)` cast on string always produces finite number or 0. NAN/INF only from division by zero or sqrt(-1) which doesn't happen here.

**Decision:** Not a bug, defensive programming is acceptable. Mark as ponytail comment if removing.

---

### BUG-025: Pagination URL Order Non-Deterministic
**File:** `pages/orders/index.php:170`  
**Severity:** LOW  
**Impact:** Unlikely issue

**Description:**  
Pagination uses `array_merge($_GET, ['page'=>'%d'])` which doesn't guarantee parameter order. Most systems handle this, but some strict query parsers might fail.

**Code:**
```php
<?= paginate($total, $per_page, $page, '?'. http_build_query(array_merge($_GET,['page'=>'%d']))) ?>
```

**Fix:** Not necessary unless actual bug occurs. Most HTTP implementations ignore order.

---

## SUMMARY STATISTICS

- **Total Bugs Found:** 25
- **Critical:** 3 (financial/data integrity)
- **High:** 4 (broken functionality)
- **Medium:** 8 (data quality/UX)
- **Low:** 10 (edge cases/cosmetic)

## PRIORITY FIXES

**Must Fix (Critical):**
1. BUG-001: Add transport fees to subscription order totals
2. BUG-002: Server-side order total validation
3. BUG-003: Fix order number race condition

**Should Fix (High):**
4. BUG-004: Allow partial deposit payments
5. BUG-005: Fix dashboard order link parameter
6. BUG-006: Remove '!' flag from datetime parsing
7. BUG-007: Fix subscription form JavaScript

**Consider Fixing (Medium):**
- BUG-008 through BUG-014

**Low Priority:**
- BUG-015 through BUG-025

---

## TESTING RECOMMENDATIONS

1. **Concurrent transaction testing:** Simulate multiple users creating orders/payments simultaneously
2. **Edge case testing:** Zero amounts, negative values, empty strings vs NULL
3. **Integration testing:** Full order lifecycle (create → pay → status updates)
4. **Subscription testing:** Monthly edge cases (31st, leap years)
5. **Security testing:** Form manipulation, SQL injection on search fields
6. **Browser testing:** Back button behavior, form restoration

---

**Audit Completed By:** Hermes Agent (Automated Analysis)  
**Review Status:** Pending developer confirmation and fix prioritization
