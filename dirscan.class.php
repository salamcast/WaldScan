<?php
ini_set ( 'memory_limit', '512M' );
require_once (dirname ( __FILE__ ) . '/includes/getid3/getid3.php');
require_once (dirname ( __FILE__ ) . '/includes/getid3/extension.cache.sqlite3.php');
/**
 * DirScan Interface
 * 
 * @package WaldScan
 * @author Karl Holz
 * @version 0.1
 */
interface DirScan {
	function clearTaboo();
	function printJSON();
	function printJSONdirs();
	function streamFile();
	function clear_cache();
}

/**
 *
 * @package WaldScan
 * @author Karl Holz
 * @version 0.1
 * 
 */
class WaldScan implements DirScan {
	/**
	 *
	 * @var string $media_root
	 * @access private
	 */
	private $media_root;
	/**
	 * These directories should not be media root
	 * 
	 * @var string $taboo
	 * @access private
	 */
	private $taboo = array (
			'/',
			'/home',
			'/mnt',
			'/Volumes',
			'/Users',
			'/opt',
			'/usr',
			'/var' 
	);
	/**
	 * These directories should not be used at all or even scanned for files to
	 * share on the web.
	 * DOCUMENT_ROOT will be default if no media root is set, even if it's very
	 * taboo,
	 * it's already shared to www
	 *
	 * media_root can't be in these directories:
	 * 
	 * @var string $very_taboo
	 * @access private
	 */
	private $very_taboo = array (
			'\/private',
			'\/Library',
			'\/System',
			'\/etc',
			'\/root',
			'\/tmp',
			'\/var',
			'\/usr',
			'\/sys',
			'\/proc',
			'\/dev',
			'\/boot',
			'\/opt',
			'\/selinux',
			'\/lib',
			'\/s?bin' 
	);
	/**
	 * Search by filetype, this will fail, since *.* scanning is not permited
	 * 
	 * @var string $search
	 * @access private
	 */
	private $search = '%2A';
	/**
	 * Full path Directories list
	 * 
	 * @var array $dir
	 * @access private
	 */
	private $dirs = array ();
	/**
	 * Full Path Files list, seperated by directory
	 * 
	 * @var array $files
	 * @access private
	 */
	private $files = array ();
	/**
	 * File to send to the browser
	 * 
	 * @var string $file
	 * @access private
	 */
	private $file = null;
	/**
	 * Array of relative path files, organized by directory within the
	 * media_root each item will contain basic file info:
	 * [file],[type],[extension],[created],[updated],[filesize],[access],[writeable],[owner]
	 * each item above is the 'info key' in the example bellow, access the array
	 * like this:
	 * $files=$dir->getFilesList;
	 * echo $files[$dir][index]['info key'];
	 *
	 * @var array $relFiles
	 * @access private
	 */
	protected $relFiles = array ();
	/**
	 *
	 * @var array $relDirs
	 * @access private
	 */
	protected $relDirs = array ();
	
	/**
	 *
	 * @var $id3 object getid3 class
	 */
	private $id3 = FALSE;
	
	/**
	 * scan file meta data for retriveing file info
	 * 
	 * @var $meta bool
	 */
	private $meta = FALSE;
	
	/**
	 * prefix of cache db to keep things seperate
	 * 
	 * @var $prefix bool
	 */
	private $prefix = FALSE;
	
	/**
	 * WaldScan, match need files by type
	 *
	 * Wald is the german word for forest/woods,
	 * this class will look through directory trees and find matching file types
	 * 
	 * @param string $root
	 *        	media root directory
	 */
	public function errorMsg($msg = 'Something went wrong') {
		$j = array ();
		$j ['code'] = '500';
		$j ['http'] = "HTTP/1.0 500 Internal Server Error";
		$j ['msg'] = $msg;
		// print feed to browser
		header ( $j ['http'] );
		header ( 'Content-Type: text/json' );
		echo json_encode ( $j );
		exit ();
	}
	function __construct($root = null, $prefix = __CLASS__, $meta = TRUE) {
		date_default_timezone_set ( 'EST' );
		
		if (is_dir ( $root )) {
			$this->root = $root;
		} elseif (! is_null ( $root )) {
			$this->errorMsg ( "You will need to suply a valid root with instantiating the class. - " . $root );
		}
		$this->meta = $meta;
		if ($meta === TRUE) {
			$this->id3 = new getID3 ();
		}
		$this->prefix = $prefix;
		$f = '/.ht.' . $this->prefix.'-'.md5 ( $this->media_root ) . '.sqlite';
		if (is_writable ( $this->media_root )) {
			$this->cache= $this->media_root . $f;
		} else {
			$this->cache= dirname ( __FILE__ ) . $f;
		}

		$this->start_cache ();
		return TRUE;
	}
	function __destruct() {
		$this->close_cache ();
		return TRUE;
	}
	function get_cache_files($dir) {
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
			// get_files_data
		$sql = $this->get_sql ( 'get_files_data' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':root', $this->media_root, SQLITE3_TEXT );
		$stmt->bindValue ( ':dirname', $dir, SQLITE3_TEXT );
		$res = $stmt->execute ();
		list ( $result ) = $res->fetchArray ();
		if (count ( $result ) > 0) {
			$this->relFiles [$this->getWebPath ( $dir )] = unserialize ( base64_decode ( $result ) );
			foreach ($this->relFiles [$this->getWebPath ( $dir )] as $k => $v)
				$this->files[$dir][$k]=$this->media_root.$v['file'];
			return TRUE;
		}
		return FALSE;
	}
	function cache_files($dir) {
		// Save result
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$sql = $this->get_sql ( 'cache_file' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':root', $this->media_root, SQLITE3_TEXT );
		$stmt->bindValue ( ':dirname', $dir, SQLITE3_TEXT );
		$stmt->bindValue ( ':val', base64_encode ( serialize ( $this->relFiles [$this->getWebPath ( $dir )] ) ), SQLITE3_TEXT );
		return $stmt->execute ();
	}
	
	/**
	 * Set or append to selected private class variables/arrays
	 * 
	 * @param type $name        	
	 * @param type $value        	
	 * @return boolean
	 */
	function __set($name, $value) {
		switch ($name) {
			case 'taboo' :
				if (is_dir ( $value )) {
					$this->very_taboo [] = str_replace ( '/', '\/', $value );
				}
				break;
			case 'search_match' :
				if (is_array ( $value )) {
					$this->search = implode ( ',', $value );
				} elseif (is_string ( $value )) {
					$this->search = trim ( $value );
				} else {
					$this->errorMsg ( 'Search match value is invalid, use an array or a csv list of file types' );
				}
				break;
			case 'root' :
				if (is_dir ( $value )) {
					$this->media_root = preg_replace ( '/\/$/', '', $value );
				} else {
					$this->errorMsg ( "Media root is not a valid directory" );
				}
				if (in_array ( $value, $this->taboo )) {
					$this->errorMsg ( "Media root is in the taboo directory list" );
				}
				foreach ( $this->very_taboo as $t ) {
					if (preg_match ( '/^' . $t . '/', $value )) {
						$this->errorMsg ( 'Media root is located in a very taboo location on your system' );
					}
				}
				break;
			case 'dirs' : // set single dir with out full scan
				if (is_dir ( $value ) && $value != '/') {
					if (preg_match ( "/^" . str_replace ( '/', '\/', $this->media_root ) . "/", $value )) {
						$this->dirs = array (
								$value 
						);
						return TRUE;
					} else {
						$this->errorMsg ( $value . " is not in media root or not a real directory" );
					}
				} elseif (is_dir ( $this->media_root . '/' . $value )) {
					$this->dirs = array (
							str_replace ( array (
									'///',
									'//' 
							), array (
									'//',
									'/' 
							), $this->media_root . '/' . $value ) 
					);
					return TRUE;
				}
				$this->errorMsg ( $value . " is not a real directory in this media root" );
				break;
			case 'file' :
				if (is_file ( $value )) {
					if (preg_match ( "/^" . str_replace ( '/', '\/', $this->media_root ) . "/", $value ) && in_array ( $this->cut_ext ( $value ), explode ( ',', $this->search ) )) {
						$this->file = $value;
						return TRUE;
					} else {
						$this->errorMsg ( $value . " is not in media root or not a real file" );
					}
				} elseif (is_file ( $this->media_root . '/' . $value )) {
					$this->file = str_replace ( array (
							'///',
							'//' 
					), array (
							'//',
							'/' 
					), $this->media_root . '/' . $value );
					return TRUE;
				}
				$this->errorMsg ( $value . " is not a real file in this media root" );
				break;
			default :
				$this->errorMsg ( $name . ': Unknown value, failed to set' );
		}
		;
	}
	/**
	 * Get selected resources and arrays
	 * 
	 * @param type $name        	
	 * @return type
	 */
	function __get($name) {
		switch ($name) {
			case 'getRealDirsList' :
				if (count ( $this->dirs ) == 0) {
					$this->getDirs ();
				}
				return $this->dirs;
				break;
			case 'getRealFilesList' :
				if (count ( $this->dirs ) == 0) {
					$this->getDirs ();
				}
				if (count ( $this->files ) == 0) {
					$this->getFiles ();
				}
				return $this->files;
				break;
			case 'getFilesList' :
				if (count ( $this->dirs ) == 0) {
					$this->getDirs ();
				} elseif (count ( $this->dirs ) == 1) {
					$this->get_cache_files ( $this->dirs [0] );
				}
				if (count ( $this->relFiles ) == 0 && $this->file === NULL) {
					$this->getFiles ();
				} elseif (count ( $this->relFiles ) > 0) {
					return $this->relFiles;
				} else {
					$this->file_info ( $this->file );
				}
				return $this->relFiles;
				break;
			case 'getDirsList' :
				// $this->get_cache_dir();
				if (count ( $this->dirs ) == 0) {
					$this->getDirs ();
				}
				$w = array ();
				if (count ( glob ( $this->glob_type_brace ( $this->media_root, GLOB_BRACE ) ) ) > 0)
					$w [] = '/';
				foreach ( $this->dirs as $d ) {
					if (count ( glob ( $this->glob_type_brace ( $d ), GLOB_BRACE ) ) > 0)
						$w [] = str_replace ( $this->media_root, '', $d );
				}
				
				return $w;
				break;
			case 'getJSON' :
				if (count ( $this->dirs ) == 0) {
					$this->getDirs ();
				} elseif (count ( $this->dirs ) == 1) {
					$this->get_cache_files ( $this->dirs [0] );
				}
				if (count ( $this->relFiles ) == 0) {
					$this->getFiles ();
				}
				return json_encode ( $this->relFiles );
				break;
			case 'getJSONdirs' :
				if (count ( $this->dirs ) == 0) {
					$this->getDirs ();
				}
				$w = array ();
				foreach ( $this->dirs as $d ) {
					$w [] = str_replace ( $this->media_root, '', $d );
				}
				return json_encode ( $w );
				break;
			case 'getMime' :
				if ($this->file !== NULL) {
					return $this->media_file_type ( $this->file );
				} else {
					return NULL;
				}
				break;
			default :
				$this->errorMsg ( $name . ': Unknown value, failed to get' );
		}
		;
	}
	function getDirInfo($dir) {
		$d = $this->dir_info ( $dir );
		foreach ( $this->relFiles [$this->getWebPath ( $dir )] as $i => $v ) {
			$d ['size'] = $d ['size'] + $v ['filesize'];
			$d ['files'] ++;
		}
		$d ['nice_size'] = $this->nice_size ( $d ['size'] );
		return $d;
	}
	/**
	 * Clears the list of taboo/banned directories
	 * 
	 * @return TRUE
	 */
	public function clearTaboo() {
		$this->very_taboo = array ();
		$this->taboo = array ();
		return TRUE;
	}
	/**
	 * Prints a JSON document of all matching files with relitive paths and
	 * basic file info
	 */
	public function printJSON() {
		if (! headers_sent ()) {
			header ( 'Content-Type: text/json' );
			header ( 'Cache-Control: max-age=28800' );
		}
		echo $this->getJSON;
		exit ();
	}
	/**
	 * Prints a JSON document of all directories and basic info
	 */
	public function printJSONdirs() {
		if (! headers_sent ()) {
			header ( 'Content-Type: text/json' );
			header ( 'Cache-Control: max-age=28800' );
		}
		echo $this->getJSONdirs;
		exit ();
	}
	/**
	 * Send file to the client after everything has checked out
	 */
	public function streamFile() {
		if (! headers_sent ()) {
			header ( "Content-Type: " . trim ( $this->media_file_type ( $this->file ) ) );
			header ( "Content-Length: " . trim ( filesize ( $this->file ) ) );
			$this->sendFile ();
			@readfile ( $this->file );
			exit ();
		} else {
			$this->errorMsg ( 'Failed to send file for saving' );
		}
	}
	
	// end of interface to class
	private function sendFile() {
		switch ($this->cut_ext ( $this->file )) {
			case 'mkv' :
			case 'doc' :
			case 'ppt' :
			case 'xls' :
			case 'docx' :
			case 'pptx' :
			case 'xlsx' :
			case 'zip' :
			case 'epub' :
			case 'ibooks' :
			case 'pdf' :
				header ( 'Content-disposition: attachment; filename=' . trim ( basename ( $this->file ) ) );
				break;
		}
	}
	
	/**
	 * Get all dirs under path, 7 levels deep
	 * 
	 * @param string $path
	 *        	path to scan under media root
	 * @return TRUE
	 */
	private function getDirs() {
		if (! isset ( $this->media_root )) {
			if (array_key_exists ( 'DOCUMENT_ROOT', $_SERVER )) {
				$this->media_root = $_SERVER ['DOCUMENT_ROOT'];
			}
		}
		$d = $this->media_root;
		if (! is_dir ( $d )) {
			$this->errorMsg ( "Can't get directories from a non directory" );
		}
		$dirs = array_merge ( glob ( $d . '/*', GLOB_ONLYDIR ), glob ( $d . '/*/*', GLOB_ONLYDIR ), glob ( $d . '/*/*/*', GLOB_ONLYDIR ), glob ( $d . '/*/*/*/*', GLOB_ONLYDIR ), glob ( $d . '/*/*/*/*/*', GLOB_ONLYDIR ), glob ( $d . '/*/*/*/*/*/*', GLOB_ONLYDIR ), glob ( $d . '/*/*/*/*/*/*/*', GLOB_ONLYDIR ) );
		if (count ( $dirs ) < 1) {
			$dirs = array (
					$d 
			);
		}
		$this->dirs = $dirs;
		return TRUE;
	}
	/**
	 * get all media root files that match search
	 * 
	 * @return TRUE
	 */
	private function getFiles() {
		$this->get_cache_files ( $this->media_root ) || $this->file_list ( $this->media_root );
		foreach ( $this->dirs as $k => $value ) {
			$this->get_cache_files ( $value ) || $this->file_list ( $value );
		}
		return TRUE;
	}
	/**
	 * file_list()
	 * 
	 * @access private
	 * @param string $search        	
	 * @return mixed
	 */
	private function file_list($dir) {
		// Lookup file
		$search = $this->glob_type_brace ( $dir );
		$s = glob ( $search, GLOB_BRACE );
		$web = $this->getWebPath ( $dir );
		if ($web == '') {
			$web = '/';
		}
		foreach ( $s as $i => $f ) {
			$f = str_replace ( array (
					'///',
					'//' 
			), array (
					'/',
					'/' 
			), $f );
			$this->files [$dir] [$i] = $f;
			if ($this->meta == FALSE) {
				$this->relFiles [$web] [$i] = $this->file_info ( $f );
			} else {
				$this->relFiles [$web] [$i] = $this->getMETA ( $f );
			}
			// Dirs
			if (! array_key_exists ( $web, $this->relDirs ) || ! is_array ( $this->relDirs [$web] )) {
				$this->dir_info ( $dir );
			}

		}
		if (array_key_exists ( $web, $this->relFiles ) && (count ( $this->relFiles [$web] ) > 0)) {
			$this->cache_files ( $dir );
			return $this->relFiles [$web];
		}
		return false;
	}
	private function dir_info($dir) {
		$ctime = filectime ( $dir );
		$keywords = str_replace ( '/', ',', $dir ) . ',' . $this->search;
		$build = time ();
		return array (
				'dirname' => $dir,
				'webpath' => $this->getWebPath ( $dir ),
				'size' => 0,
				'nice_size' => $this->nice_size ( 0 ),
				'files' => 0,
				'search' => $this->search,
				'keywords' => trim ( $keywords, ',' ),
				'created' => date ( DATE_RSS, $ctime ),
				'year' => date ( "Y", $ctime ),
				'month' => date ( "m", $ctime ),
				'day' => date ( "d", $ctime ),
				'hour' => date ( "H", $ctime ),
				'min' => date ( "i", $ctime ),
				'sec' => date ( "s", $ctime ),
				'build' => date ( DATE_RSS, $build ),
				'build_year' => date ( "Y", $build ),
				'build_month' => date ( "m", $build ),
				'build_day' => date ( "d", $build ),
				'build_hour' => date ( "H", $build ),
				'build_min' => date ( "i", $build ),
				'build_sec' => date ( "s", $build ) 
		);
	}
	
	/**
	 * generate file info
	 * 
	 * @param string $f        	
	 * @return TRUE
	 */
	private function file_info($f) {
		if (! is_file ( $f )) {
			return FALSE;
		}
		$dir = dirname ( $f );
		$f = str_replace ( array (
				'///',
				'//' 
		), array (
				'/',
				'/' 
		), $f );
		
		if (is_writeable ( $f )) {
			$write = 'TRUE';
		} else {
			$write = 'FALSE';
		}
		$posix = posix_getpwuid ( fileowner ( $f ) );
		$info = pathinfo ( $f );
		if (array_key_exists ( 'extension', $info )) {
			$ext = $info ['extension'];
		} else {
			$ext = "FALSE";
		}
		$size = filesize ( $f );
		$nice_size = $this->nice_size ( $size );
		$ctime = filectime ( $f );
		$utime = filemtime ( $f );
		// Files
		$info = array (
				'file' => $this->getWebPath ( $f ),
				'dir' => $this->getWebPath ( $dir ),
				'filename' => $info ['filename'],
				'dirname' => $info ['dirname'],
				'basename' => $info ['basename'],
				'type' => $this->media_file_type ( $f ),
				'extension' => $ext,
				'created' => date ( DATE_RSS, $ctime ),
				'year' => date ( "Y", $ctime ),
				'month' => date ( "m", $ctime ),
				'day' => date ( "d", $ctime ),
				'hour' => date ( "H", $ctime ),
				'min' => date ( "i", $ctime ),
				'sec' => date ( "s", $ctime ),
				'updated' => date ( DATE_RSS, $utime ),
				'update_year' => date ( "Y", $utime ),
				'update_month' => date ( "m", $utime ),
				'update_day' => date ( "d", $utime ),
				'update_hour' => date ( "H", $utime ),
				'update_min' => date ( "i", $utime ),
				'update_sec' => date ( "s", $utime ),
				'filesize' => $size,
				'nice_size' => $nice_size,
				// 'access' => substr(sprintf('%o', fileperms($f)), -4),
				'writeable' => "$write",
				'owner' => $posix ['gecos'] 
		);
		return $info;
	}
	/**
	 * returns the file size in a nicer easyer to read format
	 * 
	 * @param int $size        	
	 * @return string
	 */
	private function nice_size($size) {
		switch ($size) {
			case round ( (($size / 1024) / 1024) / 1024 ) > 1 :
				$nice_size = round ( ((($size / 1024) / 1024) / 1024), 2 ) . " GB";
				break;
			case round ( ($size / 1024) / 1024 ) > 1 :
				$nice_size = round ( (($size / 1024) / 1024), 2 ) . " MB";
				break;
			case round ( $size / 1024 ) > 1 :
				$nice_size = round ( ($size / 1024), 2 ) . " KB";
				break;
			default :
				$nice_size = $size . " Bytes";
				break;
		}
		return $nice_size;
	}
	/**
	 * glob_type_brace() * Might not work on Solaris and other non GNU systems *
	 *
	 * Configures a filetype list for use with glob searches,
	 * will match uppercase or lowercase extensions only
	 * 
	 * @param string $dir
	 *        	directory to add filetype in upper lower
	 * @return string
	 */
	private function glob_type_brace($dir) {
		$dir = str_replace ( array (
				'///',
				'//' 
		), array (
				'/',
				'/' 
		), $dir );
		if (! is_dir ( $dir )) {
			$this->errorMsg ( "Not Dir! in glob_type_brace" );
		}
		if ($this->search == '%2A' || $this->search == '*') { // block showing all
		                                                      // files, filetypes must
		                                                      // be defined in ini
		                                                      // file
			$this->errorMsg ( "Please set the search match to a valid file type" );
		} else {
			$ext = array ();
			$e = explode ( ',', $this->search );
			foreach ( $e as $new ) {
				$new = trim ( $new );
				if ($new != '*' || $new != '%2A') {
					$ext [] = strtolower ( $new ); // lower case file extention
					$ext [] = strtoupper ( $new ); // upper case file extention
				}
			}
			$b = $dir . '/*.{' . implode ( ',', $ext ) . '}';
			return $b;
		}
	}
	/**
	 * getWebPath of given file
	 * 
	 * @param
	 *        	$file
	 * @return web class path
	 */
	private function getWebPath($file = __FILE__) {
		if (array_key_exists ( 'DOCUMENT_ROOT', $_SERVER ) && ($_SERVER ['DOCUMENT_ROOT'] == $this->media_root)) {
			return str_replace ( $_SERVER ['DOCUMENT_ROOT'], '', $file );
		}
		return str_replace ( $this->media_root, '', $file );
	}
	/**
	 * cut off the file extention and return the end in lower case
	 * 
	 * @param string $filename        	
	 * @return $type
	 */
	private function cut_ext($filename) {
		$fn = explode ( '.', $filename );
		$type = array_pop ( $fn );
		return strtolower ( $type );
	}
	/**
	 * media_file_type()
	 *
	 * @param string $filename
	 *        	- media file name
	 * @return string $type returns MIME type for file for the <link /> tag in
	 *         rss
	 */
	private function media_file_type($filename) {
		switch ($this->cut_ext ( $filename )) {
			case "m4a" :
				$type = "audio/x-m4a";
				break;
			case 'm4b' :
				$type = 'audio/mp4';
				break;
			case "mov" :
				$type = "video/quicktime";
				break;
			case "m4v" :
				$type = "video/x-m4v";
				break;
			case "mp4" :
				$type = 'video/mp4';
				break;
			case "mp3" :
				$type = 'audio/mpeg';
				break;
			case "jpg" :
			case "jpeg" :
			case "jpe" :
				$type = 'image/jpeg';
				break;
			case "gif" :
				$type = 'image/gif';
				break;
			case "png" :
				$type = 'image/png';
				break;
			case "mpg" :
			case "mpeg" :
			case "mpe" :
				$type = 'video/mpeg';
				break;
			case "avi" :
				$type = 'video/x-msvideo';
				break;
			case "xls" :
				$type = 'application/vnd.ms-excel';
				break;
			case "doc" :
				$type = 'application/msword';
				break;
			case "ppt" :
				$type = 'application/vnd.ms-powerpoint';
				break;
			case "xml" :
			case "rss" :
			case "xsl" :
				$type = 'text/xml';
				break;
			case "ogg" :
			case "ogv" :
				$type = 'video/ogg';
				break;
			case "oga" :
				$type = 'audio/ogg';
				break;
			case "webm" :
			case "webmv" :
				$type = 'video/webm';
				break;
			case "webma" :
				$type = 'audio/webm';
				break;
			case "wav" :
				$type = 'audio/wav';
				break;
			case "pdf" :
				$type = "application/pdf";
				break;
			case "epub":
				$type= 'application/epub+zip';
				break;
			case "ibooks":
				$type='application/x-ibooks+zip'; 
				break;
			case 'mkv' :
				$type = "video/x-matroska";
				break;
			case 'zip' :
				$type = "application/zip";
				break;
			case 'json' :
				$type = "text/json";
				break;
			case 'js' :
				$type = "text/javascript";
				break;
			case 'css' :
				$type = "text/css";
				break;
			// iWork
			case "key" :
				$type = "application/x-iwork-keynote-sffkey";
				break;
			case 'pages' :
				$type = 'application/x-iwork-pages-sffpages';
				break;
			case "numbers" :
				$type = "application/x-iwork-numbers-sffnumbers";
				break;
			default :
				$type = 'text/plain';
		}
		return $type;
	}
	
	/**
	 * getID3 Tags, meta info
	 */
	/**
	 * This function will return detailed information about the file's id3
	 * information
	 * 
	 * @param void $file
	 *        	file sesource
	 * @return mixed
	 */
	public function getMETA($file) {
		$ThisFileInfo = $this->analyze ( $file ); // Analyze file and store returned
		                                       // data in $ThisFileInfo
		if (is_array ( $ThisFileInfo )) {
			getid3_lib::CopyTagsToComments ( $ThisFileInfo );
			if (array_key_exists ( 'comments_html', $ThisFileInfo )) {
				if (array_key_exists ( 'title', $ThisFileInfo ['comments_html'] )) {
					$title = $ThisFileInfo ['comments_html'] ['title'] [0];
				}
				if (array_key_exists ( 'tv_show_name', $ThisFileInfo ['comments_html'] )) {
					$show = $ThisFileInfo ['comments_html'] ['tv_show_name'] [0];
				}
				if (array_key_exists ( 'artist', $ThisFileInfo ['comments_html'] )) {
					$artist = $ThisFileInfo ['comments_html'] ['artist'] [0];
				}
				if (array_key_exists ( 'album', $ThisFileInfo ['comments_html'] )) {
					$album = $ThisFileInfo ['comments_html'] ['album'] [0];
				}
				if (array_key_exists ( 'description', $ThisFileInfo ['comments_html'] )) {
					$desc = $ThisFileInfo ['comments_html'] ['description'] [0];
				}
				if (array_key_exists ( 'gps_longitude', $ThisFileInfo ['comments_html'] )) {
					$long = $ThisFileInfo ['comments_html'] ['gps_longitude'] [0];
				}
				if (array_key_exists ( 'gps_latitude', $ThisFileInfo ['comments_html'] )) {
					$lat = $ThisFileInfo ['comments_html'] ['gps_latitude'] [0];
				}
				if (array_key_exists ( 'genre', $ThisFileInfo ['comments_html'] )) {
					$genre = $ThisFileInfo ['comments_html'] ['genre'] [0];
				}
				if (array_key_exists ( 'track', $ThisFileInfo ['comments_html'] )) {
					$track = $ThisFileInfo ['comments_html'] ['track'] [0];
				}
				if (array_key_exists ( 'year', $ThisFileInfo ['comments_html'] )) {
					$id3year = $ThisFileInfo ['comments_html'] ['year'] [0];
				}
				if (array_key_exists ( 'keyword', $ThisFileInfo ['comments_html'] )) {
					$keyword = $ThisFileInfo ['comments_html'] ['keyword'] [0];
				}
			}
			if (array_key_exists ( 'jpg', $ThisFileInfo ) && array_key_exists ( 'exif', $ThisFileInfo ['jpg'] )) {
				if (array_key_exists ( 'IFD0', $ThisFileInfo ['jpg'] ['exif'] ) && array_key_exists ( 'DateTime', $ThisFileInfo ['jpg'] ['exif'] ['IFD0'] )) {
					$d = explode ( ' ', $ThisFileInfo ['jpg'] ['exif'] ['IFD0'] ['DateTime'] );
					list ( $year, $month, $day ) = explode ( ':', $d [0] );
					list ( $hour, $min, $sec ) = explode ( ':', $d [1] );
					$date = mktime ( $hour, $min, $sec, $month, $day, $year );
				}
				if (array_key_exists ( 'GPS', $ThisFileInfo ['jpg'] ['exif'] ) && array_key_exists ( 'computed', $ThisFileInfo ['jpg'] ['exif'] ['GPS'] )) {
					if (array_key_exists ( 'latitude', $ThisFileInfo ['jpg'] ['exif'] ['GPS'] ['computed'] ) && array_key_exists ( 'longitude', $ThisFileInfo ['jpg'] ['exif'] ['GPS'] ['computed'] )) {
						$long = $ThisFileInfo ['jpg'] ['exif'] ['GPS'] ['computed'] ['longitude'];
						$lat = $ThisFileInfo ['jpg'] ['exif'] ['GPS'] ['computed'] ['latitude'];
						$map = "http://maps.google.ca/maps?q=" . $lat . "," . $long;
					}
				}
			}
			if (array_key_exists ( 'quicktime', $ThisFileInfo ) && array_key_exists ( 'comments', $ThisFileInfo ['quicktime'] )) {
				if (array_key_exists ( 'tv_season', $ThisFileInfo ['quicktime'] ['comments'] )) {
					$tv_season = $ThisFileInfo ['quicktime'] ['comments'] ['tv_season'] [0];
				}
				if (array_key_exists ( 'tv_episode', $ThisFileInfo ['quicktime'] ['comments'] )) {
					$tv_episode = $ThisFileInfo ['quicktime'] ['comments'] ['tv_episode'] [0];
				}
			}
			
			if (array_key_exists ( 'playtime_seconds', $ThisFileInfo )) {
				$playtime = $ThisFileInfo ['playtime_seconds'];
			}
			if (array_key_exists ( 'playtime_string', $ThisFileInfo )) {
				$time = $ThisFileInfo ['playtime_string'];
			}
			if (array_key_exists ( 'video', $ThisFileInfo )) {
				if (array_key_exists ( 'frame_rate', $ThisFileInfo ['video'] )) {
					$fps = $ThisFileInfo ['video'] ['frame_rate'];
				}
				if (array_key_exists ( 'resolution_x', $ThisFileInfo ['video'] )) {
					$res_x = $ThisFileInfo ['video'] ['resolution_x'];
				}
				if (array_key_exists ( 'resolution_y', $ThisFileInfo ['video'] )) {
					$res_y = $ThisFileInfo ['video'] ['resolution_y'];
				}
			}
			if (array_key_exists ( 'mime_type', $ThisFileInfo )) {
				$type = $ThisFileInfo ['mime_type'];
			}
			if (array_key_exists ( 'filesize', $ThisFileInfo )) {
				$size = $ThisFileInfo ['filesize'];
			}
			
			if (array_key_exists ( 'fileformat', $ThisFileInfo )) {
				$ext = $ThisFileInfo ['fileformat'];
			}
			// geo location
			if (isset ( $lat ) && isset ( $long )) {
				$map = "http://maps.google.ca/maps?q=" . $lat . "," . $long;
			} else {
				$map = null;
				$long = null;
				$lat = null;
			}
			if (! isset ( $title )) {
				$title = basename ( $file );
			}
			if (! isset ( $type )) {
				$type = $this->media_file_type ( $file );
			}
			if (! isset ( $ext )) {
				$ext = $this->cut_ext ( $file );
			}
			if (! isset ( $artist )) {
				$artist = basename ( $file );
			}
			if (! isset ( $album )) {
				$album = dirname ( $file );
			}
			if (! isset ( $size )) {
				$size = filesize ( $file );
			}
			if (! isset ( $desc )) {
				$desc = basename ( $file, '.' . $this->cut_ext ( $file ) );
			}
			if (! isset ( $date )) {
				$date = filectime ( $file );
			}
			if (! isset ( $show )) {
				$show = '';
			}
			if (! isset ( $fps )) {
				$fps = null;
			}
			if (! isset ( $res_x )) {
				$res_x = null;
			}
			if (! isset ( $res_y )) {
				$res_y = null;
			}
			if (! isset ( $genre )) {
				$genre = '';
			}
			if (! isset ( $playtime )) {
				$playtime = '';
			}
			if (! isset ( $time )) {
				$time = '';
			}
			if (! isset ( $track )) {
				$track = 0;
			}
			if (! isset ( $id3year )) {
				$id3year = "";
			}
			if (! isset ( $tv_episode )) {
				$tv_episode = '';
			}
			if (! isset ( $tv_season )) {
				$tv_season = '';
			}
			if (! isset ( $keyword )) {
				$keyword = "";
			}
			

			$id3 = array (
					'title' => $title, // need to source from ID3 tag
					'artist' => $artist,
					'album' => $album,
					'tv_show' => $show,
					'tv_episode' => $tv_episode,
					'tv_season' => $tv_season,
					'google_map' => $map,
					'geo_long' => $long,
					'geo_lat' => $lat,
					'time' => $time,
					'playtime' => $playtime,
					'desc' => $desc, // need to source from ID3 tag
					'genre' => $genre,
					'track' => $track,
					'type' => $type,
					'ext' => $ext,
					'date' => $date,
					'size' => $size,
					'id3_year' => $id3year,
					'keywords' => $keyword 
			);
			$info = $this->file_info ( $file );
			foreach ( $info as $key => $value ) {
				switch ($key) {
					case 'date' :
						break;
					default :
						$id3 [$key] = $value;
						break;
				}
			}
			// $id3= array_merge($info, $id3);
			return $id3;
		} else {
			$info = $this->file_info ( $file );
			return $info;
		}
	}
	
	/**
	 * This has be borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 *
	 * SQL statments
	 *
	 * @param type $name
	 *        	sql lable
	 * @return string null
	 */
	private function get_sql($name) {
		switch ($name) {
			case 'version_check' :
				return "SELECT val FROM getid3_cache WHERE filename = :filename AND filesize = '-1' AND filetime = '-1' AND analyzetime = '-1'";
				break;
			// delete cached data records
			case 'delete_cache' :
				return "DELETE FROM getid3_cache";
				break;
			
			case 'delete_files' :
				return "DELETE FROM files_cache";
				break;
			
			case 'delete_files_ref' :
				return "DELETE FROM files_cache WHERE dirname = :dirname";
				break;
			// create version
			case 'set_version' :
				return "INSERT INTO getid3_cache (filename, dirname, filesize, filetime, analyzetime, val) VALUES (:filename, :dirname, -1, -1, -1, :val)";
				break;
			// select cached data
			case 'get_id3_data' :
				return "SELECT val FROM getid3_cache WHERE filename = :filename AND filesize = :filesize AND filetime = :filetime";
				break;
			case 'get_files_data' :
				return "SELECT val FROM files_cache WHERE dirname = :dirname AND root = :root";
				break;
			// create cached record
			case 'cache_file_id3' :
				return "INSERT INTO getid3_cache (filename, dirname, filesize, filetime, analyzetime, val) VALUES (:filename, :dirname, :filesize, :filetime, :atime, :val)";
				break;
			case 'cache_file' :
				return "INSERT INTO files_cache (dirname, root, val) VALUES (:dirname, :root, :val)";
				break;
			// create sql tables
			case 'make_table' :
				return "CREATE TABLE IF NOT EXISTS getid3_cache (filename VARCHAR(255) NOT NULL DEFAULT '', dirname VARCHAR(255) NOT NULL DEFAULT '', filesize INT(11) NOT NULL DEFAULT '0', filetime INT(11) NOT NULL DEFAULT '0', analyzetime INT(11) NOT NULL DEFAULT '0', val text not null, PRIMARY KEY (filename))";
				break;
			case 'make_files_cache' :
				return "CREATE TABLE IF NOT EXISTS files_cache ( dirname VARCHAR(255) NOT NULL DEFAULT '', root VARCHAR(255) NOT NULL DEFAULT '', val text not null, PRIMARY KEY (dirname))";
				break;
			// read rows with dirname
			case 'get_cached_id3_dir' :
				return "SELECT val FROM getid3_cache WHERE dirname = :dirname";
				break;
		}
		return null;
	}
	
	private $cache;
	
	/**
	 * This has be borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 */
	private function start_cache() {
		$file = $this->cache; //'/.ht.' . $this->prefix.'-'.md5 ( $this->media_root ) . '.sqlite';
		
		$this->db = new SQLite3 ( $file );
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$this->create_table (); // Create cache table if not exists
		$version = '';
		$sql = $this->get_sql ( 'version_check' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':filename', getID3::VERSION, SQLITE3_TEXT );
		$result = $stmt->execute ();
		list ( $version ) = $result->fetchArray ();
		if ($version != getID3::VERSION) { // Check version number and clear cache
		                                   // if changed
			$this->clear_cache ();
		}
	}
	/**
	 * This has be borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 */
	private function close_cache() {
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$db->close ();
	}
	
	/**
	 * hold the sqlite db
	 * 
	 * @var SQLite Resource
	 */
	private $db;
	
	/**
	 * This has be borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 *
	 * clear the cache
	 * 
	 * @access private
	 * @return type
	 */
	public function clear_cache() {
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$sql = $this->get_sql ( 'delete_cache' );
		$db->exec ( $sql );
		$sql = $this->get_sql ( 'delete_dirs' );
		$db->exec ( $sql );
		$sql = $this->get_sql ( 'delete_files' );
		$db->exec ( $sql );
		$sql = $this->get_sql ( 'set_version' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':filename', getID3::VERSION, SQLITE3_TEXT );
		$stmt->bindValue ( ':dirname', getID3::VERSION, SQLITE3_TEXT );
		$stmt->bindValue ( ':val', getID3::VERSION, SQLITE3_TEXT );
		return $stmt->execute ();
	}
	
	/**
	 * analyze file and cache them, if cached pull from the db
	 * 
	 * @param type $filename        	
	 * @return boolean
	 */
	/**
	 * This has be borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 */
	public function analyze($filename) {
		if (! file_exists ( $filename )) {
			return false;
		}
		// items to track for caching
		$filetime = filemtime ( $filename );
		$filesize = filesize ( $filename );
		// this will be saved for a quick directory lookup of analized files
		// ... why do 50 seperate sql quries when you can do 1 for the same
		// result
		$dirname = dirname ( $filename );
		// Lookup file
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$sql = $this->get_sql ( 'get_id3_data' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':filename', $filename, SQLITE3_TEXT );
		$stmt->bindValue ( ':filesize', $filesize, SQLITE3_INTEGER );
		$stmt->bindValue ( ':filetime', $filetime, SQLITE3_INTEGER );
		$res = $stmt->execute ();
		list ( $result ) = $res->fetchArray ();
		if (count ( $result ) > 0) {
			return unserialize ( base64_decode ( $result ) );
		}
		// if it hasn't been analyzed before, then do it now
		
		$analysis = $this->id3->analyze ( $filename );
		
		// Save result
		$sql = $this->get_sql ( 'cache_file_id3' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':filename', $filename, SQLITE3_TEXT );
		$stmt->bindValue ( ':dirname', $dirname, SQLITE3_TEXT );
		$stmt->bindValue ( ':filesize', $filesize, SQLITE3_INTEGER );
		$stmt->bindValue ( ':filetime', $filetime, SQLITE3_INTEGER );
		$stmt->bindValue ( ':atime', time (), SQLITE3_INTEGER );
		$stmt->bindValue ( ':val', base64_encode ( serialize ( $analysis ) ), SQLITE3_TEXT );
		$res = $stmt->execute ();
		return $analysis;
	}
	
	/**
	 * create data base table
	 * this is almost the same as MySQL, with the exception of the dirname being
	 * added
	 * 
	 * @return type
	 */
	/**
	 * This has been borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 */
	private function create_table() {
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$sql = $this->get_sql ( 'make_table' );
		$db->exec ( $sql );
		$sql = $this->get_sql ( 'make_files_cache' );
		$db->exec ( $sql );
		$sql = $this->get_sql ( 'make_dirs_cache' );
		return $db->exec ( $sql );
	}
	
	/**
	 * get cached directory
	 *
	 * This function is not in the MySQL extention, it's ment to speed up
	 * requesting multiple files
	 * which is ideal for podcasting, playlists, etc.
	 *
	 * @access public
	 * @param string $dir
	 *        	directory to search the cache database for
	 * @return array return an array of matching id3 data
	 */
	/**
	 * This has been borrowed from the getid3 sqlite3 cacheing module i
	 * contributed to the project
	 */
	public function get_cached_id3_dir($dir) {
		$db = $this->db;
		if (! is_object ( $db ))
			$this->errorMsg ( __METHOD__ . ": Failed to access DB" );
		$rows = array ();
		$sql = $this->get_sql ( 'get_cached_id3_dir' );
		$stmt = $db->prepare ( $sql );
		$stmt->bindValue ( ':dirname', $dir, SQLITE3_TEXT );
		
		$res = $stmt->execute ();
		while ( $row = $res->fetchArray () ) {
			$rows [] = unserialize ( base64_decode ( $row ) );
		}
		return $rows;
	}
}
?>
