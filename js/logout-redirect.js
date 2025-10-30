/**
 * Redirect logout links to plugin logout to preserve noAUTO parameter
 */
(function() {
    'use strict';
    
    // Get plugin logout URL from data attribute set by PHP
    const pluginData = document.getElementById('singlesignon-plugin-data');
    if (!pluginData) {
        return;
    }
    
    const pluginLogout = pluginData.dataset.logoutUrl;
    if (!pluginLogout) {
        return;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Modify all logout links to use plugin logout
        document.querySelectorAll('a[href*="/front/logout.php"]').forEach(function(link) {
            const href = link.getAttribute('href');
            if (href && href.indexOf('plugins/singlesignon') === -1) {
                link.setAttribute('href', pluginLogout);
            }
        });
    });
})();
