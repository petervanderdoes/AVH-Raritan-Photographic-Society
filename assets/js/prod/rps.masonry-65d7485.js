function rpsImagesReveal(e,a){e(a).ready(function(){var a,s;s=e(".gallery-masonry").masonry({itemSelector:".gallery-item-masonry",columnWidth:".grid-sizer",isFitWidth:!0}),"function"==typeof getRpsMasonryItems&&(a=getRpsMasonryItems(),s.masonryImagesReveal(a))})}function rpsMasonryImagesReveal(e){var a=this.data("masonry"),s=a.options.itemSelector;return e.hide(),this.append(e),e.imagesLoaded().progress(function(e,r){var n;n=jQuery(r.img).parents(s),n.show(),a.appended(n)}),this}jQuery.fn.masonryImagesReveal=rpsMasonryImagesReveal,rpsImagesReveal(window.jQuery,document);