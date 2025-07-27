/**
 * Private entrypoint for admin/private area
 * 
 * This file serves as the main entry point for the private/admin section
 * of the EPROFOS e-learning platform. It includes Tabler UI framework
 * along with necessary dependencies for the admin interface.
 */

// Import Stimulus bootstrap for controller functionality
import './bootstrap.js';

import '@tabler/core';
import '@tabler/core/dist/css/tabler.min.css';

// Import Font Awesome for admin area
import '@fortawesome/fontawesome-free/css/fontawesome.min.css';
import '@fortawesome/fontawesome-free/css/solid.min.css';
import '@fortawesome/fontawesome-free/css/brands.min.css';
import '@fortawesome/fontawesome-free';

// Import admin-specific styles
import './styles/admin.css';
import './styles/objectives.css';

console.log('Private entrypoint loaded successfully');