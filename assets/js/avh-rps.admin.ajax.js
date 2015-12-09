(function ($) {
  var RPS = {
    scored: function () {
      $('.adm-scored').click(
        function () {
          var args = {
            'action': 'setscore',
            'id': $(this).attr('data-id'),
            'scored': $(this).attr('data-scored')
          };
          $.ajax({
            type: 'POST',
            url: ajaxurl,
            global: false,
            data: args,
            datatype: 'json',
            context: $('#competition-' + args.id),
            success: function (data) {
              var response = JSON.parse(data);
              $(this).find('.text').text(response.text);
              $(this).find('.adm-scored').attr('data-scored',
                response.scored);
              $(this).find('.adm-scored').text(
                response.scoredtext);
            }
          });
        });
    }
  };
  $(document).ready(RPS.scored);
})(jQuery);
