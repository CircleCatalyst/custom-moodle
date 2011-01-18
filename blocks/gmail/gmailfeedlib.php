<?php
/**
* Copyright (C) 2009  Moodlerooms Inc.
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see http://opensource.org/licenses/gpl-3.0.html.
* 
* @copyright  Copyright (c) 2009 Moodlerooms Inc. (http://www.moodlerooms.com)
* @license    http://opensource.org/licenses/gpl-3.0.html     GNU Public License
* @author Chris Stones
*/
 
 
/**
 * Google GMail Atom Feed (Still no OAuth Access)
 *
 * Accessing the GMail Atom feed with OAuth enables real time updating
 * and no relience on remembering a users password at login.
 * 
 * I don't believe Zend Gdata api's have support for OAuth just yet. or it's buggy in it's current form.
 * 
 * Helpful docs:
 *    - http://code.google.com/apis/accounts/docs/OAuth.html
 *   - http://framework.zend.com/manual/en/zend.gdata.html#zend.gdata.introduction.getfeed
 *   - http://framework.zend.com/manual/en/zend.http.response.html (we want the raw response)
 * 
 *   - adding a gmail GApp
 *   - http://framework.zend.com/manual/en/zend.gdata.html#zend.gdata.introduction.creation
 * 
 *   Might Try
 * @link   http://code.google.com/apis/accounts/AuthForWebApps.html
 * 
 * @author Chris Stones
 * @version $Id$
 * @package block_gmail
 **/
 
 // http://oauth.googlecode.com/svn/code/php/
 //http://groups.google.com/group/google-apps-apis/browse_thread/thread/3faf624fd4412e95
 
 
//require_once("../config.php");
//require_once($CFG->dirroot.'/blocks/gdata/gapps.php');

///**
// * Set include path so Zend Library functions properly
// **/
//
//$zendlibpath = $CFG->dirroot.'/blocks/gdata';
//$includepath = get_include_path();
//if (strpos($includepath, $zendlibpath) === false) {
//    set_include_path($includepath.PATH_SEPARATOR.$zendlibpath);
//}
//
///**
// * Dependencies
// **/
//
//require_once($CFG->dirroot.'/blocks/gdata/exception.php');
//require_once($CFG->dirroot.'/blocks/gdata/Zend/Gdata/Gapps.php');
//require_once($CFG->dirroot.'/blocks/gdata/Zend/Gdata/ClientLogin.php');

//class blocks_gmail_gapp extends blocks_gdata_gapps {
//    
//    public $feed;
//    
//       /**
//     * Constructor - makes sure our
//     * configs are in place and can
//     * connect to Google Apps for us
//     *
//     * @param boolean $autoconnect Automatically connect to Google Apps
//     * @return void
//     **/
//    public function __construct($autoconnect = true) {
//        if (!$config = get_config('blocks/gdata')) {
//            throw new blocks_gdata_exception('notconfigured');
//        }
//
//        $this->config = new stdClass;
//        foreach ($this->requiredconfig as $name) {
//            if (!isset($config->$name)) {
//                throw new blocks_gdata_exception('missingrequiredconfig', 'block_gdata', $name);
//            }
//            $this->config->$name = $config->$name;
//        }
//
//        $autoconnect and $this->gapps_connect();
//    }
//
//    /**
//     * Connect to Google Apps using
//     * our config credentials
//     *
//     * @return void
//     * @throws blocks_gdata_exception
//     **/
//    public function gapps_connect() {
//        try {
//            $client = Zend_Gdata_ClientLogin::getHttpClient("{$this->config->username}@{$this->config->domain}", $this->config->password, Zend_Gdata_Gapps::AUTH_SERVICE_NAME);
//            $this->service = new Zend_Gdata_Gapps($client, $this->config->domain);
//        } catch (Zend_Gdata_App_AuthException $e) {
//            throw new blocks_gdata_exception('authfailed');
//        } catch (Zend_Gdata_App_Exception $e) {
//            throw new blocks_gdata_exception('gappserror', 'block_gdata', $e->getMessage());
//        }
//    }
//    
//    
//    /**
//     * Return RAW GMail Feed for username
//     */
//     public function gapps_gmail_feed($username) {
//        
//        // OAuth obtain the token for $username
//        
//        // And ask for the https://mail.google.com/mail/feed/atom WITH that token
//        // The token should pull that users feed info
//        
//        
//        
//        // $this->config->domain
//        
//        // Who does Google know Which person we want the feed from?
//        $rsrc = 'https://'.$username.'@'.$this->config->domain.'@mail.google.com/mail/feed/atom';
//        
//       // string Zend_Http_Response::extractBody($response_str): Extract and return the HTTP response body from $response_str
////        
////        // import feed prob fails since Zend Code isn't designed to support it
////        // malformed request
////        $feed = Zend_Feed::import($rsrc);
////        assert($feed instanceof Zend_Feed_Abstract);
////
////        // dump the feed to standard output
////         $this->feed->saveXML();
////        
////        // send http headers and dump the feed
////        $this->feed->send();
//        
//        //===
//        
////        $gdata = new Zend_Gdata();
////    $query = new Zend_Gdata_Query('https://mail.google.com/mail/feed/atom');
////    $query->setMaxResults(10);
////    $this->feed = $gdata->getFeed($query);
////    
////    print $this->feed->saveXML();
// 
//  // return  $this->feed->send();
//  
//  // might try AuthSub test
//  //http://code.google.com/apis/accounts/AuthForWebApps.html
//
//     }
//}


function set_gmail_feed($username,$domain,$password) {
	global $CFG,$USER,$SESSION; // I'm expecting session to be set by now But it might get reset...
	
    
    // TODO: if moodle user is not the same as google user
    //       use the mapping
    
	// https request the link
	// https://mail.google.com/mail/feed/atom
	$username_dom = $username.'@'.$domain;//'cstones@mroomsdev.com';
	$username_dom = urlencode($username_dom);
	$password = $password;//"a344616";
	
	// Init cURL
	$url = 'https://'.$username_dom.':'.$password.'@mail.google.com/mail/feed/atom';
	$c = curl_init($url);
	
	$headers = array(
	"Host: mail.google.com",
	"Date: ".date(DATE_RFC822),
	);
	
	curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_ANY); // use authentication Should select BASIC auth
	curl_setopt($c, CURLOPT_HTTPHEADER, $headers);   
	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);      // We need to process the string results so disable direct output
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);      // Follow redirects
	curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 1);
	curl_setopt($c, CURLOPT_UNRESTRICTED_AUTH, 1);   // always stay Authorized
	curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 1);
	
    
    // Warn to only enable this debugging if absolutly necessary.
    if (debugging('',DEBUG_DEVELOPER) ) {  
        $SESSION->gmailfeedpw = $password;
        //curl_setopt($c, CURLOPT_HEADER, true); // include headers for debugging
    }

	$str = curl_exec($c); // Get it
	$SESSION->gmailfeed = base64_encode($str); // Store the feed data since we won't store passwords... yet
    
    curl_close($c); // Close the curl stream
	
	//@header("content-type: text/xml");
	
	// IF returned "Unauthorized" check headers for the header codes
	// Let the code know!
	
    // TODO: add logging code when debugging is turned on
	// TODO: for debugging(  DEBUG_DEVELOPER add feed watcher and refresh check
	
	// str has a lot of data in it Check for HEADERS INSTEAD
//	if( FALSE == stripos($str,'Unauthorized')) {
//		// No Feed returned
//		$SESSION->gmailfeed = base64_encode('unauthorized');
//	} else {
//		$SESSION->gmailfeed = base64_encode($str);
//	}


}

?>
