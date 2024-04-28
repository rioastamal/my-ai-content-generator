<?php
/**
 * Plugin Name: My AI Content Generator
 * Description: An AI content generator plugin powered by Amazon Bedrock
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load the AWS SDK for PHP from Composer autoload
require __DIR__ . '/vendor/autoload.php';
use Aws\Bedrock\BedrockClient;
use Aws\BedrockRuntime\BedrockRuntimeClient;

// Get current AWS credentials from the database
$my_ai_credentials = get_option('my_ai_credentials', ['access_key_id' => '', 'secret_access_key' => '']);

// Initialize BedrockClient and BedrockRuntimeClient, default to us-east-1
$bedrock = new BedrockClient([
    'credentials' => [
        'key' => $my_ai_credentials['access_key_id'],
        'secret' => $my_ai_credentials['secret_access_key'],
    ],
    'region' => 'us-east-1',
]);

$bedrock_runtime = new BedrockRuntimeClient([
    'credentials' => [
        'key' => $my_ai_credentials['access_key_id'],
        'secret' => $my_ai_credentials['secret_access_key'],
    ],
    'region' => 'us-east-1',
]);

/**
 * Function to return instance of BedrockClient.
 */
function my_ai_bedrock_client() {
    global $bedrock;
    return $bedrock;
}

/**
 * Function to return instance of BedrockRuntimeClient.
 */
function my_ai_bedrock_runtime_client() {
    global $bedrock_runtime;
    return $bedrock_runtime;
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
 * Function to get list of foundation models from the Bedrock and caches the result to database.
 * 
 * The database option_name should be 'my_ai_foundation_models'. It has two keys:
 * 1. 'foundation_models' - an array of foundation models
 * 2. 'last_updated' - the timestamp of the last update
 * 
 * When function is called it check the cache expiration (1 day). If it expires then
 * call the Bedrock API and update the cache.
 * 
 * Response is associative arrays with 2 keys:
 * - 'error' - default to null
 * - 'items' - The list of foundation models
 *
 * @param BedrockClient $client
 * @return array - list of foundation models
 */
function my_ai_get_foundation_models($client) {
    $foundation_models = get_option('my_ai_foundation_models', ['foundation_models' => [], 'last_updated' => 0]);

    // Check if the cache is expired (1 day)
    $now = time();
    $cache_expiration = 86400; // 1 day
    if ( $now - $foundation_models['last_updated'] > $cache_expiration ) {
        try {
            // Call the Bedrock API to get the list of foundation models
            $response = $client->listFoundationModels();
        } catch (Exception $e) {
            // If there is an error then return an empty array
            return ['error' => $e->getMessage(), 'items' => []];
        }

        // Update the cache
        update_option('my_ai_foundation_models', [
            'foundation_models' => $response['modelSummaries'],
            'last_updated' => $now,
        ]);

        // Return the list of foundation models
        return $response['modelSummaries'];
    }

    // Return the cached list of foundation models
    return $foundation_models['foundation_models'];
}

/**
 * Function to render foundation model selection page.
 * 
 * @return void
 */
function my_ai_models_page() {
    // Check user permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    my_ai_save_selected_foundation_models();

    $bedrock = my_ai_bedrock_client();

    // Get current values of the foundation models from the database
    $foundation_models = my_ai_get_foundation_models($bedrock);

    // Get current selected foundation models from the database
    $selected_foundation_models = get_option('my_ai_selected_foundation_models', []);

    // If query string updated=true exists then display a success message
    $success_message = false;
    if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
        $success_message = true;
    }

    // Link to AWS credentials page
    $aws_credentials_link = admin_url('admin.php?page=my-ai-credentials-page');

    require __DIR__ . '/views/foundation-models-page.php';
}

/**
 * Function to save selected foundation models to the database.
 * 
 * @return void
 */
function my_ai_save_selected_foundation_models() {
    // Get the submitted values from the form
    $option_page = $_POST['option_page'] ?? '';
    $selected_foundation_models = $_POST['foundation_models'] ?? [];

    // Only proceed if option_page is my-ai-models-page
    if ( $option_page !== 'my-ai-models-page' ) {
        return;
    }

    // Save the selected foundation models to the database
    update_option('my_ai_selected_foundation_models', $selected_foundation_models);
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
        'my_ai_models_page',
        'dashicons-admin-generic', // menu icon
    );

    // AWS credentials page
    add_submenu_page(
        'my-ai-models-page', // parent menu slug
        'Setup AWS Credentials', // page title
        'AWS Credentials', // menu title
        'manage_options', // capability
        'my-ai-credentials-page', // menu slug
        // callback function to render the page content
        'my_ai_credentials_page',
    );
}

require __DIR__ . '/my-ai-rest-api.php';
add_action('enqueue_block_editor_assets', function() {
    // Script dependencies
    $dependencies = ['react', 'wp-blocks', 'wp-editor'];

    // URL to js/my-ai-sidebar.js
    $script_url = plugin_dir_url(__FILE__) . 'js/my-ai-sidebar.js';
    
    // Enqueue script and use version 1.0.0 as a cache buster
    wp_enqueue_script('my-ai-sidebar', $script_url, $dependencies, '1.0.0', true);

    // Get current selected foundation models from the database
    $selected_foundation_models = get_option('my_ai_selected_foundation_models', []);

    // Add inline script so wp-ai-sidebar.js can set the selected foundation models
    $javascript_line = sprintf('var myAiSelectedFoundationModels = %s;', json_encode($selected_foundation_models));
    wp_add_inline_script('my-ai-sidebar', $javascript_line, 'before');
});
