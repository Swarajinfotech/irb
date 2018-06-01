<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.


//Make public functions without class creation


/**
 * Trim string by char length
 *
 *
 * @param $s
 * @param int $length
 *
 * @return string
 */
function um_trim_string( $s, $length = 20 ) {
	$s = strlen( $s ) > $length ? substr( $s, 0, $length ) . "..." : $s;

	return $s;
}


/**
 * Get where user should be headed after logging
 *
 * @param string $redirect_to
 *
 * @return bool|false|mixed|string|void
 */
function um_dynamic_login_page_redirect( $redirect_to = '' ) {

	$uri = um_get_core_page( 'login' );

	if (!$redirect_to) {
		$redirect_to = UM()->permalinks()->get_current_url();
	}

	$redirect_key = urlencode_deep( $redirect_to );

	$uri = add_query_arg( 'redirect_to', $redirect_key, $uri );

	return $uri;
}


/**
 * Checks if session has been started
 *
 * @return bool
 */
function um_is_session_started() {

	if ( php_sapi_name() !== 'cli' ) {
		if ( version_compare( phpversion(), '5.4.0', '>=' ) ) {
			return session_status() === PHP_SESSION_ACTIVE ? true : false;
		} else {
			return session_id() === '' ? false : true;
		}
	}

	return false;
}


/**
 * User clean basename
 *
 * @param $value
 *
 * @return mixed|void
 */
function um_clean_user_basename( $value ) {

	$raw_value = $value;
	$value = str_replace( '.', ' ', $value );
	$value = str_replace( '-', ' ', $value );
	$value = str_replace( '+', ' ', $value );

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_clean_user_basename_filter
	 * @description Change clean user basename
	 * @input_vars
	 * [{"var":"$basename","type":"string","desc":"User basename"},
	 * {"var":"$raw_basename","type":"string","desc":"RAW user basename"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_clean_user_basename_filter', 'function_name', 10, 2 );
	 * @example
	 * <?php
	 * add_filter( 'um_clean_user_basename_filter', 'my_clean_user_basename', 10, 2 );
	 * function my_clean_user_basename( $basename, $raw_basename ) {
	 *     // your code here
	 *     return $basename;
	 * }
	 * ?>
	 */
	$value = apply_filters( 'um_clean_user_basename_filter', $value, $raw_value );

	return $value;
}


/**
 * Convert template tags
 *
 * @param $content
 * @param array $args
 * @param bool $with_kses
 *
 * @return mixed|string
 */
function um_convert_tags( $content, $args = array(), $with_kses = true ) {
	$search = array(
		'{display_name}',
		'{first_name}',
		'{last_name}',
		'{gender}',
		'{username}',
		'{email}',
		'{password}',
		'{login_url}',
		'{login_referrer}',
		'{site_name}',
		'{site_url}',
		'{account_activation_link}',
		'{password_reset_link}',
		'{admin_email}',
		'{user_profile_link}',
		'{user_account_link}',
		'{submitted_registration}',
		'{user_avatar_url}',
	);

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_template_tags_patterns_hook
	 * @description Extend UM placeholders
	 * @input_vars
	 * [{"var":"$placeholders","type":"array","desc":"UM Placeholders"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_template_tags_patterns_hook', 'function_name', 10, 1 );
	 * @example
	 * <?php
	 * add_filter( 'um_template_tags_patterns_hook', 'my_template_tags_patterns', 10, 1 );
	 * function my_template_tags_patterns( $placeholders ) {
	 *     // your code here
	 *     $placeholders[] = '{my_custom_placeholder}';
	 *     return $placeholders;
	 * }
	 * ?>
	 */
	$search = apply_filters( 'um_template_tags_patterns_hook', $search );

	$replace = array(
		um_user( 'display_name' ),
		um_user( 'first_name' ),
		um_user( 'last_name' ),
		um_user( 'gender' ),
		um_user( 'user_login' ),
		um_user( 'user_email' ),
		um_user( '_um_cool_but_hard_to_guess_plain_pw' ),
		um_get_core_page( 'login' ),
		um_dynamic_login_page_redirect(),
		UM()->options()->get( 'site_name' ),
		get_bloginfo( 'url' ),
		um_user( 'account_activation_link' ),
		um_user( 'password_reset_link' ),
		um_admin_email(),
		um_user_profile_url(),
		um_get_core_page( 'account' ),
		um_user_submitted_registration(),
		um_get_user_avatar_url(),
	);

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_template_tags_replaces_hook
	 * @description Extend UM replace placeholders
	 * @input_vars
	 * [{"var":"$replace_placeholders","type":"array","desc":"UM Replace Placeholders"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_template_tags_replaces_hook', 'function_name', 10, 1 );
	 * @example
	 * <?php
	 * add_filter( 'um_template_tags_replaces_hook', 'my_template_tags_replaces', 10, 1 );
	 * function my_template_tags_replaces( $replace_placeholders ) {
	 *     // your code here
	 *     $replace_placeholders[] = 'my_replace_value';
	 *     return $replace_placeholders;
	 * }
	 * ?>
	 */
	$replace = apply_filters( 'um_template_tags_replaces_hook', $replace );

	$content = str_replace( $search, $replace, $content );
	if ( $with_kses ) {
		$content = wp_kses_decode_entities( $content );
	}

	if ( isset( $args['tags'] ) && isset( $args['tags_replace'] ) ) {
		$content = str_replace( $args['tags'], $args['tags_replace'], $content );
	}

	$regex = '~\{(usermeta:[^}]*)\}~';
	preg_match_all( $regex, $content, $matches );

	// Support for all usermeta keys
	if ( ! empty( $matches[1] ) && is_array( $matches[1] ) ) {
		foreach ( $matches[1] as $match ) {
			$strip_key = str_replace( 'usermeta:', '', $match );
			$content = str_replace( '{' . $match . '}', um_user( $strip_key ), $content );
		}
	}

	return $content;
}


/**
 * @function um_user_ip()
 *
 * @description This function returns the IP address of user.
 *
 * @usage <?php $user_ip = um_user_ip(); ?>
 *
 * @return string The user's IP address.
 *
 * @example The example below can retrieve the user's IP address
 *
 * <?php
 *
 * $user_ip = um_user_ip();
 * echo 'User IP address is: ' . $user_ip; // prints the user IP address e.g. 127.0.0.1
 *
 * ?>
 */
function um_user_ip() {
	$ip = '127.0.0.1';

	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		//check ip from share internet
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} else if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		//to check ip is pass from proxy
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_user_ip
	 * @description Change User IP
	 * @input_vars
	 * [{"var":"$ip","type":"string","desc":"User IP"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_user_ip', 'function_name', 10, 1 );
	 * @example
	 * <?php
	 * add_filter( 'um_user_ip', 'my_user_ip', 10, 1 );
	 * function my_user_ip( $ip ) {
	 *     // your code here
	 *     return $ip;
	 * }
	 * ?>
	 */
	return apply_filters( 'um_user_ip', $ip );
}


/**
 * If conditions are met return true;
 *
 * @param $data
 *
 * @return bool
 */
function um_field_conditions_are_met( $data ) {
	if (!isset( $data['conditions'] )) return true;

	$state = 1;

	foreach ($data['conditions'] as $k => $arr) {
		if ($arr[0] == 'show') {

			$val = $arr[3];
			$op = $arr[2];

			if (strstr( $arr[1], 'role_' ))
				$arr[1] = 'role';

			$field = um_profile( $arr[1] );

			switch ($op) {
				case 'equals to':

					$field = maybe_unserialize( $field );

					if (is_array( $field ))
						$state = in_array( $val, $field ) ? 1 : 0;
					else
						$state = ( $field == $val ) ? 1 : 0;

					break;
				case 'not equals':

					$field = maybe_unserialize( $field );

					if (is_array( $field ))
						$state = !in_array( $val, $field ) ? 1 : 0;
					else
						$state = ( $field != $val ) ? 1 : 0;

					break;
				case 'empty':

					$state = ( !$field ) ? 1 : 0;

					break;
				case 'not empty':

					$state = ( $field ) ? 1 : 0;

					break;
				case 'greater than':
					if ($field > $val) {
						$state = 1;
					} else {
						$state = 0;
					}
					break;
				case 'less than':
					if ($field < $val) {
						$state = 1;
					} else {
						$state = 0;
					}
					break;
				case 'contains':
					if (strstr( $field, $val )) {
						$state = 1;
					} else {
						$state = 0;
					}
					break;
			}
		} else if ($arr[0] == 'hide') {

			$state = 1;
			$val = $arr[3];
			$op = $arr[2];

			if (strstr( $arr[1], 'role_' ))
				$arr[1] = 'role';

			$field = um_profile( $arr[1] );

			switch ($op) {
				case 'equals to':

					$field = maybe_unserialize( $field );

					if (is_array( $field ))
						$state = in_array( $val, $field ) ? 0 : 1;
					else
						$state = ( $field == $val ) ? 0 : 1;

					break;
				case 'not equals':

					$field = maybe_unserialize( $field );

					if (is_array( $field ))
						$state = !in_array( $val, $field ) ? 0 : 1;
					else
						$state = ( $field != $val ) ? 0 : 1;

					break;
				case 'empty':

					$state = ( !$field ) ? 0 : 1;

					break;
				case 'not empty':

					$state = ( $field ) ? 0 : 1;

					break;
				case 'greater than':
					if ($field <= $val) {
						$state = 0;
					} else {
						$state = 1;
					}
					break;
				case 'less than':
					if ($field >= $val) {
						$state = 0;
					} else {
						$state = 1;
					}
					break;
				case 'contains':
					if (strstr( $field, $val )) {
						$state = 0;
					} else {
						$state = 1;
					}
					break;
			}
		}
	}

	return ( $state ) ? true : false;
}


/**
 * Exit and redirect to home
 */
function um_redirect_home() {
	exit( wp_redirect( home_url() ) );
}


/**
 * @param $url
 */
function um_js_redirect( $url ) {
	if (headers_sent() || empty( $url )) {
		//for blank redirects
		if ('' == $url) {
			$url = set_url_scheme( '//' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] );
		}

		$funtext = "echo \"<script data-cfasync='false' type='text/javascript'>window.location = '" . $url . "'</script>\";";
		register_shutdown_function( create_function( '', $funtext ) );

		if (1 < ob_get_level()) {
			while (ob_get_level() > 1) {
				ob_end_clean();
			}
		}

		?>
        <script data-cfasync='false' type="text/javascript">
            window.location = '<?php echo $url; ?>';
        </script>
		<?php
		exit;
	} else {
		wp_redirect( $url );
	}
	exit;
}


/**
 * Get limit of words from sentence
 *
 * @param $str
 * @param int $wordCount
 *
 * @return string
 */
function um_get_snippet( $str, $wordCount = 10 ) {
	if (str_word_count( $str ) > $wordCount) {
		$str = implode(
			'',
			array_slice(
				preg_split(
					'/([\s,\.;\?\!]+)/',
					$str,
					$wordCount * 2 + 1,
					PREG_SPLIT_DELIM_CAPTURE
				),
				0,
				$wordCount * 2 - 1
			)
		);
	}

	return $str;
}


/**
 * Get submitted user information
 *
 * @param bool $style
 *
 * @return null|string
 */
function um_user_submitted_registration( $style = false ) {
	$output = null;

	$data = um_user( 'submitted' );

	if ( $style ) {
		$output .= '<div class="um-admin-infobox">';
	}

	if ( isset( $data ) && is_array( $data ) ) {

		/**
		 * UM hook
		 *
		 * @type filter
		 * @title um_email_registration_data
		 * @description Prepare Registration data to email
		 * @input_vars
		 * [{"var":"$data","type":"array","desc":"Registration Data"}]
		 * @change_log
		 * ["Since: 2.0"]
		 * @usage add_filter( 'um_email_registration_data', 'function_name', 10, 1 );
		 * @example
		 * <?php
		 * add_filter( 'um_email_registration_data', 'my_email_registration_data', 10, 1 );
		 * function my_email_registration_data( $data ) {
		 *     // your code here
		 *     return $data;
		 * }
		 * ?>
		 */
		$data = apply_filters( 'um_email_registration_data', $data );

		$pw_fields = array();
		foreach ( $data as $k => $v ) {

			if ( strstr( $k, 'user_pass' ) || in_array( $k, array( 'g-recaptcha-response', 'request', '_wpnonce', '_wp_http_referer' ) ) ) {
				continue;
			}

			if ( UM()->fields()->get_field_type( $k ) == 'password' ) {
				$pw_fields[] = $k;
				$pw_fields[] = 'confirm_' . $k;
				continue;
			}

			if ( ! empty( $pw_fields ) && in_array( $k, $pw_fields ) ) {
				continue;
			}

			if ( ! is_array( $v ) && strstr( $v, 'ultimatemember/temp' ) ) {
				$file = basename( $v );
				$v = um_user_uploads_uri() . $file;
			}

			if ( is_array( $v ) ) {
				$v = implode( ',', $v );
			}

			if ( $k == 'timestamp' ) {
				$k = __( 'date submitted', 'ultimate-member' );
				$v = date( "d M Y H:i", $v );
			}

			if ( $style ) {
				if ( ! $v ) {
					$v = __( '(empty)', 'ultimate-member' );
				}
				$output .= "<p><label>$k</label><span>$v</span></p>";
			} else {
				$output .= "$k: $v" . "<br />";
			}
		}
	}

	if ( $style ) {
		$output .= '</div>';
	}

	return $output;
}


/**
 * Show filtered social link
 *
 * @param $key
 * @param $match
 *
 * @return mixed|string|void
 */
function um_filtered_social_link( $key, $match ) {
	$value = um_profile( $key );
	$submatch = str_replace( 'https://', '', $match );
	$submatch = str_replace( 'http://', '', $submatch );
	if (strstr( $value, $submatch )) {
		$value = 'https://' . $value;
	} else if (strpos( $value, 'http' ) !== 0) {
		$value = $match . $value;
	}
	$value = str_replace( 'https://https://', 'https://', $value );
	$value = str_replace( 'http://https://', 'https://', $value );
	$value = str_replace( 'https://http://', 'https://', $value );

	return $value;
}


/**
 * Get filtered meta value after applying hooks
 *
 * @param $key
 * @param bool $data
 * @return mixed|string|void
 */
function um_filtered_value( $key, $data = false ) {
	$value = um_user( $key );

	if ( ! $data ) {
		$data = UM()->builtin()->get_specific_field( $key );
	}

	$type = ( isset( $data['type'] ) ) ? $data['type'] : '';

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_profile_field_filter_hook__
	 * @description Change or filter field value
	 * @input_vars
	 * [{"var":"$value","type":"string","desc":"Field Value"},
	 * {"var":"$data","type":"array","desc":"Field Data"},
	 * {"var":"$type","type":"string","desc":"Field Type"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_profile_field_filter_hook__', 'function_name', 10, 3 );
	 * @example
	 * <?php
	 * add_filter( 'um_profile_field_filter_hook__', 'my_profile_field', 10, 3 );
	 * function my_profile_field( $value, $data, $type ) {
	 *     // your code here
	 *     return $value;
	 * }
	 * ?>
	 */
	$value = apply_filters( "um_profile_field_filter_hook__", $value, $data, $type );

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_profile_field_filter_hook__{$key}
	 * @description Change or filter field value by field key ($key)
	 * @input_vars
	 * [{"var":"$value","type":"string","desc":"Field Value"},
	 * {"var":"$data","type":"array","desc":"Field Data"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_profile_field_filter_hook__{$key}', 'function_name', 10, 2 );
	 * @example
	 * <?php
	 * add_filter( 'um_profile_field_filter_hook__{$key}', 'my_profile_field', 10, 2 );
	 * function my_profile_field( $value, $data ) {
	 *     // your code here
	 *     return $value;
	 * }
	 * ?>
	 */
	$value = apply_filters( "um_profile_field_filter_hook__{$key}", $value, $data );

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_profile_field_filter_hook__{$type}
	 * @description Change or filter field value by field type ($type)
	 * @input_vars
	 * [{"var":"$value","type":"string","desc":"Field Value"},
	 * {"var":"$data","type":"array","desc":"Field Data"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_profile_field_filter_hook__{$type}', 'function_name', 10, 2 );
	 * @example
	 * <?php
	 * add_filter( 'um_profile_field_filter_hook__{$type}', 'my_profile_field', 10, 2 );
	 * function my_profile_field( $value, $data ) {
	 *     // your code here
	 *     return $value;
	 * }
	 * ?>
	 */
	$value = apply_filters( "um_profile_field_filter_hook__{$type}", $value, $data );
	$value = UM()->shortcodes()->emotize( $value );
	return $value;
}


/**
 * @return bool|int|null
 */
function um_profile_id() {

	if ( um_get_requested_user() ) {
		return um_get_requested_user();
	} else if (is_user_logged_in() && get_current_user_id()) {
		return get_current_user_id();
	}

	return 0;
}


/**
 * Check that temp upload is valid
 *
 * @param $url
 *
 * @return bool|string
 */
function um_is_temp_upload( $url ) {
    if( is_string( $url ) ) {
        $url = trim($url);
    }

	if (filter_var( $url, FILTER_VALIDATE_URL ) === false)
		$url = realpath( $url );

	if (!$url)
		return false;

	$url = explode( '/ultimatemember/temp/', $url );
	if (isset( $url[1] )) {

		if (strstr( $url[1], '../' ) || strstr( $url[1], '%' ))
			return false;

		$src = UM()->files()->upload_temp . $url[1];
		if (!file_exists( $src ))
			return false;

		return $src;
	}

	return false;
}


/**
 * Check that temp image is valid
 *
 * @param $url
 *
 * @return bool|string
 */
function um_is_temp_image( $url ) {
	$url = explode( '/ultimatemember/temp/', $url );
	if (isset( $url[1] )) {
		$src = UM()->files()->upload_temp . $url[1];
		if (!file_exists( $src ))
			return false;
		list( $width, $height, $type, $attr ) = @getimagesize( $src );
		if (isset( $width ) && isset( $height ))
			return $src;
	}

	return false;
}


/**
 * Get core page url
 *
 * @param $time1
 * @param $time2
 *
 * @return mixed|void
 */
function um_time_diff( $time1, $time2 ) {
	return UM()->datetime()->time_diff( $time1, $time2 );
}


/**
 * Get user's last login timestamp
 *
 * @param $user_id
 *
 * @return mixed|string
 */
function um_user_last_login_timestamp( $user_id ) {
	$value = get_user_meta( $user_id, '_um_last_login', true );
	if ($value)
		return $value;

	return '';
}


/**
 * Get user's last login (time diff)
 *
 * @param $user_id
 *
 * @return mixed|string|void
 */
function um_user_last_login( $user_id ) {
	$value = get_user_meta( $user_id, '_um_last_login', true );
	if ( $value ) {
		$value = um_time_diff( $value, current_time( 'timestamp' ) );
	} else {
		$value = '';
	}

	return $value;
}


/**
 * Get core page url
 *
 * @param $slug
 * @param bool $updated
 *
 * @return bool|false|mixed|string|void
 */
function um_get_core_page( $slug, $updated = false ) {
	$url = '';

	if ( isset( UM()->config()->permalinks[ $slug ] ) ) {
		$url = get_permalink( UM()->config()->permalinks[ $slug ] );
		if ( $updated )
			$url = add_query_arg( 'updated', esc_attr( $updated ), $url );
	}

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_get_core_page_filter
	 * @description Change UM core page URL
	 * @input_vars
	 * [{"var":"$url","type":"string","desc":"UM Page URL"},
	 * {"var":"$slug","type":"string","desc":"UM Page slug"},
	 * {"var":"$updated","type":"bool","desc":"Additional parameter"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_get_core_page_filter', 'function_name', 10, 3 );
	 * @example
	 * <?php
	 * add_filter( 'um_get_core_page_filter', 'my_core_page_url', 10, 3 );
	 * function my_core_page_url( $url, $slug, $updated ) {
	 *     // your code here
	 *     return $url;
	 * }
	 * ?>
	 */
	return apply_filters( 'um_get_core_page_filter', $url, $slug, $updated );
}


/**
 * Check if we are on a UM Core Page or not
 *
 * Default um core pages slugs
 * 'user', 'login', 'register', 'members', 'logout', 'account', 'password-reset'
 *
 * @param string $page UM core page slug
 *
 * @return bool
 */
function um_is_core_page( $page ) {
	global $post;

	if ( empty( $post ) ) {
		return false;
	}

	if ( isset( $post->ID ) && isset( UM()->config()->permalinks[ $page ] ) && $post->ID == UM()->config()->permalinks[ $page ] )
		return true;

	if ( isset( $post->ID ) && get_post_meta( $post->ID, '_um_wpml_' . $page, true ) == 1 )
		return true;

	if ( UM()->external_integrations()->is_wpml_active() ) {
		global $sitepress;
		if ( UM()->config()->permalinks[ $page ] == wpml_object_id_filter( $post->ID, 'page', true, $sitepress->get_default_language() ) ) {
			return true;
		}
	}

	if (isset( $post->ID )) {
		$_icl_lang_duplicate_of = get_post_meta( $post->ID, '_icl_lang_duplicate_of', true );

		if (isset( UM()->config()->permalinks[$page] ) && ( ( $_icl_lang_duplicate_of == UM()->config()->permalinks[$page] && !empty( $_icl_lang_duplicate_of ) ) || UM()->config()->permalinks[$page] == $post->ID ))
			return true;
	}

	return false;
}


/**
 * @param $post
 * @param $core_page
 *
 * @return bool
 */
function um_is_core_post( $post, $core_page ) {
	if (isset( $post->ID ) && isset( UM()->config()->permalinks[$core_page] ) && $post->ID == UM()->config()->permalinks[$core_page])
		return true;
	if (isset( $post->ID ) && get_post_meta( $post->ID, '_um_wpml_' . $core_page, true ) == 1)
		return true;

	if (isset( $post->ID )) {
		$_icl_lang_duplicate_of = get_post_meta( $post->ID, '_icl_lang_duplicate_of', true );

		if (isset( UM()->config()->permalinks[$core_page] ) && ( ( $_icl_lang_duplicate_of == UM()->config()->permalinks[$core_page] && !empty( $_icl_lang_duplicate_of ) ) || UM()->config()->permalinks[$core_page] == $post->ID ))
			return true;
	}

	return false;
}


/**
 * Check value of queried search in text input
 *
 * @param $filter
 * @param bool $echo
 *
 * @return mixed|string
 */
function um_queried_search_value( $filter, $echo = true ) {
	$value = '';
	if (isset( $_REQUEST['um_search'] )) {
		$query = UM()->permalinks()->get_query_array();
		if (isset( $query[$filter] ) && $query[$filter] != '') {
			$value = stripslashes_deep( $query[$filter] );
		}
	}

	if ($echo) {
		echo $value;

		return '';
	} else {
		return $value;
	}

}


/**
 * Check whether item in dropdown is selected in query-url
 *
 * @param $filter
 * @param $val
 */
function um_select_if_in_query_params( $filter, $val ) {
	$selected = false;

	if (isset( $_REQUEST['um_search'] )) {
		$query = UM()->permalinks()->get_query_array();

		if (isset( $query[$filter] ) && $val == $query[$filter])
			$selected = true;

		/**
		 * UM hook
		 *
		 * @type filter
		 * @title um_selected_if_in_query_params
		 * @description Make selected or unselected from query attribute
		 * @input_vars
		 * [{"var":"$selected","type":"bool","desc":"Selected or not"},
		 * {"var":"$filter","type":"string","desc":"Check by this filter in query"},
		 * {"var":"$val","type":"string","desc":"Field Value"}]
		 * @change_log
		 * ["Since: 2.0"]
		 * @usage add_filter( 'um_selected_if_in_query_params', 'function_name', 10, 3 );
		 * @example
		 * <?php
		 * add_filter( 'um_selected_if_in_query_params', 'my_selected_if_in_query_params', 10, 3 );
		 * function my_selected_if_in_query_params( $selected, $filter, $val ) {
		 *     // your code here
		 *     return $selected;
		 * }
		 * ?>
		 */
		$selected = apply_filters( 'um_selected_if_in_query_params', $selected, $filter, $val );
	}

	echo $selected ? 'selected="selected"' : '';
}


/**
 * Get styling defaults
 *
 * @param $mode
 *
 * @return array
 */
function um_styling_defaults( $mode ) {

	$new_arr = array();
	$core_form_meta_all = UM()->config()->core_form_meta_all;
	$core_global_meta_all = UM()->config()->core_global_meta_all;

	foreach ( $core_form_meta_all as $k => $v ) {
		$s = str_replace( $mode . '_', '', $k );
		if (strstr( $k, '_um_' . $mode . '_' ) && !in_array( $s, $core_global_meta_all )) {
			$a = str_replace( '_um_' . $mode . '_', '', $k );
			$b = str_replace( '_um_', '', $k );
			$new_arr[$a] = UM()->options()->get( $b );
		} else if (in_array( $k, $core_global_meta_all )) {
			$a = str_replace( '_um_', '', $k );
			$new_arr[$a] = UM()->options()->get( $a );
		}
	}

	return $new_arr;
}


/**
 * Get meta option default
 *
 * @param $id
 *
 * @return string
 */
function um_get_metadefault( $id ) {
	$core_form_meta_all = UM()->config()->core_form_meta_all;

	return isset( $core_form_meta_all['_um_' . $id] ) ? $core_form_meta_all['_um_' . $id] : '';
}


/**
 * Check if a legitimate password reset request is in action
 *
 * @return bool
 */
function um_requesting_password_reset() {
	if (um_is_core_page( 'password-reset' ) && isset( $_POST['_um_password_reset'] ) == 1)
		return true;

	return false;
}


/**
 * Check if a legitimate password change request is in action
 *
 *
 * @return bool
 */
function um_requesting_password_change() {
	if (um_is_core_page( 'account' ) && isset( $_POST['_um_account'] ) == 1)
		return true;
    else if (isset( $_POST['_um_password_change'] ) && $_POST['_um_password_change'] == 1)
		return true;

	return false;
}


/**
 * boolean for account page editing
 *
 * @return bool
 */
function um_submitting_account_page() {
	if (isset( $_POST['_um_account'] ) && $_POST['_um_account'] == 1 && is_user_logged_in())
		return true;

	return false;
}


/**
 * Get a user's display name
 *
 * @param $user_id
 *
 * @return string
 */
function um_get_display_name( $user_id ) {
	um_fetch_user( $user_id );
	$name = um_user( 'display_name' );
	um_reset_user();

	return $name;
}


/**
 * Get members to show in directory
 *
 * @param $argument
 *
 * @return mixed
 */
function um_members( $argument ) {
	return UM()->members()->results[ $argument ];
}


/**
 * Clears the user data. You need to fetch a user manually after using this function.
 *
 * @function um_reset_user_clean()
 *
 * @description This function is similar to um_reset_user() with a difference that it will not use the logged-in
 *     user data after resetting. It is a hard-reset function for all user data.
 *
 * @usage <?php um_reset_user_clean(); ?>
 *
 * @example You can reset user data by using the following line in your code
 *
 * <?php um_reset_user_clean(); ?>
 */
function um_reset_user_clean() {
	UM()->user()->reset( true );
}


/**
 * Clears the user data. If a user is logged in, the user data will be reset to that user's data
 *
 * @function um_reset_user()
 *
 * @description This function resets the current user. You can use it to reset user data after
 * retrieving the details of a specific user.
 *
 * @usage <?php um_reset_user(); ?>
 *
 * @example You can reset user data by using the following line in your code
 *
 * <?php um_reset_user(); ?>
 */
function um_reset_user() {
	UM()->user()->reset();
}


/**
 * Gets the queried user
 *
 * @return mixed
 */
function um_queried_user() {
	return get_query_var( 'um_user' );
}


/**
 * Sets the requested user
 *
 * @param $user_id
 */
function um_set_requested_user( $user_id ) {
	UM()->user()->target_id = $user_id;
}


/**
 * Gets the requested user
 *
 * @return bool|null
 */
function um_get_requested_user() {
	if ( ! empty( UM()->user()->target_id ) )
		return UM()->user()->target_id;

	return false;
}


/**
 * Remove edit profile args from url
 *
 * @param string $url
 *
 * @return mixed|string|void
 */
function um_edit_my_profile_cancel_uri( $url = '' ) {

	if ( empty( $url ) ) {
		$url = remove_query_arg( 'um_action' );
		$url = remove_query_arg( 'profiletab', $url );
		$url = add_query_arg( 'profiletab', 'main', $url );
	}

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_edit_profile_cancel_uri
	 * @description Change Edit Profile Cancel URL
	 * @input_vars
	 * [{"var":"$url","type":"string","desc":"Cancel URL"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_edit_profile_cancel_uri', 'function_name', 10, 1 );
	 * @example
	 * <?php
	 * add_filter( 'um_edit_profile_cancel_uri', 'my_edit_profile_cancel_uri', 10, 1 );
	 * function my_edit_profile_cancel_uri( $url ) {
	 *     // your code here
	 *     return $url;
	 * }
	 * ?>
	 */
	$url = apply_filters( 'um_edit_profile_cancel_uri', $url );

	return $url;
}


/**
 * boolean for profile edit page
 *
 * @return bool
 */
function um_is_on_edit_profile() {
	if (isset( $_REQUEST['profiletab'] ) && isset( $_REQUEST['um_action'] )) {
		if ($_REQUEST['profiletab'] == 'main' && $_REQUEST['um_action'] == 'edit') {
			return true;
		}
	}

	return false;
}


/**
 * Can view field
 *
 * @param $data
 *
 * @return bool
 */
function um_can_view_field( $data ) {

	if ( ! isset( UM()->fields()->set_mode ) ) {
		UM()->fields()->set_mode = '';
	}

	if ( isset( $data['public'] ) && UM()->fields()->set_mode != 'register' ) {

		if ( ! is_user_logged_in() && $data['public'] != '1' ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			$previous_user = um_user( 'ID' );
			um_fetch_user( get_current_user_id() );

			$current_user_roles = um_user( 'roles' );
			um_fetch_user( $previous_user );

			if ( $data['public'] == '-3' && ! um_is_user_himself() && ( empty( $current_user_roles ) || count( array_intersect( $current_user_roles, $data['roles'] ) ) <= 0 ) )
				return false;

			if ( ! um_is_user_himself() && $data['public'] == '-1' && ! UM()->roles()->um_user_can( 'can_edit_everyone' ) )
				return false;

			if ( $data['public'] == '-2' && $data['roles'] )
				if ( empty( $current_user_roles ) || count( array_intersect( $current_user_roles, $data['roles'] ) ) <= 0 )
					return false;
		}

	}

	return true;
}


/**
 * Checks if user can view profile
 *
 * @param $user_id
 *
 * @return bool
 */
function um_can_view_profile( $user_id ) {
	if ( ! um_user( 'can_view_all' ) && $user_id != get_current_user_id() && is_user_logged_in() ) {
		return false;
	}

	if ( UM()->roles()->um_current_user_can( 'edit', $user_id ) ) {
		return true;
	}

	if ( ! is_user_logged_in() ) {
		return ! UM()->user()->is_private_profile( $user_id );
	}

	if ( ! um_user( 'can_access_private_profile' ) && UM()->user()->is_private_profile( $user_id ) ) {
		return false;
	}

	$temp_id = um_user('ID');
	um_fetch_user( get_current_user_id() );

	if ( um_user( 'can_view_roles' ) && $user_id != get_current_user_id() ) {
		if ( count( array_intersect( UM()->roles()->get_all_user_roles( $user_id ), um_user( 'can_view_roles' ) ) ) <= 0 ) {
			um_fetch_user( $temp_id );
			return false;
		}
	}
	um_fetch_user( $temp_id );
	return true;
}


/**
 * boolean check for not same user
 *
 * @return bool
 */
function um_is_user_himself() {
	if (um_get_requested_user() && um_get_requested_user() != get_current_user_id())
		return false;

	return true;
}

/**
 * Can edit field
 *
 * @param $data
 *
 * @return bool
 */
function um_can_edit_field( $data ) {
	if (isset( UM()->fields()->editing ) && UM()->fields()->editing == true &&
		isset( UM()->fields()->set_mode ) && UM()->fields()->set_mode == 'profile'
	) {

		if (is_user_logged_in() && isset( $data['editable'] ) && $data['editable'] == 0) {

			if (isset( $data['public'] ) && $data['public'] == "-2") {
				return true;
			}

			if (um_user( 'can_edit_everyone' )) return true;
			if (um_is_user_himself() && !um_user( 'can_edit_everyone' )) {
				return true;
			}

			if (!um_is_user_himself() && !UM()->roles()->um_user_can( 'can_edit_everyone' ))
				return false;
		}

	}

	return true;

}


/**
 * Check if user is in his profile
 *
 * @return bool
 */
function um_is_myprofile() {
	if (get_current_user_id() && get_current_user_id() == um_get_requested_user()) return true;
	if (!um_get_requested_user() && um_is_core_page( 'user' ) && get_current_user_id()) return true;

	return false;
}


/**
 * Returns the edit profile link
 *
 * @return mixed|string|void
 */
function um_edit_profile_url() {
	if (um_is_core_page( 'user' )) {
		$url = UM()->permalinks()->get_current_url();
	} else {
		$url = um_user_profile_url();
	}

	$url = remove_query_arg( 'profiletab', $url );
	$url = remove_query_arg( 'subnav', $url );
	$url = add_query_arg( 'profiletab', 'main', $url );
	$url = add_query_arg( 'um_action', 'edit', $url );

	return $url;
}


/**
 * checks if user can edit his profile
 *
 * @return bool
 */
function um_can_edit_my_profile() {
	if (!is_user_logged_in()) return false;
	if (!um_user( 'can_edit_profile' )) return false;

	return true;
}


/**
 * Short for admin e-mail
 *
 * @return mixed|string|void
 */
function um_admin_email() {
	return UM()->options()->get( 'admin_email' );
}


/**
 * Get admin e-mails
 *
 * @return array
 */
function um_multi_admin_email() {
	$emails = UM()->options()->get( 'admin_email' );

	$emails_array = explode( ',', $emails );
	if ( ! empty( $emails_array ) ) {
		$emails_array = array_map( 'trim', $emails_array );
	}

	return $emails_array;
}


/**
 * Display a link to profile page
 *
 * @param int|bool $user_id
 *
 * @return bool|string
 */
function um_user_profile_url( $user_id = false ) {
	if ( ! $user_id ) {
		$user_id = um_user( 'ID' );
	}

	$url = UM()->user()->get_profile_link( $user_id );
	if ( empty( $url ) ) {
		//if empty profile slug - generate it and re-get profile URL
		UM()->user()->generate_profile_slug( $user_id );
		$url = UM()->user()->get_profile_link( $user_id );
	}

	return $url;
}


/**
 * Get all UM roles in array
 *
 * @return array
 */
function um_get_roles() {
	return UM()->roles()->get_roles();
}


/**
 * Sets a specific user and prepares profile data and user permissions and makes them accessible.
 *
 * @function um_fetch_user()
 *
 * @description This function sets a user and allow you to retrieve any information for the retrieved user
 *
 * @usage <?php um_fetch_user( $user_id ); ?>
 *
 * @param $user_id (numeric) (required) A user ID is required. This is the user's ID that you wish to set/retrieve
 *
 *
 * @example The example below will set user ID 5 prior to retrieving his profile information.
 *
 * <?php
 *
 * um_fetch_user(5);
 * echo um_user('display_name'); // returns the display name of user ID 5
 *
 * ?>
 *
 * @example In the following example you can fetch the profile of a logged-in user dynamically.
 *
 * <?php
 *
 * um_fetch_user( get_current_user_id() );
 * echo um_user('display_name'); // returns the display name of logged-in user
 *
 * ?>
 *
 */
function um_fetch_user( $user_id ) {
	UM()->user()->set( $user_id );
}


/**
 * Load profile key
 *
 * @param $key
 *
 * @return mixed|void
 */
function um_profile( $key ) {

	if ( ! empty( UM()->user()->profile[ $key ] ) ) {
		/**
		 * UM hook
		 *
		 * @type filter
		 * @title um_profile_{$key}__filter
		 * @description Change not empty profile field value
		 * @input_vars
		 * [{"var":"$value","type":"mixed","desc":"Profile Value"}]
		 * @change_log
		 * ["Since: 2.0"]
		 * @usage add_filter( 'um_profile_{$key}__filter', 'function_name', 10, 1 );
		 * @example
		 * <?php
		 * add_filter( 'um_profile_{$key}__filter', 'my_profile_value', 10, 1 );
		 * function my_profile_value( $value ) {
		 *     // your code here
		 *     return $value;
		 * }
		 * ?>
		 */
		$value = apply_filters( "um_profile_{$key}__filter", UM()->user()->profile[ $key ] );
	} else {
		/**
		 * UM hook
		 *
		 * @type filter
		 * @title um_profile_{$key}_empty__filter
		 * @description Change Profile field value if it's empty
		 * @input_vars
		 * [{"var":"$value","type":"mixed","desc":"Profile Value"}]
		 * @change_log
		 * ["Since: 2.0"]
		 * @usage add_filter( 'um_profile_{$key}_empty__filter', 'function_name', 10, 1 );
		 * @example
		 * <?php
		 * add_filter( 'um_profile_{$key}_empty__filter', 'my_profile_value', 10, 1 );
		 * function my_profile_value( $value ) {
		 *     // your code here
		 *     return $value;
		 * }
		 * ?>
		 */
		$value = apply_filters( "um_profile_{$key}_empty__filter", false );
	}

	return $value;
}


/**
 * Get youtube video ID from url
 *
 * @param $url
 *
 * @return bool
 */
function um_youtube_id_from_url( $url ) {
	$pattern =
		'%^# Match any youtube URL
		(?:https?://)?  # Optional scheme. Either http or https
		(?:www\.)?      # Optional www subdomain
		(?:             # Group host alternatives
		  youtu\.be/    # Either youtu.be,
		| youtube\.com  # or youtube.com
		  (?:           # Group path alternatives
			/embed/     # Either /embed/
		  | /v/         # or /v/
		  | /watch\?v=  # or /watch\?v=
		  )             # End path alternatives.
		)               # End host alternatives.
		([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
		$%x';
	$result = preg_match( $pattern, $url, $matches );
	if (false !== $result) {
		return $matches[1];
	}

	return false;
}


/**
 * user uploads uri
 *
 * @return string
 */
function um_user_uploads_uri() {
    UM()->files()->upload_baseurl = set_url_scheme( UM()->files()->upload_baseurl );

	$uri = UM()->files()->upload_baseurl . um_user( 'ID' ) . '/';

	return $uri;
}


/**
 * user uploads directory
 *
 * @return string
 */
function um_user_uploads_dir() {
	$uri = UM()->files()->upload_basedir . um_user( 'ID' ) . '/';

	return $uri;
}


/**
 * Find closest number in an array
 *
 * @param $array
 * @param $number
 *
 * @return mixed
 */
function um_closest_num( $array, $number ) {
	sort( $array );
	foreach ($array as $a) {
		if ($a >= $number) return $a;
	}

	return end( $array );
}


/**
 * get cover uri
 *
 * @param $image
 * @param $attrs
 *
 * @return bool|string
 */
function um_get_cover_uri( $image, $attrs ) {
	$uri = false;
	$ext = '.' . pathinfo( $image, PATHINFO_EXTENSION );
	if (file_exists( UM()->files()->upload_basedir . um_user( 'ID' ) . '/cover_photo' . $ext )) {
		$uri = um_user_uploads_uri() . 'cover_photo' . $ext . '?' . current_time( 'timestamp' );
	}
	if (file_exists( UM()->files()->upload_basedir . um_user( 'ID' ) . '/cover_photo-' . $attrs . $ext )) {
		$uri = um_user_uploads_uri() . 'cover_photo-' . $attrs . $ext . '?' . current_time( 'timestamp' );
	}

	return $uri;
}


/**
 * get avatar URL instead of image
 *
 * @param $get_avatar
 *
 * @return mixed
 */
function um_get_avatar_url( $get_avatar ) {
	preg_match( '/src="(.*?)"/i', $get_avatar, $matches );

	return $matches[1];
}


/**
 * get avatar uri
 *
 * @param $image
 * @param $attrs
 *
 * @return bool|string
 */
function um_get_avatar_uri( $image, $attrs ) {
	$uri = false;
	$find = false;
	$ext = '.' . pathinfo( $image, PATHINFO_EXTENSION );

	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_filter_avatar_cache_time
	 * @description Change Profile field value if it's empty
	 * @input_vars
	 * [{"var":"$timestamp","type":"timestamp","desc":"Avatar cache time"},
	 * {"var":"$user_id","type":"int","desc":"User ID"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_filter_avatar_cache_time', 'function_name', 10, 2 );
	 * @example
	 * <?php
	 * add_filter( 'um_filter_avatar_cache_time', 'my_avatar_cache_time', 10, 2 );
	 * function my_avatar_cache_time( $timestamp, $user_id ) {
	 *     // your code here
	 *     return $timestamp;
	 * }
	 * ?>
	 */
	$cache_time = apply_filters( 'um_filter_avatar_cache_time', current_time( 'timestamp' ), um_user( 'ID' ) );

	if( $attrs == 'original' && file_exists( um_user_uploads_dir() . "profile_photo{$ext}" ) ) {
        $uri = um_user_uploads_uri() . "profile_photo{$ext}";
    } else if (file_exists( um_user_uploads_dir() . "profile_photo-{$attrs}{$ext}" )) {
		$uri = um_user_uploads_uri() . "profile_photo-{$attrs}{$ext}";
	} else {
		$sizes = UM()->options()->get( 'photo_thumb_sizes' );
		if (is_array( $sizes )) $find = um_closest_num( $sizes, $attrs );

		if (file_exists( um_user_uploads_dir() . "profile_photo-{$find}{$ext}" )) {
			$uri = um_user_uploads_uri() . "profile_photo-{$find}{$ext}";
		} else if (file_exists( um_user_uploads_dir() . "profile_photo{$ext}" )) {
			$uri = um_user_uploads_uri() . "profile_photo{$ext}";
		}
	}

	if ( !empty( $cache_time ) ) {
		$uri .= "?{$cache_time}";
	}

	return $uri;
}


/**
 * Default avatar URL
 *
 * @return string
 */
function um_get_default_avatar_uri() {
	$uri = UM()->options()->get( 'default_avatar' );
	$uri = !empty( $uri['url'] ) ? $uri['url'] : '';
	if ( ! $uri ) {
		$uri = um_url . 'assets/img/default_avatar.jpg';
	}

	return set_url_scheme( $uri );
}


/**
 * get user avatar url
 *
 * @param $user_id
 * @param $size
 *
 * @return bool|string
 */
function um_get_user_avatar_data( $user_id = '', $size = '96' ) {
	if( empty( $user_id ) ) {
		$user_id = um_user( 'ID' );
	} else {
		um_fetch_user( $user_id );
	}

	$data = array(
		'user_id' => $user_id,
		'default' => um_get_default_avatar_uri(),
		'class' => 'gravatar avatar avatar-' . $size . ' um-avatar',
		'size' => $size
	);

	if ( $profile_photo = um_profile( 'profile_photo' ) ) {
		$data['url'] = um_get_avatar_uri( $profile_photo, $size );
		$data['type'] = 'upload';
		$data['class'] .= ' um-avatar-uploaded';
	} elseif ( $synced_profile_photo = um_user( 'synced_profile_photo' ) ) {
		$data['url'] = $synced_profile_photo;
		$data['type'] = 'sync';
		$data['class'] .= ' um-avatar-default';
	} elseif ( UM()->options()->get( 'use_gravatars' ) ) {
		$avatar_hash_id = get_user_meta( $user_id, 'synced_gravatar_hashed_id', true );
		$data['url'] = set_url_scheme( '//gravatar.com/avatar/' . $avatar_hash_id );
		$data['url'] = add_query_arg( 's', 400, $data['url'] );
		$rating = get_option( 'avatar_rating' );
		if ( ! empty( $rating ) ) {
			$data['url'] = add_query_arg( 'r', $rating, $data['url'] );
		}

		$gravatar_type = UM()->options()->get( 'use_um_gravatar_default_builtin_image' );
		if ( $gravatar_type == 'default' ) {
			if ( UM()->options()->get( 'use_um_gravatar_default_image' ) ) {
				$data['url'] = add_query_arg( 'd', $data['default'], $data['url'] );
			} else {
				$default = get_option( 'avatar_default', 'mystery' );
				if ( $default == 'gravatar_default' ) {
					$default = '';
				}
				$data['url'] = add_query_arg( 'd', $default, $data['url'] );
			}
		} else {
			$data['url'] = add_query_arg( 'd', $gravatar_type, $data['url'] );
		}

		$data['type'] = 'gravatar';
		$data['class'] .= ' um-avatar-gravatar';
	} else {
		$data['url'] = $data['default'];
		$data['type'] = 'default';
		$data['class'] .= ' um-avatar-default';
	}


	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_user_avatar_url_filter
	 * @description Change user avatar URL
	 * @input_vars
	 * [{"var":"$avatar_uri","type":"string","desc":"Avatar URL"},
	 * {"var":"$user_id","type":"int","desc":"User ID"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_user_avatar_url_filter', 'function_name', 10, 2 );
	 * @example
	 * <?php
	 * add_filter( 'um_user_avatar_url_filter', 'my_user_avatar_url', 10, 2 );
	 * function my_user_avatar_url( $avatar_uri ) {
	 *     // your code here
	 *     return $avatar_uri;
	 * }
	 * ?>
	 */
	$data['url'] = apply_filters( 'um_user_avatar_url_filter', $data['url'], $user_id, $data );
	/**
	 * UM hook
	 *
	 * @type filter
	 * @title um_avatar_image_alternate_text
	 * @description Change user display name on um_user function profile photo
	 * @input_vars
	 * [{"var":"$display_name","type":"string","desc":"User Display Name"}]
	 * @change_log
	 * ["Since: 2.0"]
	 * @usage add_filter( 'um_avatar_image_alternate_text', 'function_name', 10, 1 );
	 * @example
	 * <?php
	 * add_filter( 'um_avatar_image_alternate_text', 'my_avatar_image_alternate_text', 10, 1 );
	 * function my_avatar_image_alternate_text( $display_name ) {
	 *     // your code here
	 *     return $display_name;
	 * }
	 * ?>
	 */
	$data['alt'] = apply_filters( "um_avatar_image_alternate_text", um_user( "display_name" ), $data );

	return $data;
}


/**
 * get user avatar url
 *
 * @param $user_id
 * @param $size
 *
 * @return bool|string
 */
function um_get_user_avatar_url( $user_id = '', $size = '96' ) {
    $data = um_get_user_avatar_data( $user_id, $size );
    return $data['url'];
}


/**
 * default cover
 *
 * @return mixed|string|void
 */
function um_get_default_cover_uri() {
	$uri = UM()->options()->get( 'default_cover' );
	$uri = ! empty( $uri['url'] ) ? $uri['url'] : '';
	if ( $uri ) {

		/**
		 * UM hook
		 *
		 * @type filter
		 * @title um_get_default_cover_uri_filter
		 * @description Change Default Cover URL
		 * @input_vars
		 * [{"var":"$uri","type":"string","desc":"Default Cover URL"}]
		 * @change_log
		 * ["Since: 2.0"]
		 * @usage add_filter( 'um_get_default_cover_uri_filter', 'function_name', 10, 1 );
		 * @example
		 * <?php
		 * add_filter( 'um_get_default_cover_uri_filter', 'my_default_cover_uri', 10, 1 );
		 * function my_default_cover_uri( $uri ) {
		 *     // your code here
		 *     return $uri;
		 * }
		 * ?>
		 */
		return apply_filters( 'um_get_default_cover_uri_filter', $uri );
	}

	return '';
}


/**
 * @param $data
 * @param null $attrs
 *
 * @return string
 */
function um_user( $data, $attrs = null ) {

	switch ($data) {

		default:

			$value = um_profile( $data );

			$value = maybe_unserialize( $value );

			if (in_array( $data, array( 'role', 'gender' ) )) {
				if (is_array( $value )) {
					$value = implode( ",", $value );
				}

				return $value;
			}

			return $value;
			break;

		case 'user_email':

			$user_email_in_meta = get_user_meta( um_user( 'ID' ), 'user_email', true );
			if ($user_email_in_meta) {
				delete_user_meta( um_user( 'ID' ), 'user_email' );
			}

			$value = um_profile( $data );

			return $value;
			break;

		case 'first_name':
		case 'last_name':

			$name = um_profile( $data );

			if ( UM()->options()->get( 'force_display_name_capitlized' ) ) {
				$name = implode( '-', array_map( 'ucfirst', explode( '-', $name ) ) );
			}

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_user_{$data}_case
			 * @description Change user name on um_user function
			 * @input_vars
			 * [{"var":"$name","type":"string","desc":"User Name"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_filter( 'um_user_{$data}_case', 'function_name', 10, 1 );
			 * @example
			 * <?php
			 * add_filter( 'um_user_{$data}_case', 'my_user_case', 10, 1 );
			 * function my_user_case( $name ) {
			 *     // your code here
			 *     return $name;
			 * }
			 * ?>
			 */
			$name = apply_filters( "um_user_{$data}_case", $name );

			return $name;

			break;

		case 'full_name':

			if (um_user( 'first_name' ) && um_user( 'last_name' )) {
				$full_name = um_user( 'first_name' ) . ' ' . um_user( 'last_name' );
			} else {
				$full_name = um_user( 'display_name' );
			}

			$full_name = UM()->validation()->safe_name_in_url( $full_name );

			// update full_name changed
			if (um_profile( $data ) !== $full_name) {
				update_user_meta( um_user( 'ID' ), 'full_name', $full_name );
			}

			return $full_name;

			break;

		case 'first_and_last_name_initial':

			$f_and_l_initial = '';

			if (um_user( 'first_name' ) && um_user( 'last_name' )) {
				$initial = um_user( 'last_name' );
				$f_and_l_initial = um_user( 'first_name' ) . ' ' . $initial[0];
			} else {
				$f_and_l_initial = um_profile( $data );
			}

			$f_and_l_initial = UM()->validation()->safe_name_in_url( $f_and_l_initial );

			if ( UM()->options()->get( 'force_display_name_capitlized' ) ) {
				$name = implode( '-', array_map( 'ucfirst', explode( '-', $f_and_l_initial ) ) );
			} else {
				$name = $f_and_l_initial;
			}

			return $name;

			break;

		case 'display_name':

			$op = UM()->options()->get( 'display_name' );

			$name = '';


			if ($op == 'default') {
				$name = um_profile( 'display_name' );
			}

			if ($op == 'nickname') {
				$name = um_profile( 'nickname' );
			}

			if ($op == 'full_name') {
				if (um_user( 'first_name' ) && um_user( 'last_name' )) {
					$name = um_user( 'first_name' ) . ' ' . um_user( 'last_name' );
				} else {
					$name = um_profile( $data );
				}
				if (!$name) {
					$name = um_user( 'user_login' );
				}
			}

			if ($op == 'sur_name') {
				if (um_user( 'first_name' ) && um_user( 'last_name' )) {
					$name = um_user( 'last_name' ) . ' ' . um_user( 'first_name' );
				} else {
					$name = um_profile( $data );
				}
			}

			if ($op == 'first_name') {
				if (um_user( 'first_name' )) {
					$name = um_user( 'first_name' );
				} else {
					$name = um_profile( $data );
				}
			}

			if ($op == 'username') {
				$name = um_user( 'user_login' );
			}

			if ($op == 'initial_name') {
				if (um_user( 'first_name' ) && um_user( 'last_name' )) {
					$initial = um_user( 'last_name' );
					$name = um_user( 'first_name' ) . ' ' . $initial[0];
				} else {
					$name = um_profile( $data );
				}
			}

			if ($op == 'initial_name_f') {
				if (um_user( 'first_name' ) && um_user( 'last_name' )) {
					$initial = um_user( 'first_name' );
					$name = $initial[0] . ' ' . um_user( 'last_name' );
				} else {
					$name = um_profile( $data );
				}
			}


			if ($op == 'field' && UM()->options()->get( 'display_name_field' ) != '') {
				$fields = array_filter( preg_split( '/[,\s]+/', UM()->options()->get( 'display_name_field' ) ) );
				$name = '';

				foreach ($fields as $field) {
					if (um_profile( $field )) {
						$name .= um_profile( $field ) . ' ';
					} else if (um_user( $field )) {
						$name .= um_user( $field ) . ' ';
					}

				}
			}

			if ( UM()->options()->get( 'force_display_name_capitlized' ) ) {
				$name = implode( '-', array_map( 'ucfirst', explode( '-', $name ) ) );
			}

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_user_display_name_filter
			 * @description Change user display name on um_user function
			 * @input_vars
			 * [{"var":"$name","type":"string","desc":"User Name"},
			 * {"var":"$user_id","type":"int","desc":"User ID"},
			 * {"var":"$html","type":"bool","desc":"Is HTML"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_filter( 'um_user_display_name_filter', 'function_name', 10, 3 );
			 * @example
			 * <?php
			 * add_filter( 'um_user_display_name_filter', 'my_user_display_name', 10, 3 );
			 * function my_user_display_name( $name, $user_id, $html ) {
			 *     // your code here
			 *     return $name;
			 * }
			 * ?>
			 */
			return apply_filters( 'um_user_display_name_filter', $name, um_user( 'ID' ), ( $attrs == 'html' ) ? 1 : 0 );

			break;

		case 'role_select':
		case 'role_radio':

			return UM()->roles()->get_role_name( UM()->roles()->get_editable_priority_user_role( um_user( 'ID' ) ) );
			break;

		case 'submitted':
			$array = um_profile( $data );
			if (empty( $array )) return '';
			$array = unserialize( $array );

			return $array;
			break;

		case 'password_reset_link':
			return UM()->password()->reset_url();
			break;

		case 'account_activation_link':
			return UM()->permalinks()->activate_url();
			break;

		case 'profile_photo':
			$data = um_get_user_avatar_data( um_user( 'ID' ), $attrs );

			return '<img src="' . esc_attr($data['url']) . '" 
			    class="' . esc_attr($data['class']) . '" 
			    width="' . esc_attr($data['size']) . '"  
			    height="' . esc_attr($data['size']) . '" 
			    alt="' . esc_attr($data['alt']) . '"
			    data-default="' . esc_attr($data['default']) . '"
			    onerror="if(!this.getAttribute(\'data-load-error\')){this.setAttribute(\'data-load-error\', \'1\');this.setAttribute(\'src\', this.getAttribute(\'data-default\'));}" />'; //don't move attribute before src

			break;

		case 'cover_photo':

			$is_default = false;

			if (um_profile( 'cover_photo' )) {
				$cover_uri = um_get_cover_uri( um_profile( 'cover_photo' ), $attrs );
			} else if (um_profile( 'synced_cover_photo' )) {
				$cover_uri = um_profile( 'synced_cover_photo' );
			} else {
				$cover_uri = um_get_default_cover_uri();
				$is_default = true;
			}

			/**
			 * UM hook
			 *
			 * @type filter
			 * @title um_user_cover_photo_uri__filter
			 * @description Change user avatar URL
			 * @input_vars
			 * [{"var":"$cover_uri","type":"string","desc":"Cover URL"},
			 * {"var":"$is_default","type":"bool","desc":"Default or not"},
			 * {"var":"$attrs","type":"array","desc":"Attributes"}]
			 * @change_log
			 * ["Since: 2.0"]
			 * @usage add_filter( 'um_user_cover_photo_uri__filter', 'function_name', 10, 3 );
			 * @example
			 * <?php
			 * add_filter( 'um_user_cover_photo_uri__filter', 'my_user_cover_photo_uri', 10, 3 );
			 * function my_user_cover_photo_uri( $cover_uri, $is_default, $attrs ) {
			 *     // your code here
			 *     return $cover_uri;
			 * }
			 * ?>
			 */
			$cover_uri = apply_filters( 'um_user_cover_photo_uri__filter', $cover_uri, $is_default, $attrs );

			if ( $cover_uri )
				return '<img src="' . $cover_uri . '" alt="" />';

			if ( ! $cover_uri )
				return '';

			break;


			case 'user_url':

				$value = um_profile( $data );

				return $value;

			break;


	}

}


/**
 * Get server protocol
 *
 * @return  string
 */
function um_get_domain_protocol() {

	if (is_ssl()) {
		$protocol = 'https://';
	} else {
		$protocol = 'http://';
	}

	return $protocol;
}


/**
 * Set SSL to media URI
 *
 * @param  string $url
 *
 * @return string
 */
function um_secure_media_uri( $url ) {

	if (is_ssl()) {
		$url = str_replace( 'http:', 'https:', $url );
	}

	return $url;
}


/**
 * Force strings to UTF-8 encoded
 *
 * @param  mixed $value
 *
 * @return mixed
 */
function um_force_utf8_string( $value ) {

	if (is_array( $value )) {
		$arr_value = array();
		foreach ($value as $key => $value) {
			$utf8_decoded_value = utf8_decode( $value );

			if (mb_check_encoding( $utf8_decoded_value, 'UTF-8' )) {
				array_push( $arr_value, $utf8_decoded_value );
			} else {
				array_push( $arr_value, $value );
			}

		}

		return $arr_value;
	} else {

		$utf8_decoded_value = utf8_decode( $value );

		if (mb_check_encoding( $utf8_decoded_value, 'UTF-8' )) {
			return $utf8_decoded_value;
		}
	}

	return $value;
}


/**
 * Filters the search query.
 *
 * @param  string $search
 *
 * @return string
 */
function um_filter_search( $search ) {
	$search = trim( strip_tags( $search ) );
	$search = preg_replace( '/[^a-z \.\@\_\-]+/i', '', $search );

	return $search;
}


/**
 * Returns the user search query
 *
 * @return string
 */
function um_get_search_query() {
	$query = UM()->permalinks()->get_query_array();
	$search = isset( $query['search'] ) ? $query['search'] : '';

	return um_filter_search( $search );
}


/**
 * Returns the ultimate member search form
 *
 * @return string
 */
function um_get_search_form() {
	return do_shortcode( '[ultimatemember_searchform]' );
}


/**
 * Display the search form.
 *
 */
function um_search_form() {
	echo um_get_search_form();
}


/**
 * Get user host
 *
 * Returns the webhost this site is using if possible
 *
 * @since 1.3.68
 * @return mixed string $host if detected, false otherwise
 */
function um_get_host() {
	$host = false;

	if (defined( 'WPE_APIKEY' )) {
		$host = 'WP Engine';
	} else if (defined( 'PAGELYBIN' )) {
		$host = 'Pagely';
	} else if (DB_HOST == 'localhost:/tmp/mysql5.sock') {
		$host = 'ICDSoft';
	} else if (DB_HOST == 'mysqlv5') {
		$host = 'NetworkSolutions';
	} else if (strpos( DB_HOST, 'ipagemysql.com' ) !== false) {
		$host = 'iPage';
	} else if (strpos( DB_HOST, 'ipowermysql.com' ) !== false) {
		$host = 'IPower';
	} else if (strpos( DB_HOST, '.gridserver.com' ) !== false) {
		$host = 'MediaTemple Grid';
	} else if (strpos( DB_HOST, '.pair.com' ) !== false) {
		$host = 'pair Networks';
	} else if (strpos( DB_HOST, '.stabletransit.com' ) !== false) {
		$host = 'Rackspace Cloud';
	} else if (strpos( DB_HOST, '.sysfix.eu' ) !== false) {
		$host = 'SysFix.eu Power Hosting';
	} else if (strpos( $_SERVER['SERVER_NAME'], 'Flywheel' ) !== false) {
		$host = 'Flywheel';
	} else {
		// Adding a general fallback for data gathering
		$host = 'DBH: ' . DB_HOST . ', SRV: ' . $_SERVER['SERVER_NAME'];
	}

	return $host;
}


/**
 * Let To Num
 *
 * Does Size Conversions
 *
 * @since 1.3.68
 * @author Chris Christoff
 *
 * @param string $v
 *
 * @return int|string
 */
function um_let_to_num( $v ) {
	$l = substr( $v, -1 );
	$ret = substr( $v, 0, -1 );

	switch (strtoupper( $l )) {
		case 'P': // fall-through
		case 'T': // fall-through
		case 'G': // fall-through
		case 'M': // fall-through
		case 'K': // fall-through
			$ret *= 1024;
			break;
		default:
			break;
	}

	return $ret;
}


/**
 * Check if we are on UM page
 *
 * @return bool
 */
function is_ultimatemember() {
	global $post;

	if ( isset( $post->ID ) && in_array( $post->ID, UM()->config()->permalinks ) )
		return true;

	return false;
}


/**
 * Maybe set empty time limit
 */
function um_maybe_unset_time_limit() {
	if ( ! ini_get( 'safe_mode' ) ) {
		@set_time_limit(0);
	}
}