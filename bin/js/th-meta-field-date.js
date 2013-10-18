/**
 * jQuery for date field.
 *
 * @since 0.1.0
 *
 * @package TH Meta
 * @author  Thijs Huijssoon
 */
jQuery(document).ready(function($){

    $('.th-date').each(function( index ) {
        var t   = $(this),
            id  = t.attr('data-input'),
            min = t.attr('data-min'),
            max = t.attr('data-max'),
            d   = $('#' + id).val(),
            arg = {
                altField: '#' + id,
                numberOfMonths: 1,
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                dateFormat: 'yy-mm-dd',
                altFormat: 'yy-mm-dd'
            };
        if( 'undefined' !== typeof min ) {
            arg['onSelect'] = function( selectedDate ) {
                $( '#' + min ).datepicker( 'option', 'minDate', selectedDate );
            };
        }
        if( 'undefined' !== typeof max ) {
            arg['onSelect'] = function( selectedDate ) {
                $( '#' + max ).datepicker( 'option', 'maxDate', selectedDate );
            };
        }
        t.datepicker( arg );
        t.datepicker('setDate', d);
    });

    $('.th-date').each(function( index ) {
        var t   = $(this),
            id  = t.attr('data-input'),
            min = t.attr('data-min'),
            max = t.attr('data-max'),
            d   = $('#' + id).val();
        if( 'undefined' !== typeof min ) {
            $( '#' + min ).datepicker( 'option', 'minDate', d );
        }
        if( 'undefined' !== typeof max ) {
            $( '#' + max ).datepicker( 'option', 'maxDate', d );
        }
    });

});