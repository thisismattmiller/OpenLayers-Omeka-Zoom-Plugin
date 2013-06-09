var map = null;

var open_layers_zoom_total_zooms = 0;
// keep track of how many zooms we got going on

/**
 * file_name = the base of the filename
 * width/height = w/h of the image
 * url = the url to the tiles directory
 * req = which image to display, corosponds to the open_layers_zoom_total_zooms counter
 */
function open_layers_zoom_add_zoom(file_name_base, width, height, url, req) {

    // Is this the first call to this function to add a zoom element?
    if (! open_layers_zoom_total_zooms) {
        // Yes so add the holders
        jQuery("#item-images").append(jQuery("<div>").attr("id", 'open_layers_zoom_map'));
        jQuery("#item-images").append(jQuery("<div>").attr("id", 'open_layers_zoom_map_more'));
        jQuery("#item-images").append(jQuery("<div>").attr("id", 'open_layers_zoom_map_full_window'));
    }

    // If this is not a specific request and it is the first image or it is a
    // specifc request display it.
    if ((req == -1 && open_layers_zoom_total_zooms == 0) || open_layers_zoom_total_zooms == req) {

    /* Vector layer */

    /**
     * Layer style
     */
    var vectorLayer = new OpenLayers.Layer.Vector("Simple Geometry", {
        styleMap: new OpenLayers.StyleMap({
          "default": new OpenLayers.Style({
            fillColor: "red",
            fillOpacity: 0,
            strokeColor: "red",
            strokeWidth: 0
          }),
          "highlight": new OpenLayers.Style({
            fillColor: "red",
            fillOpacity: 0.2,
            strokeWidth: 0
          })
        })
    });

    zoomify = new OpenLayers.Layer.Zoomify( "zoom", url, new OpenLayers.Size( width, height ) );

    var mapbounds =  new OpenLayers.Bounds(0, 0, width, height);

    // Full screen button, based on http://jsfiddle.net/_DR_/K2WaA/1/
    var fullscreenPanel = new OpenLayers.Control.Panel({displayClass: 'open_layers_zoom_map_full_window_panel'});

    var fullscreenControl = new OpenLayers.Control.Button({
        displayClass: 'open_layers_zoom_map_full_window_button',
        type: OpenLayers.Control.TYPE_TOGGLE,
        eventListeners: {
            'activate': function () {
              open_layers_zoom_toggle_full_window();
              map.updateSize();
              map.zoomToMaxExtent();
            },
            'deactivate': function () {
              open_layers_zoom_toggle_full_window();
              map.updateSize();
              map.zoomToMaxExtent();
            }
        }
    });
    fullscreenPanel.addControls(fullscreenControl);

    // We must list all the controls, since we want to replace the default
    // PanZoom with a PanZoomBar
    options = {
        maxExtent: mapbounds,
        restrictedExtent: mapbounds,
        maxResolution: Math.pow(2, zoomify.numberOfTiers -1),
        numZoomLevels: zoomify.numberOfTiers,
        units: "pixels",
        controls:[
        new OpenLayers.Control.Navigation(),
        new OpenLayers.Control.ArgParser(),
        new OpenLayers.Control.Attribution(),
        new OpenLayers.Control.PanZoomBar({
            "zoomWorldIcon": true
        }),
        fullscreenPanel]
    };

    map = new OpenLayers.Map("open_layers_zoom_map", options);
    map.addLayer(zoomify);
    map.addControl(new OpenLayers.Control.Permalink('permalink', null, {
    }));
    map.setBaseLayer(zoomify);

    if (!map.getCenter()) map.zoomToMaxExtent();
        // Add overview map
        // workaround based on http://osgeo-org.1803224.n2.nabble.com/zoomify-layer-WITH-overview-map-td5534360.html

        // Optional number to reduce your original pixel to fit Overview map
        // container (I used Math.floor(width/150), since my container is
        // 150 x 110)
        var ll = Math.floor(width/150);
        var a = width/ll;
        var b = height/ll;

        //New layer and new control:
        var overview = new OpenLayers.Layer.Image(
            'overview',
            url + 'TileGroup0/0-0-0.jpg',
            mapbounds,
            new OpenLayers.Size(a, b), {
                numZoomLevels: 1,
                maxExtent: mapbounds,
                restrictedExtent: mapbounds
            }
        );
        var overviewVectors = vectorLayer.clone();
        var overviewControl = new OpenLayers.Control.OverviewMap({
            // This is optional,you may use default values.
            size: new OpenLayers.Size(150, Math.floor(b)),
            autopan: false,
            maximized: true,
            layers: [overview, overviewVectors]
        });

        // At last,adding it to the map:
        map.addControl(overviewControl);
    }

    // Now add in the links.
    jQuery("#open_layers_zoom_map_more").empty();
    if (open_layers_zoom_total_zooms > 0) {
        for (x = 0; x <= open_layers_zoom_total_zooms; x++) {
            jQuery("#open_layers_zoom_map_more").append(jQuery("<a>").attr("href", '?open_zoom_layer_req=' + x).text("Load Image " + (x + 1)));
        }
    }

    open_layers_zoom_total_zooms = open_layers_zoom_total_zooms + 1;
}

function open_layers_zoom_toggle_full_window() {
    jQuery('#open_layers_zoom_map').toggleClass('open_layers_zoom_map_full_window');
}
