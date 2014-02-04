jQuery(function(){
	var error_msg = jQuery("#setting-error p[class='setting-error-message']");
	// look for admin messages with the "setting-error-message" error class
	if (error_msg.length != 0) {
		error_msg.each(function(index) {
			// get the title
			var error_setting = jQuery(this).attr('title');
			
			// look for the label with the "for" attribute=setting title and give it an "error" class (style this in the css file!)
			jQuery("label[for='" + error_setting + "']").css('color', '#CC0000').css('font-weight', 'bold');
			
			// look for the input with id=setting title and add a red border to it.
			jQuery("input[id='" + error_setting + "']").css('background-color', '#FFEBE8').css('border-color', '#CC0000');
		});
	}
	
	 function urlParam(name, url){
	    var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(url);
	    if (!results) { 
	        return 0; 
	    }
	    return results[1] || 0;
	}
	
    // var tabs = jQuery('h2.nav-tab-wrapper a.nav-tab');
    // var tabcontainers = jQuery('div.tab-container');
    // var use_ajax = jQuery('<input type="hidden" value="true" name="' + th_settings.option_key + '[using_ajax]">');
    // jQuery('form').append( use_ajax );

    //    jQuery(tabs).click(function () {
    //    	id = '#' + urlParam( 'tab', this.href ) + '-container';
    //        // hide all tabs
    //        jQuery(tabcontainers).removeClass('tab-container-active').hide();
    //        jQuery(tabcontainers).filter( jQuery( id ) ).fadeIn();

    //        // set up the selected class
    //        jQuery(tabs).removeClass('nav-tab-active');
    //        jQuery(this).addClass('nav-tab-active');

    //        this.blur();
    //        return false;
    //    });
});