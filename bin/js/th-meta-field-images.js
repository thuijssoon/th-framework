/*jslint browser: true, devel: true, indent: 4, unparam: true */
/*global jQuery, wp */

(function ($, window, document, undefined) {
    'use strict';

    var esl_frame,
        max_images = null;

    function center_image_in_preview($img) {

        $img.one('load', function () {

            var $image,
                $preview,
                iWidth,
                iHeight,
                pWidth,
                pHeight,
                scaleW,
                scaleH,
                scale,
                rWidth,
                rHeight;

            $image = $(this);
            $preview = $image.parents('.esl-image-preview');
            iWidth = $image.width();
            iHeight = $image.height();
            pWidth = $preview.width();
            pHeight = $preview.height();
            scaleW = iWidth / pWidth;
            scaleH = iHeight / pHeight;
            scale = Math.max(scaleW, scaleH);
            scale = Math.max(scale, 1);
            rWidth = iWidth / scale;
            rHeight = iHeight / scale;

            $image.css({
                'marginLeft': rWidth / -2 + 'px',
                'marginTop': rHeight / -2 + 'px'
            });
            $image.fadeIn();

        }).each(function () {
            if (this.complete) {
                $(this).load();
            }
        });

    }

    function maybe_add_new($container) {
        var $parent = $container.parent(),
            $clone,
            total = $parent.find('li').length;

        if (null === max_images) {
            max_images = $parent.data('esl-max-images');
        }

        if (max_images > 0 && total >= max_images) {
            return;
        }

        if (0 === $parent.find('li.esl-no-image').length) {
            $clone = $parent.find('li:first').clone();
            $clone.find('.esl-image-preview').html('');
            $clone.find('input').val('');
            $clone.removeClass('esl-has-image').addClass('esl-no-image');
            $clone.appendTo($container.parent());
        }
    }

    function remove_image($container) {
        $container.fadeOut(400, function () {
            $(this).remove();
        });
    }

    function open_manager(element) {

        var $container = $(element).parents('.esl-additional-images-picker'),
            $preview = $container.find('.esl-image-preview'),
            $input = $container.find('.esl-image-input'),
            select = $container.data('esl-image-select'),
            title = $container.data('esl-image-title');

        // Set up the media picker frame
        esl_frame = wp.media({
            // Open the media picker in select mode only
            frame: 'select',

            // Only allow a single image to be chosen
            multiple: false,

            // Set the popup title from the HTML markup we output for the active picker
            title: title,

            // Only allow the user to choose form images
            library: {
                type: 'image'
            },

            button: {
                // Set the button text from the HTML markup we output for the active picker
                text: select
            }
        });

        esl_frame.on('select', function () {
            var media_attachment = esl_frame.state().get('selection').first().toJSON(),
                size,
                $img;

            // Add the image to the preview container
            if (undefined !== media_attachment.sizes.medium) {
                size = media_attachment.sizes.medium;
            } else {
                size = media_attachment.sizes.full;
            }

            $img = $('<img />').attr('src', size.url).attr('alt', media_attachment.title);
            $preview.html('').append($img);

            center_image_in_preview($img);

            $input.val(media_attachment.id);
            $container.removeClass('esl-no-image').addClass('esl-has-image');

            maybe_add_new($container);

        });

        esl_frame.on('open', function () {
            var selection = esl_frame.state().get('selection'),
                attachment,
                ids = $input.val().split(',');
            ids.forEach(function (id) {
                attachment = wp.media.attachment(id);
                attachment.fetch();
                selection.add(attachment ? [attachment] : []);
            });
        });

        esl_frame.on('close', function () {
            var selection = esl_frame.state().get('selection');
            if (!selection.length) {
                $preview.html('');
                $input.val('');
                $container.removeClass('esl-has-image').addClass('esl-no-image');
                if ($container.parent().find('li.esl-no-image').length > 1) {
                    $container.remove();
                }
            }
            maybe_add_new($container);
        });

        esl_frame.open();

    }

    $(document.body).on('click.esl-additional-images', '.esl-image-remove', function (event) {

        event.preventDefault();

        var $remove = $(this),
            $container = $remove.parents('.esl-additional-images-picker'),
            $preview = $remove.parent().siblings('.esl-image-preview'),
            $input = $container.find('.esl-image-input');

        if ($container.parent().find('li.esl-no-image').length > 0) {
            remove_image($container);
        } else {
            $container.addClass('esl-no-image').removeClass('esl-has-image');
            $input.val('');

            // Remove the preview thumbnail because it is no longer valid
            $preview.empty();
        }

    });

    $(document.body).on('click.esl-additional-images', '.esl-image-add', function (event) {

        event.preventDefault();

        open_manager(this);

    });

    $(document.body).on('click.esl-additional-images', '.esl-image-preview', function (event) {

        event.preventDefault();

        open_manager(this);

    });

    $(function () {

        var $inputEl = [];

        $(".esl-additional-images-picker img").each(function () {
            center_image_in_preview($(this));
        });

        $(".esl-additional-images-container").sortable({
            items: "li:not(.esl-no-image)",
            placeholder: "sortable-placeholder",
            handle: ".esl-image-drag-handle"
        });

        // Event handlers for metatbox open and moving
        // to force recalculation of image positions.
        if ($.esl_add_postbox_show_callback) {
            $.esl_add_postbox_show_callback(function (box) {
                $(".esl-additional-images-picker img").each(function () {
                    center_image_in_preview($(this));
                });
            });
        }

        $('#post, #edittag').submit(function () {

            $('.esl-additional-images-picker.esl-no-image input').attr('disabled', 'disabled');

        });

        $('#addtag #submit').mousedown( function() {
            $('.esl-additional-images-picker.esl-no-image input').attr('disabled', 'disabled');
        });

        $(document).ajaxSend(function (event, jqXHR, ajaxOptions) {
            if (ajaxOptions.data.indexOf('meta-box-order') !== -1) {
                $(".esl-additional-images-picker img").each(function () {
                    center_image_in_preview($(this));
                });
            }
        });

        $(document).ajaxSuccess(function (event, xhr, settings) {
            if (settings.data.indexOf("action=add-tag") !== -1) {
                $(".esl-additional-images-container").each(function (index) {
                    var $this = $(this),
                        $empty_image = $this.find('.esl-no-image');
                    if (0 === $empty_image.length) {
                        $clone = $parent.find('li:first').clone();
                        $clone.find('.esl-image-preview').html('');
                        $clone.find('input').val('');
                        $clone.removeClass('esl-has-image').addClass('esl-no-image');
                        $clone.appendTo($container.parent());
                    }
                    $this.find('.esl-has-image').remove();
                });
                $('.esl-additional-images-picker.esl-no-image input').removeAttr('disabled');
            }
        });


    });

}(jQuery, window, document));