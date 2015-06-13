<?php
/**
 * Facebook Login add-on
 *
 * Crear New Facebook app
 * @see https://developers.facebook.com/apps/
 *
 * @see https://developers.facebook.com/docs/facebook-login/login-flow-for-web/v2.3
 *
 * API End Point
 * @see https://graph.facebook.com/me?client_id=xxx&access_token=xx
 * @see https://graph.facebook.com/oauth/access_token_info?client_id=xxx&access_token=xxx
 * @see https://graph.facebook.com/app?access_token=XXX
 */

/**
 * Main class  to login width facebook
 * Class ST_Uer_Facebook_Login
 *
 */
class ST_Uer_Facebook_Login{

    public $app_id ;
    private $option_setting = 'st_user_fb_app_id';

    function __construct(){

        $this->app_id = get_option( $this->option_setting );
        // if the app id is not set
        if( $this->app_id ) {
            add_action('wp_enqueue_scripts', array($this, 'scripts'));
            add_action('st_user_after_login_form', array($this, 'button'));
            add_filter('st_user_localize_script', array($this, 'localize_script'));

            add_action('wp_ajax_st_user_fb_ajax', array($this, 'ajax'));
            add_action('wp_ajax_nopriv_st_user_fb_ajax', array($this, 'ajax'));
        }

        if ( is_admin() ) {
            add_filter('st_settings_keys', array($this, 'admin_setting_keys') );
            add_action('st_user_settings_table', array($this, 'admin_settings'));
        }

    }
    /*
     * @TODO: Add Js script for site
     * @since 1.0.0
     */
    function scripts(){
        wp_enqueue_script( 'fb-login', trailingslashit( plugins_url('', __FILE__) )  . 'fb.js', array('st-user'), '1.0.0', true );
    }

    /**
     * @TODO: add more settings for st-user localize script
     * @since 1.0
     * @param $settings
     * @return mixed
     */
    function localize_script(  $settings ){
        $settings['fb_app_id'] = $this->app_id;
        $settings['is_logged_in'] = is_user_logged_in() ? 'true' : false;
        return $settings;
    }

    /**
     * @TODO: Display login button
     * @since 1.0
     */
    function button( $in_modal = false ){
        ?>
        <input type="button" value="<?php echo esc_attr__('Login width Facebook','st-user-fb-add-on') ?>" class="st-btn full-width fb-login-button <?php echo $in_modal ? 'fb-in-modal' : ''; ?>"/>
        <?php
    }

    /**
     * @TODO Verify if user is already logged in with this sie via facebook
     *
     * @since 1.0
     */
    function ajax(){
        $do = $_REQUEST['_do'];
        $token = $_REQUEST['access_token'];
        $json =  array(
            'did' => 'nothing',
            'redirect_url' => '',
            'reload' => false
        );
        switch ( $do ) {
            case 'get_login_status':
                $status = isset(  $_REQUEST['status'] ) ?  $_REQUEST['status'] : '' ;
                if( $status == 'connected' ){
                    if ( ! is_user_logged_in() ) {
                        $me = $this->get_me( $token );
                        if( $me ) {
                            $user_id = $this->maybe_create_update_user($me);
                            // if user exists
                            if ( $user_id ) {
                                $this->login( $user_id, isset($me['email'] ) );
                                $json['redirect_url'] = apply_filters('login_redirect','');
                                $json['reload'] =  true;
                                $json['did'] =  'login';
                            }
                        }
                    } else {
                        $json['did'] =  'login';
                    }
                }
                break;
            case 'login':
                if( $this->check_app(  $token ) ) {
                    $me = $this->get_me( $token );
                    if( $me ) {
                        $user_id = $this->maybe_create_update_user( $me );
                        // if user exists
                        if( $user_id ) {
                            $this->login( $user_id, isset( $me['email'] ) );
                            $json['redirect_url'] = apply_filters('login_redirect','');
                            $json['reload'] =  true;
                            $json['did'] =  'login';
                        }
                    }
                }
                break;
        }
        echo json_encode( $json );
        die();
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
        if( !$me ) {
            return false;
        }

        $u = false;

       // try fo find with facebook ID
        $us =  get_users( array(
            'meta_key'     => 'fb_id',
            'meta_value'   => $me['id'],
            'meta_compare' => '=',
            'number'       => 1,
        ) );
        if( $us ) {
            $u = $us[0];
        }
        unset( $us );
        if ( $u ) {
            // update this user email if they provide
            if ( isset( $me['email'] ) ) {
                if (!get_user_by('email', $me['email'])) {
                    wp_update_user(array(
                        'user_email' => $me['email'],
                        'ID' => $u->ID
                    ));
                }
            }
            return $u->ID;
        }


        /**
         * Try fo find user if logged in/registered before
         * check if email is not registered
         */
        if ( isset( $me['email'] ) ) { // try to find with email
            $u = get_user_by('email', $me['email'] );
        }

        if( ! $u ) { // this email not register yet
             // register new account with this email here.
            $random_password = wp_generate_password( $length=12, $include_standard_special_chars = false );
            $name =explode( '@', $me['email'] );
            $name = $name[0];

            $rand = '';
            $new_username = $name.$rand;
            $user_id = username_exists( $new_username );

            // While user exists do until wrong
            while ( $user_id ) {
                $rand = (int) $rand + 1;
                $new_username = $name.'_f'.$rand;
                $user_id = username_exists( $new_username );
            }

            $nr = wp_create_user( $new_username , $random_password, $me['email'] );
            if ( ! is_wp_error(  $nr ) ) {
                $this->update_user( $nr,  $me );
                wp_new_user_notification( $nr, $random_password );
                return  $nr;
            }

        } else {
            // check this is email is using for this facebook id ?
            $fb_id = get_user_meta($u->ID, 'fb_id', true);
            // if this fb email is used for this user
            if ( $fb_id == $me['id'] ) {
                return $u->ID;
            } else { // if this email is already used for other account then create new

                $rand = '';
                $new_username = 'fb'.$rand.$me['id'];
                $user_id = username_exists( $new_username );

                // While user exists do until wrong
                while ( $user_id ) {
                    $rand = strtolower( wp_generate_password( 4, false ) );
                    $new_username = 'fb'.$rand.$me['id'];
                    $user_id = username_exists( $new_username );
                }

                $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );

                $nr = wp_create_user( $new_username, $random_password, '' );

                if ( ! is_wp_error( $nr ) ) {

                    $this->update_user( $nr,  $me );

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

        update_user_meta( $user_id , 'fb_user_data', $me );
        foreach(  $me as $k => $v ){
            update_user_meta( $user_id, 'fb_'.$k, $v );
        }

        $user_data =  array(
            'display_name' => $me['name'],
            'first_name' => isset( $me['first_name'] ) ?  $me['first_name'] : '',
            'last_name' => isset( $me['last_name'] ) ?  $me['last_name'] : '',
            'ID'=>  $user_id
        );

        wp_update_user( $user_data );

        $user_meta = array(
            'first_name' => isset( $me['first_name'] ) ?  $me['first_name'] : '',
            'last_name' => isset( $me['last_name'] ) ?  $me['last_name'] : '',
            'nickname' => $me['name'],
        );

        $user_meta = apply_filters('st_user_fb_update_meta', $user_meta , $me );
        foreach( $user_meta  as $k => $v ){
            update_user_meta( $user_id, $k, $v );
        }

    }

    /**
     * Let user login without password
     *
     * @since 1.0.0
     * @param $user_id
     * @param bool $remember
     * @return bool
     */
    function login( $user_id, $remember =  true  ){
        return st_user_add_ond_login( $user_id, $remember );
    }

    /**
     * Get user info via token
     * @since 1.0.0
     * @param string $token
     * @return false|array
     */
    function get_me( $token ){
        $api_end_point = 'https://graph.facebook.com/me';
        $api_end_point = add_query_arg( array(
            'client_id'=> $this->app_id,
            'access_token'=> $token,
        ),  $api_end_point );
        $r = $this->remote_data( $api_end_point );
        if( is_array( $r ) && isset( $r['id'] ) ){
            return  $r;
        }else{
            return false;
        }
    }

    /**
     * Check token is using for this this app or not
     * @param string $token
     * @return bool
     */
    function check_app( $token ){
        $api_end_point = 'https://graph.facebook.com/app';
        $api_end_point = add_query_arg( array(
            'access_token'=> $token,
        ),  $api_end_point );
        $r = $this->remote_data( $api_end_point );
        // if this token is using for this app
        if( is_array( $r ) && isset( $r['id'] )  && $r['id'] ==  $this->app_id ){
            return true;
        }
        return false;
    }

    /**
     * Get remote data
     *
     * @since 1.0
     * @param $url
     * @return array|mixed
     */
    function remote_data(  $url ){
        $response = wp_remote_get( $url );
        /* Will result in $api_response being an array of data,
        parsed from the JSON response of the API listed above */
        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        return $api_response;
    }

    /**
     * A filter that you can save option setting automatically
     *
     * @since 1.0.0
     * @param $keys
     * @return mixed
     */
    function admin_setting_keys(  $keys ){
        $keys[ $this->option_setting ] = '';
        return $keys;
    }

    /**
     * Add Admin settings
     *
     * @since 1.0.0
     */
    function admin_settings(){
        ?>
        <h3><?php _e('Facebook Settings','st-user'); ?></h3>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="st_user_fb_app_id"><?php _e('Facebook App ID','st-user-fb-add-on'); ?></label></th>
                <td>
                    <input type="text" placeholder="E.g: 836604639749778" class="regular-text" value="<?php echo esc_attr( get_option(  $this->option_setting ) ); ?>" id="<?php echo esc_attr( $this->option_setting ) ?>" name="<?php echo esc_attr( $this->option_setting ) ?>">
                    <p class="description"><?php echo sprintf( __( 'Get Your app id <a target="_blank" href="%1$s">HERE</a>', 'st-user-fb-add-on' ) , 'https://developers.facebook.com/apps/' ); ?></p>
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
    function run_st_user_fb_add_on() {
        new ST_Uer_Facebook_Login();
    }
    add_action('st_user_init', 'run_st_user_fb_add_on' );
}else{
    //add_action('init', 'run_st_user_fb_add_on');
}

