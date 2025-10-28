# Featured Image Logic & Testing

## Summary

Based on the HTML structure in `html-and-css/example-page-index.html`, here's how featured images are determined:

## Current Implementation

### How Featured Images Are Set

1. **Parsing** (`class-parser.php` lines 188-205):
    - Extracts image URL from HTML using XPath
    - Checks attributes in this priority: `data-src` → `src` → `data-original`
    - Your HTML uses `data-original`, so it's checked as the 3rd fallback

2. **Download** (`class-images.php`):
    - Downloads image from extracted URL
    - Sets as featured image via `set_post_thumbnail($post_id, $attachment_id)`

3. **Storage**:
    - Stores source URL in `_ffp_source_url` meta to prevent duplicates
    - Sets as post's featured image via `_thumbnail_id` meta

### Expected Results

For the listings you mentioned:

**Listing 1: "Now Pre-Leasing 1 Bedroom Plans for Fall 2026!"**

- HTML: Line 1606 in example-page-index.html
- Image URL: `https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg`
- Attribute used: `data-original` ✓

**Listing 2: "Now Pre-Leasing 3 Bedroom 2 Bath Plans for Fall 2026!"**

- HTML: Line 1685 in example-page-index.html
- Image URL: `https://images.cdn.appfolio.com/cityblockprop/images/94b4a9a6-7459-4a6b-a969-5d567196f589/medium.png`
- Attribute used: `data-original` ✓

## Test Files Created

### 1. `test-images.php`

Simple standalone test that verifies image extraction logic works.

```bash
php test-images.php
```

This tests that the XPath logic correctly extracts images from `data-original` attributes.

### 2. `test-featured-image-integration.php`

Integration test that mocks the full flow (requires WordPress context).

### 3. `FEATURED_IMAGE_LOGIC.md`

Complete documentation of the featured image flow.

## Running Tests

### Quick Test (Image Extraction Logic)

```bash
cd /path/to/farmers-floorplans
php test-images.php
```

Expected output:

```
✓ SUCCESS: Extracted image URL: https://images.cdn.appfolio.com/cityblockprop/images/...
✓ URL contains expected domain
✓ URL contains expected UUID
```

### Full Integration Test (Requires WordPress)

```bash
wp plugin deactivate farmers-floorplans  # if activated
php test-featured-image-integration.php
```

## Verification Checklist

To confirm featured images are working in production:

- [ ] Check logs at `wp-content/uploads/ffp-logs/` for "Downloaded image" messages
- [ ] Verify posts have featured images in WP Admin
- [ ] Check
  database: `SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key = '_thumbnail_id' AND post_id IN (SELECT ID FROM wp_posts WHERE post_type = 'floor_plan')`
- [ ] Check image meta: `SELECT * FROM wp_postmeta WHERE meta_key = '_ffp_source_url'` to see source URLs

## Troubleshooting

### If images aren't being extracted:

1. Check logs for "Image node found but no valid src/data-src attributes"
2. Verify HTML structure matches expected structure
3. Check that building filter is matching listings (should contain "580 E Broad St")

### If images download but aren't set as featured:

1. Check that `FFP_Images::download_image()` is called with `$featured = true`
2. Check logs for "Downloaded image" followed by attachment ID
3. Verify no errors in WordPress debug log

## Code Locations

- **Image Extraction**: `includes/class-parser.php` lines 188-205
- **Image Download**: `includes/class-images.php` lines 15-68
- **Feature Image Setting**: `includes/class-sync.php` lines 198-201, 177-179
