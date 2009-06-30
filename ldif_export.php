<?php 

/*
 * ldif_export.php
 * Dumps the LDIF file for a given DN
 *
 * Variables that come in as GET vars:
 *  - dn (rawurlencoded)
 *  - server_id
 *  - format (one of 'win', 'unix', 'mac'
 *  - scope (one of 'sub', 'base', or 'one')
 */

require 'common.php';

$dn = rawurldecode( $_GET['dn'] );
$server_id = $_GET['server_id'];
$format = $_GET['format'];
$scope = $_GET['scope'] ? $_GET['scope'] : 'base';

check_server_id( $server_id ) or pla_error( "Bad server_id: " . htmlspecialchars( $server_id ) );
have_auth_info( $server_id ) or pla_error( "Not enough information to login to server. Please check your configuration." );

$objects = pla_ldap_search( $server_id, 'objectClass=*', $dn, array(), $scope, false );
$server_name = $servers[ $server_id ][ 'name' ];
$server_host = $servers[ $server_id ][ 'host' ];

//echo "<pre>";
//print_r( $objects );
//exit;

$rdn = get_rdn( $dn );
$friendly_rdn = get_rdn( $dn, 1 );

switch( $format ) {
	case 'win': 	$br = "\r\n"; break;
	case 'mac': 	$br = "\r"; break;
	case 'unix': 
	default:	$br = "\n"; break;
}
		
if( ! $objects )
	pla_error( "Search on dn (" . htmlspecialchars($dn) . ") came back empty" );

// define the max length of a ldif line to 76
// as it is suggested (implicitely) for (some) binary
// attributes in rfc 2849 (see note 10)

define("MAX_LDIF_LINE_LENGTH",76);

header( "Content-type: application/download" );
header( "Content-Disposition: filename=$friendly_rdn.ldif" ); 
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
header( "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT" ); 
header( "Cache-Control: post-check=0, pre-check=0", false );

echo "version: 1$br$br";
echo "# LDIF Export for: " . utf8_decode( $dn ) . "$br";
echo "# Generated by phpLDAPadmin on " . date("F j, Y g:i a") . "$br";
echo "# Server: " . utf8_decode( $server_name ) . " ($server_host)$br";
echo "# Search Scope: $scope$br";
echo "# Total entries: " . count( $objects ) . "$br"; 
echo $br;

$counter = 0;
foreach( $objects as $dn => $attrs )
{
	$counter++;
	unset( $attrs['dn'] );
	unset( $attrs['count'] );


	// display "# Entry 3: cn=test,dc=example,dc=com..."
	$title_string = "# Entry $counter: " . utf8_decode( $dn ); 
	if( strlen( $title_string ) > MAX_LDIF_LINE_LENGTH-3 )
		$title_string = substr( $title_string, 0, MAX_LDIF_LINE_LENGTH-3 ) . "...";
	echo "$title_string$br";

	// display the DN
	if( is_safe_ascii( $dn ) )
	  multi_lines_display("dn: $dn");
	else
	  multi_lines_display("dn:: " . base64_encode( $dn ));

	// display all the attrs/values
	foreach( $attrs as $attr => $val ) {
		if( is_array( $val ) ) {
			foreach( $val as $v ) {
				if( is_safe_ascii( $v ) ) {
				  multi_lines_display("$attr: $v");
				} else {
				  multi_lines_display("$attr:: " . base64_encode( $v ));
				}
			}
		} else {
			$v = $val;
			if( is_safe_ascii( $v ) ) {
			  multi_lines_display("$attr: $v");
			} else {
			  multi_lines_display("$attr:: " . base64_encode( $v ));
			}
		}
	}
	echo $br;
}

function is_safe_ascii( $str )
{
	for( $i=0; $i<strlen($str); $i++ )
		if( ord( $str{$i} ) < 32 || ord( $str{$i} ) > 127 )
			return false;
	return true;
}


function multi_lines_display($str){
  global $br;
  
  $length_string = strlen($str);
  $max_length = MAX_LDIF_LINE_LENGTH;

  while ($length_string > $max_length){
    echo substr($str,0,$max_length).$br." ";
    $str= substr($str,$max_length,$length_string);
    $length_string = strlen($str);
    
    // need to do minus one to align on the right
    // the first line with the possible following lines 
    // as these will have an extra space
    $max_length = MAX_LDIF_LINE_LENGTH-1;
  }
  echo $str."".$br;
}


?>