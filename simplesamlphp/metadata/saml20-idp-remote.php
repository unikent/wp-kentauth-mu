<?php
/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote 
 */

/*
 * Guest IdP. allows users to sign up and register. Great for testing!
 */
$metadata['https://sso.id.kent.ac.uk/idp'] = array(
	'name' => array(
		'en' => 'sso.id.kent.ac.uk'
	),
	'description'          => 'University of Kent SSO',

	'SingleSignOnService'  => 'https://sso.id.kent.ac.uk/idp/saml2/idp/SSOService.php',
	'SingleLogoutService'  => 'https://sso.id.kent.ac.uk/idp/saml2/idp/SingleLogoutService.php',
	'certFingerprint'      => '6ca587d0cad1566c4f7edc447e399f2f0be0aa2b'
);

