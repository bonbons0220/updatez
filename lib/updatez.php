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
	 * Array of Panel class values to display Status on page
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
	 * @var    object
	 */
	public $default_updater;
	
	/**
	 * Initial page updater for new pages and nuclear option.
	 *
	 * @var    object
	 */
	public $updater;
	
	/**
	 * Default page status.
	 *
	 * @var    string
	 */
	public $default_status;

	/**
	 * Initial page status for new pages and nuclear option.
	 *
	 * @var    string
	 */
	public $status;

	/**
	 * Default directory to hold temporary files.
	 *
	 * @var    string
	 */	 
	public $default_tmppath;
	
	/**
	 * Name of directory to hold temporary files.
	 *
	 * @var    string
	 */	 
	public $tmppath;
	
	/**
	 * Default for Show status on front end
	 *
	 * @var    boolean
	 */	 
	public $default_frontend;
	
	/**
	 * Show status on front end
	 *
	 * @var    boolean
	 */	 
	public $frontend;
	
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
	 * Name of url for temporary files.
	 *
	 * @var    string
	 */	 
	public $tmpurl;
		
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
	public $get_updater;
	
	/**
	 * $_GET['statuz']
	 *
	 * @var    string
	 */	 
	public $get_statuz;
	
	/**
	 * $_GET['statuz']
	 *
	 * @var    string
	 */	 
	public $actions;
	
	/**
	 * Default for whether to show update status on All Pages Screen
	 *
	 * @var    boolean
	 */	 
	public $default_pages_screen;
	
	/**
	 * Flag for whether to show update status on All Pages Screen
	 *
	 * @var    boolean
	 */	 
	public $pages_screen;
	
	/**
	 * Default for whether to Preview or Send Emails
	 *
	 * @var    boolean
	 */	 
	public $default_preview_emails;
	
	/**
	 * Flag for whether to preview or send emails
	 *
	 * @var    boolean
	 */	 
	public $preview_emails;
	
	/**
	 * Public constructor method to prevent a new instance of the object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */	
	public function __construct() {
		
		register_activation_hook( __FILE__, array( 'updatez', 'activate' ) );
		//register_deactivation_hook( __FILE__, 'deactivate' );
		
		// Don't show on Prod/WWW
		if ( !defined( 'WP_ENV' )) {
		    define( 'WP_ENV' , 'production' );
		}
			
		//date_default_timezone_set('America/New_York');
		date_default_timezone_set('UTC');
		
		/********************************************************************************/
		/*********** FILTERS AND ACTIONS ************************************************/
		/********************************************************************************/
		// FRONT END STATUS
		add_filter( 'the_content' , array( $this , 'showstatus' ) );
		
		// DASHBOARD PAGES
		add_action( 'admin_menu' , array( $this , 'create_admin_menu' ) );
		add_action( 'admin_menu' , array( $this , 'get_pages' ) );
		
		// ADMIN CONTENT EDITOR
		add_action( 'add_meta_boxes_page' , array( $this , 'meta_box_page' ) );		
		add_action( 'save_post' , array( $this , 'save_meta' ) );
		
		// ALL PAGES SCREEN
		add_action( 'admin_enqueue_scripts' , array(  $this , 'admin_enqueue_scripts' ) );
		
		//                  CUSTOM COLUMNS
		add_action( 'manage_pages_custom_column' , array( $this , 'page_column_content' ) , 10 , 2 );
		add_filter( 'manage_pages_columns' , array( $this , 'custom_pages_columns' ) );
		add_filter( 'manage_edit-page_sortable_columns' , array( $this , 'sortable_pages_column' ) );
		add_action( 'pre_get_posts' ,array(  $this , 'custom_columns_column_orderby' ) );
		
		//                  QUICK EDIT
		add_action( 'quick_edit_custom_box' , array( $this , 'display_quickedit_custom') , 10, 2 );
		add_action( 'save_post' , array(  $this , 'save_quickedit_custom' ) );
		
		//                  FILTERING OPTIONS
		add_filter( 'posts_where' , array( $this , 'posts_where' ) );
		add_action( 'restrict_manage_posts' , array( $this , 'add_filter_options' ) );

		/********************************************************************************/
		/*********** VARIABLES **********************************************************/
		/********************************************************************************/
		
		// DEFAULTS
		$this->default_pages_screen=true;
		$this->default_preview_emails=true;
		$this->default_frontend = true;
		$this->default_updater = false;
		$this->default_status = 'not-started';
		$this->default_tmppath = '/data1/dswww-ln01/sdss.org/updates';
		
		$this->get_options();
		
		$this->meta_fields = array( 
			'updatez_status',
			'updatez_updater',
			'updatez_comment',
		);
		$this->post_type = 'page';
		$this->post_status  = array( 
			'publish',
			'private',
			//'trash',
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
		$this->get_updater = "updater";
		$this->get_statuz = "statuz";
		
		$this->all_users = get_users( 'orderby=nicename' );
		
		// SETTINGS PAGES ACTIONS
		$this->actions = array(
			'overview'=>array( ),
			'settings'=>array( 'update' , 'nuclear' , 'notify' ),
			'import'=>array( 'import' ),
			'export'=>array(  ),
		);
		
		// IMPORT EXPORT
		$this->tmpurl = '/wp-tmp';							// TEMP FILE LOCATION
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

		$this->default_status = ( in_array( $this->default_status , $this->statuses ) ) ? $this->default_status : $this->statuses[ 0 ] ;

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
		add_menu_page(   'Status Updates' , 'Status Updates' , 'edit_posts' , 'idies-status-menu', array( $this , 'show_page_overview' ) ,'dashicons-yes' );
		
		// Add subpages to the main menu. Note that the Overview page re-uses the Main page slug, which avoids a duplicate subpage for the main page
		add_submenu_page('idies-status-menu' , 'Status Updates Overview',      'Overview',      'edit_posts',         'idies-status-menu',     array( $this , 'show_page_overview' ) );
		add_submenu_page('idies-status-menu' , 'Status Updates Settings',      'Settings',      'edit_theme_options', 'idies-status-settings', array( $this , 'show_page_settings' ) );
		add_submenu_page('idies-status-menu' , 'Status Updates Import', 'Import', 'edit_theme_options', 'idies-status-import',   array( $this , 'show_page_import' ) );
		add_submenu_page('idies-status-menu' , 'Status Updates Export', 'Export', 'edit_theme_options', 'idies-status-export',   array( $this , 'show_page_export' ) );
		
	}

	/**
	/* Show the Dashboard OVERVIEW Page
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function show_page_overview() {
		
		if ( !current_user_can( 'edit_posts' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$result = '';
		$this_page = 'overview';
		
		// Go through pages and get summary of update statuses.
		$summary = $this->get_summary( $messages );
		
		// Get and Do the Action, if there is one
		$messages = $this->do_action( $this_page );
		
		// Show Dashboard Notice ( notice-error, -success, -info, or -warning )
		foreach ($messages as $thiskey=>$thisvalue) {
			$result .= '<div class="notice notice-' . $thiskey . ' is-dismissible">' . $thisvalue . '</div>';
		}
				
		$result .= '<div class="wrap">';
		$result .= '<h1>Overview</h1>';
				
		/* PAGES */
		$result .= '<hr>';
		$result .= '<h2>Pages</h2>';
		$result .= '<table class="form-table">';
		$result .= '<thead>';
		$result .= '<tr>';
		$result .= '<th>&nbsp;</th>';
		foreach( $this->statuses as $this_update_status ) $result .= '<th>' . $this_update_status . '</th>';
		$result .= '</tr>';
		$result .= '</thead>';
		$result .= '<tbody>';
		foreach( $this->post_status as $this_page_status ) {
			$result .= '<tr>';
			$result .= '<th scope="row">' . ucfirst($this_page_status) . '</th>';
			foreach( $this->statuses as $this_update_key=>$this_update_status ) {
				$result .= 
					'<td>' . 
					'<a href="/wp-admin/edit.php?post_type=page' .
						'&statuz=' . $this_update_key . 
						'&post_status=' . $this_page_status . '">' . 
					$summary['pages'][ $this_page_status ][ $this_update_key ] . 
					'</a>';
					'</td>';
			}
			$result .= '</tr>';
		}
		$result .= '</tbody>';
		$result .= '</table>';
		
		$result .= '<hr>';
			
		/* USERS */
		$result .= '<h2>Users</h2>';
		$result .= '<table class="form-table">';
		$result .= '<thead>';
		$result .= '<tr>';
		$result .= '<th>&nbsp;</th>';
		foreach( $this->statuses as $this_update_status ) $result .= '<th>' . $this_update_status . '</th>';
		$result .= '</tr>';
		$result .= '</thead>';
		$result .= '<tbody>';
		foreach( $this->all_users as $this_user ) {
			if ( !array_key_exists( $this_user->user_nicename , $summary['users'] ) ) continue;
				$result .= '<tr>';
				$result .= '<th scope="row">' . $this_user->display_name . '</th>';
				foreach( $this->statuses as $this_update_status ) {
					$result .= '<td>' . 
					'<a href="/wp-admin/edit.php?post_type=page&all_posts=1' .
						'&updater=' . $this_user->user_nicename .
						'&statuz=' . array_shift( array_keys( $this->statuses , $this_update_status ) ) . '">' . 
					$summary['users'][ $this_user->user_nicename ][ array_shift( array_keys( $this->statuses , $this_update_status ) ) ] . 
					'</a>';
					'</td>';
				}
				$result .= '</tr>';
		}
	
		$result .= '</tbody>';
		$result .= '</table>';

		$result .= '<hr>';
		
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
	function show_page_settings() {
		
		if ( !current_user_can( 'edit_theme_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$result = '';
		$this_page = 'settings';
		
		// Get and Do the Action, if there is one
		$messages = $this->do_action( $this_page );
		
		// Get Options
		$this->get_options();
		
		// Show Dashboard Messages ( notice-error, -success, -info, or -warning )
		foreach ($messages as $thiskey=>$thisvalue) {
			$result .= '<div class="notice notice-' . $thiskey . ' is-dismissible">' . $thisvalue . '</div>';
		}

		// Show this Page
		$result .= '<div class="wrap">';
		$result .= '<h1>Settings and Utilities</h1>';
		
		$result .= '<hr>';
		$result .= '<h2>Settings</h2>';
		$result .= '<form method="post" action="">';
		$result .= '<input type="hidden" name="update" value="update">';
		$result .= wp_nonce_field( 'save_settings_action' , 'save_settings_nonce' , false );
		
		$result .= '<table class="form-table">';
		$result .= '<tbody>';

		$result .= '<tr>';
		$result .= '<th scope="row">Show Status on Front End</th>';
		$result .= '<td>';
		$result .= '<input type="checkbox" name="frontend" value="true" id="frontend" ' . checked( $this->frontend , true , false ) . ' >';
		$result .= '</td>';
		$result .= '</tr>';

		$result .= '<tr>';
		$result .= '<th scope="row">Preview Reminder Emails</th>';
		$result .= '<td>';
		$result .= '<input type="checkbox" name="preview_emails" value="true" id="preview_emails" ' . checked( $this->preview_emails , true , false ) . ' >';
		$result .= '</td>';
		$result .= '</tr>';

		$result .= '<tr>';
		$result .= '<th scope="row">Show Update Status on All Pages Screen</th>';
		$result .= '<td>';
		$result .= '<input type="checkbox" name="pages_screen" value="true" id="pages_screen" ' . checked( $this->pages_screen , true , false ) . ' >';
		$result .= '</td>';
		$result .= '</tr>';

		$result .= '<tr>';
		$result .= '<th scope="row">Default Path (Writable dir for temp files)</th>';
		$result .= '<td>';
		$result .= '<input type="text" name="tmppath" id="tmppath" value="' . $this->tmppath . '" >';
		$result .= '</td>';
		$result .= '</tr>';

		$result .= '<tr>';
		$result .= '<th scope="row">Default Updater</th>';
		$result .= '<td>';
		$result .= '<select name="updater" id="updater">';
		foreach ( $this->all_users as $thisuser ) {
			$result .= '<option value="' . $thisuser->user_nicename . '"' . selected( $this->updater , $thisuser->user_nicename , false ) . '>' . $thisuser->display_name . '</option>';
		}
		$result .= '</select>';
		$result .= '</td>';
		$result .= '</tr>';

		
		$result .= '<tr>';
		$result .= '<th scope="row">Default Status</th>';
		$result .= '<td>';
		$result .= '<select name="status" id="status">';
		foreach ( $this->statuses as $thiskey => $thisvalue ) {
			$result .= '<option value="' . $thiskey . '"' . selected( $this->status , $thiskey , false ) . '>' . $thisvalue. '</option>';
		}
		$result .= '</select>';
		$result .= '</td>';
		$result .= '</tr>';
		
		$result .= '<tr>';
		$result .= '<th scope="row">Update Settings</th>';
		$result .= '<td><input type="submit" name="submit" id="submit" class="button button-primary" value="Update"></td>';
		$result .= '</tr>';
		
		$result .= '</tbody>';
		$result .= '</table>';

		$result .= '<hr>';
		$result .= '<h2>Utilities</h2>';

		$result .= '<table class="form-table">';
		$result .= '<tbody>';
		$result .= '<tr>';
		$result .= '<th scope="row">Reset All Pages to Defaults</th>';
		$result .= '<td><button class="button button-secondary" id="nuclear" name="nuclear" value="nuclear">Reset</button></td>';
		$result .= '</tr>';
	
		$result .= '<tr>';
		$result .= '<th scope="row">Send Reminder Emails to Updaters</th>';
		$btntxt = ( $this->preview_emails ) ? 'Preview the Emails' : 'Send the Emails' ; 
		$result .= '<td><button class="button button-secondary" id="notify" name="notify" value="notify">' . $btntxt . '</button></td>';
		$result .= '</tr>';
	
		$result .= '</tbody>';
		$result .= '</table>';
		
		$result .= '<hr>';

		$result .= '</form>';
		$result .= '</div>';

		echo $result;
	}

	/**
	/* Show the Dashboard IMPORT Page
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function show_page_import() {
		
		if ( !current_user_can( 'edit_theme_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$result = '';
		$this_page = 'import';
		
		// Get and Do the Action, if there is one
		$messages = $this->do_action( $this_page );
		
		// Show Dashboard Messages ( notice-error, -success, -info, or -warning )
		foreach ($messages as $thiskey=>$thisvalue) {
			$result .= '<div class="notice notice-' . $thiskey . ' is-dismissible">' . $thisvalue . '</div>';
		}

		// Show this Page		
		$result .= '<div class="wrap">';
		$result .= '<h1>Import Update Statuses</h1>';
		$result .= '<form method="post" action="" enctype="multipart/form-data">';
		$result .= wp_nonce_field( 'save_settings_action' , 'save_settings_nonce' , false );
		$result .= '<table class="form-table">';
		$result .= '<tbody>';

		$result .= '<tr>';
		$result .= '<th scope="row" rowspan="2">Import Page Updates CSV...</th>';
		$result .= '<td colspan="2"><input type="file" name="importfile" id="importfile" class="widefat"></td>';
		$result .= '</tr>';
		$result .= '<tr>';
		$result .= '<td><button type="submit" class="button button-primary" id="import" name="import" value="import">Import</button></td>';
		$result .= '<td scope="row"><em>N.b. you can only update the <strong>Updater</strong>, <strong>Status</strong>, ' . 
			 'and/or <strong>Comments</strong> with CSV import. All other columns are ignored (but ' .
			 'column titles and order must match CSV Export file). ' .
			 'Also, the <strong>Updater</strong> must be a WordPress login for a valid user ' .
			 '(e.g. bsouter), not a display name (e.g. Bonnie Souter). You can find users\' login ' . 
			 'names on <a href="/wp-admin/users.php">All Users</a>.</em></td>';
		$result .= '</tr>';
				
		$result .= '</tbody>';
		$result .= '</table>';
		$result .= '</form>';
		$result .= '</div>';
		
		echo $result;
	}

	/**
	/* Show the Dashboard EXPORT Page
	/* 
	 * @since  1.4
	 * @access public
	 * @return void
	 */	
	function show_page_export() {
		
		if ( !current_user_can( 'edit_posts' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$result = '';
		$this_page = 'export';
		
		// Write the export file each time page is loaded.
		$fname = $this->write_export_data();
		
		// Get and Do the Action, if there is one
		$messages = $this->do_action( $this_page );
		
		// Show Dashboard Messages ( notice -error, -success, -info, or -warning )
		if ( !empty( $messages ) ) {
			foreach ( $messages as $thiskey=>$thisvalue ) {
				$result .= '<div class="notice notice-' . $thiskey . ' is-dismissible">' . $thisvalue . '</div>';
			}
		}
				
		$result .= '<div class="wrap">';
		$result .= '<h1>Export Page Update Status</h1>';
		$result .= '<form method="post" action="" enctype="multipart/form-data">';
		$result .= wp_nonce_field( 'save_settings_action' , 'save_settings_nonce' , false );
		$result .= '<table class="form-table">';
		$result .= '<tbody>';

		$result .= '<tr>';
		$result .= '<th scope="row">Export Page Updates as CSV...</th>';
		$result .= '<td><a class="button button-secondary" href="' . $this->tmpurl . '/' . $fname . '" >Export</a></td>';
		$result .= '<td><em>Export to CSV file to update Status, Updater, and Comments of multiple pages manually.</em></td>';
		$result .= '</tr>';
				
		$result .= '</tbody>';
		$result .= '</table>';
		$result .= '</form>';
		$result .= '</div>';
		
		echo $result;
	}

	/**
	// Deal with action requested for dashboard page`
	/* 
	 * @since  1.4
	 * @access private
	 * @return void
	 */	
	private function do_action( $this_page = false ) {
		
		$messages = array();
		
		if ( !array_key_exists( $this_page , $this->actions ) ) {
			$messages = array_merge( $messages , array( 'error'=>"$this_page has no actions." ) );
			return $messages;
		}
		
		$nothing = true;
		foreach ( $this->actions[$this_page] as $this_action ) {
			if ( isset( $_POST[ $this_action ] ) && !empty( $_POST[ $this_action ] ) ) {
				
				check_admin_referer( 'save_settings_action' , 'save_settings_nonce' );
				
				if ( method_exists( $this , "do_action_$this_action" ) ) {
					$messages = array_merge( $messages , $this->{"do_action_" . $this_action}(  ) );
					$nothing=false;
				} else {
					$messages = array_merge( $messages , array( 'error'=>"do_action_$this_action() not found." ) );
				}
			}
		}
		return $messages;
	}

	/**
	/* Update plugin options
	/* 
	 * @since  1.4
	 * @access private
	 * @return void
	 */	
	private function do_action_update() {
		
		$updated = false;
		$result = array( ); 
		
		//debug
		//$result = array( 'info'=>var_export( $_POST , true ) ); 
		
		// Text and Select form Input fields
		$inputs = array( 
			'tmppath' ,
			'updater' ,
			'status' ,
		);
		foreach( $inputs as $this_option ) {
			if ( isset( $_POST[ $this_option ] ) && !empty( $_POST[ $this_option ] ) ) {
				$this->$this_option = rtrim( $_POST[ $this_option ] , '/' );
				$updated = ( update_option( 'updatez_' . $this_option , $this->$this_option ) ) ? true : $updated ;
			} 	
		}
		
		$checks = array( 
			'frontend' ,
			'preview_emails' ,
			'pages_screen' ,
		);
		foreach( $checks as $this_option ) {
			$this->$this_option = ( isset( $_POST[ $this_option ] ) ) ? true : false ; 
			$updated = ( update_option( 'updatez_' . $this_option , $this->$this_option ) ) ? true : $updated ;
		}

		$result = ( $updated ) ? array_merge( $result , array( 'success'=>'Settings updated.' ) ) : $result ;
		return $result;
	}

	/**
	/* Nuclear option to reset Page updater, status, and comments to initial values
	/* 
	 * @since  1.1
	 * @access private
	 * @return void
	 */	
	private function do_action_nuclear() {
		
		foreach ( $this->all_pages as $thispage ){
			update_post_meta( $thispage->ID , 'updatez_status' , $this->status ) ;
			update_post_meta( $thispage->ID , 'updatez_updater' , $this->updater ) ;
			update_post_meta( $thispage->ID , 'updatez_comment' , '' ) ;
		}
	
		$result = array( 'success'=>'All Pages reset.' ) ; 
		return $result;
	}		
	
	/**
	/* Notify all users of the pages assigned to them.
	/* 
	 * @since  1.4
	 * @access private
	 * @return array( string=>string )
	 */	
	private function do_action_notify() {
		
		$message = '';
		
		$result = array();
		$sent = 0;
		$notsent = 0;
				
		$thisupdater = get_user_by( 'slug' , $this->updater ); 
					
		//Need to override the default 'text/plain' content type to send a HTML email.
		add_filter('wp_mail_content_type', array( $this, 'override_mail_content_type'));

		// Get the list of pages of each update status for each user
		$user_updates = $this->get_user_updates();
		
		//Let auto-responders and similar software know this is an auto-generated email
		//that they shouldn't respond to.
		//$headers = array('Auto-Submitted: auto-generated');
		$subject = 'REMINDER SDSS Status Updates';

		// send an email to each user with update statuses in it.
		foreach ( $user_updates as $slug=>$this_user_update ) {
			
			$body = '';
			
			// check user exists
			if ( false !== $thisuser = get_user_by( 'slug' , $slug ) ) {
				
				$body .= '<h2>SDSS Page Status Update for ' . $thisuser->display_name . '.</h2>';
				$body .= ( $this->preview_emails ) ? "Preview on. Sending all emails to " . $thisupdater->display_name . ".\n" : '' ; 

				foreach( $this->statuses as $this_status_key => $this_status ) {
					
					// Skip 0's
					if ( empty( $this_user_update[ $this_status_key ] ) ) continue;
					
					$body .=  '<h3>' . $this->statuses[ $this_status_key ] . '</h3>' . PHP_EOL;
					$body .=  '<p>You have ' . count( $this_user_update[ $this_status_key ] ) . ' pages with status "' . $this_status . '".</p>' . PHP_EOL;
					
					switch ( $this_status_key ) {
						case 'completed' :
							break;
						case 'do-not-publish' :
							$body .=  '<p>Please ensure that pages with status "Do Not Publish" have their status set to "Private", or "Draft", and are not linked from other published pages.</p>' . PHP_EOL;
						default:
							$body .=  "<ul>" . PHP_EOL;
							foreach ( $this_user_update[ $this_status_key ] as $thispage ) {
								$body .=  '<li><a href="' . get_the_permalink( $thispage ) . '">' . get_the_title( $thispage ) . '</a></li>' . PHP_EOL;
							}
							$body .=  "</ul>" . PHP_EOL;
							break;
					}
				}
				
				// If previewing, send to this default updater's email address
				$to_email = ( $this->preview_emails ) ?  $thisupdater->user_email : $thisuser->user_email ;
				
				// Send the emails
				if ( wp_mail( $to_email , $subject , $body ) ) $sent++;
					else $notsent++;
					
				// Maybe add to string to echo
				if ( $this->preview_emails ) {
					$message .= "To: " . $thisuser->user_email . PHP_EOL;
					$message .= "Subject: " . $subject . PHP_EOL;
					$message .= "Body: " . $body . PHP_EOL;
					$message .= "<hr>";
				}
			}
		}

		//Remove the override so that it doesn't interfere with other plugins that might
		//want to send normal plaintext emails.
		remove_filter('wp_mail_content_type', array( $this, 'override_mail_content_type'));
		
		$result = ( $sent ) ? array_merge( $result , array( 'success'=>"Sent $sent Emails!" ) ) : $result ; 
		$result = ( $notsent ) ? array_merge( $result , array( 'error'=>"Could not send $notsent emails." ) ) : $result ; 
		
		$result = array_merge( $result , array( 'info'=>$message ) ); 
		
		return $result;
	}

	/**
	// Import a CSV file with Page Status Updates
	/* 
	 * @since  1.1
	 * @access private
	 * @return void
	 */	
	private function do_action_import() {

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
			$error .= "Error. User not found: " . $this_csv[5] . ", User not found, skipping " . $this_csv[0] . "...<br>\n";
			continue;
		} else if ( ( $this_key = array_search( $this_csv[4], $this->statuses ) ) === false ) {
			$error .= "Error. Status not found: " . $this_csv[4] . ", Status not found, skipping " . $this_csv[0] . "...<br>\n";
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
	// Get All Pages 
	/* 
	 * @since  1.4
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
	// Write Status Updates CSV export file
	/* 
	 * @since  1.1
	 * @access private
	 * @return $result
	 */	
	private function write_export_data(  ) {

		$output = '';
		$sep = ',';
		$quo = '"';
		
		$fname = sanitize_title( home_url( ) . '-export-' . date('Ymd-Hi') ) . '.csv';

		$fpath = $this->tmppath . "/" . $fname;

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
		$expfile = fopen( $fpath , "w") or die("Unable to create ". $fpath ." file.");
		fwrite($expfile, $output);
		fclose($expfile);
		
		return $fname;
		
	}		

	/**
	/* Change the default mail content type to html
	 *
	 * @since  1.4
	 * @access public
	 * @return string
	 */	
	function override_mail_content_type( $content_type){
		return 'text/html';
	}

	/**
	/* Save Meta box Data from Content Editor
	 *
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function save_meta($post_id){	
		
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
	function meta_box_page( $page ){
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
		
		$updater = isset( $values['updatez_updater'] ) ? esc_attr( $values['updatez_updater'][0] ) : $this->updater ;
		$status = isset( $values['updatez_status'] ) ? esc_attr( $values['updatez_status'][0] ) : $this->status ;
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
	/* Show a custom column on the All Pages admin screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function page_column_content( $column_name, $post_id ) {
		
		//if ( !$this->pages_screen ) return;
		
		if ( $column_name == 'update_updater' ) {
			$the_user = get_user_by(  'slug' , get_post_meta( $post_id , "updatez_updater" , true) );
			echo "<a href='" . add_query_arg( $this->get_updater , $the_user->user_nicename ) . "'>";
			echo $the_user->display_name . "<span class='lookup hidden'>$the_user->user_nicename</span>";
			echo "</a>";
		} 
		
		if ( $column_name == 'update_status' ) {
			$the_status = get_post_meta( $post_id , "updatez_status" , true) ;
			$the_user = get_user_by(  'slug' , get_post_meta( $post_id , "updatez_updater" , true) );
			echo "<a href='" . add_query_arg( $this->get_statuz , $the_status ) . "'>";
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
	function custom_pages_columns( $columns ) {

		if ( !$this->pages_screen ) return $columns;

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
	function sortable_pages_column( $columns ) {
	
		if ( !$this->pages_screen ) return $columns;

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
	function custom_columns_column_orderby( $query ) {
	 
		//if ( !$this->pages_screen ) return;

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
	function display_quickedit_custom( $column_name, $post_type ) {
		
		if ( !$this->pages_screen ) return;
		
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
	/* Saves custom fields in Quick Edit box on All Pages screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function save_quickedit_custom( $post_id ) {

		//if ( !$this->pages_screen ) return;

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
		
		if ( !$this->pages_screen ) return $where;

		$get_updater = $this->get_updater;
		$get_statuz = $this->get_statuz;
		
		$where .= " and post_status != 'pending' ";
		
		if( is_admin() ) {
			global $wpdb;
			
			if ( isset( $_GET[$get_updater] ) && 
				!empty( $_GET[$get_updater] ) ) {

				if ( false !== $updater_info = get_user_by( 'slug' , $_GET[ $get_updater ] ) ) {
					$where .= " and ID in " .
						"(select post_id from wp_postmeta WHERE meta_value = '" . $updater_info->user_nicename . "' and post_id in " .
						"(select post_id from wp_postmeta where meta_key='updatez_updater' ) )";
				}
			}
			
			if ( isset( $_GET[$get_statuz] ) && 
				!empty( $_GET[$get_statuz] ) ) {
				
				$statuz = sanitize_title( $_GET[$get_statuz] );
				$where .= " and ID in " .
						"(select post_id from wp_postmeta WHERE meta_value = '$statuz' and post_id in " .
						"(select post_id from wp_postmeta where meta_key='updatez_status' ) )";
			}
		}
		return $where;
	}	
	
	/**
	/* Show Users dropdown for Filter Options on All Pages Admin Screen
	/* 
	 */	
	function add_filter_options(  ) {

		global $typenow;
		
		if ('page' !== $typenow ) return;
		if ( !$this->pages_screen ) return;
			
		$updater = $this->get_updater;
		$statuz = $this->get_statuz;
			
		$selected_user = ( isset( $_GET[ $updater ] ) && !empty( $_GET[ $updater ] ) && get_user_by( 'slug' , $_GET[$updater ] ) ) ? 
			$_GET[ $updater ] : 
			0 ;
				
		$selected_status = ( isset( $_GET[$statuz] ) && 
			!empty( $_GET[$statuz] ) &&
			array_key_exists( $_GET[$statuz] , $this->statuses ) ) ? 
				$_GET[$statuz] :
				0 ;
	
		
		// Add Updater (Users) Dropdown to Filter Options section
		$this->wp_dropdown_updaters( );
		
		// Add Status Dropdown to Filter Options section
		$this->wp_dropdown_status( array(
			'selected'			=>  $selected_status ,
		));
	}

	/**
	/* Get plugin options or use default options
	/* 
	 * @since  1.4
	 * @access private
	 * @return void
	 */	
	private function get_options() {
		
		$this->pages_screen = get_option( 'updatez_pages_screen' , $this->default_pages_screen );
		$this->preview_emails = get_option( 'updatez_preview_emails' , $this->default_preview_emails );
		$this->frontend = get_option( 'updatez_frontend' , $this->default_frontend );
		$this->tmppath = get_option( 'updatez_tmppath' , $this->default_tmppath );
		$this->updater = get_option( 'updatez_updater' , $this->default_updater );
		$this->status = get_option( 'updatez_status' , $this->default_status );
	}

	/**
	/* Get Users' Pages and their Update Status for Notification Emails 
	 * @since  1.4
	 * @access private
	 * @return $user_updates
	 */	
	private function get_user_updates(  ) {
		
		$user_updates = array();
		$this_user = array();
		foreach ( $this->statuses as $statuskey=>$statusvalue ) {
			$this_user_status[ $statuskey ] = array();
		}
		
		foreach ( $this->all_pages as $this_page ) {
			
			// initialize if new
			if ( !array_key_exists( $this_page->updatez_updater , $user_updates ) ) {
				$user_updates[ $this_page->updatez_updater ] = $this_user_status;
			}
			
			// increment
			if ( !empty( $this_page->updatez_status ) ) {
				$user_updates[ $this_page->updatez_updater ][ $this_page->updatez_status ][] = $this_page->ID;
			}
		}
		
		return $user_updates;
	}

	/**
	/* Get summary of Status Updates for Overview page 
	 /* 
	 * @since  1.4
	 * @access private
	 * @return string
	 */	
	private function get_summary( &$messages = array() ) {
		
		$this_summary = array();
		foreach ( $this->statuses as $key=>$value ) {
			$this_summary[ $key ] = 0;
		}
		
		$page_summary = array();
		$user_summary = array();
		
		//loop through all the pages
		foreach ( $this->all_pages as $this_page ) {
			
			// if not tracking this post_status, continue. Eg 'revision'
			if ( !in_array( $this_page->post_status , $this->post_status ) ) continue;
			
			$pstat = $this_page->post_status;
			$ustat = ( !empty( $this_page->updatez_status ) && ( array_key_exists( $this_page->updatez_status , $this->statuses ) ) ) ? $this_page->updatez_status : $this->status ; 
			$pupdater = ( empty( $this_page->updatez_updater ) || ( false === get_user_by( 'slug' , $this_page->updatez_updater ) ) ) ? $this->updater : $this_page->updatez_updater ;

			//pages
			if ( empty( $page_summary[ $pstat ] ) ) 
				$page_summary[ $pstat ] = $this_summary;
			else
				$page_summary[ $pstat ][ $ustat ]++;
			
			//users
			if ( empty( $user_summary[ $pupdater ] ) )
				$user_summary[ $pupdater ] = $this_summary;
			else
				$user_summary[ $pupdater ][ $ustat ]++;

		}
		
		$summary = array( 
			'pages'=>$page_summary ,
			'users'=>$user_summary ,
		);
		return $summary;
	}
		
	/*/
	// Enqueue JavaScript for All Pages Screen
	//
	// @since  1.4
	// @access public
	// @return none 
	/*/	
	function admin_enqueue_scripts( $hook ) {

		// All Pages Screen
		if ( 'edit.php' === $hook &&
			$this->pages_screen &&
			isset( $_GET['post_type'] ) && $this->post_type === $_GET['post_type'] ) {
								
				wp_enqueue_script( 'updatez_admin_script' , ICT_DIR_URL . 'js/admin_edit.js' ,
					false, null, true );
		}
	}
	
	/*/
	// Show Dropdown Update Status Select
	//
	// @since  1.4
	// @access private
	// @return string 
	/*/	
	private function wp_dropdown_updaters( $class='' , $echo=true ) {
		
		$result = '' ;
		$result .= '<select name="updater" class="' . $class . '" id="updater">';
		$result .= '<option value="' . 0 . '">All Updaters</option>';
		foreach ( $this->all_users as $thisuser ) {
			$result .= '<option value="' . $thisuser->user_nicename . '">' . $thisuser->display_name . '</option>';
		}
		$result .= '</select>';
		
		if ( !$echo ) return $result;
		
		echo $result;
	} 

	/*/	
	// Show Dropdown Update Status Select
	//
	// @since  1.4
	// @access private
	// @return string 
	/*/	
	private function wp_dropdown_status( $args = '' ) {
		
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

	/**
	/* Show the status on a page, under the content.
	/* 
	 * @since  1.0
	 * @access public
	 * @return void
	 */	
	function showstatus( $content ) {
	
		// Get Options
		$this->get_options();

		// Status is only shown on dev devng test and testng.
		$update = '';
		if ( ( 'development' !== WP_ENV ) || ( !( $this->frontend ) ) ) return $content;
		
		// No status - no show
		$status = get_post_meta( get_the_ID() , 'updatez_status' , true );
		if ( empty( $status ) ) return $content;
		$updater = get_post_meta( get_the_ID() , 'updatez_updater' , true );
		$comments = get_post_meta( get_the_ID() , 'updatez_comment' , true );
		
		//if ( array_key_exists ( $status , $this->panel_class ) ) $class = $this->panel_class->$status;
		$class='panel-danger';

		$update .= '<div class="clearfix"></div>';
		$update .= '<div class="panel ' . $this->panel_class[$status] . '">';
		$update .= '<div class="panel-heading"><h3 class="panel-title">' . $this->statuses[$status] . '</h3></div>';
		$update .= '<div class="panel-body">';
		$update .= "Updater: " . $updater . "<br>\n";
		$update .= "Comments: " . $comments;
		$update .= '</div></div>';
		
		return $content  . $update;
	}
}
?>
