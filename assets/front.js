(function ($) {
  'use strict';
  
  $(document).ready(function () {
    // Handle gallery thumbnail clicks to update main image
    $('.gallery-thumb').on('click', function (e) {
      e.preventDefault();
      var $thumb = $(this);
      var imageUrl = $thumb.data('image-url') || $thumb.attr('href');
      
      // Update active state
      $('.gallery-thumb').removeClass('active');
      $thumb.addClass('active');
      
      // Update main image
      var $mainImage = $('#ffp-main-gallery-image');
      if ($mainImage.length && imageUrl) {
        $mainImage.fadeOut(200, function () {
          $mainImage.attr('src', imageUrl).fadeIn(200);
        });
      }
    });
    
    // Lovers modal gallery if needed
    $('.gallery-item').on('click', function (e) {
      e.preventDefault();
      // Could add lightbox functionality here
    });
    
    // Handle sorting dropdown (AJAX)
    $('.ffp-sort-select').on('change', function () {
      var $select = $(this);
      var sortValue = $select.val();
      var $wrapper = $select.closest('.ffp-content-area');
      var $grid = $wrapper.find('.ffp-grid');
      $grid.attr('data-sort', sortValue);
      fetchResults($wrapper, buildParams({sort: sortValue}));
    });
    
    // Handle filter checkbox changes
    $('.ffp-filter-checkbox').on('change', function () {
      applyFilters();
    });
    
    // Initialize price range slider
    initPriceSlider();
    
    function applyFilters() {
      var $wrapper = $('.ffp-content-area');
      var params = {};
      
      // Get filter values
      var unitTypes = [];
      $('.ffp-filter-checkbox[name="unit_type[]"]:checked').each(function () {
        unitTypes.push($(this).val());
      });
      
      if (unitTypes.length > 0) params['unit_type'] = unitTypes;
      
      // Handle available only
      if ($('.ffp-filter-checkbox[name="available_only"]').is(':checked')) params['available_only'] = '1';
      
      // Handle price range
      var minPrice = $('#ffp-min-price').val();
      var maxPrice = $('#ffp-max-price').val();
      
      if (minPrice && minPrice != '0') params['min_price'] = minPrice;
      if (maxPrice && maxPrice != '10000') params['max_price'] = maxPrice;
      
      fetchResults($wrapper, buildParams(params));
    }
    
    function buildParams(extra) {
      var params = extra || {};
      // Preserve current sort
      var currentSort = $('.ffp-grid').attr('data-sort');
      if (currentSort) params['orderby'] = currentSort;
      return params;
    }
    
    function updateUrl(params) {
      if (!window.history || !window.history.pushState) return;
      var url = new URL(window.location.href);
      // Clear existing filters
      ['sort', 'beds', 'min_price', 'max_price', 'available_only'].forEach(function (k) {
        url.searchParams.delete(k);
      });
      if (params['orderby']) url.searchParams.set('sort', params['orderby']);
      if (params['available_only']) url.searchParams.set('available_only', '1');
      if (params['min_price']) url.searchParams.set('min_price', params['min_price']);
      if (params['max_price']) url.searchParams.set('max_price', params['max_price']);
      if (params['unit_type']) {
        url.searchParams.delete('unit_type[]');
        params['unit_type'].forEach(function (v) {
          url.searchParams.append('unit_type[]', v);
        });
      }
      window.history.pushState({}, '', url.toString());
    }
    
    function fetchResults($wrapper, params) {
      if (typeof ffpFront === 'undefined') return;
      var $grid = $wrapper.find('.ffp-grid');
      var $spinner = $('<div class="ffp-loading">Loading…</div>').css({padding: '1rem', textAlign: 'center'});
      $grid.css('opacity', 0.5).before($spinner);
      
      var payload = $.extend({action: 'ffp_filter'}, params);
      if (params['unit_type']) payload['unit_type'] = params['unit_type'];
      
      $.ajax({
        url: ffpFront.ajaxUrl,
        type: 'POST',
        data: payload,
        success: function (resp) {
          if (resp && resp.success) {
            $grid.html(resp.data.html);
            updateUrl(params);
          }
        },
        complete: function () {
          $spinner.remove();
          $grid.css('opacity', 1);
        }
      });
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
    
    // Initialize card image carousels
    initCardCarousels();
    
    function initCardCarousels() {
      $('[data-carousel]').each(function () {
        var $gallery = $(this);
        var $slides = $gallery.find('.ffp-gallery-slide');
        var $dots = $gallery.closest('.ffp-card-image-carousel').find('.ffp-gallery-dot');
        var currentSlide = 0;
        var totalSlides = $slides.length;
        
        if (totalSlides <= 1) return;
        
        var $prevBtn = $gallery.closest('.ffp-card-image-carousel').find('.ffp-gallery-prev');
        var $nextBtn = $gallery.closest('.ffp-card-image-carousel').find('.ffp-gallery-next');
        
        function showSlide(index) {
          $slides.removeClass('active');
          $dots.removeClass('active');
          
          if (index >= totalSlides) index = 0;
          if (index < 0) index = totalSlides - 1;
          
          $slides.eq(index).addClass('active');
          $dots.eq(index).addClass('active');
          currentSlide = index;
        }
        
        $prevBtn.on('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          showSlide(currentSlide - 1);
        });
        
        $nextBtn.on('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          showSlide(currentSlide + 1);
        });
        
        $dots.on('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          showSlide($(this).data('slide'));
        });
      });
    }
  });
})(jQuery);

