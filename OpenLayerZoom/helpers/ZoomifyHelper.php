<?php

/****************************************************************************************
Class Name: zoomify

Author: Justin Henry, http://greengaloshes.cc

Purpose: This class contains methods to support the use of the ZoomifyFileProcessor 
class.  The ZoomifyFileProcessor class is a port of the ZoomifyImage python script to a 
PHP class.  The original python script was written by Adam Smith, and was ported to 
PHP (in the form of ZoomifyFileProcessor) by Wes Wright.

Both tools do the about same thing - that is, they convert images into a format 
that can be used by the "zoomify" image viewer. 
    
This class provides an interface for performing "batch" conversions using the 
ZoomifyFileProcessor class.  It also provides methods for inspecting resulting 
processed images. 
    
****************************************************************************************/

require_once("ZoomifyFileProcessor.php");

class zoomify
{

    var $_debug ;
    var $_filemode=0777;
    var $_dirmode =0777;
    var $_filegroup ;

    //*****************************************************************************
    // constructor
    // initialize process, set class vars
    function zoomify ($imagepath) {
    
        define ("IMAGEPATH", $imagepath);
        
    }
    
    //*****************************************************************************
    //takes path to a directory
    //prints list of links to a zoomified image
    function listZoomifiedImages($dir) {
        if ($dh = @opendir($dir)) {
            while (false !== ($filename = readdir($dh))) {
                if (($filename != ".") && ($filename != "..") && (is_dir($dir.$filename."/")))
                    echo "<a href=\"viewer.php?file=" . $filename . "&path=" . $dir ."\">$filename</a><br>\n";
            }

        } else return false;

    }    
            
    //*****************************************************************************
    //takes path to a directory
    //returns an array containing each entry in the directory
    function getDirList($dir) {
        if ($dh = @opendir($dir)) {
            while (false !== ($filename = readdir($dh))) {
                if (($filename != ".") && ($filename != ".."))
                    $filelist[] = $filename;
            }

            sort($filelist);
        
            return $filelist;
        } else return false;

    }

    //*****************************************************************************
    //takes path to a directory
    //returns an array w/ every file in the directory that is not a dir
    function getImageList($dir) {
        if ($dh = @opendir($dir)) {
            while (false !== ($filename = readdir($dh))) {
                if (($filename != ".") && ($filename != "..") && (!is_dir($dir.$filename."/")))
                    $filelist[] = $filename;
            }

            sort($filelist);
        
            return $filelist;
        } else return false;

    }

    //*****************************************************************************
    // run the zoomify converter on the specified file.
    // check to be sure the file hasn't been converted already
    // set the perms appropriately
    function zoomifyObject($filename, $path) {
    
        $converter = new ZoomifyFileProcessor();
        $converter->_debug = $this->_debug;
        $converter->_filemode = octdec($this->_filemode);
        $converter->_dirmode = octdec($this->_dirmode);
        $converter->_filegroup = $this->_filegroup;
        
        $trimmedFilename = $this->stripExtension($filename);
    
        if (!file_exists($path . $trimmedFilename)) {
            $file_to_process = $path . $filename;
           // echo "Processing " . $file_to_process . "...<br />";
            $converter->ZoomifyProcess($file_to_process);
        } else {
           // echo "Skipping " . $path . $filename . "... (" . $path . $trimmedFilename . " exists)<br />";
        }
    
    }

    //*****************************************************************************
    // list the specified directory 
    function processImages() {
        $objects = $this->getImageList(IMAGEPATH);

        foreach ($objects as $object) {
            $this->zoomifyObject($object,IMAGEPATH);
        }
    }

    /***************************************************************************/
    //strips the extension off of the filename, i.e. file.ext -> file
    function stripExtension($filename, $ext=".jpg")
    {
        $filename = explode(".",$filename);
        $file_ext = array_pop($filename);
        $filename = implode(".",$filename);
        return $filename; 
    }

}