(function ($) {
  'use strict';

  /**
   * Generate random prices and values of change (percentage) every 5 sec and pass optional css class
   * to run global function setPrice() which displays those values
   * in black stripe in d_p_single_text_block paragraphs that contain modifier class "with-price"
   */
  Drupal.behaviors.d_demo_random_price = {
    attach: function (context, settings) {
      $(document).ready(function () {
        var $wrapper = $('.wrapper-d_p_single_text_block');
        if ($wrapper.hasClass('with-price')) {
          setInterval(function () {
              var number = (2000 + Math.floor(Math.random() * 1000)) / 100;

              if (localStorage.getItem('price')) {
                var oldPrice = localStorage.getItem('price');
                var change = Math.round((((number - oldPrice) * 100) / oldPrice)* 100) / 100;
              } else {
                var change = 0;
              }
              localStorage.setItem('price', number);
              var cssClass = 'testing';
              setPrice(number, change, cssClass);
            },
            5000);
        }
      });
    }
  };
})(jQuery);
