<?php
/**
* Part of WordPress Plugin: Warm cache
* Based on script from : http://blogs.tech-recipes.com/johnny/2006/09/17/handling-the-digg-effect-with-wordpress-caching/
*/
class warm_cache_crawl {

    private $newvalue;
    private $totalcount;
    private $start;
    private $limit;
    private $hits;
    private $index;
    private $useflush;
    private $pid_lock;


    public function crawl_limit_filter( $limit ) {
        if( defined( 'MP_WARM_CACHE_FILTER_LIMIT' ) ) {
            $newlimit = intval( MP_WARM_CACHE_FILTER_LIMIT );
            if( $newlimit > 0 ) {
                return $newlimit;
            }
        }
        return $limit;
    }

    private function do_crawl_multi( $urls ) {
        $header_variations = array(
                                array( "Accept" => "text/html,application/xhtml+xml,application/xml" ),
                                array( "Accept-Encoding" => "gzip", "Accept" => "text/html,application/xhtml+xml,application/xml" ),
                                array( "Accept-Encoding" => "br", "Accept" => "text/html,application/xhtml+xml,application/xml" )
                                );

        $multi_curl = curl_multi_init();
        $curl_array = array();
        $curl_index = 0;

        foreach( $urls as $url ) {
            foreach ( $header_variations as $i => $headers ) {
                $curl_array[$curl_index] = curl_init();

                curl_setopt_array( $curl_array[$curl_index], array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_MAXREDIRS => 1,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_HTTPHEADER => $headers
                ) );

                curl_multi_add_handle( $multi_curl, $curl_array[$curl_index] );

                $curl_index++;
            }
        }

        // execute the multi handle
        $active = null;

        do {
            $status = curl_multi_exec( $multi_curl, $active );
            if ( $active ) {
                // Wait a short time for more activity
                curl_multi_select( $multi_curl );
            }
        } while ( $active && $status == CURLM_OK );

        // close the handles
        for ( $i=0; $i < $curl_index; $i++ ) {
            curl_multi_remove_handle( $multi_curl, $curl_array[$i] );
        }

        curl_multi_close( $multi_curl );

        // free up additional memory resources
        for ( $i=0; $i < $curl_index; $i++ ) {
            curl_close( $curl_array[$i] );
        }
    }

    public function __construct() {
        // Prevent any reverse proxy caching / browser caching
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("X-Accel-Buffering: no");

        // flush any output buffers
        while ( @ob_end_flush() ) ;

        add_filter( 'mp_warm_cache_limit_filters', array( $this, 'crawl_limit_filter' ) , 10, 1 );
        $this->pid_lock = 'mp_warm_cache_pid_lock';
        $this->useflush = get_option( 'plugin_warm_cache_lb_flush' );

        $is_locked = get_transient( $this->pid_lock );
        if ( $is_locked ) {
            echo "Lock active, stopped processing. Wait 60 seconds\n";
            die();
        } else {
            set_transient( $this->pid_lock, 'Busy', MINUTE_IN_SECONDS );
        }

        $warm_cache = new warm_cache();
        $warm_cache->google_sitemap_generator_options = get_option( 'sm_options' );
        $sitemap_url = $warm_cache->get_sitemap_url();

        $this->limit = apply_filters( 'mp_warm_cache_limit_filters', get_option( 'plugin_warm_cache_limit', 20 ) );
        $start_p = get_option( 'plugin_warm_cache_start', 0 );
        $this->start = ($start_p!==false) ? $start_p : 0;

        echo 'Start at item '.$this->start. ' limit '. $this->limit . "\n";
        if ( $this->useflush == 'yes' && function_exists( 'flush' ) ){
            flush();
        }

        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $starttime = $mtime;

        @set_time_limit(0);

        // For stats
        $statdata = get_option( 'plugin_warm_cache_statdata' );
        if ( !isset( $statdata ) || !is_array( $statdata ) ){
            add_option( 'plugin_warm_cache_statdata', array(), NULL, 'no' );
        }

        $this->newvalue = array();
        $this->totalcount = 0;
        $this->hits = 0;
        $this->index = 0;
        $this->newvalue['url'] = $sitemap_url;
        $this->newvalue['time_start'] = time();
        $this->newvalue['pages'] = array();

        // GOGOGO!
        $this->mp_process_sitemap( $sitemap_url  );

        echo "\n\nTotal hits was ".$this->hits.", total count was ".$this->totalcount."\n\n";
        // Give it some time to post-process
        set_transient( $this->pid_lock, 'Busy', 10 );

        // check to see if we managed to process all URLs in the sitemaps
        if ( ( ( $this->hits == $this->totalcount ) && ($this->totalcount <= $this->limit) ) || ( !$this->hits && $this->totalcount ) || ( $this->start + $this->hits >= $this->totalcount ) ) {
            $newstart = 0;
        } else {
            $newstart = $this->start + $this->limit;
        }
        echo "<br/>Updating to start (next time) at : " . $newstart . "\n";
        update_option( 'plugin_warm_cache_start', $newstart );

        if ( !defined( 'MP_WARM_CACHE_NO_LOGGING_AT_ALL' ) ) {

            $mtime = microtime();
            $mtime = explode(" ", $mtime);
            $mtime = $mtime[1] + $mtime[0];
            $endtime = $mtime;
            $totaltime = ( $endtime - $starttime );
            $cnt = count( $this->newvalue['pages'] );
            $returnstring = '<br/><br/>Crawled '.$cnt. ' pages in ' .$totaltime. ' seconds.';

            $post = array ();
            $post['post_title'] = date('l jS F Y h:i:s A', $starttime);
            $post['post_type'] = 'warmcache';
            $post['post_content'] = $this->newvalue['url']."\n<br/>".$returnstring."\n<br/>".implode("\n<br/>", $this->newvalue['pages']);
            $post['post_status'] = 'publish';
            $post['post_author'] = 0; // FIXME? author

            // GOGOGO
            try {

                if( !function_exists('is_user_logged_in' ) ) {
                    require_once( ABSPATH . "wp-includes/pluggable.php" );
                }

                $this_page_id = wp_insert_post($post);
                if ( $this_page_id ) {
                    add_post_meta($this_page_id, 'mytime', $totaltime);
                    add_post_meta($this_page_id, 'mypages', $cnt);
                    add_post_meta($this_page_id, 'totalpages', $this->totalcount);
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }

            // Cleanup, delete old data
            $period_php = '180 minutes';
            $myposts = get_posts( 'post_type=warmcache&numberposts=100&order=ASC&orderby=post_date' );

            $now = strtotime( 'now' );
            foreach ( $myposts AS $post) {
                $post_date_plus_visibleperiod = strtotime( $post->post_date . " +" . $period_php );
                if ( $post_date_plus_visibleperiod < $now ) {
                    wp_delete_post( $post->ID, false );
                }
            }
        }

        echo $returnstring;
        echo '<br><br><strong>Done!</strong>\n';

        if ( $this->useflush == 'yes' && function_exists( 'flush' ) ){
            flush(); // prevent timeout from the loadbalancer
        }


        delete_transient( $this->pid_lock );
        die();
    }

    private function mp_process_sitemap( $sitemap_url, $is_sub = false ) {
        if ( substr_count( $sitemap_url, 'warmcache-sitemap.xml' ) > 0 || substr_count( $sitemap_url, 'warmcache' ) > 0) {
            // No need to crawl our own post type .. bail
            return;
        }
        $xmldata = wp_remote_retrieve_body( wp_remote_get( $sitemap_url ) );
        $xml = simplexml_load_string( $xmldata );
        if ( $xml === false ) {
            return;
        }

        $cnt = count( $xml->url );
        if ( $cnt > 0 ) {
            $this->totalcount += $cnt;

            if ( $this->hits >= $this->limit ) {
                return;
            }

            if ( ( $this->start > $this->index ) && ($this->start > $this->index + $cnt ) ) {
                $this->index += $cnt;
                return;
            }

            for ($i = 0; $i < $cnt; $i++) {
                $this->index++;

                if ( $this->index > $this->start ) {

                    $page = (string)$xml->url[$i]->loc;
                    echo '<br/>Busy with: ' . $page . "\n";
                    if ( $this->useflush == 'yes' && function_exists( 'flush' ) ) {
                        flush(); // prevent timeout from the loadbalancer
                    }

                    set_transient( $this->pid_lock, 'Busy', MINUTE_IN_SECONDS );

                    $this->newvalue['pages'][] = $page;

                    $this->do_crawl_multi( array( $page ) );

                    $this->hits++;
                    if ( $this->hits >= $this->limit ) {
                        return;
                    }

                }
            }
        } else {
            // Sub sitemap?
            $cnt = count( $xml->sitemap );
            if ( $cnt > 0 ) {
                for( $i = 0;$i < $cnt; $i++ ){
                    $sub_sitemap_url = (string)$xml->sitemap[$i]->loc;
                    echo "<br/>Start with submap: " . $sub_sitemap_url . "\n";
                    $this->mp_process_sitemap( $sub_sitemap_url, true );
                }
            }
        }
    }
}
