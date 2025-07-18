<?php
/*
 * Plugin Name: Custom Profile Picture
 * Description: Allows users to upload their own profile pictures in the profile section.
 * Plugin URI: https://ifatwp.wordpress.com/2025/05/07/custom-profile-picture/
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: ifatwp
 * Author URI:  https://ifatwp.wordpress.com/
 * Text Domain: custom-profile-picture
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'CPP_PLUGIN_VERSION', '1.0.0' );
define('CPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPP_PLUGIN_FILE', __FILE__);
define('CPP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CPP_PLUGIN_DIR . 'includes/form-handler.php';
require_once CPP_PLUGIN_DIR . 'includes/profile-field.php';
require_once CPP_PLUGIN_DIR . 'includes/save-profile-picture.php';
require_once CPP_PLUGIN_DIR . 'includes/avatar-replacement.php';
require_once CPP_PLUGIN_DIR . 'includes/image-cropping.php';

// Modify the avatar URL to use the custom profile picture
add_filter('get_avatar_url', 'cpp_custom_avatar_url', 10, 2);

function cpp_custom_avatar_url($url, $user_id) {
    // Get the custom profile picture URL from the user meta
    $profile_picture = get_user_meta($user_id, 'cpp_profile_picture', true);

    // If a custom profile picture exists, return that URL
    if ($profile_picture) {
        return esc_url($profile_picture);
    }

    // If no custom profile picture, return the default URL (as provided by WordPress)
    return $url;
}


// Modify avatar data using the pre_get_avatar_data filter
add_filter('pre_get_avatar_data', 'cpp_custom_avatar_data', 10, 2);

function cpp_custom_avatar_data($avatar_data, $args) {

    if (!empty($args->ID)) {
        $user_id = $args->ID;
        
        // Get the custom profile picture URL from user meta
        $profile_picture = get_user_meta($user_id, 'cpp_profile_picture', true);

        // If a custom profile picture exists, modify the avatar data
        if ($profile_picture) {
            // Modify the URL to use the custom profile picture
            $avatar_data['url'] = esc_url($profile_picture);
            // Optionally modify other avatar data (size, etc.)
            $avatar_data['found'] = true; // Indicate that an avatar was found
        }
    }

    return $avatar_data;
}