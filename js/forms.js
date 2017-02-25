(function ($, Drupal) {

    $('#submit-form').click(function (e) {

        var form_id;
            form_id = $(this).parent().attr('id');
        var fields = {};

        $('.f_element').each(function (i, obj) {
            fields[$(obj).attr('name')] = $(obj).val();
        });


        var $data = {
            // 'form_id': form_id,
            'fields': fields
        }

        $data['form_id'] = form_id;

        $.ajax({
            type: $(this).parent().attr('method'),
            url: $(this).parent().attr('action'),
            contentType: "application/json; charset=utf-8",
            data: JSON.stringify($data),
            success: function (msg) {
                // alert(msg);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                // alert(errorThrown);
            }
        });

        e.preventDefault();
    });
})(jQuery, Drupal);
