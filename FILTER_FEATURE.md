# Filter Feature Documentation

## Overview

Added comprehensive filtering functionality to the Farmers Floor Plans plugin, allowing users to filter listings by:

- Available units only
- Unit type (Studio, 1 Bedroom, 2 Bedrooms, 3 Bedrooms, 4 Bedrooms, 5 Bedrooms)
- Price range ($0 - $10,000)

## Features

### 1. Available Units Only

- Single checkbox to show only available units
- Already filtered by `_ffp_active` meta field

### 2. Unit Type Filter

- Multiple checkboxes for different bedroom combinations
- Maps to: Studio (0), 1 Bedroom, 2 Bedrooms, 3 Bedrooms, 4 Bedrooms, 5 Bedrooms
- Uses bedroom count from `_ffp_bedrooms` meta
- Supports multiple selections

### 3. Price Range Slider

- Dual-range slider for min/max price filtering
- Range: $0 - $10,000
- Displays current values in real-time
- Filters by `_ffp_price` meta field

### 4. Reset Button

- Clears all filters and returns to default view
- Green border with hover effect

## Implementation

### HTML Structure

```html
<div class="ffp-filters-sidebar">
    <div class="ffp-filters-inner">
        <!-- Filter sections -->
        <button class="ffp-reset-btn">RESET</button>
    </div>
</div>
```

### CSS Styling

- Light off-white background (#fafafa)
- Dark green headings using `var(--ffp-primary)`
- Two-column grid for checkboxes
- Custom styled range sliders
- Responsive design

### JavaScript Functionality

- **Checkbox filtering**: Updates URL on change
- **Range slider**: Dual handles for min/max price
- **URL management**: Uses URLSearchParams API
- **Page reload**: Filters applied on change

### Backend Query Logic

- Reads `unit_type[]` from GET parameters
- Supports array of bedroom values
- Uses `IN` compare for multiple selections
- Handles price range with min/max logic

## URL Parameters

The filters use these URL parameters:

- `unit_type[]` - Array of bedroom counts
- `min_price` - Minimum price
- `max_price` - Maximum price
- `available_only` - Show available only (1)
- `sort` - Sort order (cleared when filters change)

## Usage

### Basic Shortcode

```php
[farmers_floor_plans]
```

Shows filters by default.

### Homepage vs Full Listing Page

**Homepage - Featured listings only (clean, simple):**

```php
[farmers_floor_plans featured="1" limit="6" show_filter="no" show_sort="no"]
```

- Shows only featured floor plans
- No filters or sorting (clean homepage experience)
- Limited to 6 units
- Mark listings as "Featured" in admin

**Full Listing Page - All units with full functionality:**

```php
[farmers_floor_plans]
```

or

```php
[farmers_floor_plans show_filter="yes" show_sort="yes"]
```

- Shows all active units
- Full filter sidebar enabled
- Sorting dropdown enabled
- All filtering capabilities

### Hide Filters

```php
[farmers_floor_plans show_filter="no"]
```

### Show Only Sorting

```php
[farmers_floor_plans show_sort="yes" show_filter="no"]
```

## File Changes

### Modified Files

- `includes/class-render.php` - Added filter HTML and query logic
- `assets/front.js` - Added filter interactions and range slider
- `assets/front.css` - Added filter sidebar styling

### Key Methods Added

- `render_filters($atts)` - Outputs filter HTML
- `initPriceSlider()` - Initializes range slider
- `applyFilters()` - Updates URL with filter parameters

## User Experience

1. User checks filters or adjusts price range
2. Page reloads with updated URL parameters
3. Results filtered accordingly
4. Sort order reset when filters change (to prevent confusion)
5. Reset button clears all filters instantly

## Design Notes

- Matches the design shown in the inspiration image
- Clean, minimalist aesthetic
- Green accent color matches brand
- Responsive on all devices
- Accessible with proper labels
