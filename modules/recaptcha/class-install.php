<?php
namespace umm\recaptcha;


if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Class Install
 *
 * @package umm\recaptcha
 */
class Install {


	/**
	 * @var array Default module settings
	 */
	var $settings_defaults;


	/**
	 * Install constructor.
	 */
	function __construct() {
		//settings defaults
		$this->settings_defaults = [
			'g_recaptcha_status'            => 1,

			/* reCAPTCHA v3 */
			'g_reCAPTCHA_site_key'          => '',
			'g_reCAPTCHA_secret_key'        => '',

			/* reCAPTCHA v2 */
			'g_recaptcha_sitekey'           => '',
			'g_recaptcha_secretkey'         => '',

			'g_recaptcha_language_code'     => 'en',
			'g_recaptcha_theme'             => 'light',
			'g_recaptcha_type'              => 'image',
			'g_recaptcha_size'              => 'normal',
			'g_recaptcha_password_reset'    => 0,
		];
	}


	/**
	 *
	 */
	function start() {
		UM()->options()->set_defaults( $this->settings_defaults );
	}
}