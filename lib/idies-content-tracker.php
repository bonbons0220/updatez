<?php 
/**
 * idies_content_tracker class
 *
 * Contains the main idies-content-tracker plugin class.
 *
*/

class idies_content_tracker {

	public function __construct() {
	
		register_activation_hook( __FILE__, '$this->activate' );
		register_deactivation_hook( __FILE__, '$this->deactivate' );
		if ( !defined( 'WP_ENV' )) {
		    define( 'WP_ENV' , 'production' );
		}
		
		// Add content filter
		add_filter( 'the_content', array($this , 'show' ) );

	}
	
	function activate() {
	
		//do activation stuff
	
	}
	
	function deactivate() {
	
		// clean up on deactivation
	
	}
	
	function setup() {
	
		// clean up on deactivation
	
	}
	
	function show( $content ) {
	
		$append = '';
		
		// This is only shown on dev devng test and testng.
		if ( 'development' !== WP_ENV) return $content;

		if ( function_exists( 'get_cfc_meta' ) ) {
			
			// These are the Tracking entries for this page.
			// Display them in reverse chronological order.
			$tracking = get_cfc_meta( 'tracking' );
			$final = count($tracking)-1;
			
			foreach( get_cfc_meta( 'tracking' ) as $key => $value ){
				//get all the fields for this entry
				$update = '';
				$fields = array_keys($tracking[$key]);
				$class='default';
				foreach ($fields as $thisfield) {
					$thisresult = the_cfc_field( 'tracking',$thisfield, false, $key , false);
					$update .= ucfirst($thisfield) . ": " . $thisresult . "<br>";
					if (count($tracking)-1 == $key) $class = ('status' === $thisfield) ? strtolower(str_replace(" ","-",$thisresult)) : $class ;
				}
				$update = '<div class="panel panel-' . $class . '"><div class="panel-body">' . $update . '</div></div>';
				$append = $update .  $append;
			}
		
		}
		
		return $content  . $append;
	}

}
?>