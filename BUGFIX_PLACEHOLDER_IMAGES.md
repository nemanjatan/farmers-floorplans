# Bug Fix: Placeholder Images Being Selected Instead of Real Images

## Problem

The featured image logic was always selecting a placeholder image:
```
https://listings.cdn.appfolio.com/listings/assets/listings/rental_listing/place_holder-ea9e892a45f62e048771a4b22081d1eed003a21f0658a92aa5abcfd357dd4699.png
```

Instead of the actual listing images like:
```
https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg
```

## Root Cause

The HTML structure uses lazy loading where:
- The `src` attribute contains a placeholder image
- The real image URL is stored in the `data-original` attribute
- The parser was checking `src` before `data-original`

**Example HTML structure:**
```html
<img class="listing-item__image" 
     src="https://listings.cdn.appfolio.com/.../place_holder-..." 
     data-original="https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg" 
     alt="Test" />
```

## Fix

**File:** `includes/class-parser.php` (lines 188-216)

Changed the attribute priority order:
1. ✅ `data-src` first (modern lazy loading)
2. ✅ `data-original` second (legacy lazy loading - **This is what the HTML uses**)
3. ✅ `src` last, but skip if it contains placeholder patterns

Added placeholder detection to skip-"src" attribute if it contains:
- "placeholder" 
- "place_holder"
- "loading"

## Test Results

All tests pass! ✅

```
✓ Test 1 PASSED: Not using placeholder URL
✓ Test 2 PASSED: Contains expected UUID
✓ Test 3 PASSED: Has correct domain
```

## How to Verify

1. Run the sync manually via WordPress admin
2. Check that floor plans now have the correct featured images (not placeholders)
3. Review logs in `wp-content/uploads/ffp-logs/` for "Downloaded image" messages
4. Verify images show real property photos, not generic placeholder graphics

## Before vs After

### Before (Broken)
- Would extract: `place_holder-ea9e892a45f62e048771a4b22081d1eed003a21f0658a92aa5abcfd357dd4699.png`
- All listings showed the same placeholder image

### After (Fixed)
- Extracts: `c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg`
- Each listing shows its unique property image

## Related Files

- **Parser**: `includes/class-parser.php` lines 188-216 (image extraction logic)
- **Images**: `includes/class-images.php` (image download and setting)
- **Test**: `test-images.php` (verifies the fix works correctly)
