# OsGetTexture #

OsGetTexture is a php script to get [OpenSimulator](http://opensimulator.org) asset textures converted to gif, jpg or png.

This script is a complete rewrite of [ci_os_getasset](https://github.com/ssm2017/ci_osgetasset) without using CodeIgniter (only raw php).

This script is made to have links compatibles with the [OpenSimulator GetTexture CAPS](http://opensimulator.org/wiki/Direct_Service_Requests#Direct_GetTexture_capability_handling). (Thank you to djphil for making me know about this capability).

## How to install ? ##

Use this script as any other php script (php 5.3+).

  * Rename the "databaseinfo.php.example" to "databaseinfo.php"
  * The file "databaseinfo.php" should be in an "etc" folder in the parent of your web root folder (ex: if your web folder is in /var/www/mywebsite/htdocs, the file should be in /var/www/mywebsite/etc) but if you would like to have it somewhere else, modify the relative path at the top of the "index.php" file.
  * make the same for the "config.php.example" file
  * fill these files with appropriate values

## How to get images ? ##
The url to get image is : http://yourwebsite.com/?texture_id=UUID&format=jpg&width=100

This link will send you a jpg image of the asset UUID with a width of 100 (height is automatically determined when using imagick or gmagick).

Only the "texture_id" parameter is mandatory.

  * Default value for "format" is "jp2".
  * Default value for "width" is the original size of the asset.

You can also set the format with a http header "Accept" like "Accept: image/jpg".

## Note ##
### How to install gmagick with Ubuntu 16.0.4 ###

#### Install gmagick for php ####
```
sudo apt-get install php-pear php-dev libgraphicsmagick1-dev
sudo pecl install gmagick-2.0.4RC1
```
#### Configure gmagick for php ####
```
sudo echo "extension=gmagick.so" > /etc/php/7.0/mods-available/gmagick.ini
sudo phpenmod gmagick 
```
Restart your web server
