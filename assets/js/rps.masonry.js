/* global masonry getRpsMasonryItems */
/* eslint no-undef: ["error", { "typeof": false }] */
function rpsImagesReveal($, document) {
  $(document).ready(function onReady() {
    var $items;
    var $container;
    $container = $('.gallery-masonry').masonry({
      itemSelector: '.gallery-item-masonry',
      columnWidth: '.grid-sizer',
      isFitWidth: true
    });
    if (typeof getRpsMasonryItems === 'function') {
      $items = getRpsMasonryItems();
      $container.masonryImagesReveal($items);
    }
  });
}

function rpsMasonryImagesReveal($items) {
  var msnry = this.data('masonry');
  var itemSelector = msnry.options.itemSelector;

  // hide by default
  $items.hide();

  // append to container
  this.append($items);
  $items.imagesLoaded().progress(function handleProgress(imgLoad, image) {
    // get item
    // image is imagesLoaded class, not <img>, <img> is image.img
    var $item;
    $item = jQuery(image.img).parents(itemSelector);

    // un-hide item
    $item.show();

    // masonry does its thing
    msnry.appended($item);
  });

  return this;
}
jQuery.fn.masonryImagesReveal = rpsMasonryImagesReveal;
rpsImagesReveal(window.jQuery, document);
