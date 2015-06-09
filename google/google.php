<?php
/**
 * Google Login add-on
 *
 * @see http://enarion.net/programming/php/google-client-api/google-client-api-php/
 * @see https://developers.google.com/identity/protocols/OAuth2#webserver
 *
 * @see https://console.developers.google.com/project/148881152670/apiui/credential?authuser=0
 */


/**
 * Main class  to login width facebook
 * Class ST_Uer_Facebook_Login
 *
 */
class  ST_Uer_Google_Login {

    protected $settings =  array();
    protected $login_url  = '';
    protected $end_point = 'https://accounts.google.com/o/oauth2/auth';

    function __construct(){

        $resp= wp_remote_post( 'https://www.googleapis.com/oauth2/v3/token', array(
            'body'  => array(
                'code'      => '4/NYAcf0sQqa34npP9xm-_0CvmDqPGg7CsSlmgyNI_aCY.ctVMBzj7tMQbBrG_bnfDxpIi-dKrmwI',
                'client_id' => '148881152670.apps.googleusercontent.com',
                'client_secret' => 'B05l-TVl4w_mATTzTqfZiXA2',
                'redirect_uri' => 'http://localhost/wp-plugins/?google_return=1',
                'grant_type' => 'authorization_code',
            ),
        )  );

        $body = wp_remote_retrieve_body( $resp );
        var_dump( $body );


        if( isset( $_REQUEST['google_return'] ) ){
            $this->parse_respond();
        }


        /*
         https://accounts.google.com/o/oauth2/auth?
 scope=email profile&
 state=security_token=138r5719ru3e1&url=https://oa2cb.example.com/myHome&
 redirect_uri=https://oauth2-login-demo.appspot.com/code&,
 response_type=code&
 client_id=812741506391.apps.googleusercontent.com&
 approval_prompt=force
         */

        $args = array(
            'scope' => 'email profile',
            'approval_prompt' => 'force',
            'login_hint' => 'email',
            'redirect_uri' => 'http://localhost/wp-plugins/?google_return=1',
            'response_type' => 'code',
            'client_id' => '148881152670.apps.googleusercontent.com',
        );

        $this->login_url =  add_query_arg( $args , $this->end_point );

        add_action('st_user_after_login_form', array($this, 'button'));

    }


    /**
     * @TODO: Display login button
     * @since 1.0
     */
    function button( $in_modal = false ){
        ?>
        <a href="<?php echo $this->login_url; ?>" class="st-btn full-width fb-google-button <?php echo $in_modal ? 'fb-in-modal' : ''; ?>"><?php echo esc_attr__('Login width Google','st-user-fb-add-on') ?></a>
    <?php
    }

    function parse_respond(){

        /**
         * array(2) {
        ["google_return"]=&gt;
        string(1) "1"
         * error =>'',
        ["code"]=&gt;
        string(77) "4/NYAcf0sQqa34npP9xm-_0CvmDqPGg7CsSlmgyNI_aCY.ctVMBzj7tMQbBrG_bnfDxpIi-dKrmwI"
        }
         */



        echo var_dump( $_REQUEST );
        die();
    }

}



/**
 * Make sure Main Plugin is actived
 */
if( in_array( 'ST-User/st-user.php', (array) get_option( 'active_plugins', array() ) ) ){

    /**
     * Run the script
     *
     * @since 1.0.0
     */
    function run_st_user_google_add_on() {
        new ST_Uer_Google_Login();
    }
    add_action('st_user_init', 'run_st_user_google_add_on' );
}else{
    //add_action('init', 'run_st_user_fb_add_on');
}