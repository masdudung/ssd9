<?php

/*
    Plugin Name: wt9-testimonial-api
    Plugin URI: http://jadipesan.com/
    Description: custom wordpress api.
    Version: 1.0
    Author: Moch Mufiddin
    Author URI: http://jadipesan.com/
    License: GPLv2

    "url": "http://localhost/ssd6/",
    "namespaces": [
        "wt9-testimonial-api/v1",
    ]
*/

class wt9_testimonial_api {
    
    private $CPT_name   = 'wt9-testimonial';
    private $CPT_labels = array(
        'name'               => 'Testimonial',
        'singular_name'      => 'Testimonial',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Testimonial',
        'edit_item'          => 'Edit Testimonial',
        'new_item'           => 'New Testimonial',
        'all_items'          => 'List Testimonials',
        'view_item'          => 'View Product',
        'search_items'       => 'Search Testimonial',
        'not_found'          => 'No Testimonial found',
        'not_found_in_trash' => 'No Testimonial found in the Trash', 
        'parent_item_colon'  => '',
        'menu_name'          => 'Testimonials'
    );

    private $CPT_args = array(
        'description'   => 'Holds our Testimonial',
        'public'        => true,
        'menu_position' => 5,
        'supports'      => array('editor'),
        'has_archive'   => true,
    );

    function __construct()
    {
        # Hooking up our function to theme setup
        add_action( 'init', [$this, 'create_posttype_wt9_testimonial'] );

        # hook metabox
        add_action('add_meta_boxes', [$this, 'testimonial_native_metabox']);
        add_action('save_post', [$this, 'testimonial_native_metabox_save']);

        #register api endpoint
        add_action( 'rest_api_init', [$this, 'register_api_endpoint'] );
    }

    function register_api_endpoint()
    {
        
        register_rest_route( 'masdudung/v1', '/testimonials', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_testimonials'],
        ) );

        register_rest_route( 'masdudung/v1', '/testimonials/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_testimonials_detail'],
        ) );

        register_rest_route( 'masdudung/v1', '/testimonials', array(
            'methods' => 'POST',
            'callback' => [$this, 'post_testimonials'],
        ) );

        register_rest_route( 'masdudung/v1', '/testimonials/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_testimonials'],
        ) );

        register_rest_route( 'masdudung/v1', '/testimonials/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_testimonials'],
        ) );
    }

    function get_testimonials(WP_REST_Request $request)
    {
        #get param
        $page = $request->get_param( 'page' );
        if(!$page) $page = 1;

        $per_page = $request->get_param( 'per_page' );
        if(!$per_page) $per_page = 5;
        
        $args = array(
            'post_type' => $this->CPT_name,
            'posts_per_page' => $per_page,
            'paged' => $page
        );

        $data = array();
        $the_query = new WP_Query( $args );
        if ( $the_query->have_posts() ) 
        {
            while ( $the_query->have_posts() ) {
                $the_query->the_post();

                $temp_data = array(
                    'post_id' => get_the_ID(),
                    'content' =>  get_the_content()
                );
                $data[] = $temp_data;
            }
        } else {
        }
        wp_reset_postdata();

        $this->send_message('testimonial', $data );
    }

    function get_testimonials_detail(WP_REST_Request $request)
    {
        $post_id = $request->get_param('id');
        if($post_id==0)
        {
            $this->send_message('testimonial detail', null );
        }

        $data = array();
        $the_query = new WP_Query( array( 'post_type' => $this->CPT_name, 'p' => $post_id ) );
        if ( $the_query->have_posts() ) 
        {
            while ($the_query->have_posts()) : $the_query->the_post();
                $ID = get_the_ID();
                $temp_data = array(
                    'post_id' => $ID,
                    // 'title' => get_the_title(),
                    'content' =>  get_the_content(),
                    'author' => get_post_meta( $ID, "wt9-author", true),
                    'date' => get_post_meta( $ID, "wt9-date", true),
                    'rate' => get_post_meta( $ID, "wt9-rate", true)
                );
                $data[] = $temp_data;
            endwhile;
        } else {
        }
        wp_reset_postdata();
        
        $this->send_message('testimonial detail', $data );
    }

    function post_testimonials(WP_REST_Request $request)
    {
        $content = ($request->get_param('content') ? $request->get_param('content') : '');
        $author = ($request->get_param('author') ? $request->get_param('author') : '');
        $date = ($request->get_param('date') ? $request->get_param('date') : date("Y-m-d"));
        $rate = ($request->get_param('rate') ? $request->get_param('rate') : '1');

        $id = wp_insert_post(
            array(
                'post_title' => 'Auto Draft',
                'post_type'=> $this->CPT_name, 
                'post_content'=> $content,
                'post_status' => 'publish',
                'meta_input' => array(
                    'wt9-author' => $author,
                    'wt9-date' => $date,
                    'wt9-rate' => $rate
                )
            )
        );

        if($id)
        {
            $this->send_message('post testimonial', 'success' );
        }else{
            $this->send_message('post testimonial', 'something wrong, try again' );
        }

    }

    function delete_testimonials(WP_REST_Request $request)
    {
        $post_id = $request->get_param('id');
        
        $id = wp_delete_post($post_id, true);
        if($id != null)
        {
            $this->send_message('delete testimonial', 'success' );
        }else{
            $this->send_message('delete testimonial', 'something wrong, try again' );
        }
    }

    function update_testimonials(WP_REST_Request $request)
    {
        
        $post_id = $request->get_param('id');
        if($post_id==0)
        {
            $this->send_message('update testimonial', 'post not found' );
        }

        $data = array();
        $the_query = new WP_Query( array( 'post_type' => $this->CPT_name, 'p' => $post_id ) );
        if ( $the_query->have_posts() )
        {
            $param = $request->get_json_params();

            $content = ( isset($param['content']) ? $param['content'] : '');
            $author = ( isset($param['author']) ? $param['author'] : '');
            $date = ( isset($param['date']) ? $param['date'] : date("Y-m-d"));
            $rate = ( isset($param['rate']) ? $param['rate'] : '1');

            $ID = wp_update_post(
                array(
                    'ID' => $post_id, 
                    'post_content'=> $content,
                    'meta_input' => array(
                        'wt9-author' => $author,
                        'wt9-date' => $date,
                        'wt9-rate' => $rate
                    )
                ), true
            );

            if($ID == $post_id)
            {
                $this->send_message('update testimonial', 'success' );
            }else{
                $this->send_message('update testimonial', 'something wrong, try again' );
            }

        }else{
            $this->send_message('update testimonial', 'post not found' );
        }
    }

    function send_message($code=null, $message=null, $status=null)
    {
        $msg = array(
            'code' => 'success',
            'message' => 'ok',
            'data' => array(
                'status' => 200
            )
        );
        
        if($code) $msg['code'] = $code;
        if($message) $msg['message'] = $message;
        if($status) $status['data']['status'] = $status;

        echo json_encode($msg);
        exit;
    }

    function create_posttype_wt9_testimonial() 
    {
        $this->CPT_args['labels'] = $this->CPT_labels;
        register_post_type($this->CPT_name, $this->CPT_args);
    }

    function testimonial_native_metabox()
    {
        add_meta_box(
            'testimonial_metabox', // Unique ID
            'Testimonial Metadata', // Box title
            [$this, 'testimonial_native_metabox_view'], // Content callback, must be of type callable
            $this->CPT_name // Post type
        );
        
    }

    function testimonial_native_metabox_view($post)
    {
        $author = get_post_meta($post->ID, "wt9-author", true);
        $date = get_post_meta($post->ID, "wt9-date", true);
        $rate = get_post_meta($post->ID, "wt9-rate", true);
        if(!$rate)
        {
            $rate = 1;
        }

        // debug(array($author, $date, $rate));
        ?>
        <table>
            <tbody>
                <tr>
                    <td>
                        <p>author</p>
                    </td>
                    <td>
                        <input type="text" name="wt9-author" value="<?php echo $author; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <p>Date</p>
                    </td>
                    <td>
                        <input type="date" name="wt9-date" value="<?php echo $date; ?>">
                    </td>
                </tr>
                <tr>
                    <td>
                        <p>Rate</p>
                    </td>
                    <td>
                        <input type="number" min="1" max="5" name="wt9-rate" value="<?php echo $rate; ?>">
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    function testimonial_native_metabox_save($post_id)
    {
        
        # save textbox
        $fields = array(
            'wt9-author', 'wt9-date', 'wt9-rate'
        );

        foreach ($fields as $field) {
            # code...
            if (array_key_exists($field, $_POST)) {
                update_post_meta(
                    $post_id,
                    $field,
                    $_POST[$field]
                );
            }
        }
    }


}

$plugin = new wt9_testimonial_api();

function debug($a){echo "<pre>";var_dump($a);echo "</pre>";}