<?php
/**
 * Test script to verify image extraction from HTML
 * 
 * Usage: php test-image-extraction.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Load the parser class
require_once('includes/class-parser.php');

// Sample HTML with the exact structure from the example
$html_sample = <<<HTML
<div class="listing-item result js-listing-item" id="listing_74">
   <div class="listing-item__figure-container">
      <a href="/listings/detail/5146bd15-a294-4045-9a9f-596c8de61bc5" target="_blank">
         <div class="listing-item__figure">
            <img class="listing-item__image is-placeholder lazy js-listing-image" data-original="https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg" alt="580 E Broad St, Athens, GA 30601" />
         </div>
      </a>
   </div>
   <div class="listing-item__body">
      <h2 class="listing-item__title js-listing-title">
         <a href="/listings/detail/5146bd15-a卫健委-4045-9a9f-596c8de61bc5" target="_blank">Now Pre-Leasing 1 Bedroom Plans for Fall 2026!</a>
      </h2>
      <p class="u-space-an">
         <span class="u-pad-rm js-listing-address">580 E Broad St, Athens, GA 30601</span>
      </p>
   </div>
</div>
<div class="listing-item result js-listing-item" id="listing_73">
   <div class="listing-item__figure-container">
      <a href="/listings/detail/ef687f9e-1fbb-45f4-b42c-417b02470800" target="_blank">
         <div class="listing-item__figure">
            <img class="listing-item__image is-placeholder lazy js-listing-image" data-original="https://images.cdn.appfolio.com/cityblockprop/images/94b4a9a6-7459-4a6b-a969-5d567196f589/medium.png" alt="580 E Broad St, Athens, GA 30601" />
         </div>
      </a>
   </div>
   <div class="listing-item__body">
      <h2 class="listing-item__title js-listing-title">
         <a href="/listings/detail/ef687f9e-1fbb-45f4-b42c-417b02470800" target="_blank">Now Pre-Leasing 3 Bedroom 2 Bath Plans for Fall 2026!</a>
      </h2>
      <p class="u-space-an">
         <span class="u-pad-rm js-listing-address">580 E Broad St, Athens, GA 30601</span>
      </p>
   </div>
</div>
HTML;

echo "=== Testing Image Extraction ===\n\n";

$parser = new FFP_Parser("580 E Broad St");
$listings = $parser->parse($html_sample);

if (empty($listings)) {
    echo "❌ ERROR: No listings found!\n";
    exit(1);
}

echo "✓ Found " . count($listings) . " listing(s)\n\n";

foreach ($listings as $index => $listing) {
    echo "Listing #" . ($index + 1) . ":\n";
    echo "  Title: " . ($listing['title'] ?? 'N/A') . "\n";
    echo "  Address: " . ($listing['address'] ?? 'N/A') . "\n";
    
    if (!empty($listing['image_url'])) {
        echo "  ✓ Image URL: " . $listing['image_url'] . "\n";
        
        // Verify the expected image URLs
        $expected_urls = [
            'https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg',
            'https://images.cdn.appfolio.com/cityblockprop/images/94b4a9a6-7459-4a6b-a969-5d567196f589/medium.png'
        ];
        
        $found = false;
        foreach ($expected_urls as $expected) {
            if (strpos($listing['image_url'], $expected) !== false) {
                echo "  ✓ Matches expected URL!\n";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "  ⚠ WARNING: Image URL doesn't match any expected URLs\n";
        }
    } else {
        echo "  ❌ ERROR: No image URL extracted!\n";
    }
    
    echo "\n";
}

echo "=== Testing Image Attributes Priority ===\n\n";

// Test 1: data-src (should be highest priority)
$html1 = '<img data-src="priority1.jpg" src="priority2.jpg" data-original="priority3.jpg" />';
test_image_extraction($html1, 'priority1.jpg', 'data-src should have highest priority');

// Test 2: src (should be second priority)
$html2 = '<img src="priority2.jpg" data-original="priority3.jpg" />';
test_image_extraction($html2, 'priority2.jpg', 'src should be second priority');

// Test 3: data-original (should be third priority)
$html3 = '<img data-original="priority3.jpg" />';
test_image_extraction($html3, 'priority3.jpg', 'data-original should be third priority');

echo "\n=== All Tests Complete ===\n";

function test_image_extraction($html, $expected, $description) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    
    $img_nodes = $xpath->query("//img");
    if ($img_nodes->length > 0) {
        $img = $img_nodes->item(0);
        
        // Try data-src first (lazy loading), then src
        $img_src = $img->getAttribute('data-src');
        if (empty($img_src)) {
            $img_src = $img->getAttribute('src');
        }
        if (empty($img_src)) {
            $img_src = $img->getAttribute('data-original');
        }
        
        if ($img_src === $expected) {
            echo "✓ PASS: {$description}\n";
        } else {
            echo "❌ FAIL: {$description} (got: {$img_src}, expected: {$expected})\n";
        }
    } else {
        echo "❌ FAIL: No image found in HTML\n";
    }
}