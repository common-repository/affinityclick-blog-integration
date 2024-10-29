<?php

include_once('configuration.php');

/*
Plugin Name: AffinityClick
Plugin URI: http://www.affinityclick.com/ca/
Description: AffinityClick helps web publishers, retailers, marketing and advertising agencies and consumers connect in a more meaningful, contextually relevant way. Together, we form a national network of content providers and trusted brand-name retailers. AffinityClick works by automatically matching the content of a publisher's website or blog posts with contextually relevant product placements.
Version: 1.3
Author: Affinity Click
*/


//this is for the account selector in the settings. must be added to the admin head
add_action('admin_head', 'AffinityClick_AccountSelector_javascript');

//gets called when wp is building the admin menu. tells it what the AC menu looks like
add_action('admin_menu', 'AffinityClick_SetupMenu');

//whenever widgets are initialized, add ours
add_action('widgets_init', 'AffinityClick_installWidgets');

//check if the user's settings are set properly and displays a notice. probably doesn't need to be called
//checkApiKey, but hopefully at some point the username/password code will be changed to
//oauth+key
add_action('admin_notices', 'AffinityClick_checkAPIKey');

//css hack to remove the extra submenu. I can't get a clear answer from anybody in wordpress why this happens
//so this is a fallback.
add_action('admin_print_styles', 'AffinityClick_submenu_fix');

//this is the function that's called via javascript+wp when the user selects an account in the settings.
//the action is custom created here.
add_action('wp_ajax_AffinityClick_AccountSelector', 'AffinityClick_AccountSelector_callback');

//wordpress has a very strange 'curiousity' where it will print an extra submenu. apparently they refuse
//to accept its a bug. fix it by hiding using css (unlike js, it wont throw an error if it fails)
function AffinityClick_submenu_fix()
{
	//separating the two here for cleanliness and extensibility.
	$the_style = "li.toplevel_page_affinity-click-top-menu * li.wp-first-item {display: none; }";
	
	echo "<style type='text/css'>$the_style</style>";
}

//this just dumps the event handler for the account selector button into the head of the settings page
function AffinityClick_AccountSelector_javascript()
{
	echo "<script type=\"text/javascript\" >
	jQuery(document).ready(function() {
		jQuery('#SetAccountButton').click(function(){
			
			
			jQuery('#wpbody-content').prepend(\"<div class='update-nag'><p><strong>Settings Changed Successfully</strong></p></div>\");
			var data = {
				action: 'AffinityClick_AccountSelector',
				account: jQuery('#account_key').val()
			};

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				//noop
			});
		});
	});
	</script>";
}

//this is the code executed by wordpress when the javascript above is triggered.
//this function must end with a die() at some point. the result is not visible unless
//the user has debug turned on.
function AffinityClick_AccountSelector_callback()
{
	global $AffinityClick, $wpdb; // this is how you get access to the database. probably don't need it here?

	$account_key = $_POST['account'];
	
	$username = get_option("affinityclick_email");
	$password = get_option("affinityclick_password");
	
	$login_url = $AffinityClick->getUrlFor('api')."/login.json?username=$username&password=$password";
	$result = wp_remote_get($login_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>array()));
	
	if( is_wp_error( $result ) ) {
		die("Failed to Login. Are you sure your email and password are active?");
	}
	else if(json_decode($result['body'])->{"success"})
	{
		$cookies = $result['cookies'];
		//$accounts_url = $AffinityClick->getUrlFor('api')."/accounts/".$account_key + ".json";
		$accounts_url = $AffinityClick->getUrlFor('api')."/accounts/".$account_key;
		
		$result = wp_remote_get($accounts_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>$cookies));
	
		update_option("affinityclick_account_in_use",$account_key);
		
		//do we care about the result?
	}
	
	die("success"); //wordpress expects this to happen at the end.
}
function AffinityClick_installWidgets()
{
	require_once('affinityclick_intext.php');
	require_once('affinityclick_banner.php');
	register_widget('AffinityClick_InText');
	register_widget('AffinityClick_Banner');
}

#this function goes through the plugin settings to make sure its set up correctly.
function AffinityClick_checkAPIKey()
{
	global $AffinityClick;
	
	$is_setup_correctly = false;
	
	$username = get_option("affinityclick_email");
	$password = get_option("affinityclick_password");
	
	$login_url = $AffinityClick->getUrlFor('api')."/login.json?username=$username&password=$password";
	$result = wp_remote_get($login_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>array()));
	
	
	if( is_wp_error( $result ) ) {
		$is_setup_correctly = false;
	}
	else
	{	
		$obj = json_decode($result['body']);
		$is_setup_correctly = $obj->{"success"};
		
	}
	
	if(!$is_setup_correctly)
	{
		echo '<div class="update-nag"><p><strong>
		The AffinityClick plugin is not configured properly:
		Go to <a href="admin.php?page=affinity-click-widget-settings">the settings page</a> to configure your username and password.
		</strong></p></div>
		';
	}
	else //successful setup complete
	{}
}

//generates the affinityclick menu. the final argument to add_menu_page is an icon. I used a random one from the internet, but you guys should
//add your own here
function AffinityClick_SetupMenu()
{	
	add_menu_page('AffinityClick Settings', 'AffinityClick', 'manage_options', 'affinity-click-top-menu', 'AffinityClick_options_about', 'http://www.messagetostream.com/assets/images/icons/money_dollar.png');
	add_submenu_page( 'affinity-click-top-menu', 'AffinityClick - Setup Plugin', 'Settings', 'manage_options', 'affinity-click-widget-settings', 'AffinityClick_options_widget_settings');
	add_submenu_page( 'affinity-click-top-menu', 'AffinityClick - Login', 'Login to AffinityClick', 'manage_options', 'affinity-click-in-text', 'AffinityClick_options_in_text');
	
	add_action( 'admin_init', 'AffinityClick_options_setup_db' );
}

//ended up not using this. leaving it here anyway in case it ends up being needed
function AffinityClick_options_about()
{	
	echo "<iframe width='100%' height='100%' style='height: 100%;' src='http://affinityclick.com'></iframe>";
}

function AffinityClick_options_in_text()
{
	global $AffinityClick;
	
	$page_to_show = $AffinityClick->getUrlFor('my')."/";
	
	$username = get_option("affinityclick_email");
	$password = get_option("affinityclick_password");
	
	if($username != "")
	{
		$page_to_show = $AffinityClick->getUrlFor('my')."/login?username=$username&password=$password";
	}
	echo "<iframe id='ac_frame' onload='' width='100%' frameborder='0' height='600px' style='height: 600px;display:block; width:100%; border:none;' src='$page_to_show'></iframe>";
}

//this tells wordpress what our required settngs will be
function AffinityClick_options_setup_db()
{	
  	register_setting( 'ac_options', 'affinityclick_email' );
  	register_setting( 'ac_options', 'affinityclick_password' );

	//this is the setting that tracks what the last set account in use was
  	register_setting( 'ac_options', 'affinityclick_account_in_use' );
	
}
function AffinityClick_options_widget_settings()
{
	global $AffinityClick;
	
	$username = get_option("affinityclick_email");
	$password = get_option("affinityclick_password");
	$account_in_use = get_option("affinityclick_account_in_use");
		
	if($username == "")
	{
		echo "To register for a new account, visit the <a href='admin.php?page=affinity-click-top-menu'>AffinityClick</a> website.<br/>";
	}
	
	echo "<div class='wrap'><h2>AffinityClick Setup</h2><form method='post' action='options.php'>";
    settings_fields( 'ac_options' );
    #do_settings_fields( 'ac_options' );
#	echo wp_nonce_field('update-options');
	echo "<table class='form-table'>";
	
	echo "<tr valign='top'><th scope='row'>AffinityClick Email</th><td><input type='text' name='affinityclick_email' value='";
	echo get_option('affinityclick_email');
	echo "'/></td></tr>";
	
	echo "<tr valign='top'><th scope='row'>AffinityClick Password</th><td><input type='password' name='affinityclick_password' value='";
	echo get_option('affinityclick_password');
	echo "'/></td></tr>";
	
	echo "</table>";#"<input type='hidden' name='action' value='update' />";
	
	echo "<p class='submit'><input type='submit' class='button-primary' value='";
	_e('Save Changes');
	echo "'></p></form></div>";
	
	$login_url = $AffinityClick->getUrlFor('api')."/login.json?username=$username&password=$password";
	$result = wp_remote_get($login_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>array()));
	
	
	if( is_wp_error( $result ) ) {
		#should I do anything here?
		#NOOP
	}
	else if(json_decode($result['body'])->{"success"})
	{
		$cookies = $result['cookies'];
		$accounts_url = $AffinityClick->getUrlFor('api')."/accounts.json";
		$result = wp_remote_get($accounts_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>$cookies));
		
		if (is_wp_error($result)){}
		else{
			$parsed = json_decode($result['body']);
			
			echo "<div style=\"padding:15px;\">";
			echo 'Please select the account to use for this website:<br/><br/>';
			echo '<select id="account_key">';
			foreach ($parsed->{"data"} as $value) {
				
		    	echo "<option value=\"".$value->{"key"}."\"";
				if($value->{"key"} == $account_in_use)
				{
					echo " selected";
				}
				echo ">".$value->{"name"}."</option>";
			}
			echo "</select><br/><br/></div>";
			echo "<input type=\"button\" id=\"SetAccountButton\" value=\"Set Account\" style=\"margin-left: 10px;\" class=\"button-primary\">";
		}
	}
	
	echo "<p>Once you have saved your account information please ensure you add the AffinityClick widget to your WordPress site using Appearance -> Widget</p>";
	
}

?>