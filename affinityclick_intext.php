<?php

include_once('configuration.php');


if( !class_exists( 'WP_Http' ) )
    include_once( ABSPATH . WPINC. '/class-http.php' );

class AffinityClick_InText extends WP_Widget {
	
  function AffinityClick_InText() {
    $widget_ops = array( 'classname' => 'AffinityClickInText', 'description' => 'In-line text ads.' );
    $control_ops = array( 'width' => 200, 'height' => 300, 'id_base' => 'acintext','ac_widget_key'=>'','ac_widget_code'=>'' );
	
	//create a wordpress widget using the above parameters as setting structures
    $this->WP_Widget( 'acintext', 'AffinityClick In-Text', $widget_ops, $control_ops );
  }
  function control(){
  }	
  function form($instance) {
	#get all of our intext widgets
	$title = "Your Available In-Text Widgets:";
	echo "<p>$title</p>";
	echo "<select id=".$this->get_field_id('ac_widget_key')." name=".$this->get_field_name('ac_widget_key')." class=\"widefat\" style=\"width:100%;\">";
	$result = $this->getwidgets("intext");
	
	$opts = $result['widgets'];
	foreach($opts as $item)
	{
		echo "<option value=\"".$item[1]."\"";
		if(array_key_exists('ac_widget_key',$instance))
		{
			if ( $instance['ac_widget_key'] == $item[1] )
			{ 
				echo 'selected="selected"';
			}
		}
		echo " >".$item[0]."</option>";
	}
	echo "</select>";
	echo "<input id='".$this->get_field_id('ac_widget_code')."' name='".$this->get_field_name('ac_widget_code')."' type='hidden' value='".urlencode($result['code'])."'>";
  }
  
  function widget($args,$instance){
	
	$default_code = "<!-- AFFINITYCLICK --><script type=\"text/javascript\">(function(){var acId = \"_ID_\";document.write( '<a '+ 'id=\"'+acId+'\" name=\"'+acId+'\" '+ 'style=\"display:none;\" '+ '>'+ acId+ '</'+'a>'+ '<s'+'cript '+ 'lan'+'guage=\"jav'+'as'+'cript\" '+ 'src=\"'+'//development-fly-cdn-ac.s3.amazonaws.com'+'/seat/'+acId+'/ticket.js\" '+ '>'+ '</'+ 'scr'+'ipt>');})();</script><!-- /AFFINITYCLICK -->";
	
	#this is the original way to do this, but right now impossible due to a bug in the widgets/code function.
	#$result = str_replace("_ID_",$instance['ac_widget_key'],$instance['ac_widget_code']);
	
	#instead, use this version:
	$result = preg_replace('/var acId = \".*\";document.write/','var acId = "'.$instance['ac_widget_key'].'";document.write',$instance['ac_widget_code']);
	
	echo "<aside>".$result."</aside>";
  }
  function register(){
    register_sidebar_widget('AffinityClick_InText', array('AffinityClick_InText', 'widget'));
    register_widget_control('AffinityClick_InText', array('AffinityClick_InText', 'control'));
  }

  function update($new_instance, $old_instance) {
	$instance = $old_instance;
	$instance['ac_widget_key'] = strip_tags($new_instance['ac_widget_key']);
	$instance['ac_widget_code'] = urldecode($new_instance['ac_widget_code']);
	
	return $instance;
  }

function getwidgets($type){
	
	global $AffinityClick;
	
	$username = get_option("affinityclick_email");
	$password = get_option("affinityclick_password");
	
	$login_url = $AffinityClick->getUrlFor('my')."/login.json?username=$username&password=$password";
	
	$result = wp_remote_get($login_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>array()));
	
	if( is_wp_error( $result ) ) {
		#who cares? we get nothing from the server here.
	#	return array($result->get_error_message());
		$cookies = array();
	}
	else
	{
		$cookies = $result['cookies'];
	}
	
	$widget_code_url=$AffinityClick->getUrlFor('api')."/widgets/code";
	$result = wp_remote_get($widget_code_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>$cookies));
	if( is_wp_error( $result ) ) {
	   return array( 'Something went wrong getting your widget\'s code!');
	}
	$widget_code = $result['body'];
	
	$widget_url = $AffinityClick->getUrlFor('api')."/widgets.json";
	$result = wp_remote_get($widget_url, array('method'=>'GET','timeout'=>5,'redirection'=>1,'httpversion'=>'1.0','blocking'=>true,'headers'=>array(),'body'=>'','cookies'=>$cookies));
	if( is_wp_error( $result ) ) {
	   return array( 'Something went wrong getting your widgets!');
	}
	
	$obj = json_decode($result['body']);
	
	$arr = array();
	
	if($obj->{'success'} == strtolower("true"))
	{
		
		foreach($obj->{'data'} as $widget)
		{
			if($widget->{'kind'} == strtolower($type))
			{
				$arr[] = array($widget->{'name'},$widget->{'key'});
			}
		}
		return array('code'=>$widget_code,'widgets'=>$arr);
	}
	return array("failed to get widgets for you");
}	
}
?>