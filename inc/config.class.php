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

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginDoomConfig extends CommonGLPI
{
    public static string $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __('Doom', 'doom');
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    public static function canCreate(): bool
    {
        return self::canView();
    }

    public static function getMenuName(): string
    {
        return __('Doom', 'doom');
    }

    public static function getSearchURL($full = true): string
    {
        return doom_web_dir('doom', $full) . '/front/index.php';
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) {
            return false;
        }

        $menu = [
            'title' => self::getTypeName(1),
            'page' => doom_web_dir('doom', false) . '/front/index.php',
            'icon' => 'ti ti-paint',
        ];

        return $menu;
    }

    public function showForm($ID, array $options = [])
    {
        echo "<div class='center'><p>" . __('Doom configuration will be available soon.', 'doom') . "</p></div>";
        return true;
    }
}
