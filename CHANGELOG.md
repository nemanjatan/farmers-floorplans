# Changelog

All notable changes to the Farmers Floor Plans plugin.

## [1.1.2] - 2025-11-09

### Improved

- **Enhanced Card Design**: Premium redesign of floor plan cards
    - Increased border-radius from 8px to 12px for softer edges
    - Improved hover effects with smoother transitions
    - Enhanced shadows with better depth perception
    - Subtle gradient background on card content

- **Better Card Actions Section**:
    - Added separator border-top for clear visual separation
    - Improved unit number badge with gradient background and border
    - Enhanced "View Details" button with shimmer effect on hover
    - Better spacing and alignment between elements
    - Refined typography with adjusted font sizes and weights

- **Premium Meta Tags**:
    - Card meta items now have white background with borders
    - Hover effects on meta items for better interactivity
    - Improved spacing with tighter gaps

- **Price Prominence**:
    - Larger, bolder price display (1.35rem, weight 800)
    - Gradient background with prominent border
    - Enhanced box shadow for depth
    - Price now stands out as the key information

- **Typography Enhancements**:
    - Title font size increased to 1.3rem with weight 700
    - Better line-height for improved readability
    - Refined letter-spacing throughout

- **Animation & Interactions**:
    - Smooth cubic-bezier transitions for cards
    - Shimmer effect on "View Details" button hover
    - Enhanced hover states on all interactive elements
    - Active states for better touch feedback

### Changed

- Updated preview-featured-grid.html to reflect new design
- Card hover lift increased from 4px to 6px
- Card content padding increased from 1.5rem to 1.75rem

## [1.1.1] - 2025-11-09

### Added

- **Unit # Column in Admin**: New sortable "Unit #" column in the Floor Plans admin list
    - Automatically extracts unit number from address field
    - Shows in bold for easy visibility
    - Sortable by clicking column header
    - Displays dash (—) if no unit number found
    - Example: "580 E Broad St - 302" shows as "302"

### Changed

- Enhanced admin list table with unit number for easier management
- Updated column sorting handler to support unit number sorting

## [1.1.0] - 2025-11-09

### Added

- **Featured Floor Plans Shortcode**: New `[farmers_featured_floor_plans]` shortcode for home page display
    - Full-width 5-column grid layout on desktop
    - Responsive breakpoints (5 → 4 → 3 → 2 → 1 columns)
    - Queries floor plans marked as "Featured" in admin
    - Falls back to most recent posts if no featured posts exist
    - Clean presentation without filters or sorting UI

- **Featured Column in Admin**:
    - New "⭐ Featured" column in floor plans admin list
    - Shows star (⭐) for featured posts, dash (—) for regular posts
    - Sortable column for easy management
    - Quick visual identification of featured status

- **Featured Meta Box Field**:
    - "Feature on homepage" checkbox in Floor Plan Details meta box
    - Saves to `_ffp_featured` meta field
    - Integrated with existing meta box UI

- **CSS for Featured Grid**:
    - New `.ffp-featured-section` container class
    - New `.ffp-featured-grid` grid layout class
    - Responsive breakpoints at 1400px, 1024px, 768px, 480px
    - Reuses existing card styles for consistency

- **Documentation**:
    - New `FEATURED_FLOORPLANS.md` with complete usage guide
    - Updated `README.md` with featured shortcode section
    - New `preview-featured-grid.html` for visual preview
    - Examples for marking posts as featured and ordering

### Changed

- Updated README.md to document both shortcodes
- Enhanced admin column management for better UX

## [1.0.0] - 2025-11-08

### Added

- Initial release
- AppFolio listing scraper
- Custom post type for floor plans
- Daily automated sync
- Manual sync option in admin
- Image gallery management
- Filter and sort functionality
- Contact form modal with AppFolio integration
- Dynamic "Apply Now" button per unit
- Unit number extraction and display
- High-quality image downloads from swipebox galleries
- Responsive card layout
- WordPress admin settings page
- WP-CLI commands for sync
- Comprehensive logging system

### Features

- Scrapes listings from AppFolio
- Filters by building name
- Stores listings as custom post types
- Downloads and optimizes images locally
- Displays floor plans in clean grid layout
- Image lazy loading for performance
- Query caching for faster page loads
- Contact form that posts to AppFolio
- Native PHP cURL for bypassing WAF
- Minimal, monochromatic message design

### Security

- Nonce verification on all forms
- Capability checks for admin access
- Input sanitization on all fields
- Output escaping in templates
- AJAX security with wp_verify_nonce

### Performance

- Query caching with 10-minute TTL
- Custom image sizes for cards (600x400)
- Lazy loading on images
- No remote requests on frontend
- Optimized database queries
- Deduplication logic in sync

---

## Version History

- **1.1.0** - Featured floor plans shortcode and admin enhancements
- **1.0.0** - Initial release with full AppFolio integration

