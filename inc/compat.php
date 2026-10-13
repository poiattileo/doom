<?php

/**
 * ------------------------------------------------------------------------
 * Doom Plugin (Community Edition) — GLPI 11/12 compatibility layer
 * ------------------------------------------------------------------------
 * Same scheme as kanpro/inc/compat.php and the other URE plugins.
 * Helpers guarded by function_exists/method_exists. On GLPI 12:
 * Plugin::getWebDir() was removed, Session::getNewCSRFToken() and
 * Session::checkCSRF() became no-ops (deprecation only) and CSRF is
 * validated by the CheckCsrfListener (Sec-Fetch-Site/Origin).
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

if (!function_exists('doom_is_glpi12')) {
    function doom_is_glpi12(): bool
    {
        if (defined('GLPI_VERSION')) {
            return version_compare(GLPI_VERSION, '12.0.0', '>=');
        }
        return false;
    }
}

if (!function_exists('doom_web_dir')) {
    /**
     * Plugin base URL, mirroring Plugin::getWebDir($plugin, $full).
     * GLPI <=11: uses Plugin::getWebDir(); GLPI 12 (method removed):
     * builds from root_doc. $full=true includes root_doc, false is
     * relative to the docroot (for menu pages, GLPI prepends root_doc).
     */
    function doom_web_dir(string $plugin = 'doom', bool $full = true): string
    {
        if (method_exists('Plugin', 'getWebDir')) {
            try {
                return (string) Plugin::getWebDir($plugin, $full);
            } catch (\Throwable $e) {
                // falls through to the fallback below
            }
        }
        global $CFG_GLPI;
        $root = (is_array($CFG_GLPI) && isset($CFG_GLPI['root_doc'])) ? (string) $CFG_GLPI['root_doc'] : '';
        $base = '/plugins/' . $plugin;
        if (defined('GLPI_ROOT')) {
            $disk = (string) GLPI_ROOT;
            if (@is_dir($disk . '/marketplace/' . $plugin)) {
                $base = '/marketplace/' . $plugin;
            }
        }
        return $full ? $root . $base : $base;
    }
}

if (!function_exists('doom_check_csrf')) {
    /**
     * Explicit CSRF check. No-op on GLPI 12 (CheckCsrfListener handles it);
     * on GLPI <=11 delegates to Session::checkCSRF() preserving the token.
     */
    function doom_check_csrf(array $data): void
    {
        if (doom_is_glpi12()) {
            return;
        }
        Session::checkCSRF($data, true);
    }
}
