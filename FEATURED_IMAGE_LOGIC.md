# Featured Image Logic Documentation

## Overview

This document explains how featured images are extracted and assigned to floor plan listings.

## Flow

### 1. HTML Fetching (class-sync.php)

- Fetches listings from AppFolio URL: `https://cityblockprop.appfolio.com/listings`
- Stores raw HTML for parsing

### 2. HTML Parsing (class-parser.php lines 188-205)

The parser extracts image URLs in the following priority order:

```php
// Priority 1: data-src (modern lazy loading)
$img_src = $img->getAttribute('data-src');

// Priority 2: src (standard attribute)
if (empty($img_src)) {
    $img_src = $img->getAttribute('src');
}

// Priority 3: data-original (legacy lazy loading)
if (empty($img_src)) {
    $img_src = $img->getAttribute('data-original');
}
```

### 3. Image Download (class-images.php lines 15-68)

- Downloads the image from the extracted URL
- Checks if image already exists by checking `_ffp_source_url` meta
- Creates WordPress attachment
- **Sets as featured image** via `set_post_thumbnail($post_id, $attachment_id)`

### 4. Post Sync (class-sync.php lines 177-201)

- For **new posts**: Sets featured image when creating
- For **existing posts**: Updates featured image if changed

## Test Cases

Based on the HTML example, here are the expected results:

### Test Case 1: 1 Bedroom Plan

- **Title**: "Now Pre-Leasing 1 Bedroom Plans for Fall 2026!"
- **Address**: "580 E Broad St, Athens, GA 30601"
- **Expected Image URL
  **: `https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg`
- **Image Attribute**: `data-original` (third priority, should work)

### Test Case 2: 3 Bedroom 2 Bath Plan

- **Title**: "Now Pre-Leasing 3 Bedroom 2 Bath Plans for Fall 2026!"
- **Address**: "580 E Broad St, Athens, GA 30601"
- **Expected Image URL
  **: `https://images.cdn.appfolio.com/cityblockprop/images/94b4a9a6-7459-4a6b-a969-5d567196f589/medium.png`
- **Image Attribute**: `data-original` (third priority, should work)

## HTML Structure Example

```html
<div class="listing-item result js-listing-item" id="listing_74">
   <div class="listing-item__figure-container">
      <a href="/listings/detail/5146bd15-a294-4045-9a9f-596c8de61bc5" target="_blank">
         <div class="listing-item__figure">
            <img class="listing-item__image is-placeholder lazy js-listing-image" 
                 data-original="https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg" 
                 alt="580 E Broad St, Athens, GA 30601" />
         </div>
      </a>
   </div>
   <div class="listing-item__body">
      <h2 class="listing-item__title js-listing-title">
         <a href="/listings/detail/5146bd15-a294-4045-9a9f-596c8de61bc5">Now Pre-Leasing 1 Bedroom Plans for Fall 2026!</a>
      </h2>
      <p class="u-space-an">
         <span class="u-pad-rm js-listing-address">580 E Broad St, Athens, GA 30601</span>
      </p>
   </div>
</div>
```

## How to Verify

1. **Run the sync manually** via WordPress admin
2. **Check the logs** in `wp-content/uploads/ffp-logs/` for image extraction messages
3. **Verify in admin** that posts have featured images set
4. **Check the database** for `_thumbnail_id` meta on floor_plan posts

## Potential Issues

1. **Image URL not extracted**: Check logs for "Image node found but no valid src/data-src attributes"
2. **Image download fails**: Check logs for "Failed to download image"
3. **Featured image not set**: Check that `FFP_Images::download_image()` is called with `$featured = true`
4. **Building filter not matching**: Check that "580 E Broad St" is in the building filter option

## Files to Check

- `includes/class-parser.php` lines 188-205 (image extraction logic)
- `includes/class-images.php` lines 15-68 (image download and set)
- `includes/class-sync.php` lines 177-201 (sync logic for featured images)
- Log files in `wp-content/uploads/ffp-logs/`
