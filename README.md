CBM_Plugin_for_CiviCRM_4.2
==========================
Created by UBC IT Web Services to integrate UBC E-Payment gateway with CiviCRM v 4.2

Tested on CiviCRM 4.4.2 on Dec 6th, 2013 - Worked as expected - Dave K

Tested on CiviCRM 4.6.8 on Sep 18th, 2015 - Worked as expected - Dave K


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