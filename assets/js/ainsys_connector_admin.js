// ////////////////////////////////////
jQuery(function($){

    setTimeout(() => {
        $('.ainsys-logo').css('opacity', 1)
    }, 500)

    //////// Ajax clear log ////////
    $('#setting_section_log').on('click', '#clear_log', function (e){
        var data = {
            action: "clear_log",
            nonce: ainsys_connector_params.nonce
        };
        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            if(value){
                $('#connection_log').html(value);
            }
        });
    });

    //////// Ajax toggle loging controll, set log until time  ////////
    $('#setting_section_log').on('click', '.loging_controll', function (e){
        e.preventDefault();

        if ( $(this).hasClass("disabled") ){
            return;
        }
        var time = $( "#start_loging_timeinterval" ).val()
        var id = $(this).prop('id');
        $(this).addClass("disabled");
        var data = {
            action: "toggle_logging",
            command: id,
            time: time,
            nonce: ainsys_connector_params.nonce
        };
        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            $(value).removeClass("disabled");
            if (value == '#stop_loging') {
                $('#start_loging_timeinterval').addClass("disabled");
            } else {
                $('#start_loging_timeinterval').removeClass("disabled");
            }
        });
    });

    ////////  Ajax reload log HTML ////////
    $('#setting_section_log').on('click', '#reload_log', function (e){
        $.ajax({
            url: ainsys_connector_params.ajax_url,
            type: 'POST',
            data: {
                action: 'reload_log_html',
                // check
                nonce: ainsys_connector_params.nonce
            },
            success: function (msg) {
                if(msg){
                    $('#connection_log').html(msg);
                }
            }//,
            // error: function (jqXHR, exception) {
            //
            // }
        })
    });

    //////// Ajax remove ainsys integration ////////
    $('#setting_section_general').on('click', '#remove_ainsys_integration', function (e){
        var data = {
            action: "remove_ainsys_integration",
            nonce: ainsys_connector_params.nonce
        };
        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            location.reload();
        });
    });

    //////// Expand/collapse data contaner ////////
    $('#setting_section_log').on('click', '.expand_data_contaner', function (e){
        $(this).parent().toggleClass('expand_tab');

        var text = $(this).text() == 'less' ? 'more' : 'less';
        $(this).text(text);

    })

    /////////////////////////////////
    ////////////Settings tabs///////

    $('.ainsys-nav-tab-wrapper').on('click', '.nav-tab', function(event){

        event.preventDefault();

        var targ = $(this).data('target');

        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-target').removeClass('tab-target-active');
        $(this).addClass('nav-tab-active');
        $('#'+targ).addClass('tab-target-active');

        var ref = $('.ainsys_settings_wrap input[name="_wp_http_referer"]').val();
        var new_ref = ref;
        var query_string = {};
        var url_vars = ref.split("?");
        if (url_vars.length > 1){
            new_ref = url_vars[0] + '?';
            var url_pairs = url_vars[1].split("&");
            for (var i=0;i<url_pairs.length;i++){

                var pair = url_pairs[i].split("=");

                if (pair[0] != 'setting_tab'){
                    new_ref = new_ref + url_pairs[i] + '&';
                }
            }
        } else {
            new_ref = new_ref + '?';
        }
        new_ref = new_ref + 'setting_tab=' + targ;
        $('.ainsys_settings_wrap input[name="_wp_http_referer"]').val(new_ref);
    });
});
