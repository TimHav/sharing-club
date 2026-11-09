<?php
/*
Plugin Name: Sharing Club
Plugin URI: https://wordpress.org/plugins/sharing-club/
Description: Share books, dvd, tools, toys or any object with your community. Your users can easily lend, borrow and rate items and you know who borrowed what.
Author: Manu Z.
Version: 1.3.1
Author URI: http://netdelight.be/
Text Domain: sharing-club
Domain Path: /languages/
Update URI: false
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function scwp_validate_lending($item){
    $messages = array();
    foreach($item as $n=>$v){
        if($n=='comment_karma' && !preg_match("/^[0-5]?$/", $v) )$messages[] = __('Please enter a rating between 0 and 5 (or leave empty).', 'sharing-club');
        if($n=='comment_content' && !is_string($v))$messages[] = __('Please enter a valid comment.', 'sharing-club');
        if(($n=='comment_date'||$n=='comment_date_gmt') && $v!='00-00-0000'){
            list($yyyy, $mm, $dd) = explode('-', $v);
            if ( !@checkdate($mm,$dd,$yyyy) ) {
                    $messages[] = __('Please enter a valid date.', 'sharing-club');
            }
        }
        if($n=='user_id' && $v==0)$messages[] = __('Please select a user.', 'sharing-club');
        if($n=='comment_post_ID' && $v==0)$messages[] = __('Please select an object.', 'sharing-club');
    }
    
    if (empty($messages)) return true;
    return implode('<br />', $messages);
}

function scwp_generate_select($selector_id, $table, $selected = 0, $label = NULL, $where = NULL, $default_option = NULL, $disabled = NULL) {
    global $wpdb;
    if ($label == NULL) {
        $label = $selector_id;
    }
    $query = "SELECT ID, $label AS label";
    if ($disabled != NULL) {
        $query .= ", $disabled AS disabled";
    }
    $query .= " FROM " . $table;
    if ($where) {
        $query .= ' WHERE ' . $where;
    }
    $query .= " ORDER BY " . ($label ? $label : $selector_id);

    $data = $wpdb->get_results($query);
    echo '<select name="'. $selector_id .'">';
    if (isset($default_option)) {
        echo '<option value="' . $default_option['value'] . '">' . esc_html($default_option['label']) . '</option>';
    }
    foreach ($data as $r) {
        echo '<option value="', $r->ID, '"',
            $selected == $r->ID ? ' selected="selected"' : '',
            $disabled != NULL && $r->disabled ? ' disabled' : '',
            '>', $r->label, '</option>';
    }
    echo '</select>';
}

function scwp_plugin_activate() {
    if ( current_user_can( 'manage_options' ) ) {
        add_option('sharing-club_version', '1.2.2');
    }
}
register_activation_hook( __FILE__, 'scwp_plugin_activate' );

function scwp_send_weekly_lending_digest($activity_date = null) {
    if (empty($activity_date)) {
        $activity_date = current_time('Y-m-d');
    }

    if (!is_string($activity_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activity_date)) {
        return;
    }

    global $wpdb;
    $active_user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT user_id
        FROM {$wpdb->comments}
        WHERE comment_type = 'lending'
          AND user_id > 0
          AND ((comment_approved = 'lent' AND DATE(comment_date) = %s)
            OR (comment_approved = 'returned' AND DATE(comment_date_gmt) = %s))",
        $activity_date,
        $activity_date
    ));

    foreach ($active_user_ids as $user_id) {
        $user = get_userdata((int) $user_id);
        if (!$user || !is_email($user->user_email)) {
            continue;
        }

        $loans = $wpdb->get_results($wpdb->prepare(
            "SELECT post_title AS item_name,
                comment_date_gmt AS return_date_ymd,
                DATE_FORMAT(comment_date_gmt, '%%d-%%m-%%Y') AS return_date
            FROM {$wpdb->comments}
            INNER JOIN {$wpdb->posts} ON ID = comment_post_ID
            WHERE comment_type = 'lending'
              AND comment_approved = 'lent'
              AND user_id = %d
              AND post_type = 'shared_item'
            ORDER BY post_title ASC",
            (int) $user_id
        ));
        if (empty($loans)) {
            continue;
        }

        $rows = '';
        foreach ($loans as $loan) {
            $return_date = $loan->return_date_ymd ? new DateTimeImmutable($loan->return_date_ymd) : null;
            $is_overdue = $return_date && $return_date < new DateTimeImmutable('today');
            $row_style = $is_overdue ? ' style="background-color:#fff4f4; color:#8a3d3d;"' : '';
            $rows .= '<tr' . $row_style . '><td>' . esc_html($loan->item_name) . '</td><td>' . esc_html($loan->return_date) . '</td></tr>';
        }

        $site_name = get_bloginfo('name');
        $subject = __('Your current borrowed items', 'sharing-club') . ' - ' . $site_name;
        $message = '<p>' . esc_html__('Dear lender,', 'sharing-club') . '</p>';
        $message .= '<p>' . esc_html__('Here is your overview of currently lent items:', 'sharing-club') . '</p>';
        $message .= '<table style="border-collapse: collapse; width: 100%;"><thead><tr><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">' . esc_html__('Item', 'sharing-club') . '</th><th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">' . esc_html__('Return date', 'sharing-club') . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        $message .= '<p>' . esc_html__('With kind regards,', 'sharing-club') . '<br>' . esc_html($site_name) . '</p>';
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }

}
add_action('scwp_weekly_lending_digest', 'scwp_send_weekly_lending_digest', 10, 1);

function scwp_load_textdomain() {
    load_plugin_textdomain(
        'sharing-club',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'scwp_load_textdomain' );

function scwp_init() {
    //if ( current_user_can( 'manage_options' ) ) {
        $object = array(
            'label' => __('Objects', 'sharing-club'),
            'labels' => array(
              'name' => __('Sharing Club', 'sharing-club'),
              'singular_name' => __('Objects', 'sharing-club'),
              'all_items' => __('All objects', 'sharing-club'),
              'add_new_item' => __('Add object', 'sharing-club'),
              'edit_item' => __('Edit object', 'sharing-club'),
              'new_item' => __('New object', 'sharing-club'),
              'view_item' => __('View object', 'sharing-club'),
              'search_items' => __('Search object', 'sharing-club'),
              'not_found' => __('Object not found', 'sharing-club'),
              'not_found_in_trash'=> __('Trashed object not found', 'sharing-club'),
              // this is the menu label :
              'add_new' => __('Add object', 'sharing-club'),
              ),
            'menu_icon' => 'dashicons-universal-access-alt',
            'public' => true,
            'publicly_queryable' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'shared_item'),
            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields'
            ),
            // meta capabilities (enable roles customization)
            // see https://wordpress.stackexchange.com/questions/14553/allow-member-to-have-access-to-custom-post-type-only-permission-to-only-edit-th
            'capability_type' => 'shared_item',
            'capabilities' => array(
                'edit_post' => 'edit_shared_item',
                'edit_posts' => 'edit_shared_item',
                'edit_others_posts' => 'edit_others_shared_item',
                'publish_posts' => 'publish_shared_item',
                'read_post' => 'read_shared_item',
                'read_private_posts' => 'read_private_shared_item',
                'delete_post' => 'delete_shared_item',
                'delete_posts' => 'delete_shared_item'
            ),            
        );
    
        function scwp_admin_init(){
            $administrator = get_role( 'administrator' );
            if ( $administrator ) {
                $administrator->add_cap( 'read_shared_item' );
                $administrator->add_cap( 'publish_shared_item' );
                $administrator->add_cap( 'edit_shared_item' );
                $administrator->add_cap( 'edit_others_shared_item' );
                $administrator->add_cap( 'delete_shared_item' );
                $administrator->add_cap( 'delete_others_shared_item' );
                $administrator->add_cap( 'read_private_shared_item' );
                $administrator->add_cap( 'lend_shared_item' );
            }

            $contributor = get_role( 'contributor' );
            if ( $contributor ) {
                $contributor->add_cap( 'read_shared_item' );
                $contributor->add_cap( 'edit_shared_item' );
                $contributor->add_cap( 'edit_others_shared_item' );
                $contributor->add_cap( 'read_private_shared_item' );
                $contributor->add_cap( 'lend_shared_item' );
                $contributor->add_cap( 'publish_shared_item' );
            }
        }

        add_action('admin_init', 'scwp_admin_init');

        register_post_type('shared_item', $object);

        register_taxonomy(
            'shared_item_category',
            'shared_item',
            array(
                'label' => __('Categories'),
                'labels' => array(
                    'name' => __('Categories'),
                    'singular_name' => __('Category'),
                    'all_items' => __('All categories'),
                    'edit_item' => __('Edit category'),
                    'view_item' => __('View category'),
                    'update_item' =>  __('Update category'),
                    'add_new_item' =>  __('Add category'),
                    'new_item_name' =>  __('New category'),
                    'search_items' =>  __('Search categories'),
                    'popular_items' =>  __('Popular categories')
                ),
                'hierarchical' => true,
                'show_admin_column' => true,
            )
        );

        register_taxonomy_for_object_type( 'shared_item_category', 'shared_item' );

        //Ensure the $wp_rewrite global is loaded
        global $wp_rewrite;
        //Call flush_rules() as a method of the $wp_rewrite object
        $wp_rewrite->flush_rules( false );
    //}
}
add_action('init', 'scwp_init');

function scwp_cookies(){
    if (!isset($_REQUEST['comment_date_gmt'])) {
        return;
    }
    $cookie_options = array(
        'expires' => time() + 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Strict'
    );
    setcookie('scwp_return_date', $_REQUEST['comment_date_gmt'], $cookie_options);
}
add_action('admin_init', 'scwp_cookies');

function scwp_display_lending_table(){
    require_once plugin_dir_path( __FILE__ ) . 'admin-lending-table-display.php';
}
function scwp_display_lending_form(){
    if ( current_user_can( 'lend_shared_item' ) ) {
        wp_enqueue_style('jquery-ui-css', plugin_dir_url( __FILE__ ) . 'css/jquery-ui.css');
        wp_enqueue_script( 'scwp-admin', plugin_dir_url( __FILE__ ) . 'js/lending-library-admin.js', array( 'jquery', 'jquery-ui-datepicker' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/lending-library-admin.js' ) );
        require_once plugin_dir_path( __FILE__ ) . 'admin-lending-form-display.php';
    }
}
function scwp_get_next_lender_username() {
    global $wpdb;

    $last_username = $wpdb->get_var(
        "SELECT MAX(CAST(user_login AS UNSIGNED))
        FROM {$wpdb->users}
        WHERE user_login REGEXP '^[0-9]+$'"
    );

    return (string) ( (int) $last_username + 1 );
}
function scwp_redirect_lender_form( $status ) {
    $url = add_query_arg(
        array(
            'page' => 'add_lender',
            'scwp_lender_status' => $status,
        ),
        admin_url( 'edit.php?post_type=shared_item' )
    );
    wp_safe_redirect( $url );
    exit;
}
function scwp_process_add_lender() {
    if ( ! current_user_can( 'lend_shared_item' ) ) {
        wp_die( esc_html__( 'You are not allowed to add a lender.', 'sharing-club' ), '', array( 'response' => 403 ) );
    }

    check_admin_referer( 'scwp_add_lender' );

    $email = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $first_name = isset( $_POST['first_name'] ) && is_string( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name = isset( $_POST['last_name'] ) && is_string( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
    $kids = isset( $_POST['kids'] ) && is_string( $_POST['kids'] ) ? sanitize_text_field( wp_unslash( $_POST['kids'] ) ) : '';
    $phone = isset( $_POST['phone'] ) && is_string( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

    if ( ! is_email( $email ) || '' === $first_name || '' === $last_name || ! in_array( $kids, array( '1', '2' ), true ) ) {
        scwp_redirect_lender_form( 'invalid' );
    }

    $user_id = wp_insert_user(
        array(
            'user_login' => scwp_get_next_lender_username(),
            'user_pass' => wp_generate_password( 24, true, true ),
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'subscriber',
        )
    );

    if ( is_wp_error( $user_id ) ) {
        scwp_redirect_lender_form( 'create_error' );
    }

    $member_fields = array(
        'kids' => $kids,
        'phone' => $phone,
        'date_added' => current_time( 'd-m-Y' ),
    );
    $meta_failed = false;
    foreach ( $member_fields as $field_name => $field_value ) {
        update_user_meta( $user_id, $field_name, $field_value );
        if ( (string) get_user_meta( $user_id, $field_name, true ) !== (string) $field_value ) {
            $meta_failed = true;
        }
    }

    scwp_redirect_lender_form( $meta_failed ? 'created_meta_error' : 'created' );
}
add_action( 'admin_post_scwp_add_lender', 'scwp_process_add_lender' );
function scwp_display_lender_form() {
    if ( ! current_user_can( 'lend_shared_item' ) ) {
        wp_die( esc_html__( 'You are not allowed to add a lender.', 'sharing-club' ), '', array( 'response' => 403 ) );
    }

    $status = isset( $_GET['scwp_lender_status'] ) && is_string( $_GET['scwp_lender_status'] ) ? sanitize_key( wp_unslash( $_GET['scwp_lender_status'] ) ) : '';
    $notices = array(
        'created' => array( 'success', __( 'Lender added successfully.', 'sharing-club' ) ),
        'created_meta_error' => array( 'error', __( 'The lender was created, but some member details could not be saved.', 'sharing-club' ) ),
        'create_error' => array( 'error', __( 'The lender could not be created. The username or e-mail address may already be in use.', 'sharing-club' ) ),
        'invalid' => array( 'error', __( 'Please enter a valid e-mail address, first name, last name, and number of children.', 'sharing-club' ) ),
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Add lender', 'sharing-club' ); ?></h1>
        <?php if ( isset( $notices[ $status ] ) ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notices[ $status ][0] ); ?> is-dismissible">
                <p><?php echo esc_html( $notices[ $status ][1] ); ?></p>
            </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="scwp_add_lender">
            <?php wp_nonce_field( 'scwp_add_lender' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="scwp_lender_username"><?php esc_html_e( 'Member ID', 'sharing-club' ); ?>*</label></th>
                    <td><?php echo esc_attr( scwp_get_next_lender_username() ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="scwp_lender_email"><?php esc_html_e( 'E-mail', 'sharing-club' ); ?>*</label></th>
                    <td><input type="email" id="scwp_lender_email" name="email" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="scwp_lender_first_name"><?php esc_html_e( 'First name', 'sharing-club' ); ?>*</label></th>
                    <td><input type="text" id="scwp_lender_first_name" name="first_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="scwp_lender_last_name"><?php esc_html_e( 'Last name', 'sharing-club' ); ?>*</label></th>
                    <td><input type="text" id="scwp_lender_last_name" name="last_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="scwp_lender_phone"><?php esc_html_e( 'Phone number', 'sharing-club' ); ?>*</label></th>
                    <td><input type="tel" id="scwp_lender_phone" name="phone" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="scwp_lender_kids"><?php esc_html_e( 'Aantal kinderen', 'sharing-club' ); ?>*</label></th>
                    <td>
                        <fieldset id="scwp_lender_kids">
                            <label><input type="radio" name="kids" value="1" required checked> <?php esc_html_e( '1', 'sharing-club' ); ?></label><br>
                            <label><input type="radio" name="kids" value="2" required> <?php esc_html_e( '2+', 'sharing-club' ); ?></label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Add lender', 'sharing-club' ) ); ?>
        </form>
    </div>
    <?php
}
function scwp_add_menu_item(){
    if ( current_user_can( 'lend_shared_item' ) ) {
        add_submenu_page(
            'edit.php?post_type=shared_item', 
            __('Lendings', 'sharing-club'), 
            __('Lendings', 'sharing-club'), 
            'lend_shared_item', 
            'display_lending_table', 
            'scwp_display_lending_table'
        );
        add_submenu_page(
            'edit.php?post_type=shared_item', // parent slug
            __('Lending form', 'sharing-club'),     // page title
            __('Lending form', 'sharing-club'),     // menu title
            'lend_shared_item',   // capability
            'display_lending_form',     // menu slug
            'scwp_display_lending_form' // callback function
        );
        add_submenu_page(
            'edit.php?post_type=shared_item',
            __( 'Add lender', 'sharing-club' ),
            __( 'Add lender', 'sharing-club' ),
            'lend_shared_item',
            'add_lender',
            'scwp_display_lender_form'
        );
    }
    // Hide "Add New" for users without edit_shared_item capability
    if ( ! current_user_can( 'edit_shared_item' ) ) {
        remove_submenu_page( 'edit.php?post_type=shared_item', 'post-new.php?post_type=shared_item' );
    }
}
add_action('admin_menu', 'scwp_add_menu_item');

// add an author column
function shared_item_columns($columns) {
	$new_columns = array(
		'author' => __('Author'),
        'note' => __('Excerpt'),
	);
    return array_merge($columns, $new_columns);
}
add_filter('manage_shared_item_posts_columns' , 'shared_item_columns');

function display_shared_item_columns( $column, $post_id ) {
    if ($column == 'note'){
        echo get_post_field( 'post_excerpt', $post_id );
    }
}
add_action( 'manage_posts_custom_column' , 'display_shared_item_columns', 10, 2 );

// exclude custom comments from regular comments
add_filter( 'comments_clauses', 'scwp_exclude_comments', 10, 1);
function scwp_exclude_comments( $clauses ) {
    // Hide all those comments which aren't of type system_message
    $clauses['where'] .= ' AND comment_type != "lending"';   
    return $clauses;
}




/* FRONT END */

/* Filter the single_template & archive_template with our custom function*/
function scwp_custom_post_templates($template) {
    global $post;
    $archive_page = 'archive-shared_item.php';
    $single_page = 'single-shared_item.php';
    $exists_in_theme = locate_template(array($archive_page, $single_page), false);
    if ( $exists_in_theme != '' ) {
        $template = $exists_in_theme;
    }else{
        wp_enqueue_style( 'scwp-public', plugin_dir_url( __FILE__ ) . 'css/lending-library-public.css');
        wp_enqueue_script( 'scwp-public', plugin_dir_url( __FILE__ ) . 'js/lending-library-public.js', array( 'jquery', 'jquery-ui-datepicker' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/lending-library-public.js' ) );
        if ( is_post_type_archive ( 'shared_item' ) ) {
            /* Checks for archive template */
            if(file_exists(plugin_dir_path( __FILE__ ).'/templates/'.$archive_page ))
                $template =  plugin_dir_path( __FILE__ ).'/templates/'.$archive_page;
        }else if ($post->post_type == 'shared_item'){
            /* Checks for single template */
            if(file_exists(plugin_dir_path( __FILE__ ).'/templates/'.$single_page))
                $template = plugin_dir_path( __FILE__ ).'/templates/'.$single_page;
        }
    }

    return $template;
}
add_filter('single_template',   'scwp_custom_post_templates');
add_filter('archive_template',  'scwp_custom_post_templates' ) ;


function scwp_process_data(){
    if(is_user_logged_in()){
        global $post, $wpdb;
        if(isset($_POST['comment_ID']) || isset($_POST['iwantit'])){
            if( $post->post_type == 'shared_item' && is_user_logged_in() ){
                if(isset($_REQUEST['iwantit']) && isset( $_POST['booking_nonce'] ) && wp_verify_nonce( $_POST['booking_nonce'], 'booking' )){
                    // email notification
                    $to = get_option('admin_email');
                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    $current_user = wp_get_current_user();
                    $subject = sprintf(__('%s requested an object', 'sharing-club'), $current_user->user_login);
                    $message = __("There's a new request for lending. You can manage the requests connecting to :", 'sharing-club');
                    $message .= ' <a href="'.get_admin_url().'" target="_blank">'.get_admin_url().'</a>';
                    add_filter( 'wp_mail_content_type', function( $content_type ) {
                        return 'text/html';
                    });
                    wp_mail( $to, $subject,  $message, $headers) ;
                    $wpdb->insert( $wpdb->comments, array('user_id'=>get_current_user_id(), 'comment_post_ID'=>get_the_ID(), 'comment_type'=>'lending'));
                    header('Location: '.$_SERVER['REQUEST_URI']);
                }
                if((isset($_POST['comment_karma']) || isset($_POST['comment_content']) ) && wp_verify_nonce( $_POST['review_nonce'], 'review' )){
                    // sanitized data
                    $comment_ID = intval($_POST['comment_ID']);
                    $vars = array('comment_type'=>'lending');
                    if(isset($_POST['comment_karma']))  $vars['comment_karma']  = preg_replace('([^0-5])', '', $_POST['comment_karma']);
                    if(isset($_POST['comment_content'])) $vars['comment_content'] = sanitize_text_field($_POST['comment_content']);
                    $validation = scwp_validate_lending($vars);
                    if($validation){
                        $wpdb->update( $wpdb->comments, $vars, array('comment_ID'=>$comment_ID));   
                    }else{
                        echo $validation;
                        exit();
                    }
                }
            }
            wp_redirect( $_SERVER['REQUEST_URI'] );
            exit();
        }
    }
}
add_action( 'template_redirect', 'scwp_process_data' );

// ADMIN OPTION PAGE
require_once plugin_dir_path( __FILE__ ) . 'admin-options.php';
