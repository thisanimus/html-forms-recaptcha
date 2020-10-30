<?php
/**
* Plugin name: HTML Forms Recaptcha
* Description: Enable Recaptcha validation for ibericode's HTML5 Forms Plugin.
* Version: 1.0.1
* Author: Andrew Hale
* 
*/


// Make sure ibericode's HTML5 Forms is installed and active.
add_action( 'admin_init', 'child_plugin_has_parent_plugin' );
function child_plugin_has_parent_plugin() {
    if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'html-forms/html-forms.php' ) ) {
        add_action( 'admin_notices', 'child_plugin_notice' );

        deactivate_plugins( plugin_basename( __FILE__ ) ); 

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}

function child_plugin_notice(){
    ?><div class="error"><p>HTML Forms Recaptcha requires the ibericode's HTML5 Forms to be installed and active.</p></div><?php
}



// Add a link to the settings page
function plugin_add_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=recaptcha-setting-admin">' . __( 'Settings' ) . '</a>';
    array_push( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'plugin_add_settings_link' );



// Add Google's recaptcha code to the head
function recaptcha_init() {
    $keys = get_option('recaptcha_keys', array() );

    echo '<script async defer src="https://www.google.com/recaptcha/api.js?render='.$keys['site_key'].'"></script>
        <script>
            grecaptcha.ready(function() {
                grecaptcha.execute("'.$keys['site_key'].'", {action:"validate_captcha"})
                    .then(function(token) {
                        var recaptchaElements = document.getElementsByName("recaptcha");
                        for (var i = 0; i < recaptchaElements.length; i++) {
                            recaptchaElements[i].value = token;
                        }
                });
            });
        </script>';
}
add_action( 'wp_head', 'recaptcha_init' );


// Hook into ibericode's HTML Forms form validation
add_filter( 'hf_validate_form', function( $error_code, $form, $data ) {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recaptcha_response'])) {

        $keys = get_option('recaptcha_keys', array() );

        // Build POST request:
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_secret = $keys['secret_key'];
        $recaptcha_response = $_POST['recaptcha_response'];

        // Make and decode POST request:
        $recaptcha = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
        $recaptcha = json_decode($recaptcha);

        // Take action based on the score returned:
        if ($recaptcha->score >= 0.5) {
            // Verified - send email
        } else {
            $error_code = 'recaptcha_error'; 
        }

    }

	return $error_code;
}, 10, 3 );

// Register error message for our custom error code
add_filter( 'hf_form_message_recaptcha_error', function( $message ) {
    return 'Form validation failed because of spam filtering.';
});

// Add the recatchca to forms
add_filter( 'hf_form_markup', function( $markup ) {
	$markup .= '<input type="hidden" name="recaptcha" value="validate_captcha">';
	return $markup;
});



// Register Settings
class RecaptchaSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'HTML Forms Recaptcha', 
            'HTML Forms Recaptcha', 
            'manage_options', 
            'recaptcha-setting-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'recaptcha_keys' );
        ?>
        <div class="wrap">
            <h1>HTML Form Recaptcha</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'recaptcha_option_group' );
                do_settings_sections( 'recaptcha-setting-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'recaptcha_option_group', // Option group
            'recaptcha_keys', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'recaptcha_settings', // ID
            'Recaptcha Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'recaptcha-setting-admin' // Page
        );  

        add_settings_field(
            'site_key', // ID
            'Site Key', // Title 
            array( $this, 'site_key_callback' ), // Callback
            'recaptcha-setting-admin', // Page
            'recaptcha_settings' // Section           
        );      

        add_settings_field(
            'secret_key', 
            'Secret Key', 
            array( $this, 'secret_key_callback' ), 
            'recaptcha-setting-admin', 
            'recaptcha_settings'
        );      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['site_key'] ) )
            $new_input['site_key'] = sanitize_text_field( $input['site_key'] );

        if( isset( $input['secret_key'] ) )
            $new_input['secret_key'] = sanitize_text_field( $input['secret_key'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function site_key_callback()
    {
        printf(
            '<input type="text" id="site_key" name="recaptcha_keys[site_key]" value="%s" />',
            isset( $this->options['site_key'] ) ? esc_attr( $this->options['site_key']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function secret_key_callback()
    {
        printf(
            '<input type="text" id="secret_key" name="recaptcha_keys[secret_key]" value="%s" />',
            isset( $this->options['secret_key'] ) ? esc_attr( $this->options['secret_key']) : ''
        );
    }
}

if( is_admin() )
    $my_settings_page = new RecaptchaSettingsPage();