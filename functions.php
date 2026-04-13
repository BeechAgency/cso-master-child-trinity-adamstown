<?php 

$GLOBALS['THEME_NAME'] = 'cso-master-child-trinity-college-adamstown';
$GLOBALS['CHILD_THEME_COLORS'] = array(
	'white' => 'ffffff',
	'black' => '000000',
	'primary-dark' => '002a4e',
	'primary-light' => 'D9DAE4',
	'secondary-dark' => 'c7994a',
	'secondary-light' => 'c52233',
	'warning' => 'E31E39',
	'success' => '2DC98D'
);


add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles' );

function enqueue_parent_styles() {
    // get the parent object
    $parent_theme = wp_get_theme()->parent();
    // get parent version
    $csomaster_version = '0.1';
    if (!empty($parent_theme)) $csomaster_version = $parent_theme->Version;

    wp_enqueue_style( 'parent-style', get_template_directory_uri().'/style.css?v='.$csomaster_version );
}

/* Default brand colors for MCE color picker */
function csomaster_mce4_options($init) {

	// Loop through THEME_COLORS and add them to the MCE color picker
	$THEME_COLORS = $GLOBALS['CHILD_THEME_COLORS'];

	$custom_colours = "";

	foreach($THEME_COLORS as $name => $hex) {
		$custom_colours .= "'$hex',' $name',";
	}

    // build colour grid default+custom colors
    $init['textcolor_map'] = '['.$custom_colours.']';

    // change the number of rows in the grid if the number of colors changes
    // 8 swatches per row
    $init['textcolor_rows'] = 1;

    return $init;
}
add_filter('tiny_mce_before_init', 'csomaster_mce4_options');


require get_stylesheet_directory() . '/inc/updater.php';

$update_key = get_option('csomaster_updates_key', null );
$updater = new CatholicSchoolsMN_Child_Theme_Updater( __FILE__ );
$updater->set_username( 'BeechAgency' );
$updater->set_repository( $GLOBALS['THEME_NAME'] );
$updater->set_theme($GLOBALS['THEME_NAME']); 

if( $update_key ) {
    $updater->authorize($update_key);    
}

$updater->initialize();
