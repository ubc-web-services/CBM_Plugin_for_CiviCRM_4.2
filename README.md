CBM_Plugin_for_CiviCRM_4.2
==========================
Created by UBC IT Web Services to integrate UBC E-Payment gateway with CiviCRM v 4.2

Tested on CiviCRM 4.4.2 on Dec 6th, 2013 - Worked as expected - Dave K

Tested on CiviCRM 4.6.8 on Sep 18th, 2015 - Worked as expected - Dave K

Tested on CiviCRM 4.6.19 on Jan 25th, 2017 - Changes made, and project tagged (keep reading) - Dave K



######################################
Error Occurring on CiviCRM version 4.6
--------------------------------------
After setting up the payment processor and running through the payment workflow, you might get this error:

Could not load the settings file at: /www_data/aegir/platforms/drupal-7.##/sites/domain.ubc.ca/modules/contrib/civicrm/../..//default/civicrm.settings.php

Insert:
define( 'CIVICRM_CONFDIR', '/www_data/aegir/platforms/drupal-7.##/sites/domain.ubc.ca/' );
into civicrm_config.php, above
(aprox line 55) if ( defined( 'CIVICRM_CONFDIR' ) && ! isset( $confdir ) ) {

######################################



TAGS!!
======

Note: this project has tags that correspond with versions of CiviCRM

tag: 4.6.8 is good up to CiviCRM 4.6.8

tag: 4.6.19 has a small change for CiviCRM code base changes in 4.6.19.  This plugin has not been tested on versions in between 4.6.8 and 4.6.19


