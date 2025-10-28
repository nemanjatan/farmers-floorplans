# Installation Instructions

## Quick Start

1. **Upload to WordPress**
    - Upload the entire `farmers-floorplans` folder to `/wp-content/plugins/`
    - Or zip the folder and upload via WordPress admin

2. **Activate the Plugin**
    - Go to WordPress Admin → Plugins
    - Find "Farmers Floor Plans" and click "Activate"

3. **Configure Settings**
    - Go to Settings → Farmers Floor Plans
    - Verify the AppFolio URL is correct
    - Verify the building filter matches your listings
    - Set your preferred daily sync time
    - Click "Save Settings"

4. **Run First Sync**
    - Click the "Sync Now" button in the admin
    - Wait for it to complete (check status panel)
    - You should see listings appear

5. **View Floor Plans**
    - A "Floor Plans" page should be auto-created
    - Visit `/floor-plans/` on your site
    - Or add `[farmers_floor_plans]` shortcode to any page

## Homepage Featured Section

To add featured floor plans to your homepage:

1. Edit your homepage
2. Add the shortcode: `[farmers_floor_plans featured="1" limit="6"]`
3. Save and view

## Manual Floor Plan Management

You can manually edit floor plans:

1. Go to Floor Plans → All Floor Plans
2. Click on any floor plan to edit
3. Modify details, set featured, etc.
4. Changes will be preserved (except on next sync if field is from AppFolio)

## Troubleshooting

### No listings appear after sync

**Check:**

1. Logs in admin panel - look for errors
2. Building filter text - must match exactly what's in AppFolio
3. HTML structure - if you see "zero listings parsed", the selectors may need adjustment

**Adjust parser selectors:**

- Edit `includes/class-parser.php`
- Look for the XPath queries around line 42
- May need to inspect AppFolio HTML to find correct selectors

### Images not downloading

**Check:**

1. WordPress media directory is writable
2. `wp-content/uploads/` has proper permissions
3. Image URLs are accessible (check logs)

### Sync taking too long

- Normal for first sync (downloading images)
- Subsequent syncs should be faster
- Check if mutex transient expired (15 min timeout)

## Customization

### Styling

To match your site's colors/styles:

1. Edit `assets/front.css`
2. Modify CSS variables at the top
3. Adjust grid, cards, buttons as needed

### Templates

To customize display:

1. Edit files in `templates/` directory
2. Card template: `templates/parts/card-floor-plan.php`
3. Single view: `templates/single-floor_plan.php`
4. Archive: `templates/archive-floor_plan.php`

### Parser adjustments

If AppFolio HTML structure changes:

1. Open browser inspector on listings page
2. Identify card container classes/IDs
3. Update XPath selectors in `includes/class-parser.php`

## Testing Checklist

- [ ] Plugin activates without errors
- [ ] Settings page loads correctly
- [ ] Manual sync completes successfully
- [ ] Listings appear in admin
- [ ] Floor Plans page displays correctly
- [ ] Images load properly
- [ ] Featured shortcode works on homepage
- [ ] Single floor plan view shows details
- [ ] Filtering/sorting works
- [ ] Daily cron runs (check cron schedule)

## Next Steps After Installation

1. **Test sync** - Run manual sync and verify data
2. **Check styling** - Adjust CSS to match your brand
3. **Add to homepage** - Insert featured shortcode
4. **Configure cron** - Set appropriate sync time
5. **Monitor logs** - Check first few daily syncs for issues

## Support

If you encounter issues:

1. Check logs in admin first
2. Verify all settings are correct
3. Test with manual sync
4. Check WordPress error logs
5. Review parser selectors if data structure changed

