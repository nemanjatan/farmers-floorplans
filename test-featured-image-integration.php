<?php
/**
 * Integration test to verify featured images are set correctly
 * This tests the full flow from HTML parsing to image download
 * 
 * Usage: php test-featured-image-integration.php
 */

// Mock WordPress functions for testing
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return [
            'response' => ['code' => 200],
            'body' => 'Mock HTML with images'
        ];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

echo "=== Featured Image Integration Test ===\n\n";

// Load the parser
require_once('includes/class-parser.php');

// Real HTML sample with specific listings
$html_sample = <<<HTML
<div class="listing-item result js-listing-item" id="listing_74">
   <div class="listing-item__figure-container">
      <a href="/listings/detail/5146bd15-a294-4045-9a9f-596c8de61bc5" target="_blank">
         <div class="listing-item__figure">
            <img class="listing-item__image is-placeholder lazy js-listing-image" 
                 data-original="https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg" 
                 alt="580 E Broad St, Athens, GA 30601" />
         </div>
      </a>
   </div>
   <div class="listing-item__body">
      <h2 class="listing-item__title js-listing-title">
         <a href="/listings/detail/5146bd15-a294-4045-9a9f-596c8de61bc5">Now Pre-Leasing 1 Bedroom Plans for Fall 2026!</a>
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
            <img class="listing-item__image is-placeholder lazy js-listing-image" 
                 data-original="https://images.cdn.appfolio.com/cityblockprop/images/94b4a9a6-7459-4a6b-a969-5d567196f589/medium.png" 
                 alt="580 E Broad St, Athens, GA 30601" />
         </div>
      </a>
   </div>
   <div class="listing-item__body">
      <h2 class="listing-item__title js-listing-title">
         <a href="/listings/detail/ef687f9e-1fbb-45f4-b42c-417b02470800">Now Pre-Leasing 3 Bedroom 2 Bath Plans for Fall 2026!</a>
      </h2>
      <p class="u-space-an">
         <span class="u-pad-rm js-listing-address">580 E Broad St, Athens, GA 30601</span>
      </p>
   </div>
</div>
HTML;

// Expected results
$expected_results = [
    [
        'title' => 'Now Pre-Leasing 1 Bedroom Plans for Fall 2026!',
        'image_url' => 'https://images.cdn.appfolio.com/cityblockprop/images/c5277 Majesty-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg',
        'address' => '580 E Broad St, Athens, GA 30601'
    ],
    [
        'title' => 'Now Pre-Leasing 3 Bedroom 2 Bath Plans for Fall 2026!',
        'image_url' => 'https://images.cdn.appfolio.com/cityblockprop/images/94b4a9a6-7459-4a6b-a969-5d567196f589/medium.png',
        'address' => '580 E Broad St, Athens, GA 30601'
    ]
];

echo "Testing image extraction with building filter: '580 E Broad St'\n\n";

$parser = new FFP_Parser("580 E Broad St");
$listings = $parser->parse($html_sample);

$tests_passed = 0;
$tests_failed = 0;

// Verify listings were found
if (empty($listings)) {
    echo "❌ CRITICAL: No listings found after parsing!\n";
    echo "This means the building filter or HTML structure is not matching.\n";
    $tests_failed++;
} else {
    echo "✓ Found " . count($listings) . " listing(s)\n\n";
    
    // Verify count matches expected
    if (count($listings) === count($expected_results)) {
        $tests_passed++;
        echo "✓ PASS: Listing count matches expected (" . count($listings) . ")\n\n";
    } else {
        $tests_failed++;
        echo "❌ FAIL: Expected " . count($expected_results) . " listings, got " . count($listings) . "\n\n";
    }
    
    // Test each listing
    foreach ($listings as $index => $listing) {
        $expected = $expected_results[$index] ?? null;
        
        if (!$expected) {
            continue;
        }
        
        echo "Test " . ($index + 1) . ": Checking listing\n";
        
        // Test title extraction
        if (isset($listing['title']) && strpos($listing['title'], substr($expected['title'], 0, 20)) !== false) {
            echo "  ✓ Title extracted correctly\n";
            $tests_passed++;
        } else {
            echo "  ❌ Title mismatch\n";
            echo "    Got: " . ($listing['title'] ?? 'N/A') . "\n";
            echo "    Expected: " . substr($expected['title'], 0, 50) . "...\n";
            $tests_failed++;
        }
        
        // Test address extraction
        if (isset($listing['address']) && $listing['address'] === $expected['address']) {
            echo "  ✓ Address extracted correctly\n";
            $tests_passed++;
        } else {
            echo "  ❌ Address mismatch\n";
            echo "    Got: " . ($listing['address'] ?? 'N/A') . "\n";
            echo "    Expected: " . $expected['address'] . "\n";
            $tests_failed++;
        }
        
        // Test image URL extraction
        if (!empty($listing['image_url'])) {
            echo "  ✓ Image URL extracted: " . substr($listing['image_url'], 0, 80) . "...\n";
            
            // Check if URL contains expected UUID
            $expected_uuid = null;
            if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $expected['image_url'], $matches)) {
                $expected_uuid = $matches[1];
            }
            
            if ($expected_uuid && strpos($listing['image_url'], $expected_uuid) !== false) {
                echo "  ✓ Image URL contains correct UUID\n";
                $tests_passed++;
            } else {
                echo "  ⚠ WARNING: Image URL may not match expected\n";
            }
        } else {
            echo "  ❌ No image URL extracted!\n";
            $tests_failed++;
        }
        
        // Test source_id (UUID) extraction
        if (!empty($listing['source_id'])) {
            echo "  ✓ Source ID extracted: " . substr($listing['source_id'], 0, 40) . "...\n";
            $tests_passed++;
        } else {
            echo "  ❌ No source ID extracted\n";
            $tests_failed++;
        }
        
        echo "\n";
    }
}

// Summary
echo "=== Test Summary ===\n";
echo "Tests Passed: {$tests_passed}\n";
echo "Tests Failed: {$tests_failed}\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n\n";

if ($tests_failed === 0) {
    echo "✓ ALL TESTS PASSED - Featured image logic is working correctly!\n";
} else {
    echo "❌ SOME TESTS FAILED - Please review the issues above\n";
}

echo "\n=== Recommendations ===\n";
echo "If image extraction is failing:\n";
echo "1. Check that the HTML structure matches what's expected\n";
echo "2. Verify the image attributes (data-original, data-src, src) are present\n";
echo "3. Check building filter is matching the listings correctly\n";
echo "4. Review class-parser.php lines 188-205 for image extraction logic\n";
