<?php

/*
Controller name: ConstApps API
Controller description: Extends the JSON API for ConstApps API user registration, authentication, password reset, user meta and Profile related functions.
Controller Author: ConstApps
 */

class JSON_API_Constapps_User_Controller
{

    public function __construct() {
        global $json_api;
    }

    public function register() {
        global $json_api;

        if (!get_option('users_can_register')) {
            $json_api->error("User registration is disabled. Please enable it in Settings > Gereral.");
        }

        if (!$json_api->query->username) {
            $json_api->error("You must include 'username' in your request. ");
        }
        else $username = sanitize_user( $json_api->query->username );


        if (!$json_api->query->email) {
            $json_api->error("You must include 'email' in your request. ");
        }
        else $email = sanitize_email( $json_api->query->email );

        if (!$json_api->query->nonce) {
            $json_api->error("You must include 'nonce' in your request. Use the 'get_nonce' Core API method. ");
        }
        else $nonce =  sanitize_text_field( $json_api->query->nonce ) ;

        if (!$json_api->query->display_name) {
            $json_api->error("You must include 'display_name' in your request. ");
        }
        else $display_name = sanitize_text_field( $json_api->query->display_name );

        $user_pass = sanitize_text_field( $_REQUEST['user_pass'] );

        if ($json_api->query->seconds) 	$seconds = (int) $json_api->query->seconds;
        		else $seconds = 1209600;//14 days

        $invalid_usernames = array( 'admin' );
        if ( !validate_username( $username ) || in_array( $username, $invalid_usernames ) ) {
            $json_api->error("Username is invalid.");
        }
        elseif ( username_exists( $username ) ) {
            $json_api->error("Username already exists.");
        }
        else {
            if ( !is_email( $email ) ) {
                $json_api->error("E-mail address is invalid.");
            }
            elseif (email_exists($email)) {
                $json_api->error("E-mail address is already in use.");
            }
            else {

            if( !isset($_REQUEST['user_pass']) ) {
                 $user_pass = wp_generate_password();
                 $_REQUEST['user_pass'] = $user_pass;
            }

            $_REQUEST['user_login'] = $username;
            $_REQUEST['user_email'] = $email;

            $allowed_params = array('user_login', 'user_email', 'user_pass', 'display_name', 'user_nicename', 'user_url', 'nickname', 'first_name',
                                 'last_name', 'description', 'rich_editing', 'user_registered', 'role', 'jabber', 'aim', 'yim',
        						 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front'
            );
            foreach($_REQUEST as $field => $value){
        	    if( in_array($field, $allowed_params) ) $user[$field] = trim(sanitize_text_field($value));
            }
            $user['role'] = get_option('default_role');
            $user_id = wp_insert_user( $user );

            if( isset($_REQUEST['user_pass']) && $_REQUEST['notify']=='no') {
        	    $notify = '';
            } elseif ($_REQUEST['notify']!='no') $notify = $_REQUEST['notify'];

            if($user_id) wp_new_user_notification( $user_id, '',$notify );
        	}
        }

        $expiration = time() + apply_filters('auth_cookie_expiration', $seconds, $user_id, true);

        $cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');

        return array(
            "cookie" => $cookie,
            "user_id" => $user_id
        );
    }

    public function validate_auth_cookie()
    {
        global $json_api;
        if (!$json_api->query->cookie) {

            $json_api->error("You must include a 'cookie' authentication cookie. Use the `create_auth_cookie` method.");

        }
        $valid = wp_validate_auth_cookie($json_api->query->cookie, 'logged_in') ? true : false;

        return array(
            'cookie' => $json_api->query->cookie,
            "valid" => $valid,
        );
    }

    public function generate_auth_cookie()
    {

        global $json_api;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $json = file_get_contents('php://input');
            $params = json_decode($json);
            foreach ($params as $k => $val) {
                $json_api->query->$k = $val;
            }
        }


        if (!$json_api->query->username && !$json_api->query->email) {
            $json_api->error("You must include 'username' or 'email' var in your request to generate cookie.");
        }

        if (!$json_api->query->password) {
            $json_api->error("You must include a 'password' var in your request.");
        }

        if ($json_api->query->seconds) {
            $seconds = (int) $json_api->query->seconds;
        } else {
            $seconds = 1209600;
        }
        if ($json_api->query->email) {

            if (is_email($json_api->query->email)) {
                if (!email_exists($json_api->query->email)) {
                    $json_api->error("email does not exist.");
                }
            } else {
                $json_api->error("Invalid email address.");
            }

            $user_obj = get_user_by('email', $json_api->query->email);

            $user = wp_authenticate($user_obj->data->user_login, $json_api->query->password);
        } else {

            $user = wp_authenticate($json_api->query->username, $json_api->query->password);
        }

        if (is_wp_error($user)) {

            $json_api->error("Invalid username/email and/or password.", 'error', '401');

            remove_action('wp_login_failed', $json_api->query->username);

        }

        $expiration = time() + apply_filters('auth_cookie_expiration', $seconds, $user->ID, true);

        $cookie = wp_generate_auth_cookie($user->ID, $expiration, 'logged_in');

        preg_match('|src="(.+?)"|', get_avatar($user->ID, 512), $avatar);

        return array(
            "cookie" => $cookie,
            "cookie_name" => LOGGED_IN_COOKIE,
            "user" => array(
                "id" => $user->ID,
                "username" => $user->user_login,
                "nicename" => $user->user_nicename,
                "email" => $user->user_email,
                "url" => $user->user_url,
                "registered" => $user->user_registered,
                "displayname" => $user->display_name,
                "firstname" => $user->user_firstname,
                "lastname" => $user->last_name,
                "nickname" => $user->nickname,
                "description" => $user->user_description,
                "capabilities" => $user->wp_capabilities,
                "role" => $user->roles,
                "avatar" => $avatar[1],

            ),
        );
    }

    public function post_comment()
    {
        global $json_api;
        if (!$json_api->query->cookie) {
            $json_api->error("You must include a 'cookie' var in your request. Use the `generate_auth_cookie` method.");
        }
        $user_id = wp_validate_auth_cookie($json_api->query->cookie, 'logged_in');

        if (!$user_id) {
            $json_api->error("Invalid cookie. Use the `generate_auth_cookie` method.");
        }
        if (!$json_api->query->post_id) {
            $json_api->error("No post specified. Include 'post_id' var in your request.");
        } elseif (!$json_api->query->content) {
            $json_api->error("Please include 'content' var in your request.");
        }

        $comment_approved = 0;
        $user_info = get_userdata($user_id);
        $time = current_time('mysql');
        $agent = filter_has_var(INPUT_SERVER, 'HTTP_USER_AGENT') ? filter_input(INPUT_SERVER, 'HTTP_USER_AGENT') : 'Mozilla';
        $ips = filter_has_var(INPUT_SERVER, 'REMOTE_ADDR') ? filter_input(INPUT_SERVER, 'REMOTE_ADDR') : '127.0.0.1';
        $data = array(
            'comment_post_ID' => $json_api->query->post_id,
            'comment_author' => $user_info->user_login,
            'comment_author_email' => $user_info->user_email,
            'comment_author_url' => $user_info->user_url,
            'comment_content' => $json_api->query->content,
            'comment_type' => '',
            'comment_parent' => 0,
            'user_id' => $user_info->ID,
            'comment_author_IP' => $ips,
            'comment_agent' => $agent,
            'comment_date' => $time,
            'comment_approved' => $comment_approved,
        );
        //print_r($data);
        $comment_id = wp_insert_comment($data);
        //add metafields
        $meta = json_decode(stripcslashes($json_api->query->meta), true);
        //extra function
        add_comment_meta($comment_id, 'rating', $meta['rating']);
        add_comment_meta($comment_id, 'verified', 0);

        return array(
            "comment_id" => $comment_id,
        );
    }

    public function get_currentuserinfo() {
		global $json_api;
		if (!$json_api->query->cookie) {
			$json_api->error("You must include a 'cookie' var in your request. Use the `generate_auth_cookie` Auth API method.");
		}
		$user_id = wp_validate_auth_cookie($json_api->query->cookie, 'logged_in');
		if (!$user_id) {
			$json_api->error("Invalid authentication cookie. Use the `generate_auth_cookie` Auth API method.");
		}
		$user = get_userdata($user_id);
		preg_match('|src="(.+?)"|', get_avatar( $user->ID, 32 ), $avatar);
		return array(
			"user" => array(
				"id" => $user->ID,
				"username" => $user->user_login,
				"nicename" => $user->user_nicename,
				"email" => $user->user_email,
				"url" => $user->user_url,
				"registered" => $user->user_registered,
				"displayname" => $user->display_name,
				"firstname" => $user->user_firstname,
				"lastname" => $user->last_name,
				"nickname" => $user->nickname,
				"description" => $user->user_description,
                "capabilities" => $user->wp_capabilities,
                "role" => $user->roles,
				"avatar" => $avatar[1]
			)
		);
    }

    function get_points(){
        global $wc_points_rewards;
        global $json_api;
        $user_id = (int) $_GET['user_id'];
        $current_page = (int) $_GET['page'];

		$points_balance = WC_Points_Rewards_Manager::get_users_points( $user_id );
		$points_label   = $wc_points_rewards->get_points_label( $points_balance );
		$count        = apply_filters( 'wc_points_rewards_my_account_points_events', 5, $user_id );
		$current_page = empty( $current_page ) ? 1 : absint( $current_page );

		$args = array(
			'calc_found_rows' => true,
			'orderby' => array(
				'field' => 'date',
				'order' => 'DESC',
			),
			'per_page' => $count,
			'paged'    => $current_page,
			'user'     => $user_id,
        );
        $total_rows = WC_Points_Rewards_Points_Log::$found_rows;
		$events = WC_Points_Rewards_Points_Log::get_points_log_entries( $args );

        return array(
            'points_balance' => $points_balance,
            'points_label'   => $points_label,
            'total_rows'     => $total_rows,
            'page'   => $current_page,
            'count'          => $count,
            'events'         => $events
        );
    }
}
