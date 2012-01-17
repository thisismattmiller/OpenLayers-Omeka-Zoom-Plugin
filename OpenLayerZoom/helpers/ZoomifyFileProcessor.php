<?php
##############################################################################
# Copyright (C) 2005  Adam Smith  asmith@agile-software.com
# 
# Ported from Python to PHP by Wes Wright
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
##############################################################################
function rm($fileglob)
{
   if (is_string($fileglob)) {
       if (is_file($fileglob)) {
           return unlink($fileglob);
       } else if (is_dir($fileglob)) {
           $ok = rm("$fileglob/*");
           if (! $ok) {
               return false;
           }
           return rmdir($fileglob);
       } else {
           $matching = glob($fileglob);
           if ($matching === false) {
               trigger_error(sprintf('No files match supplied glob %s', $fileglob), E_USER_WARNING);
               return false;
           }      
           $rcs = array_map('rm', $matching);
           if (in_array(false, $rcs)) {
               return false;
           }
       }      
   } else if (is_array($fileglob)) {
       $rcs = array_map('rm', $fileglob);
       if (in_array(false, $rcs)) {
           return false;
       }
   } else {
       trigger_error('Param #1 must be filename or glob pattern, or array of filenames or glob patterns', E_USER_ERROR);
       return false;
   }

   return true;
}

function imageCrop($image,$left,$upper,$right,$lower) {
    $x=imagesx($image);
    $y=imagesy($image);
    //if ($this->_debug) print "imageCrop x=$x y=$y left=$left upper=$upper right=$right lower=$lower<br>\n";
    $w=abs($right-$left);
    $h=abs($lower-$upper);
    $crop=imagecreatetruecolor($w,$h);
    imagecopy($crop,$image,0,0,$left,$upper,$w,$h);
    return $crop;
}

class ZoomifyFileProcessor  {
     var $_v_imageFilename ;
    var $originalWidth ;
    var $originalHeight ;
    var $_v_scaleInfo = array();
    var $numberOfTiles ;
    var $_v_tileGroupMappings = array();
    var $qualitySetting ;
    var $tileSize ;
    var $_debug ;
    var $_filemode ;
    var $_dirmode ;
    var $_filegroup ;
 
    function ZoomifyFileProcessor() {
        $this->_v_imageFilename = '';
        $this->format = '';
        $this->originalWidth = 0;
        $this->originalHeight = 0;
        $this->numberOfTiles = 0;
        $this->qualitySetting = 80;
        $this->tileSize = 256;
        $this->_debug = 0;
        $this->_filemode = octdec('664');
        $this->_dirmode = octdec('2775');
        $this->_filegroup = "user";
        
    }
    
    function openImage() {
#    """ load the image data """
        if ($this->_debug) print "openImage $this->_v_imageFilename<br>\n";
        return imagecreatefromjpeg($this->_v_imageFilename);
    }      
  
    function getTileFileName($scaleNumber, $columnNumber, $rowNumber) {
#    """ get the name of the file the tile will be saved as """
    
#        return '%s-%s-%s.jpg' % (str(scaleNumber), str(columnNumber), str(rowNumber))
        return "$scaleNumber-$columnNumber-$rowNumber.jpg";
    }

    function getNewTileContainerName($tileGroupNumber=0) {
#    """ return the name of the next tile group container """
        return "TileGroup" . $tileGroupNumber;
    }

    
    function preProcess() {
#    """ plan for the arrangement of the tile groups """
   
        $tier = 0;
        $tileGroupNumber = 0;
        $numberOfTiles = 0;
        foreach ($this->_v_scaleInfo as $width_height) {
              list($width,$height)=$width_height;
    #        cycle through columns, then rows
            $row=0;
            $column=0;
            $ul_x=0;
            $ul_y=0;
            $lr_x=0;
            $lr_y=0;
            while (! (($lr_x == $width) && ($lr_y == $height))) {
               
                $tileFileName = $this->getTileFileName($tier, $column,$row);
                $tileContainerName = $this->getNewTileContainerName($tileGroupNumber);
                if ($numberOfTiles ==0) {
                    $this->createTileContainer($tileContainerName);
                } elseif  ($numberOfTiles % $this->tileSize == 0) {
                    $tileGroupNumber++;
                    $tileContainerName = $this->getNewTileContainerName($tileGroupNumber);
                    $this->createTileContainer($tileContainerName);
                      if ($this->_debug) print "new tile group " .$tileGroupNumber ." tileContainerName=" . $tileContainerName ."<br>\n";
                }
                $this->_v_tileGroupMappings[$tileFileName] = $tileContainerName;
                $numberOfTiles++;
               
                # for the next tile, set lower right cropping point
                if ($ul_x + $this->tileSize < $width) {
                    $lr_x = $ul_x + $this->tileSize;
                } else {
                    $lr_x = $width;
                }
                 
                if ($ul_y + $this->tileSize < $height) {
                    $lr_y = $ul_y + $this->tileSize;
                } else {
                    $lr_y = $height;
                }
          
                # for the next tile, set upper left cropping point
                if ($lr_x == $width) {
                    $ul_x=0;
                    $ul_y = $lr_y;
                    $column = 0;
                    $row++;
                } else {
                    $ul_x = $lr_x;
                    $column++;
                }
            }
            $tier++;
        }
    }




    function processRowImage($tier=0, $row=0) {
        #    """ for an image, create and save tiles """
        
        #    print '*** processing tier: ' + str(tier) + ' row: ' + str(row)
        
        list($tierWidth, $tierHeight) = $this->_v_scaleInfo[$tier];
        if ($this->_debug) print "tier $tier width $tierWidth  height $tierHeight<br>\n";
        $rowsForTier = floor($tierHeight/$this->tileSize);
        if ($tierHeight % $this->tileSize > 0) $rowsForTier++;
		
		//echo "Something " . $this->_v_imageFilename;
		
        list($root, $root2, $ext) = explode(".",$this->_v_imageFilename);
		
		//echo "root = $root<br />";
		//echo "root2 = $root2<br />";		
		
		//.com
		$root = $root.'.'.$root2;
		
        if ( !$root) $root = $this->_v_imageFilename;
		
		//echo "root = $root<br />";
				
        $ext = ".jpg";
        
		
        #    $imageRow = None
        
        if ($tier == count($this->_v_scaleInfo) -1) {
            $firstTierRowFile = $root . $tier. "-" . $row . $ext;
            if ($this->_debug) print "firstTierRowFile=$firstTierRowFile<br>";
            if (is_file($firstTierRowFile)) {
                $imageRow = imagecreatefromjpeg($firstTierRowFile);
                if ($this->_debug) print "firstTierRowFile exists<br>";
            }
        }  else {
            # create this row from previous tier's rows
            $imageRow = imagecreatetruecolor($tierWidth, $this->tileSize);
            $t=$tier+1;
            $r=$row+$row;
            $firstRowFile = $root . $t . "-" . $r . $ext;
            if ($this->_debug) print "create this row from previous tier's rows tier=$tier row=$row firstRowFile=$firstRowFile<br>\n";
            if ($this->_debug) print "imageRow tierWidth=$tierWidth tierHeight= $this->tileSize<br>\n";
            $firstRowWidth=0;
            $firstRowHeight = 0;
            $secondRowWidth=0;
            $secondRowHeight = 0;
            if (is_file($firstRowFile)) {
                #        print firstRowFile + ' exists, try to open...'
                $firstRowImage = imagecreatefromjpeg($firstRowFile);
                $firstRowWidth=imagesx( $firstRowImage);
                $firstRowHeight = imagesy( $firstRowImage);
                $imageRowHalfHeight=floor($this->tileSize/2);
                #          imagecopy ( resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int src_w, int src_h )
#                imagecopy            ($imageRow       , $firstRowImage ,         0,         0,         0,         0,  $firstRowWidth, $firstRowHeight);
                if ($this->_debug) print "imageRow imagecopyresized tierWidth=$tierWidth imageRowHalfHeight= $imageRowHalfHeight firstRowWidth=$firstRowWidth firstRowHeight=$firstRowHeight<br>\n";
                #    imagecopyresized ( resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y,       int dst_w,                int dst_h,      int src_w, int src_h )
                imagecopyresized     ($imageRow       , $firstRowImage ,         0,         0,         0,         0,      $tierWidth,      $imageRowHalfHeight, $firstRowWidth, $firstRowHeight);
                unlink($firstRowFile);
            }
            $r=$r+1;
            $secondRowFile =  $root . $t . "-" . $r . $ext;
            if ($this->_debug) print "create this row from previous tier's rows tier=$tier row=$row secondRowFile=$secondRowFile<br>\n";
            # there may not be a second row at the bottom of the image...
            if (is_file($secondRowFile)) {
                if ($this->_debug) print $secondRowFile . " exists, try to open...<br>\n";
                $secondRowImage =imagecreatefromjpeg($secondRowFile);
                $secondRowWidth=imagesx( $secondRowImage);
                $secondRowHeight = imagesy( $secondRowImage);
                #          imagecopy ( resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y, int src_w, int src_h )
                if ($this->_debug) print "imageRow imagecopyresized tierWidth=$tierWidth imageRowHalfHeight= $imageRowHalfHeight firstRowWidth=$firstRowWidth firstRowHeight=$firstRowHeight<br>\n";
                #    imagecopyresampled ( resource dst_im, resource src_im, int dst_x,                int dst_y, int src_x, int src_y,       int dst_w,           int dst_h,       int src_w,       int src_h )
                imagecopyresampled     ($imageRow       , $secondRowImage ,         0,     $imageRowHalfHeight,         0,         0,      $tierWidth, $secondRowHeight, $secondRowWidth, $secondRowHeight);
#                imagecopy($imageRow,$secondRowImage,0,$firstRowWidth,0,0,$firstRowWidth,$firstRowHeight);
                unlink($secondRowFile);
            }
        
        
            # the last row may be less than $this->tileSize...
            $rowHeight=$firstRowHeight+$secondRowHeight;
            $tileHeight=$this->tileSize*2;
            if (($firstRowHeight + $secondRowHeight) < $this->tileSize*2) {
                if ($this->_debug) print "line 241 calling crop rowHeight=$rowHeight tileHeight=$tileHeight<br>";
                 $imageRow=imageCrop($imageRow,0,0,$tierWidth,$firstRowHeight+$secondRowHeight);
                #        imageRow = imageRow.crop((0, 0, tierWidth, (firstRowHeight+secondRowHeight)))
            }
        }
        if ($imageRow) {
        
            # cycle through columns, then rows
            $column = 0;
            $imageWidth=imagesx( $imageRow);
            $imageHeight = imagesy( $imageRow);
            $ul_x=0;
            $ul_y=0;
            $lr_x=0;
            $lr_y =0;
            while  (!(($lr_x == $imageWidth) && ($lr_y == $imageHeight))){
                if ($this->_debug) print "ul_x=$ul_x lr_x=$lr_x ul_y=$ul_y lr_y=$lr_y imageWidth=$imageWidth imageHeight=$imageHeight<br>\n";
                # set lower right cropping point
                if (($ul_x + $this->tileSize) < $imageWidth) {
                    $lr_x = $ul_x + $this->tileSize;
                } else {
                    $lr_x = $imageWidth;
                }
                  
                if (($ul_y + $this->tileSize) < $imageHeight) {
                    $lr_y = $ul_y + $this->tileSize;
                } else {
                    $lr_y = $imageHeight;
                }
                    
                #tierLabel = len($this->_v_scaleInfo) - tier
                if ($this->_debug) print "line 248 calling crop<br>";
                $this->saveTile(imageCrop($imageRow, $ul_x, $ul_y, $lr_x, $lr_y), $tier, $column, $row);
                $this->numberOfTiles++;
                if ($this->_debug) print "created tile: numberOfTiles= $this->numberOfTiles tier column row =($tier,$column,$row)<br>\n";
                
                # set upper left cropping point
                if ($lr_x == $imageWidth) {
                    $ul_x=0;
                    $ul_y = $lr_y;
                    $column = 0;
                    #row += 1
                } else {
                    $ul_x = $lr_x;
                    $column++;
                }
            }
            if ($tier > 0) {
                $halfWidth=max(1,floor($imageWidth/2));
                $halfHeight=max(1,floor($imageHeight/2));
                #        tempImage = imageRow.resize((imageWidth/2, imageHeight/2), PIL.Image.ANTIALIAS)
                $tempImage=imagecreatetruecolor($halfWidth,$halfHeight);
                #    imagecopyresampled ( resource dst_im, resource src_im, int dst_x, int dst_y, int src_x, int src_y,       int dst_w,       int dst_h,   int src_w,   int src_h )
                imagecopyresampled     (      $tempImage,       $imageRow,         0,         0,         0,          0,     $halfWidth,     $halfHeight, $imageWidth, $imageHeight);
                #        tempImage = imageRow.resize((halfWidth, halfHeight), PIL.Image.ANTIALIAS)
                #        print 'resize as ' + str(imageWidth/2) + ' by ' + str(imageHeight/2) + ' (or ' + str(halfWidth) + ' x ' + str(halfHeight) + ')'
                #        tempImage.save(root + str(tier) + '-' + str(row) + ext)
                $rowFileName=$root.$tier."-".$row.$ext;
                touch ($rowFileName);
                imagejpeg($tempImage,$rowFileName);
                chmod ($rowFileName,0777);
                //chgrp ($rowFileName,$this->_filegroup);
                imagedestroy($tempImage);
                #        print 'saved row file: ' + root + str(tier) + '-' + str(row) + ext
                #        tempImage = None
                #      rowImage = None
            }
            
            if ($tier > 0) {
                if ($this->_debug) print "processRowImage final checks for tier $tier row=$row rowsForTier=$rowsForTier<br>\n";
                if ($row % 2 != 0) {
                  if ($this->_debug) print "processRowImage final checks tier=$tier row=$row mod 2 check before<br>\n";
#                  $this->processRowImage($tier=$tier-1,$row=($row-1)/2);
                  $this->processRowImage($tier-1,floor(($row-1)/2));
                  if ($this->_debug) print "processRowImage final checks tier=$tier row=$row mod 2 check after<br>\n";
                } elseif ($row==$rowsForTier-1) {
                  if ($this->_debug) print "processRowImage final checks tier=$tier row=$row rowsForTier=$rowsForTier check before<br>\n";
#                  $this->processRowImage($tier=$tier-1, $row=$row/2);
                  $this->processRowImage($tier-1, floor($row/2));
                  if ($this->_debug) print "processRowImage final checks tier=$tier row=$row rowsForTier=$rowsForTier check after<br>\n";
                }
            }
        }
    }

  
  
    function processImage() {
#        """ starting with the original image, start processing each row """
        $tier=(count($this->_v_scaleInfo) -1);
        $row = 0;
        list($ul_y, $lr_y) = array(0,0);

        list($root, $root2, $ext) = explode(".",$this->_v_imageFilename);
		 
		
		$root = $root . '.' .$root2;		
		
		
		
		
        if (!$root) $root = $this->_v_imageFilename;
		

		
        $ext = ".jpg";
        if ($this->_debug) print "processImage root=$root ext=$ext<br>\n";
        $image = $this->openImage();
        while ($row * $this->tileSize < $this->originalHeight) {
            $ul_y = $row * $this->tileSize;
            if ($ul_y + $this->tileSize < $this->originalHeight) {
                $lr_y = $ul_y + $this->tileSize;
            } else {
                $lr_y = $this->originalHeight;
            }
#            print "line 309 calling crop<br>";
            $imageRow=imageCrop($image,0, $ul_y, $this->originalWidth, $lr_y);
    #        imageRow = image.crop([0, ul_y, $this->originalWidth, lr_y])
            $saveFilename = $root . $tier . "-" . $row .  $ext;
            if ($this->_debug) print "processImage root=$root tier=$tier row=$row saveFilename=$saveFilename<br>\n";
            touch($saveFilename);
            chmod ($saveFilename,0777);
            imagejpeg($imageRow,$saveFilename, 100);
            //chgrp ($saveFilename,$this->_filegroup);
            imagedestroy($imageRow);
            $this->processRowImage($tier, $row);
            $row++;
        }
        imagedestroy($image);
    }
    

    function getXMLOutput() {
#    """ create xml metadata about the tiles """
    
        $numberOfTiles = $this->getNumberOfTiles();
        $xmlOutput = "<IMAGE_PROPERTIES WIDTH=\"".$this->originalWidth."\" HEIGHT=\"".$this->originalHeight."\" NUMTILES=\"".$numberOfTiles."\" NUMIMAGES=\"1\" VERSION=\"1.8\" TILESIZE=\"".$this->tileSize."\" />";
        return $xmlOutput;
    }


    function getAssignedTileContainerName($tileFileName) {
#    """ return the name of the tile group for the indicated tile """
        if ($tileFileName) {
#            print "getAssignedTileContainerName tileFileName $tileFileName exists<br>\n";
#            if (isset($this->_v_tileGroupMappings)) print "getAssignedTileContainerName this->_v_tileGroupMappings defined<br>\n";
#            if ($this->_v_tileGroupMappings) print "getAssignedTileContainerName this->_v_tileGroupMappings is true\n";
            if (isset($this->_v_tileGroupMappings) && $this->_v_tileGroupMappings) {
                $containerName = $this->_v_tileGroupMappings[$tileFileName];
                if ($containerName) {
#                    print "getAssignedTileContainerName returning containerName " . $containerName ."<br>\n";
                     return $containerName;
                }
            }
        }
        $containerName = $this->getNewTileContainerName();
        if ($this->_debug) print "getAssignedTileContainerName returning getNewTileContainerName " .$containerName ."<br>\n";

        return $containerName ;
    }


    
    
  function getImageMetadata() {
#    """ given an image name, load it and extract metadata """
    
        list($this->originalWidth, $this->originalHeight,$this->format)=getimagesize($this->_v_imageFilename);
        
        # get scaling information
        $width =$this->originalWidth;
        $height = $this->originalHeight;
        if ($this->_debug) print "getImageMetadata for file $this->_v_imageFilename originalWidth=$width originalHeight=$height tilesize=$this->tileSize<br>\n";
        $width_height=array($width,$height);
        array_unshift($this->_v_scaleInfo,$width_height);
        while (($width > $this->tileSize) || ($height > $this->tileSize)) {
            $width = floor($width / 2);
            $height = floor($height / 2);
            $width_height=array($width,$height);
            array_unshift($this->_v_scaleInfo,$width_height);
            if ($this->_debug) print "getImageMetadata newWidth=$width newHeight=$height<br>\n";
        }
        # tile and tile group information
        $this->preProcess();
    }  
    function createTileContainer($tileContainerName="") {
#    """ create a container for the next group of tiles within the data container """
        $cwd=dirname($this->file);
        $tileContainerPath =$cwd."/".$this->_v_saveToLocation."/".$tileContainerName;
		
		
        if (!is_dir($tileContainerPath)) {
			//echo "Trying to make $tileContainerPath<br />";
            mkdir($tileContainerPath) ;
            chmod($tileContainerPath,0777);
            //chgrp($tileContainerPath,$this->_filegroup);
        }
    }
      
     
  
    function createDataContainer($imageName) {
#    """ create a container for tiles and tile metadata """
    
        $directory=dirname($imageName);
        $filename=basename($imageName);
        list($root,$ext)=explode(".",basename($filename));
	    //list($root, $root2, $ext) = explode(".",basename($filename));
		 
		$root = $root.$root2;		 
		
        $root = $root . "_zdata";
    
			
        $this->_v_saveToLocation = $directory."/".$root;

    #    If the paths already exist, an image is being re-processed, clean up for it.
        if (is_dir($this->_v_saveToLocation)) {
            $rm_err=rm($this->_v_saveToLocation);
        } 
        mkdir($this->_v_saveToLocation);
        chmod($this->_v_saveToLocation,0777);
        //chgrp($this->_v_saveToLocation,$this->_filegroup);
        
    }
    
    function getFileReference($scaleNumber, $columnNumber, $rowNumber) {
#    """ get the full path of the file the tile will be saved as """
    
        $tileFileName = $this->getTileFileName($scaleNumber, $columnNumber, $rowNumber);
        $tileContainerName = $this->getAssignedTileContainerName($tileFileName);
        return $this->_v_saveToLocation."/".$tileContainerName."/".$tileFileName;
    }
    
    
    function getNumberOfTiles() {
#    """ get the number of tiles generated """
      
    #return len(os.listdir($this->_v_tileContainerPath))
        return $this->numberOfTiles;
    }
    
    function saveXMLOutput() {
#    """ save xml metadata about the tiles """
    
        $xmlFile = fopen($this->_v_saveToLocation."/ImageProperties.xml", 'w');
        fwrite($xmlFile,$this->getXMLOutput());
        fclose( $xmlFile);
        chmod($this->_v_saveToLocation."/ImageProperties.xml",0777);
        //chgrp($this->_v_saveToLocation."/ImageProperties.xml",$this->_filegroup);
    }
    
    
  
    function saveTile($image, $scaleNumber, $column, $row) {
#    """ save the cropped region """
        $tile_file=$this->getFileReference($scaleNumber, $column, $row);
        touch($tile_file);
        chmod ($tile_file,0777);
        imagejpeg($image,$tile_file,$this->qualitySetting);
        if ($this->_debug) print "Saving to tile_file $tile_file<br>\n";
    }    
    
  function ZoomifyProcess($image_name) {
#    """ the method the client calls to generate zoomify metadata """
      
      $this->_v_imageFilename = $image_name;
      $this->createDataContainer($image_name);
      $this->getImageMetadata();
      $this->processImage();
      $this->saveXMLOutput();
    }    
}
?>