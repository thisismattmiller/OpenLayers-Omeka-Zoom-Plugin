<?php
/**
 * OpenLayers Zoom
 *
 * IIPImage compatible
 *
 * @see README.md
 *
 * @copyright Daniel Berthereau, 2013
 * @copyright Peter Binkley, 2012-2013
 * @copyright Matt Miller, 2012
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package OpenLayersZoom
 */

/**
 * @internal
 * Original tile paths: http://localhost/omeka/archive/zoom_tiles/4b937070760d406a5bdab0d07d2a2b87_zdata/TileGroup0/1-1-0.jpg
 * New tile paths: http://panther/cgi-bin/iipsrv.fcgi?zoomify=/var/www/iip/N018796.jp2/TileGroup0/4-4-9.jpg
 *
 * Strategy:
 * - replace ZOOMTILES_WEB to point to panther instead of local Directory
 * - change code that builds link to use original item id instead of Omeka's
 * file id
 *
 * - first test: change name of jp2 to match omeka's file id
 * /cgi-bin/iipsrv.fcgi?zoomify=/var/www/iip/4b937070760d406a5bdab0d07d2a2b87_zdata/TileGroup0/1-1-0.jpg
 * cp N018796.jp2 4b937070760d406a5bdab0d07d2a2b87_zdata
 *
 * - doesn't work: returns a file called IIPImageIsAMadGameClosedToOurUnderstanding.netpfx
 * presumably because the file doesn't have a .jp2 extension, it doesn't know
 * what to do with it.
 *
 * - so: hard-coded id in line 102:
 * open_layers_zoom_add_zoom("' . $root . '","' . $width . '","' . $height . '","' . ZOOMTILES_WEB . '/N018796.jp2/' . '",' . $open_zoom_layer_req . ');
 * it works!
 */

if (!defined('OPENLAYERSZOOM_PLUGIN_DIR')) {
    define('OPENLAYERSZOOM_PLUGIN_DIR', dirname(__FILE__));
    define('ZOOMTILES_DIR', ARCHIVE_DIR . '/zoom_tiles');
    // define('ZOOMTILES_WEB', 'http://ec2-75-101-192-109.compute-1.amazonaws.com/cgi-bin/iipsrv.fcgi?zoomify=/var/www/jp2samples');
    define('ZOOMTILES_WEB', WEB_DIR . '/archive/zoom_tiles');
    define('ZOOM_RESOURCES', WEB_PLUGIN . '/OpenLayersZoom/views/shared/');
}

include_once OPENLAYERSZOOM_PLUGIN_DIR . '/helpers/ZoomifyHelper.php';
require_once BASE_DIR . '/application/helpers/Media.php';

add_plugin_hook('install', 'open_layers_zoom_install');
// When an items is called from display_files_for_item if it is a jpeg (only file
// type this plugin supports) it will  call the zoom  applet if zoom is enabled.
add_mime_display_type(array('image/jpeg'), 'open_layers_zoom_display_items', array());
// Add the zoom options to the admin.
add_filter('admin_items_form_tabs', 'open_layers_zoom_item_form_tabs');
// Fired after the form is saved.
add_plugin_hook('after_save_form_item', 'open_layers_zoom_save_item');
// Add files and lets the script know what view we are int.
add_plugin_hook('public_theme_header', 'open_layers_zoom_public_header');
add_plugin_hook('admin_theme_header', 'open_layers_zoom_admin_header');
add_plugin_hook('uninstall', 'open_layers_zoom_uninstall');

/**
 * Installs the plugin.
 */
function open_layers_zoom_install()
{
    // Check if there is a direcroy in the archive for the zoom titles we will
    // be making.
    if (!file_exists(ZOOMTILES_DIR)) {
        mkdir(ZOOMTILES_DIR);
    }
}

/**
 * Uninstalls the plugin.
 */
function open_layers_zoom_uninstall()
{
    // Nuke the zoom tiles directory.
    open_layers_zoom_rrmdir(ZOOMTILES_DIR);
}


/**
 * Add css and js in the header of the admin theme.
 */
function open_layers_zoom_admin_header($request)
{
    define('ZOOM_ADMIN_VIEW', true);
}

/**
 * Add css and js in the header of the theme.
 */
function open_layers_zoom_public_header($request)
{
    if ($request->getControllerName() == 'items' && $request->getActionName() == 'show') {
        queue_css('OpenLayersZoom');
        queue_js(array(
            'OpenLayers',
            'OpenLayersZoom',
        ));
    }

    define('ZOOM_ADMIN_VIEW', false);
}

/**
 * Controls how the image will be returned.
 *
 * Maybe need to change this based on how non-zoomed images are to be presented.
 *
 * @param object $file the file object
 * @param array $options
 *
 * @return string
 */
function open_layers_zoom_display_items($file, array $options = array())
{
    // Is this a zoomed image?
    // Root is not used in the javascript, but only here.
    list($root, $ext) = open_layers_zoom_getRootAndExtension($file['archive_filename']);

    // Does it use a IIPImage server?
    $useIIPImageServer = open_layers_zoom_useIIPImageServer();

    // Does it have a Tile Server URL?
    $tileUrl = '';
    if ($useIIPImageServer) {
        $tileUrl = item('Item Type Metadata', 'Tile Server URL');
    }
    // Does it have zoom tiles?
    elseif (file_exists(ZOOMTILES_DIR . DIRECTORY_SEPARATOR . $root . '_zdata')) {
        // fetch identifier, to use in link to tiles for this jp2 - pbinkley
        // $jp2 = item('Dublin Core', 'Identifier') . '.jp2';
        // $tileUrl = ZOOMTILES_WEB . '/' . $jp2;
        $tileUrl = ZOOMTILES_WEB . '/' . $root . '_zdata';
    }

    // Do not show the zoomer on the admin page.
    if ($tileUrl && !ZOOM_ADMIN_VIEW) {
        // Grab the width/height of the original image.
        list($width, $height, $type, $attr) = getimagesize(FILES_DIR . '/' . $file['archive_filename']);

        // If the var is set then they are requesting a specific image to be
        // zoomed not just the first.
        // This is kind of a hack to get around some problems with OpenLayers
        // displaying multiple zoomify layers on a single page
        // It doesn't even come into play if there is just one zoomed image per
        // record.
        $open_zoom_layer_req = isset($_REQUEST['open_zoom_layer_req'])
            ? html_escape($_REQUEST['open_zoom_layer_req'])
            : '-1';

        $html = '
        <script type="text/javascript">
            open_layers_zoom_add_zoom("' . $root . '","' . $width . '","' . $height . '","' . $tileUrl . '/",' . $open_zoom_layer_req . ');
        </script>';
    }

    // Else display normal image.
    else {
        // If an options is not passed then send the thumbnail version.
        if ($options['imageSize'] == '') {
            $options['imageSize'] = 'thumbnail';
            // You might want square thumbnails
            // $options['imageSize'] = 'square_thumbnail';
        }

        // Set the alt text to the file name...
        if ($options['imgAttributes'] == '') {
            $options['imgAttributes'] = array(
                'alt' => $file->original_filename,
                'title' => $file->original_filename,
            );
        }

        // Link to the full size.
        $uri = WEB_FULLSIZE . '/' . $file->archive_filename;

        $media = new Omeka_View_Helper_Media;
        $html = $media->image($file,$options);

        // Add the link.
        $html = '<a href="' . $uri . '">' . $html . '</a>';
    }

    return $html;
}

/**
 * Fired once the record is saved, if there is a open_layers_zoom_filename#
 * passed in the $_POST along with save then we know that we need to zoom that
 * resource.
 */
function open_layers_zoom_save_item($item, $post)
{
    // Loop through and see if there are any files to zoom.
    $filesave = false;
    foreach ($_POST as $key => $value)
    {
        if (strpos($key, 'open_layers_zoom_filename') !== false) {
            open_layers_zoom_zoom_resource($value);
            $filesaved = true;
        }
        else {
            if ((strpos($key, 'open_layers_zoom_removed_hidden') !== false) and ($filesaved != true)) {
                $removeDir = $value;
                if ($removeDir != '') {
                    if (strpos($removeDir, '.') !== false) {
                        // They are thing to do something....
                    }
                    else{
                        if (file_exists(ZOOMTILES_DIR . '/' . $removeDir . '_zdata')) {
                            // Make sure there is a jpg file with this name,
                            // meaning that it really is a zoomed image dir and
                            // not deleting the root of the site :(
                            if (file_exists(FILES_DIR . '/' . $removeDir . '.jpg')
                                    || file_exists(FILES_DIR . '/' . $removeDir . '.JPG')
                                ) {
                                open_layers_zoom_rrmdir(ZOOMTILES_DIR . '/' . $removeDir . '_zdata');
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * Adds the zoom options to the jpgs attached to the record, it inserts a
 * "Zoom" tab in the admin->edit page
 */
function open_layers_zoom_item_form_tabs($tabs)
{
    $item = get_current_item();

    $useHtml = "<span>Only .jpg files attached to the record can be zoomed</span>";
    $counter = 0;
    $zoomList = '';

    foreach($item->Files as $file) {
        if (strpos(strtolower($file['archive_filename']), '.jpg') !== false) {
            // See if this image has been zoooomed yet.
            list($root, $ext) = explode(".", $file['archive_filename']);

            if (file_exists(ZOOMTILES_DIR . '/' . $root . '_zdata')) {
                // $isChecked = '<span>This Image is Zoomed</span>';
                $isChecked = '<input type="checkbox" checked="checked" name="open_layers_zoom_filename' . $counter . '" id="open_layers_zoom_filename' . $counter . '" value="' . $root . '"/>This image is Zoomed</label>';
                $isChecked .= '<input type="hidden" name="open_layers_zoom_removed_hidden' . $counter . '" id="open_layers_zoom_removed_hidden' . $counter . '" value="' . $root . '"/>';

                // $zoomList .= $root . '|';
                $title = "Click and Save Changes to make this image un zoom-able";
                $style_color = "color:green";
//                $useHtml .= '<br style="clear:both;" /><label style="width:auto;" for="removeZoom">Check this box and "Save Changes" to remove the zoom from all the images.</label>';
            }
            else {
                $isChecked = '<input type="checkbox" name="open_layers_zoom_filename' . $counter . '" id="open_layers_zoom_filename' . $counter . '" value="' . $file['archive_filename'] . '"/>Zoom This Image</label>';
                $title = "Click and Save Changes to make this image zoom-able";
                $style_color = "color:black";
            }

            $counter++;

            $useHtml .= '<div style="float:left; margin:10px;">
            <label title="' . $title . '" style="width:auto;' . $style_color . ';" for="zoomThis' . $counter . '">
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
 * Passed a file name, it will initilize the zoomify and cut the tiles
 *
 * @param filename of image
 */
function open_layers_zoom_zoom_resource($filename)
{
    $pathToFull = FILES_DIR . '/';
    $zoomifyObject = new zoomify($pathToFull);
    $zoomifyObject->zoomifyObject($filename, $pathToFull);

    list($root, $ext) = explode('.', $filename);

    // move the tiles into their directory
    if (file_exists($pathToFull . $root . '_zdata')) {
        rename( $pathToFull . $root . '_zdata', ZOOMTILES_DIR . '/' . $root . '_zdata');
    }
}

/**
 * Removes directories recursively.
 *
 * @param filename of dir
 */
function open_layers_zoom_rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . '/' . $object) == 'dir') {
                    open_layers_zoom_rrmdir($dir . '/' . $object);
                }
                else {
                    unlink($dir . '/' . $object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

/**
 * Explode a filepath in a root and an extension, i.e. "/path/file.ext" to
 * "/path/file" and "ext".
 *
 * @return array
 */
function open_layers_zoom_getRootAndExtension($filepath)
{
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    $root = $extension ? substr($filepath, 0, strrpos($filepath, '.')) : $filepath;
    return array($root, $extension);
}

/**
 * Determine if Omeka is ready to use an IIPImage server.
 *
 * @return boolean
 */
function open_layers_zoom_useIIPImageServer()
{
    static $flag = null;

    if (is_null($flag)) {
        $db = get_db();
        $sql = "
            SELECT elements.id
            FROM {$db->Elements} elements
            WHERE elements.record_type_id = ?
                AND elements.name = ?
            LIMIT 1
        ";
        $bind = array(
            2,
            'Tile Server URL'
        );
        $IIPImage = $db->fetchOne($sql, $bind);
        $flag = (boolean) $IIPImage;
    }

    return $flag;
}
