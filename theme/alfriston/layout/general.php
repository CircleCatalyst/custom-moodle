<?php echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes() ?>>
<head>
    <title><?php echo $PAGE->title ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->pix_url('favicon', 'theme')?>" />
    <meta name="description" content="<?php p(strip_tags(format_text($SITE->summary, FORMAT_HTML))) ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo $OUTPUT->standard_head_html() ?>

	<!--[if lt IE 9]>
		<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script> 
		<style type="text/css" media="screen">
			#header, #slideshow { border-bottom: 1px solid #464646; }
			#navigation li ul { border: 1px solid #464646; border-top: none; }
		</style>
	<![endif]-->
	
	
</head>
	


<body id="<?php p($PAGE->bodyid) ?>" class="<?php p($PAGE->bodyclasses.' '.join(' ', $bodyclasses)) ?>">
<?php echo $OUTPUT->standard_top_of_body_html() ?>
	
	<div id="header">
		
		<div class="wrap">
			
			<h1><a href="<?php echo $CFG->wwwroot; ?>">Alfriston College</a></h1>
				
			<h2><?php echo isset($PAGE->theme->settings->tagline) ? $PAGE->theme->settings->tagline : 'Zest for Learning - "Te Ihi ki te Ako"'; ?></h2>
			
		</div>
		
	</div>
	
	<table id="wrapper" class="fluid" border="0" cellpadding="0" celllspacing="0">
		
		<tr>
			<td id="main-content" valign="top">
				
				<div class="navi">
					<div class="breadcrumb"><?php echo $OUTPUT->navbar(); ?></div>
					<div class="navbutton"> <?php echo $PAGE->button; ?></div>
				</div>
				
				<div class="content">
					<?php // print_r($PAGE); ?>
					<?php echo $OUTPUT->main_content() ?>
				</div>
				
			</td>
		
		
			<td id="right-content-wrapper" valign="top">
				<div id="right-content">
					
						<?php
	                        echo $OUTPUT->login_info();
	                        echo $OUTPUT->lang_menu();
	                        echo $PAGE->headingmenu;
							if($PAGE->blocks->region_has_content('side-post', $OUTPUT)) echo $OUTPUT->blocks_for_region('side-post'); 
						?>
					
				</div>
			</td>
		</tr>
		
	</table>
	
	<div id="footer">
		
		<div class="wrap">
			
			<div class="col">
				<h2>Zest for Learning <br /> "Te Ihi ki te Ako"</h2>
			</div>
		
			<div class="col">
				<h3>Physical Address</h3>
				<p>550 Porchester Road <br />
					Randwick Park <br />
					Manukau 21058 <br />
					New Zealand
				</p>
			</div>
			
			<div class="col">
				<h3>Postal Address</h3>
				<p>P O Box 75448 <br />
					Manurewa <br />
					Manukau 2243 <br />
					New Zealand
				</p>
			</div>
		
			<div class="col phone">
				<p>Phone: +64 9 269 0080 <br />
					Fax: +64 9 269 0083 <br />
					<a href="mailto:info@alfristoncollege.school.nz">info@alfristoncollege.school.nz</a> <br />
					<a href="http://www.alfristoncollege.school.nz">www.alfristoncollege.school.nz</a> <br />
					<a href="http://www.ac.school.nz">www.ac.school.nz</a> 
				</p>
			</div>
		
			<div class="col branding">
				<h1><a href="<?php echo $CFG->wwwroot; ?>">Alfriston College</a></h1>
			</div>
		
			<p class="byline">&copy; <?=date('Y')?> Alfriston College. All rights reserved. | <a href="http://www.alfristoncollege.school.nz">www.alfristoncollege.school.nz</a> | <a href="#" id="top">Top of Page</a></p>

			<p id="inboxdesign">KAMAR/Moodle by <a href="http://www.inboxdesign.co.nz/">Inbox Design</a>.</p>
			
		</div>
		
	</div>
	
<?php echo $OUTPUT->standard_end_of_body_html() ?>
</body>
</html>