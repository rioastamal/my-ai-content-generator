<?php
/**
 * Plugin Name: My AI Content Generator
 * Description: An AI content generator plugin powered by Amazon Bedrock
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hooks into admin_menu to add our AI content generator settings page: 
// AWS credentials page and foundation models selection page
add_action('admin_menu', 'my_ai_settings_menu');

/**
 * Function to render AWS credentials page.
 *
 * @return void
 */
function my_ai_credentials_page() {
    // Check user permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    my_ai_save_credentials();

    // Get the current values of the access key id from the database
    // Option name is 'my_ai_credentials' and has two keys 'access_key_id' and 'secret_access_key'
    // (We never display the secret access key to the user)
    $credentials = get_option( 'my_ai_credentials', ['access_key_id' => ''] );
    $access_key_id = $credentials['access_key_id'];

    // If query string updated=true exists then display a success message
    $success_message = false;
    if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
        $success_message = true;
    }

    require __DIR__ . '/views/aws-credentials-page.php';
}

/**
 * Function to save AWS credentials to the database.
 * 
 * @return void
 */
function my_ai_save_credentials() {
    // Get the submitted values from the form
    $option_page = $_POST['option_page'] ?? '';
    $access_key_id = $_POST['access_key_id'] ?? '';
    $secret_access_key = $_POST['secret_access_key'] ?? '';

    // Only proceed if option_page is my-ai-credentials-page
    if ( $option_page !== 'my-ai-credentials-page' ) {
        return;
    }

    // Save the credentials to the database
    update_option('my_ai_credentials', [
        'access_key_id' => $access_key_id,
        'secret_access_key' => $secret_access_key,
    ]);
}

/**
 * Function to add our AI content generator settings page to the admin menu.
 * 
 * @return void
 */
function my_ai_settings_menu() {
    // Foundation model selection page
    add_menu_page(
        'Foundation models', // page title
        'My AI Content Generator', // menu title
        'manage_options', // capability
        'my-ai-models-page', // menu slug
        // callback function to render the page content
        function() {
            return ''; // Temporary output, will be updated later
        }, 
        'dashicons-admin-generic', 
    );

    // AWS credentials page
    add_submenu_page(
        'my-ai-models-page', // parent menu slug
        'AWS credentials', // page title
        'AWS credentials', // menu title
        'manage_options', // capability
        'my-ai-credentials-page', // menu slug
        // callback function to render the page content
        'my_ai_credentials_page',
    );
}