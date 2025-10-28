(function ($) {
  'use strict';
  
  $(document).ready(function () {
    // Lovers modal gallery if needed
    $('.gallery-item').on('click', function (e) {
      e.preventDefault();
      // Could add lightbox functionality here
    });
    
    // Handle sorting dropdown
    $('.ffp-sort-select').on('change', function () {
      var $select = $(this);
      var sortValue = $select.val();
      var $grid = $select.closest('.ffp-content-area').find('.ffp-grid');
      
      // Update current sort
      $grid.attr('data-sort', sortValue);
      
      // Get current URL parameters
      var urlParams = new URLSearchParams(window.location.search);
      urlParams.set('sort', sortValue);
      
      // Reload with new sort parameter
      window.location.search = urlParams.toString();
    });
    
    // Handle filter checkbox changes
    $('.ffp-filter-checkbox').on('change', function () {
      applyFilters();
    });
    
    // Initialize price range slider
    initPriceSlider();
    
    function applyFilters() {
      var urlParams = new URLSearchParams(window.location.search);
      
      // Clear sort when filters change
      urlParams.delete('sort');
      
      // Get filter values
      var unitTypes = [];
      $('.ffp-filter-checkbox[name="unit_type[]"]:checked').each(function () {
        unitTypes.push($(this).val());
      });
      
      // Handle unit type filter
      if (unitTypes.length > 0) {
        urlParams.delete('beds');
        urlParams.delete('unit_type[]');
        unitTypes.forEach(function (value) {
          urlParams.append('unit_type[]', value);
        });
      } else {
        urlParams.delete('beds');
        urlParams.delete('unit_type[]');
      }
      
      // Handle available only
      if ($('.ffp-filter-checkbox[name="available_only"]').is(':checked')) {
        urlParams.set('available_only', '1');
      } else {
        urlParams.delete('available_only');
      }
      
      // Handle price range
      var minPrice = $('#ffp-min-price').val();
      var maxPrice = $('#ffp-max-price').val();
      
      if (minPrice && minPrice != '0') {
        urlParams.set('min_price', minPrice);
      } else {
        urlParams.delete('min_price');
      }
      
      if (maxPrice && maxPrice != '10000') {
        urlParams.set('max_price', maxPrice);
      } else {
        urlParams.delete('max_price');
      }
      
      // Reload with new filters
      window.location.search = urlParams.toString();
    }
    
    function initPriceSlider() {
      var minVal = parseInt($('#ffp-min-price').val()) || 0;
      var maxVal = parseInt($('#ffp-max-price').val()) || 10000;
      
      $('#ffp-price-range').empty();
      
      var sliderHTML = '<div class="ffp-slider-track"></div>' +
        '<input type="range" id="price-min" min="0" max="10000" value="' + minVal + '" step="100">' +
        '<input type="range" id="price-max" min="0" max="10000" value="' + maxVal + '" step="100">';
      $('#ffp-price-range').html(sliderHTML);
      
      var track = $('.ffp-slider-track');
      
      var minSlider = document.getElementById('price-min');
      var maxSlider = document.getElementById('price-max');
      
      function updateSliderTrack() {
        var minPercent = (minSlider.value / 10000) * 100;
        var maxPercent = (maxSlider.value / 10000) * 100;
        track.css('left', minPercent + '%');
        track.css('width', (maxPercent - minPercent) + '%');
      }
      
      function updateMinValue() {
        if (parseInt(minSlider.value) >= parseInt(maxSlider.value)) {
          minSlider.value = maxSlider.value - 100;
        }
        $('#ffp-min-price').val(minSlider.value);
        $('#ffp-min-display').text(minSlider.value);
        updateSliderTrack();
      }
      
      function updateMaxValue() {
        if (parseInt(maxSlider.value) <= parseInt(minSlider.value)) {
          maxSlider.value = parseInt(minSlider.value) + 100;
        }
        $('#ffp-max-price').val(maxSlider.value);
        $('#ffp-max-display').text(maxSlider.value);
        updateSliderTrack();
      }
      
      // Initialize track position
      updateSliderTrack();
      
      // Bring min slider to front on interaction
      minSlider.addEventListener('mousedown', function () {
        minSlider.style.zIndex = '10';
        maxSlider.style.zIndex = '3';
      });
      
      // Bring max slider to front on interaction
      maxSlider.addEventListener('mousedown', function () {
        maxSlider.style.zIndex = '10';
        minSlider.style.zIndex = '2';
      });
      
      // Reset z-index after interaction
      minSlider.addEventListener('mouseup', function () {
        minSlider.style.zIndex = '2';
        maxSlider.style.zIndex = '3';
      });
      
      maxSlider.addEventListener('mouseup', function () {
        minSlider.style.zIndex = '2';
        maxSlider.style.zIndex = '3';
      });
      
      minSlider.addEventListener('input', function () {
        updateMinValue();
      });
      
      maxSlider.addEventListener('change', function () {
        updateMaxValue();
        applyFilters();
      });
      
      minSlider.addEventListener('change', function () {
        updateMinValue();
        applyFilters();
      });
      
      maxSlider.addEventListener('input', function () {
        updateMaxValue();
      });
    }
  });
})(jQuery);

