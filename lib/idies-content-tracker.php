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
	public $default_reviewer = 'bsouter';
	
	/**
	 * Default page status.
	 *
	 * @var    string
	 */
	public $default_status = 'not-started';

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
	 * Public constructor method to prevent a new instance of the object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */	
	public function __construct() {
		
		register_activation_hook( __FILE__, '$this->activate' );
		register_deactivation_hook( __FILE__, '$this->deactivate' );
		if ( !defined( 'WP_ENV' )) {
		    define( 'WP_ENV' , 'production' );
		}
			
		// Add content filter
		add_filter( 'the_content', array($this , 'showstatus' ) );

		/** Create Top Level Admin Menu item */
		add_action( 'admin_menu', array( $this , 'create_admin_menu' ) );

		// Add metabox
		add_action( 'add_meta_boxes_page', array( $this , 'idies_update_meta_box_page' ) );		
		
		// Save Status Update Post Data
		add_action('save_post', array( $this , 'idies_update_save_meta' ) );
		
		// Set up Variables
		$this->default_reviewer = 'bsouter';
		$this->default_status = 'not-started';
		$this->all_users = get_users( 'orderby=nicename' );		
		//Don't need to do this unless on the Settings or Overview page.
		//$this->all_pages = $this->get_page_updates();

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
	/* Save Meta box Data as Post Meta
	 *
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function idies_update_save_meta($post_id)
	{	
		$newreviewer = isset( $_POST['meta_box_reviewer'] ) ? sanitize_user($_POST['meta_box_reviewer'] ) : false ;
		$newstatus = isset( $_POST['meta_box_status'] ) ? sanitize_title($_POST['meta_box_status'])  : false ;
		$newcomment = isset( $_POST['meta_box_comment'] ) ? sanitize_text_field($_POST['meta_box_comment']) : false ;
		
		if ( !( $newreviewer || $newstatus || $newcomment ) ) {
			return $post_id;
		}
		
		// Check if nonce is set
		check_admin_referer( 'update_save_meta', 'save_meta' );

		if ( ! current_user_can( 'edit_post' ) && ! wp_is_post_autosave( $post_id ) ) 
			return $post_id;

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
	/* Add Meta box to content editor
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
		wp_nonce_field( 'update_save_meta', 'save_meta' );
	?>
	<table class="form-table meta-box">
	<tr>
	<th scope="row"><label for="idies_update_meta_box_status">Status</label></th>
    <td><?php
		echo '<select  class="postbox" name="meta_box_status" id="meta-box-status">';
		foreach ( $this->statuses as $thiskey=>$thisstatus ) {
			echo '<option value="' . $thiskey . '"' . selected( $status , $thiskey ) . '>' . $thisstatus . '</option>';
		}
		echo '</select>';
	?></td>
	</tr>
	<tr>
	<th scope="row"><label for="idies_update_meta_box_reviewer">Reviewer</label></th>
    <td><?php
		echo '<select  class="postbox" name="meta_box_reviewer" id="meta-box-reviewer">';
		foreach ( $this->all_users as $thisuser ) {
			echo '<option value="' . $thisuser->user_nicename . '"' . selected( $reviewer , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
		}
		echo '</select>';
	?></td>
	</tr>
	<tr>
	<th scope="row"><label for="idies_update_meta_box_comment">Comments</label></th>
    <td><?php
		echo '<textarea class="postbox" rows="10" cols="60" name="meta_box_comment" id="meta-box-comment">' . $comment . '</textarea>';
	?></td>
	</tr>
	</table>
    <?php
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
		add_menu_page( 'Status Updates' , 'Status Updates', 'edit_pages', 'idies-status-menu' , array( $this , 'show_settings_page' ) ); 
		
		// Add Settings page to the submenu
		add_submenu_page( 'idies-status-menu' , 'Status Update Settings' , 'Settings' , 'edit_pages' , 'idies-status-settings' , array( $this , 'show_settings_page' ) );
		
		// Add Overview page to the submenu
		add_submenu_page( 'idies-status-menu' , 'Page Status Overview' , 'Overview' , 'edit_pages' , 'idies-status-overview' , array( $this , 'show_overview_page' ) );
		
	}

	/**
	/* Show the Plugin Dashboard Settings Page
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function show_settings_page() {
		
		if ( !current_user_can( 'edit_pages' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// PAGES
		$this->all_pages = $this->get_page_updates();
		$update_args = array( 
			'meta_key' => 'idies_status_update',
			'meta_compare' => 'NOT EXISTS', 
			'meta_value' => '' ,
		);
		$updated_pages = $this->get_page_updates( $update_args );
		$completed_args = array( 
			'meta_key' => 'idies_status_update',
			'meta_value' => 'completed' ,
		);
		$completed_pages = $this->get_page_updates( $completed_args );
		
		// Get Action
		$the_action = ( isset( $_POST['export'] ) ? 'export' :
			( isset( $_POST['import'] ) ? 'import' :
				( isset( $_POST['nuclear'] ) ? 
					'nuclear' :
					( isset( $_POST['action'] ) ? 
						'update' :
						false ) ) ) );
						
		if ( ( $the_action ) && ( ! empty( $_POST ) && check_admin_referer( 'update_idies_settings' , 'update_nonce' ) ) ) {
		
			switch ($the_action) {
				case 'import' :
					$this->import();
				break;
				case 'export' :
					$this->export();
				break;
				case 'nuclear' :
					if ( !current_user_can( 'edit_theme_options' ) ) {
						echo 'You do not have sufficient permissions to use the nuclear option.';
					} else {
						$this->nuclear(  );
					}
				break;
				case 'update' :
					$this->default_reviewer = isset( $_POST[ 'default_reviewer' ] ) ? $_POST[ 'default_reviewer' ] : get_option( 'idies_default_reviewer' , $this->default_reviewer );
					update_option( 'idies_default_reviewer' , $this->default_reviewer );
					$this->default_status = isset( $_POST[ 'default_status' ] ) ? $_POST[ 'default_status' ] : get_option( 'idies_default_status' , $this->default_status );
					update_option( 'idies_default_status' , $this->default_status );
				break;
			}
			
		} else {
		
			$this->default_reviewer = get_option( 'idies_default_reviewer' , $this->default_reviewer );
			$this->default_status = get_option( 'idies_default_status' , $this->default_status );
		
		}

		// Show the form
		echo '<div class="wrap">';
		echo '<h1>Status Updates Settings</h1>';
		
		echo '<form method="post" action="/wp-admin/admin.php?page=idies-status-settings">';
		echo '<input type="hidden" name="options" value="settings">';
		echo '<input type="hidden" name="action" value="update">';
		wp_nonce_field( 'update_idies_settings' , 'update_nonce' );
		
		echo '<table class="form-table">';
		echo '<tbody>';

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
		
		echo '<tr>';
		echo '<th scope="row">All Pages<br><em>Includes published, private, and draft</em></th>';
		echo '<td>' . count( $this->all_pages ) . '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">Status Complete</th>';
		echo '<td>' . count( $completed_pages ) . '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">Status Not Set</th>';
		echo '<td>' . count( $updated_pages ) . '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">Export Page Updates as CSV...</th>';
		echo '<td><button class="button button-secondary" id="export" name="export" value="export">Export</button></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">Import Page Updates CSV...</th>';
		echo '<td><button class="button button-secondary" id="import" name="import" value="export">Import</button></td>';
		echo '</tr>';
				
		echo '<tr>';
		echo '<th scope="row">Nuclear Option: <br>Set All to Default Status & Reviewer</th>';
		echo '<td><button class="button button-secondary" id="nuclear" name="nuclear" value="nuclear">Go Nuclear</button></td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="Update Settings">';
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
	function get_page_updates( $args = array() ) {
		
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

		$defaults = array(
			'sort_order' => $order,
			'sort_column' => $orderby,
			'posts_per_page' => $number,
			'offset' => $offset,
			'post_type' => $this->post_type,
			'post_status' => $this->post_status,
		); 

		$args = wp_parse_args( $args, $defaults );
		
		$my_pages = get_pages( $args ); 
		return $my_pages;
	}

	/**
	// Show the Status Updates Overview Page in the Backend
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function show_overview_page() {
	
		if ( !current_user_can( 'edit_pages' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		$status_options = array_merge( 
			array( 'all-status'=>'Any Update Status') , 
			$this->statuses , 
			array( 'no-status'=>'No Status' )
		);
		
		// Query vars
		$filter_status = ( isset( $_POST['filter_status'] ) ) ?	sanitize_text( $_POST['filter_status'] ) : 'all-status' ;
		$filter_reviewer = ( isset( $_POST['filter_reviewer'] ) ) ?	sanitize_text( $_POST['filter_reviewer'] ) : 'all-reviewers' ;
		
		$paged = isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : 1 ;
		$offset = isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : 1 ;

		$order = ( isset( $_GET['order'] ) && in_array( $_GET['order'] , array( 'asc' , 'desc' ) ) ) ?
				$_GET['order'] : 'asc';
				
		$orderby_columns = array( 
			'title'=>'Title' ,
			'reviewer'=>'Reviewer' , 
			'update-status'=>'Update Status' , 
			'page-status'=>'Page Status' , 
			'post-modified'=>'Last Revised' );
		$orderby = ( isset( $_GET['orderby'] ) && array_key_exists( $_GET['orderby'] , $orderby_columns ) ) ?
			$_GET['orderby'] : 'title' ;
		
		// GET PAGES
		$this->all_pages = $this->get_page_updates();

		echo '<div class="wrap">';
		echo '<h1>Page Status Overview</h1>';
		echo '<hr>';
		echo '<form method="get" action="/wp-admin/admin.php?page=idies-status-overview" >';
		wp_nonce_field( 'update_idies_overview' , 'update_overview' );		
?>
<div class="tablenav top">
	<div class="alignleft actions bulkactions">
		<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
		<select name="action" id="bulk-action-selector-top">
			<option value="-1">Bulk Actions</option>
			<option value="edit" class="hide-if-no-js">Update Status</option>
			</select>
		<input type="submit" id="doaction" class="button action" value="Apply">
	</div>
	<div class="alignleft actions">
		<label for="filter-by-status" class="screen-reader-text">Filter by Status</label>
		<select name="filter-by-status" id="filter-by-status">
<?php
		foreach ( $status_options as $thiskey => $thisvalue ) 
			echo '<option value="' . $thiskey . '" ' . selected( 'this-key' , $filter_status ) . '>' . $thisvalue. '</option>';
?>
		</select>
		<label for="filter-by-reviewer" class="screen-reader-text">Filter by Reviewer</label>
		<select name="filter-by-reviewer" id="filter-by-reviewer">
		<option value="all-reviewers" <?php selected( $filter_reviewer , 'all-reviewers' ); ?> >All Reviewers</option>
<?php
		foreach ( $this->all_users as $thisuser ) 
			echo '<option value="' . $thisuser->user_nicename . '"' . selected( $filter_reviewer , $thisuser->user_nicename ) . '>' . $thisuser->display_name . '</option>';
?>
		</select>
	</div>
	<h2 class="screen-reader-text">Status of Page Update List Nav</h2>
	<br class="clear">
</div>
<h2 class="screen-reader-text">List of Page Status Updates</h2>
<table class="wp-list-table widefat fixed striped pages">
<thead>
<tr>
	<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>
<?php
	foreach ( $orderby_columns as $this_key=>$this_column ) {
		echo '<th scope="col" id="' . $this_key . '" class="manage-column column-' . $this_key . '><span>' . $this_column . '</span></th>';
	}
?>
</thead>
<tbody>
<tr>
<th scope="row" class="check-column">
	<label class="screen-reader-text" for="cb-select-10215">Select Page</label>
	<div class="locked-indicator">
		<span class="locked-indicator-icon" aria-hidden="true"></span>
		<span class="screen-reader-text">“Data Release 11” is locked</span>
	</div>
</th>
<td class="title column-title has-row-actions column-primary page-title" data-colname="Title">
	<div class="locked-info"><span class="locked-avatar"></span> <span class="locked-text"></span></div>
	<strong><a class="row-title" href="http://test.sdss.org/wp-admin/post.php?post=10215&amp;action=edit" aria-label="“Data Release 11” (Edit)">Data Release 11</a></strong>
	<div class="row-actions">
		<span class="edit"><a href="http://test.sdss.org/wp-admin/post.php?post=10215&amp;action=edit" aria-label="Edit “Data Release 11”">Edit</a> | </span>
		<span class="view"><a href="http://test.sdss.org/dr11/" rel="permalink" aria-label="View “Data Release 11”">View</a></span>
	</div>
	<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
</td>
	<th scope="col" id="reviewer" class="manage-column column-reviewer">Reviewer</th>
	<th scope="col" id="status" class="manage-column column-status">Status</th>
	<th scope="col" id="post-modified" class="manage-column column-post-modified sortable asc"><a href="http://test.sdss.org/wp-admin/edit.php?post_type=page&amp;orderby=date&amp;order=desc"><span>Last Revised</span><span class="sorting-indicator"></span></a></th>
</tr>
</tbody>
</table>
<div class="tablenav bottom">
	<div class="alignleft actions bulkactions">
		<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
		<select name="action" id="bulk-action-selector-top">
			<option value="-1">Bulk Actions</option>
			<option value="edit" class="hide-if-no-js">Update Status</option>
			</select>
		<input type="submit" id="doaction" class="button action" value="Apply">
	</div>
	<div class="alignleft actions">
		<label for="filter-by-status" class="screen-reader-text">Filter by Status</label>
		<select name="m" id="filter-by-status">
			<option selected="selected" value="any-status">Any Status</option>
			<option value="not-started">Not Started</option>
			<option value="in-progress">In Progress</option>
			<option value="needs-review">Needs Review</option>
			<option value="completed">Completed</option>
			<option value="do-not-publish">Do not Publish</option>
		</select>
	</div>
	<h2 class="screen-reader-text">Status Update list navigation</h2>
	<div class="tablenav-pages">
		<span class="displaying-num"># items</span>
		<span class="pagination-links">
			<span class="tablenav-pages-navspan" aria-hidden="true">«</span>
			<span class="tablenav-pages-navspan" aria-hidden="true">‹</span>
			<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Current Page</label><input class="current-page" id="current-page-selector" type="text" name="page" value="<?php echo $paged; ?>" size="2" aria-describedby="table-paging"><span class="tablenav-paging-text"> of <span class="total-pages">17</span></span></span>
			<a class="next-page" href="http://test.sdss.org/wp-admin/admin.php?page=idies-status-overview&amp;paged=2"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>
			<a class="last-page" href="http://test.sdss.org/wp-admin/admin.php?page=idies-status-overview&amp;paged=17"><span class="screen-reader-text">Last page</span><span aria-hidden="true">»</span></a>
		</span>
	</div>
	<div class="view-switch"></div>
	<br class="clear">
</div>
</form>
<?php
		echo '</div>';
	}		

	/**
	// Import a CSV file with Page Status Updates
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function import() {
	
	}		

	/**
	// Export a CSV file with Page Status Updates
	/* 
	 * @since  1.1
	 * @access public
	 * @return void
	 */	
	function export() {
	
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
	/* do activation stuff
	/* 
	 * @since  1.0
	 * @access public
	 * @return void
	 */	
	function activate() {
		//do activation stuff
	}
	
	/**
	/* do de-activation stuff
	/* 
	 * @since  1.0
	 * @access public
	 * @return void
	 */	
	function deactivate() {
		// clean up on deactivation
	}
	
	/**
	/* do setup stuff
	/* 
	 * @since  1.0
	 * @access public
	 * @return void
	 */	
	function setup() {
		// clean up on deactivation
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

}
