<?php
/*
Plugin Name: Updatez
Plugin URI: https://github.com/bonbons0220/updatez
Description: Tracks content updates. Requires WCK Pro Version to work.
Version: 1.2
Author: Bonnie Souter
Author URI: http://zendgame.com
License: GPLv2

Copyright 2015 Bonnie Souter  (email : bonbons0220@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Only allow this script to be run within WordPress
defined('ABSPATH') or die("Unknown Access Error");

if ( defined( 'ICT_PASSWORD_VERSION' ) ) die('Updatez vesion ' . ICT_PASSWORD_VERSION . ' already running.');
define('ICT_PASSWORD_VERSION', '1.2');

define('ICT_DIR_PATH',plugin_dir_path( __FILE__ ));
define('ICT_DIR_URL',plugin_dir_url( __FILE__ ));

// load the class file
require_once( ICT_DIR_PATH . 'lib/updatez.php' );
$updatez = new updatez();

?>