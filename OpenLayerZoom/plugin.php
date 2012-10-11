<?php
/**
IIPImage version

Original tile paths: http://localhost/omeka/archive/zoom_tiles/4b937070760d406a5bdab0d07d2a2b87_zdata/TileGroup0/1-1-0.jpg
New tile paths: http://panther/cgi-bin/iipsrv.fcgi?zoomify=/var/www/iip/N018796.jp2/TileGroup0/4-4-9.jpg

Strategy:
 - replace ZOOMTILES_WEB to point to panther instead of local Directory
 - change code that builds link to use original item id instead of Omeka's file id

- first test: change name of jp2 to match omeka's file id
https://pbinkley.dyndns.org/cgi-bin/iipsrv.fcgi?zoomify=/var/www/iip/4b937070760d406a5bdab0d07d2a2b87_zdata/TileGroup0/1-1-0.jpg

cp N018796.jp2 4b937070760d406a5bdab0d07d2a2b87_zdata 

- doesn't work: returns a file called IIPImageIsAMadGameClosedToOurUnderstanding.netpfx - presumably because the file doesn't
have a .jp2 extension, it doesn't know what to do with it.

- so: hard-coded id in line 102: 

open_layer_zoom_add_zoom("'.$root.'","'.$width.'","'.$height.'","'.ZOOMTILES_WEB.'/N018796.jp2/'.'",'.$open_zoom_layer_req.');
**/

if (!defined('OPENLAYERZOOM_PLUGIN_DIR')) {
    define('OPENLAYERZOOM_PLUGIN_DIR', dirname(__FILE__));
	define('ZOOMTILES_DIR', ARCHIVE_DIR . '/zoom_tiles');
	define('ZOOMTILES_WEB', 'https://pbinkley.dyndns.org:59443/cgi-bin/iipsrv.fcgi?zoomify=/var/www/iip');	
	define('ZOOM_RESOURCES', WEB_PLUGIN . '/OpenLayerZoom/views/shared/');
}

include_once OPENLAYERZOOM_PLUGIN_DIR . "/helpers/ZoomifyHelper.php";
require_once dirname(__FILE__) . '/../../application/helpers/Media.php';
 


add_plugin_hook('install', 'open_layer_zoom_install');
add_mime_display_type(array('image/jpeg'), 'open_layer_zoom_display_items', array());	//when an items is called from display_files_for_item if it is a jpeg (only file type this plugin supports) it will  call the zoom  applet if zoom is enabled
add_filter('admin_items_form_tabs', 'open_layer_zoom_item_form_tabs');					//add the zoom options to the admin 
add_plugin_hook('after_save_form_item', 'open_layer_zoom_save_item');					//fired after the form is saved,
add_plugin_hook('public_theme_header', 'open_layer_zoom_public_header');				//add files and lets the script know what view we are int
add_plugin_hook('admin_theme_header', 'open_layer_zoom_admin_header');
add_plugin_hook('uninstall', 'open_layer_zoom_uninstall');


function open_layer_zoom_install(){
	//check if there is a direcroy in the archive for the zoom titles we will be making
	if (!file_exists(ZOOMTILES_DIR)){
		mkdir(ZOOMTILES_DIR);
	}
}



function open_layer_zoom_uninstall(){	
	//nuke the zoom tiles directory
	rrmdir(ZOOMTILES_DIR);
}



/**
 * plops the open layer js into the headers
 **/
function open_layer_zoom_public_header(){	
	echo '<script src="' . ZOOM_RESOURCES . 'common/OpenLayers.js"></script>';		
	echo '<script src="' . ZOOM_RESOURCES . 'common/OpenLayerZoom.js"></script>';			
	echo '<link rel="stylesheet" href="'.ZOOM_RESOURCES.'common/theme/default/style.css" />';
	
	define('ZOOM_ADMIN_VIEW', false);
}

function open_layer_zoom_admin_header(){
	define('ZOOM_ADMIN_VIEW', true);	
}


 
/**
 * Controls how the image will be returned, maybe need to change this based on how non-zoomed images are to be presented
 * @param topject he file object
 * @param array $options 
 * @return string
 **/
function open_layer_zoom_display_items($file, array $options=array()){
 

	//is this a zoomed image?
	list($root, $ext) = explode(".",$file['archive_filename']);
	
	if (file_exists(ZOOMTILES_DIR.'/'.$root.'_zdata') && ZOOM_ADMIN_VIEW==false){	//do not show the zoomer on the admin page
	
		//yess
 	
		//grab the width/height
  	    list($width, $height, $type, $attr) = getimagesize(FILES_DIR . '/' . $file['archive_filename']);		
 
 		//if the var is set then they are requesting a specifc image to be zoomed not just the first
		//this is kind of a hack to get around some problems with openlayers displaying multiple zoomify layers on a single page
		//it doesn't even come into play if there is just one zoomed image per record.
		if (isset($_REQUEST['open_zoom_layer_req'])){$open_zoom_layer_req = html_escape($_REQUEST['open_zoom_layer_req']);}else{$open_zoom_layer_req = "-1";}
 		
		$html = '	
		
			<script type="text/javascript">
				open_layer_zoom_add_zoom("'.$root.'","'.$width.'","'.$height.'","'.ZOOMTILES_WEB.'/N018796.jp2/'.'",'.$open_zoom_layer_req.');
			</script>
			
		';
				 
		
	}else{
		
		//nope
		//if an options is not passed then send the thumbnail version	
		if ($options['imageSize']==''){$options['imageSize']='thumbnail';}
		//if ($options['imageSize']==''){$options['imageSize']='square_thumbnail';}		//you might want square thumbnails
		 
		//set the alt text to the file name...
		if ($options['imgAttributes']==''){$options['imgAttributes']=array('alt'=> $file->original_filename, 'title'=>$file->original_filename);}
	
		
		//link to the full size
		$uri = WEB_FULLSIZE . "/" . $file->archive_filename;	  	
		
		$media = new Omeka_View_Helper_Media;
		$html = $media->image($file,$options);
		
		//add the link
		$html = '<a href="'.$uri.'">'.$html.'</a>';
	  
	}	


	
	return $html;

	
}

 
/**
 * Fired once the record is saved, if there is a open_layer_zoom_filename# passed  in the $_POST along with save then we know that we need to zoom that resource
 **/
function open_layer_zoom_save_item($item, $post)
{
	
	
	//loop through and see if there are any files to zoom   
	$filesave=false;
	foreach ($_POST as $key => $value)
	{	
	
		if (strpos($key,"open_layer_zoom_filename")!==false){
			open_layer_zoom_zoom_resource($value);
 			$filesaved=true;
		}
		else {
		if ((strpos($key,"open_layer_zoom_removed_hidden")!==false) and ($filesaved!=true)){
			$removeDir=$value;
 			if ($removeDir!=''){			
				if (strpos($removeDir,'.')!==false){					
					//they are tring to so somrthing....					
				}else{				
					if (file_exists(ZOOMTILES_DIR.'/'.$removeDir.'_zdata')){						
						//make sure there is a jpg file with this name, meaning that it really is a zoomed image dir and not deleteing the root of the site :(
						if (file_exists(FILES_DIR.'/'.$removeDir.'.jpg') || file_exists(FILES_DIR.'/'.$removeDir.'.JPG')){						
							rrmdir(ZOOMTILES_DIR.'/'.$removeDir.'_zdata');						
						}						
					}					
				}
			}
		}
		}		
	}
 

	
}



 
/**
 * Adds the zoom options to the jpges attached to the record, it inserts a "Zoom" tab in the admin->edit page
 **/
function open_layer_zoom_item_form_tabs($tabs)
{
	
	
    $item = get_current_item();
		
	$useHtml= "<span>Only .jpg files attached to the record can be zoom'ed</span>";
	$counter=0;
	$zoomList='';

	foreach($item->Files as $file) {
		
		
		if (strpos(strtolower($file['archive_filename']),'.jpg')!== false){
			
			//see if this image has been zoooomed yet
			
			list($root, $ext) = explode(".",$file['archive_filename']);
			 
			
			if (file_exists(ZOOMTILES_DIR.'/'.$root.'_zdata')){
				
				//$isChecked='<span>This Image is Zoomed</span>';
				$isChecked='<input type="checkbox" checked="checked" name="open_layer_zoom_filename'. $counter .'"  id="open_layer_zoom_filename'. $counter .'" value="'.$root.'"/>This image is Zoomed</label>';
				$isChecked.='<input type="hidden" name="open_layer_zoom_removed_hidden'. $counter .'"  id="open_layer_zoom_removed_hidden'. $counter .'" value="'.$root.'"/>';
				
			//	$zoomList.=$root.'|';
					$title = "Click and Save Changes to make this image un zoom-able";
					$style_color="color:green";
					
//					$useHtml .=  '<br style="clear:both;" /><label style="width:auto;" for="removeZoom">Check this box and "Save Changes" to remove the zoom from all the images.</label>';
				
			}else{
				
				$isChecked='<input type="checkbox"  name="open_layer_zoom_filename'. $counter .'"  id="open_layer_zoom_filename'. $counter .'" value="'.$file['archive_filename'].'"/>Zoom This Image</label>';
				$title = "Click and Save Changes to make this image zoom-able";
				$style_color="color:black";
			}

			$counter++;
			
		
			$useHtml .= '<div style="float:left; margin:10px;">
			<label title="'.$title.'" style="width:auto;'.$style_color.';" for="zoomThis'. $counter .'">
			<img src="' . WEB_THUMBNAILS . '/' . $file['archive_filename'] . '" /><br />' . $isChecked . '<br /></div>';			
				
		}
		
	}
		 

    $ttabs = array();
    foreach($tabs as $key => $html) {
        if ($key == 'Miscellaneous') {
            $ttabs['Zoom'] = $useHtml;
         }
        $ttabs[$key] = $html;
    }
    $tabs = $ttabs;
    return $tabs;	
 
}



/**
 * passed a file name it will initilize the zoomify and cut the tiles
 * @param filename of image
 **/
function open_layer_zoom_zoom_resource($filename){

	$pathToFull = FILES_DIR . '/';	  
	$zoomifyObject = new zoomify($pathToFull);	
	$zoomifyObject->zoomifyObject($filename,$pathToFull);	
	
	list($root, $ext) = explode(".",$filename);

	//move the tiles into their directory
	if(file_exists($pathToFull . $root . "_zdata")){	
		rename(	$pathToFull . $root . "_zdata", ZOOMTILES_DIR . "/" . $root . "_zdata");		
	}

}


/**
 * removes directories recursivly
 * @param filename of dir
 **/
function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
       } 
     } 
     reset($objects); 
     rmdir($dir); 
   } 
} 




?>