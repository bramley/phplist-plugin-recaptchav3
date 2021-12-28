# reCAPTCHA V3 Plugin #

## Description ##

This plugin provides Google reCAPTCHA V3 on subscribe forms. See https://www.google.com/recaptcha/intro/index.html
for information on how reCAPTCHA works.

## Installation ##

### Dependencies ###

The plugin requires phplist version 3.3.0 or later.

Requires php version 5.4 or later.

The plugin also requires CommonPlugin to be installed, see https://resources.phplist.com/plugin/common

At least one of the curl extension, the openssl extension, or the ini setting 'allow_url_fopen' must be enabled.

You must also create an API key to use reCAPTCHA, then enter the site key and the secret key into the plugin's settings.

### Install through phplist ###
Install on the Manage Plugins page (menu Config > Plugins) using the package URL
`https://github.com/bramley/phplist-plugin-recaptchav3/archive/master.zip`

### Usage ###

For guidance on configuring and using the plugin see the documentation page https://resources.phplist.com/plugin/recaptchav3

## Version history ##

    version         Description
    1.1.0+20211228  Restructure directories
    1.0.0+20211228  First release
