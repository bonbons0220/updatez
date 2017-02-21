<?php 
/**
 * idies_content_tracker class
 *
 * Contains the main idies-content-tracker plugin class.
 *
*/

class idies_content_tracker {

	public $statuses;
	public $panel_class;
	public $all_pages;
	public $default_reviewer = 'bsouter';
	public $default_status = 'not-started';
	
	
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
		
		$this->default_reviewer = 'bsouter';
		$this->default_status = 'not-started';
		
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

	//Create the Plugin Top level Dashboard Menu Item
	function create_admin_menu() {
		
		// Create the top level admin menu item
		add_menu_page( 'Status Updates' , 'Status Updates', 'edit_pages', 'idies-status-menu' , array( $this , 'show_settings_page' ) ); 
		
		// Add Settings page to the submenu
		add_submenu_page( 'idies-status-menu' , 'Status Update Settings' , 'Settings' , 'edit_pages' , 'idies-status-settings' , array( $this , 'show_settings_page' ) );
		
		// Add Overview page to the submenu
		add_submenu_page( 'idies-status-menu' , 'Page Status Overview' , 'Overview' , 'edit_pages' , 'idies-status-overview' , array( $this , 'show_overview_page' ) );
		
	}

	// Show the Plugin Dashboard Settings Page
	function show_settings_page() {
		
		if ( !current_user_can( 'edit_pages' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// THE DATA
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
		
		// USERS
		$all_users = get_users( 'orderby=nicename' );
		
		// Deal with the Action
		$the_action = ( isset( $_POST['export'] ) ? 'export' :
			( isset( $_POST['import'] ) ? 'import' :
				( isset( $_POST['nuclear'] ) ? 
					'nuclear' :
					( isset( $_POST['action'] ) ? 
						'update' :
						'showsettings') ) ) );
						
		if ( $the_action ) {
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
					$this->default_reviewer = isset( $_POST[ 'default-reviewer' ] ) ? $_POST[ 'default-reviewer' ] : get_option( 'idies_default_reviewer' , $this->default_reviewer );
					update_option( 'idies_default_reviewer' , $this->default_reviewer );
					$this->default_status = isset( $_POST[ 'default-status' ] ) ? $_POST[ 'default-status' ] : get_option( 'idies_default_status' , $this->default_status );
					update_option( 'idies_default_status' , $this->default_status );
				break;
				case 'showsettings' :
					$this->default_reviewer = get_option( 'idies_default_reviewer' , $this->default_reviewer );
					$this->default_status = get_option( 'idies_default_status' , $this->default_status );
				break;
			}
		}

		// Show the form
		echo '<div class="wrap">';
		echo '<h1>Status Updates Settings</h1>';
		
		echo '<form method="post" action="/wp-admin/admin.php?page=idies-status-settings">';
		echo '<input type="hidden" name="options" value="settings">';
		echo '<input type="hidden" name="action" value="update">';
		wp_nonce_field( 'update_ict_settings' );
		
		echo '<table class="form-table">';
		echo '<tbody>';

		echo '<tr>';
		echo '<th scope="row">Default Reviewer</th>';
		echo '<td>';
		echo '<select name="default-reviewer" id="default-reviewer">';
		foreach ( $all_users as $thisuser ) {
			$selected = ( strcmp( $this->default_reviewer , $thisuser->user_nicename )===0 ) ? " selected " : "" ;
			echo '<option value="' . $thisuser->user_nicename . '"' . $selected . '>' . $thisuser->display_name . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		
		echo '<tr>';
		echo '<th scope="row">Default Status</th>';
		echo '<td>';
		echo '<select name="default-status" id="default-status">';
		foreach ( $this->statuses as $thiskey => $thisvalue ) {
			$selected = ( strcmp( $this->default_status , $thiskey )===0 ) ? " selected " : "" ;
			echo '<option value="' . $thiskey . '"' . $selected . '>' . $thisvalue. '</option>';
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

	// Get posts updates
	function get_page_updates( $args = array() ) {
		
		// Get or Set query variables
		$post_type  = 'page' ; 															// pages only, no other post types
		$post_status  = 'publish,private,draft' ;										// published and drafts only, no revisions
		
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
			'post_type' => $post_type,
			'post_status' => $post_status,
		); 

		$args = wp_parse_args( $args, $defaults );
		
		$my_pages = get_pages( $args ); 
		return $my_pages;
	}

	// Show the Plugin Dashboard Page
	function show_overview_page() {
		if ( !current_user_can( 'edit_pages' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">Page Status Overview</h1>';
		echo '<hr class="wp-header-end">';


		
		?>
<form id="status-updates-filter" method="get">
<input type="hidden" id="_wpnonce" name="_wpnonce" value="65519ce47f">
<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=idies-status-overview">	
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
		<select name="filter-status" id="filter-status">
		<option selected="selected" value="any-status">Any Status</option>
<?php
		foreach ( $this->statuses as $thiskey => $thisvalue ) echo '<option value="' . $thiskey . '">' . $thisvalue. '</option>';
?>
		<option value="do-not-publish">Do not Publish</option>
		</select>
	</div>
	<h2 class="screen-reader-text">Status Update list navigation</h2>
	<div class="tablenav-pages">
		<span class="displaying-num"># items</span>
		<span class="pagination-links">
			<span class="tablenav-pages-navspan" aria-hidden="true">«</span>
			<span class="tablenav-pages-navspan" aria-hidden="true">‹</span>
			<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Current Page</label>
				<input class="current-page" id="current-page-selector" type="text" name="page" value="<?php echo $paged; ?>" size="2" aria-describedby="table-paging">
				<span class="tablenav-paging-text"> of <span class="total-pages">17</span>
			</span>
			<a class="next-page" href="http://test.sdss.org/wp-admin/admin.php?page=idies-status-overview&amp;paged=2"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>
			<a class="last-page" href="http://test.sdss.org/wp-admin/admin.php?page=idies-status-overview&amp;paged=17"><span class="screen-reader-text">Last page</span><span aria-hidden="true">»</span></a>
		</span>
	</div>
	<div class="view-switch"></div>
	<br class="clear">
</div>
<h2 class="screen-reader-text">Pages list</h2>
<table class="wp-list-table widefat fixed striped pages">
<thead>
<tr>
	<td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>
	<th scope="col" id="title" class="manage-column column-title column-primary sortable desc"><a href="http://test.sdss.org/wp-admin/edit.php?post_type=page&amp;orderby=title&amp;order=asc"><span>Title</span><span class="sorting-indicator"></span></a></th>
	<th scope="col" id="reviewer" class="manage-column column-reviewer">Reviewer</th>
	<th scope="col" id="update-status" class="manage-column column-update-status">Update Status</th>
	<th scope="col" id="page-status" class="manage-column column-page-status">Page Status</th>
	<th scope="col" id="post-modified" class="manage-column column-post-modified sortable asc"><a href="http://test.sdss.org/wp-admin/edit.php?post_type=page&amp;orderby=date&amp;order=desc"><span>Last Revised</span><span class="sorting-indicator"></span></a></th>
</thead>
<tbody>
<tr>
<th scope="row" class="check-column">
	<label class="screen-reader-text" for="cb-select-10215">Select Page</label>
	<input id="cb-select-10215" type="checkbox" name="post[]" value="10215">
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

	function import() {
	
	}		

	function export() {
	
	}		

	// Set all pages meta data to default_reviewer and default_status
	function nuclear() {
		
		foreach ( $this->all_pages as $thispage ){
			update_post_meta( $thispage->ID , 'idies_update_status' , $this->default_status ) ;
			update_post_meta( $thispage->ID , 'idies_update_reviewer' , $this->default_reviewer ) ;
			update_post_meta( $thispage->ID , 'idies_update_comment' , '' ) ;
		}
	
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
	
	// Show the status on a page, under the content.
	function showstatus( $content ) {
	
		$append = '';
		
		// Status is only shown on dev devng test and testng.
		if ( 'development' !== WP_ENV) return $content;
		
		// No status - no show
		$status = get_post_meta( get_the_ID() , 'idies_update_status', true );
		if ( empty( $status ) ) return $content;		
		$reviewer = get_post_meta( get_the_ID() , 'idies_update_reviewer', true );
		$comments = get_post_meta( get_the_ID() , 'idies_update_comments', true );
		
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
