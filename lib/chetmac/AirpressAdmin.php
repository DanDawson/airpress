<?php


function airpress_cx_menu() {

	add_menu_page(
		'just a redirect',	// page title
		'Airpress',			// menu title
		'manage_options',		// capabilities
		'airpress_settings',	// menu ID
		'airpress_admin_render'	// function that renders
	);

	add_submenu_page(
		"airpress_settings", // parent slug
		"Airtable Connections", // page title
		"Airtable Connections", // menu title
		"manage_options", // capability
		"airpress_cx", // menu_slug
		"airpress_cx_render" // function
	);

	add_submenu_page(
		"airpress_settings", // parent slug
		"Debug Info", // page title
		"Debug Info", // menu title
		"manage_options", // capability
		"airpress_db", // menu_slug
		"airpress_db_render" // function
	);

	remove_submenu_page('airpress_settings', 'airpress_settings');
	
}
add_action( 'admin_menu', 'airpress_cx_menu' );

function airpress_admin_render(){
	return "<div class='wrap'>what goes here?</div>";
}

function airpress_db_render( $active_tab = '' ) {
	global $wpdb;

	if (isset($_GET["delete-expired-transients"]) && $_GET["delete-expired-transients"] == "true"){

		$all = isset($_GET["all"]) && $_GET["all"] === "true";
		airpress_flush_cache( $all );

	}

	$s = memory_get_usage();
	$results = $wpdb->get_results( 'SELECT * FROM wp_options WHERE option_name LIKE "_transient_aprq_%" ORDER BY option_id ASC', OBJECT );
	$e = memory_get_usage();
	$expirations = $wpdb->get_results( 'SELECT * FROM wp_options WHERE option_name LIKE "_transient_timeout_aprq_%" ORDER BY option_value ASC', OBJECT );

	$exp = array();
	foreach($expirations as $row){
		$hash = str_replace("_transient_timeout_aprq_","",$row->option_name);
		$exp[$hash] = $row->option_value;
	}

	?>
<div class="wrap">
  <?php
		echo "There are ".esc_html(count($results))." cached queries using ".esc_html(round((($e-$s)/1024)/1024,2))." MB memory.<br><br>";
		$now = time();
		foreach($results as $row){
			$hash = str_replace("_transient_aprq_","",$row->option_name);
			$data = unserialize($row->option_value);
			$data_age = round( ($now - $data["created_at"])/60/60, 2 );
			$data_expire = round( ($exp[$hash]-$now)/60/60, 2 );
			echo esc_html($data_age)." hours old. Transient expires in " .esc_html($data_expire)." hours.<br>";
		}
	?>
  <br><br>
  <a href="<?php echo admin_url("admin.php?page=airpress_db&delete-expired-transients=true"); ?>">Delete Expired
    Transients?</a><br>
  <a href="<?php echo admin_url("admin.php?page=airpress_db&delete-expired-transients=true&all=true"); ?>">Delete All
    Transients (completely clear cache)?</a>
</div>
<?php
}

function airpress_cx_render( $active_tab = '' ) {
	global $airpress;

?>
<!-- Create a header in the default WordPress 'wrap' container -->
<div class="wrap">

  <div id="icon-themes" class="icon32"></div>
  <h2><?php _e( 'Airtable Personal Access Token Settings', 'airpress' ); ?></h2>
  <p>You may find that multiple Personal Access Tokens or APP ID configurations are required for your website. Create as many as you need!</p>
  <?php settings_errors(); ?>

  <?php
		$configs = get_airpress_configs("airpress_cx",false);
		$active_tab = (isset($_GET['tab']))? intval($_GET['tab']) : 0;

		?>

  <h2 class="nav-tab-wrapper">
    <?php
			foreach($configs as $key => $config):
				$class = ($active_tab == $key)? 'nav-tab-active' : '';
				$tab_url = "?page=airpress_cx&tab={$key}"
			?>
    <a href="<?php echo esc_url($tab_url); ?>"
      class="nav-tab <?php echo esc_attr($class); ?>"><?php echo esc_html($config["name"]); ?></a>
    <?php
			endforeach;
			$last_tab_url = "?page=airpress_cx&tab=" . count($configs);
			?>
    <a href="<?php echo esc_url($last_tab_url);?>" class="nav-tab">+</a>
  </h2>

  <form method="post" action="options.php">
    <?php
				settings_fields( 'airpress_cx'.$active_tab );
				do_settings_sections( 'airpress_cx'.$active_tab );		
				submit_button();
			
			?>
  </form>

</div><!-- /.wrap -->
<?php
}

function airpress_admin_cx_tab_controller(){

	$airpress_config_initials = false;

	if ( isset($_GET["page"]) && preg_match("/^airpress_(..)$/",sanitize_title($_GET["page"]),$matches) ){
		$airpress_config_initials = $matches[1];
	} else if ( isset($_POST["option_page"]) && preg_match("/^airpress_(..).*$/",sanitize_title($_POST["option_page"]),$matches) ){
		$airpress_config_initials = $matches[1];
	}
	
	if ( ! $airpress_config_initials || $airpress_config_initials == "db"){
		// none of our business!		
		return;
	}

	if (isset($_GET["delete"]) && $_GET["delete"] == "true"){
		delete_airpress_config("airpress_".$airpress_config_initials,intval($_GET['tab']));
		header("Location: ".admin_url("/admin.php?page=airpress_".$airpress_config_initials));
		exit;
	} else {
		$configs = get_airpress_configs("airpress_".$airpress_config_initials,false);
		$requested_tab = (isset($_GET['tab']))? intval($_GET['tab']) : 0;
	}

	if (empty($configs) || !isset($configs[$requested_tab])){
		$config = array("name" => "New Configuration");
		$configs[] = $config;
		$active_tab = count($configs)-1;
		set_airpress_config("airpress_".$airpress_config_initials,$active_tab,$config);		
	} else {
		$active_tab = $requested_tab;
	}

	$_GET['tab'] = $active_tab;

	foreach($configs as $key => $config){
		$function = "airpress_admin_".$airpress_config_initials."_tab";
		call_user_func($function,$key,$config);
	}
}
add_action( 'admin_init', 'airpress_admin_cx_tab_controller');

/***********************************************/
# TAB: DEFAULT
/***********************************************/
function airpress_admin_cx_tab($key,$config) {

	$option_name = "airpress_cx".$key;
	//$options = get_option( $option_name );

	$uploads = wp_get_upload_dir();

	$defaults = array(
			"api_key" => "",
			"app_id" => "",
			"refresh" => MINUTE_IN_SECONDS * 5,
			"expire" => DAY_IN_SECONDS,
			"api_url" => "https://api.airtable.com/v0/",
			"fresh"  => "fresh",
			"debug"	=> 0,
			"log"	=> $uploads["basedir"] . "/airpress-" . uniqid() . ".log",
			"log_max_size"	=> 3072
		);

	$options = array_merge($defaults,$config);

	################################
	################################
	$section_title = "Airtable API Connections";
	$section_name = "airpress_cx".$key;

	add_settings_section(
		$section_name,
		__( $section_title, 'airpress' ),
		"airpress_admin_cx_render_section",
		$option_name
	);
	
	################################
	$field_name = "name";
	$field_title = "Configuration Name";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "api_key";
	$field_title = "Airtable Personal Access Token";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "app_id";
	$field_title = "Airtable APP ID";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "api_url";
	$field_title = "Airtable API URL";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################

	################################
	$field_name = "refresh";
	$field_title = "Refresh";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "expire";
	$field_title = "Expire";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "fresh";
	$field_title = "Query var to force refresh cache for this request";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "debug";
	$field_title = "Enable Debugging";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_select__debug', $option_name, $section_name, array($options,$option_name,$field_name) );

	################################
	$field_name = "log";
	$field_title = "Debug Logfile";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	// ################################
	// $field_name = "log_max_size";
	// $field_title = "Logfile Max Size in Kilobytes (0 for unlimited)";
	// add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_text', $option_name, $section_name, array($options,$option_name,$field_name) );

	###############################
	$field_name = "delete";
	$field_title = "Delete Configuration?";
	add_settings_field(	$field_name, __( $field_title, 'airpress' ), 'airpress_admin_cx_render_element_delete', $option_name, $section_name, array($options,$option_name,$field_name) );

	register_setting($option_name,$option_name,"airpress_cx_validation");
}

function airpress_cx_validation($input) {
	global $wp_rewrite;
	
	// Initialize new input array to store sanitized values
	$new_input = array();

	// Directly sanitize and save the Personal Access Token
	$new_input['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';

	// Sanitize other fields similarly
	$new_input['name'] = isset($input['name']) ? sanitize_text_field($input['name']) : '';
	$new_input['app_id'] = isset($input['app_id']) ? sanitize_text_field($input['app_id']) : '';
	$new_input['api_url'] = isset($input['api_url']) ? esc_url_raw($input['api_url']) : '';

	// Handle the debug and log file logic as before
	if (isset($input["debug"])) {
		if ($input["debug"] == 1 || $input["debug"] == 2) {
			if ($h = @fopen($input["log"], "a")) {
				$message = "log file created at " . $input["log"];
				fwrite($h, $message . "\n");
				fclose($h);
			} else {
				$new_input["debug"] = 0;
				add_settings_error('airpress_cx_log', esc_attr('settings_updated'), esc_attr($input["log"]) . " is not writable.", "error");
			}
		} else {
			$manual_intervention = false;
			if (file_exists($input["log"])) {
				$manual_intervention = true;
				if (is_writable($input["log"])) {
					if ($h = @fopen($input["log"], "w")) {
						$message = "attempting to delete " . $input["log"];
						fwrite($h, $message . "\n");
						fclose($h);
					}
					$parts = pathinfo($input["log"]);
					if ($parts["basename"] == "airpress.log") {
						if (unlink($input["log"])) {
							$manual_intervention = false;
						} else {
							if ($h = @fopen($input["log"], "a")) {
								$message = "failed to delete " . $input["log"];
								fwrite($h, $message . "\n");
								fclose($h);
							}
						}
					}
				}
			}
			if ($manual_intervention) {
				add_settings_error('airpress_cx_log', esc_attr('settings_updated'), "Please delete the log file at " . esc_attr($input["log"]), "error");
			}
		}
	}

	// Assume there could be additional fields to sanitize and include in $new_input
	// Example:
	// if (isset($input['some_other_field'])) {
	//     $new_input['some_other_field'] = sanitize_text_field($input['some_other_field']);
	// }

	$wp_rewrite->flush_rules();
	return $new_input; // Return the sanitized inputs
}

function airpress_admin_cx_render_section__general() {
	echo '<p>' . __( 'Provides examples of the five basic element types.', 'sandbox' ) . '</p>';
}

function airpress_admin_cx_render_section() {
	echo '<p>' . __( '', 'airpress' ) . '</p>';
}

function airpress_admin_cx_render_element_text($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$value = esc_attr($options[$field_name]);

	// Check if this is the PAT field
	if ($field_name == "api_key" && !empty($value)) {
		// Display a truncated version of the PAT
		$displayValue = substr($value, 0, 4) . '...' . substr($value, -4);
		echo '<input type="text" value="' . $displayValue . '" readonly="readonly" style="background-color: #e9ecef; cursor: not-allowed;" />';
		echo '<p class="description">The Personal Access Token is partially hidden for security. Enter a new token below to update it.</p>';
		// Provide an input field for entering a new PAT
		echo '<input type="text" id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . ']" value="" autocomplete="off" placeholder="Enter new PAT here" />';
	} else {
		// Render other fields normally
		echo '<input type="text" id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . ']" value="' . $value . '" />';
	}

	if ($field_name == "name" && $options[$field_name] == "New Configuration") {
		echo "<p style='color:red'>You must change the configuration name from 'New Configuration' to something unique!</p>";
	}
}


function airpress_admin_cx_render_element_toggle($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$checked = checked( 1, isset( $options[$field_name] ) ? $options[$field_name] : 0, false );
	echo '<input type="checkbox" id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . ']" value="1" '.esc_attr($checked).'/>';
	echo '<label for="'.esc_attr($field_name).'">&nbsp;'  . esc_html($field_name) . '</label>'; 
}

function airpress_admin_cx_render_element_select__posttypes($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$post_types = airpress_get_posttypes_available();

	echo '<select id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . '][]" multiple>';
	foreach ( $post_types  as $post_type ) {
		$selected = (in_array($post_type, $options[$field_name]))? "selected" : "";
		echo '<option value="'.esc_attr($post_type).'" '.esc_attr($selected).'>'.esc_html($post_type).'</option>';
	}
	echo '</select>';
}

function airpress_admin_cx_render_element_select_connections($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$connections = get_airpress_configs("airpress_cx");

	echo '<select id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . '][]" multiple>';
	foreach ( $connections  as $connection ) {
		$selected = (in_array($connection["name"], $options[$field_name]))? "selected" : "";
		echo '<option value="'.esc_attr($connection["name"]).'" '.esc_attr($selected).'>'.esc_html($connection["name"]).'</option>';
	}
	echo '</select>';
}

function airpress_admin_cx_render_element_select__page($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$pages = get_pages(); 
	
	echo '<select id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . ']">';

	foreach ( $pages as $page ) {
		$selected = ($options[$field_name] == $page->ID)? " selected" : "";
		echo '<option value="' . esc_attr($page->ID) . '"'.esc_attr($selected).'>';
		echo esc_html($page->post_title)." (".esc_html($page->post_name).")";
		echo '</option>';
	}
	echo '</select>';
}

function airpress_admin_cx_render_element_select__debug($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	echo '<select id="' . esc_attr($field_name) . '" name="' . esc_attr($option_name) . '[' . esc_attr($field_name) . ']">';
	$select_options = array(0 => "Disabled", 1 => "Admin Bar & Logfile", 2 => "Logfile only", 3 => "Admin Bar only");

	foreach ( $select_options as $value => $label ) {
		$selected = ($options[$field_name] == $value)? " selected" : "";
		echo '<option value="' . esc_attr($value) . '"'.esc_attr($selected).'>';
		echo esc_html($label);
		echo '</option>';
	}
	echo '</select>';
}

function airpress_admin_cx_render_element_delete($args) {
	$options = $args[0];
	$option_name = $args[1];
	$field_name = $args[2];

	$tab = intval($_GET["tab"]);
	$delete_url = "?page=airpress_cx&tab={$tab}";
	echo "<a href='". esc_url($delete_url) ."&delete=true'>Yes, delete this configuration</a>";
}

?>
