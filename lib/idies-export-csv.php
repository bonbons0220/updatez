<?php
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename=status-update-export.txt');
$sep = ',';
$quo = '"';
echo $quo . "ID" . $quo . $sep . 
	$quo . "Title" . $quo . $sep .
	$quo . "Edit" . $quo . $sep .
	$quo . "View" . $quo . $sep .
	$quo . "Status" . $quo . $sep .
	$quo . "Reviewer" . $quo . $sep .
	$quo . "Comment" . $quo . $sep .
	$quo . "Last Revised" . $quo . PHP_EOL;
echo ( __FILE__ );
exit();
require_once '../idies_content_tracker.php';
$all_pages =  $idies_content_tracker::get_page_updates();
echo count($all_pages);
//$all_pages = $this->get_page_updates();
	
foreach ( $idies_content_tracker->all_pages as $thispage ){
	//ID, post_title, post.php?post=ID&action=edit, /post_name/, idies_update_status, idies_update_reviewer, idies_update_comment, post_modified
	$output .= 
		$thispage->ID . $sep . 
		$quo . $thispage->post_title . $quo . $sep . 
		$quo . site_url( "wp-admin/post.php?post=" . $thispage->ID . "&action=edit" ) . $quo . $sep . 
		$quo . site_url( "/" . $thispage->post_name . "/" ) . $quo . $sep . 
		$quo . $idies_content_tracker->statuses[get_post_meta( $thispage->ID, "idies_update_status" , true )] . $quo . $sep . 
		$quo . get_post_meta( $thispage->ID, "idies_update_reviewer" , true ) . $quo . $sep . 
		$quo . get_post_meta( $thispage->ID, "idies_update_comment" , true ) . $quo . $sep . 
		$quo . $thispage->post_modified . $quo . PHP_EOL;
}

//write temp file
$myfile = fopen($pname, "w") or die("Unable to create ". $fname ." file.");
fwrite($myfile, $output);
fclose($myfile);
exit();
?>

