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

Class Cache {
  public $cache_folder_path = '';
  public $cache_file_path = '';

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
}