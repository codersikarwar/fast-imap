<?php
/**
 * Plugin Name: Fast IMAP
 * Description: Fast IMAP is a free, open-source plugin that lists emails directly within WordPress. Easily add and manage multiple IMAP server accounts.
 * Version: 1.0.0
 * Author: Bhoopendra Sikarwar
 * Author URI: https://github.com/codersikarwar
 * Plugin URI: https://github.com/codersikarwar/fast-imap-wordpress/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: fast-imap
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

include plugin_dir_path( __FILE__ ) . 'includes/functions.php';


$key_from_options = get_option( 'fast_imap_encryption_key' );
if($key_from_options){
    define('FAST_IMAP_ENCRYPTION_KEY',$key_from_options);
}

// WP Activation Hook
register_activation_hook( __FILE__, 'fast_imap_activate' );

// WP Deactivation Hook
register_deactivation_hook( __FILE__, 'fast_imap_deactivate' );

// Add Admin Menu
add_action( 'admin_menu', 'fast_imap_menu' );

// Hook to run when admin page loads
add_action( 'admin_init', 'fast_imap_handle_credentials_submission' );

add_action('admin_init', 'fast_imap_handle_deletion');