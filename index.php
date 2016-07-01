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

/**
 * UUID zero
 */
if (!defined('UUID_ZERO')) {
  define('UUID_ZERO', '00000000-0000-0000-0000-000000000000');
}
include("../etc/databaseinfo.php");
include('../etc/config.php');
include('Asset.Class.php');
include('Cache.Class.php');

/**
 * Check
 */
function check() {
  $message = '<html>';
  // check if the database can be accessed
  $link = openDB();
  $db_answers = mysqli_query($link,'SELECT id FROM fsassets LIMIT 1');
  if ($db_answers) {
    $message .= '<p style="color:green">Database access success.</p>';
  }
  else {
    $message .= '<p style="color:red">Error with database config.</p>';
  }
  closeDB($link);
  // check if the cache folder is writable
  if (is_writable(CFG_IMG_CACHE_FOLDER)) {
    $message .= '<p style="color:green">Cache folder is writable.</p>';
  }
  else {
    $message .= '<p style="color:red">Cache folder is not writable.</p>';
  }
  // check if the image converter is available
  if (CFG_IMG_CONVERTER == 'imagick') {
    if(extension_loaded('imagick')) {
      $message .= '<p style="color:green">ImageMagick is available.</p>';
    }
    else {
      $message .= '<p style="color:red">ImageMagick is not available.</p>';
    }
  }
  if (CFG_IMG_CONVERTER == 'gmagick') {
    if(extension_loaded('gmagick')) {
      $message .= '<p style="color:green">GraphicsMagick is available.</p>';
    }
    else {
      $message .= '<p style="color:red">GraphicsMagick is not available.</p>';
    }
  }
  echo $message.'</html>';
}

/**
 * Validate uuid
 */
function checkUUID($uuid) {
  if (preg_match("/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/", $uuid)) {
    return TRUE;
  }
  return FALSE;
}

/**
 * Return 400
 */
function show400() {
  header('HTTP/1.1 400 Bad Request');
  exit();
}

/**
 * Return 404
 */
function show404() {
  header('HTTP/1.1 404 Not Found');
  exit();
}

/**
 * Connect to database
 */
function openDB() {
  $link = mysqli_connect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
  if (!$link) { die('Connect Error (' . mysqli_connect_errno() . ') '. mysqli_connect_error()); exit; }
  mysqli_set_charset($link, "utf8");
  return $link;
}

/**
 * Close database connection
 */
function closeDB($link) {
  mysqli_close($link);
}

/**
 * Shows the image
 */
function showImage() {
  // create the asset object
  $asset = new Asset();
  $asset->id = $_GET['texture_id'];

  // get the format
  if (isset($_SERVER['HTTP_ACCEPT']) && in_array($_SERVER['HTTP_ACCEPT'], array('image/jpg', 'image/jpeg', 'image/gif', 'image/png'))) {
    $asset->format = explode('/', $_SERVER['HTTP_ACCEPT'])[1];
  } else if (isset($_GET['format']) && in_array($_GET['format'], array('jpg', 'jpeg', 'gif', 'png'))) {
    $asset->format = $_GET['format'];
  }
  // get the width
  if (isset($_GET['width']) && is_numeric($_GET['width'])) {
    $asset->width = ($_GET['width'] > 1024) ? 1024 : $_GET['width'];
  }

  // get values in database
  if ($asset->id != UUID_ZERO) {
    (ASSET_FORMAT == 'fsassets') ? $asset->getFSAssetValues() : $asset->getAssetValues();
  }
  else {
    $asset->data = $asset->getAssetZero();
    $asset->hash = hash('sha256', $asset->data);
  }

  $image = '';

  // build common headers
  Header("Content-type: image/". $asset->format);

  // manage the cache
  Header("Cache-Control: public");
  $cache = new Cache();

  // build the cache path
  $cache->cache_folder_path = $_SERVER['DOCUMENT_ROOT']. '/'. CFG_IMG_CACHE_FOLDER_NAME. '/'.  $asset->width;
  $cache->cache_file_path = $cache->cache_folder_path . '/' . $asset->hash . '.' . $asset->format;

   // check if the file exists in the cache
  if ($cache->cacheCheck()) {
    $file_mod_time = filemtime($cache->cache_file_path);
    // build cache headers
    //Header("Last-Modified: ".date("r",filemtime($cache->cache_file_path)), true, 304);
    Header("Last-Modified: ".date("r",filemtime($cache->cache_file_path)));
    Header("Expires: ". date("r",(CFG_IMG_CACHE_MAX_AGE + $file_mod_time)));
    // if browser asks if modified
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $file_mod_time)) {
      header('HTTP/1.1 304 Not Modified');
      //Header('Location:/'. CFG_IMG_CACHE_FOLDER_NAME. '/'. $this->width. '/'. $this->hash. '.'. $this->format);
      exit();
    }
    else {
      Header('Location:/'. CFG_IMG_CACHE_FOLDER_NAME. '/'. $asset->width. '/'. $asset->hash. '.'. $asset->format);
      exit();
    }
  }
  else {
    if (ASSET_FORMAT == 'fsassets' && $asset->id != UUID_ZERO) $asset->getFSAssetOnDisk();

    if ($asset->format == 'jp2') {
      $image = $asset->data;
    }
    else {
      switch (CFG_IMG_CONVERTER) {
        case 'imagick':
          $image = $asset->convertAssetImagick();
          break;
        case 'gmagick':
          $image = $asset->convertAssetGmagick();
          break;
        case 'j2k_to_image':
          $image = $asset->convertAssetJ2kToImage();
          break;
      }
    }
    $cache->cacheWrite($image);
  }
  Header("Last-Modified: ".date("r",filemtime($cache->cache_file_path)));
  Header("Expires: ". date("r",(CFG_IMG_CACHE_MAX_AGE + filemtime($cache->cache_file_path))));
  echo $image;
  exit();
}

/**
 * Main code
 */
switch(True) {
  // return bad request
  default:
    show400();
    break;
  // return the "check" page
  case isset($_GET['check']):
    check();
    break;
  // return the image
  case isset($_GET['texture_id']) && checkUUID($_GET['texture_id']):
    // get the image
    showImage();
    break;
}
?>