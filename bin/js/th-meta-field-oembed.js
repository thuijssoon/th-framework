/**
 * Controls the behaviours of custom metabox fields.
 *
 * @author Andrew Norcross
 * @author Jared Atchison
 * @author Bill Erickson
 * @author Justin Sternberg
 * @see    https://github.com/jaredatch/Custom-Metaboxes-and-Fields-for-WordPress
 */

/*jslint browser: true, devel: true, indent: 4, maxerr: 50, sub: true */
/*global jQuery, tb_show, tb_remove */

/**
 * Custom jQuery for Custom Metaboxes and Fields
 */
jQuery(document).ready(function ($) {
	'use strict';

	var formfield;

	/**
	 * Ajax oEmbed display
	 */

	// ajax on paste
	$('.th-oembed').bind('paste', function (e) {
		var pasteitem = $(this);
		// paste event is fired before the value is filled, so wait a bit
		setTimeout(function () {
			// fire our ajax function
			doCMBajax(pasteitem, 'paste');
		}, 100);
	}).blur(function () {
		// when leaving the input
		setTimeout(function () {
			// if it's been 2 seconds, hide our spinner
			$('.postbox table.th-meta .th-spinner').hide();
		}, 2000);
	});

	// ajax when typing
	$('.th_oembed_cell').on('keyup', '.th-oembed', function (event) {
		// fire our ajax function
		doCMBajax($(this), event);
	});

	$('.th_remove_file_button').live('click', function () {
		formfield = $(this).attr('rel');
		$('input#' + formfield).val('');
		$('input#' + formfield + '_id').val('');
		$(this).parent().parent().remove();
		return false;
	});

    $(document).ajaxSuccess(function(event, xhr, settings) {
        if ( settings.data.indexOf("action=add-tag") !== -1 ) {
            $('.th_remove_file_button:visible').trigger('click');
        }
    });

	// function for running our ajax
	function doCMBajax(obj, e) {
		// get typed value
		var oembed_url = obj.val();

		// get our inputs context for pinpointing
		var context = obj.parents('.th_oembed_cell');

		// only proceed if the field contains more than 6 characters
		if (oembed_url.length === 0)
			$('.th-oembed-container', context).html('');

		// only proceed if the field contains more than 6 characters
		if (oembed_url.length < 6)
			return;

		// only proceed if the user has pasted, pressed a number, letter, or whitelisted characters
		if (e === 'paste' || e.which <= 90 && e.which >= 48 || e.which >= 96 && e.which <= 111 || e.which == 8 || e.which == 9 || e.which == 187 || e.which == 190) {

			// get field id
			var field_id = obj.attr('id');
			// show our spinner
			$('.th-spinner', context).show();
			// clear out previous results
			$('.th-oembed-container', context).html('');
			// and run our ajax function
			setTimeout(function () {
				// if they haven't typed in 500 ms
				if ($('.th-oembed:focus').val() == oembed_url) {
					$.ajax({
						type : 'post',
						dataType : 'json',
						url : window.ajaxurl,
						data : {
							'action': 'th_oembed_handler',
							'oembed_url': oembed_url,
							'field_id': field_id,
							'post_id': window.th_ajax_data.post_id,
							'th_ajax_nonce': window.th_ajax_data.ajax_nonce
						},
						success: function (response) {
							// if we have a response id
							if (typeof response.id !== 'undefined') {
								// hide our spinner
								$('.th-spinner', context).hide();
								// and populate our results from ajax response
								$('.th-oembed-container', context).html(response.result);
							}
						}
					});
				}
			}, 500);
		}
	}

});