<?php

class AffinityClick {
	public static function getUrlFor($which) {
		switch($which) {
			case 'my' :
				return 'http://my.affinityclick.com';
				break;
			case 'api' :
				return 'https://my.affinityclick.com';
				break;
		}
		
		
	}
}

$AffinityClick = new AffinityClick;

?>