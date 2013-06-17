<?php
/**
 * Helpers for OpenLayersZoom.
 *
 * @package OpenLayersZoom
 */
class OpenLayersZoom
{
    /**
     * Get an array of all zoomed images of an item.
     *
     * @param object $item
     *
     * @return array
     *   Associative array of file id and files.
     */
    public static function getZoomedFiles($item = null)
    {
        $o = new OpenLayersZoomPlugin;
        return $o->getZoomedFiles($item);
    }

    /**
     * Count the number of zoomed images attached to an item.
     *
     * @param object $item
     *
     * @return integer
     *   Number of zoomed images attached to an item.
     */
    public static function zoomedFilesCount($item = null)
    {
        $o = new OpenLayersZoomPlugin;
        return $o->zoomedFilesCount($item);
    }

    /**
     * Get the url to tiles or a zoomified file, if any.
     *
     * @param object $file
     *
     * @return string
     */
    public static function getTileUrl($file = null)
    {
        $o = new OpenLayersZoomPlugin;
        return $o->getTileUrl($file);
    }

    /**
     * Determine if a file is zoomed.
     *
     * @param object $file
     *
     * @return boolean
     */
    public static function isZoomed($file = null)
    {
        $o = new OpenLayersZoomPlugin;
        return $o->isZoomed($file);
    }
}
