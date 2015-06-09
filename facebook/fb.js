jQuery(document).ready(function( $ ) {
    $.ajaxSetup({ cache: true });
    $.getScript('//connect.facebook.net/en_US/sdk.js', function(){
        var is_fb_contented = false;
        FB.init({
            appId: ST_User.fb_app_id, // 836604639749778
            version: 'v2.3' // or v2.0, v2.1, v2.0
        });

        FB.getLoginStatus( statusChangeCallback );

        // This is called with the results from from FB.getLoginStatus().
        function statusChangeCallback(response) {
            console.log('statusChangeCallback');
            console.log(response);
            // The response object is returned with a status field that lets the
            // app know the current login status of the person.
            // Full docs on the response object can be found in the documentation
            // for FB.getLoginStatus().
            var token = '';
            if (response.status === 'connected') {
                // Logged into your app and Facebook.
                token = response.authResponse.accessToken;
                is_fb_contented = true;
            } else if (response.status === 'not_authorized') {
                // The person is logged into Facebook, but not your app.
            } else {
                // The person is not logged into Facebook, so we're not sure if
                // they are logged into this app or not.
            }

            // check server
            /*
            $.ajax({
                url: ST_User.ajax_url,
                data: { 'action': 'st_user_fb_ajax', _do: 'get_login_status', status: response.status , access_token: token },
                type: 'POST',
               // dataType: 'json',
                success: function( response ){

                }
            });
            */

        }

        /**
         // get profile picture
         FB.api('/me/picture?type=large', function(response) {
            profilepicture = response.data.url;
        });
         */

        // https://developers.facebook.com/docs/facebook-login/login-flow-for-web/v2.3
        // https://developers.facebook.com/docs/facebook-login/reauthentication
        $('.fb-login-button').on('click fb_ask_login', function(){
            var btn = $(this);
            var in_modal =  btn.hasClass('fb-in-modal');
            $('body').trigger('st_add_loading_form');
            fb_login( btn,  in_modal );
        });

        function fb_login( click_btn,  in_modal  ){
            FB.login(function(response) {

                if (response.authResponse) {
                    var token = response.authResponse.accessToken ;
                    FB.api('/me', function(response) {
                        // console.log('Good to see you, ' + response.name + '.');
                        $.ajax({
                            url: ST_User.ajax_url,
                            data: { 'action': 'st_user_fb_ajax', _do: 'login', fb_response: response, access_token: token },
                            type: 'POST',
                            dataType: 'json',
                            success: function( response ){
                                $('body').trigger('st_remove_loading_form');
                                if( typeof  response.redirect_url !== 'undefined'  && typeof   response.redirect_url !== '' ){
                                    window.location =  response.redirect_url;
                                }
                            }
                        });

                    });
                } else {
                    $('body').trigger('st_remove_loading_form');
                    //console.log('User cancelled login or did not fully authorize.');
                }
            }, {
                scope : 'public_profile, email, user_friends',
                auth_type: 'rerequest'
            });
        }


        $('.fb-logout-button').on('click', function(){
            FB.logout(function(response) {
                // user is now logged out
            });
            return false;
        });

    });

});