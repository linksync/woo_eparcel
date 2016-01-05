<?php
class LinksynceparcelPluginUpdater {
	private $slug;
    private $pluginData;
    private $username;
    private $repo;
    private $pluginFile;
    private $githubAPIResult;
    private $accessToken;
	
	public function __construct($pluginFile, $gitHubUsername, $gitHubProjectName, $accessToken = '') {
		add_action( 'admin_bar_menu', array($this, 'force_updates_check_link'), 999 );
		add_action( 'admin_init', array($this, 'trigger_force_updates_check') );

		add_filter( "pre_set_site_transient_update_plugins", array( $this, "setTransitent" ) );
        add_filter( "plugins_api", array( $this, "setPluginInfo" ), 10, 3 );
        add_filter( "upgrader_post_install", array( $this, "postInstall" ), 10, 3 );
		
 
        $this->pluginFile = $pluginFile;
        $this->username = $gitHubUsername;
        $this->repo = $gitHubProjectName;
        $this->accessToken = $accessToken;
	}
	
	public function force_updates_check_link( $wp_admin_bar ) {

		if( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		if( ! $wp_admin_bar->get_node( 'updates' ) ) {

			// This forces the update menu to show at all times, even if there are no updates

			$update_data = wp_get_update_data();

			$title = '<span class="ab-icon"></span><span class="ab-label">' . number_format_i18n( 0 ) . '</span>';
			$title .= '<span class="screen-reader-text">' . $update_data['title'] . '</span>';

			$wp_admin_bar->add_menu( array(
				'id'    => 'updates',
				'title' => $title,
				'href'  => network_admin_url( 'update-core.php' ),
				'meta'  => array(
					'title' => $update_data['title'],
				),
			) );
		}

		$args = array(
			'parent' => 'updates',
			'id'     => 'force-plugins-update',
			'title'  => __( 'Check for Plugin Updates' ),
			'href'   => add_query_arg( 'action', 'force_plugin_updates_check', admin_url( 'index.php') )
		);
		$wp_admin_bar->add_node( $args );
	}
	
	public function trigger_force_updates_check() {
		if( ! isset( $_GET['action'] ) || 'force_plugin_updates_check' != $_GET['action'] ) {
			return;
		}

		if( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		set_site_transient( 'update_plugins', null );

		wp_safe_redirect( admin_url( 'index.php' ) ); exit;
	}
	
	/* 
	* Get information regarding our plugin from WordPress
	*/
    private function initPluginData() {
        $this->slug = plugin_basename( $this->pluginFile );
		$this->pluginData = get_plugin_data( $this->pluginFile );	
    }
 
    /* 
	* Get information regarding our plugin from GitHub
	*/
    private function getRepoReleaseInfo() {
        if ( ! empty( $this->githubAPIResult ) ) {
			return;
		}
		
		$url = "https://api.github.com/repos/". $this->username ."/". $this->repo ."/releases";
		 
		if ( ! empty( $this->accessToken ) ) {
			$url = add_query_arg( array( "access_token" => $this->accessToken ), $url );
		}
		 
		$this->githubAPIResult = wp_remote_retrieve_body( wp_remote_get( $url ) );
		if ( ! empty( $this->githubAPIResult ) ) {
			$this->githubAPIResult = @json_decode( $this->githubAPIResult );
		}
		
		if ( is_array( $this->githubAPIResult ) ) {
			$this->githubAPIResult = $this->githubAPIResult[0];
		}
    }

    /* 
	* Push in plugin version information to get the update notification
	*/
    public function setTransitent( $transient ) {
		
        if ( empty( $transient->checked ) )
			return $transient;
		
		$this->initPluginData();
		$this->getRepoReleaseInfo();
		
		$doUpdate = version_compare( $this->githubAPIResult->tag_name, $transient->checked[$this->slug] );
		
		if ( $doUpdate == 1 ) {
			$package = $this->githubAPIResult->zipball_url;
		 
			if ( !empty( $this->accessToken ) ) {
				$package = add_query_arg( array( "access_token" => $this->accessToken ), $package );
			}
		 
			$obj = new stdClass();
			$obj->slug = $this->slug;
			$obj->new_version = $this->githubAPIResult->tag_name;
			$obj->url = $this->pluginData["PluginURI"];
			$obj->package = $package;
			$transient->response[$this->slug] = $obj;
		}
		
		return $transient;
    }
 
    /* 
	* Push in plugin version information to display in the details lightbox
	*/
    public function setPluginInfo( $false, $action, $response ) {
		$this->initPluginData();
		$this->getRepoReleaseInfo();
		
		if ( empty( $response->slug ) || $response->slug != $this->slug ) {
			return false;
		}
		
		
		$response->last_updated = $this->githubAPIResult->published_at;
		$response->slug = $this->slug;
		$response->plugin_name  = $this->pluginData["Name"];
		$response->version = $this->githubAPIResult->tag_name;
		$response->author = $this->pluginData["AuthorName"];
		$response->homepage = $this->pluginData["PluginURI"];
		 
		$downloadLink = $this->githubAPIResult->zipball_url;
		 
		if ( !empty( $this->accessToken ) ) {
			$downloadLink = add_query_arg(
				array( "access_token" => $this->accessToken ),
				$downloadLink
			);
		}
		$response->download_link = $downloadLink;
		
		$response->sections = array(
			'description' => $this->pluginData["Description"],
			'changelog' => $this->githubAPIResult->body
		);
		
		$matches = null;
		preg_match( "/requires:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches );
		if ( ! empty( $matches ) ) {
			if ( is_array( $matches ) ) {
				if ( count( $matches ) > 1 ) {
					$response->requires = $matches[1];
				}
			}
		}
		
		$matches = null;
		preg_match( "/tested:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches );
		if ( ! empty( $matches ) ) {
			if ( is_array( $matches ) ) {
				if ( count( $matches ) > 1 ) {
					$response->tested = $matches[1];
				}
			}
		}
		 
		return $response;
    }
 
    /* 
	* Perform additional actions to successfully install our plugin
	*/
    public function postInstall( $true, $hook_extra, $result ) {
		global $wp_filesystem;
		
        $this->initPluginData();
		// $wasActivated = is_plugin_active( $this->slug );
		
		$pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->slug );
		$wp_filesystem->move( $result['destination'], $pluginFolder );
		$result['destination'] = $pluginFolder;
		
		$activate = activate_plugin( WP_PLUGIN_DIR.'/'.$this->config['slug'] );

		// Output the update message
		$fail  = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'github_plugin_updater' );
		$success = __( 'Plugin reactivated successfully.', 'github_plugin_updater' );
		echo is_wp_error( $activate ) ? $fail : $success;
        return $result;
    }
}
