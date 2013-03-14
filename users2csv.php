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

  /** Add backend menu voice **/
  public function add_admin_pages() {
    add_users_page( 'Users2Csv', 'Users2Csv', 'list_users', 'Users2Csv', array( $this, 'users_page' ) );
  }


  /** Create a list of selectable fields **/
  private function export_fields() {
    global $wpdb;
    // Default user fields
    $data_keys_range = array('ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name');

    // Collect usermeta fields
    $meta_keys_range = $wpdb->get_results( "SELECT distinct(meta_key) FROM $wpdb->usermeta" );
    $meta_keys_range = wp_list_pluck( $meta_keys_range, 'meta_key' );
    $data_fields = "<strong>Default details:</strong><br />";
    // Create checkboxes
    foreach ($data_keys_range as $field) {
      $data_fields .= '<input type="checkbox" name="field_default[]" value="'. $field .'">'. $field .'<br>';
    }
    $meta_fields = "<strong>Additional details:</strong><br />";
    foreach ($meta_keys_range as $field) {
      $meta_fields .= '<input type="checkbox" name="field_meta[]" value="'. $field .'">'. $field .'<br>';
    }
    $selectable_fields = $data_fields . $meta_fields;
    return $selectable_fields;
  }
}

new users_export;
