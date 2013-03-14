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
    add_users_page( 'Export to Csv', 'Export to Csv', 'list_users', 'Export to Csv', array( $this, 'users_page' ) );
  }

  /** Process content into CSV file **/
  public function generate_csv() {
    // Check the POST source
    if ( isset( $_POST['_wpnonce-export-users-page_export'] ) ) {
      check_admin_referer( 'export-users-page_export', '_wpnonce-export-users-page_export' );
      // Get users by Role
      $args = array(
        'fields' => 'all_with_meta',
        'role' => stripslashes( $_POST['role'] )
      );
      add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
      $users = get_users( $args );
      remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
      // Error: no matched users
      if ( ! $users ) {
        $referer = add_query_arg( 'error', 'empty', wp_get_referer() );
        wp_redirect( $referer );
        exit;
      }
      // Filename
      $sitename = sanitize_key( get_bloginfo( 'name' ) );
      if ( ! empty( $sitename ) )
        $sitename .= '.';
      $filename = $sitename . 'users.' . date( 'Y-m-d-H-i-s' ) . '.csv';
      // Set CSV header
      header( 'Content-Description: File Transfer' );
      header( 'Content-Disposition: attachment; filename=' . $filename );
      header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );
      $exclude_data = apply_filters( 'u2c_exclude_data', array() );

      global $wpdb;
      // if Custom Default WP Fields are checked use it else use default set
      if (isset($_POST['field_default'])) $data_keys = $_POST['field_default'];
      else $data_keys = array('ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_registered', 'display_name');
      // if Custom Additional Metas WP Fields are checked use it else use all
      if (isset($_POST['field_meta'])) $meta_keys = $_POST['field_meta'];
      else $meta_keys = wp_list_pluck( $wpdb->get_results( "SELECT distinct(meta_key) FROM $wpdb->usermeta" ), 'meta_key' );
      // Merge fields list
      $fields = array_merge( $data_keys, $meta_keys );

      // Headers
      $headers = array();
      // Generate CSV fields
      foreach ( $fields as $key => $field ) {
        if ( in_array( $field, $exclude_data ) )
          unset( $fields[$key] );
        else
          $headers[] = '"' . $field . '"';
      }
      echo implode( ',', $headers ) . "\n";

      foreach ( $users as $user ) {
        $data = array();
        foreach ( $fields as $field ) {
          $value = isset( $user->{$field} ) ? $user->{$field} : '';
          $value = is_array( $value ) ? serialize( $value ) : $value;
          $data[] = '"' . str_replace( '"', '""', $value ) . '"';
        }
        echo implode( ',', $data ) . "\n";
      }
      exit;
    }
    
  }

  /** Content of the settings page **/
  public function users_page() {
    if ( ! current_user_can( 'list_users' ) ) wp_die( 'You do not have sufficient permissions to access this page.');
    echo '<div class="wrap">';
      // Header
      echo '<h1>Export users to a CSV file</h1>';
      echo '<p>Usage: set options than click to Export button to save csv.</p>';
      // No user found
      if ( isset( $_GET['error'] ) ) echo '<div class="updated"><p><strong> No user found. </strong></p></div>';
      if ( isset( $_GET['error'] ) && ($_GET['error'] == 'month') ) echo '<div class="updated"><p><strong> The start date must be earlier than end date. </strong></p></div>';
      echo '<form method="post" action="" enctype="multipart/form-data">';
        echo '<h3>Filter selection</h3>';
        wp_nonce_field( 'export-users-page_export', '_wpnonce-export-users-page_export' ); 
        // Role select
        echo '<strong>Role: </strong>';
        echo '<select name="role" id="u2c_users_role">';
          echo '<option value=""> Every Role </option>';
          global $wp_roles;
          foreach ( $wp_roles->role_names as $role => $name ) {
            echo "\n\t<option value='" . esc_attr( $role ) . "'>$name</option>";
          }
        echo '</select>';
        // Date range select
        echo '<br /><strong>Date range: </strong>';
        // Start month
        echo '<select name="start_month" id="u2c_users_start_month">';
          echo '<option value="0">Start month</option>';
          $this->export_date_options();
        echo '</select>';
        // End month
        echo '<select name="end_month" id="u2c_users_end_month">';
          echo '<option value="0">End Month</option>';
          $this->export_date_options();
        echo '</select>';
        echo '<h3>Select fields to export</h3>';
        echo $this->export_fields();
        // Submit
        echo '<input type="hidden" name="_wp_http_referer" value="'. $_SERVER['REQUEST_URI'] .'" />';
        echo '<br /><input type="submit" class="button-primary" value="Export" />';
      echo '</form>';
    echo '</div>';
  }

  public function exclude_data() {
    $exclude = array( 'user_pass', 'user_activation_key' );

    return $exclude;
  }

  public function pre_user_query( $user_search ) {
    global $wpdb;

    $where = '';

    if( strtotime($_POST['start_month']) >= strtotime('+1 month', strtotime($_POST['end_month'])) ){
      $referer = add_query_arg( 'error', 'month', wp_get_referer() );
      wp_redirect( $referer );
      exit;
    }

    if ( ! empty( $_POST['start_month'] ) )
      $where .= $wpdb->prepare( " AND $wpdb->users.user_registered >= %s", date( 'Y-m-d', strtotime( $_POST['start_month'] ) ) );

    if ( ! empty( $_POST['end_month'] ) )
      $where .= $wpdb->prepare( " AND $wpdb->users.user_registered < %s", date( 'Y-m-d', strtotime( '+1 month', strtotime( $_POST['end_month'] ) ) ) );

    if ( ! empty( $where ) )
      $user_search->query_where = str_replace( 'WHERE 1=1', "WHERE 1=1$where", $user_search->query_where );

    return $user_search;
  }

  /** Export months **/
  private function export_date_options() {
    global $wpdb, $wp_locale;

    $months = $wpdb->get_results( "
      SELECT DISTINCT YEAR( user_registered ) AS year, MONTH( user_registered ) AS month
      FROM $wpdb->users
      ORDER BY user_registered DESC
    " );

    $month_count = count( $months );
    if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
      return;

    foreach ( $months as $date ) {
      if ( 0 == $date->year )
        continue;

      $month = zeroise( $date->month, 2 );
      echo '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
    }
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
