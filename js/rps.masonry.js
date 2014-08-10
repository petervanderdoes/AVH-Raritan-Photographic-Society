(function ($, window, document) {
    // The $ is now locally scoped
    $(document).ready(function () {
        var $container = $('#gallery-month-entries').masonry({
            itemSelector: '.gallery-item-masonry',
            columnWidth: '.grid-sizer',
            isFitWidth: true
        });
        // reveal initial images
        $container.masonryImagesReveal($('#images').find('.gallery-item-masonry'));
    });

}(window.jQuery, window, document));

jQuery.fn.masonryImagesReveal = function ($items) {
    var msnry = this.data('masonry');
    var itemSelector = msnry.options.itemSelector;
    // hide by default
    $items.hide();
    // append to container
    this.append($items);
    $items.imagesLoaded().progress(function (imgLoad, image) {
        // get item
        // image is imagesLoaded class, not <img>, <img> is image.img
        var $item = jQuery(image.img).parents(itemSelector);
        // un-hide item
        $item.show();
        // masonry does its thing
        msnry.appended($item);
    });

    return this;
};