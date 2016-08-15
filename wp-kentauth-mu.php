<?php

$_SERVER['HTTPS'] = 'On';

$saml_included =true;

include_once('simplesamlphp/lib/_autoload.php');

if($saml_included){
	try {
		$as = new SimpleSAML_Auth_Simple('default-sp');
	}
	catch (Exception $e) {
		$as = NULL;
		$saml_included = false;
	}
}

if($saml_included) {
	add_action('authenticate', 'kent_wp_saml_authenticate', 10, 2);
	add_action('wp_logout', 'kent_wp_saml_logout');
	add_action('lost_password', 'kent_wp_saml_disable_function');
	add_action('retrieve_password', 'kent_wp_saml_disable_function');
	add_action('password_reset', 'kent_wp_saml_disable_function');
	add_filter('show_password_fields', '__return_false');
	add_action('login_form','kent_wp_saml_modify_login_form');
}

function kent_wp_saml_disable_function(){
	wp_die('Function Disabled!','Function Disabled',array('response'=>403,'back_link'=>true));
}

function kent_wp_saml_modify_login_form(){
	if(isset($_REQUEST['local_login']) && !empty($_REQUEST['local_login'])) {
		echo '<input type="hidden" name="local_login" value="1">' . "\n";
	}
}

if($saml_included) {
	/*
	 Log the user out from WordPress if the simpleSAMLphp SP session is gone.
	 This function overrides the is_logged_in function from wp core.
	 (Another solution could be to extend the wp_validate_auth_cookie func instead).
	*/
	function is_user_logged_in() {
		global $as;
		// Allow use of "is_user_logged_in" filter to override current login mechanism
		$is_authed = apply_filters("is_user_logged_in", null);
		if($is_authed !== null) {
			return $is_authed;
		}
		$user = wp_get_current_user();
		if($user->ID > 0 ){

			if($user->user_login ==='ninukisadm' || $user->user_login ==='iswebdev'){
				return $user->exists();
			}

			// User is local authenticated but SP session was closed
			if(!isset($as)) {
				try {
					$as = new SimpleSAML_Auth_Simple('default-sp');
				} catch(Exception $e) {
					return false;
				}
			}

			if(!$as->isAuthenticated()) {
				wp_logout();

				return false;
			} else {
				return true;
			}
		}

		return false;
	}
}


/*
 We call simpleSAMLphp to authenticate the user at the appropriate time.
 If the user has not logged in previously, we create an account for them.
*/
function kent_wp_saml_authenticate($user, $username) {

	if(is_a($user, 'WP_User')) { return $user; }
	global $as;

	if(!(isset($_REQUEST['local_login']) && !empty($_REQUEST['local_login']))) {

		// fix from http://wordpress.org/support/topic/suggested-change-for-fixing-re-login
		try {
			$as->requireAuth(['ReturnTo' => wp_login_url(wp_make_link_relative($_REQUEST["redirect_to"]), false)]);
		} catch(Exception $e) {
			wp_die("SAML login could not be initiated");
		}
		// Reset values from input ($_POST and $_COOKIE)
		$username = '';

		$attributes = $as->getAttributes();

		$username = $attributes['uid'][0];

		if($username != substr(sanitize_user($username, true), 0, 60)) {
			$error = sprintf(__('<p><strong>ERROR</strong><br /><br />
				We got back the following identifier from the login process:<pre>%s</pre>
				Unfortunately that is not suitable as a username.<br />
				Please contact the <a href="mailto:%s"> administrator</a> for support.</p>'),
							 $username,
							 get_option('admin_email'));
			wp_die($error);
		}

		$user = get_user_by('login', $username);

		if($user) {
			// user already exists
			if($user->get('pswd_cleared') < (time() - 86400)) {
				kent_wp_saml_invalidate_password($user->ID);
			}

			kent_wp_saml_update_super_admins($user->ID,$attributes);

			return $user;
		} else {

			// User is not in the WordPress database
			// They passed SimpleSAML and so are authorised
			// Add them to the database

			// User must have an e-mail address to register

			if($attributes['mail'][0]) {
				// Try to get email address from attribute
				$user_email = $attributes['mail'][0];
			} else {

				// Otherwise use default email suffix
				$user_email = $username . '@kent.ac.uk';

			}

			$user_info = [];
			$user_info['user_login'] = $username;
			$user_info['user_pass'] = wp_generate_password(); // Gets reset later on.
			$user_info['user_email'] = $user_email;

			$user_info['first_name'] = $attributes['givenName'][0];
			$user_info['last_name'] = $attributes['sn'][0];

			$user_info['role'] = "subscriber";

			$wp_uid = wp_insert_user($user_info);
			if(is_object($wp_uid) && is_a($wp_uid, 'WP_Error')) {
				$error = $wp_uid->get_error_messages();
				$error = implode("<br>", $error);
				$error = '<p><strong>ERROR</strong>: ' . $error . '</p>';
				wp_die($error);
			}
			kent_wp_saml_invalidate_password($wp_uid);
			kent_wp_saml_update_super_admins($wp_uid,$attributes);
			return get_user_by('login', $username);
		}
	}
}

function kent_wp_saml_logout() {

	global $as;

	$as->logout(get_option('siteurl'));

}

function kent_wp_saml_invalidate_password($ID) {
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE $wpdb->users SET user_pass = '" . bin2hex(random_bytes(10)) ."' WHERE ID = %d",
			$ID
		)
	);
	update_user_meta($ID,'pswd_cleared',time());
}

function kent_wp_saml_update_super_admins($user_id, $attributes){
	$admins = get_site_option('site_admins',array(),false);
	$user = get_userdata($user_id);
	$changed=false;

	if(isset($attributes['unikentadminresource']) &&
	   isset($attributes['unikentadminresource'][0]) &&
	   $attributes['unikentadminresource'][0] === 'wp-admin'
	) {
		$admins = get_site_option('site_admins', [], false);
		if(!in_array($user->user_login, $admins)) {
			$admins[] = $user->user_login;
			$changed = true;
		}
	} else {
		if(($key = array_search($user->user_login, $admins)) !== false) {
			unset($admins[$key]);
			$changed = true;
		}
	}

	if($changed) {
		update_site_option('site_admins', $admins);
	}
}

/*
 * Override errors generated by WordPress due to network username
 * restrictions that are sort of insane.
 */
function kent_wp_validate_username($result) {
	if (! is_wp_error($result['errors'])) {
		return $result;
	}

	$username = $result['user_name'];

	// Copy any error messages that have not been overridden
	$new_errors = new WP_Error();

	$errors = $result['errors'];
	$codes = $errors->get_error_codes();

	foreach ($codes as $code) {
		$messages = $errors->get_error_messages($code);

		if ($code == 'user_name') {
			foreach ($messages as $message) {
				if ($message == __('Username must be at least 4 characters.')) {
					// Check the username length

					if (strlen($username) < 2) {
						$new_errors->add($code, $message);
					}
				}
				else {
					// Restore other username errors
					$new_errors->add($code, $message);
				}
			}
		}
		else {
			// Restore any other errors
			foreach ($messages as $message) {
				$new_errors->add($code, $message);
			}
		}
	}

	$result['errors'] = $new_errors;

	return $result;
}

add_filter('wpmu_validate_user_signup', 'kent_wp_validate_username');