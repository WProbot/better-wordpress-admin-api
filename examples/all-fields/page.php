<?php
/*
    All Fields
*/

include_once __DIR__ . '/../../better-wp-admin-api/init.php';

// creates a page
$my_page = wp_create_admin_page( [
    'menu_name'  => 'All Fields',
    'id'         => 'all-fields',
    'prefix'     => 'all_fields_',
] );

// hook your setup function
$my_page->add_subtitle( 'Common fields', '' );

$my_page->add_field([
    'type'    => 'text',
    'id'      => 'text_field',
    'label'   => __( 'My Text field' ),
    'desc'    => __( 'some description.' ),
    'default' => '',
]);

$my_page->add_field([
    'type'    => 'select',
    'id'      => 'select_field',
    'label'   => __( 'My Select field' ),
    'desc'    => __( 'some description.' ),
    'default' => '',

    'choices'   => [
        'opt-1'             => 'Option',
        'My option group'   => [
            'opt-2'         => 'Another Option',
        ],
    ],
]);

$my_page->add_field([
    'type'    => 'checkbox',
    'id'      => 'checkbox_field',
    'label'   => __( 'My Checkbox field' ),
    'desc'    => __( 'some description.' ),
    'default' => '',
    'after'   => 'html rendered after the checkbox',
]);

$my_page->add_field([
    'type'    => 'checkbox_multi',
    'id'      => 'checkbox_multi_field',
    'label'   => __( 'My Multiple Checkboxes field' ),
    'desc'    => __( 'some description.' ),
    'default' => '',
    'choices' => [
        'banana'    => 'Banana',
        'apple'     => 'Apple',
        'orange'    => 'Orange',
    ],
]);

$my_page->add_field([
    'type'    => 'radio',
    'id'      => 'radio_field',
    'label'   => 'My Radio fields',
    'desc'    => 'some description.',
    'default' => '',
    'choices'   => [
        'BR'    => 'Brazil',
        'SV'    => 'El Salvador',
        'DE'    => 'Germany',
        'IT'    => 'Italy',
    ],
]);

$my_page->add_field([
    'type'    => 'hidden',
    'id'      => 'hidden_field',
    'default' => '',
]);

$my_page->add_subtitle( 'Special fields', '' );

$my_page->add_field([
    'type'    => 'html',
    'id'      => 'html_field',
    'label'   => 'My HTML field',
    'desc'    => 'some description.',
    'default' => '',
    'content' => 'all_fields_render_html_field',
]);

$my_page->add_field([
    'type'    => 'code',
    'id'      => 'code_field',
    'label'   => 'My Code field',
    'desc'    => 'some description.',
    'default' => '',
]);

$my_page->add_field( [
    'type'    => 'color',
    'id'      => 'color_field',
    'label'   => 'My Color picker',
    'desc'    => 'some description.',
    'default' => '#fff',
] );

$my_page->add_field( [
    'type'    => 'content',
    'id'      => 'content_field',
    'label'   => 'My Content picker',
    'desc'    => 'some description.',
    'default' => '',
] );

function all_fields_render_html_field ( $field, $my_page ) {
    $value = $my_page->get_field_value( $field['id'] );
    $html = '<div class="card">Made with HTML field<br>';
    $html .= '<input type="text" name="' . $field['id'] . '" value="' . esc_attr( $value ) . '">';
    $html .= '</div>';
    echo $html;
}