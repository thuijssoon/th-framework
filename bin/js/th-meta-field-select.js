/**
 * jQuery media file for select field.
 *
 * @since 0.1.0
 *
 * @package TH Meta
 * @author  Thijs Huijssoon
 */
jQuery(document).ready(function($){

    $('.th-select2').select2({width: 'element', allowClear: true, placeholder: "Please select a value"});
    $('.th-select2-sortable').select2({width: 'element'}).select2Sortable({bindOrder: 'sortableStop'});

});