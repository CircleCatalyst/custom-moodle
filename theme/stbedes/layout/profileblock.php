<table border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td><div class="header-profilename" id="header-profilename">
    <?PHP
	
if (!isloggedin() or isguestuser()) {
echo get_string('loggedinnot').'<br/>';
    echo '<a href="'.$CFG->wwwroot.'/auth/saml/" title="Click here to login via SSO">Click here to login</a>';

} else {
echo 'You are logged in as<br /><a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&amp;course='.$COURSE->id.'">'.$USER->firstname.' '.$USER->lastname.'</a>';
}		


?>
    </div>
    <div class="header-profileoptions" id="header-profileoptions">
    
    
    

 <?PHP
				
if (isloggedin()) {
echo '<ul>';			
echo '<li><a href="'.$CFG->wwwroot.'/login/logout.php?sesskey='.sesskey().'">'.get_string('logout').'</a></li>';
echo '</ul>';

}
?>


    
    </div>
    </td>
    <td width="90" height="90">
        <?PHP

    if (isloggedin()) {
        echo '<div class="header-profilepic" id="header-profilepic">';
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&amp;course='.$COURSE->id.'"><img src="'.$CFG->wwwroot.'/user/pix.php?file=/'.$USER->id.'/f1.jpg" width="70px" height="70px" title="Profile Picture" alt="Profile Picture" /></a>'; 
        echo '</div>';
    }

?>
      </td>
  </tr>
</table>
