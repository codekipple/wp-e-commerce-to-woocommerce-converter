<?php
/*
Plugin Name: wp-e-commerce to woocommerce conversion
Plugin URI: ralcus.com
Version: 0.1
Author: ralcus
Description: converts wp-e-commerce based shops to be compatible with woocommerce
*/


if (!class_exists("ralc_wpec_to_woo")) {

    class ralc_wpec_to_woo {
        
        var $output;
        var $products; // stores all the product posts
        
        function ralc_wpec_to_woo() { } // constructor
        
        function plugin_menu() {
          add_submenu_page( 'tools.php', 'wpec to woo', 'wpec to woo', 'manage_options', 'wpec-to-woo', array( $this, 'plugin_options' ) );
        }// END: plugin_menu

        function plugin_options() {
          if (!current_user_can('manage_options'))  {
            wp_die( __('You do not have sufficient permissions to access this page.') );
          }
          
          $this->output = '<div class="wrap">';
          $this->output .= '<h2>Wp-e-commerce to woocommerce converter</h2>';
          $this->output .= '<p>Use at your own risk!, still working on it, only use it on a test version of your site.</p>';
          $this->output .= '<p>The idea is you run this on a wordpress shop already setup with wp-e-commerce. Then this code will convert as much as it can into a woocommerce a shop. Make sure you have the Woocommerce plugin activated.</p>';
          $this->output .= '<p>Currently only converting products and categories, plan to try and convert the orders too. Because the sites i\'m writing this for don\'t have any variations on products i have not taken the time to work out a system for them. It also sets all products tax status to \'taxable\' and the tax class to \'standard\' regardless.</p>';
          
          $this->output .= '<p><b>One last caveat:</b> i\'m working with version:3.8.6 of wp-e-commerce, things may well have changed with the lastest version but the shops i need to convert are on this version and i\'m not interested in trying to upgrade them because of the many problems i have been having each time i upgrade the wp-e-commerce plugin. I\'ll test the plugin with the latest verion at a later date.</p>';
          
          if( !$_POST['order'] == 'go_go_go' ){
            $this->output .= '<p>Press the button for conversion goodness.</p>';
            $this->output .= '<form method="post" action="tools.php?page=wpec-to-woo">';
            $this->output .= '<input type="hidden" name="order" value="go_go_go" />';
            $this->output .= '<input type="submit" value="go go go" />';
            $this->output .= '</form>';
          }else{
            $this->conversion();
          }
          $this->output .= '</div>';
          echo $this->output;          
        } //END: plugin_options
        
        function conversion(){
          $this->output .= '<p>Conversion Finished</p>';
          
          $this->get_posts();
          $this->update_products();
          $this->update_categories();          
          
        }// END: conversion
        
        function get_posts(){
          $args = array( 'post_type' => 'wpsc-product', 'posts_per_page' => -1 );
          // $args = array( 'post_type' => 'product', 'posts_per_page' => -1 ); this line is just when im testing so i can keep running the code on the products even though their post_type has already been converted
          $this->products = new WP_Query( $args );
        }
        
        /*
         * convert post type to woocommerce post type
         * update price field meta
         */
        function update_products(){
          $count = 0;
          //wp-e stores all the featured products in one place
          $featured_products = get_option('sticky_products', false);

          while ( $this->products->have_posts() ) : $this->products->the_post();
            $post_id = get_the_id();
            $count ++;
            
            // ______ POST TYPE ______
            set_post_type( $post_id , 'product');                                  
            // ______________________________
            
            
            // get the serialized wpec product metadata
            $_wpsc_product_metadata = get_post_meta($post_id, '_wpsc_product_metadata', true);
            
                       
            // ______ PRICE ______ 
            $regular_price = get_post_meta($post_id, '_wpsc_price', true);
            update_post_meta($post_id, 'regular_price', $regular_price);   
            update_post_meta($post_id, 'price', $regular_price);
            
            $sale_price = get_post_meta($post_id, '_wpsc_special_price', true);
            update_post_meta($post_id, 'sale_price', $sale_price);
            // ______________________________
            
            
            // ______ INVENTORY ______
            $stock = get_post_meta($post_id, '_wpsc_stock', true);
            if( $stock != '' ){              
              $manage_stock = 'yes'; 
              $backorders = 'no';              
              if( (int)$stock > 0 ){
                $stock_status = 'instock';
              }else{
                $stock_status = 'outofstock';
              }
            }else{
              $manage_stock = 'no';
              $backorders = 'yes';
              $stock_status = 'instock';
            }
            // stock qty
            update_post_meta($post_id, 'stock', $stock);
            // stock status
            update_post_meta($post_id, 'stock_status', $stock_status);
            // manage stock
            update_post_meta($post_id, 'manage_stock', $manage_stock);  
            // backorders
            update_post_meta($post_id, 'backorders', $backorders);            
            // ______________________________
            
            
            // ______ PRODUCT TYPE AND VISIBILITY ______
            // setting all products to simple
            $product_type = 'simple';
            wp_set_object_terms($post_id, $product_type, 'product_type');
            if( $stock_status == 'instock' ){
                $visibility = 'visible';
            }else{
              $visibility = 'hidden';
            }
            // visibility
            update_post_meta($post_id, 'visibility', $visibility);
            // ______________________________
            
            
            // ______ OTHER PRODUCT DATA ______
            // sku code
            $sku = get_post_meta($post_id, '_wpsc_sku', true);
            if( $sku == null ){
              // try the old name
              $sku = $_wpsc_product_metadata['_wpsc_sku'];
            }
            update_post_meta($post_id, 'sku', $sku);            
            
            // tax status
            $tax_status = 'taxable';
            update_post_meta($post_id, 'tax_status', $sku);
            // tax class empty sets it to stndard
            $tax_class = '';
            update_post_meta($post_id, 'tax_class', $sku);
            
            // weight
            $weight = $_wpsc_product_metadata['weight'];

            update_post_meta($post_id, 'weight', $weight);
            /*
             * WPEC use to use ['_wpsc_dimensions'] but then changed to use ['dimensions']
             * some products may still have the old variable name
             */
            $dimensions = $_wpsc_product_metadata['dimensions'];
            if( $dimensions == null ){
              // try the old name
              $dimensions = $_wpsc_product_metadata['_wpsc_dimensions'];
            }
            // height
            $height = $dimensions['height'];
            update_post_meta($post_id, 'height', $height);
            //length
            $length = $dimensions['length'];
            update_post_meta($post_id, 'length', $length);
            //width
            $width = $dimensions['width'];
            update_post_meta($post_id, 'width', $width);
            
            
            // featured?
            if (in_array($post_id, $featured_products)) {
              $featured = 'yes';
            }else{
              $featured = 'no';
            }
            update_post_meta($post_id, 'featured', $featured);
            
            // ______________________________

            
            // ______ PRODUCT IMAGES ______
            /*
             * if products have multiple images wpec puts those pictures into the post gallery
             * because of this those pictures don't need to be ammended and should still be working
             * we only need to update the featured image
             * wpec uses the first one in the galley as the product image so we just have to set that as 
             * the featured image
             */
            $args = array( 'post_type' => 'attachment', 'numberposts' => 1, 'post_status' => null, 'post_parent' => $post_id, 'post_mime_type' => 'image' );
            $attachments = get_posts($args);
            if ($attachments) {
              foreach ( $attachments as $attachment ) {
                set_post_thumbnail( $post_id, $attachment->ID );
              }
            }
             
            // ______________________________
            
            // ______ RELATED PRODUCTS ______
            
            // ______________________________
            
            
            // ______ DELETE REDUNDANT WPEC ENTRIES ______
            /* left this commented while testing so i can repeatadly run the program
            delete_post_meta($post_id, '_wpsc_price');
            delete_post_meta($post_id, '_wpsc_special_price');
            delete_post_meta($post_id, '_wpsc_stock');
            delete_post_meta($post_id, '_wpsc_is_donation');
            delete_post_meta($post_id, '_wpsc_original_id');
            delete_post_meta($post_id, '_wpsc_sku');
            delete_post_meta($post_id, '_wpsc_product_metadata');
            delete_option('sticky_products');
            */
            // ______________________________
          endwhile;    
          $this->output .= '<p>' . $count . ' products updated</p>'; 

        }// END: update_products
        
        /*
         * update category
         */
        function update_categories(){
          global $wpdb;
          //$wpdb->show_errors();          
          $data = array( 'taxonomy' => 'product_cat' );
          $where = array( 'taxonomy' => 'wpsc_product_category' );
          $table = $wpdb->prefix . 'term_taxonomy';
          $wpdb->update( $table, $data, $where );
          $this->output .= '<p>' . $wpdb->num_rows . ' categories updated</p>';   
          $wpdb->flush();
          
          /* category stuff inside postmeta */
          $data = array( 'meta_value' => 'product_cat' );
          $where = array( 'meta_value' => 'wpsc_product_category' );
          $table = $wpdb->prefix . 'postmeta';
          $wpdb->update( $table, $data, $where ); 
          $wpdb->flush();
        }// END: update_categories
        

    } //End Class: ralc_wpec_to_woo
 
}


// instantiate class
if (class_exists("ralc_wpec_to_woo")) {
    $ralc_wpec_to_woo = new ralc_wpec_to_woo();
}

//Actions and Filters   
if (isset($ralc_wpec_to_woo)) {
    //Actions
    add_action('admin_menu', array($ralc_wpec_to_woo, 'plugin_menu') );
    //Filters
}