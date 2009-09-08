<?php

require_once(dirname(__FILE__) . '/geopress_class.php');
/*
GeoPress
Plugin Name: GeoPress 
Plugin URI:  http://georss.org/geopress/
Description: GeoPress adds geographic tagging of your posts and blog. You can enter an address, points on a map, upload a GPX log, or enter latitude & longitude. You can then embed Maps, location tags, and ground tracks in your site and your blog entries. Makes your feeds GeoRSS compatible and adds KML output. (http://georss.org/geopress)
Version: 2.8
Author: Andrew Turner & Mikel Maron
Author URI: http://mapufacture.com
Author URI: http://highearthorbit.com
Author URI: http://brainoff.com
 
*/
 
/*  Copyright 2006-8  Andrew Turner, Mikel Maron
 
	Copyright 2005  Ravi Dronamraju
	
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 
	Additional Contributors: 
	Barry () - added KML output [3/1/07]
	David Fraga (comerporlapatilla.com) - Added map for locations in the main wordpress loop [4/11/07]
	
	Supported by:
	Allan () - by post zoom/maptype settings
*/
 
define('google_geocoder', 'http://maps.google.com/maps/geo?q=', true);
define('google_regexp', "<coordinates>(.*),(.*),0</coordinates>", true);
define('yahoo_regexp', "<Latitude>(.*)<\/Latitude>.*<Longitude>(.*)<\/Longitude>", true);
define('yahoo_geocoder', 'http://api.local.yahoo.com/MapsService/V1/geocode?appid=geocodewordpress&location=', true);
define('yahoo_annotatedmaps', 'http://api.maps.yahoo.com/Maps/V1/AnnotatedMaps?appid=geocodewordpress&xmlsrc=', true);
define('yahoo_embedpngmapurl', 'http://api.local.yahoo.com/MapsService/V1/mapImage?appid=geocodewordpress&', true);
define('GEOPRESS_USE_ZIP', true);
define('GEOPRESS_FETCH_TIMEOUT', 2);
define('GEOPRESS_USER_AGENT',  'GeoPress2.0', false);
define('GEOPRESS_LOCATION', 'geopress/', false);
define('GEOPRESS_VERSION', '2.4.3', false);
 
if ( !function_exists('Snoopy') ) { 
  require_once(ABSPATH.WPINC.'/class-snoopy.php');
  error_reporting(E_ERROR);
}
 
function geocode($location, $geocoder) {
  if($geocoder == null) {
    $geocoder = "yahoo";
  }
  
  if( !preg_match('/\[(.+),[ ]?(.+)\]/', $location, $matches) ) { 
    $client = new Snoopy();
    $client->agent = GEOPRESS_USER_AGENT;
    $client->read_timeout = GEOPRESS_FETCH_TIMEOUT;
    $client->use_gzip = GEOPRESS_USE_GZIP;
    if($geocoder == 'google') {
      $url = google_geocode . urlencode($location);
      $regexp = google_regexp;
    }
    elseif($geocoder == 'yahoo') {
      $url = yahoo_geocoder . urlencode($location);
      $regexp = yahoo_regexp;
    }
 
    @$client->fetch($url);
    $xml = $client->results;
 
    $lat = "";
    $lon = "";
    $latlong = "";
 
    if ($geocoder == 'google' && preg_match("/$regexp/", $xml, $latlong)) { 
      $lat = trim($latlong[1]);
      $lon = trim($latlong[2]);
    } 
    elseif ($geocoder == 'yahoo' && preg_match("/$regexp/", $xml, $latlong)) { 
      $lat = trim($latlong[1]);
      $lon = trim($latlong[2]);
    }
  }
  else {
    $lat = trim($matches[1]);
    $lon = trim($matches[2]);
  }
  return array($lat, $lon);
}
 
function yahoo_geocode($location) {
  if( !preg_match('/\[(.+),[ ]?(.+)\]/', $location, $matches) ) { 
 
    $client = new Snoopy();
    $client->agent = GEOPRESS_USER_AGENT;
    $client->read_timeout = GEOPRESS_FETCH_TIMEOUT;
    $client->use_gzip = GEOPRESS_USE_GZIP;
    $url = yahoo_geocoder . urlencode($location);
 
    @$client->fetch($url);
    $xml = $client->results;
	$dom = domxml_open_file($xml); 
 
 
    $lat = "";
    $lon = "";
 
	// $lat = $dom->get_elements_by_tagname('Latitude')[0].child_nodes()[0]->node_value();
	// $lon = $dom->get_elements_by_tagname('Longitude')[0].child_nodes()[0]->node_value();
 
    // $latlong = "";
    // if (preg_match("/<Latitude>(.*)<\/Latitude>.*<Longitude>(.*)<\/Longitude>/", $xml, $latlong)) { 
    //   $lat = $latlong[1];
    //   $lon = $latlong[2];
    // } 
  }
  else {
    $lat = $matches[1];
    $lon = $matches[2];
  }
  return array($lat, $lon);
}
// Converts a zoom from 1 (world) to 18 (closest)  to Yahoo coords: 1 (close) 12(country)
function yahoo_zoom($zoom) {
	return ceil(12 / $zoom);
}
function yahoo_mapurl($location) { 
 
    $client = new Snoopy();
    $client->agent = GEOPRESS_USER_AGENT;
    $client->read_timeout = GEOPRESS_FETCH_TIMEOUT;
    $client->use_gzip = GEOPRESS_USE_GZIP;
    $mapwidth = get_settings('_geopress_mapwidth', true);
    $mapheight= get_settings('_geopress_mapheight', true);
    $url = yahoo_embedpngmapurl . "image_width=" . $mapwidth . "&image_height=" . $mapheight;
 	$url .= "&zoom=" . (yahoo_zoom( GeoPress::mapstraction_map_zoom())); // TODO: put in an appropriate conversion function
 
	// Get the image for a location, or just lat/lon
	if( !preg_match('/\[(.+),[ ]?(.+)\]/', $location, $matches) ) { 
		$url .= "&location=" . urlencode($location);
	} else {
		$url .= "&latitude=" . $matches[1] . "&longitude=" . $matches[2];
	}
 
    @$client->fetch($url);
    $xml = $client->results;
    
    $mapinfo = "";
    if (preg_match("/<Result xmlns:xsi=\"[^\"]*\"( warning=\"[^\"]*\")?>(.*)<\/Result>/", $xml, $mapinfo)) { 
      $warn = $mapinfo[1];
      $mapurl = $mapinfo[2];
    }
 
    return array($warn, $mapurl);
      
}
 

 
///
/// Wordpress Plugin Hooks
///
add_action('activate_geopress/geopress.php', array('GeoPress', 'install'));
 
// Add form to post editing
add_action('edit_form_advanced', array('GeoPress', 'location_edit_form')); 
add_action('simple_edit_form', array('GeoPress', 'location_edit_form'));
// Add form to page editing
add_action('edit_page_form', array('GeoPress', 'location_edit_form'));
 
 
// Handles querying for a specific location
add_action('template_redirect', array('GeoPress', 'location_redirect'));
add_filter('posts_join', array('GeoPress','join_clause') );
add_filter('posts_where', array('GeoPress','where_clause') );
// add_filter('query_vars', array('GeoPress','register_query_var') );
// add_filter( 'init', array('GeoPress', 'add_rewrite_tag') );
 
add_action('admin_head', array('GeoPress', 'admin_head'));
add_action('save_post', array('GeoPress', 'update_post'));
add_action('edit_post', array('GeoPress', 'update_post'));
add_action('publish_post', array('GeoPress', 'update_post'));
add_filter('the_content', array('GeoPress', 'embed_map_inpost'));
add_action('admin_menu', array('GeoPress', 'admin_menu'));
add_action('option_menu', array('GeoPress', 'geopress_options_page'));
add_action('wp_head', array('GeoPress', 'wp_head'));
add_action('admin_head', array('GeoPress', 'wp_head'));
 
// XML Feed hooks //
add_action('atom_ns', array('GeoPress', 'geopress_namespace'));
add_action('atom_entry', array('GeoPress', 'atom_entry'));
add_action('rss2_ns', array('GeoPress', 'geopress_namespace'));
//add_action('rss2_head', array('GeoPress', 'rss2_head'));
add_action('rss2_item', array('GeoPress', 'rss2_item'));
add_action('rdf_ns', array('GeoPress', 'geopress_namespace'));
//add_action('rdf_head', array('GeoPress', 'rss2_head'));
add_action('rdf_item', array('GeoPress', 'rss2_item'));
add_action('rss_ns', array('GeoPress', 'geopress_namespace'));
//add_action('rss_head', array('GeoPress', 'rss2_head'));
add_action('rss_item', array('GeoPress', 'rss2_item'));
 

function geopress_header() {
    $map_format = get_settings('_geopress_map_format', true);
    $google_apikey = get_settings('_geopress_google_apikey', true);
    $yahoo_appid = get_settings('_geopress_yahoo_appid', true);
    $plugindir = get_bloginfo('wpurl') . "/wp-content/plugins/geopress";
    $providers = array();

    $scripts = "<!-- Location provided by GeoPress v".GEOPRESS_VERSION." (http://georss.org/geopress) -->";
    $scripts .= "<meta name=\"plugin\" content=\"geopress\" />";
 
    if($yahoo_appid != "") {
        array_push($providers,"yahoo");
        $scripts .= "\n".'<script type="text/javascript" src="http://api.maps.yahoo.com/ajaxymap?v=3.4&amp;appid='. $yahoo_appid .'"></script>';
    }
    if($map_format == "microsoft") {
        array_push($providers,"microsoft");
        $scripts .= "\n".'<script src="http://dev.virtualearth.net/mapcontrol/v3/mapcontrol.js"></script>';
    }
    if($google_apikey != "") {
        array_push($providers,"google");
        $scripts .= "\n".'<script type="text/javascript" src="http://maps.google.com/maps?file=api&amp;v=2&amp;key='. $google_apikey .'" ></script>';
    }
    // $scripts .= "\n".'<script type="text/javascript" src="http://openlayers.org/api/OpenLayers.js"></script>';


    $scripts .= "\n".'<script type="text/javascript" src="'.$plugindir.'/mapstraction/mxn.js?('.implode(",",$providers).')"></script>';
    $scripts .= "\n".'<script type="text/javascript" src="'.$plugindir.'/geopress.js"></script>';
    return $scripts;
}
 
///
/// User/Template Functions
///
 
function geopress_locations_list() {
  $locations = GeoPress::get_locations();
  foreach ($locations as $loc) {
    echo '<li><a href="'.get_settings('home').'?location='.$loc->name.'">'.$loc->name.'</a></li>';
  }
  return;
}
// dfraga - Debugging function added
function dump_locations ($locations, $msg = "") {
  $string =  "+ Dumping: ".$msg."<br />\n";
  if ($locations == "") {
    $string .= "- Void locations<br />\n";
  }
  foreach ($locations as $loc) {
    $string .= "- Location name: ".$loc->name."<br />\n";
  }
  $string .= "+ End of locations<br />\n";
  return $string;
}
 
 
 
// dfraga - Loop mapping added
// Creates a dynamic map with the posts in "the_loop". Useful for category/search/single visualization.
// $height, $width are the h/w in pixels of the map
// $locations is the last N locations to put on the map, be default puts *all* locations
// $unique_id is a true/false if a unique_id is required
function geopress_map_loop($height = "", $width = "", $locations = -1, $zoom_level = -1) {
	return geopress_map ($height, $width, $locations, false, true, $zoom_level);
}
 
 
function geopress_page_map($height = "", $width = "", $controls = true) {
	global $post, $geopress_map_index;
	// $children = get_children("post_parent=".$post->post_id."&post_type=page&orderby=menu_order ASC post_date&order=ASC");
	$children = get_children("post_parent=".$post->ID."&orderby=menu_order ASC, post_date&order=ASC");
	$output='';
	if($children == false)
	{
		$output.= geopress_post_map($height, $width, $controls);
	}
	else
	{
		$map_format = get_settings('_geopress_map_format', true);
		if ($height == "" || $width == "" )
		{
			$width = get_settings('_geopress_mapwidth', true);
			$height = get_settings('_geopress_mapheight', true)*2;
		}
 
		$map_id = $post->ID . $geopress_map_index;
 
		$coords = split(" ",$geo->coord);
 
		$map_controls = $controls ? GeoPress::mapstraction_map_controls() : "false";
		$output = '<div id="geo_map'.$map_id.'" class="mapstraction" style="height: '.$height.'px; width: '.$width.'px;"></div>';
		$output .= '<!-- GeoPress Map --><script type="text/javascript">';
		$output .= 'geopress_addEvent(window,"load", function() { geopress_maketravelmap(';
		$output .=$map_id.',';
		$output .='{';
 
		$pointList = array();
		foreach($children as $key=>$value)
		{
			$line= "";
			$geo = GeoPress::get_geo($key);
			if($geo)
			{
				$line .= $key.':{';
				$coords = split(" ",$geo->coord);
				$line .= 'lat:'.$coords[0].',lng:'.$coords[1];
				$line .= ',name:"'.addslashes($geo->name).'"';
//				$line .= ',title:"'.'"guid."'title='".addslashes($value->post_title) . "'>".addslashes($value->post_title)."".'"';
				$line .='}';
				array_push($pointList, $line);
			}
		}
		$output .= implode(',' , $pointList );
		$output .= '},';
		$output .= '"'.GeoPress::mapstraction_map_format($geo->map_format) . '",' . GeoPress::mapstraction_map_type($geo->map_type).', '. $map_controls .')';
		$output .= "}); </script><!-- end GeoPress Map -->";
	}
	return $output;
}
 
// Creates a dynamic map
// $height, $width are the h/w in pixels of the map
// $locations is the last N locations to put on the map, be default puts *all* locations
// $unique_id is a true/false if a unique_id is required
// $loop_locations set this to true if you're running this in a Post loop and want a map of the currently visible posts
// $zoom_level set the map zoom level for the overview. Default is to auto zoom to show all markers.
// $url is an option URL to a KML or GeoRSS file to include in the map
function geopress_map($height = "", $width = "", $locations = -1, $unique_id, $loop_locations = false, $zoom_level = -1, $url = "") {
	$plugindir = get_bloginfo('wpurl') . "/wp-content/plugins/geopress";		
  $map_format = get_settings('_geopress_map_format', true);
  $geopress_marker = get_settings('_geopress_marker', true);
  if ($height == "" || $width == "" )
  {
   	$height = get_settings('_geopress_mapheight', true);
   	$width = get_settings('_geopress_mapwidth', true);
  }
  
  // sometimes we don't want to deal with a unique ID b/c we know there will 
  // 	only be 1 map, like the select map
  if($unique_id)
  	$map_id = geopress_rand_id();
  else
	$map_id = "";
 
  // dfraga - Getting specific locations 
  if ($loop_locations == true) {
      $locs = GeoPress::get_loop_locations($locations);
  } else {
      $locs = GeoPress::get_location_posts($locations);
  }
  $output = '<div id="geo_map'.$map_id.'" class="mapstraction" style="height: '.$height.'px; width: '.$width.'px;"></div>';
  $output .= '<!-- GeoPress Map --> <script type="text/javascript">'."\n";
  $output .= " //<![CDATA[ \n";
  $output .= "var geo_map;\ngeopress_addEvent(window,'load', function() { ";
  $output .= 'geo_map'.$map_id.' = new mxn.Mapstraction("geo_map'.$map_id.'","'. $map_format .'");'."\n";
  $output .= 'geo_map'.$map_id.'.setCenterAndZoom(new mxn.LatLonPoint(0,0), 1);';
//  $output .= 'var geo_bounds = new mxn.BoundingBox();'."\n";
  $output .= 'geo_map'.$map_id.'.addControls('.GeoPress::mapstraction_map_controls().');'."\n";
  if($map_format != "openstreetmap")
  	$output .= 'geo_map'.$map_id.'.setMapType('.GeoPress::mapstraction_map_type().');'."\n";
  $output .= "var markers = new Array(); var i = 0;"."\n";
 
	//  Output one marker per location, but with all posts at that location
	// Todo - optionally do a clustering of a larger marker
  foreach ($locs as $posts) {
    $loc = $posts[0];
    $coords = split(" ",$loc->coord);
    $output .= "i = markers.push(new mxn.Marker(new mxn.LatLonPoint($coords[0], $coords[1])));\n";
    $details = " @ <strong>". htmlentities($loc->name)."</strong><br/>";
    $url = get_bloginfo('wpurl');
    foreach($posts as $post) {
        $details .= "<a href='".$url.'/?p='.$post->post_id."' title='". htmlentities($post->post_title)."'>".htmlentities($post->post_title)."</a><br/>";
    }
	$output .= "\tgeo_map$map_id.addMarkerWithData(markers[i-1],{ infoBubble: \"$details\", date : \"new Date($post->post_date)\", icon:\"$geopress_marker\", iconSize:[24,24], iconShadow:\"".$plugindir."/blank.gif\", iconShadowSize:[0,0] });\n";
 
    // $output .= "geo_map$map_id.addMarker(markers[i-1]);\n";
    //	$output .= 'geo_bounds.extend(markers[i-1].point);'."\n";
  }
 
  $output .= "geo_map$map_id.autoCenterAndZoom();";
//  $output .= 'geo_map'.$map_id.'.setZoom(map.getBoundsZoomLevel(bounds));';
 
  // dfraga - Zoom level setting added
  if ($zoom_level > 0) {
    $output .= "geo_map$map_id.setZoom(".$zoom_level.");\n";
  }
 
	if($url != "") {
		$output .= 'geo_map'.$map_id.'.addOverlay("'.$url.'");';
	}
 
  $output .= "}); \n // ]]> \n </script><!-- end GeoPress Map --> ";
  
  return $output;
}
 
$geopress_map_index = 1;
function geopress_post_map($height = "", $width = "", $controls = true, $overlay = "") {
    global $post, $geopress_map_index;
	$geopress_marker = get_settings('_geopress_marker', true);
 
    $geo = GeoPress::get_geo($post->ID);
    if($geo) {
        if(!is_feed()) {
 
            if ($height == "" || $width == "" ) {
                $height = get_settings('_geopress_mapheight', true);
                $width = get_settings('_geopress_mapwidth', true);
            }
 
            $map_id = $post->ID . $geopress_map_index;
 
            $coords = split(" ",$geo->coord);
 
            $map_controls = $controls ? GeoPress::mapstraction_map_controls() : "false"; 
            $output = '<div id="geo_map'.$map_id.'" class="mapstraction" style="height: '.$height.'px; width: '.$width.'px;"></div>';
            $output .= '<!-- GeoPress Map --><script type="text/javascript">';
            // $output .= " //<![CDATA[ ";
            $output .= 'geopress_addEvent(window,"load", function() { geopress_makemap('.$map_id.',"'. $geo->name .'",'.$coords[0].','.$coords[1].',"'.GeoPress::mapstraction_map_format($geo->map_format).'",'.GeoPress::mapstraction_map_type($geo->map_type).', '. $map_controls .','.GeoPress::mapstraction_map_zoom($geo->map_zoom).', "'.$geopress_marker.'") }); ';
			if($url != "") {
				$output .= 'geo_map'.$map_id.'.addOverlay("'.$url.'");';
			}
			$output .= "</script><!-- end GeoPress Map -->";
        }
        else
        {
            $output = '<img src="'.$geo->mapurl.'" title="GeoPress map of '.$geo->name.'"/>';
        }
        $geopress_map_index++;
    }
    return $output;
 
}
 
function geopress_map_select($height=250, $width=400, $style="float: left;") {   
  $map_format = get_settings('_geopress_map_format', true);
  $map_view_type = get_settings('_geopress_map_type', true);
  $output = '<div id="geo_map" class="mapstraction" style="width: '.$width.'px; height: '.$height.'px;'.$style.'"></div>';
  $output .= '<!-- GeoPress Map --><script type="text/javascript">';
  $output .= " //<![CDATA[ \n";
  $output .= "var geo_map;\ngeopress_addEvent(window,'load', function() { \n";
  $output .= 'geo_map = new mxn.Mapstraction("geo_map","'.$map_format.'"); ';
  $output .= "var myPoint = new mxn.LatLonPoint(20,-20);\n";
  $output .= "geo_map.addControls(".GeoPress::mapstraction_map_controls(true, 'small', false, true, true).");\n";
  $output .= "geo_map.setCenterAndZoom(myPoint,1);\n";
  $output .= "geo_map.setMapType(".GeoPress::mapstraction_map_type($map_view_type).");\n";
  $output .= 'geo_map.addEventListener("click", function(p){ setClickPoint(p); } );';
  $output .= 'geo_map.addEventListener("zoom", function(p){ alert("Zoomed!"); } );';
 
  $output .= "});\n // ]]> \n </script><!-- end GeoPress Map -->";
 
  return $output;
 
}
// Does the post have a location?
function has_location() { 
 
  global $post;
  $geo = GeoPress::get_geo($post->ID);
  if($geo)
    return true;
  else
    return false;
}
// Get the coordinates for a post
function the_coord() { 
 
  global $post;
  $geo = GeoPress::get_geo($post->ID);
  return $geo->coord;
 
}
 
// The Geographic coordinates in microformats
// see http://microformats.org/wiki/geo
function the_geo_mf() { 
  
  $coord = the_coord();
  $coord = split(" ", $coord);
 
  $coord_tag = "\n\t<div class='geo'><span class='latitude'>$coord[0]</span>, <span class='longitude'>$coord[1]</span></div>";
  
  return $coord_tag;
 
}
 
// Gets the name for a location when passed in via the URL query
function geopress_location_name() {
 	if(isset($_GET['loc']) && $_GET['loc'] != "") {
		$loc_id = $_GET['loc'];
		$location = GeoPress::get_location($loc_id);
		return $location->name;
	}
}
 
// Get the address (name) for a post
function the_location_name() {
  global $post;
  $geo = GeoPress::get_geo($post->ID);
  $addr = $geo->name;
  return $addr;
}
 
// Get the address (name) for a post
function the_address() {
  global $post;
  $geo = GeoPress::get_geo($post->ID);
  $addr = $geo->loc;
  return $addr;
}
 
// The Address in microformats
// see http://microformats.org/wiki/adr
function the_adr_mf() { 
  
  $addr = the_address();
  $addr_tag = "\n\t<div class='adr'>$addr</div>";
  
  return $addr_tag;
 
}
// The Location in microformats
// see http://microformats.org/wiki/adr
function the_loc_mf() { 
  
  $loc_name = the_location_name();
  $loc_tag = "\n\t<div class='vcard'><span class='fn'>$loc_name</span></div>";
  
  return $loc_tag;
 
}
 
function ymap_post_url() { 
 
  global $post;
  $coord = the_coord();
  list($lat, $lon) = split(" ", $coord);
  
  return "http://maps.yahoo.com/int/index.php#lat=$lat&lon=$lon&mag=5&trf=0";
 
}
 
function ymap_blog_url($type ='rss2_url') { 
 
  // Note this url won't produce a valid, plottable map if you haven't 
  //   modified your wp-rss file for the type of feed you want (wp-rss.php or wp-rss2.php)
  $url = yahoo_annotatedmaps . bloginfo($type); 
return "$url";
 
}
 
// Get the Coordinates in RSS format
// this doesn't need to be called directly, it is added by the Plugin hooks
function the_coord_rss() {
  $coord = the_coord();
  $featurename = the_address();
  $rss_format = get_settings('_geopress_rss_format', true);
  if($coord != "") {
	switch($rss_format) {
	case "w3c":
		  $coord = split(" ", $coord);
  		$coord_tag = "\t<geo:lat>$coord[0]</geo:lat>\n\t\t<geo:lon>$coord[1]</geo:lon>\n";
		break;
	case "gml":
  		$coord_tag = "\t<georss:where>\n\t\t<gml:Point>\n\t\t\t<gml:pos>$coord</gml:pos>\n\t\t</gml:Point>\n\t</georss:where>";
		break;
	case "simple": // cascade to default
	default:
		$coord_tag = "\t<georss:point>$coord</georss:point>\n";
		if($featurename != ""){
  			$coord_tag .= "\t<georss:featurename>$featurename</georss:featurename>\n";
		}		
 		break;
	}
  	echo $coord_tag;
  }
}
 
function the_addr_rss() { 
  
  $addr = the_address();
  $addr_tag = "\n\t<ymaps:Address>$addr</ymaps:Address>";
  
  echo $addr_tag;
 
}
 
// returns a random number require_once(dirname(__FILE__) . '/geopress_class.php');used to ensure unique HTML element ids
function geopress_rand_id() {
  srand((double)microtime()*1000000);  
  return rand(0,1000); 
}
 
function geopress_kml_link() {
	$plugindir = get_bloginfo('wpurl') . "/wp-content/plugins/geopress";	
	echo "<a href=\"$plugindir/wp-kml-link.php\" title=\"KML Link\">KML</a>";
}
 
?>