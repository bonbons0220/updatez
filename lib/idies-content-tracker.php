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
	
		//if (empty(WP_DEBUG)) return;
		$append = '';
		
		// Set up Panel Classes
		$panel_class = array(
				'Needs Update' => 'panel-danger',
				'Update in Progress' => 'panel-warning',
				'Needs Review' => 'panel-info',
				'Update Completed' => 'panel-success',
				'Do not Publish' => 'panel-default',
			);
		
		// This is only shown on dev devng test and testng.
		if ( 'development' !== WP_ENV) return $content;

		if ( function_exists( 'get_cfc_meta' ) ) {
			
			// These are the Tracking entries for this page.
			// Display them in reverse chronological order.
			$tracking = get_cfc_meta( 'tracking' );
			$final = count($tracking)-1;
			
			foreach( get_cfc_meta( 'tracking' ) as $key => $value ){
				//get all the fields for this entry
				$description = '';
				$title = '' ;
				$fields = array_keys($tracking[$key]);
				foreach ($fields as $thisfield) {
					$thisresult = the_cfc_field( 'tracking',$thisfield, false, $key , false);
					$description .= ucfirst($thisfield) . ": " . $thisresult . "<br>";
					if ('status' === $thisfield) {
						$title = ucfirst($thisfield) . ": " . $thisresult;
						if (count($tracking)-1 == $key) {
							$class = ( empty( $panel_class[$thisresult] ) ) ? 'panel-default' : $panel_class[$thisresult] ;
						} else {
							$class = 'panel-default' ;
						}
					}
				}
				$update = '<div class="panel ' . $class . '">';
				$update .= '<div class="panel-heading"><h3 class="panel-title">' . $title . '</h3></div>';
				$update .= '<div class="panel-body">' . $description . '</div></div>';
				$append = $update .  $append;
			}
		
		}
		
		return $content  . $append;
	}

}
?>