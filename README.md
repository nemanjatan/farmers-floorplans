# Farmers Floor Plans Plugin

A WordPress plugin that scrapes and displays property listings from AppFolio, specifically for Farmer's Exchange
building.

## Features

- Scrapes listings from AppFolio listings page
- Filters by building name (Farmer's Exchange 580 E Broad St.)
- Stores listings as custom post types
- Auto-syncs daily at configurable time
- Manual sync option in WordPress admin
- Downloads and optimizes images locally
- Displays floor plans in a clean grid layout
- Featured floor plans section for homepage
- Image lazy loading for performance
- Query caching for faster page loads

## Installation

1. Upload the `farmers-floorplans` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Farmers Floor Plans to configure

## Configuration

In the admin settings page, you can configure:

- **AppFolio List URL**: The URL to scrape listings from (default: https://cityblockprop.appfolio.com/listings)
- **Building Filter**: Text to filter listings by (default: Farmer's Exchange 580 E Broad St.)
- **Daily Sync Time**: Time to run automatic daily sync
- **Auto-create Page**: Create Floor Plans page on activation

## Usage

### Shortcode

Display floor plans on any page or post:

```
[farmers_floor_plans]
```

### Shortcode Parameters

- `featured="1"` - Show only featured floor plans
- `limit="12"` - Number of floor plans to display (default: 12)
- `show_filter="yes"` - Show filter sidebar (default: yes)
- `show_sort="yes"` - Show sorting dropdown (default: yes)
- `beds="2"` - Filter by number of bedrooms
- `min_price="500"` - Minimum price filter
- `max_price="1000"` - Maximum price filter
- `orderby="date"` - Order by field (date, price_asc, price_desc, sqft_asc, sqft_desc)

### Common Usage Examples

**Homepage - Featured listings only (no filters/sorting):**

```
[farmers_floor_plans featured="1" limit="6" show_filter="no" show_sort="no"]
```

**Full listing page - All units with filters and sorting:**

```
[farmers_floor_plans limit="12"]
```

**Filtered results:**

```
[farmers_floor_plans beds="2" min_price="600" max_price="1200"]
```

### Setting Featured Listings

To mark floor plans as featured:

1. Go to **Floor Plans → All Floor Plans** in WordPress admin
2. Edit any floor plan
3. Check the **"Feature on homepage"** checkbox in the Floor Plan Details meta box
4. Save the post

Only listings marked as featured will appear when using `featured="1"` in the shortcode.

## Manual Sync

To manually trigger a sync:

1. Go to Settings → Farmers Floor Plans
2. Click the "Sync Now" button
3. Check the status panel for results

## Data Model

### Custom Post Type: floor_plan

**Meta Fields:**

- `_ffp_source_id` - Unique identifier for upsert
- `_ffp_building` - Building name
- `_ffp_address` - Property address
- `_ffp_price` - Rent price
- `_ffp_bedrooms` - Number of bedrooms
- `_ffp_bathrooms` - Number of bathrooms
- `_ffp_sqft` - Square footage
- `_ffp_available` - Availability date/status
- `_ffp_active` - Active status (0 or 1)
- `_ffp_featured` - Featured on homepage (0 or 1)
- `_ffp_gallery_ids` - Array of attachment IDs
- `_ffp_source_url` - Original AppFolio URL

## Template Files

The plugin includes custom templates:

- `templates/archive-floor_plan.php` - Archive page template
- `templates/single-floor_plan.php` - Single floor plan template
- `templates/parts/card-floor-plan.php` - Card component

## Cron Schedule

The plugin schedules a daily sync using WordPress cron. Default time is 3:00 AM.

To customize:

1. Go to Settings → Farmers Floor Plans
2. Set your preferred sync time
3. Save settings

## Performance

- Query caching with 10-minute TTL
- Custom image sizes for cards (600x400)
- Lazy loading on images
- No remote requests on frontend

## Security

- Nonce verification on all forms
- `manage_options` capability required for admin access
- Input sanitization on all fields
- Output escaping in templates

## Troubleshooting

### No listings showing

1. Check if sync has run successfully
2. Verify building filter text matches your listings
3. Check logs in admin for errors
4. Ensure listings contain the filter text in address or title

### Images not downloading

1. Check server write permissions
2. Verify image URLs are accessible
3. Check logs for specific error messages

### Selector drift warning

If you see "zero listings parsed" but the page fetched correctly, the HTML structure may have changed. Check the parser
selectors in `includes/class-parser.php`.

## Development

### File Structure

```
farmers-floorplans/
├── farmers-floorplans.php
├── includes/
│   ├── class-cpt.php
│   ├── class-admin.php
│   ├── class-sync.php
│   ├── class-parser.php
│   ├── class-render.php
│   ├── class-images.php
│   └── class-logger.php
├── templates/
│   ├── archive-floor_plan.php
│   ├── single-floor_plan.php
│   └── parts/
│       └── card-floor-plan.php
└── assets/
    ├── admin.css
    ├── admin.js
    ├── front.css
    └── front.js
```

## Version

1.0.0

## Author

Nemanja Tanaskovic

## License

Proprietary - Developed for Farmers Athens

