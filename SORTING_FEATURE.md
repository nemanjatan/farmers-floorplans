# Sorting Feature Documentation

## Overview

Added a sorting dropdown feature to the Farmers Floor Plans shortcode, allowing users to sort listings by:
- Most Recent (default)
- Rent - Low to High
- Rent - High to Low  
- Square Feet - Low to High
- Square Feet - High to Low

## How It Works

### 1. Shortcode Parameter

The shortcode now reads URL parameters to determine the sort order:

```php
[farmers_floor_plans show_sort="yes"]
```

**Parameters:**
- `show_sort` - Show/hide the sorting dropdown (default: "yes")
- `orderby` - Default sort order (default: "date")
  - Options: `date`, `price_asc`, `price_desc`, `sqft_asc`, `sqft_desc`

### 2. URL-Based Sorting

The sort parameter is read from the URL query string:
- `?sort=price_asc` - Rent Low to High
- `?sort=price_desc` - Rent High to Low
- `?sort=sqft_asc` - Square Feet Low to High
- `?sort=sqft_desc` - Square Feet High to Low
- `?sort=date` - Most Recent

When users select a sort option, the page reloads with the new `sort` parameter.

### 3. Query Implementation

The shortcode now supports sorting by custom meta fields:

```php
switch ($atts['orderby']) {
    case 'price_asc':
        $orderby = 'meta_value_num';
        $meta_key = '_ffp_price';
        $order = 'ASC';
        break;
    // ... etc
}
```

This uses WordPress's `meta_value_num` orderby with the appropriate meta key (`_ffp_price` or `_ffp_sqft`).

## Files Modified

### 1. `includes/class-render.php`
- Added URL parameter reading for sort
- Added orderby parsing logic
- Added dropdown HTML output
- Updated query args to support meta_value_num sorting

### 2. `assets/front.js`
- Added change event listener for dropdown
- Updates URL with sort parameter
- Reloads page with new sort order

### 3. `assets/front.css`
- Added styles for dropdown wrapper
- Added styles for select element
- Made responsive for mobile devices

## Usage Examples

### Basic Usage
```php
[farmers_floor_plans]
```

### Default to specific sort
```php
[farmers_floor_plans orderby="price_asc"]
```

### Hide sorting dropdown
```php
[farmers_floor_plans show_sort="no"]
```

### Combined with filters
```php
[farmers_floor_plans beds="2" min_price="1000" max_price="2000" orderby="price_asc"]
```

## UI Design

The sorting dropdown features:
- Clean, minimal design matching Farmers Athens branding
- 220px minimum width on desktop
- Full width on mobile
- Green hover/focus states using brand colors
- Smooth transitions

## Browser Compatibility

- Works on all modern browsers
- Uses standard HTML5 `<select>` element
- Gracefully falls back on older browsers
- Uses `URLSearchParams` API (supported IE 11+)

## Testing Checklist

- [ ] Sorting dropdown displays correctly
- [ ] All 5 sort options work
- [ ] URL parameter updates on selection
- [ ] Results reorder correctly
- [ ] Mobile responsive design works
- [ ] Caching doesn't interfere with sorting
- [ перед ] Works with existing filters (beds, price range)
- [ ] Maintains sort when using filters

## Future Enhancements

Potential improvements:
- AJAX-based sorting without page reload
- Remember user's sort preference in localStorage
- Add more sort options (bedrooms, bathrooms)
- Add animation during sort transition
