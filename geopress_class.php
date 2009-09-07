<?php

class GeoPress {  
  function install() {
  	global $table_prefix, $wpdb;
 
  // Do a dbDelta to make any necessary updates to the database depending on previous GeoPress version
  $table_name = $table_prefix . "geopress";
  $sql = "CREATE TABLE $table_name (
	id int(11) NOT NULL AUTO_INCREMENT,
	name tinytext NOT NULL,
	loc	tinytext,
	warn tinytext,
	mapurl tinytext,
	coord text NOT NULL,
	geom varchar(16) NOT NULL,
	relationshiptag tinytext,
	featuretypetag tinytext,
	elev float,
	floor float,
	radius float,
	visible tinyint(4) DEFAULT 1,
	map_format tinytext DEFAULT '',
	map_zoom tinyint(4) DEFAULT 0,
	map_type tinytext DEFAULT '',
	UNIQUE KEY id (id)
	);";
 
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	dbDelta($sql);
 
	// One time change necessary to convert from id to geopress_id
	$update_to_geopress_id = 0;
	$sql = "DESCRIBE $table_name;";
	$tablefields = $wpdb->get_results( $sql );
	foreach($tablefields as $tablefield) {	
		if(strtolower($tablefield->Field) == "id")
		{
			$update_to_geopress_id = 1;
			break;
		}
	}
	if($update_to_geopress_id)
	{
		$sql = "ALTER TABLE $table_name CHANGE id geopress_id int(11) NOT NULL auto_increment;";
		$result = $wpdb->get_results( $sql );
	}
	// default options
	add_option('_geopress_mapwidth', "400");
	add_option('_geopress_mapheight', "200");
	add_option('_geopress_marker', $plugindir."/flag.png");
	add_option('_geopress_rss_enable', "true");
	add_option('_geopress_rss_format', "simple");
	add_option('_geopress_map_format', "openlayers");
 
	add_option('_geopress_map_type', 'hybrid');
	add_option('_geopress_controls_pan', true);
	add_option('_geopress_controls_map_type', true);
	add_option('_geopress_controls_zoom', "small");
	add_option('_geopress_controls_overview', false);
	add_option('_geopress_controls_scale', true);
	add_option('_geopress_default_add_map', 0);
	add_option('_geopress_default_zoom_level', "11");
 
	$ping_sites = get_option("ping_sites");
	if( !preg_match('/mapufacture/', $ping_sites, $matches) ) { 
		update_option("ping_sites", $ping_sites . "\n" . "http://mapufacture.com/georss/ping/api");
    }
  }
 
  // Returns an array of locations, each key containing the array of posts at the location
  function get_location_posts ($number = -1) {
    global $table_prefix, $wpdb;
    $geopress_table = $table_prefix . "geopress";
 
    $sql = "SELECT * FROM $geopress_table, $wpdb->postmeta";
    $sql .= " INNER JOIN $wpdb->posts ON $wpdb->posts.id = $wpdb->postmeta.post_id";
    $sql .= " WHERE $wpdb->postmeta.meta_key = '_geopress_id'";
    $sql .= " AND $wpdb->postmeta.meta_value = $geopress_table.geopress_id";
	$sql .= " AND $wpdb->posts.post_status = 'publish'";
    $sql .= " AND coord != ''";
    if($number >= 0) {
      $sql .= " LIMIT ".$number;
    }
    $result = $wpdb->get_results( $sql );
 
    //  Build a hash of Location => Posts @ location
    $locations = array();
    foreach ($result as $loc) {
        if($locations[$loc->name] == null) {
            $locations[$loc->name] = array();
        }
        array_push($locations[$loc->name], $loc);
    }
    return $locations;
  }
  function get_locations ($number = -1) {
    global $table_prefix, $wpdb;
    $geopress_table = $table_prefix . "geopress";
 
    $sql = "SELECT * FROM $geopress_table";
    $sql .= " INNER JOIN $wpdb->postmeta ON $wpdb->postmeta.meta_key = '_geopress_id'";
    $sql .= " AND $wpdb->postmeta.meta_value = $geopress_table.geopress_id";
    $sql .= " WHERE coord != '' GROUP BY 'name'";
	$sql .= " AND $wpdb->posts.post_status = 'publish'";
    if($number >= 0) {
      $sql .= " LIMIT ".$number;
    }
    $result = $wpdb->get_results( $sql );
    // echo $sql; // debug
    return $result;
  }
  // dfraga - Getting loop locations 
  function get_loop_locations ($locations = -1) {
      $result = "";
      $i = 0;
      while (have_posts()) { the_post();
          // echo "get_loop_locations: -> ".get_the_ID()." -> ".get_the_title()."<br />";
          $geo = GeoPress::get_geo(get_the_ID());
          if ($geo != "") {
              $result[] = $geo;
              $i++;
              if ($i == $locations) {
                  break;
              }
          }
      }
    //  Build a hash of Location => Posts @ location
    $locations = array();
    foreach ($result as $loc) {
        if($locations[$loc->coord] == null) {
            $locations[$loc->coord] = array();
        }
        array_push($locations[$loc->coord], $loc);
 
    }
    return $locations;	
  }
 
  // function get_bounds($locations) {
  //   //  lat = 0;
  //   //  lon = 0;
  //   //  zoom = 1;
  //   latbounds = [MAXINT, -MAXINT];
  //   lonbounds = [MAXINT, -MAXINT];
  //   if(count($locations) > 0) {
  //     foreach($locations as $loc) {
  //       $coords = split(" ",$loc->coord);
  //       lat += $loc->coords[0];
  //       lon += $loc->coords[0];
  //       if(lat > latbounds[1]) latbounds[1] = lat;
  //       if(lat < latbounds[0]) latbounds[0] = lat;
  //       if(lon > lonbounds[1]) lonbounds[1] = lon;
  //       if(lon < lonbounds[0]) lonbounds[0] = lon;
  // 
  //     }
  //     //    lat = lat / count($locations);
  //     //    lon = lon / count($locations);
  //   }
  //   return [latbounds[0],lonbounds[0],latbounds[1],lonbounds[1]];
  // }
 
function get_geo ($id) {
  global $table_prefix, $wpdb;
    // 
    // $sql = "SELECT * FROM $geopress_table";
    // $sql .= " INNER JOIN $wpdb->postmeta ON $wpdb->postmeta.meta_key = '_geopress_id'";
    // $sql .= " AND $wpdb->postmeta.meta_value = $geopress_table.geopress_id";
    // $sql .= " WHERE AND coord != '' GROUP BY 'name'";
    // if($number >= 0) {
    //   $sql .= " LIMIT ".$number;
    // }
 
  $geopress_table = $table_prefix . "geopress";
  $geo_id = get_post_meta($id,'_geopress_id',true);
  if ($geo_id) {
    $sql = "SELECT * FROM $geopress_table, $wpdb->postmeta";
    $sql .= " INNER JOIN $wpdb->posts ON $wpdb->posts.id = $wpdb->postmeta.post_id";
    $sql .= " WHERE $wpdb->postmeta.meta_key = '_geopress_id'";
    $sql .= " AND $wpdb->postmeta.meta_value = $geopress_table.geopress_id";
    $sql .= " AND $wpdb->postmeta.post_id = $id AND $geopress_table.geopress_id = $geo_id";
    $row = $wpdb->get_results( $sql );
    return $row[0];
  }
}
 
function get_location ($loc_id) {
  if ($loc_id) {
    global $table_prefix, $wpdb;
 
    $table_name = $table_prefix . "geopress";
 
    $sql = "SELECT * FROM ".$table_name." WHERE geopress_id = ".$loc_id;
    $row = $wpdb->get_row( $sql );
    return $row;
  }
}
 
// Store the location and map parameters to the database
function save_geo ($id, $name,$loc,$coord,$geom,$warn,$mapurl,$visible = 1,$map_format = '', $map_zoom = 0, $map_type = '') {
  global $table_prefix, $wpdb;
 
  if($name == "") { $visible = 0; }
 
  $table_name = $table_prefix . "geopress";
 
  if($id && $id != -1) {
  	$sql = "SELECT * FROM $table_name WHERE geopress_id = $id";
  } else {
  	$sql = "SELECT * FROM $table_name WHERE (name = '$name' AND coord = '$coord') OR loc = '$loc'";
  }
  $row = $wpdb->get_row( $sql );
  //TODO SQL INJECTION POSSIBLE?
  if ($row) {
    $geo_id = $row->geopress_id;
    $sql = "UPDATE ".$table_name." SET name = '$name', loc = '$loc', coord = '$coord', geom = '$geom', warn = '$warn', mapurl = '$mapurl', visible = '$visible', map_format = '$map_format', map_zoom = '$map_zoom', map_type = '$map_type' WHERE geopress_id = '$geo_id'";
    $wpdb->query( $sql );
  } else {
    $sql = "INSERT INTO ".$table_name." VALUES (NULL,'$name','$loc','$warn','$mapurl','$coord','$geom',NULL,NULL,NULL,NULL,NULL,'$visible','$map_format','$map_zoom','$map_type')";
    $wpdb->query( $sql );
    $geo_id = mysql_insert_id();
  }
  return $geo_id;
}
 
  function default_loc() {
    global $table_prefix, $wpdb;
 
    $table_name = $table_prefix . "geopress";
    $sql = "SELECT * FROM ".$table_name." LIMIT 1";	
    $result = $wpdb->get_results( $sql );
    foreach ($result as $row) {
      return $row->loc;
    }	
  }
  function select_saved_geo () {
    global $table_prefix, $wpdb;
 
    $table_name = $table_prefix . "geopress";
    $sql = "SELECT * FROM ".$table_name." WHERE visible = 1";
    $result = $wpdb->get_results( $sql );
    foreach ($result as $row) {
      echo "<option value=\"" . $row->loc . "\"";
      echo ">" . $row->name . "</option>\n";
    }
  }
  function map_saved_locations ($locations) {
    global $table_prefix, $wpdb;
 
    $output = geopress_map_select(250, 250, "float:right;");
    $output .= "<script type='text/javascript'>\n";
    
    if($locations == null) {
        $table_name = $table_prefix . "geopress";
        $sql = "SELECT * FROM ".$table_name;
        $locations = $wpdb->get_results( $sql );
    }
 
	$geopress_marker = get_settings('_geopress_marker', true);
    $output .= "geopress_addEvent(window,'load', function() { \n";
    foreach ($locations as $row) {
      if($row->coord != " ") {
        $coords = preg_split('/\s+/',$row->coord);
        $output .= "\tvar myPoint = new mxn.LatLonPoint( $coords[0], $coords[1]);\n";
        $output .= "\tvar marker = new mxn.Marker(myPoint);\n";
        $output .= "\tgeo_map$map_id.addMarkerWithData(marker,{ infoBubble: \"" . htmlentities($row->name) . "\", icon:\"$geopress_marker\", iconSize:[24,24], iconShadow:\"".$plugindir."/blank.gif\", iconShadowSize:[0,0] });\n";
      }
    }
    $output .= "});\n</script>";
 
    echo $output;
  }
 
  function location_edit_form () { 
    global $post_ID;
 
	$geo = GeoPress::get_geo($post_ID);
?>
 
<?php
	GeoPress::geopress_new_location_form($geo);
  }
  
  function geopress_new_location_form($geo) {
	echo '<div id="locationdiv" class="postbox">
          <h3> <a href="http://www.georss.org/">' . __('Location','GeoPress') . '</a></h3>';
    $loc = $geo->loc;
    $geometry = $geo->coord;
    $locname = $geo->name;
    echo '<div class="inside">
          <table width="100%" cellpadding="3" cellspacing="3">
               <thead>
                  <tr>
                  <th scope="col" align=left>'.__('Saved Name', 'GeoPress').'</th>
                  <th scope="col" colspan=3 align=left>'.__('Location Name, Address, or [Latitude, Longitude]', 'GeoPress').'</th>
				  <th scope="col" colspan=3 align=left></th>
			     </tr>
                </thead>
           <tbody>
                 <tr>
          ';
    echo '<td width=15%> <input size="10" type="text" value="' . $locname . '" name="locname" id="locname" /></td> ';
    echo '<td width=50%> <input size="50" type="text" value="' . $loc . '" name="addr" id="addr" onKeyPress="return checkEnter(event);"/></td> ';
    echo "<td width=20%> <a href='#' onclick='geocode();return false;' title='Geocode this address' id='geocode'>Map Location</a></td>";
    echo '</tr>
          </tbody>
          </table>';
    echo '<input size="50" type="hidden" value="' . $geometry . '" name="geometry" id="geometry" style="hidden" />';
    echo '
          <p>
          <table width="30%" cellpadding="3" cellspacing="3">
          <tbody>
                  <tr>
                    <td scope="col" align=left>'.__('Saved Locations', 'GeoPress').'</td>
					<td rowspan="3">';
	echo geopress_map_select();
 
	echo '</td>		
                  </tr>
          <tr>
          	<td><label for="geopress_select"> <select id="geopress_select" onchange="geopress_loadsaved(this);showLocation(\'addr\',\'geometry\');"><option value="">--choose one--</option>';
			GeoPress::select_saved_geo();
		    echo '</td>';
    echo '
          </tr>
		  <tr><td width="20%" height="200px"> <a href="#" onclick="geopress_resetMap();return false;" title="Zoom out and center map" id="geocode">Reset Map</a></td></tr>
          </tbody>
          </table>
		</div> <!-- class="inside" -->';        
 
    // echo '
    //  <input type="text" id="geopress_map_format" name="geopress_map_format" value=""/>
    //  <input type="text" id="geopress_map_zoom" name="geopress_map_zoom" value="0"/>
    //  <input type="text" id="geopress_map_type" name="geopress_map_type" value=""/>
    // ';
	// If there is already a geo location - map it
	if($geo) {
?>
	<script type="text/javascript">
		geopress_addEvent(window,'load', function() {showLocation();});
	</script>
<?php
	}
	?>
	
	<?php
    echo '</div>';
  }
  function geopress_admin_page() { 
	  echo "<h2>Locations</h2>";
  }
 
  function geopress_documentation_page() { 
		require_once(dirname(__FILE__) . '/geopress_documentation.php');
	

  }
  function geopress_locations_page() { 
    if(isset($_POST['Options'])) { 
      for($i = 0; $i < count($_POST['locname']);$i++) {
        // If the user set the locations via the web interface, don't change it here.
        if( !preg_match('/\[(.+),[ ]?(.+)\]/', $_POST['geometry'], $matches) ) { 
          list($lat, $lon) = geocode($_POST['locaddr'][$i]);
        }
        else {
          $lat = $matches[1];
          $lon = $matches[2];          
        }
        list($warn, $mapurl) = yahoo_mapurl($_POST['locaddr'][$i]);
        $geo_id = GeoPress::save_geo($_POST['locid'][$i], $_POST['locname'][$i], $_POST['locaddr'][$i], "$lat $lon", "point", $warn, $mapurl, $_POST['locvisible'][$i]);
      }
      echo '<div class="updated"><p><strong>' . __('Locations updated.', 'GeoPress') . '</strong></p></div>';
    }   
 
	echo '<div class="wrap"><h2>Configure Locations</h2>';
	echo '<form method="post">';
	global $table_prefix, $wpdb;
 
 
	$table_name = $table_prefix . "geopress";
	$sql = "SELECT * FROM ".$table_name;
	$result = $wpdb->get_results( $sql );
    echo '<div style="width: 70%; float: left;">
          <table width="100%" cellpadding="3" cellspacing="3" style="">
               <thead>
                  <tr>
                  <th scope="col" align=left>'.__('Show', 'GeoPress').'</th>
                  <th scope="col" align=left>'.__('Name', 'GeoPress').'</th>
                  <th scope="col" align=left>'.__('Address', 'GeoPress').'</th>
				  <th scope="col" align=left>'.__('Geometry', 'GeoPress').'</th>
			     </tr>
                </thead>
           <tbody>';
 
	$i = -1;
	foreach ($result as $loc) {
		$i++;
		if($loc->visible) { $checked = "checked='checked'";}
		else {$checked = "";}
?>
	    <tr>
			<td width=5%><input type="hidden" name="locid[<?php echo $i?>]" value="<? echo $loc->geopress_id?>"/><input type="checkbox" value="1" name='locvisible[<?php echo $i?>]' <?php echo $checked?>/></td>
	    	<td width=15%> <input size="10" type="text" value="<?php echo $loc->name?>" name='locname[<?php echo $i?>]' /></td>
	    	<td width=50%> <input size="40" type="text" value="<?php echo $loc->loc?>" name='locaddr[<?php echo $i?>]' /></td>
	    	<td> <input type="text" disabled="disabled" value="<?php echo $loc->coord?>" name='loccoord[<?php echo $i?>]' /></td>
 
<?php
	    echo "</tr>\n";
	}	
    echo '</tbody>
          </table>';	
	echo '<div class="submit"><input type="submit" name="Options" value="'. __('Save Locations', 'GeoPress') . '&raquo;" /></div></div>';
     GeoPress::map_saved_locations($result);
    
  }
  function geopress_maps_page() { 
    if(isset($_POST['Options'])) { 
      $default_mapwidth = $_POST['default_mapwidth'];
      $default_mapheight = $_POST['default_mapheight'];
      $default_marker = $_POST['default_marker'];
      $default_zoom_level = $_POST['default_zoom_level'];
      $map_controls_type = $_POST['map_controls_type'];
      $map_view_type = $_POST['map_view_type'];
 
      $map_controls_pan = $_POST['map_controls_pan'];
      $map_controls_map_type = $_POST['map_controls_map_type'];
      $map_controls_zoom = $_POST['map_controls_zoom'];
      $map_controls_overview = $_POST['map_controls_overview'];
      $map_controls_scale = $_POST['map_controls_scale'];
      $map_format = $_POST['map_format'];
 
 
      update_option('_geopress_map_format', $map_format);
      update_option('_geopress_mapwidth', $default_mapwidth);
      update_option('_geopress_mapheight', $default_mapheight);
      update_option('_geopress_marker', $default_marker);
      update_option('_geopress_map_type', $map_view_type);
      update_option('_geopress_controls_pan', $map_controls_pan);
      update_option('_geopress_controls_map_type', $map_controls_map_type);
      update_option('_geopress_controls_zoom', $map_controls_zoom);
      update_option('_geopress_controls_overview', $map_controls_overview);
      update_option('_geopress_controls_scale', $map_controls_scale);
      update_option('_geopress_default_zoom_level', $default_zoom_level);
 
      echo '<div class="updated"><p><strong>' . __('Map layout updated.', 'GeoPress') . '</strong></p></div>';
    }
 
    $map_format = get_settings('_geopress_map_format', true);
    $default_mapwidth = get_settings('_geopress_mapwidth', true);
    $default_mapheight = get_settings('_geopress_mapheight', true);
    $default_marker = get_settings('_geopress_marker', $plugindir."/flag.png");
    $default_zoom_level = get_settings('_geopress_default_zoom_level', true);
    $map_view_type = get_settings('_geopress_map_type', true);
    $map_controls_zoom = get_settings('_geopress_controls_zoom', true);
    $map_controls_pan = get_settings('_geopress_controls_pan ', true) ? 'checked="checked"' : '';
    $map_controls_overview = get_settings('_geopress_controls_overview', true) ? 'checked="checked"' : '';
    $map_controls_scale = get_settings('_geopress_controls_scale', true) ? 'checked="checked"' : '';
    $map_controls_map_type = get_settings('_geopress_controls_map_type', true) ? 'checked="checked"' : '';
 
 
	echo '<div class="wrap"><h2>Configure Map Layout</h2>';
?>
<h3>About</h3>
<div><p>This page configures the default map that will appear with posts and when you use INSERT_MAP. By setting the map size, default zoom level, and various controls that appear, you can customize how the maps on your site look.</p>
<p>Unfortunately, not all mapping providers (Google, Yahoo, Microsoft, or OpenStreetMap) support turning on or off some of the controls to the right. Therefore, some of your settings may not appear correct when displayed on certain mapping providers. For example, Yahoo maps doesn't currently allow for removing the zoom control.</p>
</div>
<h3>Default Map</h3>
<?php
	global $table_prefix, $wpdb;
	echo '<form method="post">';
	echo "<div style='float:left;'>".geopress_map('','',1,false)."</div>\n";
?>
<fieldset class="options">
    <table width="100%" cellspacing="2" cellpadding="5" class="editform">
	<tr valign="top">
	        <th width="33%" scope="row"><?php _e('Map Size', 'GeoPress')?>:</th>
	        <td>
	                <dl>
	                <dt><label for="default_mapwidth"><?php _e('Map Width', 'GeoPress') ?>:</label></dt>
	                <dd><input type="text" name="default_mapwidth" value="<?php echo $default_mapwidth ?>" style="width: 10%"/> px</dd>
	                <dt><label for="default_mapheight"><?php _e('Map Height', 'Geo') ?>:</label></dt>
	                <dd><input type="text" name="default_mapheight" value="<?php echo $default_mapheight ?>" style="width: 10%"/> px</dd>
					<dt><label for="default_zoom_level"><?php _e('Default Zoom', 'GeoPress') ?>:</label></dt>
					<dd><?php
					$select = "<select name='default_zoom_level' id='default_zoom_level' onchange='geopress_change_zoom();'>
						<option value='18'>Zoomed In</option>
						<option value='17'>Single Block</option>
						<option value='16'>Neighborhood</option>
						<option value='15'>15</option>
						<option value='14'>Several blocks</option>
						<option value='13'>13</option>
						<option value='12'>12</option>
						<option value='11'>City</option>
						<option value='10'>10</option>
						<option value='9'>9</option>
						<option value='8'>8</option>
						<option value='7'>Region</option>
						<option value='6'>6</option>
						<option value='5'>5</option>
						<option value='4'>4</option>
						<option value='3'>Continent</option>
						<option value='2'>2</option>
						<option value='1'>Zoomed Out</option>
					</select>";
					echo str_replace("value='$default_zoom_level'>","value='$default_zoom_level' selected='selected'>", $select);
					?>
					
					</dd>					
	                </dl>
	        </td>
	</tr>	
	<tr valign="top">
		<th scope="row"><?php _e('Map Marker', 'GeoPress') ?>:</th>			
		<td>
			<input type="text" name="default_marker" value="<?php echo $default_marker ?>" />&nbsp;<label for="default_marker"><img src="<?php echo $default_marker ?>" alt="Default GeoPress marker"  style="padding:2px; background-color: white; border: 1px solid #888;"/></label>
		</td>
	</tr>	
	<tr valign="top">
		<th scope="row"><?php _e('Map Format', 'GeoPress') ?>:</th>			
		<td>
		<?php
		$select = "<select name='map_format' id='map_format' onchange='geopress_change_map_format()'>
			<option value='google'>Google</option>
			<option value='yahoo'>Yahoo</option>
			<option value='microsoft'>Microsoft</option>
			<option value='openstreetmap'>OpenStreetMap</option>
			<option value='openlayers'>OpenLayers</option>			
		</select>";
		echo str_replace("value='$map_format'>","value='$map_format' selected='selected'>", $select);
		?>
		<em>Changing to Microsoft Maps requires saving your options</em></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php _e('Map Type', 'GeoPress') ?>:</th>			
		<td>
		<?php
		$select = "<select name='map_view_type' id='map_view_type' onchange='geopress_change_view()'>
			<option value='road'>Road</option>
			<option value='satellite'>Satellite</option>
			<option value='hybrid'>Hybrid</option>
		</select>";
		echo str_replace("value='$map_view_type'>","value='$map_view_type' selected='selected'>", $select);
		?>
		</td>
	</tr>		
	<tr valign="top">
		<th scope="row"><?php _e('Controls', 'GeoPress') ?>:</th>			
		<td>
		<?php
		$select = "<select name='map_controls_zoom' id='map_controls_zoom' onchange='geopress_change_controls(this)' >
			<option value='false'>None</option>
			<option value='small'>Small</option>
			<option value='large'>Large</option>
		</select>";
		echo str_replace("value='$map_controls_zoom'>","value='$map_controls_zoom' selected='selected'>", $select);
		?>
		<label for="map_controls_zoom"><?php _e('Zoom control size', 'GeoPress') ?></label>
		</td>
	</tr>	
	<tr>
		<th scope="row"></th> 
		<td>
		<input name="map_controls_pan" type="checkbox" id="map_controls_pan" onchange="geopress_change_controls(this)" value="true" <?php echo $map_controls_pan ?> /> 
		<label for="map_controls_pan"><?php _e('Pan control', 'GeoPress') ?></label> <em>(Yahoo)</em>
		</td>
	</tr>	
	<tr>
		<th scope="row"></th> 
		<td>
		<input name="map_controls_map_type" type="checkbox" id="map_controls_map_type" onchange="geopress_change_controls(this)" value="true" <?php echo $map_controls_map_type ?> /> 
		<label for="map_controls_map_type"><?php _e('Map Type', 'GeoPress') ?></label> <em>(Google)</em>
		</td>
	</tr>
	<tr>
		<th scope="row"></th> 
		<td>
		<input name="map_controls_overview" type="checkbox" id="map_controls_overview" onchange="geopress_change_controls(this)" value="true" <?php echo $map_controls_overview ?> /> 
		<label for="map_controls_overview"><?php _e('Overview', 'GeoPress') ?></label> <em>(Google)</em>
		</td>
	</tr>
	<tr>
		<th scope="row"></th> 
		<td>
		<input name="map_controls_scale" type="checkbox" id="map_controls_scale" onchange="geopress_change_controls(this)" value="true" <?php echo $map_controls_scale ?> /> 
		<label for="map_controls_scale"><?php _e('Scale', 'GeoPress') ?></label> <em>(Google)</em>
		</td>
	</tr>
	</table>
</fieldset>
 
<?php
 
	echo '<div class="submit"><input type="submit" name="Options" value="'. __('Save Map Layout', 'GeoPress') . '&raquo;" /></div>';
 
  }
 
  function geopress_options_page() { 
 
	if(isset($_POST['Options'])) { 
		$default_rss_enable = $_POST['georss_enable'];
		$default_add_map = $_POST['default_add_map'];
		$rss_format = $_POST['georss_format'];
		$google_apikey = $_POST['google_apikey'];
		$yahoo_appid = $_POST['yahoo_appid'];
		update_option('_geopress_rss_enable', $default_rss_enable);
		update_option('_geopress_rss_format', $rss_format);
		update_option('_geopress_default_add_map', $default_add_map);
		update_option('_geopress_google_apikey', $google_apikey);
		update_option('_geopress_yahoo_appid', $yahoo_appid);
		echo '<div class="updated"><p><strong>' . __('Map options updated.', 'GeoPress') . '</strong></p></div>';
	}
 
    $default_rss_enable = get_settings('_geopress_rss_enable', true) ? 'checked="checked"' : '';
    $default_add_map = get_settings('_geopress_default_add_map', true);
    $rss_format = get_settings('_geopress_rss_format', true);
    $google_apikey = get_settings('_geopress_google_apikey', true);
    $yahoo_appid = get_settings('_geopress_yahoo_appid', true);
	?>
                        <div class="wrap">
                        <h2><?php _e('Customize GeoPress', 'GeoPress') ?></h2>
                        <form method="post">
				<p>Welcome to GeoPress. To begin using GeoPress, please obtain and enter a GoogleMaps and Yahoo AppID as shown below. You can customize your default map view using the "Maps" tab above. Or just go and start writing your posts!</p>
				<fieldset class="options">
				<legend><?php _e('Map', 'GeoPress') ?></legend>
                                <table width="100%" cellspacing="2" cellpadding="5" class="editform">			
					<tr valign="top"> 
						<th scope="row">GoogleMaps Key:</th> 
						<td>
							<input name="google_apikey" type="text" id="google_apikey" style="width: 95%" value="<?php echo $google_apikey ?>" size="45" />
							<br />
							<a href="http://www.google.com/apis/maps/signup.html" title="GoogleMaps API Registration">GoogleMaps API Registration</a> - <?php _e('Enter your blog url as the GoogleMaps URL', 'GeoPress') ?>
						</td> 
					</tr> 
					<tr valign="top"> 
						<th scope="row">Yahoo AppID:</th> 
						<td>
							<input name="yahoo_appid" type="text" id="yahoo_appid" style="width: 95%" value="<?php echo $yahoo_appid ?>" size="45" />
							<br />
							<a href="http://api.search.yahoo.com/webservices/register_application" title="Yahoo! Developer Registration">Yahoo! Developer Registration</a>
						</td> 
					</tr>	
 					<tr valign="top">
						<th scope="row"><?php _e('Add Maps', 'GeoPress') ?>:</th>
						<td>
						<?php _e('Automatically add a map after posts?', 'GeoPress') ?> <br/>
						<select name="default_add_map" id="default_add_map">
							<?php
							$select = "<option value='0'>I'll do it myself, thanks</option>
							<option value='1'>Only on single post pages</option>
							<option value='2'>Give me everything .. any post, any page</option>";
							echo str_replace("value='$default_add_map'>","value='$default_add_map' selected='selected'>", $select);
							?>
						</select>
						</td>
					</tr>
					</table>
				</fieldset>
				<fieldset>
				<legend><?php _e('GeoRSS Feeds', 'GeoPress') ?></legend>
				<table width="100%" cellspacing="2" cellpadding="5" class="editform">
					<tr valign="top">
						<th width="33%" scope="row"><?php _e('GeoRSS Feeds', 'GeoPress') ?>:</th>
						<td>
						<label for="georss_enable">
						<input name="georss_enable" type="checkbox" id="georss_enable" value="true" <?php echo $default_rss_enable ?> /> <?php _e('Enable GeoRSS tags in feeds', 'GeoPress') ?></label>
						</td>
					</tr>
 					<tr valign="top">
						<th scope="row"><?php _e('Feed Format', 'GeoPress') ?>:</th>
						<td>
						<select name="georss_format" id="georss_format">
							<?php
							$select = "<option value='simple'>Simple &lt;georss:point&gt;</option>
							<option value='gml'>GML &lt;gml:pos&gt;</option>
							<option value='w3c'>W3C &lt;geo:lat&gt;</option>";
							echo str_replace("value='$rss_format'>","value='$rss_format' selected='selected'>", $select);
							echo $rss_format;
							?>
						</select><br/>
						<?php _e('The format of your syndication feeds (Simple is recommended)', 'GeoPress') ?> 
					</td>
					</tr>
                </table>
				</fieldset>
                                <div class="submit"><input type="submit" name="Options" value="<?php _e('Update Options', 'GeoPress') ?> &raquo;" /></div>
                        </form>
                        </div>
                <?php
  }
 
	// Adds administration menu options
	function admin_menu() { 
	    add_menu_page(__('Customize GeoPress', 'GeoPress'), __('GeoPress', 'GeoPress'), 5, GEOPRESS_LOCATION.basename(__FILE__), array('GeoPress', 'geopress_options_page'));
	//      add_menu_page(__('GeoPress Locations', 'GeoPress'), __('GeoPress', 'GeoPress'), 5, GEOPRESS_LOCATION.basename(__FILE__), array('GeoPress', 'geopress_options_page'));
	    add_submenu_page(GEOPRESS_LOCATION.basename(__FILE__),__('Locations', 'GeoPress'), __('Locations', 'GeoPress'), 5, 'geopress_locations', array('GeoPress', 'geopress_locations_page'));
	    add_submenu_page(GEOPRESS_LOCATION.basename(__FILE__),__('Maps', 'GeoPress'), __('Maps', 'GeoPress'), 5, 'geopress_maps', array('GeoPress', 'geopress_maps_page'));
	    add_submenu_page(GEOPRESS_LOCATION.basename(__FILE__),__('Documentation', 'GeoPress'), __('Documentation', 'GeoPress'), 5, 'geopress_documentation', array('GeoPress', 'geopress_documentation_page'));
	}
 
	function admin_head($unused) { 
	  /* Use this function to output javascript needed for the post page. 
	     the js function updates the text boxes from saved locations */
		// if ( strstr($_SERVER['REQUEST_URI'], 'post.php')) { 
		// 	echo geopress_header();
		// }
	}
 
 
  // This function is called just before a post is updated
  // Replaces INSERT_MAP with a dynamic map
 
  // ^(.+)[[:space:]]+[T|t]ags:[[:space:]]*([.\n]+)$
  function update_post($id) { 
    // delete_post_meta($id, '_geopress_id'); 
    global $wpdb;
 
    $postdata = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID = '$id'");
 
    $addr = $_POST['addr'];
    $geometry = $_POST['geometry'];
    $locname = $_POST['locname'];
    // Allow the location to be set within the post body
    if ((preg_match_all('/GEOPRESS_LOCATION\((.+)\)/', $postdata->post_content, $matches) > 0) ) {
      // $locname = $matches[1];
      $addr = $matches[1][0];
    }
    // tags: geo:long=24.9419260025024 geo:lat=60.1587851399795
    elseif ((preg_match_all('/geo:lat=([-\d\.]+)(.*)?geo:lon[g]?=([-\d\.]+)/', $postdata->post_content, $matches) > 0) ) {
      // $locname = $matches[1];
      $addr = "[".$matches[1][0].",".$matches[3][0]."]";
    }
    else {
    }
 
    // $map_format = $_POST['geopress_map_format'];
    // $map_zoom = $_POST['geopress_map_zoom'];
    // $map_type = $_POST['geopress_map_type'];
 
    if ( $addr ) {
      // if just lat/lon coordinates were given, don't geocode
      if( !preg_match('/\[(.+),[ ]?(.+)\]/', $addr, $matches) ) { 
        
        // If the user set the coordinates via the web interface (using the geocoder), don't change it here.
        if( preg_match('/(.+),[ ]?(.+)/', $geometry, $matches) ) { 
          $lat = $matches[1];
          $lon = $matches[2];          
        }
        else {
          list($lat, $lon) = geocode($addr);
        }
      } else {
        $lat = $matches[1];
        $lon = $matches[2];                  
      }        
      list($warn, $mapurl) = yahoo_mapurl($addr);
      $coords = "$lat $lon";
      $coord_type = "point";
        
      // Create a new loc - therefore -1
      $geo_id = GeoPress::save_geo(-1, $locname, $addr, $coords, $coord_type, $warn, $mapurl, 1, $map_format, $map_zoom, $map_type);
	  $updated = update_post_meta($id, '_geopress_id', $geo_id);
	  if(!$updated) {
      	add_post_meta($id, '_geopress_id', $geo_id);
      }
    }
  }
  
  // Replaces INSERT_MAP with a geopress map
  function embed_map_inpost($content) { 
    $default_add_map = get_settings('_geopress_default_add_map', true);
 
    // If the user explicitly wants to insert a map
    if(preg_match_all('/INSERT_MAP/', $content, $matches) > 0) {
      $content = preg_replace("/INSERT_MAP\((\d+),[ ]?(\d+)\)/", geopress_post_map('\1','\2'), $content);
      $content = preg_replace("/INSERT_OVERLAY_MAP\((\d+),[ ]?(\d+),[ ]?(.+)\)/", geopress_post_map('\1','\2',true,'\3'), $content);
 
      $content = preg_replace("/INSERT_MAP/", geopress_post_map(), $content);
 
      // This can probably be made into a single preg_replace with ? optionals - ajturner //
      } elseif (preg_match_all('/INSERT_GEOPRESS_MAP/', $content, $matches) > 0) {
        $content = preg_replace("/INSERT_GEOPRESS_MAP\((\d+),[ ]?(\d+)\)/", geopress_map('\1','\2'), $content);
        $content = preg_replace("/INSERT_GEOPRESS_MAP/", geopress_map(), $content);
        // This can probably be made into a single preg_replace with ? optionals - ajturner //
      } elseif (($default_add_map == 2) || ( is_single() && ($default_add_map == 1))) {
        // Add a map to the end of the post if "automatically add map" is enabled
        $content .= geopress_post_map();
      }
      $content = preg_replace("/GEOPRESS_LOCATION\((.+)\)/", "", $content);
 
      return $content;
    }
 
  // Replaces INSERT_COORDS or INSERT_ADDRESS with the geopress information
  function embed_data_inpost($content) { 
    $content = preg_replace("/INSERT_COORDS/", the_geo_mf(), $content);
    $content = preg_replace("/INSERT_ADDRESS/", the_adr_mf(), $content);
    $content = preg_replace("/INSERT_LOCATION/", the_loc_mf(), $content);
	  return $content;
  }
 
  ///
  /// Syndication Functions
  ///
  function atom_entry($post_ID) {
    if(get_settings('_geopress_rss_enable', true))
    {
      $coord = the_coord();
      if($coord != "") {
        the_coord_rss();
      }
    }
  }
 
  function rss2_item($post_ID) {
		if(get_settings('_geopress_rss_enable', true))
		{
			the_coord_rss();
		}
  }
  function geopress_namespace() {
		if(get_settings('_geopress_rss_enable', true))
		{
			switch(get_settings('_geopress_rss_format', true)) {
				case "w3c":
					echo 'xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"'."\n";
					break;
				case "gml":
				case "simple":
				default:
					echo 'xmlns:georss="http://www.georss.org/georss" xmlns:gml="http://www.opengis.net/gml"'."\n";
			}
		}
  }
 
  function wp_head() {
		echo geopress_header();
  }
 
  // If the location is queried, JOIN with the postmeta table 
  function join_clause($join) {
	if (((isset($_GET['location']) || $wp->query_vars['location'] != null)  && $_GET['location'] != "") OR ((isset($_GET['loc']) || $wp->query_vars['loc'] != null)  && $_GET['loc'] != "")) {
		global $wpdb, $id, $post, $posts;
		global $table_prefix;
		$geo_table_name = $table_prefix . "geopress";
 
		$join .= " , $wpdb->postmeta, $geo_table_name ";
	}
	return $join;	
  }
 
	  // If the location is queried, add to the WHERE clause
	  function where_clause($where) {
		if ((isset($_GET['location']) || $wp->query_vars['location'] != null) && $_GET['location'] != "") {
			global $wpdb, $id, $post, $posts;
			global $table_prefix;
			$geo_table_name = $table_prefix . "geopress";
			$post_table_name = $table_prefix . "posts";
			$postmeta_table_name = $table_prefix . "postmeta";
 
			$location = $_GET['location'];
 
			$where.= " AND $geo_table_name.geopress_id = $wpdb->postmeta.meta_value AND $wpdb->posts.id = $wpdb->postmeta.post_id AND $wpdb->postmeta.meta_key = '_geopress_id'"; 
			// If the location= is a number, assume they're referring to the location id
			if( preg_match('/[0-9]+/', $location, $matches )) {
				$where .= " AND $wpdb->postmeta.meta_value=".mysql_real_escape_string($location);
			}
			// otherwise, look for the name
			else {
				$where .= " AND $geo_table_name.name='".mysql_real_escape_string($location)."'";	
			}
		}
		return $where;
	  }
 
	  // If the location is requested, and there exists a "location.php" template in 
	  //  the theme, then use it
	  function location_redirect() {
		if ((isset($_GET['location']) || $wp->query_vars['location'] != null) && $_GET['location'] != "") {
			global $posts;
			// $location = $wp->query_vars['loc'];
			$location = $_GET['location'];
			if($template = get_query_template('location')) {
				include($template);
				exit;
			}
		}
		return;
	  }
		// TODO: ajturner - I'm not sure if these work properly
        // function register_query_var($vars) {
        //         $vars[] = 'loc';
        //         return $vars;
        // }
        // function add_rewrite_tag() {
        //         global $wp_rewrite;
        //         $wp_rewrite->add_rewrite_tag('%loc%', '([0-9]{2})', "loc=");
        // }
        // function filter_query_string($query_string) {
        //         return preg_replace_callback("#loc=([0-9]{2})#", array('GeoPress', 'query_string_callback'), $query_string);
        // }
 
 
  // Returns the map format (google, yahoo, microsoft, osm, etc) set in the defaults, or passed by the optional parameter
  function mapstraction_map_format($map_format_type = "") {
	if($map_format_type == "")
	 	return get_settings('_geopress_map_format', true);
	else
		return $map_format_type;
  }
 
  // Returns the map type (road, satellite, hybrid) set in the defaults, or passed by the optional parameter
  function mapstraction_map_type($map_view_type = "") {
		if($map_view_type == "")
			$map_view_type = get_settings('_geopress_map_type', true);
 
		switch($map_view_type) {
			case "hybrid":
				return 'mxn.Mapstraction.HYBRID';
				break;
			case "road":
				return 'mxn.Mapstraction.ROAD';
				break;
			case "satellite":
				return 'mxn.Mapstraction.SATELLITE';
				break;
			default :
				return 'mxn.Mapstraction.HYBRID';
			break;		
		}
  }
 
  // Returns the map controls set in the defaults, or passed by the optional parameter
/*     pan:      true,
 *     zoom:     'large' || 'small',
 *     overview: true,
 *     scale:    true,
 *     map_type: true,
*/
  function mapstraction_map_controls($pan = "", $zoom = "", $overview = "", $scale = "", $map_type = "") {
 
	if($pan == "") $map_controls_pan = get_settings('_geopress_controls_pan', true)  ? 'true' : 'false';
	else $map_controls_pan = $pan;
	if($zoom == "") $map_controls_zoom = get_settings('_geopress_controls_zoom', true);
	else $map_controls_zoom = $zoom;
	if($overview == "") $map_controls_overview = get_settings('_geopress_controls_overview', true)  ? 'true' : 'false';
	else $map_controls_overview = $overview;
	if($scale == "") $map_controls_scale = get_settings('_geopress_controls_scale', true)  ? 'true' : 'false';
	else $map_controls_scale = $scale;
	if($map_type == "") $map_controls_map_type = get_settings('_geopress_controls_map_type', true)  ? 'true' : 'false';
	else $map_controls_map_type = $map_type;
 
	$controls = "{\n";
	$controls .= "\tpan: $map_controls_pan,\n";
	$controls .= "\tzoom: '$map_controls_zoom',\n";
	$controls .= "\toverview: $map_controls_overview,\n";
	$controls .= "\tscale: $map_controls_scale,\n";
	$controls .= "\tmap_type: $map_controls_map_type\n";
	$controls .= "\t}";
	return $controls;
  }
 
  // Returns the map zoom level set in the defaults, or passed by the optional parameter
  function mapstraction_map_zoom($map_zoom = 0) {
	if($map_zoom == 0)
		return get_settings('_geopress_default_zoom_level', true);
	else
		return $map_zoom;
  }
}
?>