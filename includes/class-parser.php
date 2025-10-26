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
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        // Try multiple selector strategies
        $cards = $xpath->query("//div[contains(@class,'listing-card')] | //div[contains(@class,'listing-item')] | //div[contains(@class,'property-card')] | //article[contains(@class,'listing')]");
        
        if ($cards->length === 0) {
            // Fallback: look for any card-like structure
            $cards = $xpath->query("//div[contains(@class,'card')]");
        }
        
        foreach ($cards as $card) {
            $listing = $this->extract_listing_data($card, $xpath);
            
            if ($listing && $this->matches_building_filter($listing)) {
                $listings[] = $listing;
            }
        }
        
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
        $text_to_search = strtolower($listing['address'] . ' ' . ($listing['title'] ?? ''));
        
        return strpos($text_to_search, strtolower($search_text)) !== false;
    }
}

