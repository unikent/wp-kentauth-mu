# SSO for wordpress multisite

## installation

should be installed as an MU-Plugin in the /wp-content/mu-plugins/ folder.

the `simplesamlphp/cert` folder contains a `config-sample.php` this is a template for a **required** `config.php` file. The values should be changed in each environment.
This folder also requires a `saml.crt` and `saml.pem` certificate files, again these should be unique to each environment.

## Super-Admin provisioning

Super admins are provistions/deprovisioned by presence/absence of a "unikentadminresource" attribute of "wp-admin" being recieved from SSO.
Contact SIT to add/remove this attribute for a user.

## SSO Bypass

The SSO proccess can be bypassed (to use the standard wordpress login proccess) by navigating to {base site url}/wp-login.php?local_login=1
This proccess will only work for the pressidium support account and the webdev base account, all other accounts **MUST** use SSO.

