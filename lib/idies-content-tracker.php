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

		/** Create Top Level Admin Menu item */
		add_action( 'admin_menu', array( $this , 'create_admin_menu' ) );
		
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
		
		
		echo '<div class="wrap">';
		echo '<h1>Status Updates Settings</h1>';		
		echo '<div><h2>Page Update Status Summary</h2>';
		
		$all_pages = $this->get_page_updates();
		echo '<p>There are ' . count( $all_pages ) . ' Pages</p></div>';
		
		$meta_args = array( 
			'meta_key' => 'idies_status_update',
			'meta_compare' => 'NOT EXISTS', 
			'meta_value' => '' ,
		);
		$updated_pages = $this->get_page_updates( $meta_args );
		echo '<p>There are ' . count( $updated_pages ) . ' Pages with the Status Update Set</p></div>';
		
		echo '<p>Initialize Update Status for all Pages: </p></div>';
		
		echo '<div><h2>Export CSV</h2></div>';
		
		echo '<div><h2>Import CSV</h2></div>';
		
		echo '<div><h2>Notify Editors</h2></div>';
		
		echo '</div>';

		return;
	}

	// Get posts updates
	function get_page_updates( $args = array() ) {
		
		// Get or Set query variables
		$post_type  = 'page' ; 															// pages only, no other post types
		$post_status  = 'publish,draft' ; 												// published and drafts only, no revisions
		
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
		<label for="filter-by-date" class="screen-reader-text">Filter by Status</label>
		<select name="m" id="filter-by-date">
			<option selected="selected" value="any-status">Any Status</option>
			<option value="not-started">Not Started</option>
			<option value="in-progress">In Progress</option>
			<option value="needs-review">Needs Review</option>
			<option value="completed">Completed</option>
			<option value="do-not-publish">Do Not Publish</option>
			<option value="no-status">No Status</option>
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
		<label for="filter-by-date" class="screen-reader-text">Filter by Status</label>
		<select name="m" id="filter-by-date">
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
		
		// Set up Panel Classes
		$panel_class = array(
				'Not Started' => 'panel-danger',
				'In Progress' => 'panel-warning',
				'Needs Review' => 'panel-info',
				'Completed' => 'panel-success',
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
