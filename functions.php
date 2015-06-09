<?php
/**
 * Common functions used in all WordPress Migration scripts.
 * 
 * @package    wordpress-migrate
 * @author     Crystal Barton <cbarto11@uncc.edu>
 */


// The amount of memory allocated for this script.
// Increase this number if "Allowed memory size" errors occur.
ini_set('memory_limit', '512M');


$db_connection = null;
$errors = array();
$filters = array();
$actions = array();


/**
 * Process an arguments included in the script call and add to the config array.
 */
if( !function_exists('process_args') ):
function process_args( $switches = array() )
{
	global $argv, $config;

	$opt_keys = array_keys( $config );
	
	foreach( $opt_keys as &$k )
	{
		if( !in_array($k, $switches) )
			$k .= ':';
	}
	
	$new_config = getopt( '', $opt_keys );
	
	foreach( $new_config as &$c )
	{
		if( in_array($c, $switches) )
			$c = true;
	}
	
	$config = array_merge( $config, $new_config );
}
endif;


/**
 * Verify that all config values have a value.
 */
if( !function_exists('verify_config_values') ):
function verify_config_values()
{
	global $config;
	
	foreach( $config as $key => $value )
	{
		if( $value === null ) die( "The $key value is null.\n\n" );
		if( $value === '' ) die( "The $key value is empty.\n\n" );
	}
}
endif;


/**
 * Connect to the local database.
 */
if( !function_exists('sql_connect') ):
function sql_connect()
{
	global $db_connection, $dbhost, $dbname, $dbusername, $dbpassword;
	if( $db_connection ) return;
	
	// Create connection
	echo "\nConnecting to the database.\n";
	try
	{
		$db_connection = new PDO( "mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbusername, $dbpassword );
		$db_connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}
	catch( PDOException $e )
	{
		$db_connection = null;
		die( "Unable to connect to the database:\n".$e->getMessage()."\n\n" );
	}
}
endif;

	
/**
 * Close the connection to the local database.
 */
if( !function_exists('sql_close') ):
function sql_close()
{
	global $db_connection;
	$db_connection = null;
}
endif;


if( !function_exists('do_action') ):
function do_action( $name )
{
	global $actions;
	
	if( !array_key_exists($name, $actions) ) return;
	
	$args = func_get_args();
	array_shift( $args );
	
	foreach( $actions[$name] as $callback )
	{
		
	}
}
endif;


if( !function_exists('apply_filters') ):	
function apply_filters( $name, $value )
{
	global $filters;
	
	if( !array_key_exists($name, $filters) ) return;
	
	$args = func_get_args();
	array_shift( $args );
	array_shift( $args );
	
	foreach( $filters[$name] as $callback )
	{
		
	}
}
endif;
	
	
if( !function_exists('add_error') ):
function add_error()
{
	global $errors;
	
	$errors[] = func_get_args();
}
endif;
	
	
if( !function_exists('print_errors') ):
function print_errors()
{
	global $errors;
	
	if( count($errors) == 0 )
	{
		echo "\nNo errors were logged.\n\n";
		return;
	}
	
	echo "\n".count($errors)." errors were logged.\n\n";
	
	foreach( $errors as $error )
	{
		echo implode( ' : ', $error )."\n";
	}
	
	echo "\n\n";
}
endif;


/**
 * Removes a directory.
 * @param   string  $folder  The directory to remove.
 * @param   int     $depth   The depth in the directory hierarchy the process is.
 *                           Should not be included in the initial function call.
 * @return  bool    True if the directory was deleted, otherwise False.
 */
if( !function_exists('remove_directory') ):
function remove_directory( $folder, $depth = 0 )
{
	if( !is_dir($folder) ) return;

	foreach( glob($folder . '/*') as $file )
	{
		if( strrpos($file, '/.') === strlen($file)-2 )  continue;
		if( strrpos($file, '/..') === strlen($file)-3 ) continue;
	
		if( !is_dir($file) ) @unlink( $file );
	}

	foreach( glob($folder . '/.*') as $file )
	{
		if( strrpos($file, '/.') === strlen($file)-2 )  continue;
		if( strrpos($file, '/..') === strlen($file)-3 ) continue;
	
		if( !is_dir($file) ) @unlink( $file );
	}

	foreach( glob($folder . '/*') as $file )
	{
		if( is_dir($file) ) remove_directory( $file, $depth+1 );
		else @unlink( $file );
	}

	@rmdir( $folder );

	if( is_dir($folder) )
	{
		exec( "rm -rf '$folder'" );
		if( is_dir($folder) )
		{
			return false;
		}
	}

	return true;
}
endif;


/**
 * Delete files and folders based on a wildcard (*) match.
 * @param   string  $filename  The wildcard file or folder name.
 */
if( !function_exists('delete_with_wildcard') ):
function delete_with_wildcard( $filename )
{
	foreach( glob($filename) as $file )
	{
		if( is_dir($file) )
			remove_directory( $file, true );
		else
			@unlink( $file );
	}
}
endif;


/**
 * Determins if a string is serialized.
 * * Copied directly from the WordPress sorce code. *
 * @param   string  $data    The string to analyze.
 * @param   bool    $strict  Use a strict comparison to determine if a serialized object/array.
 * @return  bool    True if the string is serialized data, otherwise False.
 */
if( !function_exists('is_serialized') ):
function is_serialized( $data, $strict = true )
{
	// if it isn't a string, it isn't serialized.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( 'N;' == $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	if ( $strict ) {
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	} else {
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		// Either ; or } must exist.
		if ( false === $semicolon && false === $brace )
			return false;
		// But neither must be in the first X characters.
		if ( false !== $semicolon && $semicolon < 3 )
			return false;
		if ( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( $strict ) {
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			} elseif ( false === strpos( $data, '"' ) ) {
				return false;
			}
			// or else fall through
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}
endif;





