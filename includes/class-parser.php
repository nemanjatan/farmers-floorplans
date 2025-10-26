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
                $sample_listings[] = $listing;
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
        
        // Extract bedrooms
        $bed_nodes = $xpath->query(".//*[contains(@class,'bed')] | .//*[contains(text(),'bed')]", $card);
        if ($bed_nodes->length > 0) {
            $bed_text = trim($bed_nodes->item(0)->textContent);
            $listing['bedrooms'] = $this->extract_number($bed_text);
        }
        
        // Extract bathrooms
        $bath_nodes = $xpath->query(".//*[contains(@class,'bath')] | .//*[contains(text(),'bath')]", $card);
        if ($bath_nodes->length > 0) {
            $bath_text = trim($bath_nodes->item(0)->textContent);
            $listing['bathrooms'] = $this->extract_number($bath_text);
        }
        
        // Extract square feet
        $sqft_nodes = $xpath->query(".//*[contains(@class,'sqft')] | .//*[contains(@class,'sq-ft')] | .//*[contains(text(),'sq')]", $card);
        if ($sqft_nodes->length > 0) {
            $sqft_text = trim($sqft_nodes->item(0)->textContent);
            $listing['sqft'] = $this->extract_number($sqft_text);
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
        
        // Extract image URL
        $img_nodes = $xpath->query(".//img", $card);
        if ($img_nodes->length > 0) {
            $img_src = $img_nodes->item(0)->getAttribute('src');
            if ($img_src) {
                $listing['image_url'] = $this->normalize_url($img_src);
            }
        }
        
        // Extract detail URL
        $link_nodes = $xpath->query(".//a[@href]", $card);
        if ($link_nodes->length > 0) {
            $href = $link_nodes->item(0)->getAttribute('href');
            if ($href) {
                $listing['detail_url'] = $this->normalize_url($href);
            }
        }
        
        // Extract unit number
        $unit_nodes = $xpath->query(".//*[contains(@class,'unit')] | .//*[contains(text(),'Unit')]", $card);
        if ($unit_nodes->length > 0) {
            $listing['unit'] = trim($unit_nodes->item(0)->textContent);
        }
        
        // Generate source ID
        $listing['source_id'] = $this->generate_source_id($listing);
        
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
        preg_match('/[\d.]+/', $text, $matches);
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
     * Generate unique source ID
     */
    private function generate_source_id($listing) {
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

