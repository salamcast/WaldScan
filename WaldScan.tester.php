<?php
/**
 * This script is to demo the functionality of WaldScan
 * 
 * change the $test variable to one on your server 
 * 
 * change the $ext variable to a file list that you want to view
 */

/**
 * require the WaldScan class file
 */
require_once ('dirscan.class.php');
// $test=$_SERVER['DOCUMENT_ROOT'];
// no need to esscape your directories, it will fail if you do

$test = "/Volumes/DRAWING_BOARD/MacBook/Music/";
$ext = "'png,jpg,avi,mkv,mp3,m4b'";
// taboo directory tester
// $test='/sbin';
// $test='/etc';
// $test='/private';
foreach ( $_GET as $key => $value ) {
	switch ($key) {
		case 'json' :
			$dir = new WaldScan ( $test );
			if (isset ( $value ) && $value != '/') {
				$dir->dirs = $value;
			}
			$dir->search_match = explode ( ',', $ext );
			$dir->printJSON ();
			break;
		case 'jsondir' :
			if (isset ( $value ) && $value != '/') {
				$base = $test . $value;
			} else {
				$base = $test;
			}
			$dir = new WaldScan ( $base );
			$dir->search_match = explode ( ',', $ext );
			$dir->printJSONdirs ();
			break;
		case 'stream' :
			$dir = new WaldScan ( $test );
			$dir->file = $value;
			$dir->streamFile ();
			break;
	}
}

?>
<h1>WaldScan</h1>
<pre>
--------------------------------------------------------------------------------

WaldScan is a PHP 5 class that will recursively scan the given directory for a 
list of selected file types. This can scan your directories for media files, 
documents and/or images. You are required to pass a valid full path directory or
 the DOCUMENT_ROOT will be used for the root if nothing is passed. A list of 
default banned directories has been set to avoid potentially dangerous results 
for you and I; You don't want someone to get access to your /etc, /var or 
/private directories. These directories can be cleared if you need to use those 
directories for say a PHP CLI or PHP-GTK utility.

This class has many uses for any web page that serves files over http/https, a
CLI program that does batch processing of files or in cron jobs for caching file
 data for faster access.  getID3 is a great project that would work well with 
this class for accessing meta data in many media file formats; caching the id3 
data to one of the database caching modules would greatly improve the 
performance of your web site. This class can be a little slow scanning many 
files stored on a network share (over wifi), just keep this in mind if you want 
to use network shares on a live website with many users and no caching.

--------------------------------------------------------------------------------
 * Wald is the German word for forest/woods, since this is dealing with multiple 
 * directory trees it seem appropriate 
--------------------------------------------------------------------------------
</pre>
<div style="position: absolute; top: 1px; right: 1px;">
	<h3>View class functions output</h3>
	<p style="width: 300px;">
		edit the <strong><?php echo $_SERVER['SCRIPT_NAME']; ?></strong>
		file's <strong>$test</strong> variable from [ <em><?php echo $test; ?></em>
		] to something on your computer
	</p>
<?php
foreach ( array (
		'getdirs',
		'getfiles',
		'realfile',
		'realdir',
		'json',
		'jsondir' 
) as $k ) {
	echo '<a href="' . $_SERVER ['SCRIPT_NAME'] . '?' . $k . '=/">' . $k . ' </a><br />';
}
?>
</div>
<hr />
<?php 
/**
 * inspect $_GET for view requests
 */
foreach ( $_GET as $key => $value ) {
	switch ($key) {
		case 'realdir' :
			$dir = new WaldScan ( $test );
			if (isset ( $value ) && $value != '/') {
				if (is_dir ( $test . $value )) {
					$dir->dirs = $value;
				} elseif (is_file ( $test . $value )) {
					$dir->file = $value;
				}
			}
			$d = $dir->getRealDirsList;
			echo 'List of directories in ' . $test . '<br />This PHP code will give you this Array:<hr />';
			echo '$dir=new WaldScan($test);<br />$d=$dir->getRealDirsList;';
			echo '<pre>';
			print_r ( $d );
			echo '</pre>';
			break;
		case 'realfile' :
			$dir = new WaldScan ( $test );
			// search for search file type match
			$dir->search_match = explode ( ',', $ext );
			if (isset ( $value ) && $value != '/') {
				if (is_dir ( $test . $value )) {
					$dir->dirs = $value;
				} elseif (is_file ( $test . $value )) {
					$dir->file = $value;
				}
			}
			$r = $dir->getRealFilesList;
			echo 'List of directories in ' . $test . '<br />This PHP code will give you this Array:<hr />';
			echo '$dir=new WaldScan($test);<br />$dir->search_match="'.$ext.'";<br />$f=$dir->getRealFilesList;';
			echo '<pre>';
			print_r ( $r );
			echo '</pre>';

			break;
		case 'getfiles' :
			$dir = new WaldScan ( $test );
			$dir->search_match = explode ( ',', $ext );
			if (isset ( $value ) && $value != '/') {
				if (is_dir ( $test . $value )) {
					$dir->dirs = $value;
				} elseif (is_file ( $test . $value )) {
					$dir->file = $value;
				}
			}
			$f = $dir->getFilesList;
			echo 'List of all files in ' . $test . ', this array is useful for building RESTful resourses from directories<br /> This PHP Code will give you this Array: <hr />';
			echo '$dir=new WaldScan($test);<br />$dir->search_match="'.$ext.'";<br />$f=$dir->getFilesList;';
			echo '<pre>';
			print_r ( $f );
			echo '</pre>';
			break;
		case 'getdirs' :
			$dir = new WaldScan ( $test );
			$dir->search_match = explode ( ',', $ext );
			$f = $dir->getDirsList;
			echo 'List of all files in ' . $test . ', this array is useful for building RESTful resourses from directories<br /> This PHP Code will give you this Array: <hr />';
			echo '$dir=new WaldScan($test);<br />$dir->search_match="'.$ext.'";<br />$f=$dir->getDirsList;';
			echo '<pre>';
			print_r ( $f );
			echo '</pre>';
			break;
	}
}

 if (is_object($dir)) echo '<hr /><pre>'.print_r ( $dir, true ).'</pre>';
?>