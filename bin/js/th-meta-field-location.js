/*!
 * jQuery lightweight plugin boilerplate
 * Original author: @ajpiano
 * Further changes, comments: @addyosmani
 * Licensed under the MIT license
 */

;(function ( $, window, document, undefined ) {

    // Create the defaults once
    var pluginName = "thMetaFieldLocation",
        defaults = {
            'label' : 'Drag me to change the address'
        };

    // The actual plugin constructor
    function Plugin( element, options ) {
        this.element  = element;
        this.$element = $(element);

        var metadata = this.$element.data();

        this.options = $.extend( {}, defaults, options, metadata);

        this._defaults = defaults;
        this._name = pluginName;

        this.init();
    }

    Plugin.prototype = {

        /**
         * [ description]
         * @return {[type]} [description]
         */
        init: function() {
            this.cell      = $(this.element).parents('.th_location_cell');
            this.lat       = this.cell.find('.th-lat');
            this.lng       = this.cell.find('.th-lng');
            this.zoom      = this.cell.find('.th-zoom');
            this.column    = this.cell.find('.th-column');
            this.centerlat = this.cell.find('.th-center-lat');
            this.centerlng = this.cell.find('.th-center-lng');
            this.nelat     = this.cell.find('.th-bounds-ne-lat');
            this.nelng     = this.cell.find('.th-bounds-ne-lng');
            this.swlat     = this.cell.find('.th-bounds-sw-lat');
            this.swlng     = this.cell.find('.th-bounds-sw-lng');
            this.map       = null;
            this.fields    = null;
            this.marker    = null;
            var self       = this,
                fields     = [];

            if(this.options.fields) {
                $.each(this.options.fields, function(k,v){
                    fields[k] = $('#'+v);
                    fields[k].attr('previous_value',fields[k].val());
                    fields[k].on("blur", {self:self}, self.updatemap);
                });
                this.fields = fields;
            }

            if( '' !== this.lat.val() && '' !== this.lng.val() ) {
                this.initmap();
            }
        },

        /**
         * [ description]
         * @return {[type]} [description]
         */
        initmap: function() {
            var mapCenterPosition = new google.maps.LatLng(
                parseFloat( this.centerlat.val() ),
                parseFloat( this.centerlng.val() )
            ),
            pinPosition = new google.maps.LatLng(
                parseFloat( this.lat.val() ),
                parseFloat( this.lng.val() )
            ),
            mapOptions = {
                zoom: parseFloat( this.zoom.val() ),
                center: mapCenterPosition,
                scrollwheel: false,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            },
            ne,
            sw,
            bounds;
            this.map = new google.maps.Map(this.element, mapOptions);
            var requestMarker = new google.maps.Marker({
                map: this.map,
                position: pinPosition,
                title: this.options.label,
                draggable: true,
                zIndex: Infinity
            });
            // if(
            //     '' !== this.nelat.val() &&
            //     '' !== this.nelng.val() &&
            //     '' !== this.swlat.val() &&
            //     '' !== this.swlng.val()
            // ) {
            //     ne = new google.maps.LatLng(this.nelat.val(), this.nelng.val());
            //     sw = new google.maps.LatLng(this.swlat.val(), this.swlng.val());
            //     this.map.setCenter(sw);
            //     this.map.setZoom(parseFloat(this.nelat.val()));
            // }
            var self     = this;
            this.marker = requestMarker;

            //Reverse geocode
            // google.maps.event.addListener(requestMarker, "dragstart", function(mouseEvt){
            //     var mapelement = requestMarker.map.getDiv();
            //     var plugin     = $.data(mapelement, "plugin_" + pluginName);
            // });

            google.maps.event.addListener(requestMarker, "dragend", function(mouseEvt){
                var geocoder   = new google.maps.Geocoder(),
                    request    = { location: mouseEvt.latLng };
                geocoder.geocode(request, function(results, status){
                    self.updatefields(results, status);
                });
            });

            google.maps.event.addListener(this.map, "idle", function(event){
                self.centerlat.val( self.map.getCenter().lat() );
                self.centerlng.val( self.map.getCenter().lng() );
                self.zoom.val( self.map.getZoom() );

                var bounds = self.map.getBounds();
                self.nelat.val( bounds.getNorthEast().lat() );
                self.nelng.val( bounds.getNorthEast().lng() );
                self.swlat.val( bounds.getSouthWest().lat() );
                self.swlng.val( bounds.getSouthWest().lng() );
            });
        },

        /**
         * [ description]
         * @param  {[type]} results [description]
         * @param  {[type]} status  [description]
         * @return {[type]}         [description]
         */
        updatefields: function(results, status){
            if (status == google.maps.GeocoderStatus.OK) {
                address = results[0].address_components;
                number  = '';
                route   = '';
                if (this.fields) {
                    // Empty the fields
                    for(var index in this.fields) {
                        this.fields[index].val('');
                    }
                    // Loop through the data and populate the fields
                    for (var i = 0; i <= address.length - 1; i++) {
                        if (this.fields[address[i].types[0]]) {
                            this.fields[address[i].types[0]].val(address[i].long_name);
                        }
                        if('street_number' === address[i].types[0]) {
                            number = address[i].long_name;
                        }
                        if('route' === address[i].types[0]) {
                            route = address[i].long_name;
                        }
                    }
                    // Fill out the street_address field if not yet done
                    // use the number and route fields
                    if(
                        this.fields['street_address'] &&
                        '' === this.fields['street_address'].val() &&
                        ( '' !== number || '' !== route )
                    ) {
                        this.fields['street_address'].val($.trim(number+' '+route));
                    }

                    if(this.fields['formatted_address']) {
                        this.fields['formatted_address'].val(results[0].formatted_address);
                    }
                }
                this.lat.val(results[0].geometry.location.lat());
                this.lng.val(results[0].geometry.location.lng());
                this.updatecolumn();
            } else {
                alert("Code:" + status);
                this.lat.val('');
                this.lng.val('');
                resultMarker.setMap(null);
            }
        },

        /**
         * [ description]
         * @param  {[type]} event [description]
         * @return {[type]}       [description]
         */
        updatemap: function(event){
            var address  = '',
                element  = $(this),
                self     = event.data.self,
                geocoder = new google.maps.Geocoder();

            if(element.attr('previous_value') === element.val()) {
                return;
            }

            // Build the address
            for(var index in self.fields) {
                var part = $.trim(self.fields[index].val());
                if('' !== part) {
                    if('' !== address ) {
                        part = ', ' + part;
                    }
                    address += part;
                }
            }

            self.updatecolumn();

            geocoder.geocode( { 'address': address}, function(results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    self.map.setCenter(results[0].geometry.location);
                    self.map.fitBounds(results[0].geometry.viewport);
                    self.marker.setPosition(results[0].geometry.location);
                    self.marker.setMap(self.map);
                    self.lat.val(results[0].geometry.location.lat());
                    self.lng.val(results[0].geometry.location.lng());
                } else {
                    self.marker.setMap(null);
                    self.lat.val('');
                    self.lng.val('');
                }
            });
        },

        updatecolumn: function() {
            var column_value = '';
            // Build the address
            for(var index in this.fields) {
                var part = $.trim(this.fields[index].val());
                if('' !== part) {
                    if('' !== column_value ) {
                        part = ', ' + part;
                    }
                    column_value += part;
                }
            }
            this.column.val(column_value);
        }
    };

    // A really lightweight plugin wrapper around the constructor,
    // preventing against multiple instantiations
    $.fn[pluginName] = function ( options ) {
        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" + pluginName,
                new Plugin( this, options ));
            }
        });
    };

    // Init the plugin on ready
    $(function() {
        $('.th-map-camvas').thMetaFieldLocation();
    });

})( jQuery, window, document );