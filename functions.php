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



class CSO_Child_Theme_Updater {
    private $file;    
    private $theme;    
    private $themeObject;
    private $version;    
    private $active;    
    private $username;    
    private $repository;    
    private $authorize_token;
    private $github_response;
    public $log = array();
  
    public function __construct( $file ) {
        $this->file = $file;
        $this->set_theme_properties();
  
        //add_action( 'admin_init', array( $this, 'set_theme_properties' ) );
  
        return $this;
    }
  
    public function set_theme_properties() {
        $this->version  = wp_get_theme($this->theme)->get('Version');
        $this->themeObject = wp_get_theme($this->theme);

        $this->log[] = array('version' =>  wp_get_theme($this->theme)->get('Version'));
    }
  
    public function set_theme( $theme ) {
        $this->theme = $theme;
        $this->active	= $this->theme === get_stylesheet() ? true : false;

        $this->log[] = array('active' =>  $this->active, 'stylesheet' => get_stylesheet(),'theme'=> $theme );
    }
    public function set_username( $username ) {
        $this->username = $username;
    }
    public function set_repository( $repository ) {
        $this->repository = $repository;
    }
    public function authorize( $token ) {
        $this->authorize_token = $token;
    }
  
    private function get_repository_info() {
        if ( is_null( $this->github_response ) ) { // Do we have a response?
          $args = array();
          $request_uri = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository ); // Build URI
            
          $args = array();

          $this->log[] = array('request_url' => $request_uri);
  
          if( $this->authorize_token ) { // Is there an access token?
              $args['headers']['Authorization'] = "token {$this->authorize_token}"; // Set the headers
          }
  
          //$response = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_uri, $args ) ), true ); // Get JSON and parse it
          $response = json_decode(
            file_get_contents(
              'https://api.github.com/repos/'.$this->username.'/'.$this->repository.'/releases/latest', false,
                stream_context_create([
                'http' => ['header' => "User-Agent: ".$this->username."\r\n"],
                'ssl' => ["verify_peer"=>false, "verify_peer_name"=>false]
            ])
          ));

          $this->log[] = array('response' => $response);
  
          if( is_array( $response ) ) { // If it is an array
              $response = current( $response ); // Get the first item
          }
  
          $this->github_response = $response; // Set it to our property
        }
    }
  
    public function initialize() {
        $this->log[] = array('init' =>  true );

        add_filter( 'pre_set_site_transient_update_themes', array( $this, 'modify_transient' ), 10, 1 );
        //add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3);
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        
        // Attempt rename of files when downloading
        //add_filter( 'upgrader_source_selection', array( $this, 'rename_package_upon_download' ), 10, 3 );

        // Add Authorization Token to download_package
        add_filter( 'upgrader_pre_download',
            function() {
                add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );
                return false; // upgrader_pre_download filter default return value.
            }
        );
    }
  
    public function modify_transient( $transient ) {

        $this->log[] = array('transient_unmodified' =>  $transient );
  
        if( property_exists( $transient, 'checked') ) { // Check if transient has a checked property
  
            if( $checked = $transient->checked ) { // Did Wordpress check for updates?
                $this->get_repository_info(); // Get the repo info


                $this->log[] = array('transient_checked' =>  $checked );

  
                if( gettype($this->github_response) === "boolean" ) { return $transient; }
  
                $github_version = filter_var($this->github_response->tag_name, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
                $out_of_date = version_compare( 
                    $github_version, 
                    $checked[ $this->theme ], 
                    'gt' 
                ); // Check if we're out of date


                $this->log[] = array('modify_transient' =>  true, 'github_version' => $github_version,  'out_of_date' => $out_of_date );
  
                if( $out_of_date ) {
  
                    $new_files = $this->github_response->zipball_url; // Get the ZIP
                      
                    $slug = current( explode('/', $this->theme ) ); // Create valid slug
  
                    $theme = array( // setup our theme info
                        'url' => 'https://beech.agency', //$this->themeObject["ThemeURI"],
                        'slug' => $slug,
                        'package' => $new_files,
                        'new_version' => $this->github_response->tag_name
                    );
  
                    $transient->response[$this->theme] = $theme; // Return it in response
                    
                    $this->log[] = array('out_of_date' => true, 'theme'=> $theme);
                }
            }
        }
  
        return $transient; // Return filtered transient
    }


	public function rename_package_upon_download( $source, $remote_source=NULL, $upgrader=NULL ) {		
		if( isset($_GET['action'] ) && stristr( $_GET['action'], 'theme' ) ) {
			//$upgrader->skin->feedback( "Trying to customize theme folder name..." );
			if( isset( $source, $remote_source ) && stristr( $source, $theme ) ){
                
				$corrected_source = $this->theme;

				if( @rename( $source, $corrected_source ) ) {
					//$upgrader->skin->feedback( "Theme folder name corrected to: " . $theme );
					return $corrected_source;
				} else {
					//$upgrader->skin->feedback( "Unable to rename downloaded theme." );
					return new WP_Error();
				}
			}
		}
	    return $source;
	}
  
    public function download_package( $args, $url ) {
      //dump_it('Download Package', 'red');
      //dump_it($args, 'red');
  
        if ( null !== $args['filename'] ) {
            if( $this->authorize_token ) { 
                $args = array_merge( $args, array( "headers" => array( "Authorization" => "token {$this->authorize_token}" ) ) );
            }
        }
        
        remove_filter( 'http_request_args', [ $this, 'download_package' ] );
  
        return $args;
    }
  
    public function after_install( $response, $hook_extra, $result ) {
  
        global $wp_filesystem; // Get global FS object


        $this->log[] = array('after_install' => true );
  
        $install_directory = get_theme_root(). '/' . $this->theme ; // Our theme directory
        $wp_filesystem->move( $result['destination'], $install_directory ); // Move files to the theme dir
        $result['destination'] = $install_directory; // Set the destination for the rest of the stack

        $this->log[] = array('post_after_install' => true, 'install_directory' => $install_directory,'result_destination' => $result['destination']);

        // Activate the theme again once the files have been moved etc.
        if($this->active) {
            switch_theme( $this->theme );
        }
  
        return $result;
    }
}
  
$updater = new CSO_Child_Theme_Updater( __FILE__ );

$updater->set_username( 'BeechAgency' );
$updater->set_repository( $GLOBALS['THEME_NAME'] );
$updater->set_theme($GLOBALS['THEME_NAME']); 

$updater->initialize();

function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log("DEBUG ON"); console.log(' . json_encode($output, JSON_HEX_TAG) . 
');';
    if ($with_script_tags) {
        $js_code = '<script type="text/javascript" id="debugging">' . $js_code . '</script>';
    }
    echo $js_code;
}

//console_log($updater->log, true);

do_action('admin_footer', 'console_log');