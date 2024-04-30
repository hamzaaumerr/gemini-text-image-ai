<?php
/*
Plugin Name: Gemini Image AI
Description: Text and Image Input Gemini AI.
Version: 1.0
Author: Hamza Umer
Author URI: http://example.com
License: GPL2
*/

// Add a menu item to the dashboard for the plugin settings
function gemini_image_ai_menu() {
    add_menu_page(
        'Gemini Image AI Settings',
        'Gemini Image AI',
        'manage_options',
        'gemini-image-ai-settings',
        'gemini_image_ai_settings_page'
    );
}
add_action('admin_menu', 'gemini_image_ai_menu');

// Callback function for rendering the plugin settings page
function gemini_image_ai_settings_page() {
    ?>
    <div class="wrap"> 
        <h2>Gemini Image AI Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('gemini_image_ai_options'); ?>
            <?php do_settings_sections('gemini-image-ai-settings'); ?>
            <input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>">
        </form>
    </div>
    <?php
}

// Register and define the settings
function gemini_image_ai_register_settings() {
    register_setting('gemini_image_ai_options', 'gemini_image_ai_api_key');
    add_settings_section(
        'gemini_image_ai_section',
        '',
        '',
        'gemini-image-ai-settings'
    );
    add_settings_field(
        'gemini_image_ai_api_key',
        'Gemini Image AI API Key',
        'gemini_image_ai_api_key_callback',
        'gemini-image-ai-settings',
        'gemini_image_ai_section'
    );
}
add_action('admin_init', 'gemini_image_ai_register_settings');

// Callback function for rendering the API key field
function gemini_image_ai_api_key_callback() {
    $api_key = get_option('gemini_image_ai_api_key');
    echo '<input type="password" id="gemini_image_ai_api_key" name="gemini_image_ai_api_key" value="' . esc_attr($api_key) . '" class="form-control" />';
}

// Define function to generate content using Gemini Image AI
function gemini_generate_custom_content($atts) {
    // Extract attributes
    $atts = shortcode_atts(
        array(
            'text' => '',
            'image' => ''
        ),
        $atts
    );

    // Get Gemini API key from settings
    $gemini_api_key = get_option('gemini_image_ai_api_key');

    // Check if API key is empty
    if (empty($gemini_api_key)) {
        return "Error: Gemini Image AI API key is not set. Please set it in the plugin settings.";
    }

    // Get user input text and uploaded image
    $user_text = sanitize_text_field($atts['text']);
    $user_image = isset($atts['image']) ? $atts['image'] : '';

    // Check if image is uploaded
    if (!empty($user_image['tmp_name'])) {
        // Read image file
        $image_data = base64_encode(file_get_contents($user_image['tmp_name']));
    } else {
        return "Error: Please upload an image.";
    }

    // Define the request body
    $request_body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array(
                        'text' => $user_text
                    ),
                    array(
                        'inline_data' => array(
                            'mime_type' => 'image/jpeg',
                            'data' => $image_data
                        )
                    )
                )
            )
        )
    );

    // Encode request body to JSON
    $request_json = json_encode($request_body);

    // Set API endpoint URL
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent?key=' . $gemini_api_key;

    // Set headers
    $headers = array(
        'Content-Type' => 'application/json'
    );

    // Make POST request using wp_remote_post()
    $response = wp_remote_post($api_url, array(
        'headers' => $headers,
        'body' => $request_json,
        'timeout' => 30
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    } else {
        // Get the response body
        $body = wp_remote_retrieve_body($response);
        // Decode the JSON response
        $data = json_decode($body, true);
        // Check if data is valid
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            // Extract and display the text
            $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
            return $generated_text;
        } else {
            return "Error: Unable to retrieve generated text from API response.";
        }
    }
}

function enqueue_image_bootstrap() {
    // Enqueue Bootstrap CSS
    wp_enqueue_style('bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css', array(), '4.5.2');
}
add_action('wp_enqueue_scripts', 'enqueue_image_bootstrap');

function enqueue_image_jquery() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'enqueue_image_jquery');

// Register shortcode with frontend fields
function gemini_custom_content_shortcode($atts) {
    // Output the form with text and image input fields
    ob_start();
    ?>
    <div class="container">
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="gemini_text">Enter Text:</label>
                <input type="text" id="gemini_text" name="gemini_text" value="" class="form-control">
            </div>
            <div class="form-group">
                <label for="gemini_image">Upload Image:</label>
                <input type="file" id="gemini_image" name="gemini_image" class="form-control-file">
            </div>
            <input type="submit" name="gemini_submit" value="Generate Content" class="btn btn-primary">
        </form>
    </div>
    <?php
    $form = ob_get_clean();

    // Check if form is submitted
    if (isset($_POST['gemini_submit'])) {
        // Generate content with user input
        $generated_content = gemini_generate_custom_content(array(
            'text' => $_POST['gemini_text'],
            'image' => $_FILES['gemini_image']
        ));

        // Display generated content
        return $generated_content;
    }

    return $form;
}
add_shortcode('gemini_custom_content', 'gemini_custom_content_shortcode');
?>