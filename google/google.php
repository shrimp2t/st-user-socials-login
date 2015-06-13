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
 * @version 1.0.0
 */
class ST_Uer_Google_Login {

    /**
     * String URL login
     * @var string
     */
    protected $login_url    = '';

    /**
     *
     * @var string
     */
    protected $end_point    = 'https://accounts.google.com/o/oauth2/auth';

    /**
     * Settings keys for google api
     *
     * @var array
     */
    protected $settings = array(
        'st_user_google_client_id'     => 'client_id',
        'st_user_google_redirect_uri'  => 'redirect_uri',
        'st_user_google_client_secret' => 'client_secret'
    );

    /**
     *Args for google API
     *
     * @var null
     */
    protected $api_args  = null;

    function __construct(){

        if ( is_admin() ) {
            add_filter('st_settings_keys', array($this, 'admin_setting_keys') );
            add_action('st_user_settings_table', array($this, 'admin_settings'));
        }

        $this->get_api_keys();

        $this->handle_response();

        $args = array(
            'scope'             => 'email profile',
            'approval_prompt'   => 'force',
            'login_hint'        => 'email',
            //'redirect_uri'      => 'http://localhost/wp-plugins/?google_return=1',
            'response_type'     => 'code',
            'access_type'       => 'offline',
            //'client_id'         => '148881152670.apps.googleusercontent.com',
        );
        $args_more = $this->get_api_keys();
        unset( $args_more['client_secret'] );
        $args =  array_merge( $args, $args_more );


        $this->login_url =  add_query_arg( $args, $this->end_point );
        add_action( 'st_user_after_login_form', array( $this, 'button') );
    }

    /**
     * Set Api key for google login
     *
     * @since 1.0.0
     */
    function get_api_keys(){
        if ( ! $this->api_args ){
            foreach ( $this->settings as $k => $v ){
                $this->api_args[ $v ] =  get_option( $k );
            }
        }

        return $this->api_args;
    }

    /**
     *
     */
    function handle_response(){

        if( isset( $_GET['google_return'] ) && $_GET['google_return'] == 1 ){
            $code = $_GET['code'];
            $resp= wp_remote_post( 'https://www.googleapis.com/oauth2/v3/token', array(
                'body'  => array_merge(
                    array(
                        'code'          => $code,
                        //'client_id'     => '148881152670.apps.googleusercontent.com',
                        //'client_secret' => 'B05l-TVl4w_mATTzTqfZiXA2',
                        //'redirect_uri'  => 'http://localhost/wp-plugins/?google_return=1',
                        'grant_type'    => 'authorization_code',
                    ),
                    $this->get_api_keys()
                ),
            )  );

            $body = (array) json_decode( wp_remote_retrieve_body( $resp ) );

            if( isset( $body['access_token'] ) ){
                $resp =  wp_remote_get( 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . esc_attr( $body['access_token'] ) );
                $me = (array) json_decode( wp_remote_retrieve_body( $resp ) );
                if( isset( $me[ 'user_id' ] ) ){
                    $user_id = $this->maybe_create_update_user( $me );
                    if ( $user_id ) {
                        if ( st_user_add_ond_login( $user_id ) ) {
                            ob_start();
                            ob_end_clean();
                            $url = apply_filters( 'login_redirect', get_option( 'st_user_login_redirect_url' ) );
                            if ( $url == '' ){
                                $url = get_permalink();
                            }
                            wp_redirect( $url );
                            die();
                        }
                    }
                }
            }

        }

    }

    /**
     * Maybe we need create new account if not exits
     *
     * @see get_me
     * @since 1.0.0
     * @param  array $me
     * @return init|bool
     */
    function maybe_create_update_user( $me ){
        if( ! $me ) {
            return false;
        }
        $u = false;
        // try fo find with facebook ID
        $us =  get_users( array (
            'meta_key'     => 'google_user_id',
            'meta_value'   => $me['user_id'],
            'meta_compare' => '=',
            'number'       => 1,
        ) );
        if(  $us ){
            $u = $us[0];
        }
        unset( $us );
        if( $u ) {
            // update this user email if they provide
            if ( isset( $me['email'] ) ) {
                if (!get_user_by('email', $me['email'])) {
                    wp_update_user(array(
                        'user_email' => $me['email'],
                        'ID'         => $u->ID
                    ));
                }
            }
            return $u->ID;
        }


        /**
         * Try fo find user if logged in/registered before
         * check if email is not registered
         */
        if( isset(  $me['email'] ) ) { // try to find with email
            $u = get_user_by('email', $me['email'] );
        }

        if( ! $u ) { // this email not register yet
            // register new account with this email here.
            $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
            $name =explode( '@', $me['email'] );
            $name = $name[0];

            $rand = '';
            $new_username = $name.$rand;
            $user_id = username_exists( $new_username );

            // While user exists do until wrong
            while ( $user_id ) {
                $rand = (int) $rand + 1;
                $new_username = $name.'_g'.$rand;
                $user_id = username_exists( $new_username );
            }

            $nr = wp_create_user( $new_username , $random_password, $me['email'] );
            if( ! is_wp_error( $nr ) ) {
                $this->update_user( $nr,  $me );
                wp_new_user_notification( $nr, $random_password );
                return  $nr;
            }

        }else{
            // check this is email is using for this facebook id ?
            $g_id = get_user_meta( $u->ID, 'google_user_id', true );
            // if this fb email is used for this user
            if ( $g_id == $me['user_id'] ) {
                return $u->ID;
            } else { // if this email is already used for other account then create new

                $name =explode( '@', $me['email'] );
                $name = $name[0];

                $rand = '';
                $new_username = $name.$rand;
                $user_id = username_exists( $new_username );

                // While user exists do until wrong
                while ( $user_id ) {
                    $rand = (int) $rand + 1;
                    $new_username = $name.'_g'.$rand;
                    $user_id = username_exists( $new_username );
                }

                $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );

                $nr = wp_create_user( $new_username, $random_password, '' );

                if ( ! is_wp_error(  $nr ) ) {
                    $this->update_user(  $nr,  $me );
                    wp_new_user_notification( $nr, $random_password );
                    return  $nr;
                }

            }
        }

        return false;
    }

    /**
     * Update User data
     *
     * @since 1.0.0
     * @param $user_id
     * @param $me
     */
    function update_user(  $user_id , $me ){
        if( ! is_array(  $me ) ){
            return;
        }

        update_user_meta( $user_id , 'google_user_data', $me );
        foreach(  $me as $k => $v ){
            update_user_meta( $user_id, 'google_'.$k, $v );
        }

        $user_data =  array(
            'display_name'  => $me['name'],
            'first_name'    => isset( $me['first_name'] ) ?  $me['first_name'] : '',
            'last_name'     => isset( $me['last_name'] ) ?  $me['last_name'] : '',
            'ID'=>  $user_id
        );

        wp_update_user( $user_data );

        $user_meta = array(
            'first_name'  => isset( $me['first_name'] ) ?  $me['first_name'] : '',
            'last_name'   => isset( $me['last_name'] ) ?  $me['last_name'] : '',
            'nickname'    => $me['name'],
        );

        $user_meta = apply_filters('st_user_google_update_meta', $user_meta , $me );
        foreach( $user_meta  as $k => $v ){
            update_user_meta( $user_id, $k, $v );
        }

    }


    /**
     * @TODO: Display login button
     * @since 1.0
     */
    function button( $in_modal = false, $redirect_url = '' ){
        ?>
        <a href="<?php echo $this->login_url; ?>" class="st-btn full-width fb-google-button <?php echo $in_modal ? 'fb-in-modal' : ''; ?>"><?php echo esc_attr__('Login width Google','st-user-fb-add-on') ?></a>
        <?php
    }

    /**
     * A filter that you can save option setting automatically
     *
     * @since 1.0.0
     * @param $keys
     * @return mixed
     */
    function admin_setting_keys(  $keys ){
        foreach ( $this->settings as $k => $v ){
            $keys[ $k ] = '';
        }
        return $keys;
    }

    /**
     * Add Admin settings
     *
     * @since 1.0.0
     */
    function admin_settings(){

        $return_url = add_query_arg( array( 'google_return' => 1 ), site_url( '/' ) ) ;

        ?>
        <h3><?php _e( 'Google Settings', 'st-user-social-add-on' ); ?></h3>
        <p class="description"><?php
            printf( __( '<a target="_blank" href="%1$s">Setup the Google API project</a>', 'st-user-social-add-on' ), 'https://console.developers.google.com/project/' );
            ?></p>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="st_user_google_client_id"><?php _e( 'Client ID', 'st-user-social-add-on'); ?></label></th>
                <td>
                    <input type="text" placeholder="client id" class="regular-text" value="<?php echo esc_attr( get_option( 'st_user_google_client_id' ) ); ?>" id="<?php echo esc_attr( 'st_user_google_client_id' ) ?>" name="<?php echo esc_attr( 'st_user_google_client_id' ) ?>">
                    <p class="description"><?php  _e( 'The client ID you obtain from the Developers Console.', 'st-user-fb-add-on' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="st_user_google_client_secret"><?php _e('Client secret','st-user-fb-add-on'); ?></label></th>
                <td>
                    <input type="text" placeholder="client secret" class="regular-text" value="<?php echo esc_attr( get_option( 'st_user_google_client_secret' ) ); ?>" id="<?php echo esc_attr( 'st_user_google_client_secret' ) ?>" name="<?php echo esc_attr( 'st_user_google_client_secret' ) ?>">
                    <p class="description"><?php _e( 'The client secret obtained from the Developers Console.', 'st-user-social-add-on' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="st_user_google_redirect_uri"><?php _e('Redirect URI','st-user-fb-add-on'); ?></label></th>
                <td>
                    <input type="text" placeholder="<?php echo esc_url( $return_url ); ?>" class="regular-text" value="<?php echo esc_attr( get_option( 'st_user_google_redirect_uri' ) ); ?>" id="<?php echo esc_attr( 'st_user_google_redirect_uri' ) ?>" name="<?php echo esc_attr( 'st_user_google_redirect_uri' ) ?>">
                    <p class="description"><?php _e( 'One of the redirect_uri values listed for this project in the Developers Console.', 'st-user-social-add-on' ); ?></p>
                    <p class="description">
                        <strong style="color: red"><?php _e( 'Please edit your client settings following:', 'st-user-social-add-on' ); ?></strong><br/>
                        <strong>Authorized JavaScript origins:</strong> <code><?php echo site_url( '/' ); ?></code><br/>
                        <strong>Authorized redirect URIs:</strong> <code><?php echo esc_attr( $return_url ); ?></code><br/>
                    </p>
                </td>
            </tr>

            </tbody>
        </table>
    <?php
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