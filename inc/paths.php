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
 * Shared path helpers for Doom Plugin.
 *
 * @package   Doom Plugin
 * @author    Marcati
 * @copyright 2026 Marcati
 * @license   AGPL-3.0-or-later
 * @link      https://github.com/juniormarcati/brandkit
 * @since     2026
 * ------------------------------------------------------------------------
 */

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

if (!function_exists('doom_get_glpi_root_doc')) {
    function doom_get_glpi_root_doc(): string
    {
        $root = '';
        if (isset($GLOBALS['CFG_GLPI']) && is_array($GLOBALS['CFG_GLPI'])) {
            $cfg = $GLOBALS['CFG_GLPI'];
            if (isset($cfg['root_doc']) && is_string($cfg['root_doc'])) {
                $root = rtrim($cfg['root_doc'], '/');
            }
        }
        if ($root === '' && class_exists('Plugin')) {
            $pluginWeb = doom_web_dir('doom', false);
            if (is_string($pluginWeb) && $pluginWeb !== '') {
                $normalized = rtrim($pluginWeb, '/');
                $root = preg_replace('~/?plugins/doom$~', '', $normalized) ?? '';
            }
        }
        if ($root !== '' && $root[0] !== '/') {
            $root = '/' . $root;
        }

        return $root;
    }
}

if (!function_exists('doom_get_front_web_base')) {
    function doom_get_front_web_base(): string
    {
        $root = doom_get_glpi_root_doc();
        return ($root === '' ? '' : $root) . '/front';
    }
}

if (!function_exists('doom_get_pics_web_base')) {
    function doom_get_pics_web_base(): string
    {
        $root = doom_get_glpi_root_doc();
        return ($root === '' ? '' : $root) . '/pics';
    }
}

if (!function_exists('doom_get_pics_dir')) {
    function doom_get_pics_dir(): ?string
    {
        if (defined('GLPI_PICS_DIR')) {
            return rtrim(GLPI_PICS_DIR, DIRECTORY_SEPARATOR);
        }

        if (!defined('GLPI_ROOT')) {
            return null;
        }

        $root = rtrim(GLPI_ROOT, DIRECTORY_SEPARATOR);
        $publicPics = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'pics';
        $legacyPics = $root . DIRECTORY_SEPARATOR . 'pics';
        $candidates = doom_is_glpi11()
            ? [$publicPics, $legacyPics]
            : [$legacyPics, $publicPics];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('doom_get_pics_logos_dir')) {
    function doom_get_pics_logos_dir(): ?string
    {
        if (defined('GLPI_PICS_DIR')) {
            $picsDir = rtrim(GLPI_PICS_DIR, DIRECTORY_SEPARATOR);
            $logosDir = $picsDir . DIRECTORY_SEPARATOR . 'logos';
            if (is_dir($logosDir)) {
                return $logosDir;
            }
            if (is_dir($picsDir)) {
                return $picsDir;
            }
        }

        $picsDir = doom_get_pics_dir();
        if (!$picsDir) {
            return null;
        }

        $logosDir = $picsDir . DIRECTORY_SEPARATOR . 'logos';
        return is_dir($logosDir) ? $logosDir : null;
    }
}
