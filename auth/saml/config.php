<?php 
/**
 * config.php - config file for auth/saml based SAML 2.0 login
 * 
 * make sure that you define both the SimpleSAMLphp lib directory and config
 * directory for the associated SP and also specify the IdP that it will talk to
 * 
 * 
 * 
 * @originalauthor Martin Dougiamas
 * @author Erlend Strømsvik - Ny Media AS 
 * @author Piers Harding - made quite a number of changes
 * @version 1.0
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package auth/saml
 */


$SIMPLESAMLPHP_LIB = '/data/u00/www/htdocs/simplesaml';
$SIMPLESAMLPHP_CONFIG = '/data/u00/www/htdocs/simplesaml/config';
$SIMPLESAMLPHP_SP = 'default-sp';
//$SIMPLESAMLPHP_RETURN_TO = 'http://some.other.target'; // for when you need to override RelayState
//$SIMPLESAMLPHP_RETURN_TO = null;
$SIMPLESAMLPHP_RETURN_TO = 'http://'.$_SERVER['HTTP_HOST'].'/auth/saml/';


// change this to something specific if you don't want users to be sent to Moodle $CFG->wwwroot when logout is completed
// ## BK: the above is BS. The redirect goes to '/' which is www004.kncluster or its mates, use this instead:
$SIMPLESAMLPHP_LOGOUT_LINK = 'http://'.$_SERVER['HTTP_HOST']; 
