<?php
echo "<pre>";
die("Disabled.");

define('DB_NAME', 'sdsswp_dr13_test');
define('DB_USER', 'sdsswp');
define('DB_PASSWORD', 'BTNjnFO2JXQzgj4DsrnBewR85cb');
define('DB_HOST', 'dsa008');

$mysqli = new mysqli( DB_HOST , DB_USER, DB_PASSWORD, DB_NAME );

//Initial Status has no editor or reviewer set; status of all pages is 'Started'.
$initial_status = 'a:1:{i:0;a:4:{s:6:"editor";s:0:"";s:8:"reviewer";s:0:"";s:6:"status";s:7:"Started";s:8:"comments";s:0:"";}}';

//get the post meta id's to delete
$select_existing_tracking_postmeta = "SELECT pm.meta_id " . 
		"FROM sdsswp_dr13_test.wp_postmeta pm " . 
		"inner join sdsswp_dr13_test.wp_posts p " . 
		"on p.ID = pm.post_id " . 
		"where p.post_type = 'page' " . 
		"and p.post_status = 'publish' " . 
		"and pm.meta_key='tracking'";
$result_select_existing = $mysqli->query( $select_existing_tracking_postmeta );
//echo $select_existing_tracking_postmeta . "\n";

//Show me what you got.
$meta_ids = array();
while ( $row = $result_select_existing->fetch_row(  ) ) {
	$meta_ids[] = $row[0];
}
$result_select_existing->close();

if (!empty($meta_ids)) {

	//Set up Statement and Delete them
	$delete_existing_tracking_postmeta = "DELETE FROM wp_postmeta WHERE meta_id IN " ;
	$delete_existing_tracking_postmeta .= "(" .  implode($meta_ids , ', ') . ")" ;
	//echo $delete_existing_tracking_postmeta . "\n";
	$result_delete_existing = $mysqli->query( $delete_existing_tracking_postmeta );
	if ( $result_delete_existing === FALSE ) echo $result_delete_existing->error . "\n";

} else {
	echo "Nothing to delete" . "\n";
}

//Get the page id's to initialize
$select_all_pages = "SELECT ID " . 
		"FROM wp_posts  " . 
		"WHERE post_type='page'  " . 
		"AND post_status='publish' ";
$result_select_pages = $mysqli->query( $select_all_pages );
//echo $select_all_pages . "\n";

//Set up statement to initialize tracking data

while ( $row = $result_select_pages->fetch_row(  ) ) {
	if (!empty($row[0])) $values[] = "($row[0],'tracking','$initial_status')";
}
$result_select_pages->close();

if (!empty($values)) {

	//Set up Statement and Insert them
	$insert_init_tracking_all_pages = "INSERT INTO wp_postmeta ( post_id , meta_key , meta_value ) VALUES ";
	$insert_init_tracking_all_pages .= implode($values , ', ');
	//echo $insert_init_tracking_all_pages . "\n";
	$result_insert_tracking = $mysqli->query( $insert_init_tracking_all_pages );
	if ( $result_insert_tracking === FALSE ) echo $result_insert_tracking->error . "\n";
	
} else {
	echo "No Pages to Initialize" . "\n";
}

echo "</pre>";
?>