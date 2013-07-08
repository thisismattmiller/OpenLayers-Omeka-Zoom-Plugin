<?php
/**
 * OpenLayers Zoom: an OpenLayers based image zoom widget.
 *
 * @see README.md
 *
 * @copyright Daniel Berthereau, 2013
 * @copyright Peter Binkley, 2012-2013
 * @copyright Matt Miller, 2012
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package OpenLayersZoom
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'OpenLayersZoomFunctions.php';

/**
 * Contains code used to integrate the plugin into Omeka.
 *
 * @package OpenLayersZoom
 */
class OpenLayersZoomPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var string Extension added to a folder name to store data and tiles.
     */
    const ZOOM_FOLDER_EXTENSION = '_zdata';

    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'public_head',
        'after_save_item',
        'before_delete_file',
        'open_layers_zoom_display_file',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_items_form_tabs',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        'openlayerszoom_tiles_dir' => '/zoom_tiles',
        'openlayerszoom_tiles_web' => '/zoom_tiles',
    );

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        $this->_options['openlayerszoom_tiles_dir'] = FILES_DIR . DIRECTORY_SEPARATOR . 'zoom_tiles';
        // define('ZOOMTILES_WEB', 'http://ec2-75-101-192-109.compute-1.amazonaws.com/cgi-bin/iipsrv.fcgi?zoomify=/var/www/jp2samples');
        $this->_options['openlayerszoom_tiles_web'] = WEB_FILES . '/zoom_tiles';

        $this->_installOptions();

        // Check if there is a directory in the archive for the zoom titles we
        // will be making.
        $tilesDir = get_option('openlayerszoom_tiles_dir');
        if (!file_exists($tilesDir)) {
            mkdir($tilesDir);
            @chmod($tilesDir, 0755);

            copy(FILES_DIR . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR . 'index.html', $tilesDir . DIRECTORY_SEPARATOR . 'index.html');
            @chmod($tilesDir . DIRECTORY_SEPARATOR . 'index.html', 0644);
        }
    }

    /**
     * Uninstalls the plugin.
     */
    public function hookUninstall()
    {
        // Nuke the zoom tiles directory.
        $tilesDir = get_option('openlayerszoom_tiles_dir');
        $this->_rrmdir($tilesDir);

        $this->_uninstallOptions();
    }

    /**
     * Add css and js in the header of the public theme.
     *
     * TODO Don't add css and javascript when OpenLayersZoom is not used.
     */
    public function hookPublicHead($args)
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        if ($request->getControllerName() == 'items' && $request->getActionName() == 'show') {
            queue_css_file('OpenLayersZoom');
            queue_js_file(array(
                'OpenLayers',
                'OpenLayersZoom',
            ));
        }
    }

    /**
     * Fired once the record is saved, if there is a `open_layers_zoom_filename`
     * passed in the $_POST along with save then we know that we need to zoom
     * resource.
     */
    public function hookAfterSaveItem($args)
    {
        if (!$args['post']) {
            return;
        }

        $item = $args['record'];
        $post = $args['post'];

        // Loop through and see if there are any files to zoom.
        // Only checked values are posted.
        $filesave = false;
        $files = $this->_get_files($item);
        foreach ($post as $key => $value) {
            // Key is the file id of the stored image, value is the filename.
            if (strpos($key, 'open_layers_zoom_filename_') !== false) {
                if (!$this->isZoomed($files[(int) substr($key, strlen('open_layers_zoom_filename_'))])) {
                   $this->_createTiles($value);
                }
                $filesaved = true;
            }
            elseif ((strpos($key, 'open_layers_zoom_removed_hidden_') !== false) && ($filesaved != true)) {
                $this->_removeZDataDir($value);
            }
        }
    }

    /**
     * Manages deletion of the folder of a file when this file is removed.
     */
    public function hookBeforeDeleteFile($args)
    {
        $file = $args['record'];
        $item = $file->getItem();

        $this->_removeZDataDir($file);
    }

    /**
     * Controls how the image will be returned.
     *
     * @todo Need to change this based on how non-zoomed images are to be
     * presented.
     *
     * @param array $args
     *   Array containing:
     *   - 'file': object a file object
     *   - 'options'
     *
     * @return string
     */
    public function hookOpenLayersZoomDisplayFile($args = array())
    {
        if (!isset($args['file'])) {
            return '';
        }

        $file = $args['file'];
        $options = isset($args['options']) ? $args['options'] : array();

        // Is it a zoomified file?
        $tileUrl = $this->getTileUrl($file);

        // Do not show the zoomer on the admin page.
        if ($tileUrl) {
            // Root is not used in the javascript, but only here.
            list($root, $ext) = $this->_getRootAndExtension($file->filename);

            // Grab the width/height of the original image.
            list($width, $height, $type, $attr) = getimagesize(FILES_DIR . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR . $file->filename);

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
            $html = file_markup($file, $options);
        }
        echo $html;
    }

    /**
     * Adds the zoom options to the images attached to the record, it inserts a
     * "Zoom" tab in the admin->edit page
     *
     * @return array of tabs
     */
    public function filterAdminItemsFormTabs($tabs, $args)
    {
        $item = $args['item'];

        $useHtml = '<span>' . __('Only images files attached to the record can be zoomed.') . '</span>';
        $zoomList = '';

        foreach($item->Files as $file) {
            if (strpos($file->mime_type, 'image/') === 0) {
                // See if this image has been zoooomed yet.
                if ($this->isZoomed($file)) {
                    $isChecked = '<input type="checkbox" checked="checked" name="open_layers_zoom_filename_' . $file->id . '" id="open_layers_zoom_filename_' . $file->id . '" value="' . $file->filename . '"/>' . __('This image is zoomed.') . '</label>';
                    $isChecked .= '<input type="hidden" name="open_layers_zoom_removed_hidden_' . $file->id . '" id="open_layers_zoom_removed_hidden_' . $file->id . '" value="' . $file->filename . '"/>';

                    $title = __('Click and Save Changes to make this image un zoom-able');
                    $style_color = "color:green";
                }
                else {
                    $isChecked = '<input type="checkbox" name="open_layers_zoom_filename_' . $file->id . '" id="open_layers_zoom_filename_' . $file->id . '" value="' . $file->filename . '"/>' . __('Zoom this image') . '</label>';
                    $title = __('Click and Save Changes to make this image zoom-able');
                    $style_color = "color:black";
                }

                $useHtml .= '
                <div style="float:left; margin:10px;">
                    <label title="' . $title . '" style="width:auto;' . $style_color . ';" for="zoomThis_' . $file->id . '">'
                    . file_markup($file, array('imageSize'=>'thumbnail'))
                    . $isChecked . '<br />
                </div>';
            }
        }

        $ttabs = array();
        foreach($tabs as $key => $html) {
            if ($key == 'Tags') {
                $ttabs['Zoom'] = $useHtml;
            }
            $ttabs[$key] = $html;
        }
        $tabs = $ttabs;
        return $tabs;
    }

    /**
     * Get an array of all zoomed images of an item.
     *
     * @param object $item
     *
     * @return array
     *   Associative array of file id and files.
     */
    public function getZoomedFiles($item = null)
    {
        if ($item == null) {
            $item = get_current_record('item');
        }

        $list = array();
        set_loop_records('files', $item->getFiles());
        foreach (loop('files') as $file) {
            if ($this->isZoomed($file)) {
                $list[$file->id] = $file;
            }
        }
        return $list;
    }

    /**
     * Count the number of zoomed images attached to an item.
     *
     * @param object $item
     *
     * @return integer
     *   Number of zoomed images attached to an item.
     */
    public function zoomedFilesCount($item = null)
    {
        return count($this->getZoomedFiles($item));
    }

    /**
     * Get the url to tiles or a zoomified file, if any.
     *
     * @param object $file
     *
     * @return string
     */
    public function getTileUrl($file = null)
    {
        if ($file == null) {
            $file = get_current_record('file');
        }

        $tileUrl = '';
        // Does it use a IIPImage server?
        if ($this->_useIIPImageServer()) {
            $item = $file->getItem();
            $tileUrl = metadata($item, array('Item Type Metadata', 'Tile Server URL'));
        }

        // Does it have zoom tiles?
        elseif (file_exists($this->_getZDataDir($file))) {
            // fetch identifier, to use in link to tiles for this jp2 - pbinkley
            // $jp2 = item('Dublin Core', 'Identifier') . '.jp2';
            // $tileUrl = ZOOMTILES_WEB . '/' . $jp2;
            $tileUrl = $this->_getZDataWeb($file);
        }

        return $tileUrl;
    }

    /**
     * Determine if a file is zoomed.
     *
     * @param object $file
     *
     * @return boolean
     */
    public function isZoomed($file = null)
    {
        return (boolean) $this->getTileUrl($file);
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
            $bind = array(3, 'Tile Server URL');
            $IIPImage = $db->fetchOne($sql, $bind);
            $flag = (boolean) $IIPImage;
        }

        return $flag;
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
     * Returns the folder where are stored xml data and tiles (zdata path).
     *
     * @param string|object $file
     *   Filename or file object.
     *
     * @return string
     *   Full folder path where xml data and tiles are stored.
     */
    protected function _getZDataDir($file)
    {
        $filename = is_string($file) ? $file : $file->filename;
        list($root, $extension) = $this->_getRootAndExtension($filename);
        return get_option('openlayerszoom_tiles_dir') . DIRECTORY_SEPARATOR . $root . self::ZOOM_FOLDER_EXTENSION;
    }

    /**
     * Returns the url to the folder where are stored xml data and tiles (zdata
     * path).
     *
     * @param string|object $file
     *   Filename or file object.
     *
     * @return string
     *   Url where xml data and tiles are stored.
     */
    protected function _getZDataWeb($file)
    {
        $filename = is_string($file) ? $file : $file->filename;
        list($root, $extension) = $this->_getRootAndExtension($filename);
        return get_option('openlayerszoom_tiles_web') . DIRECTORY_SEPARATOR . $root . self::ZOOM_FOLDER_EXTENSION;
    }

    /**
     * Passed a file name, it will initilize the zoomify and cut the tiles.
     *
     * @param filename of image
     */
    protected function _createTiles($filename)
    {
        include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers'. DIRECTORY_SEPARATOR . 'ZoomifyHelper.php';

        // Tiles are built in-place, in a subdir of the original image folder.
        // TODO Add a destination path to use local server path and to avoid move.
        $originalDir = FILES_DIR . DIRECTORY_SEPARATOR . 'original' . DIRECTORY_SEPARATOR;
        list($root, $ext) = $this->_getRootAndExtension($filename);
        $sourcePath = $originalDir . $root . '_zdata';

        $zoomifyObject = new zoomify($originalDir);
        $zoomifyObject->zoomifyObject($filename, $originalDir);

       // Move the tiles into their storage directory.
       if (file_exists($sourcePath)) {
            // Check if destination folder exists, else create it.
            $destinationPath = $this->_getZDataDir($filename);
            if (!is_dir(dirname($destinationPath))) {
                $result = mkdir(dirname($destinationPath), 0755, true);
                if (!$result) {
                    $message = __('Impossible to create destination directory: "%s" for file "%s".', $destinationPath, basename($filename));
                    _log($message, Zend_Log::WARN);
                    throw new Omeka_Storage_Exception($message);
                }
            }
            rename($sourcePath, $destinationPath);
        }
    }

    /**
     * Manages deletion of the folder of a file when this file is removed.
     *
     * @param string|object $file
     *   Filename or file object.
     *
     * @return void
     */
    protected function _removeZDataDir($file)
    {
        $file = is_string($file) ? $file : $file->filename;
        if ($file == '' || $file == '/') {
            return;
        }

        $removeDir = $this->_getZDataDir($file);
        if (file_exists($removeDir)) {
            // Make sure there is an image file with this name,
            // meaning that it really is a zoomed image dir and
            // not deleting the root of the site :(
            // We check a derivative, because the original image
            // is not always a jpg one.
            list($root, $ext) = $this->_getRootAndExtension($file);
            if (file_exists(FILES_DIR . DIRECTORY_SEPARATOR . 'fullsize' . DIRECTORY_SEPARATOR . $root . '.jpg')) {
                $this->_rrmdir($removeDir);
            }
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
     * Order files attached to an item by file id.
     *
     * @param object $item.
     *
     * @return array
     *  Array of files ordered by file id.
     */
    protected function _get_files($item)
    {
        $files = array();
        foreach ($item->Files as $file) {
            $files[$file->id] = $file;
        }

        return $files;
    }
}
