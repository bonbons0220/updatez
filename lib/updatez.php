<?php 
/**
 * Updatez WordPress plug-in tracks update status of pages.
 *
 * Tracks the assigned updater, status, and comments for each page that is 
 * published, private, or in draft on the website.
 *
 * @link https://github.com/bonbons0220/updatez
 *
 * @package Updatez
 * @subpackage updatez main class
 * @since 1.2 (when the file was introduced)
 */

/**
 * updatez Main Class definition
 *
 * @since 1.2.0
 */
 class updatez {

	/**
	 * Array of Page Status values.
	 *
	 * @var    array
	 */
	public $statuses;
	
	/**
	 * Array of Panel class values to display Status on Frontend.
	 *
	 * @var    array
	 */
	public $panel_class;
	
	/**
	 * Array of all pages (all of which can/should have a status).
	 *
	 * @var    array
	 */
	public $all_pages;
	
	/**
	 * Default page updater.
	 *
	 * @var    string
	 */
	public $default_updater;
	
	/**
	 * Default page status.
	 *
	 * @var    string
	 */
	public $default_status;

	/**
	 * Which publication status to show update status on.
	 *
	 * @var    string
	 */
	public $post_status;
	
	/**
	 * Which post type to show update status on.
	 *
	 * @var    string
	 */
	public $post_type;
	
	/**
	 * Array of all users ordered by display name.
	 *
	 * @var    array
	 */
	public $all_users = array();
	
	/**
	 * Name of directory to hold temporary files.
	 *
	 * @var    string
	 */	 
	public $default_tmppath;
	
	/**
	 * Name of url for temporary files.
	 *
	 * @var    string
	 */	 
	public $tmpurl;
	
	/**
	 * Name of temporary export csv file.
	 *
	 * @var    string
	 */	 
	public $export_fname;	
	
	/**
	 * CSV field names in import/export file
	 *
	 * @var    string
	 */	 
	public $csv_fields;
	
	/**
	 * $_GET['updater']
	 *
	 * @var    string
	 */	 
	public $updater;
	
	/**
	 * $_GET['statuz']
	 *
	 * @var    string
	 */	 
	public $statuz;
	
	/**
	 * Public constructor method to prevent a new instance of the object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */	
	public function __construct() {
		
		//register_activation_hook( __FILE__, '$this->activate' );
		register_activation_hook( __FILE__, array( 'updatez', 'activate' ) );
		//register_deactivation_hook( __FILE__, 'deactivate' );
		
		// Don't show on Prod/WWW
		if ( !defined( 'WP_ENV' )) {
		    define( 'WP_ENV' , 'production' );
		}
			
		/********************************************************************************/
		/*********** FILTERS AND ACTIONS ************************************************/
		/********************************************************************************/
		// FRONT END STATUS
		add_filter( 'the_content' , array($this , 'showstatus' ) );
		
		// DASHBOARD PAGES
		add_action( 'admin_menu' , array( $this , 'create_admin_menu' ) );
		add_action( 'admin_menu' , array( $this , 'get_pages' ) );
		
		// ADMIN CONTENT EDITOR
		add_action( 'add_meta_boxes_page' , array( $this , 'updatez_meta_box_page' ) );		
		add_action( 'save_post' , array( $this , 'updatez_save_meta' ) );
		
		// ALL PAGES SCREEN
		add_action( 'admin_enqueue_scripts' , array(  $this , 'idies_admin_enqueue_scripts' ) );
		
		//                  CUSTOM COLUMNS
		add_action( 'manage_pages_custom_column' , array( $this , 'idies_page_column_content' ) , 10 , 2 );
		add_filter( 'manage_pages_columns' , array( $this , 'idies_custom_pages_columns' ) );
		add_filter( 'manage_edit-page_sortable_columns' , array( $this , 'idies_sortable_pages_column' ) );
		add_action( 'pre_get_posts' ,array(  $this , 'idies_custom_columns_column_orderby' ) );
		
		//                  QUICK EDIT
		add_action( 'quick_edit_custom_box' , array( $this , 'idies_display_quickedit_custom') , 10, 2 );
		add_action( 'save_post' , array(  $this , 'idies_save_quickedit_custom' ) );
		
		//                  FILTERING OPTIONS
		add_filter( 'posts_where' , array($this , 'posts_where' ) );
		add_action( 'restrict_manage_posts' , array($this , 'add_filter_options' ) );

		/********************************************************************************/
		/*********** VARIABLES **********************************************************/
		/********************************************************************************/
		// DEFAULTS
		$this->default_updater = 'bsouter';
		$this->default_status = 'not-started';
		$this->default_tmppath = '/data1/dswww-ln01/sdss.org/tmp/';
		$this->meta_fields = array( 
			'updatez_status',
			'updatez_updater',
			'updatez_comment',
		);
		$this->post_type = 'page';
		$this->post_status  = array( 
			'publish',
			'private',
			'draft') ;
			
		$this->statuses = array(
			"not-started"=>"Not Started",
			"in-progress"=>"In Progress",
			"needs-review"=>"Needs Review",
			"completed"=>"Completed",
			"do-not-publish"=>"Do Not Publish",
		);
		$this->panel_class = array(
			'not-started' => 'panel-danger' ,
			'in-progress' => 'panel-warning' ,
			'needs-review' => 'panel-info' ,
			'completed' => 'panel-success' ,
			'do-not-publish' => 'panel-default' ,
		);
		
		// _GET VARS FOR ALL PAGES
		$this->updater = "updater";
		$this->statuz = "statuz";
		$this->all_users = get_users( 'orderby=nicename' );
		
		// IMPORT EXPORT
		$this->export_fname = sanitize_title( home_url( ) ) . '-update-export.csv';
		$this->tmpurl = '/wp-tmp/';							// TEMP FILE LOCATION
		$this->csv_fields = array("ID", 
			"Title",
			"Edit",
			"View",
			"Status",
			"Updater",
			"Comment",
			"Last Revised" );
	}
	
	/**
	/* Activate Plugin, initialize/check all pages.
	/* 
	 * @since  1.2
	 * @access public
	 * @return void
	 */	
	function activate() {

		if ( ! get_user_by( 'slug' , $this->default_updater ) ) {
			$the_user = get_users( array('role'=>'edit_theme_options' , 'number'=>1 , 'orderby'=>'ID' ) );
			$this->default_updater = $the_user->user_nicename;
		}

		// Make sure each page has a statuses that is defined, and an updater who is a user in the system.
		foreach ( $this->all_pages as $thispage ){
		
			if ( ! array_key_exists( $thispage->updatez_status , $this->statuses ) )
				update_post_meta( $thispage->ID  , 'updatez_status' , $this->default_status ) ;

			if ( ! get_user_by( 'slug' , $thispage->updatez_updater ) ) { 
				update_post_meta( $thispage->ID  , 'updatez_updater' , $this->default_updater ) ;
			}
		}
	}
	
	/**
	/* Create the Status Update Dashboard Menus
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function create_admin_menu() {
		
		// Add Dashboard Page and Main Menu item
		add_menu_page(   'Status Updates' , 'Status Updates' , 'edit_posts' , 'idies-status-menu', array( $this , 'show_overview_page' ) ,'dashicons-yes' );
		
		// Add subpages to the main menu. Note that the Overview page re-uses the Main page slug, which avoids a duplicate subpage for the main page
		add_submenu_page('idies-status-menu' , 'Status Updates Overview',      'Overview',      'edit_posts',         'idies-status-menu',     array( $this , 'show_overview_page' ) );
		add_submenu_page('idies-status-menu' , 'Status Updates Settings',      'Settings',      'edit_theme_options', 'idies-status-settings', array( $this , 'show_settings_page' ) );
		add_submenu_page('idies-status-menu' , 'Status Updates Import/Export', 'Import/Export', 'edit_theme_options', 'idies-status-import',   array( $this , 'show_import_page' ) );
		
	}

	/**
	/* Show the Dashboard OVERVIEW Page
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function show_overview_page() {
		
		if ( !current_user_can( 'edit_posts' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		// Go through pages and get summary of page status vs update status,
		// and user vs update status.
		$summary = $this->get_summary();

		$result = '';
		
		$result .= '<div class="wrap">';
		$result .=  '<h1>Overview</h1>';
		
		/* PAGES */
		$result .=  '<h2>Pages</h2>';
		$result .=  '<table class="form-table">';
		$result .=  '<thead>';
		$result .=  '<tr>';
		$result .=  '<th>Update Status\Page Status</th>';
		foreach( $this->post_status as $this_page_status ) $result .=  '<th>' . ucfirst( $this_page_status ) . '</th>';
		$result .=  '</tr>';
		$result .=  '</thead>';
		$result .=  '<tbody>';
		foreach( $this->statuses as $this_update_status ) {
			$result .=  '<tr>';
			$result .=  '<th scope="row">' . $this_update_status . '</th>';
			foreach( $this->post_status as $this_page_status ) {
				$result .=  
					'<td>' . 
					'<a href="/wp-admin/edit.php?post_type=page' .
						'&statuz=' . array_shift( array_keys( $this->statuses , $this_update_status ) ) . 
						'&post_status=' . $this_page_status . '">' . 
					$summary['pages'][ $this_page_status ][ array_shift( array_keys( $this->statuses , $this_update_status ) ) ] . 
					'</a>';
					'</td>';
			}
			$result .=  '</tr>';
		}
		$result .=  '</tbody>';
		$result .=  '</table>';
		
		/*/
		$result .=  '<pre>';
		$result .=  var_export( $this->all_users , true );
		$result .=  '</pre>';
		/*/
		
		/* USERS */
		$result .=  '<h2>Users</h2>';
		$result .=  '<table class="form-table">';
		$result .=  '<thead>';
		$result .=  '<tr>';
		$result .=  '<th>Users\Page Status</th>';
		foreach( $this->statuses as $this_update_status ) $result .=  '<th>' . $this_update_status . '</th>';
		$result .=  '</tr>';
		$result .=  '</thead>';
		$result .=  '<tbody>';
		foreach( $this->all_users as $this_user ) {
			if ( !array_key_exists( $this_user->user_nicename , $summary['users'] ) ) continue;
				$result .=  '<tr>';
				$result .=  '<th scope="row">' . $this_user->display_name . '</th>';
				foreach( $this->statuses as $this_update_status ) {
					$result .=  '<td>' . 
					'<a href="/wp-admin/edit.php?post_type=page&all_posts=1' .
						'&updater=' . $this_user->ID .
						'&statuz=' . array_shift( array_keys( $this->statuses , $this_update_status ) ) . '">' . 
					$summary['users'][ $this_user->user_nicename ][ array_shift( array_keys( $this->statuses , $this_update_status ) ) ] . 
					'</a>';
					'</td>';
				}
				$result .=  '</tr>';
		}
		/*/
		foreach( $summary['users'] as $this_user_slug=>$this_user_summary ) {
			$result .=  '<tr>';
			$result .=  '<th scope="row">' . $this_user_slug . '</th>';
			foreach( $this->statuses as $this_update_status ) $result .=  '<td>' . $summary['users'][ $this_user_slug ][ array_shift( array_keys( $this->statuses , $this_update_status ) ) ] . '</td>';
			$result .=  '</tr>';
		}
		/*/
	
		$result .=  '</tbody>';
		$result .=  '</table>';
		
		$result .= '</div>';
		
		echo $result;
	}

	/**
	/* Show the Dashboard SETTINGS Page
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function show_settings_page() {
		
		$actions = array( 'update' , 'nuclear' , 'import' );
		$this_page = 'settings';

		if ( !current_user_can( 'edit_theme_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$result = array();

		
		// Get Options
		$this->get_options();

		// Get and Do the Action 
		foreach ( $actions as $this_action ) {
			if ( isset( $_POST[ $this_action ] ) && !empty( $_POST[ $this_action ] ) ) {
				
				check_admin_referer( 'save_settings_action' , 'save_settings_nonce' );
				$result = array_merge( $result , $this->$this_action(  ) );
				
			}
		}
		/*/
		$result = array_merge( $result , 
			array( "info"=>"defaults: " . 
				$this->default_updater . ", " .
				$this->default_status . ", " .
				$this->default_tmppath ) );
		/*/
		
		// Write the export file each time page is loaded.
		$this->write_export_data();
		
		// More info
		$update_args = array( 
			'meta_key' => 'updatez_status' ,
			'meta_compare' => 'NOT EXISTS' , 
			'meta_value' => 'foobar' ,
		);
		$updated_pages = $this->get_page_updates( $update_args );
		$completed_args = array( 
			'meta_key' => 'updatez_status' ,
			'meta_value' => 'completed' ,
		);
		$completed_pages = $this->get_page_updates( $completed_args );
		
		// Show the form
		echo '<div class="wrap">';
		echo '<h1>Settings</h1>';
		
		// Show Dashboard Notice ( notice-error, -success, -info, or -warning )
		foreach ($result as $thiskey=>$thisvalue) {
			echo '<div class="notice notice-' . $thiskey . ' is-dismissible">' . $thisvalue . '</div>';
		}
		
		echo '<form method="post" action="">';
		echo '<input type="hidden" name="options" value="settings">';
		echo '<input type="hidden" name="update" value="update">';
		wp_nonce_field( 'save_settings_action' , 'save_settings_nonce' );
		
		echo '<table class="form-table">';
		echo '<tbody>';

		echo '<tr>';
		echo '<th scope="row">Default Path (Writable dir for temp files)</th>';
		echo '<td>';
		echo '<input name="default_tmppath" id="default-tmppath" value="' . $this->default_tmppath . '" >';
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">Default Updater</th>';
		echo '<td>';
		echo '<select name="default_updater" id="default-updater">';
		foreach ( $this->all_users as $thisuser ) {
			echo '<option value="' . $thisuser->user_nicename . '"' . selected( $this->default_updater , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		
		echo '<tr>';
		echo '<th scope="row">Default Status</th>';
		echo '<td>';
		echo '<select name="default_status" id="default-status">';
		foreach ( $this->statuses as $thiskey => $thisvalue ) {
			echo '<option value="' . $thiskey . '"' . selected( $this->default_status , $thiskey ) . '>' . $thisvalue. '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		
		echo '</tbody>';
		echo '</table>';
		echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="Update Settings">';
		echo '</form>';

		echo '<form method="post" action="" enctype="multipart/form-data">';
		wp_nonce_field( 'save_settings_action' , 'save_settings_nonce' );
		echo '<table class="form-table">';
		echo '<tbody>';

		echo '<tr>';
		echo '<th scope="row">Export Page Updates as CSV...</th>';
		echo '<td><a class="button button-secondary" href="' . $this->tmpurl . $this->export_fname . '">Export</a></td>';
		echo '<td><em>Export to CSV file to update Status, Updater, and Comments of multiple pages manually.</em></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row" rowspan="2">Import Page Updates CSV...</th>';
		echo '<td colspan="2"><input type="file" name="importfile" id="importfile" class="widefat"></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td><button type="submit" class="button button-primary" id="import" name="import" value="import">Import</button></td>';
		echo '<td scope="row"><em>N.b. you can only update the <strong>Updater</strong>, <strong>Status</strong>, ' . 
			 'and/or <strong>Comments</strong> with CSV import. All other columns are ignored (but ' .
			 'column titles and order must match CSV Export file). ' .
			 'Also, the <strong>Updater</strong> must be a WordPress login for a valid user ' .
			 '(e.g. bsouter), not a display name (e.g. Bonnie Souter). You can find users\' login ' . 
			 'names on <a href="/wp-admin/users.php">All Users</a>.</em></td>';
		echo '</tr>';
				
		echo '<tr>';
		echo '<th scope="row">Nuclear Option: <br></th>';
		echo '<td><button class="button button-secondary" id="nuclear" name="nuclear" value="nuclear">Go Nuclear</button></td>';
		echo '<td><em>Reset all pages\' to default status, updater, and delete comments.</em></td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '</form>';
		echo '</div>';

		return;
	}

	/**
	/* Show the Dashboard IMPORT/EXPORT Page
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function show_import_page() {
		
		if ( !current_user_can( 'edit_theme_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$actions = array( 'import' );
		
		$result = '';
		
		$result .= '<div class="wrap">';
		$result .=  '<h1>Import/Export</h1>';
		$result .= '</div>';
		
		echo $result;
	}

	/**
	// Get All Pages 
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function get_pages( $post_object = null , $return = false ) {
		global $post;
		
		// Get or Set query variables
		$args = array(
			'posts_per_page' => -1,
			'post_type' => $this->post_type,
			'post_status' => implode( ',' , $this->post_status ),
		); 
		$the_pages = get_posts( $args );
		
		if ( $return ) return $the_pages;
		
		$this->all_pages = $the_pages;
		return;

	}

	/**
	// Get Page Updates 
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function get_page_updates( $newargs = array() ) {
		
		// Get or Set query variables
		$paged = isset( $_GET[ 'paged' ] ) ? 											// page 1
					absint( $_GET[ 'paged' ] ) : 
					1 ;
		$orderby = ( isset( $_GET[ 'orderby' ] ) &&  			 						// 'post_title'
					 in_array( $_GET[ 'orderby' ] ,
						array( 
							'title' , 
							'update-status' , 
							'page-status' , 
							'post-modified' , 
						) ) ) ? 
					$_GET[ 'orderby' ] : 
					'title' ;
		$order = ( isset( $_GET[ 'order' ] ) &&							 				// 'asc'|'desc'
					 in_array( $_GET[ 'order' ] , 							 				
						array( 
							'asc' , 
							'desc' , 
						) ) ) ?  
					$_GET[ 'order' ] : 
					'asc' ;
		$updater  = isset( $_GET[ 'updater' ] ) ?										// Show pages assigned to 'all'. 
					sanitize_text_field( $_GET[ 'updater' ] ) : 
					'all' ;
		$number  = isset( $_GET[ 'number' ] ) ? 										// number to show: 20
					absint( $_GET[ 'number' ] ) : 
					20 ; 
		$offset  = isset( $_GET[ 'offset' ] ) ? 							 			// number to skip: 0
					absint( $_GET[ 'offset' ] ) : 
					0 ;

		$args = array(
			'order' => $order,
			'orderby' => $orderby,
			'posts_per_page' => -1,
			'offset' => $offset,
			'post_type' => $this->post_type,
			'post_status' => implode( ',' , $this->post_status ),
		); 
		foreach ($newargs as $thiskey=>$thisvalue) 
			$args[$thiskey] = $thisvalue;
		
		$my_pages = get_posts( $args );
		
		return $my_pages;

	}

	/**
	// Import a CSV file with Page Status Updates
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function import() {

		$status = "error";
		
		$imageFileType = pathinfo( $_FILES['importfile']['name'] , PATHINFO_EXTENSION);
		
		if ( $imageFileType != "csv" && $imageFileType != "CSV" && $imageFileType != "txt" ) {
			return array("error"=>"Wrong file type. Only CSV file uploads allowed.");
		}
		$contents = file($_FILES['importfile']['tmp_name']); 
		$fields = str_getcsv( array_shift( $contents ) );
		
		// Check that the fields match
		if ( !count( array_diff( $fields , $this->csv_fields ) ) == 0 ) {
			return array( "error"=>"CSV file must contain the following column titles: " . implode(",",$this->csv_fields) . " not " . implode(",",$fields) );
		}

		//loop through the csv and update all the files
		$error = '' ; 
		foreach( $contents as $this_line ) {
		$this_csv = str_getcsv( $this_line ) ;
		
		// Validate input
		if ( get_user_by( 'slug' , $this_csv[5] ) === false ) {
			$error .= "Error. User not found: " . $this_csv[5] . ", Skipping ID " . $this_csv[0] . "...<br>\n";
			continue;
		} else if ( ( $this_key = array_search( $this_csv[4], $this->statuses ) ) === false ) {
			$error .= "Error. Status not found: " . $this_csv[4] . ", Skipping ID " . $this_csv[0] . "...<br>\n";
			continue;
		}			
			
			update_post_meta( $this_csv[0]  , 'updatez_updater' , $this_csv[5] ) ;
			update_post_meta( $this_csv[0]  , 'updatez_status' , $this_key ) ;
			update_post_meta( $this_csv[0]  , 'updatez_comment' , $this_csv[6] ) ;
		}

		$result = ( strlen($error) > 0 ) ? array( 'error'=>$error ) : array( 'success'=>"Uploaded " . $_FILES['importfile']['name'] . ". " ) ;
		return $result;
		
	}		

	/**
	// Write Status Updates CSV export file
	/* 
	 * @since  1.1
	 * @access public
	 * @return $result
	 */	
	function write_export_data(  ) {

		$output = '';
		$sep = ',';
		$quo = '"';
		$fname = $this->default_tmppath . "/" . $this->export_fname;

		//ID, post_title, post.php?post=ID&action=edit, /post_name/, updatez_status, updatez_updater, updatez_comment, post_modified
		$output .= $quo . implode( $quo . $sep . $quo , $this->csv_fields ). $quo . "\n"; 
			
		foreach ( $this->all_pages as $thispage ){
			$output .= 
				$thispage->ID . $sep . 
				$quo . $thispage->post_title . $quo . $sep . 
				$quo . site_url( "wp-admin/post.php?post=" . $thispage->ID . "&action=edit" ) . $quo . $sep . 
				$quo . get_the_permalink( $thispage->ID ) . $quo . $sep .  
				$quo . $this->statuses[ get_post_meta( $thispage->ID, "updatez_status" , true )] . $quo . $sep . 
				$quo . get_post_meta( $thispage->ID, "updatez_updater" , true ) . $quo . $sep . 
				$quo . get_post_meta( $thispage->ID, "updatez_comment" , true ) . $quo . $sep . 
				$quo . $thispage->post_modified . $quo . "\n";
		}
		
		//write temp file
		$expfile = fopen( $fname , "w") or die("Unable to create ". $fname ." file.");
		fwrite($expfile, $output);
		fclose($expfile);
		
	}		

	/**
	/* Nuclear option to reset Page updater, status, and comments to defaults
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function nuclear() {
		
		$all_pages = $this->get_page_updates();
		foreach ( $all_pages as $thispage ){
			update_post_meta( $thispage->ID , 'updatez_status' , $this->default_status ) ;
			update_post_meta( $thispage->ID , 'updatez_updater' , $this->default_updater ) ;
			update_post_meta( $thispage->ID , 'updatez_comment' , '' ) ;
		}
	
		$result = array( 'success'=>'All Pages reset.' ) ; 
		return $result;
	}		
	

	/**
	/* Save Meta box Data from Content Editor
	 *
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function updatez_save_meta($post_id){	
		
		// Check nonce, user capabilities
		if ( ! isset($_POST['save_postmeta_nonce']) ) return; //in case this is a different save_post action
		check_admin_referer( 'save_postmeta_action' , 'save_postmeta_nonce' );

		$newupdater = isset( $_REQUEST['meta_box_updater'] ) ? sanitize_user($_REQUEST['meta_box_updater'] ) : false ;
		$newstatus = isset( $_REQUEST['meta_box_status'] ) ? sanitize_title($_REQUEST['meta_box_status'])  : false ;
		$newcomment = isset( $_REQUEST['meta_box_comment'] ) ? sanitize_text_field($_REQUEST['meta_box_comment']) : false ;
		
		// Anything to update?
		if ( !( $newupdater || $newstatus || $newcomment ) ) {
			return $post_id;
		}

		if ( ! current_user_can( 'edit_posts' ) && ! wp_is_post_autosave( $post_id ) ) return $post_id;
		
		if ( $newupdater && get_user_by( 'slug' , $newupdater ) ) {
		
			//update the post meta
			update_post_meta(
				$post_id,
				'updatez_updater' ,
				$newupdater
			);
		}
		
		if ( $newstatus && array_key_exists( $newstatus , $this->statuses ) ) {
		
			//update the post meta
			update_post_meta(
				$post_id,
				'updatez_status' ,
				$newstatus
			);
		}

		if ( $newcomment ) {
		
			//update the post meta
			update_post_meta(
				$post_id,
				'updatez_comment' ,
				$newcomment
			);
		}
	}

	/**
	/* Register Meta box to content editor
	 *
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function updatez_meta_box_page( $page ){
		add_meta_box( 
			'idies-update-meta-box-page' , 
			__( 'Status Update' ), 
			array( $this , 'render_update_meta_box' ), 
			'page' , 
			'normal' , 
			'default'
		);
	}

	/**
	/* Render the Status Update Meta Box in the content editor
	 *
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function render_update_meta_box( $page ) {
		$values = get_post_custom( $page->ID );
		
		$updater = isset( $values['updatez_updater'] ) ? esc_attr( $values['updatez_updater'][0] ) : $this->default_updater ;
		$status = isset( $values['updatez_status'] ) ? esc_attr( $values['updatez_status'][0] ) : $this->default_status ;
		$comment = isset( $values['updatez_comment'] ) ? esc_attr( $values['updatez_comment'][0] ) : '' ;
		wp_nonce_field( 'save_postmeta_action' , 'save_postmeta_nonce' );
	?>
	<table class="form-table meta-box">
	<tr>
	<th scope="row"><label for="meta_box_status">Status</label></th>
    <td><?php
		echo '<select  class="postbox" name="meta_box_status" id="meta-box-status">';
		foreach ( $this->statuses as $thiskey=>$thisstatus ) {
			echo '<option value="' . $thiskey . '"' . selected( $status , $thiskey ) . '>' . $thisstatus . '</option>';
		}
		echo '</select>';
	?></td>
	</tr>
	<tr>
	<th scope="row"><label for="meta_box_updater">Updater</label></th>
    <td><?php
		echo '<select  class="postbox" name="meta_box_updater" id="meta-box-updater">';
		foreach ( $this->all_users as $thisuser ) {
			echo '<option value="' . $thisuser->user_nicename . '"' . selected( $updater , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
		}
		echo '</select>';
	?></td>
	</tr>
	<tr>
	<th scope="row"><label for="meta_box_comment">Comments</label></th>
    <td><?php
		echo '<textarea class="postbox" rows="10" cols="60" name="meta_box_comment" id="meta-box-comment">' . $comment . '</textarea>';
	?></td>
	</tr>
	</table>
    <?php
	}

	/**
	/* Show the status on a page, under the content.
	/* 
	 * @since  1.0
	 * @access public
	 * @return void
	 */	
	function showstatus( $content ) {
	
		// Status is only shown on dev devng test and testng.
		if ( 'development' !== WP_ENV) return $content;
		
		$append = '';
		
		// No status - no show
		$status = get_post_meta( get_the_ID() , 'updatez_status' , true );
		if ( empty( $status ) ) return $content;
		$updater = get_post_meta( get_the_ID() , 'updatez_updater' , true );
		$comments = get_post_meta( get_the_ID() , 'updatez_comment' , true );
		
		//if ( array_key_exists ( $status , $this->panel_class ) ) $class = $this->panel_class->$status;
		$class='panel-danger';

		$update = '<div class="clearfix"></div>';
		$update .= '<div class="panel ' . $this->panel_class[$status] . '">';
		$update .= '<div class="panel-heading"><h3 class="panel-title">' . $this->statuses[$status] . '</h3></div>';
		$update .= '<div class="panel-body">';
		$update .= "Updater: " . $updater . "<br>\n";
		$update .= "Comments: " . $comments;
		$update .= '</div></div>';
		
		return $content  . $update;
	}

	/**
	/* Show a custom column on the All Pages admin screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_page_column_content( $column_name, $post_id ) {
	
		if ( $column_name == 'update_updater' ) {
			$the_user = get_user_by(  'slug' , get_post_meta( $post_id , "updatez_updater" , true) );
			echo "<a href='" . add_query_arg( $this->updater , $the_user->ID ) . "'>";
			echo $the_user->display_name . "<span class='lookup hidden'>$the_user->user_nicename</span>";
			echo "</a>";
			} 
		if ( $column_name == 'update_status' ) {
			$the_status = get_post_meta( $post_id , "updatez_status" , true) ;
			$the_user = get_user_by(  'slug' , get_post_meta( $post_id , "updatez_updater" , true) );
			echo "<a href='" . add_query_arg( $this->statuz , $the_status ) . "'>";
			echo $this->statuses[ $the_status ] . "<span class='lookup hidden'>$the_status</span>";
			echo "</a>";
			if ( in_array( $the_status , array( "completed" , "do-not-publish" ) ) == false ) {
				echo " <a class='button' href='mailto:$the_user->user_email" . "?subject=SDSS Website Update Reminder" . 
				"&body=Hello, " . $the_user->display_name . ". \n" . 
				"You are the updater for \"" . get_the_title( $post_id ) . "\" (" . get_the_permalink( $post_id ) . "). " . 
				"This page is \"" . $this->statuses[ get_post_meta( $post_id , "updatez_status" , true) ] . "\". " . 
				"Please complete any required updates and mark the Update Status as \"Completed\". \n" .
				"Thanks!" .
				"'><strong>Send Update Reminder</strong></a>";
			}
		}
	}

	/**
	/* Register column name and header text for custom column on All Pages
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_custom_pages_columns( $columns ) {

		/** Add a Thumbnail Column **/
		$myCustomColumns = array(
			'update_updater' => __( 'Updater' ),
			'update_status' => __( 'Update Status' )
		);
		$columns = array_merge( $columns, $myCustomColumns );

		/** Remove Columns **/
		unset(
			$columns['comments'],
			$columns['post_type']
		);

		return $columns;
	}

	/**
	/* Makes custom columns sortable on All Pages screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_sortable_pages_column( $columns ) {
	
		$columns['update_updater'] = 'updater';
		$columns['update_status'] = 'update_status';
	 
		//To make a column 'un-sortable' remove it from the array
		//unset($columns['date']);
	 
		return $columns;
	}
	
	/**
	/* Adds custom fields to Quick Edit box on All Pages screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_custom_columns_column_orderby( $query ) {
	 
		$orderby = $query->get( 'orderby');
	 
		if( 'updater' == $orderby ) {
			$query->set('meta_key' ,'updatez_updater');
			$query->set('orderby' ,'meta_value');
		}
	 
		if( 'update_status' == $orderby ) {
			$query->set('meta_key' ,'updatez_status');
			$query->set('orderby' ,'meta_value');
		}
	}		
	
	/**
	/* Adds custom fields to Quick Edit box on All Pages screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_display_quickedit_custom( $column_name, $post_type ) {
		// Only print the nonce once.
		static $printNonce = TRUE;
		if ( $printNonce ) {
			$printNonce = FALSE;
			wp_nonce_field( 'save_quickedit_action' , 'save_quickedit_nonce' );
		}

		?>
		<fieldset class="inline-edit-col-right inline-edit-idies">
		  <div class="inline-edit-col column-<?php echo $column_name; ?>">
			<label class="inline-edit-group">
			<?php 
			switch ( $column_name ) {
				case 'update_updater':
					echo '<span class="title">Update Updater</span><select name="update_updater" />';
					foreach ( $this->all_users as $thisuser ) {
						echo '<option value="' . $thisuser->user_nicename . '"/>' . $thisuser->display_name . '</option>';
					}
					echo '</select>';
					break;
				case 'update_status':
					echo '<span class="title">Update Status</span><select name="update_status" />';
					foreach ( $this->statuses as $thiskey=>$thisvalue ) {
						echo '<option value="' . $thiskey . '">' . $thisvalue . '</option>';
					}
					echo '</select>';
					break;
			}
			?>
			</label>
		  </div>
		</fieldset>
		<?php
	}

	/**
	/* Get plugin options or use default options
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function get_options() {
		
		$this->default_tmppath = get_option( 'updatez_default_tmppath' , $this->default_tmppath );
		$this->default_updater = get_option( 'updatez_default_updater' , $this->default_updater );
		$this->default_status = get_option( 'updatez_default_status' , $this->default_status );
	}

	/**
	/* Update/Set plugin options from Updated or Default options
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function update() {
		
		$options = array( 
			'default_tmppath' ,
			'default_updater' ,
			'default_status' ,
		);
		$updated = false;
	
		foreach( $options as $this_option ) {
			
			if ( isset( $_POST[ $this_option ] ) && !empty( $_POST[ $this_option ] ) ) {
				
				$this->$this_option = rtrim( $_POST[ $this_option ] , '/' );
				update_option( 'updatez_' . $this_option , $this->$this_option );
				
				$updated = true;
			}			
		}
		
		$result = ( $updated ) ? array( 'success'=>'Updates applies.' ) : array( 'warning'=>'No updates to apply.' ) ; 
		return $result;
	}

	/**
	/* Saves custom fields in Quick Edit box on All Pages screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_save_quickedit_custom( $post_id ) {

		if ( ! isset($_POST['save_quickedit_nonce']) ) return; //in case this is a different save_post action
		check_admin_referer( 'save_quickedit_action' , 'save_quickedit_nonce' );
		
		if ( $this->post_type !== $_POST['post_type'] ) {
			return;
		}
		if ( !current_user_can( 'edit_posts' , $post_id ) ) {
			return;
		}
		
		
		if ( isset( $_REQUEST['update_updater'] ) ) {
			update_post_meta( $post_id, 'updatez_updater' , $_REQUEST['update_updater'] );
		}
		if ( isset( $_REQUEST['update_status'] ) ) {
			update_post_meta( $post_id, 'updatez_status' , $_REQUEST['update_status'] );
		}
	}

	/**
	/* Apply Updatez filter to all posts query
	/* 
	 */	
	function posts_where( $where ) {
		
		$updater = $this->updater;
		$statuz = $this->statuz;
		
		if( is_admin() ) {
			global $wpdb;
			
			if ( isset( $_GET[$updater] ) && 
				!empty( $_GET[$updater] ) ) {

				if ( false !== $updater_info = get_userdata( intval( $_GET[$updater ] ) ) ) {
					$where .= " and ID in " .
						"(select post_id from sdsswp_dr14_test.wp_postmeta WHERE meta_value = '" . $updater_info->user_nicename . "' and post_id in " .
						"(select post_id from sdsswp_dr14_test.wp_postmeta where meta_key='updatez_updater' ) )";
				}
			}
			
			if ( isset( $_GET['statuz'] ) && 
				!empty( $_GET['statuz'] ) ) {
				
				$statuz = sanitize_title( $_GET['statuz'] );
				$where .= " and ID in " .
						"(select post_id from sdsswp_dr14_test.wp_postmeta WHERE meta_value = '$statuz' and post_id in " .
						"(select post_id from sdsswp_dr14_test.wp_postmeta where meta_key='updatez_status' ) )";
			}
		}
		return $where;
	}	
	
	/**
	/* Show Users dropdown for Filter OPtions on All Pages Admin Screen
	/* 
	 */	
	function add_filter_options(  ) {

		global $typenow;
		$updater = $this->updater;
		$statuz = $this->statuz;

		if ($typenow=='page') {
			
			$selected_user = ( isset( $_GET[$updater] ) && 
				!empty( $_GET[$updater] ) && 
				intval( $_GET[$updater] ) > 0 ) ? 
					intval( $_GET[$updater] ) :
					0 ;
					
			$selected_status = ( isset( $_GET[$statuz] ) && 
				!empty( $_GET[$statuz] ) &&
				array_key_exists( $_GET[$statuz] , $this->statuses ) ) ? 
					$_GET[$statuz] :
					0 ;
		
			
			// Add Updater (Users) Dropdown to Filter Options section
			wp_dropdown_users( array(
				'name'	=>  "updater" ,
				'show_option_all'	=>  __("All Updaters") ,
				'role__in'			=>  array( 'administrator' , 'editor' ) ,
				'selected'			=>  $selected_user ,
			));
			
			// Add Status Dropdown to Filter Options section
			$this->wp_dropdown_status( array(
				'selected'			=>  $selected_status ,
			));
		}
	}	

	/**
	/* Get summary of Status Updates for Overview page 
	/* 
	 */	
	function get_summary(  ) {
		$page_summary = array( );
		foreach ( $this->post_status as $ps ) {
			foreach ( $this->statuses as $uskey=>$usvalue ) {
				$page_summary[ $ps ][ $uskey ] = 0;
			}
		}
		
		$user_summary = array();
		$this_user_summary = array();
		foreach ( $this->statuses as $uskey=>$usvalue ) {
			$this_user_summary[ $uskey ] = 0;
		}
		
		//loop through all the pages
		foreach ( $this->all_pages as $this_page ) {
			/*/
			/*/
			// if this page's post_status is not one we are tracking, continue to the next.
			if ( !in_array( $this_page->post_status , $this->post_status ) ) continue;
			
			//pages
			$page_summary[ $this_page->post_status ][ $this_page->updatez_status ]++;
			
			//users
			if ( !array_key_exists( $this_page->updatez_updater , $user_summary ) ) {
				$user_summary[ $this_page->updatez_updater ] = $this_user_summary;
			}
			$user_summary[ $this_page->updatez_updater ][ $this_page->updatez_status ]++;
		}
		
		$summary = array( 
			'pages'=>$page_summary ,
			'users'=>$user_summary ,
		);
		return $summary;
	}
		
	/**
	/* Enqueue JavaScript for 
	/* 
	 */	
	function idies_admin_enqueue_scripts( $hook ) {

		if ( 'edit.php' === $hook &&
			isset( $_GET['post_type'] ) && $this->post_type === $_GET['post_type'] ) {
				wp_enqueue_script( 'idies_admin_script' , ICT_DIR_URL . 'js/admin_edit.js' ,
					false, null, true );
		}
	}
	
	function wp_dropdown_status( $args = '' ) {
		
		// Defaults: Add "All Statuses" option; echo output; "All Statuses" selected by default; name of <select> = 'statuz'; no default class for <select>
		$defaults = array(
			'show_option_all' => 'All Statuses', 
			'echo' => 1,
			'selected' => 0, 
			'name' => 'statuz', 
			'class' => '', 
		);
	 
		// Combine default and supplied args
		$my_args = wp_parse_args( $args, $defaults );
		$show_option_all = $my_args['show_option_all'];
		$statuses = array_merge( array( "0"=>$show_option_all) , $this->statuses );
		$status_keys = array_keys( $statuses );
	 
		$output = '';
		if ( ! empty( $statuses ) ) {
			
			$name = esc_attr( $my_args['name'] );
			$id = " id='$name'";
			$output = "<select name='{$name}'{$id} class='" . $my_args['class'] . "'>\n";
	  
			foreach ( (array) $statuses as $this_key=>$this_status ) {
	 
				$_selected = selected( $this_key , $my_args['selected'], false );
				
				$output .= "\t<option value='$this_key' $_selected>$this_status</option>\n";
			}
	 
			$output .= "</select>";
		}
	  
		if ( $my_args['echo'] ) {
			echo $output;
		}
		return $output;
	} 
}
?>
