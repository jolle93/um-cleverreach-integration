<?php

/**
 * Plugin Name:     FACT Ultimate Member - CleverReach Integration
 * Description:     Extension to Ultimate Member to extend the standard register formular with a newsletter registration
 * Version:         1.0.0
 * Requires PHP:    8.1
 * Author:          Julian Paul
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/jolle93/fact-um-double-optin-and-admin-approval
 * Plugin URI:      https://github.com/jolle93/fact-um-double-optin-and-admin-approval
 * Update URI:      https://github.com/jolle93/fact-um-double-optin-and-admin-approval
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.9.1
 */
include('rest_client.php');

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'UM' ) ) {
	return;
}

class UmCrIntegration {

	private string $groupId = "525499";
	private string $formId = "396876";
	private rest $rest;

	public function __construct() {
		$this->rest = new rest( "https://rest.cleverreach.com/v3" );
		add_action( 'um_submit_form_errors_hook', array($this,'checkForNLregister'), 10, 1 );
	}

	function checkForNLregister( $submitted_data ) {
		if ( empty( $submitted_data ) || !isset( $submitted_data['register_for_nl'] )) {
			return;
		}

		$new_user = $this->createNewUser( $submitted_data );
		$this->rest->setAuthMode( "bearer", $this->rest->getAccessToken() );
		if ( $success = $this->rest->post( "/groups.json/$this->groupId/receivers", $new_user ) ) {
			$this->rest->post( "/forms.json/$this->formId/send/activate", array(
					"email"   => $new_user["email"],
					"doidata" => array(
						"user_ip"    => $_SERVER["REMOTE_ADDR"],
						"referer"    => $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"],
						"user_agent" => $_SERVER["HTTP_USER_AGENT"]
					)
				)
			);
		}
	}

	function createNewUser( $submitted_data ): array {
		return array(
			"email"      => $submitted_data['user_email'],
			"registered" => time(),  //current date
			"activated"  => 0,       //NOT active, will be set by DOI
			"source"     => $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"],
			"attributes" => array(
				"firstname" => $submitted_data['first_name'],
				"lastname"  => $submitted_data['last_name'],
			)
		);

	}
}

new UmCrIntegration();