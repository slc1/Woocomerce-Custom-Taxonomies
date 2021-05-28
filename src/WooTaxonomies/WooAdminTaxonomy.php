<?php

namespace Slc\WooTaxonomies;

class WooAdminTaxonomy extends \WC_Admin_Taxonomies
{
    public $taxonomySlug;

    private $default_cat_id = 0;
    
    public function __construct($taxonomySlug) {

        $this->taxonomySlug = $taxonomySlug;
        
        // Default category ID.
        $this->default_cat_id = get_option( 'default_' . $this->taxonomySlug, 0 );

        // Category/term ordering.
        add_action( 'create_term', array( $this, 'create_term' ), 5, 3 );

        // Add form.
        add_action( $this->taxonomySlug . '_add_form_fields', array( $this, 'add_category_fields' ) );
        add_action( $this->taxonomySlug . '_edit_form_fields', array( $this, 'edit_category_fields' ), 10 );
        add_action( 'created_term', array( $this, 'save_category_fields' ), 10, 3 );
        add_action( 'edit_term', array( $this, 'save_category_fields' ), 10, 3 );

        // Add columns.
        add_filter( 'manage_edit-' .$this->taxonomySlug . '_columns', array( $this, 'product_cat_columns' ) );
        add_filter( 'manage_' .$this->taxonomySlug . '_custom_column', array( $this, 'product_cat_column' ), 10, 3 );

        // Add row actions.
        add_filter( $this->taxonomySlug . '_row_actions', array( $this, 'product_cat_row_actions' ), 10, 2 );
        add_filter( 'admin_init', array( $this, 'handle_product_cat_row_actions' ) );

        // Taxonomy page descriptions.
        add_action( $this->taxonomySlug . '_pre_add_form', array( $this, 'product_cat_description' ) );
        add_action( 'after-' .$this->taxonomySlug . '-table', array( $this, 'product_cat_notes' ) );

        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ( ! empty( $attribute_taxonomies ) ) {
            foreach ( $attribute_taxonomies as $attribute ) {
                add_action( 'pa_' . $attribute->attribute_name . '_pre_add_form', array( $this, 'product_attribute_description' ) );
            }
        }

        // Maintain hierarchy of terms.
        add_filter( 'wp_terms_checklist_args', array( $this, 'disable_checked_ontop' ) );

        // Admin footer scripts for this product categories admin screen.
        add_action( 'admin_footer', array( $this, 'scripts_at_product_cat_screen_footer' ) );
    }

    public function create_term( $term_id, $tt_id = '', $taxonomy = '' ) {
        if ( $this->taxonomySlug != $taxonomy && ! taxonomy_is_product_attribute( $taxonomy ) ) {
            return;
        }

        $meta_name = taxonomy_is_product_attribute( $taxonomy ) ? 'order_' . esc_attr( $taxonomy ) : 'order';

        update_term_meta( $term_id, $meta_name, 0 );
    }

    /**
     * Save category fields
     *
     * @param mixed  $term_id Term ID being saved.
     * @param mixed  $tt_id Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     */
    public function save_category_fields( $term_id, $tt_id = '', $taxonomy = '' ) {
        if ( isset( $_POST['display_type'] ) && $this->taxonomySlug === $taxonomy ) { // WPCS: CSRF ok, input var ok.
            update_term_meta( $term_id, 'display_type', esc_attr( $_POST['display_type'] ) ); // WPCS: CSRF ok, sanitization ok, input var ok.
        }
        if ( isset( $_POST['product_cat_thumbnail_id'] ) && $this->taxonomySlug === $taxonomy ) { // WPCS: CSRF ok, input var ok.
            update_term_meta( $term_id, 'thumbnail_id', absint( $_POST['product_cat_thumbnail_id'] ) ); // WPCS: CSRF ok, input var ok.
        }
    }

    /**
     * Adjust row actions.
     *
     * @param array  $actions Array of actions.
     * @param object $term Term object.
     * @return array
     */
    public function product_cat_row_actions( $actions, $term ) {
        $default_category_id = absint( get_option( 'default_' . $this->taxonomySlug , 0 ) );

        if ( $default_category_id !== $term->term_id && current_user_can( 'edit_term', $term->term_id ) ) {
            $actions['make_default'] = sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                wp_nonce_url( 'edit-tags.php?action=make_default&amp;taxonomy=' . $this->taxonomySlug  . '&amp;post_type=product&amp;tag_ID=' . absint( $term->term_id ), 'make_default_' . absint( $term->term_id ) ),
                /* translators: %s: taxonomy term name */
                esc_attr( sprintf( __( 'Make &#8220;%s&#8221; the default category', 'woocommerce' ), $term->name ) ),
                __( 'Make default', 'woocommerce' )
            );
        }

        return $actions;
    }

    /**
     * Handle custom row actions.
     */
    public function handle_product_cat_row_actions() {
        if ( isset( $_GET['action'], $_GET['tag_ID'], $_GET['_wpnonce'] ) && 'make_default' === $_GET['action'] ) { // WPCS: CSRF ok, input var ok.
            $make_default_id = absint( $_GET['tag_ID'] ); // WPCS: Input var ok.

            if ( wp_verify_nonce( $_GET['_wpnonce'], 'make_default_' . $make_default_id ) && current_user_can( 'edit_term', $make_default_id ) ) { // WPCS: Sanitization ok, input var ok, CSRF ok.
                update_option( 'default_' . $this->taxonomySlug, $make_default_id );
            }
        }
    }

    /**
     * Add some notes to describe the behavior of the default category.
     */
    public function product_cat_notes() {
        $category_id   = get_option( 'default_' . $this->taxonomySlug, 0 );
        $category      = get_term( $category_id, $this->taxonomySlug);
        $category_name = ( ! $category || is_wp_error( $category ) ) ? _x( 'Uncategorized', 'Default category slug', 'woocommerce' ) : $category->name;
        ?>
        <div class="form-wrap edit-term-notes">
            <p>
                <strong><?php esc_html_e( 'Note:', 'woocommerce' ); ?></strong><br>
                <?php
                printf(
                /* translators: %s: default category */
                    esc_html__( 'Deleting a category does not delete the products in that category. Instead, products that were only assigned to the deleted category are set to the category %s.', 'woocommerce' ),
                    '<strong>' . esc_html( $category_name ) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Maintain term hierarchy when editing a product.
     *
     * @param  array $args Term checklist args.
     * @return array
     */
    public function disable_checked_ontop( $args ) {
        if ( ! empty( $args['taxonomy'] ) && $this->taxonomySlug === $args['taxonomy'] ) {
            $args['checked_ontop'] = false;
        }
        return $args;
    }

    /**
     * Admin footer scripts for the product categories admin screen
     *
     * @return void
     */
    public function scripts_at_product_cat_screen_footer() {
        if ( ! isset( $_GET['taxonomy'] ) || $this->taxonomySlug !== $_GET['taxonomy'] ) { // WPCS: CSRF ok, input var ok.
            return;
        }
        // Ensure the tooltip is displayed when the image column is disabled on product categories.
        wc_enqueue_js(
            "(function( $ ) {
				'use strict';
				var product_cat = $( 'tr#tag-" . absint( $this->default_cat_id ) . "' );
				product_cat.find( 'th' ).empty();
				product_cat.find( 'td.thumb span' ).detach( 'span' ).appendTo( product_cat.find( 'th' ) );
			})( jQuery );"
        );
    }
}