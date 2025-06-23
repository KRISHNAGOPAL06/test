<?php
/**
 * Plugin Name: Simple Product Adder for Admins and Vendors
 * Description: Allows vendors (via Dokan) and admins to add products with name, price, image, and description.
 * Version: 1.0
 * Author: Krishna Gopal Halder
 */

if (!defined('ABSPATH')) exit;

// Enqueue media uploader
add_action('admin_enqueue_scripts', 'spa_enqueue_media_uploader');
add_action('wp_enqueue_scripts', 'spa_enqueue_media_uploader');

function spa_enqueue_media_uploader() {
    if (is_user_logged_in()) {
        wp_enqueue_media();
    }
}

// Add Admin Menu
add_action('admin_menu', function () {
    add_submenu_page('woocommerce', 'Simple Product Adder', 'Product Adder', 'manage_woocommerce', 'simple-product-adder', 'spa_admin_page');
});

// Admin Page Callback
function spa_admin_page() {
    echo '<div class="wrap"><h1>Add New Product</h1>';
    echo do_shortcode('[simple_product_adder]');
    echo '</div>';
}

// Shortcode for vendors and admin
add_shortcode('simple_product_adder', 'spa_form_shortcode');
function spa_form_shortcode() {
    if (!is_user_logged_in()) return '<p>Please login to add a product.</p>';

    $user = wp_get_current_user();

    // Only allow vendors or admins
    if (!in_array('administrator', $user->roles) && !in_array('seller', $user->roles)) {
        return '<p>You do not have permission to add products.</p>';
    }

    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data">
        <p><label>Product Name: <input type="text" name="spa_name" required></label></p>
        <p><label>Price (₹): <input type="number" step="0.01" name="spa_price" required></label></p>
        <p><label>Description:<br><textarea name="spa_description" required></textarea></label></p>
        <p><label>Product Image: <input type="file" name="spa_image" accept="image/*" required></label></p>
        <p><input type="submit" name="spa_submit" value="Add Product" class="button button-primary"></p>
    </form>
    <?php
    spa_handle_form_submission();
    return ob_get_clean();
}

// Handle form submission
function spa_handle_form_submission() {
    if (!isset($_POST['spa_submit']) || !is_user_logged_in()) return;

    $name = sanitize_text_field($_POST['spa_name']);
    $price = floatval($_POST['spa_price']);
    $description = wp_kses_post($_POST['spa_description']);
    $user_id = get_current_user_id();

    // Insert product
    $product_id = wp_insert_post([
        'post_title'    => $name,
        'post_content'  => $description,
        'post_status'   => 'publish',
        'post_type'     => 'product',
        'post_author'   => $user_id,
    ]);

    if (is_wp_error($product_id)) {
        echo '<p style="color:red;">Error creating product.</p>';
        return;
    }

    // Set product type
    wp_set_object_terms($product_id, 'simple', 'product_type');

    // Set price
    update_post_meta($product_id, '_regular_price', $price);
    update_post_meta($product_id, '_price', $price);

    // Handle image upload
    if (!empty($_FILES['spa_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attachment_id = media_handle_upload('spa_image', $product_id);
        if (is_wp_error($attachment_id)) {
            echo '<p style="color:red;">Image upload failed.</p>';
        } else {
            set_post_thumbnail($product_id, $attachment_id);
        }
    }

    echo '<p style="color:green;">Product added successfully!</p>';
}
