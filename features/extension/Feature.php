<?php

namespace CobraAI\Features\Extension;

use CobraAI\FeatureBase;


class Feature extends FeatureBase {
    /**
     * Feature properties
     */
    protected $feature_id = 'extension';
    protected $name = 'Extension manager API';
    protected $description = 'Integrate multiple AI providers with tracking and management';
    protected $version = '1.0.0';
    protected $author = 'Cobra AI';
    protected $has_settings = false;
    protected $has_admin = false;

    /**
     * Feature components
     */ 

    // constracteur
    public function __construct() {
        parent::__construct();
    }

    /**
     * Setup feature
     */
    protected function setup(): void {
       
       
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks(): void {
        parent::init_hooks();
      
    }

    /**
     * Get feature default options
     */
    protected function get_feature_default_options(): array {
        return [
             
        ];
    }
 
    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        
        /*
        /extension-get-answer only login user

        /extension-get-user by session cookie

        */
        
    }

    /**
     * Check REST API permissions
     */
    public function rest_check_permission(): bool {
        return is_user_logged_in();
    }

    

    
}