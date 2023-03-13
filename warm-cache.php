<?php
/*
Plugin Name: Warm cache
Plugin URI: https://www.autowarmcache.com
Description: Crawls your website-pages based on any XML sitemap plugin. If you have a caching plugin this wil keep your cache warm. Speeds up your site.
Version: 4.1.0
Author: Ramon Fincken
Author URI: https://www.autowarmcache.com
Text Domain: managedwphosting_warmcache
*/
if ( !defined( 'ABSPATH' ) && !isset( $_GET['warm_cache'] ) ) {
        die( "Aren't you supposed to come here via WP-Admin?" );
}


if ( !class_exists( 'mijnpress_plugin_framework' ) ) {
    include( 'mijnpress_plugin_framework.php' );
}


class warm_cache extends mijnpress_plugin_framework {
    public $google_sitemap_generator_options;
    public $sitemap_url;
    public $keep_time;


    function __construct() {
        $this->showcredits                = true;
        $this->showcredits_fordevelopers  = false;
        $this->plugin_title               = 'Warm Cache';
        $this->plugin_class               = 'warm_cache';
        $this->plugin_filename            = 'warm_cache/warm_cache.php';
        $this->plugin_config_url          = NULL;
        $this->keep_time                  = 7*DAY_IN_SECONDS; // 7 days for now (TODO: admin setting)
        $this->flush_loadbalancer         = get_option( 'plugin_warm_cache_lb_flush' );
    }

    public static function admin_notices() {
        $warm_cache_admin    = new warm_cache();
        $sitemap_url         = $warm_cache_admin->get_sitemap_url();

        if ( !$sitemap_url ) {
            // No override post?
            if (!isset($_POST['update_sitemap']) || !$_POST['update_sitemap']) {
                echo '<div class="error"><p>';
                _e( 'A notice from plugin Warm-cache: Your configured sitemap url is not configured, I cannot crawl your pages.', 'managedwphosting_warmcache' );
                echo '<a href="'.admin_url('plugins.php?page=warm-cache%2Fwarm-cache.php').'">'.__( 'Update your sitemap url now', 'managedwphosting_warmcache' ).'</a>.</p></div>';
            }
        } else {
            // Check sitemap validity
            $key = 'warm-cache-sitemapcheck';

            if (false === ( $sitemapSyntaxOK = get_transient( $key ) ) ) {
                $sitemapSyntaxOK = true;
                $response = wp_remote_get($sitemap_url);
                if (! is_array($response)) {
                    $errormsg = print_r($response, true);
                    $sitemapSyntaxOK = false;
                } else {
                    $xmldata = wp_remote_retrieve_body($response);
                    if (substr_count($xmldata, '<?xml') == 0) {
                        $sitemapSyntaxOK = false;
                        $errormsg = __('No xml opening tag', 'managedwphosting_warmcache' );
                    } else {
                        if (substr_count($xmldata, '<urlset') == 0 && substr_count($xmldata, '<sitemap') == 0) {
                            $sitemapSyntaxOK = false;
                            $errormsg = __('Urlset or sitemap tag', 'managedwphosting_warmcache' );
                        }
                    }
                }
                if ( $sitemapSyntaxOK ) {
                    // If it's OK, we will re-check in 12 hours
                    set_transient( $key, $sitemapSyntaxOK, 12 * HOUR_IN_SECONDS );
                } else {
                    // If it's NOT OK, we will re-check in 2 minutes (sooner could induce more server load)
                    set_transient( $key, $sitemapSyntaxOK, 2 * MINUTE_IN_SECONDS );
                }
            }

            if ( !$sitemapSyntaxOK ) {
                echo '<div class="error"><p>';
                _e('A notice from plugin Warm-cache: Your configured sitemap url', 'managedwphosting_warmcache' );
                echo '( <a href="'.$sitemap_url.'">'.$sitemap_url.'</a> ) ';
                _e( 'is configured, but does not appear to contain an xml opening tag, or a combination of urlset or sitemap, I cannot crawl your pages. Please check your sitemap plugin to fix your currupt sitemap.', 'managedwphosting_warmcache' );
                echo '<br/>';
                _e( 'Note: this check will be cached for 2 minutes. So if you fix the problem, this notice might still be present for 2 minutes.', 'managedwphosting_warmcache' );

                echo '</p><p>Error detail: '.$errormsg. '</p></div>';
            }

            if ( !function_exists( 'simplexml_load_string' ) ) {
                echo '<div class="error"><p>'.__('PHP Function simplexml_load_string is not available on your server, please contact your host.', 'managedwphosting_warmcache' ).'</p>
                <p>Error detail: undefined function simplexml_load_string</p></div>';
            }
        }
    }

    public static function init() {
        $labels = array(
            'name'               => _x( 'Books', 'post type general name', 'managedwphosting_warmcache' ),
            'singular_name'      => _x( 'Book', 'post type singular name', 'managedwphosting_warmcache' ),
            'menu_name'          => _x( 'Books', 'admin menu', 'managedwphosting_warmcache' ),
            'name_admin_bar'     => _x( 'Book', 'add new on admin bar', 'managedwphosting_warmcache' ),
            'add_new'            => _x( 'Add New', 'book', 'managedwphosting_warmcache' ),
            'add_new_item'       => __( 'Add New Book', 'managedwphosting_warmcache' ),
            'new_item'           => __( 'New Book', 'managedwphosting_warmcache' ),
            'edit_item'          => __( 'Edit Book', 'managedwphosting_warmcache' ),
            'view_item'          => __( 'View Book', 'managedwphosting_warmcache' ),
            'all_items'          => __( 'All Books', 'managedwphosting_warmcache' ),
            'search_items'       => __( 'Search Books', 'managedwphosting_warmcache' ),
            'parent_item_colon'  => __( 'Parent Books:', 'managedwphosting_warmcache' ),
            'not_found'          => __( 'No books found.', 'managedwphosting_warmcache' ),
            'not_found_in_trash' => __( 'No books found in Trash.', 'managedwphosting_warmcache' )
        );

        $args = array(
            //'labels'             => $labels,
                'description'        => __( 'Description.', 'managedwphosting_warmcache' ),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // hides: edit.php?post_type=warmcache
            'query_var'          => false,
            'rewrite'            => array( 'slug' => 'warmcache' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'custom-fields' )
        );

        register_post_type( 'warmcache', $args );

        if ( isset( $_GET['warm-key'] ) && !empty( $_GET['warm-key'] ) && sanitize_text_field($_GET['warm-key']) == get_option( 'plugin_warm_cache_api' ) ) {
            require_once( 'warm_cache_crawl.php' );
            $warm_cache_crawler = new warm_cache_crawl();
        } else {
            if ( is_admin() ) {
                add_action( 'admin_menu', array( 'warm_cache', 'addPluginSubMenu_' ) );
                add_filter( 'plugin_row_meta', array( 'warm_cache', 'addPluginContent_' ), 10, 2 );
                add_action( 'admin_notices', array( 'warm_cache', 'admin_notices' ) );
            }
        }
    }

    static function addPluginSubMenu_($title = '',$function = '') {
        $plugin = new warm_cache();
        $plugin->addPluginSubMenu( 'Warm cache', array( 'warm_cache', 'admin_menu' ), __FILE__ );
    }


    /**
     * Additional links on the plugin page
     */
    static function addPluginContent_( $links, $file, $strictstandardvoid1 = '', $strictstandardvoid2 = '' ) {
        $plugin = new warm_cache();
        $links = $plugin->addPluginContent( $plugin->plugin_filename, $links, $file, $plugin->plugin_config_url );
        return $links;
    }

    public static function admin_menu() {
        load_plugin_textdomain( 'plugin_warm_cache' );
        $warm_cache_admin = new warm_cache();
        $warm_cache_admin->plugin_title = 'Warm cache';
        if (!$warm_cache_admin->configuration_check()) {

        } else {
            $warm_cache_admin->content_start();
            $stats = $warm_cache_admin->get_stats();

            if (!$stats['crawl']) {
                $msg = 'Ok, we have detected your sitemap url but it has not been visited by the plugin\'s crawler.<br/>';
                $warm_cache_api_url = trailingslashit(get_bloginfo('url')).'warmcache/?warm-key='.get_option('plugin_warm_cache_api');
                $msg .= 'The url you should call from a cronjob is: '.$warm_cache_api_url.'<br/>';
                $msg .= 'To re-set the key, visit this url: '.admin_url('plugins.php?page=warm-cache/warm-cache.php&resetkey=true').'<br/>';
                $msg .= '<p>Or save you all the hassle and use our <a href="https://www.autowarmcache.com/">Auto Warm cache service</a></p>';

                $warm_cache_admin->show_message($msg);
                echo '<br/><br/>';
            } else {
                $msg = 'Crawled in total '.$stats['stats_pages'].' pages in a total of '.$stats['stats_times']. ' seconds (based on the last 75 crawls)<br/>';
                if ($stats['stats_pages']) {
                    $msg .= 'Average page to load a page in seconds: '. $stats['stats_times']/$stats['stats_pages'];
                }
                $warm_cache_admin->show_message($msg);
            }
            echo '<table class="widefat">';
            echo '<tr><th class="manage-column" style="width: 150px;">Crawled at</th><th class="manage-column">Time needed</th><th class="manage-column" style="width: 120px;">Number of pages</th><th class="manage-column">Average load time per page</th><th class="manage-column">Pages</th></tr>';
            echo $stats['table_string'];
            echo '</table>';
            $warm_cache_admin->content_end();
        }
    }

    /**
     * Add or update the API key
     */
    private function change_apikey() {
        $special_chars = false;
        delete_option('plugin_warm_cache_api');
        add_option('plugin_warm_cache_api', wp_generate_password(9, $special_chars));
    }

    /**
    * Gets table and stats
    */
    private function get_stats() {
        $myposts = get_posts('post_type=warmcache&numberposts=75&order=DESC&orderby=post_date');

        $statdata = get_option('plugin_warm_cache_start', false);
        if ($statdata === false && !get_option('plugin_warm_cache_api')) {
            $this->change_apikey();
        }

        $table_string = '';
        if (!count($myposts)) {
            $table_string .= '<tr><td valign="top" colspan="5">';
            $table_string .= __('Your site has not been crawled by the plugin','plugin_warm_cache');
            $table_string .= '</td></tr>';
            return array('crawl' => false, 'table_string' => $table_string);
        }
        $stats_pages = 0;
        $stats_times = 0;

        foreach($myposts as $post) {
            $mytime = get_post_meta($post->ID, 'mytime', true);
            $mypages = get_post_meta($post->ID, 'mypages', true);

            $stats_pages += $mypages; 
            $stats_times += $mytime;

            $table_string .= '<tr><td valign="top">';
            // Crawled at
            $table_string .= $post->post_title.'</td>';
            // Time needed
            $table_string .= '<td>'.$mytime.'</td>';
            // Number of pages
            $table_string .= '<td>'.$mypages.'</td>';
            // Average load time per page
            $table_string .= '<td>'.((intval($mypages) > 0) ? ($mytime/$mypages) : '0').'</td>';
            // Pages
            $table_string .= '<td><a href="'.admin_url('post.php?post='.$post->ID.'&action=edit').'">View</a></td>';
            $table_string .= '</td></tr>';
        }

        return array('crawl' => true, 'stats_pages' => $stats_pages, 'stats_times' => $stats_times, 'table_string' => $table_string);
    }

    /**
     * Updates sitemap url override
     * @param unknown_type $url
     */
    private function update_sitemap_overide_url($url) {
        delete_option('plugin_warm_cache_sitemap_override');
        add_option( 'plugin_warm_cache_sitemap_override', htmlspecialchars($url));
    }

    /**
     * Updates flush yes/no
     * @param    string    $flush

     */
    private function update_flush($flush) {
        delete_option( 'plugin_warm_cache_lb_flush' );
        if ( $flush != 'yes' ) { $flush = 'no'; } // Sanity check
        add_option( 'plugin_warm_cache_lb_flush', htmlspecialchars($flush));
        // Update local
        $this->flush_loadbalancer = get_option("plugin_warm_cache_lb_flush");
    }

    private function configuration_check() {
        $this->google_sitemap_generator_options = get_option("sm_options");
        $msg = '';
        if (isset($_GET['resetkey'])) {
            $this->change_apikey();
            $msg .= __('API key has changed, please update your cronjobs right now!','plugin_warm_cache');
            $this->show_message('<strong>'.$msg.'</strong>');
            $msg = ''; // Reset msg
        }

        if (isset($_POST['update_sitemap']) && $_POST['update_sitemap']) {
            $this->update_sitemap_overide_url($_POST['update_sitemap']);
        }
        if (isset($_POST['flush']) && $_POST['flush']) {
            $this->update_flush($_POST['flush']);
        }
        // Init config
        $this->get_sitemap_url(); // FIXME: Remove?

        $msg .= '<form method="post" action="'.admin_url('plugins.php?page=warm-cache/warm-cache.php'). '">Please enter your full sitemap url if we cannot detect it automatically (do not forget the http:// up front): ';
        $msg .= '<br/><input type="text" value="'.get_option('plugin_warm_cache_sitemap_override').'" name="update_sitemap" size="60" /><input type="submit" value="Use this sitemap" /></form>';
        $msg .= '<form method="post" action="'.admin_url('plugins.php?page=warm-cache/warm-cache.php'). '">If you have a loadbalancer you might need to set flush to Yes to prevent timeouts.';
        $msg .= '<br/>Toggle setting if you have a crawled 0 pages when calling the cronjob url.<br/> ';
        $msg .= '<input '.(( $this->flush_loadbalancer && $this->flush_loadbalancer != 'yes') ? 'checked="checked"' : '') . ' id="plugin_wc_flush_no" name="flush" type="radio" value="no"><label for="plugin_wc_flush_no">No, do not flush</label> ';
        $msg .= '<input '.(( $this->flush_loadbalancer && $this->flush_loadbalancer == 'yes') ? 'checked="checked"' : '') . ' id="plugin_wc_flush_yes" name="flush" type="radio" value="yes"><label for="plugin_wc_flush_yes">Yes, flush</label> ';
        $msg .= '<input type="submit" value="Update flush settings" /></form></br>';        

        if ( !($this->google_sitemap_generator_options && is_array($this->google_sitemap_generator_options)) && !$this->sitemap_url ) {
            $msg .= __('Could not find sitemap options, please enter your sitemap url','plugin_warm_cache');
            $returnvar = false;
        } else {
            $msg .= 'Sitemap url: <a target="_blank" href="'.$this->sitemap_url.'">'.$this->sitemap_url.'</a><br/>';
            $warm_cache_api_url = trailingslashit(get_bloginfo('url')).'warmcache/?warm-key='.get_option('plugin_warm_cache_api');
            $msg .= 'The url you should call from a cronjob is: '.$warm_cache_api_url.'<br/>';
            $msg .= 'To re-set the key, visit this url: '.admin_url('plugins.php?page=warm-cache/warm-cache.php&resetkey=true').'<br/>';
            $msg .= '<p>Or save you all the hassle and use our <a href="https://www.autowarmcache.com/">Auto Warm cache service</a></p>';
            $returnvar = true;
        }
        echo '<div style="margin-top: 25px;">';
        $this->show_message( $msg );
        echo '</div>';

        return $returnvar;
    }

    /**
    * Retrieves public front-end sitemap URL
    * @return    string|false
    */
    public function get_sitemap_url() {
        // Guess sitemap url from Google XML sitemap generator
        if ( isset( $this->google_sitemap_generator_options["sm_b_location_mode"] ) &&  $this->google_sitemap_generator_options["sm_b_location_mode"] == "manual" ) {
            $sitemap_url = $this->google_sitemap_generator_options["sm_b_fileurl_manual"];
        } elseif ( isset( $this->google_sitemap_generator_options["sm_b_filename"] ) && $this->google_sitemap_generator_options["sm_b_filename"] != '') {
            $sitemap_url =  trailingslashit(get_bloginfo('url')). $this->google_sitemap_generator_options["sm_b_filename"];
        }

        $override = get_option( 'plugin_warm_cache_sitemap_override' );
        if ( $override && !empty( $override ) && $override != 'http://' ) {
            $sitemap_url = $override;
        }
        // Final check
        if ( isset( $sitemap_url ) && $sitemap_url && !empty( $sitemap_url ) && $sitemap_url != 'http://' && $sitemap_url != trailingslashit( get_bloginfo( 'url' ) ) ) {
            $this->sitemap_url = $sitemap_url;
            return $this->sitemap_url;
        }
        return false;
    }
}


add_action( 'init', array('warm_cache', 'init' ) );
