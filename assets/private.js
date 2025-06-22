/**
 * Private entrypoint for admin/private area
 * 
 * This file serves as the main entry point for the private/admin section
 * of the EPROFOS e-learning platform. It includes Tabler UI framework
 * along with necessary dependencies for the admin interface.
 */

// Import Stimulus bootstrap for controller functionality
import './bootstrap.js';

// Import Tabler CSS for admin UI styling
import '@tabler/core/dist/css/tabler.min.css';

// Import Tabler JavaScript for interactive components
import '@tabler/core/dist/js/tabler.min.js';

// Import Hotwire Turbo for SPA-like navigation
import '@hotwired/turbo';

console.log('Private entrypoint loaded successfully');