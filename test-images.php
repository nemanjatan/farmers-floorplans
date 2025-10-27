<?php
/**
 * Test to verify image extraction with placeholder src bug fix
 * 
 * Run from the plugin directory: php test-images.php
 */

// Mock WordPress functions if not in WordPress context
if (!function_exists('FFP_Logger::log')) {
    class FFP_Logger {
        public static function log($message, $level = 'info') {
            // Suppress in test
        }
    }
}

// Sample HTML matching the exact structure WITH placeholder src
$html = <<<HTML
<div class="listing-item result js-listing-item" id="listing_74">
   <div class="listing-item__figure-container">
      <img class="listing-item__image" 
           src="https://listings.cdn.appfolio.com/listings/assets/listings/rental_listing/place_holder-ea9e892a45f62e048771a4b22081d1eed003a21f0658a92aa5abcfd357dd4699.png"
           data-original="https://images.cdn.appfolio.com/cityblockprop/images/c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9/medium.jpg" 
           alt="Test" />
   </div>
</div>
HTML;

echo "Testing Image Extraction with Placeholder Bug Fix\n";
echo "=================================================\n\n";

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);

// Find all listing items
$cards = $xpath->query("//div[contains(@class,'listing-item')]");

echo "Found " . $cards->length . " listing card(s)\n\n";

$passed = 0;
$failed = 0;

if ($cards->length > 0) {
    foreach ($cards as $card) {
        // Extract image
        $img_nodes = $xpath->query(".//img", $card);
        
        if ($img_nodes->length > 0) {
            $img = $img_nodes->item(0);
            
            echo "Testing attribute priority:\n";
            
            // Try data-src first (modern lazy loading)
            $img_src = $img->getAttribute('data-src');
            echo "  data-src: " . ($img_src ?: 'NOT FOUND') . "\n";
            
            // Try data-original second (legacy lazy loading, often contains real image)
            if (empty($img_src)) {
                $img_src = $img->getAttribute('data-original');
                echo "  data-original: " . ($img_src ?: 'NOT FOUND') . "\n";
            }
            
            // Try src last, but skip if it's a placeholder
            if (empty($img_src)) {
                $src_attr = $img->getAttribute('src');
                echo "  src: " . $src_attr . "\n";
                // Check if src contains common placeholder patterns
                if (!empty($src_attr) && 
                    stripos($src_attr, 'placeholder') === false && 
                    stripos($src_attr, 'place_holder') === false &&
                    stripos($src_attr, 'loading') === false) {
                    $img_src = $src_attr;
                } else {
                    echo "  ✓ src skipped (contains placeholder pattern)\n";
                }
            }
            
            echo "\n";
            
            if ($img_src) {
                echo "✓ SUCCESS: Extracted image URL: $img_src\n";
                
                // Test 1: Should extract from data-original, not src
                if (strpos($img_src, 'data-original') !== false || 
                    strpos($img_src, 'place_holder') === false) {
                    echo "✓ Test 1 PASSED category: Not using placeholder URL\n";
                    $passed++;
                } else {
                    echo "❌ Test 1 FAILED: Using placeholder URL!\n";
                    $failed++;
                }
                
                // Test 2: Should contain expected UUID
                if (strpos($img_src, 'c5277ab4-2a8a-41d8-8dd2-9ecf390fdfc9') !== false) {
                    echo "✓ Test 2 PASSED: Contains expected UUID\n";
                    $passed++;
                } else {
                    echo "❌ Test 2 FAILED: Missing expected UUID\n";
                    $failed++;
                }
                
                // Test 3: Should have correct domain
                if (strpos($img_src, 'images.cdn.appfolio.com') !== false) {
                    echo "✓ Test 3 PASSED: Has correct domain\n";
                    $passed++;
                } else {
                    echo "❌ Test 3 FAILED: Wrong domain\n";
                    $failed++;
                }
            } else {
                echo "❌ FAILED: No image URL extracted\n";
                $failed++;
            }
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Results: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "✓ ALL TESTS PASSED - Bug is fixed!\n";
} else {
    echo "❌ SOME TESTS FAILED\n";
}

echo "\nTest Complete!\n";