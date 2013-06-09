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

if (!defined('ZOOMTILES_DIR')) {
    define('ZOOMTILES_DIR', ARCHIVE_DIR . '/zoom_tiles');
    // define('ZOOMTILES_WEB', 'http://ec2-75-101-192-109.compute-1.amazonaws.com/cgi-bin/iipsrv.fcgi?zoomify=/var/www/jp2samples');
    define('ZOOMTILES_WEB', WEB_DIR . '/archive/zoom_tiles');
}

/**
 * Contains code used to integrate the plugin into Omeka.
 *
 * @package OpenLayersZoom
 */
class OpenLayersZoomPlugin extends Omeka_Plugin_Abstract
{
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'admin_theme_header',
        'public_theme_header',
        'after_save_item',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_items_form_tabs',
    );

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        // Check if there is a direcroy in the archive for the zoom titles we
        // will be making.
        if (!file_exists(ZOOMTILES_DIR)) {
            mkdir(ZOOMTILES_DIR);
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function hookUninstall()
    {
        // Nuke the zoom tiles directory.
        $this->_rrmdir(ZOOMTILES_DIR);
    }


    /**
     * Add css and js in the header of the admin theme.
     */
    public function hookAdminThemeHeader($request)
    {
        define('ZOOM_ADMIN_VIEW', true);
    }

    /**
     * Add css and js in the header of the public theme.
     */
    public function hookPublicThemeHeader($request)
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
     * @todo Need to change this based on how non-zoomed images are to be
     * presented.
     *
     * @param object $file the file object
     * @param array $options
     *
     * @return string
     */
    public function display_file($file, array $options = array())
    {
        // Is this a zoomed image?
        // Root is not used in the javascript, but only here.
        list($root, $ext) = $this->_getRootAndExtension($file->archive_filename);

        // Is it a zoomified file?
        $tileUrl = '';
        // Does it use a IIPImage server?
        if ($this->_useIIPImageServer()) {
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
            list($width, $height, $type, $attr) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . $file->archive_filename);

            // If the var is set then they are requesting a specific image to be
            // zoomed not just the first.
            // This is kind of a hack to get around some problems with OpenLayers
            // displaying multiple zoomify layers on a single page
            // It doesn't even come into play if there is just one zoomed image
            // per record.
            $open_zoom_layer_req = isset($_REQUEST['open_zoom_layer_req'])
                ? html_escape($_REQUEST['open_zoom_layer_req'])
                : '-1';

            $html = '<script type="text/javascript">
                open_layers_zoom_add_zoom("' . $root . '","' . $width . '","' . $height . '","' . $tileUrl . '/",' . $open_zoom_layer_req . ');
            </script>';
        }

        // Else display normal file.
        else {
            $html = display_file($file, $options);
        }

        return $html;
    }

    /**
     * Fired once the record is saved, if there is a `open_layers_zoom_filename`
     * passed in the $_POST along with save then we know that we need to zoom
     * resource.
     */
    public function hookAfterSaveItem($item)
    {
        // Loop through and see if there are any files to zoom.
        $filesave = false;
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'open_layers_zoom_filename') !== false) {
                $this->_zoom_resource($value);
                $filesaved = true;
            }
            else {
                if ((strpos($key, 'open_layers_zoom_removed_hidden') !== false) and ($filesaved != true)) {
                    $removeDir = $value;
                    if ($removeDir != '') {
                        if (strpos($removeDir, '.') !== false) {
                            // They are something to do...
                        }
                        else{
                            if (file_exists(ZOOMTILES_DIR . DIRECTORY_SEPARATOR . $removeDir . '_zdata')) {
                                // Make sure there is an image file with this name,
                                // meaning that it really is a zoomed image dir and
                                // not deleting the root of the site :(
                                // We check a derivative, because the original
                                // image is not always a jpg one.
                                if (file_exists(FULLSIZE_DIR . DIRECTORY_SEPARATOR . $removeDir . '.jpg')) {
                                    $this->_rrmdir(ZOOMTILES_DIR . DIRECTORY_SEPARATOR . $removeDir . '_zdata');
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Adds the zoom options to the images attached to the record, it inserts a
     * "Zoom" tab in the admin->edit page
     */
    public function filterAdminItemsFormTabs($tabs)
    {
        $item = get_current_item();

        $useHtml = '<span>' . __('Only images files attached to the record can be zoomed.') . '</span>';
        $counter = 0;
        $zoomList = '';

        foreach($item->Files as $file) {
            if (strpos($file->mime_os, 'image/') === 0) {
                // See if this image has been zoooomed yet.
                list($root, $ext) = $this->_getRootAndExtension($file->archive_filename);

                if (file_exists(ZOOMTILES_DIR . DIRECTORY_SEPARATOR . $root . '_zdata')) {
                    // $isChecked = '<span>' . __('This Image is zoomed.') . '</span>';
                    $isChecked = '<input type="checkbox" checked="checked" name="open_layers_zoom_filename' . $counter . '" id="open_layers_zoom_filename' . $counter . '" value="' . $root . '"/>' . __('This image is zoomed.') . '</label>';
                    $isChecked .= '<input type="hidden" name="open_layers_zoom_removed_hidden' . $counter . '" id="open_layers_zoom_removed_hidden' . $counter . '" value="' . $root . '"/>';

                    // $zoomList .= $root . '|';
                    $title = __('Click and Save Changes to make this image un zoom-able');
                    $style_color = "color:green";
                    // $useHtml .= '<br style="clear:both;" /><label style="width:auto;" for="removeZoom">' . __('Check this box and "Save Changes" to remove the zoom from all the images.') . '</label>';
                }
                else {
                    $isChecked = '<input type="checkbox" name="open_layers_zoom_filename' . $counter . '" id="open_layers_zoom_filename' . $counter . '" value="' . $file->archive_filename . '"/>' . __('Zoom this image') . '</label>';
                    $title = __('Click and Save Changes to make this image zoom-able');
                    $style_color = "color:black";
                }

                $counter++;

                $useHtml .= '
                <div style="float:left; margin:10px;">
                    <label title="' . $title . '" style="width:auto;' . $style_color . ';" for="zoomThis' . $counter . '">'
                    . display_file($file, array('imageSize'=>'thumbnail'))
                    . $isChecked . '<br />
                </div>';
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
     * Passed a file name, it will initilize the zoomify and cut the tiles.
     *
     * @param filename of image
     */
    protected function _zoom_resource($filename)
    {
        include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers'. DIRECTORY_SEPARATOR . 'ZoomifyHelper.php';

        $pathToFull = FILES_DIR . DIRECTORY_SEPARATOR;
        $zoomifyObject = new zoomify($pathToFull);
        $zoomifyObject->zoomifyObject($filename, $pathToFull);

        list($root, $ext) = $this->_getRootAndExtension($filename);

        // Tiles are built in-place, in a subdir of the original image folder.
        // Move the tiles into their storage directory.
        if (file_exists($pathToFull . $root . '_zdata')) {
            rename( $pathToFull . $root . '_zdata', ZOOMTILES_DIR . DIRECTORY_SEPARATOR . $root . '_zdata');
        }
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dirPath Directory name.
     *
     * @return boolean
     */
    protected function _rrmdir($dirPath)
    {
        $glob = glob($dirPath);
        foreach ($glob as $g) {
            if (!is_dir($g)) {
                unlink($g);
            }
            else {
                $this->_rrmdir("$g/*");
                rmdir($g);
            }
        }
        return true;
    }

    /**
     * Explode a filepath in a root and an extension, i.e. "/path/file.ext" to
     * "/path/file" and "ext".
     *
     * @return array
     */
    protected function _getRootAndExtension($filepath)
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
    protected function _useIIPImageServer()
    {
        static $flag = null;

        if (is_null($flag)) {
            $db = get_db();
            $sql = "
                SELECT elements.id
                FROM {$db->Elements} elements
                WHERE elements.element_set_id = ?
                    AND elements.name = ?
                LIMIT 1
            ";
            $bind = array(
                3,
                'Tile Server URL'
            );
            $IIPImage = $db->fetchOne($sql, $bind);
            $flag = (boolean) $IIPImage;
        }

        return $flag;
    }
}

/** Installation of the plugin. */
$OpenLayersZoomPlugin = new OpenLayersZoomPlugin();
$OpenLayersZoomPlugin->setUp();

/**
 * Wrapper called by theme.
 */
function open_layers_zoom_display_file($file = NULL, array $options = array()) {
    $OpenLayersZoom = new OpenLayersZoomPlugin();
    return $OpenLayersZoom->display_file($file, $options);
}
