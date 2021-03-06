<?php


// exit
defined( 'ABSPATH' ) OR exit;


/**
 * Cache_Enabler
 *
 * @since  1.0.0
 */

final class Cache_Enabler {


    /**
     * plugin options
     *
     * @since  1.0.0
     * @var    array
     */

    public static $options;


    /**
     * disk cache object
     *
     * @since  1.0.0
     * @var    object
     */

    private static $disk;


    /**
     * minify default settings
     *
     * @since  1.0.0
     * @var    integer
     */

    const MINIFY_DISABLED  = 0;
    const MINIFY_HTML_ONLY = 1;
    const MINIFY_HTML_JS   = 2;


    /**
     * constructor wrapper
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function instance() {

        new self();
    }


    /**
     * constructor
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param   void
     * @return  void
     */

    public function __construct() {

        // set default vars
        self::_set_default_vars();

        // register publish hook
        add_action(
            'init',
            array(
                __CLASS__,
                'register_publish_hooks',
            ),
            99
        );

        // clear cache hooks
        add_action(
            'ce_clear_post_cache',
            array(
                __CLASS__,
                'clear_page_cache_by_post_id',
            )
        );
        add_action(
            'ce_clear_cache',
            array(
                __CLASS__,
                'clear_total_cache',
            )
        );
        add_action(
            '_core_updated_successfully',
            array(
                __CLASS__,
                'clear_total_cache',
            )
        );
        add_action(
            'switch_theme',
            array(
                __CLASS__,
                'clear_total_cache',
            )
        );
        add_action(
            'wp_trash_post',
            function( $post_id ) {
                if ( get_post_status( $post_id ) === 'publish' ) {
                    self::clear_total_cache();
                }
                self::check_future_posts();
            }
        );
        add_action(
            'save_post',
            array(
                __CLASS__,
                'check_future_posts',
            )
        );
        add_action(
            'autoptimize_action_cachepurged',
            array(
                __CLASS__,
                'clear_total_cache',
            )
        );
        add_action(
            'upgrader_process_complete',
            array(
                __CLASS__,
                'on_upgrade_hook',
            )
        );

        // act on WooCommerce actions
        add_action(
            'woocommerce_product_set_stock',
            array(
                __CLASS__,
                'woocommerce_product_set_stock',
            )
        );
        add_action(
            'woocommerce_product_set_stock_status',
            array(
                __CLASS__,
                'woocommerce_product_set_stock_status',
            )
        );
        add_action(
            'woocommerce_variation_set_stock',
            array(
                __CLASS__,
                'woocommerce_product_set_stock',
            )
        );
        add_action(
            'woocommerce_variation_set_stock_status',
            array(
                __CLASS__,
                'woocommerce_product_set_stock_status',
            )
        );

        // add admin clear link
        add_action(
            'admin_bar_menu',
            array(
                __CLASS__,
                'add_admin_links',
            ),
            90
        );
        add_action(
            'init',
            array(
                __CLASS__,
                'process_clear_request',
            )
        );
        if ( ! is_admin() ) {
            add_action(
                'admin_bar_menu',
                array(
                    __CLASS__,
                    'register_textdomain',
                )
            );
        }

        // admin
        if ( is_admin() ) {
            add_action(
                'wpmu_new_blog',
                array(
                    __CLASS__,
                    'install_later',
                )
            );
            add_action(
                'delete_blog',
                array(
                    __CLASS__,
                    'uninstall_later',
                )
            );

            add_action(
                'admin_init',
                array(
                    __CLASS__,
                    'register_textdomain',
                )
            );
            add_action(
                'admin_init',
                array(
                    __CLASS__,
                    'register_settings',
                )
            );

            add_action(
                'admin_menu',
                array(
                    __CLASS__,
                    'add_settings_page',
                )
            );
            add_action(
                'admin_enqueue_scripts',
                array(
                    __CLASS__,
                    'add_admin_resources',
                )
            );

            add_action(
                'transition_comment_status',
                array(
                    __CLASS__,
                    'change_comment',
                ),
                10,
                3
            );
            add_action(
                'comment_post',
                array(
                    __CLASS__,
                    'comment_post',
                ),
                99,
                2
            );
            add_action(
                'edit_comment',
                array(
                    __CLASS__,
                    'edit_comment',
                )
            );

            add_filter(
                'dashboard_glance_items',
                array(
                    __CLASS__,
                    'add_dashboard_count',
                )
            );
            add_action(
                'post_submitbox_misc_actions',
                array(
                    __CLASS__,
                    'add_clear_dropdown',
                )
            );
            add_filter(
                'plugin_row_meta',
                array(
                    __CLASS__,
                    'row_meta',
                ),
                10,
                2
            );
            add_filter(
                'plugin_action_links_' . CE_BASE,
                array(
                    __CLASS__,
                    'action_links',
                )
            );

            // warnings and notices
            add_action(
                'admin_notices',
                array(
                    __CLASS__,
                    'warning_is_permalink',
                )
            );
            add_action(
                'admin_notices',
                array(
                    __CLASS__,
                    'requirements_check',
                )
            );

        // caching
        } else {
            add_action(
                'pre_comment_approved',
                array(
                    __CLASS__,
                    'new_comment',
                ),
                99,
                2
            );

            add_action(
                'template_redirect',
                array(
                    __CLASS__,
                    'handle_cache',
                ),
                0
            );
        }
    }


    /**
     * deactivation hook
     *
     * @since   1.0.0
     * @change  1.1.1
     */

    public static function on_deactivation() {

        self::clear_total_cache();

        if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
            // unset WP_CACHE
            self::_set_wp_cache( false );
        }

        // delete advanced cache file
        unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * activation hook
     *
     * @since   1.0.0
     * @change  1.1.1
     */

    public static function on_activation() {

        // multisite and network
        if ( is_multisite() && ! empty( $_GET['networkwide'] ) ) {
            // blog IDs
            $ids = self::_get_blog_ids();

            // switch to blog
            foreach ( $ids as $id ) {
                switch_to_blog( $id );
                self::_install_backend();
            }

            // restore blog
            restore_current_blog();

        } else {
            self::_install_backend();
        }

        if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
            // set WP_CACHE
            self::_set_wp_cache( true );
        }

        // copy advanced cache file
        copy( CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * upgrade hook actions
     *
     * @since   1.2.3
     * @change  1.2.3
     */

    public static function on_upgrade_hook( $options ) {

        // clear cache if a plugin has been updated
        if ( self::$options['clear_on_upgrade'] ) {
            self::clear_total_cache();
        }

        // check if Cache Enabler has been updated
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' && array_key_exists( 'plugins', $options ) ) {
            foreach ( (array) $options['plugins'] as $each_plugin ) {
                if ( preg_match( '/^cache-enabler\//', $each_plugin ) ) {
                    // updated
                    self::on_upgrade();
                }
            }
        }
    }


    /**
     * upgrade actions
     *
     * @since   1.2.3
     * @change  1.2.3
     */

    public static function on_upgrade() {

        // copy advanced cache file that might have changed
        copy( CE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );
    }


    /**
     * install on multisite setup
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function install_later( $id ) {

        // check if network activated
        if ( ! is_plugin_active_for_network( CE_BASE ) ) {
            return;
        }

        // switch to blog
        switch_to_blog( $id );

        // installation
        self::_install_backend();

        // restore
        restore_current_blog();
    }


    /**
     * installation options
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _install_backend() {

        add_option(
            'cache-enabler',
            array()
        );

        // clear
        self::clear_total_cache();
    }


    /**
     * WP_CACHE installation (advanced cache)
     *
     * @since   1.1.1
     * @change  1.1.1
     */

    private static function _set_wp_cache( $wp_cache_value = true ) {

        $wp_config_file = ABSPATH . 'wp-config.php';

        if ( file_exists( $wp_config_file ) && is_writable( $wp_config_file ) ) {
            // get wp-config.php as array
            $wp_config = file( $wp_config_file );

            if ( $wp_cache_value ) {
                $wp_cache_ce_line = "define('WP_CACHE', true); // Added by Cache Enabler". "\r\n";
            } else {
                $wp_cache_ce_line = '';
            }

            $found_wp_cache = false;

            foreach ( $wp_config as &$line ) {
                if ( preg_match( '/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(.*)\s*\)/', $line ) ) {
                    $line = $wp_cache_ce_line;
                    $found_wp_cache = true;
                    break;
                }
            }

            // add WP_CACHE if not found
            if ( ! $found_wp_cache ) {
                array_shift( $wp_config );
                array_unshift( $wp_config, "<?php\r\n", $wp_cache_ce_line );
            }

            // write wp-config.php file
            $fh = @fopen( $wp_config_file, 'w' );
            foreach( $wp_config as $ln ) {
                @fwrite( $fh, $ln );
            }

            @fclose( $fh );
        }
    }


    /**
     * uninstall per multisite blog
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function on_uninstall() {

        global $wpdb;

        // multisite and network
        if ( is_multisite() && ! empty( $_GET['networkwide'] ) ) {
            // legacy blog
            $old = $wpdb->blogid;

            // blog ID
            $ids = self::_get_blog_ids();

            // uninstall per blog
            foreach ( $ids as $id ) {
                switch_to_blog( $id );
                self::_uninstall_backend();
            }

            // restore
            switch_to_blog( $old );
        } else {
            self::_uninstall_backend();
        }
    }


    /**
     * uninstall for multisite and network
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function uninstall_later( $id ) {

        // check if network activated
        if ( ! is_plugin_active_for_network( CE_BASE ) ) {
            return;
        }

        // switch
        switch_to_blog( $id );

        // uninstall
        self::_uninstall_backend();

        // restore
        restore_current_blog();
    }


    /**
     * uninstall
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _uninstall_backend() {

        // delete options
        delete_option( 'cache-enabler' );

        // clear cache
        self::clear_total_cache();
    }


    /**
     * get blog IDs
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array  blog IDs array
     */

    private static function _get_blog_ids() {

        global $wpdb;

        return $wpdb->get_col("SELECT blog_id FROM `$wpdb->blogs`");
    }


    /**
     * set default vars
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    private static function _set_default_vars() {

        // get options
        self::$options = self::_get_options();

        // disk cache
        if ( Cache_Enabler_Disk::is_permalink() ) {
            self::$disk = new Cache_Enabler_Disk;
        }
    }


    /**
     * get options
     *
     * @since   1.0.0
     * @change  1.3.6
     *
     * @return  array  options array
     */

    private static function _get_options() {

        // decom
        $ce_leg = get_option( 'cache' );
        if ( ! empty( $ce_leg ) ) {
            delete_option( 'cache' );
            add_option(
                'cache-enabler',
                $ce_leg
            );
        }

        // rename
        $options = get_option( 'cache-enabler' );
        // excl_regexp to excl_paths (1.3.6)
        if ( $options['excl_regexp'] ) {
            $options['excl_paths'] = $options['excl_regexp'];
            unset( $options['excl_regexp'] );
            update_option( 'cache-enabler', $options );
        }
        // incl_attributes to incl_parameters (1.3.6)
        if ( $options['incl_attributes'] ) {
            $options['incl_parameters'] = $options['incl_attributes'];
            unset( $options['incl_attributes'] );
            update_option( 'cache-enabler', $options );
        }

        return wp_parse_args(
            get_option( 'cache-enabler' ),
            array(
                'expires'          => 0,
                'clear_on_upgrade' => 0,
                'new_post'         => 0,
                'new_comment'      => 0,
                'compress'         => 0,
                'webp'             => 0,
                'excl_ids'         => '',
                'excl_paths'       => '',
                'excl_cookies'     => '',
                'incl_parameters'  => '',
                'minify_html'      => self::MINIFY_DISABLED,
            )
        );
    }


    /**
     * warning if no custom permlinks
     *
     * @since   1.0.0
     * @change  1.3.6
     *
     * @return  array  options array
     */

    public static function warning_is_permalink() {

        if ( ! Cache_Enabler_Disk::is_permalink() && current_user_can( 'manage_options' ) ) {

            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        esc_html__( 'The %s plugin requires a custom permalink structure to start caching properly. Please enable a custom structure in the %s.', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        sprintf(
                            '<a href="%s">%s</a>',
                            admin_url( 'options-permalink.php' ),
                            esc_html__( 'Permalink Settings', 'cache-enabler' )
                        )
                    )
                )
            );
        }
    }


    /**
     * add action links
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   array  $data  existing links
     * @return  array  $data  appended links
     */

    public static function action_links( $data ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $data;
        }

        return array_merge(
            $data,
            array(
                sprintf(
                    '<a href="%s">%s</a>',
                    add_query_arg(
                        array(
                            'page' => 'cache-enabler',
                        ),
                        admin_url( 'options-general.php' )
                    ),
                    esc_html__( 'Settings' )
                )
            )
        );
    }


    /**
     * Cache Enabler meta links
     *
     * @since   1.0.0
     * @change  1.3.6
     *
     * @param   array   $input  existing links
     * @param   string  $page   page
     * @return  array   $data   appended links
     */

    public static function row_meta( $input, $page ) {

        // check permissions
        if ( $page !== CE_BASE ) {
            return $input;
        }

        return array_merge(
            $input,
            array(
                '<a href="https://www.keycdn.com/support/wordpress-cache-enabler-plugin" target="_blank">Documentation</a>',
            )
        );
    }


    /**
     * add dashboard cache size count
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @param   array  $items  initial array with dashboard items
     * @return  array  $items  merged array with dashboard items
     */

    public static function add_dashboard_count( $items = array() ) {

        // check user role
        if ( ! current_user_can( 'manage_options' ) ) {
            return $items;
        }

        // get cache size
        $size = self::get_cache_size();

        // display items
        $items = array(
            sprintf(
                '<a href="%s" title="%s">%s %s</a>',
                add_query_arg(
                    array(
                        'page' => 'cache-enabler'
                    ),
                    admin_url( 'options-general.php' )
                ),
                esc_html__( 'Disk Cache', 'cache-enabler' ),
                ( empty( $size ) ? esc_html__( 'Empty', 'cache-enabler' ) : size_format( $size ) ),
                esc_html__( 'Cache Size', 'cache-enabler' )
            )
        );

        return $items;
    }


    /**
     * get cache size
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   integer  $size  cache size (bytes)
     */

    public static function get_cache_size() {

        if ( ! $size = get_transient( 'cache_size' ) ) {

            $size = ( is_object( self::$disk ) ) ? (int) self::$disk->cache_size( CE_CACHE_DIR ) : 0;

            // set transient
            set_transient(
                'cache_size',
                $size,
                60 * 15
            );
        }

        return $size;
    }


    /**
     * add admin links
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @hook    mixed
     *
     * @param   object  menu properties
     */

    public static function add_admin_links( $wp_admin_bar ) {

        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // add admin clear link
        $wp_admin_bar->add_menu(
            array(
                'id'     => 'clear-cache',
                'href'   => wp_nonce_url( add_query_arg( '_cache', 'clear' ), '_cache__clear_nonce' ),
                'parent' => 'top-secondary',
                'title'  => '<span class="ab-item">' . esc_html__( 'Clear Cache', 'cache-enabler' ) . '</span>',
                'meta'   => array(
                                'title' => esc_html__( 'Clear Cache', 'cache-enabler' ),
                            ),
            )
        );

        if ( ! is_admin() ) {
            // add admin clear link
            $wp_admin_bar->add_menu(
                array(
                    'id'     => 'clear-url-cache',
                    'href'   => wp_nonce_url( add_query_arg( '_cache', 'clearurl' ), '_cache__clear_nonce' ),
                    'parent' => 'top-secondary',
                    'title'  => '<span class="ab-item">' . esc_html__( 'Clear URL Cache', 'cache-enabler' ) . '</span>',
                    'meta'   => array(
                                    'title' => esc_html__( 'Clear URL Cache', 'cache-enabler' ),
                                ),
                )
            );
        }
    }


    /**
     * process clear request
     *
     * @since   1.0.0
     * @change  1.1.0
     *
     * @param   array  $data  array of metadata
     */

    public static function process_clear_request( $data ) {

        // check if clear request
        if ( empty( $_GET['_cache'] ) || ( $_GET['_cache'] !== 'clear' && $_GET['_cache'] !== 'clearurl' ) ) {
            return;
        }

        // validate nonce
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], '_cache__clear_nonce' ) ) {
            return;
        }

        // check user role
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // load if network activated
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        // set clear URL without query string
        $clear_url = preg_replace( '/\?.*/', '', home_url( add_query_arg( NULL, NULL ) ) );

        // multisite and network setup
        if ( is_multisite() && is_plugin_active_for_network( CE_BASE ) ) {

            if ( is_network_admin() ) {

                // legacy blog
                $legacy = $GLOBALS['wpdb']->blogid;

                // blog IDs
                $ids = self::_get_blog_ids();

                // switch blogs
                foreach ( $ids as $id ) {
                    switch_to_blog( $id );
                    self::clear_page_cache_by_url( home_url() );
                }

                // restore
                switch_to_blog( $legacy );

                // clear notice
                if ( is_admin() ) {
                    add_action(
                        'network_admin_notices',
                        array(
                            __CLASS__,
                            'clear_notice',
                        )
                    );
                }
            } else {
                if ( $_GET['_cache'] === 'clearurl' ) {
                    // clear specific multisite URL cache
                    self::clear_page_cache_by_url( $clear_url );
                } else {
                    // clear specific multisite cache
                    self::clear_page_cache_by_url( home_url() );

                    // clear notice
                    if ( is_admin() ) {
                        add_action(
                            'admin_notices',
                            array(
                                __CLASS__,
                                'clear_notice',
                            )
                        );
                    }
                }
            }
        } else {
            if ( $_GET['_cache'] === 'clearurl' ) {
                // clear URL cache
                self::clear_page_cache_by_url( $clear_url );
            } else {
                // clear cache
                self::clear_total_cache();

                // clear notice
                if ( is_admin() ) {
                    add_action(
                        'admin_notices',
                        array(
                            __CLASS__,
                            'clear_notice',
                        )
                    );
                }
            }
        }

        if ( ! is_admin() ) {
            wp_safe_redirect(
                remove_query_arg(
                    '_cache',
                    wp_get_referer()
                )
            );

            exit();
        }
    }


    /**
     * notification after clear cache
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @hook    mixed  user_can_clear_cache
     */

    public static function clear_notice() {

        // check if admin
        if ( ! is_admin_bar_showing() || ! apply_filters( 'user_can_clear_cache', current_user_can( 'manage_options' ) ) ) {
            return false;
        }

        echo sprintf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__( 'The cache has been cleared.', 'cache-enabler' )
        );
    }


    /**
     * clear cache if post comment
     *
     * @since   1.2.0
     * @change  1.2.0
     *
     * @param   integer  $id        ID of the comment
     * @param   mixed    $approved  approval status
     */

    public static function comment_post( $id, $approved ) {

        // check if comment is approved
        if ( $approved === 1 ) {
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id(
                    get_comment( $id )->comment_post_ID
                );
            }
        }
    }


    /**
     * clear cache if edit comment
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   integer  $id  ID of the comment
     */

    public static function edit_comment( $id ) {

        // clear complete cache if option enabled
        if ( self::$options['new_comment'] ) {
            self::clear_total_cache();
        } else {
            self::clear_page_cache_by_post_id(
                get_comment( $id )->comment_post_ID
            );
        }
    }


    /**
     * clear cache if new comment
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   mixed  $approved  approval status
     * @param   array  $comment
     * @return  mixed  $approved  approval status
     */

    public static function new_comment( $approved, $comment ) {

        // check if comment is approved
        if ( $approved === 1 ) {
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id( $comment['comment_post_ID'] );
            }
        }

        return $approved;
    }


    /**
     * clear cache if comment changes
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $after_status
     * @param   string  $before_status
     * @param   object  $comment
     */

    public static function change_comment( $after_status, $before_status, $comment ) {

        // check if changes occured
        if ( $after_status !== $before_status ) {
            if ( self::$options['new_comment'] ) {
                self::clear_total_cache();
            } else {
                self::clear_page_cache_by_post_id(
                    $comment->comment_post_ID
                );
            }
        }
    }


    /**
     * register publish hooks for custom post types
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param   void
     * @return  void
     */

    public static function register_publish_hooks() {

        // get post types
        $post_types = get_post_types(
            array(
                'public' => true,
            )
        );

        // check if empty
        if ( empty( $post_types ) ) {
            return;
        }

        // post type actions
        foreach ( $post_types as $post_type ) {
            add_action(
                'publish_' . $post_type,
                array(
                    __CLASS__,
                    'publish_post_types',
                ),
                10,
                2
            );
            add_action(
                'publish_future_' . $post_type,
                function( $post_id ) {
                    // clear complete cache if option enabled
                    if ( self::$options['new_post'] ) {
                        self::clear_total_cache();
                    } else {
                        self::clear_home_page_cache();
                    }
                }
            );
        }
    }


    /**
     * delete post type cache on post updates
     *
     * @since   1.0.0
     * @change  1.0.7
     *
     * @param   integer  $post_id  post ID
     */

    public static function publish_post_types( $post_id, $post ) {

        // check if post ID or post is empty
        if ( empty( $post_id ) || empty( $post ) ) {
            return;
        }

        // check post status
        if ( ! in_array( $post->post_status, array( 'publish', 'future' ) ) ) {
            return;
        }

        // clear cache if clean post on update
        if ( ! isset( $_POST['_clear_post_cache_on_update'] ) ) {

            // clear complete cache if option enabled
            if ( self::$options['new_post'] ) {
                return self::clear_total_cache();
            } else {
                return self::clear_home_page_cache();
            }

        }

        // validate nonce
        if ( ! isset( $_POST['_cache__status_nonce_' . $post_id] ) || ! wp_verify_nonce( $_POST['_cache__status_nonce_' . $post_id], CE_BASE ) ) {
            return;
        }

        // validate user role
        if ( ! current_user_can( 'publish_posts' ) ) {
            return;
        }

        // save as integer
        $clear_post_cache = (int) $_POST['_clear_post_cache_on_update'];

        // save user metadata
        update_user_meta(
            get_current_user_id(),
            '_clear_post_cache_on_update',
            $clear_post_cache
        );

        // clear complete cache or specific post
        if ( $clear_post_cache ) {
            self::clear_page_cache_by_post_id( $post_id );
        } else {
            self::clear_total_cache();
        }
    }


    /**
     * clear page cache by post ID
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   integer  $post_id  post ID
     */

    public static function clear_page_cache_by_post_id( $post_id ) {

        // is int
        if ( ! $post_id = (int) $post_id ) {
            return;
        }

        // clear cache by URL
        self::clear_page_cache_by_url(
            get_permalink( $post_id )
        );
    }


    /**
     * clear page cache by URL
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param  string  $url  URL of a page
     */

    public static function clear_page_cache_by_url( $url ) {

        // validate string
        if ( ! $url = (string) $url ) {
            return;
        }

        call_user_func(
            array(
                self::$disk,
                'delete_asset',
            ),
            $url
        );

        // clear cache by URL post hook
        do_action( 'ce_action_cache_by_url_cleared' );
    }


    /**
     * clear home page cache
     *
     * @since   1.0.7
     * @change  1.2.3
     *
     */

    public static function clear_home_page_cache() {

        call_user_func(
            array(
                self::$disk,
                'clear_home',
            )
        );

        // clear home page cache post hook
        do_action( 'ce_action_home_page_cache_cleared' );
    }


    /**
     * check if index.php
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if index.php
     */

    private static function _is_index() {

        return strtolower( basename( $_SERVER['SCRIPT_NAME'] ) ) !== 'index.php';
    }


    /**
     * check if mobile
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  boolean  true if mobile
     */

    private static function _is_mobile() {

        return ( strpos( TEMPLATEPATH, 'wptouch' ) || strpos( TEMPLATEPATH, 'carrington' ) || strpos( TEMPLATEPATH, 'jetpack' ) || strpos( TEMPLATEPATH, 'handheld' ) );
    }


    /**
     * check if logged in
     *
     * @since   1.0.0
     * @change  1.3.6
     *
     * @return  boolean  true if logged in
     */

    private static function _is_logged_in() {

        // check if logged in
        if ( is_user_logged_in() ) {
            return true;
        }
    }


    /**
     * check if there are post to be published in the future
     *
     * @since   1.2.3
     * @change  1.2.3
     *
     * @return  void
     *
     */

    public static function check_future_posts() {

        $future_posts = new WP_Query( array(
            'post_status' => array( 'future' ),
        ) );

        if ( $future_posts->have_posts() ) {
            $post_dates = array_column( $future_posts->get_posts(), 'post_date' );
            sort( $post_dates );
            Cache_Enabler_Disk::record_advcache_settings( array(
                'cache_timeout' => strtotime( $post_dates[0] )
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'cache_timeout' ) );
        }
    }


    /**
     * check to bypass the cache
     *
     * @since   1.0.0
     * @change  1.3.6
     *
     * @return  boolean  true if exception
     *
     * @hook    boolean  bypass cache
     */

    private static function _bypass_cache() {

        // bypass cache hook
        if ( apply_filters( 'bypass_cache', false ) ) {
            return true;
        }

        // check if request method is GET
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }

        // check if conditional tags
        if ( self::_is_index() || is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) {
            return true;
        }

        // check DONOTCACHEPAGE
        if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
            return true;
        }

        // check if logged in
        if ( self::_is_logged_in() ) {
            return true;
        }

        // check mobile request
        if ( self::_is_mobile() ) {
            return true;
        }

        // Cache Enabler options
        $options = self::$options;

        // if post ID excluded
        if ( $options['excl_ids'] && is_singular() ) {
            if ( in_array( $GLOBALS['wp_query']->get_queried_object_id(), (array) explode( ',', $options['excl_ids'] ) ) ) {
                return true;
            }
        }

        // if page path excluded
        if ( ! empty( $options['excl_paths'] ) ) {
            $url_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

            if ( preg_match( $options['excl_paths'], $url_path ) ) {
                return true;
            }
        }

        // check cookies
        if ( ! empty( $_COOKIE ) ) {
            // set regex matching cookies that should cause the cache to be bypassed
            if ( ! empty( $options['excl_cookies'] ) ) {
                $cookies_regex = $options['excl_cookies'];
            } else {
                $cookies_regex = '/^(wp-postpass|wordpress_logged_in|comment_author)_/';
            }
            // bypass the cache if an excluded cookie is found
            foreach ( $_COOKIE as $key => $value) {
                if ( preg_match( $cookies_regex, $key ) ) {
                    return true;
                }
            }
        }

        // check URL query parameters
        if ( ! empty( $_GET ) ) {
            // set regex matching URL query parameters that should not cause the cache to be bypassed
            if ( ! empty( $options['incl_parameters'] ) ) {
                $parameters_regex = $options['incl_parameters'];
            } else {
                $parameters_regex = '/^fbclid|utm_(source|medium|campaign|term|content)$/';
            }
            // bypass the cache if no included URL query parameters are found
            if ( sizeof( preg_grep( $parameters_regex, array_keys( $_GET ), PREG_GREP_INVERT ) ) > 0 ) {
                return true;
            }
        }

        return false;
    }


    /**
     * minify HTML
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $data  minify request data
     * @return  string  $data  minify response data
     *
     * @hook    array   cache_minify_ignore_tags
     */

    private static function _minify_cache( $data ) {

        // check if disabled
        if ( ! self::$options['minify_html'] ) {
            return $data;
        }

        // HTML character limit
        if ( strlen( $data ) > 700000) {
            return $data;
        }

        // HTML tags to ignore
        $ignore_tags = (array) apply_filters(
            'cache_minify_ignore_tags',
            array(
                'textarea',
                'pre',
            )
        );

        // ignore JS if selected
        if ( self::$options['minify_html'] !== self::MINIFY_HTML_JS ) {
            $ignore_tags = array( 'script' );
        }

        // return of no ignore tags
        if ( ! $ignore_tags ) {
            return $data;
        }

        // stringify
        $ignore_regex = implode( '|', $ignore_tags );

        // regex minification
        $cleaned = preg_replace(
            array(
                '/<!--[^\[><](.*?)-->/s',
                '#(?ix)(?>[^\S ]\s*|\s{2,})(?=(?:(?:[^<]++|<(?!/?(?:' . $ignore_regex . ')\b))*+)(?:<(?>' . $ignore_regex . ')\b|\z))#',
            ),
            array(
                '',
                ' ',
            ),
            $data
        );

        // something went wrong
        if ( strlen( $cleaned ) <= 1 ) {
            return $data;
        }

        return $cleaned;
    }


    /**
     * clear complete cache
     *
     * @since   1.0.0
     * @change  1.2.3
     */

    public static function clear_total_cache() {

        // update advanced cache file
        self::on_upgrade();

        // clear disk cache
        Cache_Enabler_Disk::clear_cache();

        // delete transient
        delete_transient( 'cache_size' );

        // clear cache post hook
        do_action( 'ce_action_cache_cleared' );
    }


    /**
     * act on WooCommerce stock changes
     *
     * @since   1.3.0
     * @change  1.3.0
     */

    public static function woocommerce_product_set_stock( $product ) {

        self::woocommerce_product_set_stock_status( $product->get_id() );
    }

    public static function woocommerce_product_set_stock_status( $product_id ) {

        self::clear_total_cache();
    }


    /**
     * set cache
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @param   string  $data  content of a page
     * @return  string  $data  content of a page
     */

    public static function set_cache( $data ) {

        // check if empty
        if ( empty( $data ) ) {
            return '';
        }

        $data = apply_filters( 'cache_enabler_before_store', $data );

        // store as asset
        call_user_func(
            array(
                self::$disk,
                'store_asset',
            ),
            self::_minify_cache( $data )
        );

        return $data;
    }


    /**
     * handle cache
     *
     * @since   1.0.0
     * @change  1.0.1
     */

    public static function handle_cache() {

        // bypass cache
        if ( self::_bypass_cache() ) {
            return;
        }

        // get asset cache status
        $cached = call_user_func(
            array(
                self::$disk,
                'check_asset',
            )
        );

        // check if cache is empty
        if ( empty( $cached ) ) {
            ob_start( 'Cache_Enabler::set_cache' );
            return;
        }

        // get cache expiry status
        $expired = call_user_func(
            array(
                self::$disk,
                'check_expiry',
            )
        );

        // check if cache has expired
        if ( $expired ) {
            ob_start( 'Cache_Enabler::set_cache' );
            return;
        }

        // check if trailing slash is missing
        if ( self::missing_trailing_slash() ) {
            return;
        }

        // return cached asset
        call_user_func(
            array(
                self::$disk,
                'get_asset',
            )
        );
    }


    /**
     * add clear option dropdown on post publish widget
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_clear_dropdown() {

        // on published post/page only
        if ( empty( $GLOBALS['pagenow'] ) || $GLOBALS['pagenow'] !== 'post.php' || empty( $GLOBALS['post'] ) || ! is_object( $GLOBALS['post'] ) || $GLOBALS['post']->post_status !== 'publish' ) {
            return;
        }

        // check user role
        if ( ! current_user_can( 'publish_posts' ) ) {
            return;
        }

        // validate nonce
        wp_nonce_field( CE_BASE, '_cache__status_nonce_' . $GLOBALS['post']->ID );

        // get current action
        $current_action = (int) get_user_meta(
            get_current_user_id(),
            '_clear_post_cache_on_update',
            true
        );

        // init variables
        $dropdown_options = '';
        $available_options = array(
            esc_html__( 'Completely', 'cache-enabler' ),
            esc_html__( 'Page specific', 'cache-enabler' ),
        );

        // set dropdown options
        foreach ( $available_options as $key => $value ) {
            $dropdown_options .= sprintf(
                '<option value="%1$d" %3$s>%2$s</option>',
                $key,
                $value,
                selected($key, $current_action, false)
            );
        }

        // output dropdown
        echo sprintf(
            '<div class="misc-pub-section" style="border-top:1px solid #eee">
                <label for="cache_action">
                    %1$s: <span id="output-cache-action">%2$s</span>
                </label>
                <a href="#" class="edit-cache-action hide-if-no-js">%3$s</a>

                <div class="hide-if-js">
                    <select name="_clear_post_cache_on_update" id="cache_action">
                        %4$s
                    </select>

                    <a href="#" class="save-cache-action hide-if-no-js button">%5$s</a>
                    <a href="#" class="cancel-cache-action hide-if-no-js button-cancel">%6$s</a>
                 </div>
            </div>',
            esc_html__( 'Clear cache', 'cache-enabler' ),
            $available_options[ $current_action ],
            esc_html__( 'Edit' ),
            $dropdown_options,
            esc_html__( 'OK' ),
            esc_html__( 'Cancel' )
        );
    }


    /**
     * enqueue scripts
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_admin_resources( $hook ) {

        // hook check
        if ( $hook !== 'index.php' && $hook !== 'post.php' ) {
            return;
        }

        // plugin data
        $plugin_data = get_plugin_data( CE_FILE );

        // enqueue scripts
        switch( $hook ) {

            case 'post.php':
                wp_enqueue_script(
                    'cache-post',
                    plugins_url( 'js/post.js', CE_FILE ),
                    array( 'jquery' ),
                    $plugin_data['Version'],
                    true
                );
                break;

            default:
                break;
        }
    }


    /**
     * add settings page
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function add_settings_page() {

        add_options_page(
            'Cache Enabler',
            'Cache Enabler',
            'manage_options',
            'cache-enabler',
            array(
                __CLASS__,
                'settings_page',
            )
        );
    }


    /**
     * minify caching dropdown
     *
     * @since   1.0.0
     * @change  1.0.0
     *
     * @return  array  cache minification options
     */

    private static function _minify_select() {

        return array(
            self::MINIFY_DISABLED  => esc_html__( 'Disabled', 'cache-enabler' ),
            self::MINIFY_HTML_ONLY => esc_html__( 'HTML', 'cache-enabler' ),
            self::MINIFY_HTML_JS   => esc_html__( 'HTML & Inline JS', 'cache-enabler' ),
        );
    }


    /**
     * check plugin requirements
     *
     * @since   1.1.0
     * @change  1.3.6
     */

    public static function requirements_check() {

        // Cache Enabler options
        $options = self::$options;

        // WordPress version check
        if ( version_compare( $GLOBALS['wp_version'], CE_MIN_WP . 'alpha', '<' ) ) {
            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        esc_html__( 'The %s is optimized for WordPress %s. Please disable the plugin or upgrade your WordPress installation (recommended).', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        CE_MIN_WP
                    )
                )
            );
        }

        // permission check
        if ( file_exists( CE_CACHE_DIR ) && ! is_writable( CE_CACHE_DIR ) ) {
            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        esc_html__( 'The %s plugin requires write permissions %s in %s. Please change the %s.', 'cache-enabler' ),
                        '<strong>Cache Enabler</strong>',
                        '<code>755</code>',
                        '<code>wp-content/cache</code>',
                        sprintf(
                            '<a href="%s" target="_blank"></a>',
                            'https://wordpress.org/support/article/changing-file-permissions/',
                            esc_html__( 'file permissions', 'cache-enabler' )
                        )
                    )
                )
            );
        }

        // autoptimize minification check
        if ( defined( 'AUTOPTIMIZE_PLUGIN_DIR' ) && $options['minify_html'] && get_option( 'autoptimize_html', '' ) !== '' ) {
            show_message(
                sprintf(
                    '<div class="error"><p>%s</p></div>',
                    sprintf(
                        esc_html__( 'The %s plugin is already active. Please disable %s in the %s.', 'cache-enabler' ),
                        '<strong>Autoptimize</strong>',
                        esc_html__( 'Cache Minification', 'cache-enabler' ),
                        sprintf(
                            '<a href="%s">%s</a>',
                            admin_url( 'options-general.php' ) . '?page=cache-enabler',
                            esc_html__( 'Cache Enabler Settings', 'cache-enabler' )
                        )
                    )
                )
            );
        }
    }


    /**
     * register textdomain
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function register_textdomain() {

        load_plugin_textdomain(
            'cache-enabler',
            false,
            'cache-enabler/lang'
        );
    }

    /**
     * missing training slash
     *
     * we only have to really check that in advanced-cache.php
     *
     * @since   1.2.3
     * @change  1.2.3
     *
     * @return  boolean  true if we need to allow redirect, otherwise false
     */

    public static function missing_trailing_slash() {

        if ( ( $permalink_structure = get_option( 'permalink_structure' ) ) &&
            preg_match( '/\/$/', $permalink_structure ) ) {

            // record permalink structure for advanced cache
            Cache_Enabler_Disk::record_advcache_settings( array(
                'permalink_trailing_slash' => true,
            ) );

            if ( ! preg_match( '/\/(|\?.*)$/', $_SERVER['REQUEST_URI'] ) ) {
                return true;
            }
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'permalink_trailing_slash' ) );
        }

        return false;
    }

    /**
     * register settings
     *
     * @since   1.0.0
     * @change  1.0.0
     */

    public static function register_settings() {

        register_setting(
            'cache-enabler',
            'cache-enabler',
            array(
                __CLASS__,
                'validate_settings',
            )
        );
    }


    /**
     * validate regex
     *
     * @since   1.2.3
     * @change  1.2.3
     *
     * @param   string  $re  string containing regex
     * @return  string       string containing regex or empty string if input is invalid
     */

    public static function validate_regex( $re ) {

        if ( $re !== '' ) {
            if ( ! preg_match( '/^\/.*\/$/', $re ) ) {
                $re = '/' . $re . '/';
            }

            if ( @preg_match( $re, NULL ) === false ) {
                return '';
            }

            return sanitize_text_field( $re );
        }

        return '';
    }

    /**
     * validate settings
     *
     * @since   1.0.0
     * @change  1.2.3
     *
     * @param   array  $data  array form data
     * @return  array         array form data valid
     */

    public static function validate_settings( $data ) {

        // check if empty
        if ( empty( $data ) ) {
            return;
        }

        // clear complete cache
        self::clear_total_cache();

        // ignore result but call for settings recording
        self::missing_trailing_slash();

        // cache expiry
        if ( $data['expires'] > 0 ){
            Cache_Enabler_Disk::record_advcache_settings( array(
                'expires' => $data['expires'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'expires' ) );
        }

        // page path cache exclusion
        if ( strlen( $data['excl_paths'] ) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'excl_paths' => $data['excl_paths'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'excl_paths' ) );
        }

        // cookies cache exclusion
        if ( strlen( $data['excl_cookies'] ) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'excl_cookies' => $data['excl_cookies'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'excl_cookies' ) );
        }

        // URL query parameters inclusion
        if ( strlen( $data['incl_parameters'] ) > 0 ) {
            Cache_Enabler_Disk::record_advcache_settings( array(
                'incl_parameters' => $data['incl_parameters'],
            ) );
        } else {
            Cache_Enabler_Disk::delete_advcache_settings( array( 'incl_parameters' ) );
        }

        return array(
            'expires'          => (int) $data['expires'],
            'clear_on_upgrade' => (int) ( ! empty( $data['clear_on_upgrade'] ) ),
            'new_post'         => (int) ( ! empty( $data['new_post'] ) ),
            'new_comment'      => (int) ( ! empty( $data['new_comment'] ) ),
            'webp'             => (int) ( ! empty( $data['webp'] ) ),
            'compress'         => (int) ( ! empty( $data['compress'] ) ),
            'excl_ids'         => (string) sanitize_text_field( @$data['excl_ids'] ),
            'excl_paths'       => (string) self::validate_regex( @$data['excl_paths'] ),
            'excl_cookies'     => (string) self::validate_regex( @$data['excl_cookies'] ),
            'incl_parameters'  => (string) self::validate_regex( @$data['incl_parameters'] ),
            'minify_html'      => (int) $data['minify_html'],
        );
    }


    /**
     * settings page
     *
     * @since   1.0.0
     * @change  1.3.6
     */

    public static function settings_page() {

        // WP_CACHE check
        if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
            show_message(
                sprintf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        esc_html__( '%s is not set in the %s file.', 'cache-enabler' ),
                        "<code>define('WP_CACHE', true);</code>",
                        '<code>wp-config.php</code>'
                    )
                )
            );
        }

        ?>

        <div id="cache-settings" class="wrap">
            <h2>
                <?php esc_html_e( 'Cache Enabler Settings', 'cache-enabler' ); ?>
            </h2>

            <div class="notice notice-info" style="margin-bottom: 35px;">
                <p>
                <?php
                printf(
                    esc_html__( 'Combine %s with Cache Enabler for even better WordPress performance and achieve the next level of caching with a CDN.', 'cache-enabler' ),
                    '<strong><a href="https://www.keycdn.com?utm_source=wp-admin&utm_medium=plugins&utm_campaign=cache-enabler">KeyCDN</a></strong>'
                );
                ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'cache-enabler' ); ?>

                <?php $options = self::_get_options(); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Expiry', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <input name="cache-enabler[expires]" type="number" id="cache_expires" value="<?php echo esc_attr( $options['expires'] ); ?>" class="small-text" /> <?php esc_html_e( 'hours', 'cache-enabler' ); ?>
                            <p class="description"><?php esc_html_e( 'An expiry time of 0 means that the cache never expires.', 'cache-enabler' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Behavior', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_clear_on_upgrade">
                                    <input name="cache-enabler[clear_on_upgrade]" type="checkbox" id="cache_clear_on_upgrade" value="1" <?php checked( '1', $options['clear_on_upgrade'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a plugin has been updated.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_new_post">
                                    <input name="cache-enabler[new_post]" type="checkbox" id="cache_new_post" value="1" <?php checked( '1', $options['new_post'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a new post has been published (instead of only the home page cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_new_comment">
                                    <input name="cache-enabler[new_comment]" type="checkbox" id="cache_new_comment" value="1" <?php checked( '1', $options['new_comment'] ); ?> />
                                    <?php esc_html_e( 'Clear the complete cache if a new comment has been posted (instead of only the page specific cache).', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_compress">
                                    <input name="cache-enabler[compress]" type="checkbox" id="cache_compress" value="1" <?php checked( '1', $options['compress'] ); ?> />
                                    <?php esc_html_e( 'Pre-compression of cached pages. Needs to be disabled if the decoding fails in the web browser.', 'cache-enabler' ); ?>
                                </label>

                                <br />

                                <label for="cache_webp">
                                    <input name="cache-enabler[webp]" type="checkbox" id="cache_webp" value="1" <?php checked( '1', $options['webp'] ); ?> />
                                    <?php printf( esc_html__( 'Create an additional cached version for WebP image support. Convert your images to WebP with %s.', 'cache-enabler' ), '<a href="https://optimus.io" target="_blank">Optimus</a>' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Exclusions', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_excl_ids">
                                    <input name="cache-enabler[excl_ids]" type="text" id="cache_excl_ids" value="<?php echo esc_attr( $options['excl_ids'] ) ?>" class="regular-text" />
                                    <p class="description"><?php printf( esc_html__( 'Post and page IDs separated by a %s that should not be cached.', 'cache-enabler' ), '<code>,</code>' ); ?>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>2,43,65</code></p>
                                    </p>
                                </label>

                                <br />

                                <label for="cache_excl_paths">
                                    <input name="cache-enabler[excl_paths]" type="text" id="cache_excl_paths" value="<?php echo esc_attr( $options['excl_paths'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching page paths that should not be cached.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/(^\/$|\/robot\/$|^\/2018\/.*\/test\/)/</code></p>
                                </label>

                                <br />

                                <label for="cache_excl_cookies">
                                    <input name="cache-enabler[excl_cookies]" type="text" id="cache_excl_cookies" value="<?php echo esc_attr( $options['excl_cookies'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching cookies that should cause the cache to be bypassed.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author|woocommerce_items_in_cart|wp_woocommerce_session)_?/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code>/^(wp-postpass|wordpress_logged_in|comment_author)_/</code></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Inclusions', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="cache_incl_parameters">
                                    <input name="cache-enabler[incl_parameters]" type="text" id="cache_incl_parameters" value="<?php echo esc_attr( $options['incl_parameters'] ) ?>" class="regular-text code" />
                                    <p class="description"><?php esc_html_e( 'A regex matching URL query parameters that should not cause the cache to be bypassed.', 'cache-enabler' ); ?></p>
                                    <p><?php esc_html_e( 'Example:', 'cache-enabler' ); ?> <code>/^fbclid|pk_(source|medium|campaign|kwd|content)$/</code></p>
                                    <p><?php esc_html_e( 'Default if unset:', 'cache-enabler' ); ?> <code>/^fbclid|utm_(source|medium|campaign|term|content)$/</code></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php esc_html_e( 'Cache Minification', 'cache-enabler' ); ?>
                        </th>
                        <td>
                            <label for="cache_minify_html">
                                <select name="cache-enabler[minify_html]" id="cache_minify_html">
                                    <?php foreach ( self::_minify_select() as $key => $value ): ?>
                                        <option value="<?php echo esc_attr( $key ) ?>" <?php selected( $options['minify_html'], $key ); ?>>
                                            <?php echo esc_html( $value ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button( esc_html__( 'Save Changes', 'cache-enabler' ) ); ?>
            </form>
            <p class="description"><?php esc_html_e( 'Saving these settings will completely clear the cache.', 'cache-enabler' ); ?></p>
            <p><?php esc_html_e( 'It is recommended to enable HTTP/2 on your origin server and use a CDN that supports HTTP/2. Avoid domain sharding and concatenation of your assets to benefit from parallelism of HTTP/2.', 'cache-enabler' ); ?></p>
        </div>

        <?php

    }
}
