<?php

/**
 * ------------------------------------------------------------------------
 * Doom Plugin (Community Edition)
 * Copyright (C) 2026 Marcati
 * https://github.com/juniormarcati
 * ------------------------------------------------------------------------
 * This file is part of Doom Plugin.
 *
 * Doom Plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Doom Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Doom Plugin. If not, see <https://www.gnu.org/licenses/>.
 * ------------------------------------------------------------------------
 *
 * Provides branding customization for GLPI (login page and system logos).
 *
 * @package   Doom Plugin
 * @author    Marcati
 * @copyright 2026 Marcati
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/juniormarcati/brandkit
 * @since     2026
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

define('PLUGIN_DOOM_VERSION', '0.2.0');
define('PLUGIN_DOOM_MIN_GLPI', '10.0.0');
define('PLUGIN_DOOM_MAX_GLPI', '12.0.99');

include_once __DIR__ . '/inc/compat.php';
include_once __DIR__ . '/hook.php';
include_once __DIR__ . '/inc/config.class.php';

if (!function_exists('doom_is_glpi11')) {
    function doom_is_glpi11(): bool
    {
        if (defined('GLPI_VERSION')) {
            return version_compare(GLPI_VERSION, '11.0.0', '>=');
        }

        if (defined('GLPI_ROOT')) {
            $root = rtrim(GLPI_ROOT, DIRECTORY_SEPARATOR);
            $publicPics = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'pics';
            $legacyPics = $root . DIRECTORY_SEPARATOR . 'pics';
            if (is_dir($legacyPics) && !is_dir($publicPics)) {
                return false;
            }
            if (is_dir($publicPics) && !is_dir($legacyPics)) {
                return true;
            }
        }

        return false;
    }
}

function doom_relative_path(string $from, string $to): string
{
    $from = rtrim(str_replace('\\', '/', $from), '/');
    $to = rtrim(str_replace('\\', '/', $to), '/');

    $fromParts = $from === '' ? [] : explode('/', $from);
    $toParts = $to === '' ? [] : explode('/', $to);

    while (!empty($fromParts) && !empty($toParts) && $fromParts[0] === $toParts[0]) {
        array_shift($fromParts);
        array_shift($toParts);
    }

    $rel = str_repeat('../', count($fromParts)) . implode('/', $toParts);
    return $rel === '' ? '.' : $rel;
}

function plugin_init_doom()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;
    // CSRF compliance - removed hook on GLPI 12 (CheckCsrfListener handles it);
    // keep it only on GLPI <12 (same scheme as the other URE plugins).
    if (!doom_is_glpi12()) {
        $PLUGIN_HOOKS['csrf_compliant']['doom'] = true;
    }

    $PLUGIN_HOOKS['display_login']['doom'] = 'plugin_doom_display_login';

    $PLUGIN_HOOKS['add_javascript']['doom'] = 'js/doom-error.js';

    $pluginDocDir = null;
    if (defined('GLPI_VAR_DIR')) {
        $base = rtrim(GLPI_VAR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '_plugins';
        if (is_dir($base)) {
            $pluginDocDir = rtrim($base, DIRECTORY_SEPARATOR);
        }
    }
    if (!$pluginDocDir && defined('GLPI_PLUGIN_DOC_DIR')) {
        $pluginDocDir = rtrim(GLPI_PLUGIN_DOC_DIR, DIRECTORY_SEPARATOR);
    }
    $docScssPath = $pluginDocDir ? $pluginDocDir . '/doom/doom-login.scss' : null;
    $docCssUrl = null;
    if ($pluginDocDir && defined('GLPI_ROOT')) {
        $glpiRoot = realpath(GLPI_ROOT);
        $pluginDocReal = realpath($pluginDocDir);
        if ($glpiRoot && $pluginDocReal) {
            $glpiRootPrefix = $glpiRoot . DIRECTORY_SEPARATOR;
            if (strncmp($pluginDocReal, $glpiRootPrefix, strlen($glpiRootPrefix)) === 0) {
                $relative = ltrim(substr($pluginDocReal, strlen($glpiRoot)), DIRECTORY_SEPARATOR);
            } else {
                $relative = doom_relative_path($glpiRoot, $pluginDocReal);
            }
            $scssFile = ltrim($relative . '/doom/doom-login', '/');
            $docCssUrl = $CFG_GLPI['root_doc'] . '/front/css.php?file=' . $scssFile;
            $docCssVersion = is_file($docScssPath) ? (string) filemtime($docScssPath) : PLUGIN_DOOM_VERSION;
            $docCssUrl .= '&bk=' . $docCssVersion;
        }
    }

    $loginHeaderTags = [];
    if ($docCssUrl) {
        $loginHeaderTags[] = [
            'tag' => 'link',
            'properties' => [
                'rel' => 'stylesheet',
                'href' => $docCssUrl,
            ],
        ];
    }
    $isGlpi11 = doom_is_glpi11();
    if (!empty($loginHeaderTags)) {
        $PLUGIN_HOOKS['add_header_tag_anonymous_page']['doom'] = $loginHeaderTags;
    }
    // JS da tela de login: reskin do MFA roda em todas as versões; o
    // doom-login.js legado só no <11 (comportamento original preservado).
    $anonJs = ['js/doom-mfa.js'];
    if (!$isGlpi11) {
        $anonJs[] = 'js/doom-login.js';
    }
    $PLUGIN_HOOKS['add_javascript_anonymous_page']['doom'] = $anonJs;

    if (!Session::getLoginUserID()) {
        return true;
    }

    $domain = 'doom';

    if (method_exists('Plugin', 'loadLang')) {
        Plugin::loadLang($domain);
    }

    $PLUGIN_HOOKS['add_css']['doom'][] = 'front/doom.css.php';


    if (
        !isset($_SESSION['doom_menu_version'])
        || $_SESSION['doom_menu_version'] !== PLUGIN_DOOM_VERSION
    ) {
        unset($_SESSION['glpimenu']);
        $_SESSION['doom_menu_version'] = PLUGIN_DOOM_VERSION;
    }

    Plugin::registerClass('PluginDoomConfig');

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['doom'] = ['plugins' => 'PluginDoomConfig'];
        $PLUGIN_HOOKS['config_page']['doom'] = 'front/index.php';
        if (!$isGlpi11) {
            $PLUGIN_HOOKS['menu_entry']['doom'] = 'front/index.php';
            $PLUGIN_HOOKS['submenu_entry']['doom']['search'] = 'front/index.php';
        }
    }

    return true;
}

function plugin_version_doom()
{
    global $CFG_GLPI, $TRANSLATE;

    $lang = '';
    if (class_exists('Session') && method_exists('Session', 'getLanguage')) {
        $lang = Session::getLanguage() ?? '';
    }
    if (!$lang && isset($CFG_GLPI['language'])) {
        $lang = $CFG_GLPI['language'];
    }

    if (
        method_exists('Plugin', 'loadLang')
        && isset($TRANSLATE)
        && is_object($TRANSLATE)
    ) {
        Plugin::loadLang('doom', $lang);
    }

    return [
        'name' => '[URE] Doom',
        'version' => PLUGIN_DOOM_VERSION,
        'author' => '<a href="https://github.com/juniormarcati">Marcati</a>',
        'license' => 'AGPLv3+',
        'homepage' => 'https://github.com/juniormarcati/brandkit',
        'readme' => 'https://github.com/juniormarcati/brandkit/blob/master/Readme.md',
        'issues' => 'https://github.com/juniormarcati/brandkit/issues',
        'requirements' => [
            'glpi' => [
                  'min' => PLUGIN_DOOM_MIN_GLPI,
                  'max' => PLUGIN_DOOM_MAX_GLPI,
            ],
            'php' => [
                'min' => '7.4'
            ]
        ]
    ];
}

function plugin_check_prerequisites_doom()
{
    if (
          version_compare(GLPI_VERSION, PLUGIN_DOOM_MIN_GLPI, 'lt') ||
          version_compare(GLPI_VERSION, PLUGIN_DOOM_MAX_GLPI, 'gt')
    ) {
        echo sprintf(
            __('Plugin Doom requires GLPI version %s to %s.', 'doom'),
            PLUGIN_DOOM_MIN_GLPI,
            PLUGIN_DOOM_MAX_GLPI
        );
        return false;
    }

      if (version_compare(PHP_VERSION, '7.4', 'lt')) {
          echo __('Plugin Doom requires PHP version 7.4 or higher.', 'doom');
        return false;
    }

    $required_extensions = ['json', 'curl'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
              echo sprintf(__('Plugin Doom requires PHP extension: %s', 'doom'), $ext);
            return false;
        }
    }

    return true;
}

function plugin_check_config_doom()
{
    return true;
}

function plugin_install_doom()
{
    return plugin_doom_install();
}

function plugin_uninstall_doom()
{
    return plugin_doom_uninstall();
}
