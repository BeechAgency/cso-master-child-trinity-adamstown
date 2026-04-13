<?php 
/**
 * Theme Updater for Catholic Schools Child Themes
 */

class CatholicSchoolsMN_Child_Theme_Updater {
    private $file;    
    private $theme;    
    private $themeObject;
    private $version;    
    private $active;    
    private $username;    
    private $repository;    
    private $authorize_token;
    private $github_response;
    private $package_url;
    private $logging = false;

    private $theme_dir;

    /**
     * Constructor
     */
    public function __construct( $file ) {
        $this->file = $file;
        $this->set_theme_properties();
        return $this;
    }

    /**
     * Provides logging to error_log if enabled
     */
    private function log($message) {
        if ( !$this->logging ) return;
        $timestamp = date("Y-m-d H:i:s");
        error_log("ChildThemeUpdater [$timestamp]: $message");
    }

    /**
     * Enable or disable logging
     */
    public function set_logging( $status = false ) {
        $this->logging =  $status;
    }

    /**
     * Set basic theme properties
     */
    public function set_theme_properties() {
        if ( empty( $this->theme ) ) return;
        $this->themeObject = wp_get_theme($this->theme);
        $this->version  = $this->themeObject->get('Version');
        $this->active	= $this->theme === get_stylesheet() ? true : false;
        $this->theme_dir = get_theme_root();
    }

    /**
     * Set the theme slug
     */
    public function set_theme( $theme ) {
        $this->theme = $theme;
        $this->set_theme_properties();
    }

    /**
     * Set the GitHub username
     */
    public function set_username( $username ) {
        $this->username = $username;
    }

    /**
     * Set the GitHub repository name
     */
    public function set_repository( $repository ) {
        $this->repository = $repository;
    }

    /**
     * Set the GitHub authorization token
     */
    public function authorize( $token ) {
        $this->authorize_token = $token;
    }

    /**
     * Fetch latest release information from GitHub API
     */
    private function get_repository_info() {
        if ( !is_null( $this->github_response ) ) return;
    
        $request_uri = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository );
    
        $headers = array(
            'User-Agent: ' . $this->username,
        );
    
        if ($this->authorize_token) {
            $headers[] = 'Authorization: token ' . $this->authorize_token;
        }
    
        $this->log("Fetching repository info from: $request_uri");

        $ch = curl_init($request_uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($http_code == 200) {
            $this->github_response = json_decode($response);
            if ( isset($this->github_response->tag_name) ) {
                $this->log("Successfully caught release data: " . $this->github_response->tag_name);
            }
        } else {
            $this->log("GitHub API request failed with code: $http_code");
        }
    }

    /**
     * Initialize WordPress hooks
     */
    public function initialize() {
        add_filter( 'pre_set_site_transient_update_themes', array( $this, 'modify_transient' ), 10, 1 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

        // Add Authorization Token to download_package
        add_filter( 'upgrader_pre_download',
            function() {
                add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );
                return false;
            }
        );
    }

    /**
     * Modify the update transient to include our theme if a new version exists
     */
    public function modify_transient( $transient ) {
        if( !property_exists( $transient, 'checked') || !$transient->checked ) {
            return $transient;
        }

        $checked = $transient->checked;
        $this->get_repository_info();
        
        if( empty($this->github_response) || !isset($this->github_response->tag_name) ) { 
            return $transient; 
        }

        $github_version = filter_var($this->github_response->tag_name, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $out_of_date = version_compare( 
            $github_version, 
            $checked[ $this->theme ], 
            'gt' 
        );

        if( !$out_of_date )  {
            return $transient; 
        }

        $this->log("New version available: $github_version");

        $new_files = $this->github_response->zipball_url; // Fallback to zipball
        
        // If there are theme assets attached (standard workflow ZIP), use the first one
        if( isset($this->github_response->assets) && is_countable($this->github_response->assets) && count($this->github_response->assets) > 0 ) {
            $asset = $this->github_response->assets[0];
            $this->log("Using release asset for package: " . $asset->name);
            
            if (isset($asset->id) && $this->authorize_token) {
                // Use API for private repo asset download
                $new_files = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/assets/{$asset->id}";
            } else {
                $new_files = $asset->browser_download_url;
            }
        }

        $theme = array(
            'url' => 'https://github.com/'.$this->username.'/'.$this->repository,
            'slug' => $this->theme,
            'package' => $new_files,
            'new_version' => $github_version
        );

        $transient->response[$this->theme] = $theme;

        return $transient;
    }

    /**
     * Add headers to the download request
     */
    public function download_package( $args, $url ) {
        if (strpos($url, $this->username . '/' . $this->repository) === false) {
            return $args;
        }
    
        if ($this->authorize_token) {
            if (!isset($args['headers'])) {
                $args['headers'] = [];
            }
            $args['headers']['Authorization'] = "token {$this->authorize_token}";
            
            // For GitHub API asset downloads, we need specific headers
            if (strpos($url, '/releases/assets/') !== false) {
                $args['headers']['Accept'] = "application/octet-stream";
            }
        }
        
        $args['timeout'] = 300;
        $args['redirection'] = 5;
        
        remove_filter('http_request_args', [$this, 'download_package']);
    
        return $args;
    }

    /**
     * Ensure the theme lives in its own directory, not a tag-named one
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        $install_directory = get_theme_root(). '/' . $this->theme ;
        $this->log("Moving deployment from " . $result['destination'] . " to $install_directory");
        
        $wp_filesystem->move( $result['destination'], $install_directory );
        $result['destination'] = $install_directory;

        if($this->active) {
            switch_theme( $this->theme );
        }

        return $result;
    }
}
