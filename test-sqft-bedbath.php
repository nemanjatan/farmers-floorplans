<?php
  /**
   * Test to verify Square Feet and Bed/Bath extraction
   *
   * Run from the plugin directory: php test-sqft-bedbath.php
   */
  
  // Mock WordPress functions if not in WordPress context
  if ( ! function_exists( 'FFP_Logger::log' ) ) {
    class FFP_Logger {
      public static function log( $message, $level = 'info' ) {
        // Suppress in test
      }
    }
  }
  
  // Sample HTML matching the exact structure
  $html = <<<HTML
<div class="listing-item result js-listing-item" id="listing_79">
   <div class="listing-item__body">
      <div class="detail-box hand-hidden u-space-bs js-listing-quick-facts">
         <dl>
            <div class="detail-box__item">
               <dt class="detail-box__label">RENT</dt>
               <dd class="detail-box__value">$2,550</dd>
            </div>
            <div class="detail-box__item">
               <dt class="detail-box__label">Square Feet</dt>
               <dd class="detail-box__value">1,248</dd>
            </div>
            <div class="detail-box__item">
               <dt class="detail-box__label">Bed / Bath</dt>
               <dd class="detail-box__value">3 bd / 1 ba</dd>
            </div>
            <div class="detail-box__item">
               <dt class="detail-box__label">Available</dt>
               <dd class="detail-box__value js-listing-available">8/3/26</dd>
            </div>
         </dl>
      </div>
   </div>
</div>
HTML;
  
  echo "Testing Square Feet and Bed/Bath Extraction\n";
  echo "==========================================\n\n";
  
  libxml_use_internal_errors( true );
  $dom = new DOMDocument();
  $dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
  $xpath = new DOMXPath( $dom );
  
  // Find listing cards
  $cards = $xpath->query( "//div[contains(@class,'listing-item')]" );
  
  echo "Found " . $cards->length . " listing card(s)\n\n";
  
  function extract_number( $text ) {
    // Remove commas and extract number
    $cleaned = str_replace( ',', '', $text );
    preg_match( '/[\d.]+/', $cleaned, $matches );
    if ( ! empty( $matches ) ) {
      return floatval( $matches[0] );
    }
    
    return '';
  }
  
  $passed = 0;
  $failed = 0;
  
  if ( $cards->length > 0 ) {
    foreach ( $cards as $card ) {
      $listing = [];
      
      // Extract from structured detail-box format
      $detail_items = $xpath->query( ".//div[@class='detail-box__item']", $card );
      
      foreach ( $detail_items as $item ) {
        $label_nodes = $xpath->query( ".//dt[@class='detail-box__label']", $item );
        $value_nodes = $xpath->query( ".//dd[@class='detail-box__value']", $item );
        
        if ( $label_nodes->length > 0 && $value_nodes->length > 0 ) {
          $label = trim( $label_nodes->item( 0 )->textContent );
          $value = trim( $value_nodes->item( 0 )->textContent );
          
          echo "Label: '$label' => Value: '$value'\n";
          
          if ( stripos( $label, 'Square Feet' ) !== false || stripos( $label, 'sq ft' ) !== false ) {
            $listing['sqft'] = extract_number( $value );
          } elseif ( stripos( $label, 'Bed / Bath' ) !== false || stripos( $label, 'bed' ) !== false ) {
            // Extract bedrooms and bathrooms from format like "3 bd / 1 ba"
            if ( preg_match( '/(\d+)\s*bd/i', $value, $bed_matches ) ) {
              $listing['bedrooms'] = floatval( $bed_matches[1] );
            }
            if ( preg_match( '/(\d+\.?\d*)\s*ba/i', $value, $bath_matches ) ) {
              $listing['bathrooms'] = floatval( $bath_matches[1] );
            }
          }
        }
      }
      
      echo "\nExtracted Values:\n";
      echo "  Square Feet: " . ( $listing['sqft'] ?? 'NOT FOUND' ) . "\n";
      echo "  Bedrooms: " . ( $listing['bedrooms'] ?? 'NOT FOUND' ) . "\n";
      echo "  Bathrooms: " . ( $listing['bathrooms'] ?? 'NOT FOUND' ) . "\n\n";
      
      // Test results
      if ( isset( $listing['sqft'] ) && $listing['sqft'] == 1248 ) {
        echo "✓ Test 1 PASSED: Square Feet extracted correctly (1,248)\n";
        $passed ++;
      } else {
        echo "❌ Test 1 FAILED: Expected 1248, got " . ( $listing['sqft'] ?? 'NOT FOUND' ) . "\n";
        $failed ++;
      }
      
      if ( isset( $listing['bedrooms'] ) && $listing['bedrooms'] == 3 ) {
        echo "✓ Test 2 PASSED: Bedrooms extracted correctly (3)\n";
        $passed ++;
      } else {
        echo "❌ Test 2 FAILED: Expected 3, got " . ( $listing['bedrooms'] ?? 'NOT FOUND' ) . "\n";
        $failed ++;
      }
      
      if ( isset( $listing['bathrooms'] ) && $listing['bathrooms'] == 1 ) {
        echo "✓ Test 3 PASSED: Bathrooms extracted correctly (1)\n";
        $passed ++;
      } else {
        echo "❌ Test 3 FAILED: Expected 1, got " . ( $listing['bathrooms'] ?? 'NOT FOUND' ) . "\n";
        $failed ++;
      }
    }
  }
  
  echo "\n" . str_repeat( "=", 50 ) . "\n";
  echo "Test Results: $passed passed, $failed failed\n";
  
  if ( $failed === 0 ) {
    echo "✓ ALL TESTS PASSED\n";
  } else {
    echo "❌ SOME TESTS FAILED\n";
  }
  
  echo "\nTest Complete!\n";
