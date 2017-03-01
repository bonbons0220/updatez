<?php 
/**
 * IDIES Content Tracker WordPress plug-in tracks update status of pages.
 *
 * Tracks the assigned reviewer, status, and comments for each page that is 
 * published, private, or in draft on the website.
 *
 * @link git@bitbucket.org:idies/idies-update-tracker.git
 *
 * @package IDIES Content Tracker
 * @subpackage idies_content_tracker main class
 * @since 1.0.0 (when the file was introduced)
 */

/**
 * idies_content_tracker Main Class definition
 *
 * @since 1.0.0
 */
 class idies_content_tracker {

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
	 * Array of all pages that should have a status.
	 *
	 * @var    array
	 */
	public $all_pages;
	
	/**
	 * Default page reviewer.
	 *
	 * @var    string
	 */
	public $default_reviewer;
	
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
	public $post_status  = 'publish,private,draft' ;
	
	/**
	 * Which post type to show update status on.
	 *
	 * @var    string
	 */
	public $post_type = 'page';
	
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
	 * Public constructor method to prevent a new instance of the object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */	
	public function __construct() {
		
		//register_activation_hook( __FILE__, '$this->activate' );
		//register_deactivation_hook( __FILE__, '$this->deactivate' );
		
		// Don't show on Prod/WWW
		if ( !defined( 'WP_ENV' )) {
		    define( 'WP_ENV' , 'production' );
		}
			
		// filters and actions
		add_filter( 'the_content', array($this , 'showstatus' ) );
		add_action( 'admin_menu', array( $this , 'create_admin_menu' ) );
		add_action( 'add_meta_boxes_page', array( $this , 'idies_update_meta_box_page' ) );		
		add_action('save_post', array( $this , 'idies_update_save_meta' ) );
		add_action( 'manage_pages_custom_column', array( $this , 'idies_page_column_content' ) , 10 , 2 );
		add_filter( 'manage_pages_columns', array( $this , 'idies_custom_pages_columns' ) );
		add_filter( 'manage_edit-page_sortable_columns', array( $this , 'idies_sortable_pages_column' ) );
		add_action( 'quick_edit_custom_box', array( $this , 'idies_display_quickedit_custom') , 10, 2 );
		add_action( 'save_post', array(  $this , 'idies_save_quickedit_custom' ) );
		add_action( 'admin_enqueue_scripts', array(  $this , 'idies_admin_enqueue_scripts' ) );
		
		
		// ? add_action( 'manage_pages_custom_column' , array( 'custom_book_column' ), 10, 2 );

		// Set up Variables
		$this->default_reviewer = 'bsouter';
		$this->default_status = 'not-started';
		$this->default_tmppath = '/data1/dswww-ln01/sdss.org/tmp/';
		$this->post_status  = 'publish,private,draft';
		
		$this->all_users = get_users( 'orderby=nicename' );	
		$this->export_fname = sanitize_title( home_url( ) ) . '-update-export.csv';
		$this->csv_fields = array("ID", 
			"Title",
			"Edit",
			"View",
			"Status",
			"Reviewer",
			"Comment",
			"Last Revised" );

		//UPDATE DEPENDING ON WEBSITE
		$this->tmpurl = '/wp-tmp/';
			
			
		$this->statuses = array(
			"not-started"=>"Not Started",
			"in-progress"=>"In Progress",
			"needs-review"=>"Needs Review",
			"completed"=>"Completed",
			"do-not-publish"=>"Do Not Publish",
		);
		
		$this->panel_class = array(
			'not-started' => 'panel-danger',
			'in-progress' => 'panel-warning',
			'needs-review' => 'panel-info',
			'completed' => 'panel-success',
			'do-not-publish' => 'panel-default',
		);
	}
	/**
	/* Create the Status Update Dashboard Menus
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function create_admin_menu() {
		
		// Create the top level admin menu item
		add_menu_page( 'Status Updates' , 'Status Updates', 'edit_posts', 'idies-status-menu' , array( $this , 'show_settings_page' ) ); 
		
	}

	/**
	/* Show the Dashboard SETTINGS Page
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function show_settings_page() {
		
		$result = array();
		
		if ( !current_user_can( 'edit_posts' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// PAGES
		$this->all_pages = $this->get_page_updates();
		
		$this->default_tmppath = get_option( 'idies_default_tmppath' , $this->default_tmppath );
		$this->default_reviewer = get_option( 'idies_default_reviewer' , $this->default_reviewer );
		$this->default_status = get_option( 'idies_default_status' , $this->default_status );

		// Get Action
		$the_action = ( isset( $_POST['update'] ) ? 'update' :
					  ( isset( $_POST['nuclear'] ) ? 'nuclear' :
					  ( isset( $_POST['import'] ) ? 'import' :
						false ) ) );
					
		if ( ( $the_action ) && ( ! empty( $_POST ) ) ) {
	
			check_admin_referer( 'save_settings_action', 'save_settings_nonce' );
		
			switch ($the_action) {
				case 'import' :
					$result = $this->import();
				break;
				case 'nuclear' :
					if ( !current_user_can( 'edit_theme_options' ) ) {
						echo 'You do not have sufficient permissions to use the nuclear option.';
					} else {
						$this->nuclear(  );
					}
				break;
				case 'update' :
				
					if ( isset( $_POST[ 'default_tmppath' ] ) ) {
						$this->default_tmppath = trailingslashit( $_POST[ 'default_tmppath' ] );
						update_option( 'idies_default_tmppath' , $this->default_tmppath );
					}
					
					if ( isset( $_POST[ 'default_reviewer' ] ) ) {
						$this->default_reviewer = sanitize_user( $_POST[ 'default_reviewer' ] );
						update_option( 'idies_default_reviewer' , $this->default_reviewer );
					}
					
					if ( isset( $_POST[ 'default_status' ] ) ) {
						$this->default_status = sanitize_text_field( $_POST[ 'default_status' ] );
						update_option( 'idies_default_status' , $this->default_status );
					}
					
				break;
			}
		}
		
		// Write the export file each time page is loaded.
		$this->write_export_data();
		
		// More info
		$update_args = array( 
			'meta_key' => 'idies_update_status',
			'meta_compare' => 'NOT EXISTS', 
			'meta_value' => 'foobar' ,
		);
		$updated_pages = $this->get_page_updates( $update_args );
		$completed_args = array( 
			'meta_key' => 'idies_update_status',
			'meta_value' => 'completed' ,
		);
		$completed_pages = $this->get_page_updates( $completed_args );
		
		// Show the form
		echo '<div class="wrap">';
		echo '<h1>Status Updates Settings</h1>';
		
		foreach ($result as $thiskey=>$thisvalue) {
			echo '<div class="notice notice-' . $thiskey . ' is-dismissible">' . $thisvalue . '</div>';
		}
		
		echo '<form method="post" action="/wp-admin/admin.php?page=idies-status-menu">';
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
		echo '<th scope="row">Default Reviewer</th>';
		echo '<td>';
		echo '<select name="default_reviewer" id="default-reviewer">';
		foreach ( $this->all_users as $thisuser ) {
			echo '<option value="' . $thisuser->user_nicename . '"' . selected( $this->default_reviewer , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
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

		echo '<form method="post" action="/wp-admin/admin.php?page=idies-status-menu" enctype="multipart/form-data">';
		wp_nonce_field( 'save_settings_action' , 'save_settings_nonce' );
		echo '<table class="form-table">';
		echo '<tbody>';

		echo '<tr>';
		echo '<th scope="row">All Pages</th>';
		echo '<td>' . count( $this->all_pages ) . '</td>';
		echo '<td><em>Includes published, private, and draft</em></td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">Complete</th>';
		echo '<td>' . count( $completed_pages ) . '</td>';
		echo '<td><em>Pages that have "Completed" status</em></td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">Export Page Updates as CSV...</th>';
		echo '<td><a class="button button-secondary" href="' . $this->tmpurl . $this->export_fname . '">Export</a></td>';
		echo '<td><em>Export to CSV file to update Status, Reviewer, and Comments of multiple pages manually.</em></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row" rowspan="2">Import Page Updates CSV...</th>';
		echo '<td colspan="2"><input type="file" name="importfile" id="importfile" class="widefat"></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td><button type="submit" class="button button-primary" id="import" name="import" value="import">Import</button></td>';
		echo '<td scope="row"><em>N.b. you can only update the <strong>Reviewer</strong>, <strong>Status</strong>, ' . 
			 'and/or <strong>Comments</strong> with CSV import. All other columns are ignored (but ' .
			 'column titles and order must match CSV Export file). ' .
			 'Also, the <strong>Reviewer</strong> must be a WordPress login for a valid user ' .
			 '(e.g. bsouter), not a display name (e.g. Bonnie Souter). You can find users\' login ' . 
			 'names on <a href="/wp-admin/users.php">All Users</a>.</em></td>';
		echo '</tr>';
				
		echo '<tr>';
		echo '<th scope="row">Nuclear Option: <br></th>';
		echo '<td><button class="button button-secondary" id="nuclear" name="nuclear" value="nuclear">Go Nuclear</button></td>';
		echo '<td><em>Reset all pages\' to default status, reviewer, and delete comments.</em></td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '</form>';
		echo '</div>';

		return;
	}

	/**
	// Get the Pages 
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
		$reviewer  = isset( $_GET[ 'reviewer' ] ) ?										// Show pages assigned to 'all'. 
					sanitize_text_field( $_GET[ 'reviewer' ] ) : 
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
			'post_status' => $this->post_status,
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
		if ( !count( array_diff( $fields , $this->csv_fields ) ) == 0 ) 
			return array( "error"=>"CSV file must contain the following column titles: " . implode(",",$this->csv_fields) );

		//loop through the csv and update all the files
		$error = '' ; 
		foreach( $contents as $this_line ) {
		$this_csv = str_getcsv( $this_line ) ;
			
			// Validate input
			if ( get_user_by( 'slug', $this_csv[5] ) === false ) {
				$error .= "Error. User not found: " . $this_csv[5] . ", Skipping ID " . $this_csv[0] . "...<br>\n";
				continue;
			} else if ( ( $this_key = array_search( $this_csv[4], $this->statuses ) ) === false ) {
				$error .= "Error. Status not found: " . $this_csv[4] . ", Skipping ID " . $this_csv[0] . "...<br>\n";
				continue;
			}			
			
			update_post_meta( $this_csv[0]  , 'idies_update_reviewer' , $this_csv[5] ) ;
			update_post_meta( $this_csv[0]  , 'idies_update_status' , $this_key ) ;
			update_post_meta( $this_csv[0]  , 'idies_update_comment' , $this_csv[6] ) ;
		}

		if ( strlen($error) == 0 ) $status = "success";
		return array($status=>"Uploaded " . $_FILES['importfile']['name'] . ". " . $error) ;
		
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

		//header to be downloaded and saved locally
		//$output .= "header('Content-Disposition: attachment; filename=' . $this->export_fname)" . "\n";
		//$output .= "header('Content-Type: text/plain')" . "\n";
		
		//ID, post_title, post.php?post=ID&action=edit, /post_name/, idies_update_status, idies_update_reviewer, idies_update_comment, post_modified
		$output .= $quo . implode( $quo . $sep . $quo , $this->csv_fields ). $quo . "\n"; 
		
		/* 
		$output .= 
			$quo . "ID" . $quo . $sep . 
			$quo . "Title" . $quo . $sep .
			$quo . "Edit" . $quo . $sep .
			$quo . "View" . $quo . $sep .
			$quo . "Status" . $quo . $sep .
			$quo . "Reviewer" . $quo . $sep .
			$quo . "Comment" . $quo . $sep .
			$quo . "Last Revised" . $quo . "\n";
		*/
			
		foreach ( $this->all_pages as $thispage ){
			$output .= 
				$thispage->ID . $sep . 
				$quo . $thispage->post_title . $quo . $sep . 
				$quo . site_url( "wp-admin/post.php?post=" . $thispage->ID . "&action=edit" ) . $quo . $sep . 
				$quo . site_url( "/" . $thispage->post_name . "/" ) . $quo . $sep .  
				$quo . $this->statuses[get_post_meta( $thispage->ID, "idies_update_status" , true )] . $quo . $sep . 
				$quo . get_post_meta( $thispage->ID, "idies_update_reviewer" , true ) . $quo . $sep . 
				$quo . get_post_meta( $thispage->ID, "idies_update_comment" , true ) . $quo . $sep . 
				$quo . $thispage->post_modified . $quo . "\n";
		}
		
		//write temp file
		$expfile = fopen( $this->default_tmppath . $this->export_fname , "w") or die("Unable to create ". $this->default_tmppath . $this->export_fname ." file.");
		fwrite($expfile, $output);
		fclose($expfile);
		
	}		

	/**
	/* Nuclear option to reset Page reviewer, status, and comments to defaults
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function nuclear() {
		
		foreach ( $this->all_pages as $thispage ){
			update_post_meta( $thispage->ID , 'idies_update_status' , $this->default_status ) ;
			update_post_meta( $thispage->ID , 'idies_update_reviewer' , $this->default_reviewer ) ;
			update_post_meta( $thispage->ID , 'idies_update_comment' , '' ) ;
		}
	
	}		
	

	/**
	/* Save Meta box Data from Content Editor
	 *
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_update_save_meta($post_id){	
		
		// Check nonce, user capabilities
		if ( ! isset($_POST['save_postmeta_nonce']) ) return; //in case this is a different save_post action
		check_admin_referer( 'save_postmeta_action', 'save_postmeta_nonce' );

		$newreviewer = isset( $_REQUEST['meta_box_reviewer'] ) ? sanitize_user($_REQUEST['meta_box_reviewer'] ) : false ;
		$newstatus = isset( $_REQUEST['meta_box_status'] ) ? sanitize_title($_REQUEST['meta_box_status'])  : false ;
		$newcomment = isset( $_REQUEST['meta_box_comment'] ) ? sanitize_text_field($_REQUEST['meta_box_comment']) : false ;
		
		// Anything to update?
		if ( !( $newreviewer || $newstatus || $newcomment ) ) {
			return $post_id;
		}

		if ( ! current_user_can( 'edit_posts' ) && ! wp_is_post_autosave( $post_id ) ) return $post_id;
		
		if ( $newreviewer && get_user_by( 'slug', $newreviewer ) ) {
		
			//update the post meta
			update_post_meta(
				$post_id,
				'idies_update_reviewer',
				$newreviewer
			);
		}
		
		if ( $newstatus && array_key_exists( $newstatus , $this->statuses ) ) {
		
			//update the post meta
			update_post_meta(
				$post_id,
				'idies_update_status',
				$newstatus
			);
		}

		if ( $newcomment ) {
		
			//update the post meta
			update_post_meta(
				$post_id,
				'idies_update_comment',
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
	function idies_update_meta_box_page( $page ){
		add_meta_box( 
			'idies-update-meta-box-page', 
			__( 'Status Update' ), 
			array( $this , 'render_update_meta_box' ), 
			'page', 
			'normal', 
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
		
		$reviewer = isset( $values['idies_update_reviewer'] ) ? esc_attr( $values['idies_update_reviewer'][0] ) : $this->default_reviewer ;
		$status = isset( $values['idies_update_status'] ) ? esc_attr( $values['idies_update_status'][0] ) : $this->default_status ;
		$comment = isset( $values['idies_update_comment'] ) ? esc_attr( $values['idies_update_comment'][0] ) : '' ;
		wp_nonce_field( 'save_postmeta_action', 'save_postmeta_nonce' );
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
	<th scope="row"><label for="meta_box_reviewer">Reviewer</label></th>
    <td><?php
		echo '<select  class="postbox" name="meta_box_reviewer" id="meta-box-reviewer">';
		foreach ( $this->all_users as $thisuser ) {
			echo '<option value="' . $thisuser->user_nicename . '"' . selected( $reviewer , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
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
	
		$append = '';
		
		// Status is only shown on dev devng test and testng.
		if ( 'development' !== WP_ENV) return $content;
		
		// No status - no show
		$status = get_post_meta( get_the_ID() , 'idies_update_status', true );
		if ( empty( $status ) ) return $content;
		$reviewer = get_post_meta( get_the_ID() , 'idies_update_reviewer', true );
		$comments = get_post_meta( get_the_ID() , 'idies_update_comment', true );
		
		//if ( array_key_exists ( $status , $this->panel_class ) ) $class = $this->panel_class->$status;
		$class='panel-danger';

		$update = '<div class="panel ' . $this->panel_class[$status] . '">';
		$update .= '<div class="panel-heading"><h3 class="panel-title">' . $this->statuses[$status] . '</h3></div>';
		$update .= '<div class="panel-body">';
		$update .= "Reviewer: " . $reviewer . "<br>\n";
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
	
		if ( $column_name == 'update_reviewer' ) {
			$the_user = get_user_by(  'slug', get_post_meta( $post_id , "idies_update_reviewer" , true) );
			echo $the_user->display_name . "<span class='lookup hidden'>$the_user->user_nicename</span>";
			} 
		if ( $column_name == 'update_status' ) {
			$the_status = get_post_meta( $post_id , "idies_update_status" , true) ;
			$the_user = get_user_by(  'slug', get_post_meta( $post_id , "idies_update_reviewer" , true) );
			echo $this->statuses[ $the_status ] . "<span class='lookup hidden'>$the_status</span>";
			if ( in_array( $the_status , array( "completed" , "do-not-publish" ) ) == false ) {
				echo " <a class='button' href='mailto:$the_user->user_email" . "?subject=SDSS Website Update Reminder" . 
				"&body=Hello, " . $the_user->display_name . ". \n" . 
				"You are the reviewer for \"" . get_the_title( $post_id ) . "\" (" . get_the_permalink( $post_id ) . "). " . 
				"This page is \"" . $this->statuses[ get_post_meta( $post_id , "idies_update_status" , true) ] . "\". " . 
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
			'update_reviewer' => __( 'Reviewer' ),
			'update_status' => __( 'Update Status' )
		);
		$columns = array_merge( $columns, $myCustomColumns );

		/** Remove Comments Columns **/
		unset(
			$columns['comments']
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
		$columns['update_reviewer'] = 'reviewer';
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
	function idies_display_quickedit_custom( $column_name, $post_type ) {
		// Only print the nonce once.
		static $printNonce = TRUE;
		if ( $printNonce ) {
			$printNonce = FALSE;
			wp_nonce_field( 'save_quickedit_action', 'save_quickedit_nonce' );
		}

		?>
		<fieldset class="inline-edit-col-right inline-edit-idies">
		  <div class="inline-edit-col column-<?php echo $column_name; ?>">
			<label class="inline-edit-group">
			<?php 
			switch ( $column_name ) {
				case 'update_reviewer':
					echo '<span class="title">Update Reviewer</span><select name="update_reviewer" />';
					foreach ( $this->all_users as $thisuser ) {
						//echo '<option value="' . $thisuser->user_nicename . '"' . selected( $update_reviewer , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
						echo '<option value="' . $thisuser->user_nicename . '"/>' . $thisuser->display_name . '</option>';
					}
					echo '</select>';
					break;
				case 'update_status':
					echo '<span class="title">Update Status</span><select name="update_status" />';
					foreach ( $this->statuses as $thiskey=>$thisvalue ) {
						//echo '<option value="' . $thiskey . '"' . selected( $update_status , $thiskey ) . '>' . $thisvalue . '</option>';
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
	function idies_save_quickedit_custom( $post_id ) {

		if ( ! isset($_POST['save_quickedit_nonce']) ) return; //in case this is a different save_post action
		check_admin_referer( 'save_quickedit_action', 'save_quickedit_nonce' );
		//if ( ! wp_verify_nonce( 'save_quickedit_nonce' , 'save_quickedit_action' ) ) return;
		
		if ( $this->post_type !== $_POST['post_type'] ) {
			return;
		}
		if ( !current_user_can( 'edit_posts', $post_id ) ) {
			return;
		}
		
		
		if ( isset( $_REQUEST['update_reviewer'] ) ) {
			update_post_meta( $post_id, 'idies_update_reviewer', $_REQUEST['update_reviewer'] );
		}
		if ( isset( $_REQUEST['update_status'] ) ) {
			update_post_meta( $post_id, 'idies_update_status', $_REQUEST['update_status'] );
		}
	}

	/**
	/* Populates fields in Quick Edit box on All Pages screen
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	//if ( ! function_exists('wp_my_admin_enqueue_scripts') ):
	function idies_admin_enqueue_scripts( $hook ) {

		if ( 'edit.php' === $hook &&
			isset( $_GET['post_type'] ) && $this->post_type === $_GET['post_type'] ) {
				wp_enqueue_script( 'idies_admin_script', ICT_DIR_URL . 'js/admin_edit.js' ,
					false, null, true );
		}
	}
	//endif;
	
}
?>
