<?php
/**
 * @package Users to Csv
 * @version 0.1
 */
/*
Plugin Name: Users to Csv
Plugin URI: http://github.com/welaika/users2Csv
Description: Select and export Users data and metadata to a right formatted csv file.
Version: 0.1
Author: Mukkoo
Author URI: http://github.com/mukkoo
License: GPL2
Text Domain: users-to-csv
*/

/** Main class **/
class users_export {

  /** Add actions and filters **/
  public function __construct() {
    add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
    add_action( 'init', array( $this, 'generate_csv' ) );
    add_filter( 'u2c_exclude_data', array( $this, 'exclude_data' ) );
  }
}

new users_export;
