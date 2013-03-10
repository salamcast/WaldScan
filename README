--------------------------------------------------------------------------------
WaldScan
--------------------------------------------------------------------------------

@author Karl Holz
@link https://github.com/salamcast
@licence LICENCE.WaldScan.txt

--------------------------------------------------------------------------------
 * Wald is the German word for forest/woods, since this is dealing with multiple 
 * directory trees it seem appropriate, it's also the plural form of my surname
 * Holz
--------------------------------------------------------------------------------
================================================================================
WaldScan is a PHP 5 class that will recursively scan the given directory for a 
list of selected file types. This can scan your directories for media files, 
documents and/or images. You are required to pass a valid full path directory or 
the DOCUMENT_ROOT will be used for the root if nothing is passed. A list of 
default banned directories has been set to avoid potentially dangerous results 
for you and I; You don't want someone to get access to your /etc, /var or 
/private directories do you? 

These directories can be cleared if you need to use those directories for an 
PHP CLI or PHP-GTK utility.

This class has many uses for any web page that serves files over http/https, a
CLI program that does batch processing of files or in cron jobs for caching file
 data for faster access.  getID3 is a great project that is required to work with 
this class for accessing meta data in many media file formats; caching the id3 
data, etc to an SQLite3 database to greatly improve the performance of your 
application.  This class can be a little slow scanning many files stored on a 
network share (over wifi), just keep this in mind if you want to use network shares
on a live website with many users and no cron Job to do the caching.

look at the WaldScan.tester.php to get a better idea of how this class functions.
when you open it up, edit the $test variable to a directory on your server or 
share mounted on your server and edit the $ext variable to a filetype list that 
you want to view