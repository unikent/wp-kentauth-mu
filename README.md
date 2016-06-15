# SSO for wordpress multisite

## installation

should be installed as an MU-Plugin in the /wp-content/mu-plugins/ folder.

the `simplesamlphp/cert` folder contains a `config-sample.php` this is a template for a **required** `config.php` file. The values should be changed in each environment.
This folder also requires a `saml.crt` and `saml.pem` certificate files, again these should be unique to each environment.

## Super-Admin provisioning

Super admins are provistions/deprovisioned by presence/absence of a "unikentadminresource" attribute of "wp-admin" being recieved from SSO.
Contact SIT to add/remove this attribute for a user.
