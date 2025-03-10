# cobra-ai-features
Corbra AI Features is a WordPress plugin that provides a set of features to enhance the functionality of the AI and the automation platform.

cobra-ai-features/
├── cobra-ai-features.php              # Main plugin file (namespace-based)
├── composer.json                      # Composer configuration
├── uninstall.php                      # Plugin uninstall handler
│
├── includes/                          # Core classes
│   ├── class-feature-base.php         # Base feature class
│   ├── class-database.php             # Database management
│   ├── class-admin.php                # Admin functionality
│   ├── class-api-manager.php          # API functionality
│   ├── class-loader.php               # Class autoloader
│   └── utilities/                     # Helper functions & classes
│       ├── class-validator.php        # Data validation
│       └── functions.php              # Global utility functions
│
├── admin/                             # Admin interface
│   ├── views/                        # Admin pages
│       ├── dashboard.php             # Dashboard view
│       ├── features.php              # Features management
│       └── settings.php              # Global settings

│
├── features/                          # Feature modules
│   └── {feature-name}/               # Individual feature
│       ├── class-{feature-name}.php  # Main feature class
│       ├── admin/                    # Feature admin files
│       │   ├── css/                  # Admin styles
│       │   ├── js/                   # Admin scripts
│       │   └── views/                # Admin views
│       ├── assets/                   # Public assets
│       └── templates/                # Feature templates
│
├── assets/                           # Global assets
│   ├── css/                          # Global styles
│   ├── js/                           # Global scripts
│   └── images/                       # Global images
│
└── languages/                        # Translations