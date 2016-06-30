<?php
/*
This file is part of OsGetTexture.
  @copyright  Copyright (C) 2016 ssm2017 Binder / wene (S.Massiaux). All rights reserved.
  OsGetTexture is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
  OsGetTexture is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
  See <http://www.gnu.org/licenses/>.
  This library was inspired from https://github.com/alemansec/opensimWebAssets made by Anthony Le Mansec <a.lm@free.fr>
*/

class Asset {

  public $format = 'jp2';
  public $width = 'full';
  public $cache_folder_path = '';
  public $cache_file_path = '';
  public $id = '00000000-0000-0000-0000-000000000000';

  private $data = '';
  private $hash = '';
  private $name = '';
  private $description = '';
  private $asset_type = 0;
  private $local = 0;
  private $temporary = 0;
  private $base_dir = '';
  private $created_at = '';
  private $updated_at = '';
  private $enabled = 1;
  private $type = '';
  private $type_int = 0;
  private $created_at_iso8601 = 0;
  private $file_path = '';

  /**
   * Shows the image
   */
  function showImage() {

    // get values in database
    if ($this->id != UUID_ZERO) {
      (ASSET_FORMAT == 'fsassets') ? $this->getFSAssetValues() : $this->getAssetValues();
    }
    else {
      $this->data = $this->getAssetZero();
      $this->hash = hash('sha256', $this->data);
    }

    $image = '';

    // build common headers
    Header("Content-type: image/". $this->format);
    Header("Cache-Control: public");

    // build the cache path
    $this->cache_folder_path = $_SERVER['DOCUMENT_ROOT']. '/'. CFG_IMG_CACHE_FOLDER_NAME. '/'.  $this->width;
    $this->cache_file_path = $this->cache_folder_path . '/' . $this->hash . '.' . $this->format;

     // check if the file exists in the cache
    if ($this->cacheCheck()) {
      $file_mod_time = filemtime($this->cache_file_path);
      // build cache headers
      //Header( "Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($this->cache_file_path))." GMT", true, 304);
      Header("Last-Modified: ".date("r",filemtime($this->cache_file_path)));
      Header("Expires: ". date("r",(CFG_IMG_CACHE_MAX_AGE + $file_mod_time)));
      // if browser asks if modified
      if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $file_mod_time)) {
        header('HTTP/1.1 304 Not Modified');
        //Header('Location:/'. CFG_IMG_CACHE_FOLDER_NAME. '/'. $this->width. '/'. $this->hash. '.'. $this->format);
        exit();
      }
      else {
        Header('Location:/'. CFG_IMG_CACHE_FOLDER_NAME. '/'. $this->width. '/'. $this->hash. '.'. $this->format);
        exit();
      }
    }
    else {
      if (ASSET_FORMAT == 'fsassets' && $this->id != UUID_ZERO) $this->getFSAssetOnDisk();

      if ($this->format == 'jp2') {
        $image = $this->data;
      }
      else {
        switch (CFG_IMG_CONVERTER) {
          case 'imagick':
            $image = $this->convertAssetImagick();
            break;
          case 'gmagick':
            $image = $this->convertAssetGmagick();
            break;
          case 'j2k_to_image':
            $image = $this->convertAssetJ2kToImage();
            break;
        }
      }
      $this->cacheWrite($image);
    }
    Header("Last-Modified: ".date("r",filemtime($this->cache_file_path)));
    Header("Expires: ". date("r",(CFG_IMG_CACHE_MAX_AGE + filemtime($this->cache_file_path))));
    echo $image;
    exit();
  }

  /**
   * Get asset
   * assets columns are :
   * name (varchar 64)
   * description (varchar 64)
   * assetType (tinyint 4)
   * local (tinyint 1)
   * temporary (tinyint 1)
   * data (longblob)
   * id (char 36)
   * create_time (int 11)
   * access_time (int 11)
   * asset_flags (int 11)
   * CreatorID (varchar 128)
   */
  function getAssetValues() {
    $link = openDB();
    $query = "SELECT * FROM assets WHERE id ='". mysqli_real_escape_string($link, $this->id). "' LIMIT 1";
    if ($result = mysqli_query($link, $query)) {
      $exists = mysqli_fetch_row($result);
      $result->close();
      if (!isset($exists[0])) {
        closeDB($link);
        show404();
      }
      $this->name         = $exists[0];
      $this->description  = $exists[1];
      $this->type         = $exists[2];
      $this->local        = $exists[3];
      $this->temporary    = $exists[4];;
      $this->data         = base64_decode($exists[5]);
      $this->id           = $exists[6];
      $this->create_time  = $exists[7];
      $this->created_at_iso8601 = date('c', $this->create_time);
      $this->access_time  = $exists[8];
      $this->asset_flags  = $exists[9];
      $this->creator_id   = $exists[10];
      $this->hash         = hash('sha256', $this->data);
    }
    closeDB($link);
  }

  /**
   * Get fsasset
   * fsassets columns are :
   * id (char 36)
   * name (varchar 64)
   * description (varchar 64)
   * type (int 11)
   * hash (char 80)
   * create_time (int 11)
   * access_time (int 11)
   * asset_flags (int 11)
   */
  function getFSAssetValues() {
    $link = openDB();
    $query = "SELECT * FROM fsassets WHERE id ='". mysqli_real_escape_string($link, $this->id). "' LIMIT 1";
    if ($result = mysqli_query($link, $query)) {
      $exists = mysqli_fetch_row($result);
      $result->close();
      if (!isset($exists[0])) {
        closeDB($link);
        show404();
      }
      $this->id           = $exists[0];
      $this->name         = $exists[1];
      $this->description  = $exists[2];
      $this->type         = $exists[3];
      $this->hash         = $exists[4];
      $this->create_time  = $exists[5];
      $this->created_at_iso8601 = date('c', $this->create_time);
      $this->access_time  = $exists[6];
      $this->asset_flags  = $exists[7];
      $this->local        = $this->asset_flags ? 'true':'false';
      $this->temporary    = $this->asset_flags ? 'true':'false';
    }
    closeDB($link);
  }

  /**
   * Get file data
   */
  function getFSAssetOnDisk() {
    // build the file path
    $upper_hash = strtoupper($this->hash);
    $this->file_path = CFG_FSASSETS_DIR . '/'. substr($upper_hash, 0, 3) . "/" . substr($upper_hash, 3, 3) . "/". $upper_hash. '.gz';
    // check if the file exists
    if (!file_exists($this->file_path)) {
      $this->data = '';
      return;
    }
    // get the file
    $size = filesize($this->file_path);
    if ($size > 0) {
      $h = gzopen($this->file_path, "rb");
      while (!gzeof($h)) {
        $this->data .= gzread($h, 4096);
      }
      gzclose($h);
      return;
    }
    $this->data = '';
  }

  /**
   * Convert and resize the image using imagick
   */
  function convertAssetImagick() {
    // build the image
    $_img = new Imagick();
    $_img->readImageBlob($this->data);
    $_img->setImageFormat($this->format);

    // resize the image if needed
    if ($this->width != 'full') {
      $original_height = $_img->getImageHeight();
      $original_width = $_img->getImageWidth();
      $multiplier = $this->width / $original_width;
      $new_height = $original_height * $multiplier;
      $_img->resizeImage($this->width, $new_height, Imagick::FILTER_CUBIC, 1);
    }

    // return the image
    return $_img->getImageBlob();
  }

  /**
   * Convert and resize the image using gmagick
   */
  function convertAssetGmagick() {
    // build the image
    $_img = new Gmagick();
    $_img->readImageBlob($this->data);
    $_img->setImageFormat($this->format);

    // resize the image if needed
    if ($this->width != 'full') {
      $original_height = $_img->getImageHeight();
      $original_width = $_img->getImageWidth();
      $multiplier = $this->width / $original_width;
      $new_height = $original_height * $multiplier;
      $_img->resizeImage($this->width, $new_height, Gmagick::FILTER_CUBIC, 1);
    }

    // return the image
    return $_img->getImageBlob();
  }

  /**
   * Convert and resize the image using j2j_to_image
   */
  function convertAssetJ2kToImage() {

    // define the temporary path
    $path = CFG_IMG_TMP_FOLDER . '/' . $this->id;

    // write asset to a temp file
    $h = fopen($path . '.j2k', "wb+");
    if (!$h) {
      return FALSE;
    }
    fwrite($h, $this->data);
    fclose($h);

    // convert the temp file to tga
    exec('j2k_to_image -i ' . $path . '.j2k' . ' -o ' . $path . '.tga');
    $output = $path . '.jpg';
    if ($this->width != 'full') {
      $geom = $this->width . 'x' . $this->width;
      $size = escapeshellarg($geom);
      $output = $path . '-' . $geom . '.jpg';
      exec('convert -scale ' . $size . ' ' . $path . '.tga ' . $output);
    }
    else {
      exec('convert ' . $path . '.tga ' . $output);
    }

    // delete temporary files
    unlink($path . '.j2k');
    unlink($path . '.tga');
    $fd = fopen($output, "rb");
    $data = fread($fd, filesize($output));
    fclose($fd);

    // delete the temporary file
    unlink($output);

    // return the image
    return $data;
  }

  /**
   * Check if the file exists in the cache
   */
  function cacheCheck() {
    // check for expiration
    $file_max_age = time() - CFG_IMG_CACHE_MAX_AGE;
    if (!file_exists($this->cache_file_path)) {
      return FALSE;
    }

    if (filemtime($this->cache_file_path) < $file_max_age || filesize($this->cache_file_path) == 0) {

      // expired, delete the file
      unlink($this->cache_file_path);

      // delete the folder if empty
      if (!scandir($this->cache_folder_path)) {
        rmdir($this->cache_folder_path);
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Write the image to the cache folder
   */
  function cacheWrite($content) {
    // check if the folder exists and if not create it
    if (!is_dir($this->cache_folder_path)) {
      if (!mkdir($this->cache_folder_path, 0777, true)) {
        die();
      }
    }

    // write the file
    $h = fopen($this->cache_file_path, "wb+");
    if (!$h) {
      return FALSE;
    }
    fwrite($h, $content);
    fclose($h);
    return TRUE;
  }

  /**
   * Get the file from the cache
   */
  function getCachedFile() {
    // get the file
    $h = fopen($this->cache_file_path, "rb");
    $data = fread($h, filesize($this->cache_file_path));
    fclose($h);

    // return the image
    return $data;
  }

  /**
   * Get the uuid zero image from its default folder
   */
  function getAssetZero() {

    // get the filepath
    $file_path = "images/uuid_zero/uuid_zero." . strtolower($this->format);

    // get the file
    $h = fopen($file_path, "rb");
    $data = fread($h, filesize($file_path));
    fclose($h);

    // return the image
    return ($data);
  }

  /**
   * Returns the asset type.
   */
  function getAssetType($type) {
    $asset_types = array(
      0 => 'image/jp2',
      1 => 'application/ogg',
      2 => 'application/x-metaverse-callingcard',
      3 => 'application/x-metaverse-landmark',
      5 => 'application/x-metaverse-clothing',
      6 => 'application/x-metaverse-primitive',
      7 => 'application/x-metaverse-notecard',
      8 => 'application/x-metaverse-folder',
      10 => 'application/x-metaverse-lsl',
      11 => 'application/x-metaverse-lso',
      12 => 'image/tga',
      13 => 'application/x-metaverse-bodypart',
      17 => 'audio/x-wav',
      19 => 'image/jpeg',
      20 => 'application/x-metaverse-animation',
      21 => 'application/x-metaverse-gesture',
      22 => 'application/x-metaverse-simstate',
      49 => 'application/vnd.ll.mesh'
    );
    if (is_int($type)) {
      if (!isset($asset_types[$type])) {
        return 'application/octet-stream';
      }
      return $asset_types[$type];
    }
    else {
      return array_search($type, $asset_types);
    }
  }

  /**
   * Returns the asset flag.
   */
  function getAssetFlag($flag) {
    $flags = array(
      'Normal' => 0,
      'Maptile' => 1,
      'Rewritable' => 2,
      'Collectable' => 4
    );
    return $flags[$flag];
  }
}
?>