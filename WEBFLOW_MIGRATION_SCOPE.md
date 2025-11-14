# Webflow Migration Scope - Farmers Floor Plans Plugin

## Executive Summary

The current WordPress plugin integrates AppFolio property listings through a PHP-based scraping system that stores data in WordPress Custom Post Types. To migrate this to Webflow, we need to:

1. **Extract the scraping logic** into a standalone service (Python/Node.js)
2. **Replace WordPress storage** with Webflow CMS Collections API
3. **Handle images** via Webflow Assets API instead of WordPress Media Library
4. **Set up external sync** (cron service) instead of WordPress cron
5. **Rebuild frontend** using Webflow CMS Collections
6. **Recreate contact form** using Webflow Forms + serverless function

**Estimated Total Time: 18-24 hours**

---

## Current Architecture Analysis

### What Works (Reusable)
- ✅ **Scraping logic** - The HTML parsing and data extraction can be ported
- ✅ **Data structure** - Field mappings are well-defined
- ✅ **Image extraction** - Gallery image fetching from detail pages works
- ✅ **Contact form logic** - AppFolio API integration is solid

### What Needs Rebuilding
- ❌ **WordPress Custom Post Types** → Webflow CMS Collections
- ❌ **WordPress Media Library** → Webflow Assets API
- ❌ **WordPress Cron** → External cron service
- ❌ **WordPress Shortcodes/Templates** → Webflow CMS Collections
- ❌ **WordPress Admin Panel** → Webflow CMS + optional admin dashboard

---

## Detailed Breakdown

### Phase 1: Standalone Scraper Service (6-8 hours)

**Task**: Extract PHP scraping logic into Python/Node.js service

**Components**:
1. **HTML Scraper** (2-3 hrs)
   - Port `FFP_Parser` class logic
   - Use BeautifulSoup (Python) or Cheerio (Node.js)
   - Extract listings from AppFolio listings page
   - Filter by building name ("Farmer's Exchange 580 E Broad St.")
   - Extract: title, price, address, bedrooms, bathrooms, sqft, availability, images, detail URLs

2. **Detail Page Scraper** (2 hrs)
   - Port `fetch_detail_page_images()` logic
   - Extract gallery images from detail pages
   - Extract unit IDs for Apply Now/Contact Us links
   - Handle lazy-loaded images (data-src attributes)

3. **Data Normalization** (1-2 hrs)
   - Clean and format extracted data
   - Generate unique source IDs (UUID extraction from URLs)
   - Handle missing fields gracefully
   - Validate data structure

4. **Error Handling & Logging** (1 hr)
   - Retry logic for failed requests
   - Logging system (file-based or cloud logging)
   - Error notifications

**Deliverable**: Standalone script that outputs JSON array of listings

---

### Phase 2: Webflow CMS Integration (5-6 hours)

**Task**: Push scraped data to Webflow CMS Collections

**Components**:
1. **Webflow CMS Collection Setup** (1 hr)
   - Create "Floor Plans" collection in Webflow
   - Define fields:
     - Name (Plain Text)
     - Unit Number (Plain Text)
     - Address (Plain Text)
     - Price (Number)
     - Bedrooms (Number)
     - Bathrooms (Number)
     - Square Feet (Number)
     - Available (Plain Text)
     - Featured (Switch)
     - Active (Switch)
     - Source ID (Plain Text) - for deduplication
     - Source URL (Link)
     - Apply Now URL (Link)
     - Contact Us Form ID (Plain Text)
     - Featured Image (Image)
     - Gallery Images (Image Reference - multiple)

2. **Webflow API Integration** (3-4 hrs)
   - Authenticate with Webflow API (API key)
   - Create/Update items in collection
   - Handle upsert logic (check by Source ID)
   - Deactivate stale listings (set Active = false)
   - Upload images via Webflow Assets API
   - Link images to collection items

3. **Image Upload Service** (1-2 hrs)
   - Download images from AppFolio
   - Upload to Webflow Assets API
   - Handle image optimization/resizing if needed
   - Store Webflow asset URLs in collection items

**Deliverable**: Service that syncs listings to Webflow CMS

---

### Phase 3: Sync Automation (2-3 hours)

**Task**: Set up automated daily sync

**Options**:
1. **GitHub Actions** (Recommended - Free)
   - Scheduled workflow (cron: `0 3 * * *` for 3 AM daily)
   - Runs scraper + Webflow sync
   - Free for public repos, $0.008/min for private

2. **AWS Lambda + EventBridge** (Scalable)
   - Serverless function triggered daily
   - ~$0.20/month for daily executions
   - More complex setup

3. **Small VPS/Server** (Simple)
   - Cron job on existing server
   - Requires server maintenance

**Components**:
1. **Sync Orchestrator** (1-2 hrs)
   - Run scraper
   - Compare with existing Webflow items
   - Create/update/deactivate as needed
   - Log results

2. **Manual Sync Endpoint** (1 hr)
   - Simple API endpoint or script
   - Trigger sync on-demand
   - Optional: Webhook from Webflow CMS for manual trigger

**Deliverable**: Automated daily sync + manual trigger option

---

### Phase 4: Frontend Implementation (3-4 hours)

**Task**: Build Webflow pages using CMS Collections

**Components**:
1. **Floor Plans Archive Page** (1-2 hrs)
   - CMS Collection List
   - Filter by bedrooms (checkboxes)
   - Filter by price (range slider - custom code)
   - Filter by availability (checkbox)
   - Sort by price/sqft/date
   - Card design matching current style

2. **Single Floor Plan Page** (1 hr)
   - CMS Collection Page template
   - Display all listing details
   - Image gallery (lightbox)
   - Apply Now button (links to AppFolio)
   - Contact Us button (opens form)

3. **Featured Listings on Homepage** (30 min)
   - CMS Collection List (filtered by Featured = true)
   - Limit to 5 items
   - Grid layout

4. **Filtering/Sorting Logic** (1 hr)
   - Custom JavaScript for client-side filtering
   - URL parameter handling for deep linking
   - AJAX-style updates (optional enhancement)

**Deliverable**: Fully functional frontend in Webflow

---

### Phase 5: Contact Form Integration (2-3 hours)

**Task**: Recreate contact form functionality

**Components**:
1. **Webflow Form Setup** (30 min)
   - Create contact form in Webflow
   - Fields: Move-in date, First name, Last name, Email, Phone, Message
   - Optional fields section (expandable)
   - Styling to match design

2. **Serverless Function** (1-2 hrs)
   - Receive form submission from Webflow
   - Extract listing ID from form data
   - Format data for AppFolio API
   - POST to `https://cityblockprop.appfolio.com/listings/guest_cards`
   - Handle response and send confirmation

3. **Form Integration** (1 hr)
   - Pass listing context to form (hidden fields)
   - Pre-populate listing info in form
   - Handle form submission success/error states
   - Email notification to cbpsales@cityblockonline.com (optional)

**Options**:
- **Webflow Webhooks** → Serverless function (Vercel/Netlify)
- **Zapier/Make.com** integration (no-code, but less flexible)
- **Custom API endpoint** (more control)

**Deliverable**: Working contact form that submits to AppFolio

---

## Technical Considerations

### Image Handling
- **Challenge**: Webflow Assets API has rate limits and file size limits
- **Solution**: 
  - Batch image uploads
  - Compress images before upload
  - Use CDN for images if Webflow limits are restrictive
  - Cache uploaded image URLs to avoid re-uploading

### Data Deduplication
- **Current**: Uses Source ID (UUID from AppFolio URLs)
- **Webflow**: Query collection by Source ID field, update if exists, create if new
- **Deactivation**: Mark items as Active = false if not in latest scrape

### Rate Limiting
- **Webflow API**: 60 requests/minute (free tier), 120/min (paid)
- **Mitigation**: 
  - Batch operations where possible
  - Add delays between requests
  - Consider Webflow paid plan if needed

### Error Handling
- **Scraping failures**: Retry logic, fallback to cached data
- **API failures**: Log errors, continue with other listings
- **Image upload failures**: Continue without images, log for manual fix

---

## Cost Considerations

### Development Time
- **Total**: 18-24 hours @ $30/hr = **$540-$720**

### Ongoing Costs
- **Webflow CMS**: $23/month (CMS plan) or $39/month (Business)
- **Sync Service**: 
  - GitHub Actions: Free (public) or ~$5/month (private)
  - AWS Lambda: ~$0.20/month
  - VPS: $5-10/month (if using existing server, $0)
- **Serverless Functions**: 
  - Vercel: Free tier (100GB bandwidth)
  - Netlify: Free tier (100GB bandwidth)

**Total Monthly**: ~$23-50/month (depending on hosting choices)

---

## Migration Path

### Option A: Clean Slate (Recommended)
1. Build new Webflow site with CMS integration
2. Run parallel sync (WordPress + Webflow) during transition
3. Switch DNS when ready
4. Decommission WordPress plugin

### Option B: Gradual Migration
1. Keep WordPress site live
2. Build Webflow site in parallel
3. Test thoroughly
4. Switch when confident

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Webflow API rate limits | Medium | Batch operations, add delays, consider paid plan |
| AppFolio HTML structure changes | High | Robust selectors, fallback strategies, monitoring |
| Image upload failures | Low | Continue without images, manual fix process |
| Sync service downtime | Medium | Monitoring, alerts, manual sync option |
| Contact form API changes | Medium | Test regularly, have fallback email option |

---

## Timeline Estimate

- **Phase 1** (Scraper): 6-8 hours
- **Phase 2** (Webflow Integration): 5-6 hours
- **Phase 3** (Automation): 2-3 hours
- **Phase 4** (Frontend): 3-4 hours
- **Phase 5** (Contact Form): 2-3 hours
- **Testing & Refinement**: 2-3 hours

**Total: 20-27 hours** (including buffer for testing)

---

## Recommendation

**Proceed with Webflow migration** if:
- Client prefers Webflow's user-friendly CMS
- Long-term maintenance by non-technical team is important
- Design flexibility is a priority

**Stick with WordPress** if:
- Current plugin works well
- Team is comfortable with WordPress
- Budget is tight (no ongoing Webflow costs)

The migration is **technically feasible** and the scraping logic is reusable. The main effort is in adapting the data storage and frontend to Webflow's architecture.



