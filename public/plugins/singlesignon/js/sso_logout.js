/**
 * SSO logout redirect for GLPI 11.
 *
 * Rewrites the standard GLPI logout link to route through the SSO plugin's
 * logout endpoint, ensuring the SSO session is also terminated on logout.
 *
 * Loaded via ADD_JAVASCRIPT hook (Standards Mode safe — no inline echo).
 *
 * @see https://github.com/edgardmessias/glpi-singlesignon/issues/150
 */
(function () {
    "use strict";

    var rootDoc = (typeof CFG_GLPI !== "undefined" && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : "";
    var pluginLogout = rootDoc + "/plugins/singlesignon/front/logout.php";

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("a[href*='/front/logout.php']").forEach(function (link) {
            var href = link.getAttribute("href");
            if (href && href.indexOf("plugins/singlesignon") === -1) {
                link.setAttribute("href", pluginLogout);
            }
        });
    });
}());
