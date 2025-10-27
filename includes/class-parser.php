<?php
/**
 * HTML Parser for AppFolio listings
 */

if (!defined('ABSPATH')) {
    exit;
}

class FFP_Parser {
    
    private $building_filter;
    
    public function __construct($building_filter) {
        $this->building_filter = $building_filter;
    }
    
    /**
     * Parse HTML and extract listings
     */
    public function parse($html) {
        $listings = [];
        
        // Debug: save HTML for inspection
        if (defined('WP_DEBUG') && WP_DEBUG) {
            update_option('ffp_last_html', substr($html, 0, 50000)); // Save first 50KB
        }
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        // Try multiple selector strategies with better logging
        $selector_strategies = [
            "//div[contains(@class,'listing-card')] | //div[contains(@class,'listing-item')] | //div[contains(@class,'property-card')] | //article[contains(@class,'listing')]",
            "//div[contains(@class,'card')]",
            "//div[contains(@class,'listing')]",
            "//div[contains(@class,'property')]",
            "//div[contains(@class,'apt')]",
            "//div[contains(@class,'unit')]",
            "//a[contains(@class,'listing')]",
            "//a[contains(@href,'/listings/')]",
        ];
        
        $cards = null;
        $used_strategy = '';
        
        foreach ($selector_strategies as $strategy) {
            $cards = $xpath->query($strategy);
            if ($cards->length > 0) {
                $used_strategy = $strategy;
                FFP_Logger::log("Found {$cards->length} elements using strategy: " . substr($strategy, 0, 100), 'info');
                break;
            }
        }
        
        if (!$cards || $cards->length === 0) {
            FFP_Logger::log('No cards found with any selector strategy. HTML might be dynamically loaded or structure changed.', 'warning');
            
            // Debug: log sample HTML structure
            $sample = substr(strip_tags($html), 0, 500);
            FFP_Logger::log('Sample HTML text: ' . $sample, 'info');
            
            return $listings;
        }
        
        $sample_listings = [];
        $potential_matches = [];
        foreach ($cards as $card) {
            $listing = $this->extract_listing_data($card, $xpath);
            
        // Store first few listings for debugging
        if (count($sample_listings) < 3 && !empty($listing['title'])) {
            // Include image info in sample
            $sample_with_images = [
                'title' => $listing['title'] ?? '',
                'address' => $listing['address'] ?? '',
                'price' => $listing['price'] ?? '',
                'has_image' => !empty($listing['image_url']),
                'image_url' => $listing['image_url'] ?? '',
                'has_detail_url' => !empty($listing['detail_url']),
                'detail_url' => $listing['detail_url'] ?? '',
                'gallery_count' => isset($listing['gallery_images']) ? count($listing['gallery_images']) : 0,
            ];
            $sample_listings[] = $sample_with_images;
        }
            
            // Look for potential matches with "Broad" or "580" 
            if (!empty($listing['address']) && 
                (stripos($listing['address'], 'Broad') !== false || 
                 stripos($listing['address'], '580') !== false ||
                 stripos($listing['title'] ?? '', 'Farmer') !== false)) {
                $potential_matches[] = $listing;
            }
            
            if ($listing && $this->matches_building_filter($listing)) {
                $listings[] = $listing;
            }
        }
        
        // Log potential matches
        if (!empty($potential_matches)) {
            FFP_Logger::log("Found " . count($potential_matches) . " potential matches for Farmer's Exchange:", 'info');
            foreach (array_slice($potential_matches, 0, 5) as $idx => $match) {
                FFP_Logger::log("Potential match #{$idx}: " . json_encode([
                    'title' => $match['title'] ?? '',
                    'address' => $match['address'] ?? '',
                ]), 'info');
            }
        }
        
        // Log sample data for debugging
        if (!empty($sample_listings)) {
            foreach ($sample_listings as $idx => $sample) {
                FFP_Logger::log("Sample listing #{$idx}: " . json_encode([
                    'title' => $sample['title'] ?? '',
                    'address' => $sample['address'] ?? '',
                    'price' => $sample['price'] ?? '',
                    'bedrooms' => $sample['bedrooms'] ?? '',
                    'bathrooms' => $sample['bathrooms'] ?? '',
                ]), 'info');
            }
        }
        
        if (count($listings) === 0 && $cards->length > 0) {
            $filter_text = htmlspecialchars($this->building_filter, ENT_QUOTES);
            FFP_Logger::log("Building filter '{$filter_text}' did not match any listings. Check the filter text matches the actual building names.", 'warning');
        }
        
        FFP_Logger::log("Extracted {$cards->length} cards, " . count($listings) . " matched building filter", 'info');
        
        return $listings;
    }
    
    /**
     * Extract data from a listing card
     */
    private function extract_listing_data($card, $xpath) {
        $listing = [];
        
        // Extract title
        $title_nodes = $xpath->query(".//h2 | .//h3 | .//h4 | .//a[contains(@class,'title')] | .//div[contains(@class,'title')]", $card);
        if ($title_nodes->length > 0) {
            $listing['title'] = trim($title_nodes->item(0)->textContent);
        }
        
        // Extract price
        $price_nodes = $xpath->query(".//*[contains(@class,'price')] | .//*[contains(text(),'$')]", $card);
        if ($price_nodes->length > 0) {
            $price_text = trim($price_nodes->item(0)->textContent);
            $listing['price'] = $this->extract_price($price_text);
        }
        
        // Extract from structured detail-box format first
        $detail_items = $xpath->query(".//div[@class='detail-box__item']", $card);
        
        foreach ($detail_items as $item) {
            $label_nodes = $xpath->query(".//dt[@class='detail-box__label']", $item);
            $value_nodes = $xpath->query(".//dd[@class='detail-box__value']", $item);
            
            if ($label_nodes->length > 0 && $value_nodes->length > 0) {
                $label = trim($label_nodes->item(0)->textContent);
                $value = trim($value_nodes->item(0)->textContent);
                
                if (stripos($label, 'Square Feet') !== false || stripos($label, 'sq ft') !== false) {
                    $listing['sqft'] = $this->extract_number($value);
                } elseif (stripos($label, 'Bed / Bath') !== false || stripos($label, 'bed') !== false) {
                    // Extract bedrooms and bathrooms from format like "3 bd / 1 ba"
                    if (preg_match('/(\d+)\s*bd/i', $value, $bed_matches)) {
                        $listing['bedrooms'] = floatval($bed_matches[1]);
                    }
                    if (preg_match('/(\d+\.?\d*)\s*ba/i', $value, $bath_matches)) {
                        $listing['bathrooms'] = floatval($bath_matches[1]);
                    }
                } elseif (stripos($label, 'Bedrooms') !== false) {
                    $listing['bedrooms'] = $this->extract_number($value);
                } elseif (stripos($label, 'Bathrooms') !== false) {
                    $listing['bathrooms'] = $this->extract_number($value);
                }
            }
        }
        
        // Fallback: Extract bedrooms from text (if not found in detail-box)
        if (empty($listing['bedrooms'])) {
            $bed_nodes = $xpath->query(".//*[contains(@class,'bed')] | .//*[contains(text(),'bed')]", $card);
            if ($bed_nodes->length > 0) {
                $bed_text = trim($bed_nodes->item(0)->textContent);
                $listing['bedrooms'] = $this->extract_number($bed_text);
            }
        }
        
        // Fallback: Extract bathrooms from text (if not found in detail-box)
        if (empty($listing['bathrooms'])) {
            $bath_nodes = $xpath->query(".//*[contains(@class,'bath')] | .//*[contains(text(),'bath')]", $card);
            if ($bath_nodes->length > 0) {
                $bath_text = trim($bath_nodes->item(0)->textContent);
                $listing['bathrooms'] = $this->extract_number($bath_text);
            }
        }
        
        // Fallback: Extract square feet from text (if not found in detail-box)
        if (empty($listing['sqft'])) {
            $sqft_nodes = $xpath->query(".//*[contains(@class,'sqft')] | .//*[contains(@class,'sq-ft')] | .//*[contains(text(),'sq')]", $card);
            if ($sqft_nodes->length > 0) {
                $sqft_text = trim($sqft_nodes->item(0)->textContent);
                $listing['sqft'] = $this->extract_number($sqft_text);
            }
        }
        
        // Extract address
        $address_nodes = $xpath->query(".//*[contains(@class,'address')] | .//address", $card);
        if ($address_nodes->length > 0) {
            $listing['address'] = trim($address_nodes->item(0)->textContent);
        }
        
        // Extract availability
        $avail_nodes = $xpath->query(".//*[contains(@class,'available')] | .//*[contains(@class,'availability')]", $card);
        if ($avail_nodes->length > 0) {
            $listing['available'] = trim($avail_nodes->item(0)->textContent);
        }
        
        // Extract image URL - check multiple attributes for lazy loading
        $img_nodes = $xpath->query(".//img", $card);
        if ($img_nodes->length > 0) {
            $img = $img_nodes->item(0);
            
            // Try data-src first (modern lazy loading)
            $img_src = $img->getAttribute('data-src');
            
            // Try data-original second (legacy lazy loading, often contains real image)
            if (empty($img_src)) {
                $img_src = $img->getAttribute('data-original');
            }
            
            // Try src last, but skip if it's a placeholder
            if (empty($img_src)) {
                $src_attr = $img->getAttribute('src');
                // Check if src contains common placeholder patterns
                if (!empty($src_attr) && 
                    stripos($src_attr, 'placeholder') === false && 
                    stripos($src_attr, 'place_holder') === false &&
                    stripos($src_attr, 'loading') === false) {
                    $img_src = $src_attr;
                }
            }
            
            if ($img_src) {
                $listing['image_url'] = $this->normalize_url($img_src);
            }
        }
        
        // Debug: Log when image extraction fails
        if (empty($listing['image_url']) && $img_nodes->length > 0) {
            $img = $img_nodes->item(0);
            $src_attr = $img->getAttribute('src');
            $data_src = $img->getAttribute('data-src');
            if (empty($src_attr) && empty($data_src)) {
                FFP_Logger::log('Image node found but no valid src/data-src attributes', 'warning');
            }
        }
        
        // Extract detail URL
        $link_nodes = $xpath->query(".//a[@href]", $card);
        if ($link_nodes->length > 0) {
            $href = $link_nodes->item(0)->getAttribute('href');
            if ($href) {
                $listing['detail_url'] = $this->normalize_url($href);
            }
        } else {
            // Try "View Details" button
            $view_detail_nodes = $xpath->query(".//a[contains(@class,'view') or contains(@class,'detail')]", $card);
            if ($view_detail_nodes->length > 0) {
                $href = $view_detail_nodes->item(0)->getAttribute('href');
                if ($href) {
                    $listing['detail_url'] = $this->normalize_url($href);
                }
            }
        }
        
        // Extract unit number
        $unit_nodes = $xpath->query(".//*[contains(@class,'unit')] | .//*[contains(text(),'Unit')]", $card);
        if ($unit_nodes->length > 0) {
            $listing['unit'] = trim($unit_nodes->item(0)->textContent);
        }
        
        // Generate source ID (extracts UUID from detail URL if available)
        $listing['source_id'] = $this->generate_source_id($listing);
        
        // If we have a detail URL, fetch additional gallery images
        if (!empty($listing['detail_url'])) {
            FFP_Logger::log('Fetching detail page for: ' . substr($listing['detail_url'], 0, 80), 'info');
            $listing['gallery_images'] = $this->fetch_detail_page_images($listing['detail_url']);
            FFP_Logger::log('Detail page returned ' . count($listing['gallery_images']) . ' images', 'info');
        } else {
            FFP_Logger::log('No detail URL found for listing: ' . ($listing['title'] ?? 'Unknown'), 'warning');
        }
        
        return $listing;
    }
    
    /**
     * Extract price from text
     */
    private function extract_price($text) {
        preg_match('/\$[\d,]+/', $text, $matches);
        if (!empty($matches)) {
            return intval(str_replace([',', '$'], '', $matches[0]));
        }
        return 0;
    }
    
    /**
     * Extract number from text
     */
    private function extract_number($text) {
        // Remove commas and extract number
        $cleaned = str_replace(',', '', $text);
        preg_match('/[\d.]+/', $cleaned, $matches);
        if (!empty($matches)) {
            return floatval($matches[0]);
        }
        return '';
    }
    
    /**
     * Normalize URL (make absolute)
     */
    private function normalize_url($url) {
        if (empty($url)) {
            return '';
        }
        
        // If already absolute
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }
        
        // Make absolute
        $base_url = 'https://cityblockprop.appfolio.com';
        if (strpos($url, '/') === 0) {
            return $base_url . $url;
        }
        
        return $base_url . '/' . $url;
    }
    
    /**
     * Fetch gallery images from detail page
     */
    private function fetch_detail_page_images($url) {
        $images = [];
        
        // Fetch the detail page
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
            'sslverify' => true,
        ]);
        
        if (is_wp_error($response)) {
            FFP_Logger::log('Failed to fetch detail page for gallery images: ' . $response->get_error_message(), 'warning');
            return $images;
        }
        
        $html = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            FFP_Logger::log('Detail page returned non-200 status: ' . $code, 'warning');
            return $images;
        }
        
        // Parse gallery images
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        // Find gallery images - look for images with AppFolio CDN URLs
        $img_nodes = $xpath->query("//img[contains(@src, 'images.cdn.appfolio.com')]");
        
        foreach ($img_nodes as $img) {
            $img_src = $img->getAttribute('src');
            if ($img_src && !in_array($img_src, $images)) {
                // Convert medium.jpg to large.jpg for better quality
                $img_src = str_replace('/medium.jpg', '/large.jpg', $img_src);
                $images[] = $this->normalize_url($img_src);
            }
        }
        
        if (count($images) > 0) {
            FFP_Logger::log('Found ' . count($images) . ' gallery images from detail page', 'info');
        }
        
        return $images;
    }
    
    /**
     * Generate unique source ID
     */
    private function generate_source_id($listing) {
        // First, try to extract UUID from detail URL
        if (!empty($listing['detail_url'])) {
            // Extract UUID from URLs like: /listings/detail/e599d603-285e-4e3a-bc70-0ab420966bb2
            if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $listing['detail_url'], $matches)) {
                return $matches[1];
            }
        }
        
        // Fallback to hash-based ID if no UUID found
        $parts = [
            $listing['address'] ?? '',
            $listing['unit'] ?? '',
            $listing['price'] ?? '',
            $listing['bedrooms'] ?? '',
        ];
        
        return md5(implode('|', $parts));
    }
    
    /**
     * Check if listing matches building filter
     */
    private function matches_building_filter($listing) {
        if (empty($this->building_filter)) {
            return true;
        }
        
        $search_text = $this->building_filter;
        $text_to_search = strtolower(
            ($listing['address'] ?? '') . ' ' . 
            ($listing['title'] ?? '') . ' ' .
            ($listing['unit'] ?? '')
        );
        
        // Case-insensitive search
        return strpos($text_to_search, strtolower($search_text)) !== false;
    }
}

