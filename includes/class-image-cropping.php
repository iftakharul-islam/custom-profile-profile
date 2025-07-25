<?php
namespace Ifatwp\CustomProfilePicture;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Cropping Class
 */
class Image_Cropping {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_cropping_scripts'));
        add_action('admin_footer-profile.php', array($this, 'add_cropping_modal'));
        add_action('admin_footer-user-edit.php', array($this, 'add_cropping_modal'));
        add_action('wp_ajax_custprofpic_save_cropped_image', array($this, 'handle_cropped_image'));
    }
    
    /**
     * Add scripts and styles for image cropping
     */
    public function enqueue_cropping_scripts($hook) {
        if ($hook == 'profile.php' || $hook == 'user-edit.php') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('custprofpic-cropper', CUSTPROFPIC_PLUGIN_URL . 'assets/js/cropper.min.js', array(),CUSTPROFPIC_PLUGIN_VERSION, true);
            wp_enqueue_style('custprofpic-cropper-style', CUSTPROFPIC_PLUGIN_URL . 'assets/css/cropper.min.css', array(),CUSTPROFPIC_PLUGIN_VERSION);
            
            wp_enqueue_script('custprofpic-custom-cropping', CUSTPROFPIC_PLUGIN_URL . 'assets/js/image-cropping.js', array('jquery', 'custprofpic-cropper'),CUSTPROFPIC_PLUGIN_VERSION, true);
            wp_enqueue_style('custprofpic-custom-style', CUSTPROFPIC_PLUGIN_URL . 'assets/css/image-cropping.css', array(), CUSTPROFPIC_PLUGIN_VERSION);
            
            // Localize script with nonce
            wp_localize_script('custprofpic-custom-cropping', 'custprofpic_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('custprofpic_crop_nonce')
            ));
        }
    }
    
    /**
     * Add cropping modal to profile page
     */
    public function add_cropping_modal() {
        ?>
        <div id="custprofpic-cropping-modal" style="display: none;">
            <div class="custprofpic-modal-content">
                <div class="custprofpic-modal-header">
                    <h3><?php esc_html_e('Crop Profile Picture', 'custom-profile-picture'); ?></h3>
                    <span class="custprofpic-close-modal">&times;</span>
                </div>
                <div class="custprofpic-modal-body">
                    <div class="custprofpic-image-container">
                        <img id="custprofpic-crop-image" src="" alt="Crop Preview">
                    </div>
                    <div class="custprofpic-crop-controls">
                        <button type="button" class="button button-primary" id="custprofpic-crop-save">
                            <?php esc_html_e('Save Crop', 'custom-profile-picture'); ?>
                        </button>
                        <button type="button" class="button" id="custprofpic-crop-cancel">
                            <?php esc_html_e('Cancel', 'custom-profile-picture'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle cropped image upload
     */
    public function handle_cropped_image() {
        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'custprofpic_crop_nonce')) {
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_POST['image']) || !isset($_POST['user_id'])) {
            wp_send_json_error('Invalid request');
        }

        // Sanitize and validate input
        $image_data = sanitize_textarea_field(wp_unslash($_POST['image']));
        $user_id = intval($_POST['user_id']);
        
        // Validate user_id
        if (!$user_id || !get_user_by('ID', $user_id)) {
            wp_send_json_error('Invalid user ID');
        }
        
        // Validate image data format
        if (!preg_match('/^data:image\/(jpeg|png|gif);base64,/', $image_data)) {
            wp_send_json_error('Invalid image format');
        }
        
        // Remove header from base64 string
        $image_array = explode(';', $image_data);
        $image_array = explode(',', $image_array[1]);
        $image_data = base64_decode($image_array[1]);
        
        // Validate decoded data
        if (!$image_data) {
            wp_send_json_error('Invalid image data');
        }
        
        $upload_dir = wp_upload_dir();
        $filename = 'profile-picture-' . $user_id . '-' . time() . '.png';
        $upload_path = $upload_dir['path'] . '/' . $filename;
        
        // Ensure upload directory is writable
        if (!wp_mkdir_p($upload_dir['path'])) {
            wp_send_json_error('Cannot create upload directory');
        }
        
        $result = file_put_contents($upload_path, $image_data);
        if ($result === false) {
            wp_send_json_error('Failed to save image file');
        }
        
        $attachment = array(
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload_path);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error('Failed to save image');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        $image_url = wp_get_attachment_url($attachment_id);
        update_user_meta($user_id, 'custprofpic_profile_picture', esc_url($image_url));
        
        wp_send_json_success(array('url' => $image_url));
    }
} 