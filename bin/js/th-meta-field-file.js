/**
 * jQuery media file for file field.
 *
 * @since 0.1.0
 *
 * @package TH Meta
 * @author  Thijs Huijssoon
 */
jQuery(document).ready(function($){

    $('.th-clear-media').each( function() {
        var link = $(this);
        var id = link.attr('id').replace('-clear', '');
        if( $('#'+id+'-url').val() === '' ) {
            link.hide();
        }
    });

    $(document.body).on('click.thOpenMediaManager', '.th-clear-media', function(e){
        // Prevent the default action from occuring.
        e.preventDefault();

        var link = $(this);
        var id = link.attr('id').replace('-clear', '');
        var fi = link.parent().find('.attachment-info');
        $('#'+id+'-url').val('');
        $('#'+id+'-id').val('');
        $(this).hide();
        fi.html('');
    });

    $(document).ajaxSuccess(function(event, xhr, settings) {
        if ( settings.data.indexOf("action=add-tag") !== -1 ) {
            $('.th-clear-media:visible').trigger('click');
        }
    });

    // Prepare the variable that holds our custom media manager.
    var th_media_frame;

    // Bind to our click event in order to open up the new media experience.
    $(document.body).on('click.thOpenMediaManager', '.th-open-media', function(e){
        // Prevent the default action from occuring.
        e.preventDefault();

        var button = $(this);
        var fi = button.parent().find('.attachment-info');
        var id = button.attr('id').replace('-button', '');

        th_media_frame = wp.media.frames.th_media_frame = wp.media({
            className: 'media-frame th-media-frame',
            frame: 'select',
            multiple: false,
            title: button.data('title'),
            library: {
                type: button.data('filter')
            },
            button: {
                text:  button.data('button')
            }
        });

        th_media_frame.on('select', function(){
            // Grab our attachment selection and construct a JSON representation of the model.
            var media_attachment = th_media_frame.state().get('selection').first().toJSON();
            var title = th_meta_field_file_args.link_title.replace('%s', media_attachment.filename);
            var msg = '';
            if('image' == media_attachment.type) {
                if ( 'undefined' != typeof media_attachment.sizes.medium ) {
                    msg =  msg + '<div class="thumbnail"><img src="' +media_attachment.sizes.medium.url+ '" class="icon" draggable="false" /></div>';
                } else {
                    msg =  msg + '<div class="thumbnail"><img src="' +media_attachment.sizes.full.url+ '" class="icon" draggable="false" /></div>';
                }
            } else {
                msg =  msg + '<div class="thumbnail"><img src="' +media_attachment.icon+ '" class="icon" draggable="false" /></div>';
            }
            msg =  msg + '<div class="details"><div class="filename"><a href="'+media_attachment.url+'" target="_blank" title="'+title+'">'+media_attachment.filename+'</a></div>';
            msg =  msg + '<div class="uploaded">'+media_attachment.dateFormatted+'</div>';
            if('image' == media_attachment.type) {
                msg =  msg + '<div class="dimension">'+media_attachment.sizes.full.width+' x '+media_attachment.sizes.full.height+'</div>';
            }
            msg =  msg + '<div class="mimetype">'+media_attachment.mime+'</div>';
            msg =  msg + '</div>';
            fi.html(msg);

            // Send the attachment URL to our custom input field via jQuery.
            $('#'+id+'-url').val(media_attachment.url);
            $('#'+id+'-id').val(media_attachment.id);
            $('#'+id+'-clear').show();
        });

        th_media_frame.on('open',function() {
            var selection = th_media_frame.state().get('selection');
            ids = $('#'+id+'-id').val().split(',');
            ids.forEach(function(id) {
                attachment = wp.media.attachment(id);
                attachment.fetch();
                selection.add( attachment ? [ attachment ] : [] );
            });
        });

        th_media_frame.on('close', function(){
            var selection = th_media_frame.state().get('selection');
            if(!selection.length) {
                $('#'+id+'-url').val('');
                $('#'+id+'-id').val('');
                $('#'+id+'-clear').hide();
                fi.html('');
            }
        });

        // Now that everything has been set, let's open up the frame.
        th_media_frame.open();
    });
});