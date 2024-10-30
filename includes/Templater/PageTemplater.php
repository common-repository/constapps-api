<?php

class PageTemplater
{
    private static $instance;
    protected $templates;

    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new PageTemplater();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->templates = array();
        if (version_compare(floatval(get_bloginfo('version')), '4.7', '<')) {
            add_filter(
                'page_attributes_dropdown_pages_args',
                array($this, 'register_project_templates')
            );
        } else {
            add_filter(
                'theme_page_templates', array($this, 'add_new_template')
            );
        }
        add_filter(
            'wp_insert_post_data',
            array($this, 'register_project_templates')
        );
        add_filter(
            'template_include',
            array($this, 'view_project_template')
        );
        $this->templates = ['ConstappsTemplate.php' => 'Constapps Check Out'];
        add_action("plugins_loaded",array($this,'create_checkout_page'));
    }

    public function add_new_template($posts_templates)
    {
        $posts_templates = array_merge($posts_templates, $this->templates);
        return $posts_templates;
    }

    public function register_project_templates($atts)
    {
        $cache_key = 'page_templates-' . hash('sha256', get_theme_root() . '/' . get_stylesheet());
        $templates = wp_get_theme()->get_page_templates();
        if (empty($templates)) {
            $templates = array();
        }
        wp_cache_delete($cache_key, 'themes');
        $templates = array_merge($templates, $this->templates);
        wp_cache_add($cache_key, $templates, 'themes', 1800);
        return $atts;
    }

    public function view_project_template($template)
    {
        if (is_search()) {
            return $template;
        }

        global $post;
        if (!$post) {
            return $template;
        }
        if (!isset($this->templates[get_post_meta(
                $post->ID, '_wp_page_template', true
            )])) {
            return $template;
        }
        $file = plugin_dir_path(__FILE__) . get_post_meta(
                $post->ID, '_wp_page_template', true
            );
        if (WP_Filesystem_Base()->is_file($file)) {
            return $file;
        } else {
            return $file;
        }
        return $template;
    }

    public function create_checkout_page(){
        global $wpdb;
        $table_insert = $wpdb->prefix . "posts";
        $join_table = $wpdb->prefix . "postmeta";
        // $sql = ;
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM %s AS p INNER JOIN %s AS meta ON p.ID = meta.post_id WHERE post_type = '%s' AND post_status='%s' AND (meta_value = '%s' OR meta_key = '%s')", $table_insert, $join_table, 'page', 'publish', 'constapps-api-template.php', '_constapps_checkout_template'),OBJECT);
        if(empty($result)){
            $pageguid = site_url() . "/constapps-api";
            // Insert the post into the database
            $wpdb->insert(
                $table_insert,
                array(
                    'post_title' => 'Constapps Check Out',
                    'post_name' => 'constapps-api',
                    'guid' => $pageguid,
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'ping_status' => 'closed',
                    'comment_status' => 'closed',
                    'menu_order' => 0
                ),
                array(
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%d'
                )
            );
            $pageid = $wpdb->insert_id;
            update_post_meta($pageid,'_constapps_checkout_template',1);
            update_post_meta($pageid,'_wp_page_template','ConstappsTemplate.php');
            update_option('constapps_checkout_page_id',$pageid);
        }
    }
}
