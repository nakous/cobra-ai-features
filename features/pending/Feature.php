<?php

namespace CobraAI\Features\Pending;

use CobraAI\FeatureBase;

/**
 * Placeholder for pending features
 * This prevents errors when the system tries to load the 'pending' feature
 */
class Feature extends FeatureBase 
{
    protected string $feature_id = 'pending';
    protected string $name = 'Pending Features';
    protected string $description = 'Placeholder for pending features in development';
    protected string $version = '1.1.0';
    protected string $author = 'Onlevelup.com';
    protected bool $has_settings = false;
    protected bool $has_admin = false;

    /**
     * Setup feature - empty for placeholder
     */
    protected function setup(): void
    {
        // Placeholder - no setup needed
    }
    
    /**
     * Initialize - prevent any initialization
     */
    public function init(): bool 
    {
        // Always return false to prevent activation
        return false;
    }
}