<?php
/**
 * Plugin Name:       ST User Socials Login Add-on
 * Plugin URI:        http://smooththemes.com/st-user/
 * Description:       A add-on for ST User plugin
 * Version:           1.0.0
 * Author:            SmoothThemes
 * Author URI:        http://smoothemes.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       st-user
 * Domain Path:       /languages
 */




/**
 * Let user login without password
 *
 * @since 1.0.0
 * @param $user_id
 * @param bool $remember
 * @return bool
 */
function st_user_add_ond_login( $user_id, $remember =  true  ){
    $user = get_user_by( 'id', $user_id );
    if( $user ) {
        //wp_set_current_user( $user_id, $user->user_login );
        global $current_user;

        if ( isset( $current_user ) && ( $current_user instanceof WP_User ) && ( $user->ID == $current_user->ID ) ){
            return true;
        }

        $current_user = new WP_User( $user->ID, $user->user_login );
        setup_userdata( $user->ID );
        wp_set_auth_cookie( $user_id, $remember );
        do_action( 'wp_login', $user->user_login );
        return true;
    }
    return false;
}


include_once 'facebook/fb.php';
include_once 'google/google.php';