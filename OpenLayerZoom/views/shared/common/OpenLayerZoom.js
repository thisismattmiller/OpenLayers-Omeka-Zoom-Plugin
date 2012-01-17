


	var open_layer_zoom_total_zooms=0;	//keep track of how many zooms we got going on
	
	/**
	file_name = the base of the filename 
	width/height = w/h of the image
	url = the url to the tiles directory
	req = which image to display, corosponds to the open_layer_zoom_total_zooms counter
	**/
	function open_layer_zoom_add_zoom(file_name_base,width,height,url,req){
		
		
		
		//is this the first call to this function to add a zoom element?
		if (!open_layer_zoom_total_zooms){			
			
			//yes so add the holders
			jQuery("#itemfiles").append(jQuery("<div>").attr("id",'open_layer_zoom_map'));
			jQuery("#itemfiles").append(jQuery("<div>").attr("id",'open_layer_zoom_map_more'));			
 				 
		}
		
		//if this is not a specific request and it is the first image or it is a specifc request display it
		if ((req==-1&&open_layer_zoom_total_zooms==0) || open_layer_zoom_total_zooms==req){
			
			zoomify = new OpenLayers.Layer.Zoomify( "zoom", url, new OpenLayers.Size( width, height ) ); 		
	
			options = {
				maxExtent: new OpenLayers.Bounds(0, 0, width, height),
				maxResolution: Math.pow(2, zoomify.numberOfTiers-1 ),
				numZoomLevels: zoomify.numberOfTiers,
				units: "pixels"
			};		
			 
			map = new OpenLayers.Map("open_layer_zoom_map", options);
			map.addLayer(zoomify);
			map.addControl(new OpenLayers.Control.PanZoomBar());
			map.setBaseLayer(zoomify);
			map.zoomToMaxExtent();				
			
  
		}
		
		//now add in the links
		jQuery("#open_layer_zoom_map_more").empty();		
		if (open_layer_zoom_total_zooms > 0){		
			for (x=0; x <= open_layer_zoom_total_zooms; x++){
				jQuery("#open_layer_zoom_map_more").append(jQuery("<a>").attr("href",'?open_zoom_layer_req=' + x).text("Load Image " + (x+1)));	
			}			
		}
  
		
		open_layer_zoom_total_zooms=open_layer_zoom_total_zooms+1;
		 
	}