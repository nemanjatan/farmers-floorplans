# Bug Fix: Square Feet and Bed/Bath Not Being Extracted Correctly

## Problem

The Square Feet and Bed/Bath information was not being extracted correctly from the HTML listings.

## Root Cause

The HTML uses a structured format with specific CSS classes (`detail-box__label` and `detail-box__value`) that the parser wasn't utilizing:

```html
<div class="detail-box__item">
   <dt class="detail-box__label">Square Feet</dt>
   <dd class="detail-box__value">1,248</dd>
</div>
<div class="detail-box__item">
   <dt class="detail-box__label">Bed / Bath</dt>
   <dd class="detail-box__value">3 bd / 1 ba</dd>
</div>
```

The old parser was looking for generic elements containing "bed", "bath", or "sq" in their text, which was unreliable.

Additionally, the `extract_number()` function didn't handle comma-separated numbers (like "1,248"), causing it to extract only the first digit.

## Fix

### 1. Structured Detail Box Parsing
Added logic to parse the structured `detail-box__item` format:
- First extract from the structured format (most reliable)
- Fall back to generic search if not found

### 2. Bed/Bath Extraction  
Parse the combined "Bed / Bath" format like "3 bd / 1 ba":
```php
// Extract bedrooms and bathrooms from format like "3 bd / 1 ba"
if (preg_match('/(\d+)\s*bd/i', $value, $bed_matches)) {
    $listing['bedrooms'] = floatval($bed_matches[1]);
}
if (preg_match('/(\d+\.?\d*)\s*ba/i', $value, $bath_matches)) {
    $listing['bathrooms'] = floatval($bath_matches[1]);
}
```

### 3. Number Extraction Fix
Fixed `extract_number()` to handle comma-separated numbers:
```php
private function extract_number($text) {
    // Remove commas and extract number
    $cleaned = str_replace(',', '', $text);
    preg_match('/[\d.]+/', $cleaned, $matches);
    if (!empty($matches)) {
        return floatval($matches[0]);
    }
    return '';
}
```

## Test Results

All tests pass! ✅

```
✓ Test 1 PASSED: Square Feet extracted correctly (1,248)
✓ Test 2 PASSED: Bedrooms extracted correctly (3)
✓ Test 3 PASSED: Bathrooms extracted correctly (1)
```

## Files Modified

- **`includes/class-parser.php`** lines 155-209 (field extraction logic)
- **`includes/class-parser.php`** lines 316-324 (extract_number function fix)
- **`test-sqft-bedbath.php`** (test to verify the fix)

## How to Verify

1. Run a sync to pull latest listings
2. Check database or admin that Square Feet, Bedrooms, and Bathrooms are populated
3. Review logs in `wp-content/uploads/ffp-logs/` for extraction messages
4. Verify on the frontend that listing details display correctly
