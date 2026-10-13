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

include_once '../../../inc/includes.php';
include_once __DIR__ . '/../inc/login.css.php';

Session::checkRight('config', UPDATE);

$isGlpi11 = doom_is_glpi11();

function doom_is_light_color(string $hex): bool
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return false;
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luma = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    return $luma > 0.75;
}

function doom_get_upload_error_message(int $errorCode, string $fallbackMessage): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return __('Wallpaper upload failed. The file is larger than the allowed size.', 'doom');
        case UPLOAD_ERR_PARTIAL:
            return __('Wallpaper upload failed because the file was only partially uploaded.', 'doom');
        case UPLOAD_ERR_NO_TMP_DIR:
            return __('Wallpaper upload failed because the temporary upload folder is missing on the server.', 'doom');
        case UPLOAD_ERR_CANT_WRITE:
            return __('Wallpaper upload failed because the server could not write the temporary file.', 'doom');
        case UPLOAD_ERR_EXTENSION:
            return __('Wallpaper upload was blocked by a PHP extension on the server.', 'doom');
        default:
            return $fallbackMessage;
    }
}

function doom_delete_uploaded_wallpaper_variants(string $baseName = 'wallpaperLogin'): void
{
    $extensions = ['png', 'jpg', 'jpeg', 'webp'];
    $paths = [];
    $picsDir = doom_get_pics_dir();
    if ($picsDir) {
        $publicDir = $picsDir . '/doom-wallpapers';
        foreach ($extensions as $ext) {
            $paths[] = $publicDir . '/' . $baseName . '.' . $ext;
        }
    }

    $pluginDir = doom_get_plugin_storage_dir();
    if ($pluginDir) {
        $legacyDir = rtrim($pluginDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wallpaper';
        foreach ($extensions as $ext) {
            $paths[] = $legacyDir . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;
        }
    }

    foreach (array_unique($paths) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

$currentStep = isset($_GET['step']) ? (int) $_GET['step'] : 0;
if ($currentStep < 0 || $currentStep > 3) {
    $currentStep = 0;
}

$baseUrl = doom_web_dir('doom') . '/front/index.php';
$pluginWebBase = doom_web_dir('doom', false);
if (is_string($pluginWebBase) && $pluginWebBase !== '' && $pluginWebBase[0] !== '/') {
    $pluginWebBase = '/' . $pluginWebBase;
}
$picsWebBase = doom_get_pics_web_base();
if ($picsWebBase === '') {
    $picsWebBase = '/pics';
}
$pluginDocDir = doom_get_plugin_storage_dir();
$logosDir = $pluginDocDir ? $pluginDocDir . '/logos' : null;
$publicLogosDir = doom_get_pics_logos_dir();
$logoFiles = [
    'logo-GLPI-100-white.png' => __('Expanded menu logo', 'doom'),
    'logo-G-100-white.png' => __('Collapsed menu logo', 'doom'),
    'logo-GLPI-100-black.png' => __('Expanded menu logo (dark mode)', 'doom'),
    'logo-G-100-black.png' => __('Collapsed menu logo (dark mode)', 'doom'),
    'logo-GLPI-250-black.png' => __('Login logo', 'doom'),
    'favicon.ico' => __('Favicon (browser tab)', 'doom'),
];
$wallpaperPresets = doom_get_wallpaper_presets();
$wallpaperPresetDefault = doom_get_wallpaper_preset_default($wallpaperPresets);
$allowedWallpaperExtensions = ['png', 'jpg', 'jpeg', 'webp'];
$wallpaperPresetLabels = [];
foreach ($wallpaperPresets as $presetFile) {
    $labelBase = str_replace(['-', '_'], ' ', pathinfo($presetFile, PATHINFO_FILENAME));
    $wallpaperPresetLabels[$presetFile] = ucwords(trim($labelBase));
}

$loginDefaults = doom_get_login_defaults();
$loginConfig = doom_get_login_settings();
$logoScaleDefaults = [
    'logo_header_scale' => '100',
    'logo_reduced_scale' => '100',
];
$logoScaleLegacyDefaults = [
    'logo_header_light_scale' => '100',
    'logo_reduced_light_scale' => '100',
    'logo_header_dark_scale' => '100',
    'logo_reduced_dark_scale' => '100',
];
$logoScaleConfig = Config::getConfigurationValues(
    'plugin:doom',
    array_merge(array_keys($logoScaleDefaults), array_keys($logoScaleLegacyDefaults))
);
$logoScaleConfig = array_merge(
    $logoScaleLegacyDefaults,
    $logoScaleDefaults,
    array_filter($logoScaleConfig, static fn($value) => $value !== null && $value !== '')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['doom_action'] ?? '') === 'reset_login_defaults') {
    $defaults = doom_get_login_defaults();
    $picsDir = doom_get_pics_dir();
    $wallpaperDir = $picsDir ? $picsDir . '/doom-wallpapers' : null;
    if ($wallpaperDir) {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $candidate = $wallpaperDir . '/wallpaperLogin.' . $ext;
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    Config::setConfigurationValues('plugin:doom', $defaults);
    $cssWritten = doom_write_login_css($pluginDocDir, $defaults);

    if ($cssWritten) {
        Session::addMessageAfterRedirect(__('Login page settings saved.', 'doom'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Settings saved, but the login CSS file could not be written.', 'doom'), false, WARNING);
    }

    Html::redirect($baseUrl . '?step=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['doom_action'] ?? '') === 'save_login_settings') {
    $layout = $_POST['login_layout'] ?? $loginDefaults['login_layout'];
    if (!in_array($layout, ['split-left', 'split-right', 'classic'], true)) {
        $layout = $loginDefaults['login_layout'];
    }

    $panelTransparent = ($_POST['login_panel_transparent'] ?? '0') === '1' ? '1' : '0';
    $panelHoverEffect = ($_POST['login_panel_hover_effect'] ?? '1') === '1' ? '1' : '0';
    $panelOpacity = doom_clamp_int($_POST['login_panel_opacity'] ?? $loginDefaults['login_panel_opacity'], 0, 100, 65);
    $panelBlur = doom_clamp_int($_POST['login_panel_blur'] ?? $loginDefaults['login_panel_blur'], 0, 24, 6);
    $panelWidth = doom_clamp_int($_POST['login_panel_width'] ?? $loginDefaults['login_panel_width'], 280, 720, 520);
    $panelHeight = doom_clamp_int($_POST['login_panel_height'] ?? $loginDefaults['login_panel_height'], 360, 780, 560);
    $panelColor = doom_normalize_hex_color($_POST['login_panel_color'] ?? $loginDefaults['login_panel_color'], $loginDefaults['login_panel_color']);
    $textColor = doom_normalize_hex_color($_POST['login_text_color'] ?? $loginDefaults['login_text_color'], $loginDefaults['login_text_color']);
    $logoPosition = $_POST['login_logo_position'] ?? $loginDefaults['login_logo_position'];
    if (!in_array($logoPosition, ['outside', 'inside'], true)) {
        $logoPosition = $loginDefaults['login_logo_position'];
    }
    $splitOpacity = doom_clamp_int($_POST['login_split_opacity'] ?? $loginDefaults['login_split_opacity'], 0, 100, 55);
    $splitBlur = doom_clamp_int($_POST['login_split_blur'] ?? $loginDefaults['login_split_blur'], 0, 24, 10);
    $splitColor = doom_normalize_hex_color($_POST['login_split_color'] ?? $loginDefaults['login_split_color'], $loginDefaults['login_split_color']);
    $splitWidth = doom_clamp_int($_POST['login_split_width'] ?? $loginDefaults['login_split_width'], 0, 100, 50);
    $buttonStyle = $_POST['login_button_style'] ?? $loginDefaults['login_button_style'];
    if (!in_array($buttonStyle, doom_get_login_button_styles(), true)) {
        $buttonStyle = $loginDefaults['login_button_style'];
    }
    $buttonShape = $_POST['login_button_shape'] ?? $loginDefaults['login_button_shape'];
    if (!in_array($buttonShape, doom_get_login_button_shapes(), true)) {
        $buttonShape = $loginDefaults['login_button_shape'];
    }
    $buttonColorMode = $_POST['login_button_color_mode'] ?? $loginDefaults['login_button_color_mode'];
    if (!in_array($buttonColorMode, ['preset', 'custom'], true)) {
        $buttonColorMode = $loginDefaults['login_button_color_mode'];
    }
    $buttonCustomStyle = $_POST['login_button_custom_style'] ?? $loginDefaults['login_button_custom_style'];
    if (!in_array($buttonCustomStyle, ['default', 'outline'], true)) {
        $buttonCustomStyle = $loginDefaults['login_button_custom_style'];
    }
    $buttonHoverEffect = $_POST['login_button_hover_effect'] ?? $loginDefaults['login_button_hover_effect'];
    if (!in_array($buttonHoverEffect, ['press', 'fill'], true)) {
        $buttonHoverEffect = $loginDefaults['login_button_hover_effect'];
    }
    $buttonHoverFillMode = $_POST['login_button_hover_fill_mode'] ?? $loginDefaults['login_button_hover_fill_mode'];
    if (!in_array($buttonHoverFillMode, ['button', 'custom'], true)) {
        $buttonHoverFillMode = $loginDefaults['login_button_hover_fill_mode'];
    }
    $buttonTextSizeDefault = $isGlpi11 ? 14 : 15;
    $buttonTextSize = doom_clamp_int($_POST['login_button_text_size'] ?? $loginDefaults['login_button_text_size'], 10, 28, $buttonTextSizeDefault);
    $buttonColorRaw = $loginDefaults['login_button_color'];
    if (!empty($_POST['login_button_color_hex'])) {
        $buttonColorRaw = $_POST['login_button_color_hex'];
    } elseif (!empty($_POST['login_button_color'])) {
        $buttonColorRaw = $_POST['login_button_color'];
    }
    $buttonColor = doom_normalize_hex_color($buttonColorRaw, $loginDefaults['login_button_color']);
    $buttonHoverFillColor = doom_normalize_hex_color(
        $_POST['login_button_hover_fill_color'] ?? $loginDefaults['login_button_hover_fill_color'],
        $loginDefaults['login_button_hover_fill_color']
    );
    $buttonHoverTextColor = doom_normalize_hex_color(
        $_POST['login_button_hover_text_color'] ?? $loginDefaults['login_button_hover_text_color'],
        $loginDefaults['login_button_hover_text_color']
    );

    $fieldStyle = $_POST['login_field_style'] ?? $loginDefaults['login_field_style'];
    $fieldStyleOptions = ['solid', 'glass', 'soft', 'outline', 'underline', 'neon'];
    if (!in_array($fieldStyle, $fieldStyleOptions, true)) {
        $fieldStyle = $loginDefaults['login_field_style'];
    }
    $fieldBg = doom_normalize_hex_color($_POST['login_field_bg'] ?? $loginDefaults['login_field_bg'], $loginDefaults['login_field_bg']);
    $fieldBorder = doom_normalize_hex_color($_POST['login_field_border'] ?? $loginDefaults['login_field_border'], $loginDefaults['login_field_border']);
    $fieldBorderWidthDefault = $isGlpi11 ? 1 : 0;
    $fieldBorderWidth = doom_clamp_int($_POST['login_field_border_width'] ?? $loginDefaults['login_field_border_width'], 0, 6, $fieldBorderWidthDefault);
    $fieldText = doom_normalize_hex_color($_POST['login_field_text'] ?? $loginDefaults['login_field_text'], $loginDefaults['login_field_text']);
    $fieldTextSizeDefault = $isGlpi11 ? 14 : 15;
    $fieldHeightDefault = $isGlpi11 ? 44 : 40;
    $fieldTextSize = doom_clamp_int($_POST['login_field_text_size'] ?? $loginDefaults['login_field_text_size'], 10, 36, $fieldTextSizeDefault);
    $fieldHeight = doom_clamp_int($_POST['login_field_height'] ?? $loginDefaults['login_field_height'], 32, 72, $fieldHeightDefault);
    $fieldWidthDefault = 280;
    $fieldWidth = doom_clamp_int($_POST['login_field_width'] ?? $loginDefaults['login_field_width'], 260, 720, $fieldWidthDefault);
    $fieldSpacing = null;
    $titleSize = null;
    if (!$isGlpi11) {
        $fieldSpacing = doom_clamp_int($_POST['login_field_spacing'] ?? $loginDefaults['login_field_spacing'], 8, 48, 20);
        $titleSize = doom_clamp_int($_POST['login_title_size'] ?? $loginDefaults['login_title_size'], 16, 48, 20);
    }
    $textSizeDefault = $isGlpi11 ? 15 : 14;
    $textSize = doom_clamp_int($_POST['login_text_size'] ?? $loginDefaults['login_text_size'], 10, 30, $textSizeDefault);
    $fieldFocus = doom_normalize_hex_color($_POST['login_field_focus'] ?? $loginDefaults['login_field_focus'], $loginDefaults['login_field_focus']);
    $accentColor = doom_normalize_hex_color($_POST['login_accent_color'] ?? $loginDefaults['login_accent_color'], $loginDefaults['login_accent_color']);
    $iconUser = $_POST['login_icon_user'] ?? $loginDefaults['login_icon_user'];
    $iconPassword = $_POST['login_icon_password'] ?? $loginDefaults['login_icon_password'];
    $iconColor = doom_normalize_hex_color($_POST['login_icon_color'] ?? $loginDefaults['login_icon_color'], $loginDefaults['login_icon_color']);
    $iconPosition = $_POST['login_icon_position'] ?? $loginDefaults['login_icon_position'];
    $iconUserOptions = ['none', 'user', 'id', 'mail'];
    if (!in_array($iconUser, $iconUserOptions, true)) {
        $iconUser = $loginDefaults['login_icon_user'];
    }
    $iconPasswordOptions = ['none', 'lock', 'key', 'shield'];
    if (!in_array($iconPassword, $iconPasswordOptions, true)) {
        $iconPassword = $loginDefaults['login_icon_password'];
    }
    if (!in_array($iconPosition, ['left', 'right'], true)) {
        $iconPosition = $loginDefaults['login_icon_position'];
    }
    $fieldRadius = doom_clamp_int($_POST['login_field_radius'] ?? $loginDefaults['login_field_radius'], 0, 32, 12);
    $wallpaperSize = $_POST['login_wallpaper_size'] ?? $loginDefaults['login_wallpaper_size'];
    $wallpaperSizeOptions = ['cover', 'contain', 'stretch', 'original'];
    if (!in_array($wallpaperSize, $wallpaperSizeOptions, true)) {
        $wallpaperSize = $loginDefaults['login_wallpaper_size'];
    }
    $wallpaperPosition = $_POST['login_wallpaper_position'] ?? $loginDefaults['login_wallpaper_position'];
    $wallpaperPositionOptions = ['center', 'top', 'bottom', 'left', 'right'];
    if (!in_array($wallpaperPosition, $wallpaperPositionOptions, true)) {
        $wallpaperPosition = $loginDefaults['login_wallpaper_position'];
    }
    $wallpaperAttachment = $_POST['login_wallpaper_attachment'] ?? $loginDefaults['login_wallpaper_attachment'];
    $wallpaperAttachmentOptions = ['fixed', 'scroll'];
    if (!in_array($wallpaperAttachment, $wallpaperAttachmentOptions, true)) {
        $wallpaperAttachment = $loginDefaults['login_wallpaper_attachment'];
    }
    $wallpaperOverlayOpacity = doom_clamp_int(
        $_POST['login_wallpaper_overlay_opacity'] ?? $loginDefaults['login_wallpaper_overlay_opacity'],
        0,
        100,
        35
    );
    $wallpaperOverlayColor = doom_normalize_hex_color(
        $_POST['login_wallpaper_overlay_color'] ?? $loginDefaults['login_wallpaper_overlay_color'],
        $loginDefaults['login_wallpaper_overlay_color']
    );
    $hideLoginText = ($_POST['login_hide_login_text'] ?? '0') === '1' ? '1' : '0';
    $loginLogoWidth = doom_clamp_int(
        $_POST['login_logo_width'] ?? ($loginConfig['login_logo_width'] ?? $loginDefaults['login_logo_width']),
        80,
        480,
        200
    );
    $loginLogoHeight = doom_clamp_int(
        $_POST['login_logo_height'] ?? ($loginConfig['login_logo_height'] ?? $loginDefaults['login_logo_height']),
        40,
        320,
        110
    );
    $loginLogoScale = doom_clamp_int(
        $_POST['login_logo_scale'] ?? ($loginConfig['login_logo_scale'] ?? $loginDefaults['login_logo_scale']),
        80,
        480,
        200
    );
    $loginLogoOffset = doom_clamp_int($_POST['login_logo_offset'] ?? $loginDefaults['login_logo_offset'], 0, 240, 0);

    $loginWallpaper = $loginConfig['login_wallpaper'] ?? '';
    $wallpaperSource = $_POST['login_wallpaper_source'] ?? ($loginConfig['login_wallpaper_source'] ?? '');
    if ($wallpaperSource === '') {
        $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
    }
    if (!in_array($wallpaperSource, ['preset', 'custom'], true)) {
        $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
    }
    $wallpaperPreset = $_POST['login_wallpaper_preset'] ?? ($loginConfig['login_wallpaper_preset'] ?? $wallpaperPresetDefault);
    if (!in_array($wallpaperPreset, $wallpaperPresets, true)) {
        $wallpaperPreset = $wallpaperPresetDefault;
    }
    $wallpaperBaseName = 'wallpaperLogin';
    $picsDir = doom_get_pics_dir();
    $wallpaperDir = $picsDir ? $picsDir . '/doom-wallpapers' : null;
    $wallpaperPath = $wallpaperDir ? $wallpaperDir . '/' . $wallpaperBaseName . '.png' : null;
    $wallpaperError = null;

    if (!empty($_POST['login_wallpaper_remove'])) {
        doom_delete_uploaded_wallpaper_variants($wallpaperBaseName);
        $loginWallpaper = '';
        $wallpaperSource = 'custom';
    } elseif (!empty($_FILES['login_wallpaper']) && ($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errorCode = (int) ($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_OK);
            $wallpaperError = doom_get_upload_error_message(
                $errorCode,
                sprintf(__('Wallpaper upload failed due to an unexpected error (code %d).', 'doom'), $errorCode)
            );
        } else {
            $extension = strtolower(pathinfo($_FILES['login_wallpaper']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedWallpaperExtensions, true)) {
                $wallpaperError = __('Allowed formats: PNG, JPG, JPEG, WEBP.', 'doom');
            } elseif (!is_uploaded_file($_FILES['login_wallpaper']['tmp_name'])) {
                $wallpaperError = __('The upload could not be validated.', 'doom');
            } elseif (!$wallpaperPath) {
                $wallpaperError = __('Wallpaper upload failed. Public pics path is unavailable.', 'doom');
            } else {
                if ($wallpaperDir && !is_dir($wallpaperDir) && !@mkdir($wallpaperDir, 0755, true)) {
                    $wallpaperError = __('Unable to create wallpaper directory.', 'doom');
                }
                if (!$wallpaperError) {
                    if (is_file($wallpaperPath)) {
                        @unlink($wallpaperPath);
                    }
                    $storedFilename = $wallpaperBaseName . '.png';
                    if ($extension === 'png') {
                        if (!@move_uploaded_file($_FILES['login_wallpaper']['tmp_name'], $wallpaperPath)) {
                            $wallpaperError = __('Unable to save the uploaded wallpaper. Check file permissions.', 'doom');
                        }
                    } else {
                        if (doom_convert_image_to_png($_FILES['login_wallpaper']['tmp_name'], $wallpaperPath)) {
                            $storedFilename = $wallpaperBaseName . '.png';
                        } elseif ($extension === 'webp') {
                            $storedFilename = $wallpaperBaseName . '.webp';
                            $webpPath = $wallpaperDir ? $wallpaperDir . '/' . $storedFilename : null;
                            if (!$webpPath || !@move_uploaded_file($_FILES['login_wallpaper']['tmp_name'], $webpPath)) {
                                $wallpaperError = __('Unable to process the uploaded wallpaper. Ensure the file is a valid image.', 'doom');
                            } else {
                                $wallpaperPath = $webpPath;
                            }
                        } else {
                            $wallpaperError = __('Unable to process the uploaded wallpaper. Ensure the file is a valid image.', 'doom');
                        }
                    }
                }

                if (!$wallpaperError) {
                    if (!Document::isImage($wallpaperPath)) {
                        @unlink($wallpaperPath);
                        $wallpaperError = __('The uploaded file is not a valid image.', 'doom');
                    } else {
                        $optimizedPath = $wallpaperPath . '.tmp';
                        if (doom_resize_image($wallpaperPath, $optimizedPath, 1920, 1080, 80)) {
                            @rename($optimizedPath, $wallpaperPath);
                        } elseif (is_file($optimizedPath)) {
                            @unlink($optimizedPath);
                        }
                    }
                    if (!$wallpaperError) {
                        $loginWallpaper = $storedFilename;
                        $wallpaperSource = 'custom';
                    }
                }
            }
        }
    }

    if ($wallpaperSource === 'custom' && $loginWallpaper && !$wallpaperError) {
        $wallpaperSource = 'custom';
    }

    $loginConfig = [
        'login_layout' => $layout,
        'login_panel_transparent' => $panelTransparent,
        'login_panel_hover_effect' => $panelHoverEffect,
        'login_panel_opacity' => (string) $panelOpacity,
        'login_panel_color' => $panelColor,
        'login_panel_blur' => (string) $panelBlur,
        'login_panel_width' => (string) $panelWidth,
        'login_panel_height' => (string) $panelHeight,
        'login_text_color' => $textColor,
        'login_logo_position' => $logoPosition,
        'login_split_opacity' => (string) $splitOpacity,
        'login_split_color' => $splitColor,
        'login_split_blur' => (string) $splitBlur,
        'login_split_width' => (string) $splitWidth,
        'login_button_style' => $buttonStyle,
        'login_button_shape' => $buttonShape,
        'login_button_color_mode' => $buttonColorMode,
        'login_button_color' => $buttonColor,
        'login_button_custom_style' => $buttonCustomStyle,
        'login_button_hover_effect' => $buttonHoverEffect,
        'login_button_hover_fill_mode' => $buttonHoverFillMode,
        'login_button_hover_fill_color' => $buttonHoverFillColor,
        'login_button_hover_text_color' => $buttonHoverTextColor,
        'login_button_text_size' => (string) $buttonTextSize,
        'login_field_style' => $fieldStyle,
        'login_field_bg' => $fieldBg,
        'login_field_border' => $fieldBorder,
        'login_field_border_width' => (string) $fieldBorderWidth,
        'login_field_text' => $fieldText,
        'login_field_text_size' => (string) $fieldTextSize,
        'login_field_height' => (string) $fieldHeight,
        'login_field_width' => (string) $fieldWidth,
        'login_text_size' => (string) $textSize,
        'login_field_focus' => $fieldFocus,
        'login_accent_color' => $accentColor,
        'login_icon_user' => $iconUser,
        'login_icon_password' => $iconPassword,
        'login_icon_color' => $iconColor,
        'login_icon_position' => $iconPosition,
        'login_field_radius' => (string) $fieldRadius,
        'login_wallpaper_size' => $wallpaperSize,
        'login_wallpaper_position' => $wallpaperPosition,
        'login_wallpaper_attachment' => $wallpaperAttachment,
        'login_wallpaper_overlay_color' => $wallpaperOverlayColor,
        'login_wallpaper_overlay_opacity' => (string) $wallpaperOverlayOpacity,
        'login_wallpaper_source' => $wallpaperSource,
        'login_wallpaper_preset' => $wallpaperPreset,
        'login_hide_login_text' => $hideLoginText,
        'login_wallpaper' => $loginWallpaper,
        'login_logo_width' => (string) $loginLogoWidth,
        'login_logo_height' => (string) $loginLogoHeight,
        'login_logo_scale' => (string) $loginLogoScale,
        'login_logo_offset' => (string) $loginLogoOffset,
    ];
    if (!$isGlpi11) {
        $loginConfig['login_field_spacing'] = (string) $fieldSpacing;
        $loginConfig['login_title_size'] = (string) $titleSize;
    }

    Config::setConfigurationValues('plugin:doom', $loginConfig);

    $cssWritten = doom_write_login_css($pluginDocDir, $loginConfig);

    if ($cssWritten) {
        Session::addMessageAfterRedirect(__('Login page settings saved.', 'doom'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Settings saved, but the login CSS file could not be written.', 'doom'), false, WARNING);
    }
    if ($wallpaperError) {
        Session::addMessageAfterRedirect($wallpaperError, false, WARNING);
    }

    Html::redirect($baseUrl . '?step=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['doom_action'] ?? '') === 'save_logo_scales') {
    $logoHeaderScale = doom_clamp_int($_POST['logo_header_scale'] ?? null, 40, 200, 100);
    $logoReducedScale = doom_clamp_int($_POST['logo_reduced_scale'] ?? null, 40, 200, 100);

    $logoScaleConfig = [
        'logo_header_scale' => (string) $logoHeaderScale,
        'logo_reduced_scale' => (string) $logoReducedScale,
    ];

    Config::setConfigurationValues('plugin:doom', $logoScaleConfig);

    Session::addMessageAfterRedirect(__('Logo sizes updated.', 'doom'), false, INFO);
    Html::redirect($baseUrl . '?step=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['doom_action'] ?? '') === 'save_login_logo_sizes') {
    $loginLogoWidth = doom_clamp_int(
        $_POST['login_logo_width'] ?? ($loginConfig['login_logo_width'] ?? $loginDefaults['login_logo_width']),
        80,
        480,
        200
    );
    $loginLogoHeight = doom_clamp_int(
        $_POST['login_logo_height'] ?? ($loginConfig['login_logo_height'] ?? $loginDefaults['login_logo_height']),
        40,
        320,
        110
    );
    $loginLogoScale = doom_clamp_int(
        $_POST['login_logo_scale'] ?? ($loginConfig['login_logo_scale'] ?? $loginDefaults['login_logo_scale']),
        80,
        480,
        200
    );
    $loginLogoOffset = doom_clamp_int($_POST['login_logo_offset'] ?? $loginDefaults['login_logo_offset'], 0, 240, 0);

    $logoConfig = [
        'login_logo_width' => (string) $loginLogoWidth,
        'login_logo_height' => (string) $loginLogoHeight,
        'login_logo_scale' => (string) $loginLogoScale,
        'login_logo_offset' => (string) $loginLogoOffset,
    ];

    Config::setConfigurationValues('plugin:doom', $logoConfig);

    $cssWritten = doom_write_login_css($pluginDocDir);

    if ($cssWritten) {
        Session::addMessageAfterRedirect(__('Login logo size updated.', 'doom'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Login logo size saved, but the login CSS file could not be written.', 'doom'), false, WARNING);
    }

    Html::redirect($baseUrl . '?step=2');
    exit;
}

if ($logosDir && !is_dir($logosDir)) {
    @mkdir($logosDir, 0755, true);
}

Html::header(__('Doom', 'doom'), $_SERVER['PHP_SELF'], 'plugins', 'doom');

?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

    .doom-wrapper {
        margin: 16px 0 28px 0;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
        overflow: visible;
        font-family: 'Sora', 'Segoe UI', sans-serif;
        --doom-primary: #0ea5e9;
        --doom-secondary: #22c55e;
        --doom-ink: #0f172a;
        --doom-muted: #64748b;
        --doom-surface: #f8fafc;
        --doom-border: #e2e8f0;
    }

    .doom-hero {
        padding: 26px 28px 24px;
        background: linear-gradient(135deg, var(--doom-primary), var(--doom-secondary));
        color: #ffffff;
        position: relative;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        overflow: hidden;
    }

    .doom-hero-inner {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 12px;
    }

    .doom-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.35), transparent 60%);
        pointer-events: none;
    }

    .doom-hero h2 {
        margin: 0 0 6px 0;
        font-weight: 600;
        font-size: 1.6rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .doom-hero-version {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.22);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.04em;
    }

    .doom-hero p {
        margin: 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .doom-hero-links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 10px;
        align-items: center;
    }

    .doom-hero-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 500;
        transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }

    .doom-hero-link:hover {
        color: #ffffff;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.26);
        border-color: rgba(255, 255, 255, 0.42);
        transform: translateY(-1px);
    }

    .doom-hero-link i {
        font-size: 0.95rem;
    }

    .doom-steps {
        display: flex;
        gap: 12px;
        padding: 16px 24px 0 24px;
        flex-wrap: wrap;
    }

    .doom-step {
        flex: 1;
        min-width: 200px;
        border-radius: 10px;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        text-decoration: none;
        color: #0f172a;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .doom-step.active {
        border-color: #38bdf8;
        background: #e0f2fe;
        box-shadow: 0 6px 18px rgba(14, 165, 233, 0.18);
        transform: translateY(-2px);
    }

    .doom-step small {
        display: block;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.68rem;
        color: #64748b;
        margin-bottom: 6px;
    }

    .doom-content {
        padding: 20px 24px 24px 24px;
    }

    .doom-timeline {
        display: flex;
        align-items: center;
        gap: 24px;
        margin: 16px 24px 0 24px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--doom-border);
    }

    .doom-timeline-step {
        display: inline-flex;
        align-items: center;
        padding: 12px 2px 14px;
        border-radius: 0;
        border: none;
        background: transparent;
        color: var(--doom-muted);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        position: relative;
        transition: color 0.2s ease;
    }

    .doom-timeline-step::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 3px;
        border-radius: 999px;
        background: transparent;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .doom-timeline-step.active {
        color: var(--doom-ink);
    }

    .doom-timeline-step.active::after {
        background: linear-gradient(90deg, var(--doom-primary), var(--doom-secondary));
    }

    .doom-timeline-step:hover {
        color: var(--doom-ink);
    }

    .doom-timeline-step:hover::after {
        background: rgba(14, 165, 233, 0.4);
    }

    .doom-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .doom-card {
        position: relative;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        background: #ffffff;
        overflow: visible;
        z-index: 1;
    }

    .doom-card:hover {
        z-index: 5;
    }

    .doom-card h4 {
        margin: 0 0 8px 0;
        font-size: 0.95rem;
    }

    .doom-placeholder {
        border-radius: 8px;
        border: 1px dashed #cbd5f5;
        padding: 12px;
        color: #475569;
        background: #f8fafc;
        font-size: 0.9rem;
    }

    .doom-actions {
        margin-top: 18px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .doom-actions a,
    .doom-actions button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        background: #0ea5e9;
        color: #ffffff;
        text-decoration: none;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .doom-actions a.secondary {
        background: #94a3b8;
    }

    .doom-actions .doom-save-logo {
        background: linear-gradient(135deg, #22c55e, #0ea5e9);
        box-shadow: none;
    }

    .doom-actions .doom-save-logo:hover {
        background: linear-gradient(135deg, #16a34a, #0284c7);
    }

    .doom-actions .doom-save-logo:focus {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }

    .doom-actions-floating {
        position: fixed;
        right: 24px;
        bottom: 24px;
        margin: 0;
        padding: 0;
        border-radius: 0;
        background: transparent;
        border: none;
        box-shadow: none;
        z-index: 100;
        flex-wrap: wrap;
    }

    .doom-actions-floating .doom-save-login {
        padding: 10px 18px;
        border-radius: 8px;
        background: linear-gradient(135deg, #22c55e, #0ea5e9);
        box-shadow: none;
        text-transform: none;
        letter-spacing: normal;
        font-size: 0.85rem;
        border: none;
    }

    .doom-actions-floating .doom-save-login:hover {
        background: linear-gradient(135deg, #16a34a, #0284c7);
    }

    .doom-actions-floating .doom-save-login:focus {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }

    .doom-actions-floating .doom-reset-login {
        padding: 10px 18px;
        border-radius: 8px;
        background: #64748b;
        box-shadow: none;
        font-size: 0.85rem;
        border: none;
    }

    .doom-actions-floating .doom-reset-login:hover {
        background: #475569;
    }

    .doom-actions-floating .doom-reset-login:focus {
        outline: 2px solid #cbd5f5;
        outline-offset: 2px;
    }

    @media (max-width: 720px) {
        .doom-actions-floating {
            left: 16px;
            right: 16px;
            bottom: 16px;
            border-radius: 0;
            justify-content: center;
        }

        .doom-actions-floating button {
            width: 100%;
            justify-content: center;
        }

        .doom-hero-links {
            gap: 8px;
        }

        .doom-hero-link {
            justify-content: center;
        }
    }

    .doom-intro {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
        gap: 24px;
        align-items: stretch;
    }

    .doom-intro-copy {
        display: grid;
        gap: 16px;
        align-content: start;
    }

    .doom-intro-copy > * {
        animation: doom-fade-up 0.5s ease both;
    }

    .doom-intro-copy > *:nth-child(2) {
        animation-delay: 0.05s;
    }

    .doom-intro-copy > *:nth-child(3) {
        animation-delay: 0.1s;
    }

    .doom-intro-copy > *:nth-child(4) {
        animation-delay: 0.15s;
    }

    .doom-intro-eyebrow {
        text-transform: uppercase;
        letter-spacing: 0.18em;
        font-size: 0.7rem;
        color: var(--doom-muted);
        font-weight: 600;
    }

    .doom-intro h3 {
        font-size: 1.6rem;
        margin: 0;
        color: var(--doom-ink);
    }

    .doom-intro p {
        margin: 0;
        color: #475569;
        line-height: 1.6;
    }

    .doom-intro-copy a {
        color: var(--doom-primary);
        font-weight: 600;
        text-decoration: none;
    }

    .doom-intro-copy a:hover {
        text-decoration: underline;
    }

    .doom-intro-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .doom-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .doom-cta.primary {
        background: linear-gradient(135deg, var(--doom-primary), var(--doom-secondary));
        color: #ffffff;
        box-shadow: 0 12px 24px rgba(14, 165, 233, 0.2);
    }

    .doom-cta.secondary {
        background: #ffffff;
        color: var(--doom-ink);
        border-color: var(--doom-border);
    }

    .doom-cta.ghost {
        background: rgba(14, 165, 233, 0.08);
        color: var(--doom-ink);
    }

    .doom-intro-panel {
        position: relative;
        border-radius: 16px;
        padding: 18px;
        background: linear-gradient(140deg, rgba(14, 165, 233, 0.12), rgba(34, 197, 94, 0.16));
        border: 1px solid rgba(14, 165, 233, 0.2);
        display: grid;
        gap: 12px;
        overflow: hidden;
    }

    .doom-intro-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.6), transparent 60%);
        pointer-events: none;
    }

    .doom-intro-card {
        position: relative;
        padding: 14px 16px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
        animation: doom-fade-up 0.5s ease both;
    }

    .doom-intro-card:nth-child(2) {
        animation-delay: 0.08s;
    }

    .doom-intro-card:nth-child(3) {
        animation-delay: 0.16s;
    }

    .doom-intro-card span {
        display: inline-flex;
        font-weight: 700;
        color: var(--doom-primary);
        font-size: 0.9rem;
    }

    .doom-intro-card h4 {
        margin: 6px 0 6px 0;
        font-size: 1rem;
        color: var(--doom-ink);
    }

    .doom-intro-card p {
        margin: 0;
        color: #475569;
        font-size: 0.9rem;
    }

    @keyframes doom-fade-up {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 960px) {
        .doom-intro {
            grid-template-columns: 1fr;
        }
    }

    .doom-logo-preview img {
        max-width: 100%;
        max-height: 80px;
        display: block;
        margin: 8px 0 0 0;
    }

    .doom-logo-card {
        position: relative;
        overflow: visible;
        background: linear-gradient(145deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 0.7));
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .doom-logo-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin: 8px 0 12px 0;
    }

    .doom-logo-action {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        border: none;
        color: #ffffff;
        cursor: pointer;
        font-size: 13px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.35);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .doom-logo-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
        filter: saturate(1.05);
    }

    .doom-logo-remove {
        background: linear-gradient(140deg, #ef4444, #f97316);
    }

    .doom-logo-upload {
        background: linear-gradient(140deg, #0ea5e9, #22c55e);
    }

    .doom-logo-stage {
        border-radius: 12px;
        border: 1px solid #ffffff;
        background: #ffffff;
        box-shadow: inset 0 0 0 1px #e2e8f0;
        padding: 12px;
        display: grid;
        gap: 10px;
        min-height: 120px;
    }

    .doom-logo-stage.dark {
        background: linear-gradient(160deg, #0b1220, #111827);
        border-color: rgba(255, 255, 255, 0.12);
    }

    .doom-logo-stage .logo-sample {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 4px 10px;
        border-radius: 8px;
        background: #e2e8f0;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
        position: relative;
        overflow: hidden;
        gap: 6px;
    }

    .doom-logo-stage.dark .logo-sample {
        background: rgba(15, 23, 42, 0.75);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .doom-logo-stage img {
        max-height: 26px;
        max-width: 100%;
        display: block;
        transform-origin: center;
    }

    .doom-logo-stage img[data-doom-logo-scale="header"] {
        transform: scale(var(--doom-logo-preview-header-scale, 1));
    }

    .doom-logo-stage img[data-doom-logo-scale="reduced"] {
        transform: scale(var(--doom-logo-preview-reduced-scale, 1));
    }

    .doom-logo-stage img.doom-scale-pulse {
        animation: doom-scale-pulse 260ms ease-out;
    }

    .doom-logo-preview-value {
        font-size: 0.62rem;
        padding: 2px 6px;
        border-radius: 999px;
        color: #0f172a;
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(148, 163, 184, 0.4);
        letter-spacing: 0.02em;
        opacity: 0;
        transform: translateY(2px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        white-space: nowrap;
    }

    .doom-logo-stage .logo-sample.doom-preview-active .doom-logo-preview-value {
        opacity: 1;
        transform: translateY(0);
    }

    .doom-logo-stage.dark .doom-logo-preview-value {
        color: #f8fafc;
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(148, 163, 184, 0.35);
    }

    .doom-save-logo.doom-save-pulse {
        animation: doom-save-pulse 480ms ease-out;
    }

    @keyframes doom-scale-pulse {
        0% {
            filter: brightness(1);
            opacity: 1;
        }
        60% {
            filter: brightness(1.08);
            opacity: 0.98;
        }
        100% {
            filter: brightness(1);
            opacity: 1;
        }
    }

    @keyframes doom-save-pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 rgba(34, 197, 94, 0);
        }
        50% {
            transform: scale(1.04);
            box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.25);
        }
        100% {
            transform: scale(1);
            box-shadow: 0 0 0 rgba(34, 197, 94, 0);
        }
    }

    .doom-logo-header-sample,
    .doom-logo-login-sample {
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: inset 0 0 0 1px #f8fafc;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .doom-logo-stage.dark .doom-logo-header-sample,
    .doom-logo-stage.dark .doom-logo-login-sample {
        background: rgba(255, 255, 255, 0.08);
    }

    .doom-logo-login-sample {
        flex-direction: column;
        align-items: center;
        gap: 8px;
        min-height: 80px;
    }

    .doom-logo-login-field {
        width: 80%;
        height: 8px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.12);
    }

    .doom-logo-stage.dark .doom-logo-login-field {
        background: rgba(255, 255, 255, 0.2);
    }

    .doom-logo-stage.dark .doom-placeholder {
        color: #e2e8f0;
        border-color: rgba(226, 232, 240, 0.3);
        background: rgba(15, 23, 42, 0.4);
    }

    .doom-wallpaper-card {
        position: relative;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #0f172a, #1e293b);
        min-height: 0;
        overflow: hidden;
        padding: 14px;
    }

    .doom-wallpaper-preview {
        position: relative;
        width: 100%;
        max-width: 360px;
        aspect-ratio: 16 / 9;
        height: auto;
        margin: 6px auto;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.45);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.35);
        background-color: #0f172a;
        background-image: var(--doom-wallpaper-background, none);
        background-size: var(--doom-wallpaper-preview-size, contain);
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: scroll;
        transition: background 0.2s ease, background-position 0.2s ease, background-size 0.2s ease;
    }

    .doom-wallpaper-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 10px;
        display: block;
    }

    .doom-wallpaper-placeholder {
        position: absolute;
        inset: 0;
        border-radius: 10px;
        border: 1px dashed rgba(226, 232, 240, 0.5);
        color: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        background: rgba(15, 23, 42, 0.4);
    }

    .doom-wallpaper-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        gap: 8px;
        z-index: 2;
    }

    .doom-logo-input {
        display: none;
    }

    .doom-form {
        display: grid;
        gap: 12px;
    }

    .doom-form label {
        font-weight: 600;
        display: block;
        margin-bottom: 6px;
    }

    .doom-inline-form {
        margin-top: 10px;
    }

    .doom-inline-form button {
        background: #ef4444;
        color: #ffffff;
        border: none;
        padding: 6px 10px;
        border-radius: 6px;
        cursor: pointer;
    }

    .doom-option-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .doom-option {
        display: block;
        cursor: pointer;
    }

    .doom-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .doom-option-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 12px;
        background: #ffffff;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .doom-option input:checked + .doom-option-card {
        border-color: #0ea5e9;
        box-shadow: 0 8px 20px rgba(14, 165, 233, 0.18);
        transform: translateY(-2px);
    }

    .doom-option-card h4 {
        margin: 10px 0 4px 0;
        font-size: 0.95rem;
    }

    .doom-option-card p {
        margin: 0;
        color: #64748b;
        font-size: 0.85rem;
    }

    .doom-button-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .doom-button-stage {
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #0f172a, #1e293b);
        height: 120px;
        position: relative;
        overflow: hidden;
        padding: 12px;
        display: flex;
        align-items: flex-end;
    }

    .doom-button-stage::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(14, 165, 233, 0.35), transparent 65%);
    }

    .doom-button-stage::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image: linear-gradient(120deg, rgba(255, 255, 255, 0.08) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.08) 50%, rgba(255, 255, 255, 0.08) 75%, transparent 75%, transparent);
        background-size: 18px 18px;
        opacity: 0.2;
    }

    .doom-button-panel {
        position: relative;
        z-index: 1;
        width: 100%;
        background: rgba(15, 23, 42, 0.7);
        border-radius: 12px;
        padding: 10px;
        display: grid;
        gap: 6px;
    }

    .doom-button-panel-line {
        height: 8px;
        border-radius: 999px;
        background: rgba(226, 232, 240, 0.6);
    }

    .doom-button-demo {
        border: none;
        color: #ffffff;
        background: #0ea5e9;
        padding: 6px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        cursor: default;
        justify-self: start;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .doom-button-demo--square {
        border-radius: 4px;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.24);
    }

    .doom-button-demo--rounded {
        border-radius: 10px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.25);
    }

    .doom-button-demo--pill {
        border-radius: 999px;
        padding-left: 18px;
        padding-right: 18px;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.25);
    }

    .doom-button-demo--cut {
        border-radius: 6px 16px 6px 16px;
        clip-path: polygon(0 0, 92% 0, 100% 35%, 100% 100%, 8% 100%, 0 65%);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.28);
    }

    .doom-button-demo--soft {
        border-radius: 14px;
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }

    .doom-color-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }

    .doom-color-stage {
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #0f172a, #1f2937);
        height: 120px;
        position: relative;
        overflow: hidden;
        padding: 12px;
        display: flex;
        align-items: flex-end;
    }

    .doom-color-stage::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.3), transparent 60%);
    }

    .doom-color-panel {
        position: relative;
        z-index: 1;
        width: 100%;
        background: rgba(15, 23, 42, 0.75);
        border-radius: 12px;
        padding: 10px;
        display: grid;
        gap: 6px;
    }

    .doom-color-panel-line {
        height: 8px;
        border-radius: 999px;
        background: rgba(226, 232, 240, 0.6);
    }

    .doom-color-preview {
        border: 2px solid transparent;
    }

    .doom-color-preview--primary {
        background: #0d6efd;
    }

    .doom-color-preview--secondary {
        background: #6c757d;
    }

    .doom-color-preview--success {
        background: #198754;
    }

    .doom-color-preview--danger {
        background: #dc3545;
    }

    .doom-color-preview--warning {
        background: #ffc107;
        color: #111827;
    }

    .doom-color-preview--info {
        background: #0dcaf0;
        color: #0f172a;
    }

    .doom-color-preview--dark {
        background: #212529;
    }

    .doom-color-preview--light {
        background: #f8f9fa;
        color: #0f172a;
    }

    .doom-color-preview--outline-primary {
        background: transparent;
        border-color: #0d6efd;
        color: #0d6efd;
    }

    .doom-color-preview--outline-secondary {
        background: transparent;
        border-color: #6c757d;
        color: #6c757d;
    }

    .doom-color-preview--outline-success {
        background: transparent;
        border-color: #198754;
        color: #198754;
    }

    .doom-color-preview--outline-danger {
        background: transparent;
        border-color: #dc3545;
        color: #dc3545;
    }

    .doom-color-preview--outline-warning {
        background: transparent;
        border-color: #ffc107;
        color: #ffc107;
    }

    .doom-color-preview--outline-info {
        background: transparent;
        border-color: #0dcaf0;
        color: #0dcaf0;
    }

    .doom-color-preview--outline-dark {
        background: transparent;
        border-color: #212529;
        color: #212529;
    }

    .doom-color-inputs {
        display: grid;
        gap: 10px;
    }

    .doom-color-inputs .doom-inline-field {
        display: grid;
        gap: 6px;
    }

    .doom-color-inputs input[type="text"] {
        text-transform: uppercase;
    }

    .doom-color-preview-custom {
        border-radius: 999px;
        height: 12px;
        width: 80%;
        background: var(--doom-custom-color, #0ea5e9);
    }

    .doom-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .doom-form-grid--button {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px 22px;
    }

    .doom-form-grid--button .doom-color-custom {
        grid-column: 1 / -1;
        padding-top: 4px;
    }

    .doom-form-grid--button .doom-color-inputs {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .doom-form-grid > div {
        min-width: 0;
    }

    .doom-form-grid .doom-grid-full {
        grid-column: 1 / -1;
    }

    .doom-form-grid label {
        font-weight: 600;
        display: block;
        margin-bottom: 6px;
    }

    .doom-range {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        row-gap: 8px;
        padding: 6px 8px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid rgba(14, 165, 233, 0.25);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
    }

    .doom-range-value {
        margin-left: auto;
        white-space: nowrap;
    }

    .doom-wrapper input[type="range"] {
        flex: 1 1 160px;
        appearance: none;
        -webkit-appearance: none;
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--doom-primary), var(--doom-secondary));
        background-repeat: no-repeat;
        background-size: 100% 100%;
        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.65);
        outline: none;
    }

    .doom-wrapper input[type="range"]::-webkit-slider-runnable-track {
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--doom-primary), var(--doom-secondary));
    }

    .doom-wrapper input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ffffff;
        border: 2px solid var(--doom-primary);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.16);
        cursor: pointer;
        margin-top: -4px;
    }

    .doom-wrapper input[type="range"]::-moz-range-track {
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--doom-primary), var(--doom-secondary));
    }

    .doom-wrapper input[type="range"]::-moz-range-thumb {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ffffff;
        border: 2px solid var(--doom-primary);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.16);
        cursor: pointer;
    }

    .doom-wrapper input[type="range"]::-moz-range-progress {
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--doom-primary), var(--doom-secondary));
    }

    .doom-range-value {
        min-width: 60px;
        text-align: center;
        font-weight: 600;
        color: #0f172a;
        padding: 4px 10px;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.5);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }

    .doom-step2-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
        align-items: stretch;
        margin-top: 16px;
    }

    .doom-step2-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 280px;
        gap: 18px;
        align-items: stretch;
        border: 1px solid #94a3b8;
        border-radius: 14px;
        padding: 16px 18px;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
    }

    .doom-step2-row .doom-section {
        border: none;
        padding: 0;
        background: transparent;
    }

    .doom-step2-preview {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 12px;
        min-height: 210px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .doom-step2-grid .doom-section {
        min-width: 0;
    }

    .doom-section-wide {
        border: 1px solid #94a3b8;
        border-radius: 14px;
        padding: 16px 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }

    .doom-section {
        position: relative;
        border: 1px solid #94a3b8;
        border-radius: 14px;
        padding: 16px 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        overflow: visible;
        z-index: 0;
    }

    .doom-section h4 {
        margin: 0 0 12px 0;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        color: #0f172a;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .doom-section h4::before {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--doom-primary), var(--doom-secondary));
        box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.15);
    }

    .doom-info {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 1px solid #94a3b8;
        color: #334155;
        font-size: 0.7rem;
        font-weight: 700;
        background: #ffffff;
        cursor: help;
        line-height: 1;
        transform: translateY(-1px);
        z-index: 6;
    }

    .doom-info::after {
        content: attr(data-info);
        position: absolute;
        left: 50%;
        bottom: calc(100% + 10px);
        transform: translateX(-50%);
        min-width: 220px;
        max-width: 280px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #0f172a;
        color: #e2e8f0;
        font-size: 0.78rem;
        font-weight: 500;
        line-height: 1.3;
        text-align: left;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.25);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
        z-index: 60;
    }

    .doom-info::before {
        content: '';
        position: absolute;
        left: 50%;
        bottom: calc(100% + 4px);
        transform: translateX(-50%);
        border-width: 6px 6px 0 6px;
        border-style: solid;
        border-color: #0f172a transparent transparent transparent;
        opacity: 0;
        transition: opacity 0.18s ease, transform 0.18s ease;
        z-index: 59;
    }

    .doom-info:hover::after,
    .doom-info:focus-visible::after {
        opacity: 1;
        transform: translateX(-50%) translateY(-2px);
    }

    .doom-info:hover::before,
    .doom-info:focus-visible::before {
        opacity: 1;
        transform: translateX(-50%) translateY(-2px);
    }

    .doom-section + .doom-section {
        margin-top: 20px;
    }

    .doom-section + form {
        margin-top: 20px;
    }

    form + .doom-section {
        margin-top: 20px;
    }

    .doom-step2-grid .doom-section + .doom-section {
        margin-top: 0;
    }

    @media (max-width: 900px) {
        .doom-step2-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {

        .doom-form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .doom-range {
            flex-wrap: wrap;
        }

        .doom-range-value {
            width: 100%;
        }
    }

    .doom-section summary {
        cursor: pointer;
        font-weight: 600;
        list-style: none;
    }

    .doom-section summary::-webkit-details-marker {
        display: none;
    }

    .doom-toggle-hidden {
        display: none;
    }

    .doom-muted {
        color: #64748b;
        font-size: 0.85rem;
        margin-top: 6px;
    }

    .doom-preview-shell {
        width: 100%;
        height: 210px;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        background: #0f172a;
        display: flex;
        align-items: center;
        justify-content: var(--doom-preview-panel-align, center);
        padding: 10px;
    }

    .doom-preview-shell.light {
        background: #eef2f7;
    }

    .doom-preview-split {
        position: absolute;
        top: 0;
        bottom: 0;
        width: var(--doom-preview-split-width, 50%);
        background: var(--doom-preview-split-color, #cbd5e1);
        left: 0;
        opacity: var(--doom-preview-split-visibility, 1);
        transition: opacity 0.2s ease, background 0.2s ease;
        backdrop-filter: blur(var(--doom-preview-split-blur, 0px));
        -webkit-backdrop-filter: blur(var(--doom-preview-split-blur, 0px));
    }

    .doom-preview-shell[data-layout="split-right"] .doom-preview-split {
        left: auto;
        right: 0;
    }

    .doom-preview-shell[data-layout="classic"] .doom-preview-split {
        opacity: 0;
    }

    .doom-preview-panel {
        position: relative;
        z-index: 2;
        width: var(--doom-preview-panel-width, 64%);
        height: var(--doom-preview-panel-height, 68%);
        background: var(--doom-preview-panel-color, rgba(15, 23, 42, 0.7));
        border-radius: 12px;
        display: grid;
        gap: 8px;
        padding: 10px;
        align-content: center;
        opacity: var(--doom-preview-panel-opacity, 1);
        box-shadow: var(--doom-preview-panel-shadow, 0 10px 24px rgba(15, 23, 42, 0.25));
        border: var(--doom-preview-panel-border, 1px solid rgba(148, 163, 184, 0.4));
    }

    .doom-preview-shell[data-logo-position="outside"] .doom-preview-logo {
        position: absolute;
        left: 50%;
        top: calc(12px + var(--doom-preview-logo-offset, 0px));
        transform: translateX(-50%);
        margin: 0;
    }

    .doom-preview-shell[data-logo-position="outside"] .doom-preview-panel {
        padding-top: 22px;
    }

    .doom-preview-shell[data-login-text-hidden="1"] .doom-preview-title {
        display: none;
    }

    .doom-preview-shell[data-logo-position="outside"] .doom-preview-login {
        margin-top: 6px;
    }

    .doom-preview-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 22px;
        width: var(--doom-preview-logo-width, 60%);
        border-radius: 6px;
        border: 1px solid rgba(148, 163, 184, 0.7);
        color: var(--doom-preview-text, #e2e8f0);
        font-size: 0.62rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: rgba(15, 23, 42, 0.25);
        justify-self: center;
    }

    .doom-preview-login {
        display: grid;
        gap: 8px;
    }

    .doom-preview-title {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--doom-preview-text, #e2e8f0);
    }

    .doom-preview-field-label {
        font-size: 0.55rem;
        color: var(--doom-preview-text, #e2e8f0);
        opacity: 0.85;
    }

    .doom-preview-field {
        width: min(100%, var(--doom-preview-field-width, 100%));
        max-width: var(--doom-preview-field-width, 100%);
        height: var(--doom-preview-field-height, 36px);
        border-radius: var(--doom-preview-field-radius, 10px);
        background: var(--doom-preview-field-bg, rgba(255, 255, 255, 0.85));
        border: var(--doom-preview-field-border-width, 1px) solid var(--doom-preview-field-border, rgba(148, 163, 184, 0.7));
        box-shadow: var(--doom-preview-field-shadow, none);
        display: flex;
        align-items: center;
        padding: 0 10px;
        transition: border-width 0.2s ease, background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, height 0.2s ease, transform 0.2s ease;
    }

    .doom-preview-field-text {
        font-size: var(--doom-preview-field-text-size, 14px);
        color: var(--doom-preview-field-text-color, #111827);
        opacity: 0.85;
        transition: font-size 0.2s ease;
    }

    .doom-preview-fields.doom-field-pulse .doom-preview-field,
    .doom-preview-fields.doom-field-pulse .doom-preview-field-text {
        animation: doom-field-bump 0.35s ease;
    }

    @keyframes doom-field-bump {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.02);
            opacity: 0.9;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .doom-preview-button {
        border: none;
        border-radius: 10px;
        padding: 6px 12px;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: var(--doom-preview-accent, #0ea5e9);
        color: #ffffff;
        width: fit-content;
    }

    .doom-preview-line {
        height: 8px;
        border-radius: 999px;
        background: rgba(248, 250, 252, 0.6);
    }

    .doom-preview-line.small {
        width: 65%;
        justify-self: center;
    }

    .doom-preview-fields {
        background: var(--doom-preview-field-surface, #f1f5f9);
        display: grid;
        gap: 10px;
        padding: 12px;
        transition: background 0.2s ease;
    }

    .doom-preview-field {
        width: min(100%, var(--doom-preview-field-width, 100%));
        max-width: var(--doom-preview-field-width, 100%);
        height: var(--doom-preview-field-height, 36px);
        border-radius: var(--doom-preview-field-radius, 10px);
        background: var(--doom-preview-field-bg, #ffffff);
        border: var(--doom-preview-field-border-width, 1px) solid var(--doom-preview-field-border, #cbd5f5);
        box-shadow: var(--doom-preview-field-shadow, none);
    }

    .doom-preview-icons {
        background: var(--doom-preview-field-surface, #f1f5f9);
        padding: 12px;
        display: grid;
        gap: 10px;
        transition: background 0.2s ease;
    }

    .doom-preview-icon-field {
        display: grid;
    }

    .doom-preview-field--icon {
        position: relative;
        padding-left: 34px;
        padding-right: 10px;
    }

    .doom-preview-icons.icon-right .doom-preview-field--icon {
        padding-left: 10px;
        padding-right: 34px;
    }

    .doom-preview-field-icon {
        width: 18px;
        height: 18px;
        display: block;
        color: var(--doom-preview-icon-color, #0ea5e9);
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
    }

    .doom-preview-icons.icon-right .doom-preview-field-icon {
        left: auto;
        right: 10px;
    }

    .doom-preview-field-icon svg {
        width: 18px;
        height: 18px;
        display: block;
    }

    .doom-preview-icons[data-login-icon="user"] .doom-preview-field[data-field="login"] .doom-preview-field-icon[data-doom-icon="user"],
    .doom-preview-icons[data-login-icon="id"] .doom-preview-field[data-field="login"] .doom-preview-field-icon[data-doom-icon="id"],
    .doom-preview-icons[data-login-icon="mail"] .doom-preview-field[data-field="login"] .doom-preview-field-icon[data-doom-icon="mail"],
    .doom-preview-icons[data-password-icon="lock"] .doom-preview-field[data-field="password"] .doom-preview-field-icon[data-doom-icon="lock"],
    .doom-preview-icons[data-password-icon="key"] .doom-preview-field[data-field="password"] .doom-preview-field-icon[data-doom-icon="key"],
    .doom-preview-icons[data-password-icon="shield"] .doom-preview-field[data-field="password"] .doom-preview-field-icon[data-doom-icon="shield"] {
        display: block;
    }

    .doom-preview-icons[data-login-icon="none"] .doom-preview-field[data-field="login"],
    .doom-preview-icons[data-password-icon="none"] .doom-preview-field[data-field="password"] {
        padding-left: 10px;
        padding-right: 10px;
    }

    .doom-preview-icons .doom-preview-field {
        flex: 1;
    }

    .doom-icon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }

    .doom-icon-option {
        position: relative;
        display: block;
        cursor: pointer;
    }

    .doom-icon-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .doom-icon-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        background: var(--doom-icon-bg, #ffffff);
        display: grid;
        gap: 8px;
        justify-items: center;
        align-items: center;
        color: var(--doom-icon-color, #0ea5e9);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .doom-icon-card svg {
        width: 26px;
        height: 26px;
        display: block;
    }

    .doom-icon-placeholder {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        border: 2px dashed rgba(148, 163, 184, 0.7);
        opacity: 0.8;
    }

    .doom-icon-card span {
        font-size: 0.78rem;
        color: #64748b;
        text-align: center;
    }

    .doom-icon-option input:checked + .doom-icon-card {
        border-color: #38bdf8;
        box-shadow: 0 8px 18px rgba(14, 165, 233, 0.2);
        transform: translateY(-1px);
    }

    :root[data-glpi-theme-dark="1"] .doom-wrapper,
    :root[glpi-theme-dark="1"] .doom-wrapper {
        background: #111827;
        border-color: #334155;
        box-shadow: 0 12px 28px rgba(2, 6, 23, 0.35);
        --doom-ink: #e5e7eb;
        --doom-muted: #94a3b8;
        --doom-surface: #0f172a;
        --doom-border: #334155;
        color: var(--doom-ink);
    }

    :root[data-glpi-theme-dark="1"] .doom-wrapper h3,
    :root[data-glpi-theme-dark="1"] .doom-wrapper h4,
    :root[data-glpi-theme-dark="1"] .doom-wrapper label,
    :root[data-glpi-theme-dark="1"] .doom-wrapper summary,
    :root[glpi-theme-dark="1"] .doom-wrapper h3,
    :root[glpi-theme-dark="1"] .doom-wrapper h4,
    :root[glpi-theme-dark="1"] .doom-wrapper label,
    :root[glpi-theme-dark="1"] .doom-wrapper summary {
        color: var(--doom-ink);
    }

    :root[data-glpi-theme-dark="1"] .doom-step,
    :root[data-glpi-theme-dark="1"] .doom-card,
    :root[data-glpi-theme-dark="1"] .doom-logo-card,
    :root[data-glpi-theme-dark="1"] .doom-option-card,
    :root[data-glpi-theme-dark="1"] .doom-icon-card,
    :root[data-glpi-theme-dark="1"] .doom-step2-row,
    :root[data-glpi-theme-dark="1"] .doom-section,
    :root[data-glpi-theme-dark="1"] .doom-section-wide,
    :root[data-glpi-theme-dark="1"] .doom-step2-preview,
    :root[data-glpi-theme-dark="1"] .doom-range,
    :root[data-glpi-theme-dark="1"] .doom-cta.secondary,
    :root[data-glpi-theme-dark="1"] .doom-cta.ghost,
    :root[data-glpi-theme-dark="1"] .doom-info,
    :root[glpi-theme-dark="1"] .doom-step,
    :root[glpi-theme-dark="1"] .doom-card,
    :root[glpi-theme-dark="1"] .doom-logo-card,
    :root[glpi-theme-dark="1"] .doom-option-card,
    :root[glpi-theme-dark="1"] .doom-icon-card,
    :root[glpi-theme-dark="1"] .doom-step2-row,
    :root[glpi-theme-dark="1"] .doom-section,
    :root[glpi-theme-dark="1"] .doom-section-wide,
    :root[glpi-theme-dark="1"] .doom-step2-preview,
    :root[glpi-theme-dark="1"] .doom-range,
    :root[glpi-theme-dark="1"] .doom-cta.secondary,
    :root[glpi-theme-dark="1"] .doom-cta.ghost,
    :root[glpi-theme-dark="1"] .doom-info {
        background: #0f172a;
        border-color: #334155;
        color: var(--doom-ink);
        box-shadow: 0 10px 24px rgba(2, 6, 23, 0.24);
    }

    :root[data-glpi-theme-dark="1"] .doom-logo-card,
    :root[glpi-theme-dark="1"] .doom-logo-card {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.96), rgba(17, 24, 39, 0.92));
        border-color: rgba(51, 65, 85, 0.95);
    }

    :root[data-glpi-theme-dark="1"] .doom-step.active,
    :root[glpi-theme-dark="1"] .doom-step.active {
        background: rgba(14, 165, 233, 0.14);
        border-color: rgba(56, 189, 248, 0.5);
        box-shadow: 0 8px 20px rgba(14, 165, 233, 0.14);
    }

    :root[data-glpi-theme-dark="1"] .doom-intro p,
    :root[data-glpi-theme-dark="1"] .doom-option-card p,
    :root[data-glpi-theme-dark="1"] .doom-icon-card span,
    :root[data-glpi-theme-dark="1"] .doom-muted,
    :root[data-glpi-theme-dark="1"] .doom-placeholder,
    :root[data-glpi-theme-dark="1"] .doom-content > p,
    :root[glpi-theme-dark="1"] .doom-intro p,
    :root[glpi-theme-dark="1"] .doom-option-card p,
    :root[glpi-theme-dark="1"] .doom-icon-card span,
    :root[glpi-theme-dark="1"] .doom-muted,
    :root[glpi-theme-dark="1"] .doom-placeholder,
    :root[glpi-theme-dark="1"] .doom-content > p {
        color: var(--doom-muted);
    }

    :root[data-glpi-theme-dark="1"] .doom-placeholder,
    :root[glpi-theme-dark="1"] .doom-placeholder {
        background: rgba(15, 23, 42, 0.82);
        border-color: rgba(100, 116, 139, 0.55);
    }

    :root[data-glpi-theme-dark="1"] .doom-intro-panel,
    :root[glpi-theme-dark="1"] .doom-intro-panel {
        background: linear-gradient(140deg, rgba(14, 165, 233, 0.14), rgba(34, 197, 94, 0.1));
        border-color: rgba(56, 189, 248, 0.18);
    }

    :root[data-glpi-theme-dark="1"] .doom-intro-card,
    :root[glpi-theme-dark="1"] .doom-intro-card {
        background: rgba(15, 23, 42, 0.82);
        border-color: rgba(51, 65, 85, 0.9);
        box-shadow: 0 12px 22px rgba(2, 6, 23, 0.28);
    }

    :root[data-glpi-theme-dark="1"] .doom-range-value,
    :root[glpi-theme-dark="1"] .doom-range-value {
        background: #111827;
        border-color: rgba(100, 116, 139, 0.6);
        color: var(--doom-ink);
        box-shadow: 0 8px 16px rgba(2, 6, 23, 0.18);
    }

    :root[data-glpi-theme-dark="1"] .doom-wrapper input[type="text"],
    :root[data-glpi-theme-dark="1"] .doom-wrapper input[type="number"],
    :root[data-glpi-theme-dark="1"] .doom-wrapper input[type="url"],
    :root[data-glpi-theme-dark="1"] .doom-wrapper input[type="password"],
    :root[data-glpi-theme-dark="1"] .doom-wrapper select,
    :root[data-glpi-theme-dark="1"] .doom-wrapper textarea,
    :root[glpi-theme-dark="1"] .doom-wrapper input[type="text"],
    :root[glpi-theme-dark="1"] .doom-wrapper input[type="number"],
    :root[glpi-theme-dark="1"] .doom-wrapper input[type="url"],
    :root[glpi-theme-dark="1"] .doom-wrapper input[type="password"],
    :root[glpi-theme-dark="1"] .doom-wrapper select,
    :root[glpi-theme-dark="1"] .doom-wrapper textarea {
        background: #0f172a;
        color: var(--doom-ink);
        border-color: #334155;
    }

    :root[data-glpi-theme-dark="1"] .doom-wrapper input::placeholder,
    :root[data-glpi-theme-dark="1"] .doom-wrapper textarea::placeholder,
    :root[glpi-theme-dark="1"] .doom-wrapper input::placeholder,
    :root[glpi-theme-dark="1"] .doom-wrapper textarea::placeholder {
        color: #94a3b8;
    }

    :root[data-glpi-theme-dark="1"] .doom-info::after,
    :root[glpi-theme-dark="1"] .doom-info::after {
        background: #020617;
        color: #e2e8f0;
    }

    :root[data-glpi-theme-dark="1"] .doom-info::before,
    :root[glpi-theme-dark="1"] .doom-info::before {
        border-color: #020617 transparent transparent transparent;
    }
</style>

<div class="doom-wrapper">
    <div class="doom-hero">
        <div class="doom-hero-inner">
            <h2>
                <?php echo __('Doom', 'doom'); ?>
<?php if (defined('PLUGIN_DOOM_VERSION')) : ?>
<span class="doom-hero-version">v<?php echo PLUGIN_DOOM_VERSION; ?></span>
                <?php endif; ?>
            </h2>
            <p><?php echo __('Turn GLPI into a showcase for your brand in just a few clicks.', 'doom'); ?></p>
            <div class="doom-hero-links">
                <a class="doom-hero-link" href="https://www.marcati.com.br" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-globe" aria-hidden="true"></i>
                    <span>Marcati</span>
                </a>
                <a class="doom-hero-link" href="https://wa.me/5519981121515" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i>
                    <span>Whatsapp</span>
                </a>
                <a class="doom-hero-link" href="https://www.linkedin.com/company/marcati" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-linkedin-in" aria-hidden="true"></i>
                    <span>LinkedIn</span>
                </a>
                <a class="doom-hero-link" href="http://instagram.com/marca.ti" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-instagram" aria-hidden="true"></i>
                    <span>Instagram</span>
                </a>
            </div>
        </div>
    </div>

    <div class="doom-timeline">
        <a class="doom-timeline-step <?php echo $currentStep === 0 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>">
            <?php echo __('About', 'doom'); ?>
        </a>
        <a class="doom-timeline-step <?php echo $currentStep === 1 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>?step=1">
            <?php echo __('GLPI logos', 'doom'); ?>
        </a>
        <a class="doom-timeline-step <?php echo $currentStep === 2 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>?step=2">
            <?php echo __('Login Page', 'doom'); ?>
        </a>
        <a class="doom-timeline-step <?php echo $currentStep === 3 ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>?step=3">
            &#x1F512; <?php echo __('Premium', 'doom'); ?>
        </a>
    </div>

    <div class="doom-content">
        <?php if ($currentStep === 0) : ?>
            <div class="doom-intro">
                <div class="doom-intro-copy">
                    <span class="doom-intro-eyebrow"><?php echo __('Doom by Marcati', 'doom'); ?></span>
                    <h3><?php echo __('The visual evolution of your GLPI starts here.', 'doom'); ?></h3>
                    <p><?php echo __('Doom delivers logos, login screens, and colors aligned with your identity. No CSS, no effort, just professional results.', 'doom'); ?></p>
                    <p><?php echo __('Discover Marcati at', 'doom'); ?> <a href="https://brandkit.marcati.com.br" target="_blank" rel="noopener noreferrer">brandkit.marcati.com.br</a>.</p>
                    <div class="doom-intro-actions">
                        <a class="doom-cta primary" href="<?php echo $baseUrl; ?>?step=1"><?php echo __('GLPI logos', 'doom'); ?></a>
                        <a class="doom-cta secondary" href="<?php echo $baseUrl; ?>?step=2"><?php echo __('Login Page', 'doom'); ?></a>
                        <a class="doom-cta ghost" href="https://brandkit.marcati.com.br" target="_blank" rel="noopener noreferrer"><?php echo __('Meet Marcati', 'doom'); ?></a>
                    </div>
                </div>
                <div class="doom-intro-panel">
                    <div class="doom-intro-card">
                        <span>01</span>
                        <h4><?php echo __('Consistent logos', 'doom'); ?></h4>
                        <p><?php echo __('Ready uploads for header, favicon, and login.', 'doom'); ?></p>
                    </div>
                    <div class="doom-intro-card">
                        <span>02</span>
                        <h4><?php echo __('Login with presence', 'doom'); ?></h4>
                        <p><?php echo __('Pick layouts, colors, and effects for an authentication screen that sells your brand.', 'doom'); ?></p>
                    </div>
                    <div class="doom-intro-card">
                        <span>03</span>
                        <h4><?php echo __('Fast delivery', 'doom'); ?></h4>
                        <p><?php echo __('Settings saved directly in GLPI to activate branding without rework.', 'doom'); ?></p>
                    </div>
                </div>
            </div>
        <?php elseif ($currentStep === 1) : ?>
            <h3><?php echo __('GLPI logo configuration', 'doom'); ?></h3>
            <p><?php echo __('Upload logos that will replace the default GLPI assets using CSS variables.', 'doom'); ?></p>
            <?php
                $logoHeaderScale = doom_clamp_int(
                    $logoScaleConfig['logo_header_scale']
                        ?? $logoScaleConfig['logo_header_light_scale']
                        ?? $logoScaleConfig['logo_header_dark_scale']
                        ?? $logoScaleDefaults['logo_header_scale'],
                    40,
                    200,
                    100
                );
                $logoReducedScale = doom_clamp_int(
                    $logoScaleConfig['logo_reduced_scale']
                        ?? $logoScaleConfig['logo_reduced_light_scale']
                        ?? $logoScaleConfig['logo_reduced_dark_scale']
                        ?? $logoScaleDefaults['logo_reduced_scale'],
                    40,
                    200,
                    100
                );
            ?>
            <?php
                $lightThemeFiles = [
                    'logo-GLPI-100-white.png',
                    'logo-G-100-white.png',
                    'logo-GLPI-250-black.png',
                ];
                $darkThemeFiles = [
                    'logo-GLPI-100-black.png',
                    'logo-G-100-black.png',
                ];
                $logoInfoMap = [
                    'logo-GLPI-100-white.png' => __('Expanded menu logo: the logo displayed when the sidebar menu is open, with more space available.', 'doom'),
                    'logo-G-100-white.png' => __('Collapsed menu logo: the logo displayed when the sidebar menu is collapsed, so it needs to be a more compact version.', 'doom'),
                    'logo-GLPI-100-black.png' => __('Expanded menu logo: the logo displayed when the sidebar menu is open, with more space available.', 'doom'),
                    'logo-G-100-black.png' => __('Collapsed menu logo: the logo displayed when the sidebar menu is collapsed, so it needs to be a more compact version.', 'doom'),
                    'logo-GLPI-250-black.png' => __('Login page logo: the logo displayed on the login screen before the user enters the system.', 'doom'),
                ];
                $logoGroups = [
                    [
                        'title' => __('Light mode logos', 'doom'),
                        'info' => __('Light mode logos: used when the system is in light mode, ensuring good visibility on white or light backgrounds.', 'doom'),
                        'files' => $lightThemeFiles,
                    ],
                    [
                        'title' => __('Dark mode logos', 'doom'),
                        'info' => __('Dark mode logos: used when the system is in dark mode, ensuring contrast and readability on black or dark backgrounds.', 'doom'),
                        'files' => $darkThemeFiles,
                    ],
                ];
                $faviconGroup = [
                    'title' => __('Favicon', 'doom'),
                    'info' => __('The favicon is the small icon that appears in the browser tab (and also in bookmarks).', 'doom'),
                    'files' => [
                        'favicon.ico',
                    ],
                ];
            ?>
            <?php foreach ($logoGroups as $group) : ?>
            <div class="doom-section">
                <h4>
                    <?php echo htmlescape($group['title']); ?>
                    <?php if (!empty($group['info'])) : ?>
                        <span
                            class="doom-info"
                            title="<?php echo htmlescape($group['info']); ?>"
                            data-info="<?php echo htmlescape($group['info']); ?>"
                            aria-label="<?php echo htmlescape($group['info']); ?>"
                            role="img"
                            tabindex="0"
                        >i</span>
                    <?php endif; ?>
                </h4>
                <div class="doom-grid">
                <?php foreach ($group['files'] as $file) : ?>
                    <?php $label = $logoFiles[$file] ?? $file; ?>
                    <?php
                        $logoPath = $logosDir ? $logosDir . '/' . $file : null;
                        $logoExists = $logoPath && is_file($logoPath);
                        $publicLogoPath = $publicLogosDir ? $publicLogosDir . '/' . $file : null;
                        if ($publicLogoPath && is_file($publicLogoPath)) {
                            $logoUrl = rtrim($picsWebBase, '/') . '/logos/' . rawurlencode($file);
                        } else {
                            $logoUrl = $pluginWebBase . '/front/logo_preview.php?name=' . rawurlencode($file);
                        }
                        $activeLogoPath = $logoExists ? $logoPath : (($publicLogoPath && is_file($publicLogoPath)) ? $publicLogoPath : null);
                        $logoVersion = $activeLogoPath && is_file($activeLogoPath)
                            ? (string) filemtime($activeLogoPath)
                            : (string) ($_GET['refresh'] ?? time());
                        $logoUrl .= (strpos($logoUrl, '?') === false ? '?' : '&') . 'v=' . rawurlencode($logoVersion);
                        $isWhiteLogo = strpos($file, 'white') !== false;
                        $isDarkLogo = strpos($file, 'black') !== false;
                        $isLoginLogo = strpos($file, '250') !== false;
                        $isCompactLogo = strpos($file, 'logo-G-100') !== false;
                        $isFavicon = $file === 'favicon.ico';
                        $previewScale = $isCompactLogo ? 'reduced' : ($isLoginLogo || $isFavicon ? null : 'header');
                        $acceptTypes = $isFavicon
                            ? '.ico,image/x-icon,image/vnd.microsoft.icon'
                            : 'image/png,image/jpeg,image/webp';
                        $stageClass = '';
                        $uploadId = 'doom_logo_' . md5($file);
                    ?>
                    <div class="doom-card doom-logo-preview doom-logo-card">
                        <h4>
                            <?php echo htmlescape($label); ?>
                            <?php if (!empty($logoInfoMap[$file])) : ?>
                                <span
                                    class="doom-info"
                                    title="<?php echo htmlescape($logoInfoMap[$file]); ?>"
                                    data-info="<?php echo htmlescape($logoInfoMap[$file]); ?>"
                                    aria-label="<?php echo htmlescape($logoInfoMap[$file]); ?>"
                                    role="img"
                                    tabindex="0"
                                >i</span>
                            <?php endif; ?>
                        </h4>
                        <div class="doom-logo-actions">
                            <form method="post" enctype="multipart/form-data" action="logo_upload.php">
                                <input type="hidden" name="doom_action" value="upload_logo">
                                <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                <input type="hidden" name="_glpi_csrf_token" value="<?php echo doom_get_csrf_token(); ?>">
                                <input class="doom-logo-input" type="file" id="<?php echo $uploadId; ?>" name="doom_logo" accept="<?php echo $acceptTypes; ?>">
                                <label class="doom-logo-action doom-logo-upload" for="<?php echo $uploadId; ?>" aria-label="<?php echo __('Upload logo', 'doom'); ?>">&#x2B06;</label>
                            </form>
                            <?php if ($logoExists) : ?>
                                <form method="post" action="logo_upload.php">
                                    <input type="hidden" name="doom_action" value="clear_logo">
                                    <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo doom_get_csrf_token(); ?>">
                                    <button type="submit" class="doom-logo-action doom-logo-remove" aria-label="<?php echo __('Remove logo', 'doom'); ?>">×</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if ($logoExists) : ?>
                            <div class="doom-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="doom-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; background: rgba(15, 23, 42, 0.12); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #0f172a;">
                                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span style="width: 14px; height: 14px; border-radius: 4px; background: #22c55e; display: inline-block;"></span>
                                                <?php echo __('Favicon active', 'doom'); ?>
                                            </span>
                                            <span style="opacity: 0.7;">● ● ●</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="doom-logo-login-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-doom-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="doom-logo-preview-value" data-doom-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="doom-logo-login-field"></div>
                                        <div class="doom-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="doom-logo-header-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-doom-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="doom-logo-preview-value" data-doom-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="doom-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="doom-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; border: 1px dashed rgba(148, 163, 184, 0.7); background: rgba(248, 250, 252, 0.9); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #64748b;">
                                            <span><?php echo __('No favicon yet', 'doom'); ?></span>
                                            <span style="opacity: 0.7;">○ ○ ○</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="doom-logo-login-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-doom-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="doom-logo-preview-value" data-doom-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="doom-logo-login-field"></div>
                                        <div class="doom-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="doom-logo-header-sample">
                                        <div class="logo-sample">
                                            <img
                                                src="<?php echo $logoUrl; ?>"
                                                alt="<?php echo htmlescape($label); ?>"
                                                <?php echo $previewScale ? 'data-doom-logo-scale="' . htmlescape($previewScale) . '"' : ''; ?>
                                            >
                                            <?php if ($previewScale) : ?>
                                                <span class="doom-logo-preview-value" data-doom-logo-value="<?php echo htmlescape($previewScale); ?>"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="doom-placeholder">
                                    <?php echo $isFavicon ? __('No custom favicon uploaded yet.', 'doom') : __('No custom logo uploaded yet.', 'doom'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <form method="post" action="<?php echo $baseUrl; ?>?step=1">
                <div class="doom-section">
                    <h4><?php echo __('Logo size (%)', 'doom'); ?></h4>
                    <div class="doom-form-grid">
                        <div>
                            <label for="doom_logo_header_scale"><?php echo __('Expanded menu logo size (%)', 'doom'); ?></label>
                            <div class="doom-range">
                                <input type="range" id="doom_logo_header_scale" name="logo_header_scale" min="40" max="200" step="5" value="<?php echo htmlescape($logoHeaderScale); ?>" oninput="document.getElementById('doom_logo_header_scale_value').textContent = this.value + '%'">
                                <span class="doom-range-value" id="doom_logo_header_scale_value"><?php echo htmlescape($logoHeaderScale); ?>%</span>
                            </div>
                        </div>
                        <div>
                            <label for="doom_logo_reduced_scale"><?php echo __('Collapsed menu logo size (%)', 'doom'); ?></label>
                            <div class="doom-range">
                                <input type="range" id="doom_logo_reduced_scale" name="logo_reduced_scale" min="40" max="200" step="5" value="<?php echo htmlescape($logoReducedScale); ?>" oninput="document.getElementById('doom_logo_reduced_scale_value').textContent = this.value + '%'">
                                <span class="doom-range-value" id="doom_logo_reduced_scale_value"><?php echo htmlescape($logoReducedScale); ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="doom-actions">
                        <button type="submit" class="doom-save-logo"><?php echo __('Save logo sizes', 'doom'); ?></button>
                    </div>
                </div>
                <input type="hidden" name="doom_action" value="save_logo_scales">
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo doom_get_csrf_token(); ?>">
            </form>
            <div class="doom-section">
                <h4>
                    <?php echo htmlescape($faviconGroup['title']); ?>
                    <?php if (!empty($faviconGroup['info'])) : ?>
                        <span
                            class="doom-info"
                            title="<?php echo htmlescape($faviconGroup['info']); ?>"
                            data-info="<?php echo htmlescape($faviconGroup['info']); ?>"
                            aria-label="<?php echo htmlescape($faviconGroup['info']); ?>"
                            role="img"
                            tabindex="0"
                        >i</span>
                    <?php endif; ?>
                </h4>
                <div class="doom-grid">
                <?php foreach ($faviconGroup['files'] as $file) : ?>
                    <?php $label = $logoFiles[$file] ?? $file; ?>
                    <?php
                        $logoPath = $logosDir ? $logosDir . '/' . $file : null;
                        $logoExists = $logoPath && is_file($logoPath);
                        $publicLogoPath = $publicLogosDir ? $publicLogosDir . '/' . $file : null;
                        if ($publicLogoPath && is_file($publicLogoPath)) {
                            $logoUrl = rtrim($picsWebBase, '/') . '/logos/' . rawurlencode($file);
                        } else {
                            $logoUrl = $pluginWebBase . '/front/logo_preview.php?name=' . rawurlencode($file);
                        }
                        $activeLogoPath = $logoExists ? $logoPath : (($publicLogoPath && is_file($publicLogoPath)) ? $publicLogoPath : null);
                        $logoVersion = $activeLogoPath && is_file($activeLogoPath)
                            ? (string) filemtime($activeLogoPath)
                            : (string) ($_GET['refresh'] ?? time());
                        $logoUrl .= (strpos($logoUrl, '?') === false ? '?' : '&') . 'v=' . rawurlencode($logoVersion);
                        $isWhiteLogo = strpos($file, 'white') !== false;
                        $isDarkLogo = strpos($file, 'black') !== false;
                        $isLoginLogo = strpos($file, '250') !== false;
                        $isCompactLogo = strpos($file, 'logo-G-100') !== false;
                        $isFavicon = $file === 'favicon.ico';
                        $previewScale = $isCompactLogo ? 'reduced' : ($isLoginLogo || $isFavicon ? null : 'header');
                        $stageClass = '';
                        $uploadId = 'doom_logo_' . md5($file);
                    ?>
                    <div class="doom-card doom-logo-preview doom-logo-card">
                        <h4><?php echo htmlescape($label); ?></h4>
                        <div class="doom-logo-actions">
                            <form method="post" enctype="multipart/form-data" action="logo_upload.php">
                                <input type="hidden" name="doom_action" value="upload_logo">
                                <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                <input type="hidden" name="_glpi_csrf_token" value="<?php echo doom_get_csrf_token(); ?>">
                                <input class="doom-logo-input" type="file" id="<?php echo $uploadId; ?>" name="doom_logo" accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon">
                                <label class="doom-logo-action doom-logo-upload" for="<?php echo $uploadId; ?>" aria-label="<?php echo __('Upload logo', 'doom'); ?>">&#x2B06;</label>
                            </form>
                            <?php if ($logoExists) : ?>
                                <form method="post" action="logo_upload.php">
                                    <input type="hidden" name="doom_action" value="clear_logo">
                                    <input type="hidden" name="logo_target" value="<?php echo htmlescape($file); ?>">
                                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo doom_get_csrf_token(); ?>">
                                    <button type="submit" class="doom-logo-action doom-logo-remove" aria-label="<?php echo __('Remove logo', 'doom'); ?>">×</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if ($logoExists) : ?>
                            <div class="doom-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="doom-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; background: rgba(15, 23, 42, 0.12); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #0f172a;">
                                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                                <span style="width: 14px; height: 14px; border-radius: 4px; background: #22c55e; display: inline-block;"></span>
                                                <?php echo __('Favicon active', 'doom'); ?>
                                            </span>
                                            <span style="opacity: 0.7;">● ● ●</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="doom-logo-login-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="doom-logo-login-field"></div>
                                        <div class="doom-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="doom-logo-header-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="doom-logo-stage <?php echo $stageClass; ?>">
                                <?php if ($isFavicon) : ?>
                                    <div class="doom-logo-login-sample" style="gap: 10px;">
                                        <div style="flex: 1; height: 36px; border-radius: 10px; border: 1px dashed rgba(148, 163, 184, 0.7); background: rgba(248, 250, 252, 0.9); display: flex; align-items: center; justify-content: space-between; padding: 0 10px; font-size: 0.72rem; color: #64748b;">
                                            <span><?php echo __('No favicon yet', 'doom'); ?></span>
                                            <span style="opacity: 0.7;">○ ○ ○</span>
                                        </div>
                                    </div>
                                <?php elseif ($isLoginLogo) : ?>
                                    <div class="doom-logo-login-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="doom-logo-login-field"></div>
                                        <div class="doom-logo-login-field"></div>
                                    </div>
                                <?php else : ?>
                                    <div class="doom-logo-header-sample">
                                        <div class="logo-sample"><img src="<?php echo $logoUrl; ?>" alt="<?php echo htmlescape($label); ?>"></div>
                                        <div class="logo-sample" style="<?php echo $isCompactLogo ? 'width: 40px;' : 'width: 70px;'; ?>">
                                            <span style="width: 100%; height: 10px; border-radius: 999px; background: rgba(148, 163, 184, 0.7); display: block;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="doom-placeholder">
                                    <?php echo $isFavicon ? __('No custom favicon uploaded yet.', 'doom') : __('No custom logo uploaded yet.', 'doom'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var inputs = document.querySelectorAll('.doom-logo-input');
                    inputs.forEach(function (input) {
                        input.addEventListener('change', function () {
                            if (input.files && input.files.length) {
                                input.form.submit();
                            }
                        });
                    });

                    var headerScale = document.getElementById('doom_logo_header_scale');
                    var reducedScale = document.getElementById('doom_logo_reduced_scale');
                    var saveButton = document.querySelector('.doom-save-logo');
                    var root = document.documentElement;
                    var previewValues = document.querySelectorAll('[data-doom-logo-value]');
                    var previewImages = document.querySelectorAll('[data-doom-logo-scale]');
                    var previewTimers = new Map();
                    function capScale(value) {
                        return Math.min(1.15, Math.max(0.6, value / 100));
                    }
                    function updatePreviewScales() {
                        var headerValue = headerScale ? parseInt(headerScale.value || '100', 10) : 100;
                        var reducedValue = reducedScale ? parseInt(reducedScale.value || '100', 10) : 100;
                        root.style.setProperty('--doom-logo-preview-header-scale', capScale(headerValue));
                        root.style.setProperty('--doom-logo-preview-reduced-scale', capScale(reducedValue));
                        previewValues.forEach(function (node) {
                            var type = node.getAttribute('data-doom-logo-value');
                            if (type === 'header') {
                                node.textContent = headerValue + '%';
                            } else if (type === 'reduced') {
                                node.textContent = reducedValue + '%';
                            }
                            var sample = node.closest('.logo-sample');
                            if (sample) {
                                sample.classList.add('doom-preview-active');
                                var existing = previewTimers.get(sample);
                                if (existing) {
                                    clearTimeout(existing);
                                }
                                previewTimers.set(sample, setTimeout(function () {
                                    sample.classList.remove('doom-preview-active');
                                }, 1200));
                            }
                        });
                        previewImages.forEach(function (img) {
                            img.classList.remove('doom-scale-pulse');
                            void img.offsetWidth;
                            img.classList.add('doom-scale-pulse');
                        });
                        if (saveButton) {
                            saveButton.classList.remove('doom-save-pulse');
                            void saveButton.offsetWidth;
                            saveButton.classList.add('doom-save-pulse');
                        }
                    }
                    updatePreviewScales();
                    if (headerScale) {
                        headerScale.addEventListener('input', updatePreviewScales);
                    }
                    if (reducedScale) {
                        reducedScale.addEventListener('input', updatePreviewScales);
                    }
                });
            </script>
        <?php elseif ($currentStep === 2) : ?>
            <h3><?php echo __('Login page configuration', 'doom'); ?></h3>
            <p><?php echo __('Pick a layout and visual treatments for the GLPI login page.', 'doom'); ?></p>
            <?php
                $loginWallpaper = $loginConfig['login_wallpaper'] ?? '';
                if ($loginWallpaper) {
                    $resolvedWallpaper = doom_resolve_wallpaper_filename($loginWallpaper);
                    if ($resolvedWallpaper !== '') {
                        $loginWallpaper = $resolvedWallpaper;
                    }
                }
                $pluginStorageDir = doom_get_plugin_storage_dir();
                $wallpaperSource = $loginConfig['login_wallpaper_source'] ?? '';
                if ($wallpaperSource === '') {
                    $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
                }
                if (!in_array($wallpaperSource, ['preset', 'custom'], true)) {
                    $wallpaperSource = $loginWallpaper ? 'custom' : 'preset';
                }
                if ($wallpaperSource === 'custom' && $loginWallpaper && doom_get_wallpaper_path($loginWallpaper)) {
                    $wallpaperSource = 'custom';
                }
                $wallpaperPreset = $loginConfig['login_wallpaper_preset'] ?? $wallpaperPresetDefault;
                if (!in_array($wallpaperPreset, $wallpaperPresets, true)) {
                    $wallpaperPreset = $wallpaperPresetDefault;
                }
                $customWallpaperUrl = null;
                $customWallpaperWebpUrl = null;
                if ($loginWallpaper) {
                    $resolvedWallpaper = doom_resolve_wallpaper_filename($loginWallpaper);
                    if ($resolvedWallpaper !== '') {
                        $loginWallpaper = $resolvedWallpaper;
                    }
                    $customWallpaperPath = doom_get_wallpaper_path($loginWallpaper);
                    $customWallpaperVersion = ($customWallpaperPath && is_file($customWallpaperPath))
                        ? (int) filemtime($customWallpaperPath)
                        : time();
                    $customWallpaperUrl = doom_get_wallpaper_image_url($loginWallpaper, $customWallpaperVersion);
                    $customWebpPath = preg_replace('/\.[^.]+$/', '.webp', $customWallpaperPath);
                    if ($customWebpPath && is_file($customWebpPath)) {
                        $customWallpaperWebpUrl = doom_get_wallpaper_image_url(basename($customWebpPath), (int) filemtime($customWebpPath));
                    }
                }
                $presetWallpaperUrl = null;
                $presetWallpaperWebpUrl = null;
                if ($wallpaperPreset) {
                    $presetAssets = doom_get_wallpaper_preset_assets($wallpaperPreset);
                    $presetWallpaperUrl = $presetAssets['url'] ?? null;
                    $presetWallpaperWebpUrl = $presetAssets['webp_url'] ?? null;
                }
                $loginWallpaperUrl = $wallpaperSource === 'custom' ? $customWallpaperUrl : $presetWallpaperUrl;
                $loginWallpaperWebpUrl = $wallpaperSource === 'custom' ? $customWallpaperWebpUrl : $presetWallpaperWebpUrl;
                $loginLogoScale = doom_clamp_int($loginConfig['login_logo_scale'] ?? $loginDefaults['login_logo_scale'], 80, 480, 200);
                $loginLogoOffset = doom_clamp_int($loginConfig['login_logo_offset'] ?? $loginDefaults['login_logo_offset'], 0, 240, 0);
                $loginPanelWidth = doom_clamp_int(
                    $loginConfig['login_panel_width'] ?? $loginDefaults['login_panel_width'],
                    280,
                    720,
                    520
                );
                $loginPanelHeight = doom_clamp_int(
                    $loginConfig['login_panel_height'] ?? $loginDefaults['login_panel_height'],
                    360,
                    780,
                    560
                );
                $loginPanelHoverEffect = ($loginConfig['login_panel_hover_effect'] ?? $loginDefaults['login_panel_hover_effect']) === '1';
                $loginButtonStyle = $loginConfig['login_button_style'] ?? $loginDefaults['login_button_style'];
                if (!in_array($loginButtonStyle, doom_get_login_button_styles(), true)) {
                    $loginButtonStyle = $loginDefaults['login_button_style'];
                }
                $loginButtonShape = $loginConfig['login_button_shape'] ?? $loginDefaults['login_button_shape'];
                if (!in_array($loginButtonShape, doom_get_login_button_shapes(), true)) {
                    $loginButtonShape = $loginDefaults['login_button_shape'];
                }
                $loginButtonColorMode = $loginConfig['login_button_color_mode'] ?? $loginDefaults['login_button_color_mode'];
                if (!in_array($loginButtonColorMode, ['preset', 'custom'], true)) {
                    $loginButtonColorMode = $loginDefaults['login_button_color_mode'];
                }
                $loginButtonCustomStyle = $loginConfig['login_button_custom_style'] ?? $loginDefaults['login_button_custom_style'];
                if (!in_array($loginButtonCustomStyle, ['default', 'outline'], true)) {
                    $loginButtonCustomStyle = $loginDefaults['login_button_custom_style'];
                }
                $loginButtonHoverEffect = $loginConfig['login_button_hover_effect'] ?? $loginDefaults['login_button_hover_effect'];
                if (!in_array($loginButtonHoverEffect, ['press', 'fill'], true)) {
                    $loginButtonHoverEffect = $loginDefaults['login_button_hover_effect'];
                }
                $loginButtonHoverFillMode = $loginConfig['login_button_hover_fill_mode'] ?? $loginDefaults['login_button_hover_fill_mode'];
                if (!in_array($loginButtonHoverFillMode, ['button', 'custom'], true)) {
                    $loginButtonHoverFillMode = $loginDefaults['login_button_hover_fill_mode'];
                }
                $loginButtonTextSize = doom_clamp_int(
                    $loginConfig['login_button_text_size'] ?? $loginDefaults['login_button_text_size'],
                    10,
                    28,
                    14
                );
                $loginButtonColor = doom_normalize_hex_color(
                    $loginConfig['login_button_color'] ?? $loginDefaults['login_button_color'],
                    $loginDefaults['login_button_color']
                );
                $loginButtonHoverFillColor = doom_normalize_hex_color(
                    $loginConfig['login_button_hover_fill_color'] ?? $loginDefaults['login_button_hover_fill_color'],
                    $loginDefaults['login_button_hover_fill_color']
                );
                $loginButtonHoverTextColor = doom_normalize_hex_color(
                    $loginConfig['login_button_hover_text_color'] ?? $loginDefaults['login_button_hover_text_color'],
                    $loginDefaults['login_button_hover_text_color']
                );
                $loginLogoPosition = $loginConfig['login_logo_position'] ?? $loginDefaults['login_logo_position'];
                if (!in_array($loginLogoPosition, ['outside', 'inside'], true)) {
                    $loginLogoPosition = $loginDefaults['login_logo_position'];
                }
                $loginWallpaperSize = $loginConfig['login_wallpaper_size'] ?? $loginDefaults['login_wallpaper_size'];
                $loginWallpaperPosition = $loginConfig['login_wallpaper_position'] ?? $loginDefaults['login_wallpaper_position'];
                $loginWallpaperAttachment = $loginConfig['login_wallpaper_attachment'] ?? $loginDefaults['login_wallpaper_attachment'];
                $loginWallpaperOverlayColor = doom_normalize_hex_color(
                    $loginConfig['login_wallpaper_overlay_color'] ?? $loginDefaults['login_wallpaper_overlay_color'],
                    $loginDefaults['login_wallpaper_overlay_color']
                );
                $loginWallpaperOverlayOpacity = doom_clamp_int(
                    $loginConfig['login_wallpaper_overlay_opacity'] ?? $loginDefaults['login_wallpaper_overlay_opacity'],
                    0,
                    100,
                    35
                );
                $loginTextColor = doom_normalize_hex_color(
                    $loginConfig['login_text_color'] ?? $loginDefaults['login_text_color'],
                    $loginDefaults['login_text_color']
                );
                $loginFieldStyle = $loginConfig['login_field_style'] ?? $loginDefaults['login_field_style'];
                if (!in_array($loginFieldStyle, ['solid', 'glass', 'soft', 'outline', 'underline', 'neon'], true)) {
                    $loginFieldStyle = $loginDefaults['login_field_style'];
                }
                $loginFieldBg = doom_normalize_hex_color(
                    $loginConfig['login_field_bg'] ?? $loginDefaults['login_field_bg'],
                    $loginDefaults['login_field_bg']
                );
                $loginFieldBorder = doom_normalize_hex_color(
                    $loginConfig['login_field_border'] ?? $loginDefaults['login_field_border'],
                    $loginDefaults['login_field_border']
                );
                $loginFieldBorderWidth = doom_clamp_int(
                    $loginConfig['login_field_border_width'] ?? $loginDefaults['login_field_border_width'],
                    0,
                    6,
                    1
                );
                $loginFieldTextSize = doom_clamp_int(
                    $loginConfig['login_field_text_size'] ?? $loginDefaults['login_field_text_size'],
                    10,
                    36,
                    14
                );
                $loginFieldHeight = doom_clamp_int(
                    $loginConfig['login_field_height'] ?? $loginDefaults['login_field_height'],
                    32,
                    72,
                    44
                );
                $loginFieldWidth = doom_clamp_int(
                    $loginConfig['login_field_width'] ?? $loginDefaults['login_field_width'],
                    260,
                    720,
                    520
                );
                $loginFieldSpacing = null;
                $loginTitleSize = null;
                if (!$isGlpi11) {
                    $loginFieldSpacing = doom_clamp_int(
                        $loginConfig['login_field_spacing'] ?? $loginDefaults['login_field_spacing'],
                        8,
                        48,
                        16
                    );
                    $loginTitleSize = doom_clamp_int(
                        $loginConfig['login_title_size'] ?? $loginDefaults['login_title_size'],
                        16,
                        48,
                        24
                    );
                }
                $loginTextSize = doom_clamp_int(
                    $loginConfig['login_text_size'] ?? $loginDefaults['login_text_size'],
                    10,
                    30,
                    15
                );
                $loginFieldText = doom_normalize_hex_color(
                    $loginConfig['login_field_text'] ?? $loginDefaults['login_field_text'],
                    $loginDefaults['login_field_text']
                );
                $loginFieldFocus = doom_normalize_hex_color(
                    $loginConfig['login_field_focus'] ?? $loginDefaults['login_field_focus'],
                    $loginDefaults['login_field_focus']
                );
                $loginAccentColor = doom_normalize_hex_color(
                    $loginConfig['login_accent_color'] ?? $loginDefaults['login_accent_color'],
                    $loginDefaults['login_accent_color']
                );
                $loginIconUser = $loginConfig['login_icon_user'] ?? $loginDefaults['login_icon_user'];
                $loginIconUserOptions = ['none', 'user', 'id', 'mail'];
                if (!in_array($loginIconUser, $loginIconUserOptions, true)) {
                    $loginIconUser = $loginDefaults['login_icon_user'];
                }
                $loginIconPassword = $loginConfig['login_icon_password'] ?? $loginDefaults['login_icon_password'];
                $loginIconPasswordOptions = ['none', 'lock', 'key', 'shield'];
                if (!in_array($loginIconPassword, $loginIconPasswordOptions, true)) {
                    $loginIconPassword = $loginDefaults['login_icon_password'];
                }
                $loginIconColor = doom_normalize_hex_color(
                    $loginConfig['login_icon_color'] ?? $loginDefaults['login_icon_color'],
                    $loginDefaults['login_icon_color']
                );
                $loginIconPosition = $loginConfig['login_icon_position'] ?? $loginDefaults['login_icon_position'];
                if (!in_array($loginIconPosition, ['left', 'right'], true)) {
                    $loginIconPosition = $loginDefaults['login_icon_position'];
                }
                $loginFieldRadius = doom_clamp_int(
                    $loginConfig['login_field_radius'] ?? $loginDefaults['login_field_radius'],
                    0,
                    32,
                    12
                );
                $loginHideLoginText = ($loginConfig['login_hide_login_text'] ?? $loginDefaults['login_hide_login_text']) === '1';
                $buttonStyleLabels = [
                    'primary' => __('Primary (default)', 'doom'),
                    'secondary' => __('Secondary', 'doom'),
                    'success' => __('Success', 'doom'),
                    'danger' => __('Danger', 'doom'),
                    'warning' => __('Warning', 'doom'),
                    'info' => __('Info', 'doom'),
                    'dark' => __('Dark', 'doom'),
                    'light' => __('Light', 'doom'),
                    'outline-primary' => __('Outline primary', 'doom'),
                    'outline-secondary' => __('Outline secondary', 'doom'),
                    'outline-success' => __('Outline success', 'doom'),
                    'outline-danger' => __('Outline danger', 'doom'),
                    'outline-warning' => __('Outline warning', 'doom'),
                    'outline-info' => __('Outline info', 'doom'),
                    'outline-dark' => __('Outline dark', 'doom'),
                ];
                $buttonShapeLabels = [
                    'square' => __('Square', 'doom'),
                    'rounded' => __('Rounded', 'doom'),
                    'pill' => __('Pill', 'doom'),
                    'cut' => __('Cut corner', 'doom'),
                    'soft' => __('Soft depth', 'doom'),
                ];
                $buttonShapeDescriptions = [
                    'square' => __('Sharp edges with a confident shadow.', 'doom'),
                    'rounded' => __('Balanced corners for modern brands.', 'doom'),
                    'pill' => __('Friendly, fully rounded shape.', 'doom'),
                    'cut' => __('Dynamic cut for a bolder call-to-action.', 'doom'),
                    'soft' => __('Cushioned look with soft depth.', 'doom'),
                ];
                $fieldStyleLabels = [
                    'solid' => __('Solid', 'doom'),
                    'glass' => __('Glass blur', 'doom'),
                    'soft' => __('Soft shadow', 'doom'),
                    'outline' => __('Outline', 'doom'),
                    'underline' => __('Underline', 'doom'),
                    'neon' => __('Neon glow', 'doom'),
                ];
                $buttonColorModeLabels = [
                    'preset' => __('Preset palette', 'doom'),
                    'custom' => __('Custom hex color', 'doom'),
                ];
                $buttonCustomStyleLabels = [
                    'default' => __('Default fill', 'doom'),
                    'outline' => __('Outline only', 'doom'),
                ];
                $buttonHoverEffectLabels = [
                    'press' => __('Press on hover', 'doom'),
                    'fill' => __('Fill with color', 'doom'),
                ];
                $buttonHoverFillModeLabels = [
                    'button' => __('Use button color', 'doom'),
                    'custom' => __('Pick a custom color', 'doom'),
                ];
                $iconUserLabels = [
                    'none' => __('None', 'doom'),
                    'user' => __('User', 'doom'),
                    'id' => __('ID card', 'doom'),
                    'mail' => __('Email', 'doom'),
                ];
                $iconPasswordLabels = [
                    'none' => __('None', 'doom'),
                    'lock' => __('Lock', 'doom'),
                    'key' => __('Key', 'doom'),
                    'shield' => __('Shield', 'doom'),
                ];
                $iconPositionLabels = [
                    'right' => __('Right', 'doom'),
                    'left' => __('Left', 'doom'),
                ];
                $iconSvgMap = [
                    'user' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c2-4 6-6 8-6s6 2 8 6"/></svg>',
                    'id' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4"/><circle cx="10" cy="10" r="2.5"/></svg>',
                    'mail' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
                    'lock' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
                    'key' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="14.5" r="3.5"/><path d="M11 14h10l-2 2 2 2-2 2"/></svg>',
                    'shield' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6z"/><path d="M9.5 12.5 11 14l3.5-3.5"/></svg>',
                ];
                $wallpaperSizeLabels = [
                    'cover' => __('Cover', 'doom'),
                    'contain' => __('Contain', 'doom'),
                    'stretch' => __('Stretch', 'doom'),
                    'original' => __('Original size', 'doom'),
                ];
                $wallpaperPositionLabels = [
                    'center' => __('Center', 'doom'),
                    'top' => __('Top', 'doom'),
                    'bottom' => __('Bottom', 'doom'),
                    'left' => __('Left', 'doom'),
                    'right' => __('Right', 'doom'),
                ];
                $wallpaperAttachmentLabels = [
                    'fixed' => __('Fixed', 'doom'),
                    'scroll' => __('Scroll', 'doom'),
                ];
            ?>
            <?php
                $layoutLabels = [
                    'split-left' => __('Split left', 'doom'),
                    'classic' => __('Classic centered', 'doom'),
                    'split-right' => __('Split right', 'doom'),
                ];
                $selectedLayout = $loginConfig['login_layout'] ?? $loginDefaults['login_layout'];
                if (!in_array($selectedLayout, ['split-left', 'split-right', 'classic'], true)) {
                    $selectedLayout = $loginDefaults['login_layout'];
                }
            ?>
            <form id="doom-login-settings-form" method="post" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>?step=2">
                <div class="doom-step2-grid">
                    <div class="doom-step2-row">
                        <div class="doom-section">
                            <h4><?php echo __('Layout & logo', 'doom'); ?></h4>
                            <div class="doom-form-grid">
                                <div>
                                    <label for="doom_login_layout"><?php echo __('Layout', 'doom'); ?></label>
                                    <select id="doom_login_layout" name="login_layout">
                                        <?php foreach ($layoutLabels as $layoutValue => $layoutLabel) : ?>
                                            <option value="<?php echo htmlescape($layoutValue); ?>" <?php echo $selectedLayout === $layoutValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($layoutLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_login_transparent"><?php echo __('Transparent panel', 'doom'); ?></label>
                                    <select id="doom_login_transparent" name="login_panel_transparent">
                                        <option value="0" <?php echo $loginConfig['login_panel_transparent'] === '0' ? 'selected' : ''; ?>><?php echo __('No (show panel)', 'doom'); ?></option>
                                        <option value="1" <?php echo $loginConfig['login_panel_transparent'] === '1' ? 'selected' : ''; ?>><?php echo __('Yes (hide panel)', 'doom'); ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_login_panel_hover"><?php echo __('Panel hover effect', 'doom'); ?></label>
                                    <select id="doom_login_panel_hover" name="login_panel_hover_effect">
                                        <option value="1" <?php echo $loginPanelHoverEffect ? 'selected' : ''; ?>><?php echo __('Yes (enable)', 'doom'); ?></option>
                                        <option value="0" <?php echo !$loginPanelHoverEffect ? 'selected' : ''; ?>><?php echo __('No (disable)', 'doom'); ?></option>
                                    </select>
                                </div>
                                <div class="doom-panel-size">
                                    <label for="doom_login_panel_width"><?php echo __('Panel width (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_panel_width" name="login_panel_width" min="280" max="720" step="10" value="<?php echo htmlescape($loginPanelWidth); ?>" oninput="document.getElementById('doom_login_panel_width_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_panel_width_value"><?php echo htmlescape($loginPanelWidth); ?>px</span>
                                    </div>
                                </div>

                                <div class="doom-panel-size">
                                    <label for="doom_login_panel_height"><?php echo __('Panel height (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_panel_height" name="login_panel_height" min="360" max="780" step="10" value="<?php echo htmlescape($loginPanelHeight); ?>" oninput="document.getElementById('doom_login_panel_height_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_panel_height_value"><?php echo htmlescape($loginPanelHeight); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_logo_scale"><?php echo __('Login logo size (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_logo_scale" name="login_logo_scale" min="80" max="480" step="10" value="<?php echo htmlescape($loginLogoScale); ?>" oninput="document.getElementById('doom_login_logo_scale_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_logo_scale_value"><?php echo htmlescape($loginLogoScale); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_logo_position"><?php echo __('Logo position', 'doom'); ?></label>
                                    <select id="doom_login_logo_position" name="login_logo_position">
                                        <option value="outside" <?php echo $loginLogoPosition === 'outside' ? 'selected' : ''; ?>><?php echo __('Above panel', 'doom'); ?></option>
                                        <option value="inside" <?php echo $loginLogoPosition === 'inside' ? 'selected' : ''; ?>><?php echo __('Inside panel', 'doom'); ?></option>
                                    </select>
                                </div>
                                <div class="doom-logo-offset-field">
                                    <label for="doom_login_logo_offset"><?php echo __('Logo offset from top (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_logo_offset" name="login_logo_offset" min="0" max="240" step="5" value="<?php echo htmlescape($loginLogoOffset); ?>" oninput="document.getElementById('doom_login_logo_offset_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_logo_offset_value"><?php echo htmlescape($loginLogoOffset); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_accent_color"><?php echo __('Accent color', 'doom'); ?></label>
                                    <input type="color" id="doom_login_accent_color" name="login_accent_color" value="<?php echo htmlescape($loginAccentColor); ?>">
                                </div>
                                <div>
                                    <label for="doom_login_text_color"><?php echo __('Text color', 'doom'); ?></label>
                                    <input type="color" id="doom_login_text_color" name="login_text_color" value="<?php echo htmlescape($loginTextColor); ?>">
                                </div>
                                <div>
                                    <label for="doom_hide_login_text"><?php echo __('Hide login text', 'doom'); ?></label>
                                    <select id="doom_hide_login_text" name="login_hide_login_text">
                                        <option value="0" <?php echo !$loginHideLoginText ? 'selected' : ''; ?>><?php echo __('No', 'doom'); ?></option>
                                        <option value="1" <?php echo $loginHideLoginText ? 'selected' : ''; ?>><?php echo __('Yes', 'doom'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="doom-step2-preview">
                            <div class="doom-preview-shell" data-doom-preview="layout" data-layout="<?php echo htmlescape($selectedLayout); ?>">
                                <div class="doom-preview-split"></div>
                                <div class="doom-preview-panel">
                                    <div class="doom-preview-logo" data-doom-preview-logo>Logo</div>
                                    <div class="doom-preview-login">
                                        <div class="doom-preview-title" data-doom-preview-title><?php echo __('Sign in to your account', 'doom'); ?></div>
                                        <div>
                                            <div class="doom-preview-field-label" data-doom-preview-text><?php echo __('Login', 'doom'); ?></div>
                                            <div class="doom-preview-field"></div>
                                        </div>
                                        <div>
                                            <div class="doom-preview-field-label" data-doom-preview-text><?php echo __('Password', 'doom'); ?></div>
                                            <div class="doom-preview-field"></div>
                                        </div>
                                        <button type="button" class="doom-preview-button" data-doom-preview-button><?php echo __('Sign in', 'doom'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doom-step2-row" id="doom_panel_split_section">
                        <div class="doom-section">
                            <h4><?php echo __('Panel & split styling', 'doom'); ?></h4>
                            <div class="doom-form-grid">
                                <div class="doom-panel-style-controls">
                                    <label for="doom_login_opacity"><?php echo __('Panel opacity (%)', 'doom'); ?></label>
                                    <div class="doom-range">
                                    <input type="range" id="doom_login_opacity" name="login_panel_opacity" min="0" max="100" value="<?php echo htmlescape($loginConfig['login_panel_opacity']); ?>" oninput="document.getElementById('doom_login_opacity_value').textContent = this.value + '%'">
                                        <span class="doom-range-value" id="doom_login_opacity_value"><?php echo htmlescape($loginConfig['login_panel_opacity']); ?>%</span>
                                    </div>
                                </div>
                                <div class="doom-panel-style-controls">
                                    <label for="doom_login_color"><?php echo __('Panel color', 'doom'); ?></label>
                                    <input type="color" id="doom_login_color" name="login_panel_color" value="<?php echo htmlescape($loginConfig['login_panel_color']); ?>">
                                </div>
                                <div class="doom-panel-style-controls">
                                    <label for="doom_login_blur"><?php echo __('Panel blur (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_blur" name="login_panel_blur" min="0" max="24" value="<?php echo htmlescape($loginConfig['login_panel_blur']); ?>" oninput="document.getElementById('doom_login_blur_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_blur_value"><?php echo htmlescape($loginConfig['login_panel_blur']); ?>px</span>
                                    </div>
                                </div>
                            </div>
                            <div class="doom-form-grid doom-split-controls" id="doom_split_controls">
                                <div>
                                    <label for="doom_split_width"><?php echo __('Split width (%)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_split_width" name="login_split_width" min="0" max="100" value="<?php echo htmlescape($loginConfig['login_split_width']); ?>" oninput="document.getElementById('doom_split_width_value').textContent = this.value + '%'">
                                        <span class="doom-range-value" id="doom_split_width_value"><?php echo htmlescape($loginConfig['login_split_width']); ?>%</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_split_opacity"><?php echo __('Split opacity (%)', 'doom'); ?></label>
                                    <div class="doom-range">
                                    <input type="range" id="doom_split_opacity" name="login_split_opacity" min="0" max="100" value="<?php echo htmlescape($loginConfig['login_split_opacity']); ?>" oninput="document.getElementById('doom_split_opacity_value').textContent = this.value + '%'">
                                        <span class="doom-range-value" id="doom_split_opacity_value"><?php echo htmlescape($loginConfig['login_split_opacity']); ?>%</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_split_color"><?php echo __('Split color', 'doom'); ?></label>
                                    <input type="color" id="doom_split_color" name="login_split_color" value="<?php echo htmlescape($loginConfig['login_split_color']); ?>">
                                </div>
                                <div>
                                    <label for="doom_split_blur"><?php echo __('Split blur (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                    <input type="range" id="doom_split_blur" name="login_split_blur" min="0" max="24" value="<?php echo htmlescape($loginConfig['login_split_blur']); ?>" oninput="document.getElementById('doom_split_blur_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_split_blur_value"><?php echo htmlescape($loginConfig['login_split_blur']); ?>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="doom-step2-preview" id="doom_panel_split_preview">
                            <div class="doom-preview-shell" data-doom-preview="panel" data-layout="<?php echo htmlescape($selectedLayout); ?>">
                                <div class="doom-preview-split"></div>
                                <div class="doom-preview-panel">
                                    <div class="doom-preview-login">
                                        <div>
                                            <div class="doom-preview-field-label" data-doom-preview-text><?php echo __('Login', 'doom'); ?></div>
                                            <div class="doom-preview-field"></div>
                                        </div>
                                        <div>
                                            <div class="doom-preview-field-label" data-doom-preview-text><?php echo __('Password', 'doom'); ?></div>
                                            <div class="doom-preview-field"></div>
                                        </div>
                                        <button type="button" class="doom-preview-button" data-doom-preview-button><?php echo __('Sign in', 'doom'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doom-step2-row">
                        <div class="doom-section">
                            <h4><?php echo __('Button', 'doom'); ?></h4>
                            <div class="doom-form-grid doom-form-grid--button">
                                <div>
                                    <label for="doom_login_button_shape"><?php echo __('Button shape', 'doom'); ?></label>
                                    <select id="doom_login_button_shape" name="login_button_shape">
                                        <?php foreach (doom_get_login_button_shapes() as $shape) : ?>
                                            <option value="<?php echo htmlescape($shape); ?>" <?php echo $loginButtonShape === $shape ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($buttonShapeLabels[$shape] ?? $shape); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" id="doom_login_button_style" name="login_button_style" value="<?php echo htmlescape($loginButtonStyle); ?>">
                                <div>
                                    <label for="doom_login_button_custom_style"><?php echo __('Button fill style', 'doom'); ?></label>
                                    <select id="doom_login_button_custom_style" name="login_button_custom_style">
                                        <?php foreach ($buttonCustomStyleLabels as $styleValue => $styleLabel) : ?>
                                            <option value="<?php echo htmlescape($styleValue); ?>" <?php echo $loginButtonCustomStyle === $styleValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($styleLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_login_button_text_size"><?php echo __('Button text size (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_button_text_size" name="login_button_text_size" min="10" max="28" step="1" value="<?php echo htmlescape($loginButtonTextSize); ?>" oninput="document.getElementById('doom_login_button_text_size_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_button_text_size_value"><?php echo htmlescape($loginButtonTextSize); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_button_color_mode"><?php echo __('Color mode', 'doom'); ?></label>
                                    <select id="doom_login_button_color_mode" name="login_button_color_mode">
                                        <?php foreach ($buttonColorModeLabels as $colorModeValue => $colorModeLabel) : ?>
                                            <option value="<?php echo htmlescape($colorModeValue); ?>" <?php echo $loginButtonColorMode === $colorModeValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($colorModeLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="doom_button_color_custom" class="doom-color-custom">
                                    <div class="doom-color-inputs">
                                        <div class="doom-inline-field">
                                            <label for="doom_login_button_color_picker"><?php echo __('Custom color', 'doom'); ?></label>
                                            <input type="color" id="doom_login_button_color_picker" name="login_button_color" value="<?php echo htmlescape($loginButtonColor); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_button_hover_effect"><?php echo __('Hover effect', 'doom'); ?></label>
                                    <select id="doom_login_button_hover_effect" name="login_button_hover_effect">
                                        <?php foreach ($buttonHoverEffectLabels as $effectValue => $effectLabel) : ?>
                                            <option value="<?php echo htmlescape($effectValue); ?>" <?php echo $loginButtonHoverEffect === $effectValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($effectLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="doom_button_hover_options" class="doom-color-custom">
                                    <input type="hidden" id="doom_login_button_hover_fill_mode" name="login_button_hover_fill_mode" value="custom">
                                    <div id="doom_button_hover_fill_custom" class="doom-color-inputs">
                                        <div class="doom-inline-field">
                                            <label for="doom_login_button_hover_fill_color"><?php echo __('Hover fill custom color', 'doom'); ?></label>
                                            <input type="color" id="doom_login_button_hover_fill_color" name="login_button_hover_fill_color" value="<?php echo htmlescape($loginButtonHoverFillColor); ?>">
                                        </div>
                                        <div class="doom-inline-field">
                                            <label for="doom_login_button_hover_text_color"><?php echo __('Hover text color', 'doom'); ?></label>
                                            <input type="color" id="doom_login_button_hover_text_color" name="login_button_hover_text_color" value="<?php echo htmlescape($loginButtonHoverTextColor); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="doom-step2-preview">
                            <div class="doom-button-stage" data-doom-preview="button">
                                <div class="doom-button-panel">
                                    <button type="button" class="doom-button-demo doom-button-demo--rounded" data-doom-preview-button>
                                        <?php echo __('Sign in', 'doom'); ?>
                                    </button>
                                    <div class="doom-button-panel-line"></div>
                                    <div class="doom-button-panel-line" style="width: 70%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doom-step2-row">
                        <div class="doom-section">
                            <h4><?php echo __('Fields', 'doom'); ?></h4>
                            <div class="doom-form-grid">
                                <div>
                                    <label for="doom_login_field_style"><?php echo __('Field style', 'doom'); ?></label>
                                    <select id="doom_login_field_style" name="login_field_style">
                                        <?php foreach ($fieldStyleLabels as $styleValue => $styleLabel) : ?>
                                            <option value="<?php echo htmlescape($styleValue); ?>" <?php echo $loginFieldStyle === $styleValue ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($styleLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_login_field_bg"><?php echo __('Field background', 'doom'); ?></label>
                                    <input type="color" id="doom_login_field_bg" name="login_field_bg" value="<?php echo htmlescape($loginFieldBg); ?>">
                                </div>
                                <div>
                                    <label for="doom_login_field_border"><?php echo __('Field border', 'doom'); ?></label>
                                    <input type="color" id="doom_login_field_border" name="login_field_border" value="<?php echo htmlescape($loginFieldBorder); ?>">
                                </div>
                                <div>
                                    <label for="doom_login_field_border_width"><?php echo __('Field border width (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_field_border_width" name="login_field_border_width" min="0" max="6" step="1" value="<?php echo htmlescape($loginFieldBorderWidth); ?>" oninput="document.getElementById('doom_login_field_border_width_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_field_border_width_value"><?php echo htmlescape($loginFieldBorderWidth); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_field_text"><?php echo __('Field text', 'doom'); ?></label>
                                    <input type="color" id="doom_login_field_text" name="login_field_text" value="<?php echo htmlescape($loginFieldText); ?>">
                                </div>
                                <div>
                                    <label for="doom_login_text_size"><?php echo __('Page text size (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_text_size" name="login_text_size" min="10" max="30" step="1" value="<?php echo htmlescape($loginTextSize); ?>" oninput="document.getElementById('doom_login_text_size_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_text_size_value"><?php echo htmlescape($loginTextSize); ?>px</span>
                                    </div>
                                </div>
                                <?php if (!$isGlpi11) : ?>
                                <div>
                                    <label for="doom_login_title_size"><?php echo __('Title text size (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_title_size" name="login_title_size" min="16" max="48" step="1" value="<?php echo htmlescape($loginTitleSize); ?>" oninput="document.getElementById('doom_login_title_size_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_title_size_value"><?php echo htmlescape($loginTitleSize); ?>px</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <label for="doom_login_field_text_size"><?php echo __('Field text size (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_field_text_size" name="login_field_text_size" min="10" max="36" step="1" value="<?php echo htmlescape($loginFieldTextSize); ?>" oninput="document.getElementById('doom_login_field_text_size_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_field_text_size_value"><?php echo htmlescape($loginFieldTextSize); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_field_height"><?php echo __('Field height (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_field_height" name="login_field_height" min="32" max="72" step="1" value="<?php echo htmlescape($loginFieldHeight); ?>" oninput="document.getElementById('doom_login_field_height_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_field_height_value"><?php echo htmlescape($loginFieldHeight); ?>px</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_field_width"><?php echo __('Field width (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_field_width" name="login_field_width" min="260" max="720" step="10" value="<?php echo htmlescape($loginFieldWidth); ?>" oninput="document.getElementById('doom_login_field_width_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_field_width_value"><?php echo htmlescape($loginFieldWidth); ?>px</span>
                                    </div>
                                </div>
                                <?php if (!$isGlpi11) : ?>
                                <div>
                                    <label for="doom_login_field_spacing"><?php echo __('Field spacing (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_field_spacing" name="login_field_spacing" min="8" max="48" step="1" value="<?php echo htmlescape($loginFieldSpacing); ?>" oninput="document.getElementById('doom_login_field_spacing_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_field_spacing_value"><?php echo htmlescape($loginFieldSpacing); ?>px</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="doom-field-focus">
                                    <label for="doom_login_field_focus"><?php echo __('Field focus glow', 'doom'); ?></label>
                                    <input type="color" id="doom_login_field_focus" name="login_field_focus" value="<?php echo htmlescape($loginFieldFocus); ?>">
                                </div>
                                <div>
                                    <label for="doom_login_field_radius"><?php echo __('Field radius (px)', 'doom'); ?></label>
                                    <div class="doom-range">
                                        <input type="range" id="doom_login_field_radius" name="login_field_radius" min="0" max="32" step="1" value="<?php echo htmlescape($loginFieldRadius); ?>" oninput="document.getElementById('doom_login_field_radius_value').textContent = this.value + 'px'">
                                        <span class="doom-range-value" id="doom_login_field_radius_value"><?php echo htmlescape($loginFieldRadius); ?>px</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="doom-step2-preview">
                            <div class="doom-preview-shell doom-preview-fields" data-doom-preview="fields">
                                <div class="doom-preview-field">
                                    <span class="doom-preview-field-text"><?php echo __('Login', 'doom'); ?></span>
                                </div>
                                <div class="doom-preview-field">
                                    <span class="doom-preview-field-text"><?php echo __('Password', 'doom'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doom-step2-row">
                        <div class="doom-section">
                            <h4><?php echo __('Field icons', 'doom'); ?></h4>
                            <?php $iconGridBg = doom_is_light_color($loginIconColor) ? '#0f172a' : '#ffffff'; ?>
                            <div class="doom-form-grid doom-icon-grid-scope" style="--doom-icon-color: <?php echo htmlescape($loginIconColor); ?>; --doom-icon-bg: <?php echo htmlescape($iconGridBg); ?>;">
                                <div>
                                    <label><?php echo __('Login icon', 'doom'); ?></label>
                                    <div class="doom-icon-grid">
                                        <?php foreach ($iconUserLabels as $value => $label) : ?>
                                            <label class="doom-icon-option">
                                                <input type="radio" name="login_icon_user" value="<?php echo htmlescape($value); ?>" <?php echo $loginIconUser === $value ? 'checked' : ''; ?>>
                                                <div class="doom-icon-card">
                                                    <?php if ($value === 'none') : ?>
                                                        <div class="doom-icon-placeholder"></div>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php else : ?>
                                                        <?php echo $iconSvgMap[$value] ?? ''; ?>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <label><?php echo __('Password icon', 'doom'); ?></label>
                                    <div class="doom-icon-grid">
                                        <?php foreach ($iconPasswordLabels as $value => $label) : ?>
                                            <label class="doom-icon-option">
                                                <input type="radio" name="login_icon_password" value="<?php echo htmlescape($value); ?>" <?php echo $loginIconPassword === $value ? 'checked' : ''; ?>>
                                                <div class="doom-icon-card">
                                                    <?php if ($value === 'none') : ?>
                                                        <div class="doom-icon-placeholder"></div>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php else : ?>
                                                        <?php echo $iconSvgMap[$value] ?? ''; ?>
                                                        <span><?php echo htmlescape($label); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <label for="doom_login_icon_color"><?php echo __('Icon color', 'doom'); ?></label>
                                    <input type="color" id="doom_login_icon_color" name="login_icon_color" value="<?php echo htmlescape($loginIconColor); ?>">
                                </div>
                                <div>
                                    <label for="doom_login_icon_position"><?php echo __('Icon position', 'doom'); ?></label>
                                    <select id="doom_login_icon_position" name="login_icon_position">
                                        <?php foreach ($iconPositionLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginIconPosition === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="doom-step2-preview">
                            <div class="doom-preview-shell doom-preview-icons<?php echo $loginIconPosition === 'right' ? ' icon-right' : ''; ?>" data-doom-preview="icons" data-login-icon="<?php echo htmlescape($loginIconUser); ?>" data-password-icon="<?php echo htmlescape($loginIconPassword); ?>">
                                <div class="doom-preview-icon-field">
                                    <div class="doom-preview-field doom-preview-field--icon" data-field="login">
                                        <span class="doom-preview-field-icon" data-doom-icon="user"><?php echo $iconSvgMap['user'] ?? ''; ?></span>
                                        <span class="doom-preview-field-icon" data-doom-icon="id"><?php echo $iconSvgMap['id'] ?? ''; ?></span>
                                        <span class="doom-preview-field-icon" data-doom-icon="mail"><?php echo $iconSvgMap['mail'] ?? ''; ?></span>
                                        <span class="doom-preview-field-text"><?php echo __('Login', 'doom'); ?></span>
                                    </div>
                                </div>
                                <div class="doom-preview-icon-field">
                                    <div class="doom-preview-field doom-preview-field--icon" data-field="password">
                                        <span class="doom-preview-field-icon" data-doom-icon="lock"><?php echo $iconSvgMap['lock'] ?? ''; ?></span>
                                        <span class="doom-preview-field-icon" data-doom-icon="key"><?php echo $iconSvgMap['key'] ?? ''; ?></span>
                                        <span class="doom-preview-field-icon" data-doom-icon="shield"><?php echo $iconSvgMap['shield'] ?? ''; ?></span>
                                        <span class="doom-preview-field-text"><?php echo __('Password', 'doom'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doom-step2-row">
                        <div class="doom-section">
                            <h4>
                                <?php echo __('Wallpaper', 'doom'); ?>
                                <span
                                    class="doom-info"
                                    title="<?php echo htmlescape(__('Recommended: 1920x1080 or larger.', 'doom')); ?>"
                                    data-info="<?php echo htmlescape(__('Recommended: 1920x1080 or larger.', 'doom')); ?>"
                                    aria-label="<?php echo htmlescape(__('Recommended: 1920x1080 or larger.', 'doom')); ?>"
                                    role="img"
                                    tabindex="0"
                                >i</span>
                            </h4>
                            <div class="doom-form-grid" style="margin-top: 12px;">
                                <div class="doom-grid-full">
                                    <label for="doom_wallpaper_source"><?php echo __('Wallpaper source', 'doom'); ?></label>
                                    <select id="doom_wallpaper_source" name="login_wallpaper_source">
                                        <option value="preset" <?php echo $wallpaperSource === 'preset' ? 'selected' : ''; ?>><?php echo __('Preset wallpapers', 'doom'); ?></option>
                                        <option value="custom" <?php echo $wallpaperSource === 'custom' ? 'selected' : ''; ?>><?php echo __('Custom upload', 'doom'); ?></option>
                                    </select>
                                </div>
                                <div id="doom_wallpaper_preset_wrap" class="doom-grid-full">
                                    <label for="doom_wallpaper_preset"><?php echo __('Preset wallpaper', 'doom'); ?></label>
                                    <select id="doom_wallpaper_preset" name="login_wallpaper_preset">
                                        <?php if (empty($wallpaperPresets)) : ?>
                                            <option value="" selected><?php echo __('No preset wallpapers available.', 'doom'); ?></option>
                                        <?php else : ?>
                                            <?php foreach ($wallpaperPresets as $presetFile) : ?>
                                                <?php
                                                    $presetLabel = $wallpaperPresetLabels[$presetFile] ?? $presetFile;
                                                    $presetAssets = doom_get_wallpaper_preset_assets($presetFile);
                                                    $presetPreviewUrl = $presetAssets['url'] ?? '';
                                                ?>
                                                <?php $presetPreviewWebp = $presetAssets['webp_url'] ?? ''; ?>
                                                <option
                                                    value="<?php echo htmlescape($presetFile); ?>"
                                                    data-preview-url="<?php echo htmlescape($presetPreviewUrl); ?>"
                                                    data-preview-webp-url="<?php echo htmlescape($presetPreviewWebp); ?>"
                                                    <?php echo $wallpaperPreset === $presetFile ? 'selected' : ''; ?>
                                                >
                                                    <?php echo htmlescape($presetLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_wallpaper_size"><?php echo __('Size', 'doom'); ?></label>
                                    <select id="doom_wallpaper_size" name="login_wallpaper_size">
                                        <?php foreach ($wallpaperSizeLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginWallpaperSize === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_wallpaper_position"><?php echo __('Position', 'doom'); ?></label>
                                    <select id="doom_wallpaper_position" name="login_wallpaper_position">
                                        <?php foreach ($wallpaperPositionLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginWallpaperPosition === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_wallpaper_attachment"><?php echo __('Attachment', 'doom'); ?></label>
                                    <select id="doom_wallpaper_attachment" name="login_wallpaper_attachment">
                                        <?php foreach ($wallpaperAttachmentLabels as $value => $label) : ?>
                                            <option value="<?php echo htmlescape($value); ?>" <?php echo $loginWallpaperAttachment === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlescape($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="doom_wallpaper_overlay_color"><?php echo __('Overlay color', 'doom'); ?></label>
                                    <input type="color" id="doom_wallpaper_overlay_color" name="login_wallpaper_overlay_color" value="<?php echo htmlescape($loginWallpaperOverlayColor); ?>">
                                </div>
                                <div>
                                    <label for="doom_wallpaper_overlay_opacity"><?php echo __('Overlay opacity (%)', 'doom'); ?></label>
                                    <div class="doom-range">
                                    <input type="range" id="doom_wallpaper_overlay_opacity" name="login_wallpaper_overlay_opacity" min="0" max="100" value="<?php echo htmlescape($loginWallpaperOverlayOpacity); ?>" oninput="document.getElementById('doom_wallpaper_overlay_opacity_value').textContent = this.value + '%'">
                                        <span class="doom-range-value" id="doom_wallpaper_overlay_opacity_value"><?php echo htmlescape($loginWallpaperOverlayOpacity); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="doom-step2-row">
                        <div class="doom-section doom-section-wide">
                            <div class="doom-wallpaper-card" data-wallpaper-source="<?php echo htmlescape($wallpaperSource); ?>">
                                <div class="doom-wallpaper-actions">
                                    <label class="doom-logo-action doom-logo-upload" data-doom-wallpaper-upload for="doom_login_wallpaper" aria-label="<?php echo __('Upload wallpaper', 'doom'); ?>">&#x2B06;</label>
                                    <?php if ($customWallpaperUrl) : ?>
                                        <button type="button" class="doom-logo-action doom-logo-remove" id="doom_login_wallpaper_delete" aria-label="<?php echo __('Remove uploaded wallpaper', 'doom'); ?>">×</button>
                                    <?php endif; ?>
                                </div>
                                <input class="doom-logo-input" type="file" id="doom_login_wallpaper" name="login_wallpaper" accept="image/png,image/jpeg,image/webp">
                                <input class="doom-toggle-hidden" type="checkbox" id="doom_login_wallpaper_remove" name="login_wallpaper_remove" value="1">
                                <div
                                    class="doom-wallpaper-preview"
                                    data-doom-wallpaper-preview
                                    data-wallpaper-image="<?php echo htmlescape($loginWallpaperUrl); ?>"
                                    data-wallpaper-image-webp="<?php echo htmlescape($loginWallpaperWebpUrl ?? ''); ?>"
                                    data-wallpaper-custom="<?php echo htmlescape($customWallpaperUrl ?? ''); ?>"
                                    data-wallpaper-custom-webp="<?php echo htmlescape($customWallpaperWebpUrl ?? ''); ?>"
                                >
                                    <?php if (!$loginWallpaperUrl) : ?>
                                        <div class="doom-wallpaper-placeholder"><?php echo __('No wallpaper selected yet.', 'doom'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="doom-actions doom-actions-floating">
                    <button class="submit doom-save-login" type="submit" name="doom_action" value="save_login_settings"><?php echo __('Save login page', 'doom'); ?></button>
                    <button class="submit doom-reset-login" type="submit" name="doom_action" value="reset_login_defaults"><?php echo __('Restore defaults', 'doom'); ?></button>
                </div>
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo doom_get_csrf_token(); ?>">
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var layoutSelect = document.getElementById('doom_login_layout');
                    var panelTransparent = document.getElementById('doom_login_transparent');
                    var logoPosition = document.getElementById('doom_login_logo_position');
                    var colorModeSelect = document.getElementById('doom_login_button_color_mode');
                    var colorPicker = document.getElementById('doom_login_button_color_picker');
                    var fieldStyleSelect = document.getElementById('doom_login_field_style');
                    var customSection = document.getElementById('doom_button_color_custom');
                    var hoverEffectSelect = document.getElementById('doom_login_button_hover_effect');
                    var hoverOptions = document.getElementById('doom_button_hover_options');
                    var hoverFillModeInput = document.getElementById('doom_login_button_hover_fill_mode');
                    var hoverFillColorInput = document.getElementById('doom_login_button_hover_fill_color');
                    var hoverTextColorInput = document.getElementById('doom_login_button_hover_text_color');
                    var panelSplitSection = document.getElementById('doom_panel_split_section');
                    var panelSplitPreview = document.getElementById('doom_panel_split_preview');
                    var splitControlsWrap = document.getElementById('doom_split_controls');
                    var iconColor = document.getElementById('doom_login_icon_color');
                    var splitControls = document.querySelectorAll('.doom-split-controls input, .doom-split-controls select');
                    var panelSizeFields = document.querySelectorAll('.doom-panel-size');
                    var panelStyleFields = document.querySelectorAll('.doom-panel-style-controls');
                    var logoOffsetFields = document.querySelectorAll('.doom-logo-offset-field');
                    var fieldFocusFields = document.querySelectorAll('.doom-field-focus');
                    var wallpaperInput = document.getElementById('doom_login_wallpaper');
                    var wallpaperRemove = document.getElementById('doom_login_wallpaper_remove');
                    var wallpaperDelete = document.getElementById('doom_login_wallpaper_delete');
                    var wallpaperPreview = document.querySelector('[data-doom-wallpaper-preview]');
                    var wallpaperSourceInput = document.getElementById('doom_wallpaper_source');
                    var wallpaperPresetInput = document.getElementById('doom_wallpaper_preset');
                    var wallpaperPresetWrap = document.getElementById('doom_wallpaper_preset_wrap');
                    var wallpaperUploadAction = document.querySelector('[data-doom-wallpaper-upload]');
                    var wallpaperSizeInput = document.getElementById('doom_wallpaper_size');
                    var wallpaperPositionInput = document.getElementById('doom_wallpaper_position');
                    var wallpaperAttachmentInput = document.getElementById('doom_wallpaper_attachment');
                    var wallpaperOverlayColorInput = document.getElementById('doom_wallpaper_overlay_color');
                    var wallpaperOverlayOpacityInput = document.getElementById('doom_wallpaper_overlay_opacity');
                    var form = document.getElementById('doom-login-settings-form')
                        || document.querySelector('form[action*="step=2"]')
                        || document.querySelector('form');

                    var isLightColor = function (hex) {
                        if (!hex) {
                            return false;
                        }
                        var value = hex.replace('#', '');
                        if (value.length === 3) {
                            value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2];
                        }
                        var r = parseInt(value.substring(0, 2), 16) || 0;
                        var g = parseInt(value.substring(2, 4), 16) || 0;
                        var b = parseInt(value.substring(4, 6), 16) || 0;
                        var luma = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
                        return luma > 0.75;
                    };

                    var normalizeHex = function (value) {
                        if (!value) {
                            return '';
                        }
                        value = value.trim().toUpperCase();
                        if (value[0] !== '#') {
                            value = '#' + value;
                        }
                        if (/^#[0-9A-F]{3}$/.test(value)) {
                            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
                        }
                        if (!/^#[0-9A-F]{6}$/.test(value)) {
                            return '';
                        }
                        return value;
                    };

                    var toggleColorMode = function () {
                        var isCustom = colorModeSelect && colorModeSelect.value === 'custom';
                        if (customSection) {
                            customSection.classList.toggle('doom-toggle-hidden', !isCustom);
                        }
                    };

                    var toggleHoverOptions = function () {
                        var isFill = hoverEffectSelect && hoverEffectSelect.value === 'fill';
                        if (hoverOptions) {
                            hoverOptions.classList.toggle('doom-toggle-hidden', !isFill);
                            hoverOptions.querySelectorAll('input').forEach(function (input) {
                                if (isFill) {
                                    input.removeAttribute('disabled');
                                } else {
                                    input.setAttribute('disabled', 'disabled');
                                }
                            });
                        }
                    };

                    var toggleSplitControls = function () {
                        var isClassic = layoutSelect && layoutSelect.value === 'classic';
                        if (splitControlsWrap) {
                            splitControlsWrap.classList.toggle('doom-toggle-hidden', isClassic);
                        }
                        splitControls.forEach(function (control) {
                            control.disabled = isClassic;
                        });
                    };

                    var togglePanelSizeControls = function () {
                        var isTransparent = panelTransparent && panelTransparent.value === '1';
                        panelSizeFields.forEach(function (field) {
                            field.classList.toggle('doom-toggle-hidden', isTransparent);
                            var input = field.querySelector('input');
                            if (input) {
                                if (isTransparent) {
                                    input.setAttribute('disabled', 'disabled');
                                } else {
                                    input.removeAttribute('disabled');
                                }
                            }
                        });
                        panelStyleFields.forEach(function (field) {
                            field.classList.toggle('doom-toggle-hidden', isTransparent);
                            field.querySelectorAll('input, select').forEach(function (input) {
                                if (isTransparent) {
                                    input.setAttribute('disabled', 'disabled');
                                } else {
                                    input.removeAttribute('disabled');
                                }
                            });
                        });
                        if (logoPosition) {
                            var logoPositionField = logoPosition.closest('div');
                            if (isTransparent) {
                                logoPosition.setAttribute('disabled', 'disabled');
                                if (logoPositionField) {
                                    logoPositionField.classList.add('doom-toggle-hidden');
                                }
                            } else {
                                logoPosition.removeAttribute('disabled');
                                if (logoPositionField) {
                                    logoPositionField.classList.remove('doom-toggle-hidden');
                                }
                            }
                        }

                        var isClassic = layoutSelect && layoutSelect.value === 'classic';
                        var hidePanelSplit = isTransparent && isClassic;
                        if (panelSplitSection) {
                            panelSplitSection.classList.toggle('doom-toggle-hidden', hidePanelSplit);
                        }
                        if (panelSplitPreview) {
                            panelSplitPreview.classList.toggle('doom-toggle-hidden', hidePanelSplit);
                        }
                    };

                    var toggleLogoOffsetControls = function () {
                        var isInside = logoPosition && logoPosition.value === 'inside';
                        logoOffsetFields.forEach(function (field) {
                            field.classList.toggle('doom-toggle-hidden', isInside);
                            var input = field.querySelector('input');
                            if (input) {
                                if (isInside) {
                                    input.setAttribute('disabled', 'disabled');
                                } else {
                                    input.removeAttribute('disabled');
                                }
                            }
                        });
                    };

                    var toggleFieldFocusControls = function () {
                        var isNeon = fieldStyleSelect && fieldStyleSelect.value === 'neon';
                        fieldFocusFields.forEach(function (field) {
                            field.classList.toggle('doom-toggle-hidden', !isNeon);
                        });
                    };

                    var hexToRgba = function (hex, opacity) {
                        var value = normalizeHex(hex);
                        if (!value) {
                            return '';
                        }
                        var r = parseInt(value.substring(1, 3), 16) || 0;
                        var g = parseInt(value.substring(3, 5), 16) || 0;
                        var b = parseInt(value.substring(5, 7), 16) || 0;
                        var alpha = typeof opacity === 'number' ? opacity : 1;
                        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
                    };

                    var scaleRange = function (value, min, max, outMin, outMax) {
                        if (max === min) {
                            return outMin;
                        }
                        var clamped = Math.min(max, Math.max(min, value));
                        var ratio = (clamped - min) / (max - min);
                        return outMin + (outMax - outMin) * ratio;
                    };

                    var previewLayout = document.querySelector('[data-doom-preview="layout"]');
                    var previewPanel = document.querySelector('[data-doom-preview="panel"]');
                    var previewButtonStage = document.querySelector('[data-doom-preview="button"]');
                    var previewButton = previewButtonStage ? previewButtonStage.querySelector('[data-doom-preview-button]') : null;
                    var previewFields = document.querySelector('[data-doom-preview="fields"]');
                    var previewIcons = document.querySelector('[data-doom-preview="icons"]');
                    var isPreviewButtonHovered = false;

                    var panelColorInput = document.getElementById('doom_login_color');
                    var panelOpacityInput = document.getElementById('doom_login_opacity');
                    var panelWidthInput = document.getElementById('doom_login_panel_width');
                    var panelHeightInput = document.getElementById('doom_login_panel_height');
                    var logoScaleInput = document.getElementById('doom_login_logo_scale');
                    var splitWidthInput = document.getElementById('doom_split_width');
                    var splitColorInput = document.getElementById('doom_split_color');
                    var splitOpacityInput = document.getElementById('doom_split_opacity');
                    var splitBlurInput = document.getElementById('doom_split_blur');
                    var accentColorInput = document.getElementById('doom_login_accent_color');
                    var loginTextColorInput = document.getElementById('doom_login_text_color');
                    var buttonShapeInput = document.getElementById('doom_login_button_shape');
                    var buttonStyleInput = document.getElementById('doom_login_button_custom_style');
                    var buttonTextSizeInput = document.getElementById('doom_login_button_text_size');
                    var fieldBgInput = document.getElementById('doom_login_field_bg');
                    var fieldBorderInput = document.getElementById('doom_login_field_border');
                    var fieldBorderWidthInput = document.getElementById('doom_login_field_border_width');
                    var fieldTextInput = document.getElementById('doom_login_field_text');
                    var fieldTextSizeInput = document.getElementById('doom_login_field_text_size');
                    var fieldRadiusInput = document.getElementById('doom_login_field_radius');
                    var fieldHeightInput = document.getElementById('doom_login_field_height');
                    var fieldWidthInput = document.getElementById('doom_login_field_width');
                    var fieldFocusInput = document.getElementById('doom_login_field_focus');
                    var logoOffsetInput = document.getElementById('doom_login_logo_offset');
                    var loginHideLoginTextInput = document.getElementById('doom_hide_login_text');
                    var iconPositionInput = document.getElementById('doom_login_icon_position');
                    var iconUserInputs = document.querySelectorAll('input[name="login_icon_user"]');

                    var updateLayoutPreview = function (preview) {
                        if (!preview) {
                            return;
                        }
                        var layoutValue = layoutSelect ? layoutSelect.value : 'classic';
                        preview.setAttribute('data-layout', layoutValue);
                        preview.style.setProperty('--doom-preview-panel-align', layoutValue === 'split-left' ? 'flex-start' : layoutValue === 'split-right' ? 'flex-end' : 'center');

                        var isLayoutPreview = preview.getAttribute('data-doom-preview') === 'layout';
                        var splitWidth = splitWidthInput ? parseInt(splitWidthInput.value || '50', 10) : 50;
                        preview.style.setProperty('--doom-preview-split-width', splitWidth + '%');

                        var splitOpacity = splitOpacityInput ? parseInt(splitOpacityInput.value || '55', 10) / 100 : 0.55;
                        var splitColor = isLayoutPreview ? '#cbd5e1' : (splitColorInput ? normalizeHex(splitColorInput.value) : '#cbd5e1');
                        var splitRgba = splitColor ? hexToRgba(splitColor, splitOpacity) : '';
                        if (splitRgba) {
                            preview.style.setProperty('--doom-preview-split-color', splitRgba);
                        }
                        if (!isLayoutPreview) {
                            preview.style.setProperty('--doom-preview-split-blur', splitBlurInput ? parseInt(splitBlurInput.value || '10', 10) + 'px' : '10px');
                        }

                        var panelOpacity = panelOpacityInput ? parseInt(panelOpacityInput.value || '65', 10) / 100 : 0.65;
                        if (isLayoutPreview && panelTransparent && panelTransparent.value === '0') {
                            panelOpacity = 0.9;
                        }
                        var panelHex = panelColorInput ? normalizeHex(panelColorInput.value) : '#111827';
                        if (isLayoutPreview && panelTransparent && panelTransparent.value === '0') {
                            panelHex = '#e2e8f0';
                        }
                        var panelRgba = panelHex ? hexToRgba(panelHex, panelOpacity) : '';
                        if (panelRgba) {
                            preview.style.setProperty('--doom-preview-panel-color', panelRgba);
                        }
                        if (panelTransparent && panelTransparent.value === '1') {
                            preview.style.setProperty('--doom-preview-panel-opacity', '1');
                            preview.style.setProperty('--doom-preview-panel-border', 'none');
                            if (!isLayoutPreview) {
                                preview.style.setProperty('--doom-preview-panel-color', 'transparent');
                                preview.style.setProperty('--doom-preview-panel-shadow', 'none');
                            }
                        } else {
                            preview.style.setProperty('--doom-preview-panel-opacity', '1');
                            preview.style.setProperty('--doom-preview-panel-border', '1px solid rgba(148, 163, 184, 0.6)');
                            if (!isLayoutPreview) {
                                preview.style.setProperty('--doom-preview-panel-shadow', '0 10px 24px rgba(15, 23, 42, 0.25)');
                            }
                        }

                        if (isLayoutPreview && (layoutValue === 'split-left' || layoutValue === 'split-right')) {
                            preview.style.setProperty('--doom-preview-panel-color', 'transparent');
                        }

                        var accentColor = accentColorInput ? normalizeHex(accentColorInput.value) : '';
                        if (accentColor) {
                            preview.style.setProperty('--doom-preview-accent', accentColor);
                        }
                        var textColor = loginTextColorInput ? normalizeHex(loginTextColorInput.value) : '';
                        if (textColor) {
                            preview.style.setProperty('--doom-preview-text', textColor);
                        }

                        var panelWidth = panelWidthInput ? parseInt(panelWidthInput.value || '520', 10) : 520;
                        var panelHeight = panelHeightInput ? parseInt(panelHeightInput.value || '560', 10) : 560;
                        var widthPct = scaleRange(panelWidth, 280, 720, 55, 85);
                        var heightPct = scaleRange(panelHeight, 360, 780, 55, 80);
                        if (isLayoutPreview && (layoutValue === 'split-left' || layoutValue === 'split-right')) {
                            widthPct = Math.min(widthPct, Math.max(45, splitWidth - 6));
                        }
                        preview.style.setProperty('--doom-preview-panel-width', widthPct + '%');
                        preview.style.setProperty('--doom-preview-panel-height', heightPct + '%');

                        if (!isLayoutPreview) {
                            var showSplit = layoutValue === 'split-left' || layoutValue === 'split-right';
                            preview.style.setProperty('--doom-preview-split-visibility', showSplit ? '1' : '0');
                        }

                        var logoScale = logoScaleInput ? parseInt(logoScaleInput.value || '200', 10) : 200;
                        var logoWidth = scaleRange(logoScale, 80, 480, 45, 85);
                        preview.style.setProperty('--doom-preview-logo-width', logoWidth + '%');

                        var logoOffset = logoOffsetInput ? parseInt(logoOffsetInput.value || '0', 10) : 0;
                        var logoOffsetPx = Math.min(4, Math.max(-12, logoOffset / 6));
                        var logoOutside = logoPosition ? logoPosition.value === 'inside' : true;
                        if (!logoOutside) {
                            logoOffsetPx = 0;
                        }
                        preview.style.setProperty('--doom-preview-logo-offset', logoOffsetPx + 'px');

                        if (logoPosition) {
                            preview.setAttribute('data-logo-position', logoOutside ? 'outside' : 'inside');
                        }
                        preview.setAttribute('data-login-text-hidden', loginHideLoginTextInput && loginHideLoginTextInput.value === '1' ? '1' : '0');

                        var previewButtonInline = preview.querySelector('[data-doom-preview-button]');
                        if (previewButtonInline) {
                            var buttonColor = accentColor || '#0ea5e9';
                            previewButtonInline.style.background = buttonColor;
                            previewButtonInline.style.color = isLightColor(buttonColor) ? '#0f172a' : '#ffffff';
                        }

                        preview.style.setProperty('--doom-preview-field-bg', 'rgba(255, 255, 255, 0.85)');
                        preview.style.setProperty('--doom-preview-field-border', 'rgba(148, 163, 184, 0.7)');
                        preview.style.setProperty('--doom-preview-field-border-width', '1px');
                    };

                    var applyButtonHoverState = function () {
                        if (!previewButton) {
                            return;
                        }
                        var effect = hoverEffectSelect ? hoverEffectSelect.value : 'press';
                        var baseBg = previewButton.dataset.doomBaseBg || '';
                        var baseBorder = previewButton.dataset.doomBaseBorder || '';
                        var baseText = previewButton.dataset.doomBaseText || '';
                        var baseShadow = previewButton.dataset.doomBaseShadow || '';

                        if (!isPreviewButtonHovered || !effect) {
                            previewButton.style.background = baseBg;
                            previewButton.style.border = '2px solid ' + baseBorder;
                            previewButton.style.color = baseText;
                            previewButton.style.transform = '';
                            if (baseShadow) {
                                previewButton.style.boxShadow = baseShadow;
                            }
                            return;
                        }

                        if (effect === 'press') {
                            previewButton.style.background = baseBg;
                            previewButton.style.border = '2px solid ' + baseBorder;
                            previewButton.style.color = baseText;
                            previewButton.style.transform = 'translateY(2px)';
                            previewButton.style.boxShadow = '0 2px 6px rgba(15, 23, 42, 0.35)';
                            return;
                        }

                        if (effect === 'fill') {
                            var hoverFill = previewButton.dataset.doomHoverFill || baseBorder;
                            var hoverText = previewButton.dataset.doomHoverText || baseText;
                            previewButton.style.background = hoverFill || baseBg;
                            previewButton.style.border = '2px solid ' + (hoverFill || baseBorder);
                            previewButton.style.color = hoverText;
                            previewButton.style.transform = '';
                            if (baseShadow) {
                                previewButton.style.boxShadow = baseShadow;
                            }
                        }
                    };

                    var updateButtonPreview = function () {
                        if (!previewButton) {
                            return;
                        }
                        var shape = buttonShapeInput ? buttonShapeInput.value : 'rounded';
                        previewButton.classList.remove(
                            'doom-button-demo--square',
                            'doom-button-demo--rounded',
                            'doom-button-demo--pill',
                            'doom-button-demo--cut',
                            'doom-button-demo--soft'
                        );
                        previewButton.classList.add('doom-button-demo--' + shape);

                        var color = '#0ea5e9';
                        var customColor = colorPicker ? normalizeHex(colorPicker.value) : '';
                        var accentColor = accentColorInput ? normalizeHex(accentColorInput.value) : '';
                        if (colorModeSelect && colorModeSelect.value === 'custom' && customColor) {
                            color = customColor;
                        } else if (accentColor) {
                            color = accentColor;
                        }

                        var isOutline = buttonStyleInput && buttonStyleInput.value === 'outline';
                        var baseBg = isOutline ? 'transparent' : color;
                        var baseBorder = color;
                        var baseText = isOutline ? color : (isLightColor(color) ? '#0f172a' : '#ffffff');
                        previewButton.style.background = baseBg;
                        previewButton.style.border = '2px solid ' + baseBorder;
                        previewButton.style.color = baseText;
                        previewButton.dataset.doomBaseBg = baseBg;
                        previewButton.dataset.doomBaseBorder = baseBorder;
                        previewButton.dataset.doomBaseText = baseText;

                        var hoverFillMode = hoverFillModeInput ? hoverFillModeInput.value : 'custom';
                        var hoverFillCustom = hoverFillColorInput ? normalizeHex(hoverFillColorInput.value) : '';
                        var hoverFillColor = hoverFillMode === 'button' ? color : (hoverFillCustom || color);
                        var hoverTextColor = hoverTextColorInput ? normalizeHex(hoverTextColorInput.value) : '';
                        if (!hoverTextColor) {
                            hoverTextColor = isLightColor(hoverFillColor) ? '#0f172a' : '#ffffff';
                        }
                        previewButton.dataset.doomHoverFill = hoverFillColor;
                        previewButton.dataset.doomHoverText = hoverTextColor;

                        var textSize = buttonTextSizeInput ? parseInt(buttonTextSizeInput.value || '14', 10) : 14;
                        previewButton.style.fontSize = Math.max(10, Math.min(18, textSize)) + 'px';

                        previewButton.style.transform = '';
                        previewButton.style.boxShadow = '';
                        previewButton.dataset.doomBaseShadow = window.getComputedStyle(previewButton).boxShadow;
                        applyButtonHoverState();
                    };

                    var applyFieldStyles = function (container) {
                        if (!container) {
                            return;
                        }
                        var fields = container.querySelectorAll('.doom-preview-field');
                        if (!fields.length) {
                            return;
                        }
                        var bg = fieldBgInput ? normalizeHex(fieldBgInput.value) : '#ffffff';
                        var border = fieldBorderInput ? normalizeHex(fieldBorderInput.value) : '#cbd5f5';
                        var focus = fieldFocusInput ? normalizeHex(fieldFocusInput.value) : border;
                        var borderWidth = fieldBorderWidthInput ? parseInt(fieldBorderWidthInput.value || '1', 10) : 1;
                        var radius = fieldRadiusInput ? parseInt(fieldRadiusInput.value || '10', 10) : 10;
                        var height = fieldHeightInput ? parseInt(fieldHeightInput.value || '36', 10) : 36;
                        var width = fieldWidthInput ? parseInt(fieldWidthInput.value || '520', 10) : 520;
                        var style = fieldStyleSelect ? fieldStyleSelect.value : 'solid';
                        var textColor = fieldTextInput ? normalizeHex(fieldTextInput.value) : '#111827';
                        var textSize = fieldTextSizeInput ? parseInt(fieldTextSizeInput.value || '14', 10) : 14;

                        fields.forEach(function (field) {
                            field.style.height = height + 'px';
                            field.style.width = 'min(100%, ' + width + 'px)';
                            field.style.maxWidth = width + 'px';
                            field.style.borderRadius = radius + 'px';
                            field.style.borderWidth = borderWidth + 'px';
                            field.style.borderStyle = 'solid';
                            field.style.boxShadow = 'none';
                            field.style.borderColor = border || '#cbd5f5';
                            field.style.background = bg || '#ffffff';

                            if (style === 'glass') {
                                field.style.background = hexToRgba(bg, 0.35);
                                field.style.borderColor = hexToRgba(border, 0.6);
                                field.style.boxShadow = '0 8px 16px rgba(15, 23, 42, 0.12)';
                            } else if (style === 'soft') {
                                field.style.boxShadow = '0 10px 20px rgba(15, 23, 42, 0.15)';
                            } else if (style === 'outline') {
                                field.style.background = 'transparent';
                                field.style.boxShadow = 'none';
                            } else if (style === 'underline') {
                                field.style.background = 'transparent';
                                field.style.border = 'none';
                                field.style.borderBottom = borderWidth + 'px solid ' + (border || '#cbd5f5');
                                field.style.borderRadius = '0';
                            } else if (style === 'neon') {
                                field.style.boxShadow = '0 0 0 2px ' + hexToRgba(focus, 0.45) + ', 0 0 16px ' + hexToRgba(focus, 0.6);
                            }

                            var textNode = field.querySelector('.doom-preview-field-text');
                            if (textNode) {
                                textNode.style.color = textColor || '#111827';
                                textNode.style.fontSize = Math.max(10, Math.min(22, textSize)) + 'px';
                            }
                        });
                    };

                    var updateFieldPreview = function () {
                        if (previewFields) {
                            var style = fieldStyleSelect ? fieldStyleSelect.value : 'solid';
                            var bg = fieldBgInput ? normalizeHex(fieldBgInput.value) : '#ffffff';
                            var border = fieldBorderInput ? normalizeHex(fieldBorderInput.value) : '#cbd5f5';
                            var artTone = bg || '#ffffff';
                            if (style === 'outline' || style === 'underline') {
                                artTone = border || bg || '#ffffff';
                            }
                            var surface = isLightColor(artTone) ? '#0f172a' : '#ffffff';
                            previewFields.style.setProperty('--doom-preview-field-surface', surface);
                            previewFields.style.setProperty('--doom-preview-field-text-size', (fieldTextSizeInput ? fieldTextSizeInput.value : 14) + 'px');
                            previewFields.style.setProperty('--doom-preview-field-text-color', fieldTextInput ? fieldTextInput.value : '#111827');
                            if (fieldWidthInput) {
                                previewFields.style.setProperty('--doom-preview-field-width', fieldWidthInput.value + 'px');
                            }
                        }
                        applyFieldStyles(previewFields);
                    };

                    var pulseFieldPreview = function () {
                        if (!previewFields) {
                            return;
                        }
                        previewFields.classList.remove('doom-field-pulse');
                        void previewFields.offsetWidth;
                        previewFields.classList.add('doom-field-pulse');
                    };

                    var updateIconPreview = function () {
                        if (!previewIcons) {
                            return;
                        }
                        var selectedLogin = document.querySelector('input[name="login_icon_user"]:checked');
                        var selectedPassword = document.querySelector('input[name="login_icon_password"]:checked');
                        var loginIconValue = selectedLogin ? selectedLogin.value : 'none';
                        var passwordIconValue = selectedPassword ? selectedPassword.value : 'none';
                        previewIcons.setAttribute('data-login-icon', loginIconValue);
                        previewIcons.setAttribute('data-password-icon', passwordIconValue);
                        if (iconPositionInput && iconPositionInput.value === 'right') {
                            previewIcons.classList.add('icon-right');
                        } else {
                            previewIcons.classList.remove('icon-right');
                        }
                        if (iconColor) {
                            previewIcons.style.setProperty('--doom-preview-icon-color', iconColor.value);
                        }
                        var style = fieldStyleSelect ? fieldStyleSelect.value : 'solid';
                        var bg = fieldBgInput ? normalizeHex(fieldBgInput.value) : '#ffffff';
                        var border = fieldBorderInput ? normalizeHex(fieldBorderInput.value) : '#cbd5f5';
                        var artTone = bg || '#ffffff';
                        if (style === 'outline' || style === 'underline') {
                            artTone = border || bg || '#ffffff';
                        }
                        var iconTone = iconColor ? normalizeHex(iconColor.value) : '';
                        var surfaceSource = iconTone || artTone;
                        var surface = isLightColor(surfaceSource) ? '#0f172a' : '#ffffff';
                        previewIcons.style.setProperty('--doom-preview-field-surface', surface);
                        previewIcons.style.setProperty('--doom-preview-field-text-size', (fieldTextSizeInput ? fieldTextSizeInput.value : 14) + 'px');
                        previewIcons.style.setProperty('--doom-preview-field-text-color', fieldTextInput ? fieldTextInput.value : '#111827');
                        if (fieldWidthInput) {
                            previewIcons.style.setProperty('--doom-preview-field-width', fieldWidthInput.value + 'px');
                        }
                        applyFieldStyles(previewIcons);
                    };

                    var updateWallpaperPreview = function () {
                        if (!wallpaperPreview) {
                            return;
                        }
                        var imageUrl = wallpaperPreview.getAttribute('data-wallpaper-image') || '';
                        var imageWebpUrl = wallpaperPreview.getAttribute('data-wallpaper-image-webp') || '';
                        var overlayColor = wallpaperOverlayColorInput ? normalizeHex(wallpaperOverlayColorInput.value) : '';
                        if (!overlayColor) {
                            overlayColor = '#000000';
                        }
                        var overlayOpacity = wallpaperOverlayOpacityInput ? parseInt(wallpaperOverlayOpacityInput.value || '0', 10) / 100 : 0;
                        var overlayRgba = hexToRgba(overlayColor, isNaN(overlayOpacity) ? 0.35 : overlayOpacity);
                        var backgroundLayers = 'linear-gradient(' + overlayRgba + ', ' + overlayRgba + ')';
                        if (imageUrl) {
                            var fallbackMime = 'image/jpeg';
                            var match = imageUrl.toLowerCase().match(/\.(jpe?g|png|webp)(?:\?|#|$)/);
                            if (match && match[1] === 'png') {
                                fallbackMime = 'image/png';
                            } else if (match && match[1] === 'webp') {
                                fallbackMime = 'image/webp';
                            }
                            if (imageWebpUrl) {
                                backgroundLayers += ', image-set(url(\"' + imageWebpUrl + '\") type(\"image/webp\"), url(\"' + imageUrl + '\") type(\"' + fallbackMime + '\"))';
                            } else {
                                backgroundLayers += ', url(\"' + imageUrl + '\")';
                            }
                        }
                        wallpaperPreview.style.setProperty('--doom-wallpaper-background', backgroundLayers);

                        var sizeValue = wallpaperSizeInput ? wallpaperSizeInput.value : 'cover';
                        var previewSizeMap = {
                            cover: 'contain',
                            contain: 'contain',
                            stretch: 'contain',
                            original: 'contain'
                        };
                        wallpaperPreview.style.setProperty('--doom-wallpaper-preview-size', previewSizeMap[sizeValue] || 'contain');

                        if (wallpaperPreview.style.getPropertyValue('--doom-wallpaper-position')) {
                            wallpaperPreview.style.removeProperty('--doom-wallpaper-position');
                        }

                        wallpaperPreview.style.setProperty('background-attachment', 'scroll');

                        var placeholder = wallpaperPreview.querySelector('.doom-wallpaper-placeholder');
                        if (placeholder) {
                            placeholder.style.display = imageUrl ? 'none' : 'flex';
                        }
                    };

                    var getPresetPreviewUrl = function () {
                        if (!wallpaperPresetInput) {
                            return '';
                        }
                        var selected = wallpaperPresetInput.options[wallpaperPresetInput.selectedIndex];
                        return selected ? (selected.getAttribute('data-preview-url') || '') : '';
                    };

                    var getPresetPreviewWebpUrl = function () {
                        if (!wallpaperPresetInput) {
                            return '';
                        }
                        var selected = wallpaperPresetInput.options[wallpaperPresetInput.selectedIndex];
                        return selected ? (selected.getAttribute('data-preview-webp-url') || '') : '';
                    };

                    var toggleWallpaperSource = function () {
                        var source = wallpaperSourceInput ? wallpaperSourceInput.value : 'preset';
                        var isCustom = source === 'custom';
                        if (wallpaperPresetWrap) {
                            wallpaperPresetWrap.classList.toggle('doom-toggle-hidden', isCustom);
                        }
                        if (wallpaperPresetInput) {
                            wallpaperPresetInput.disabled = isCustom;
                        }
                        if (wallpaperInput) {
                            if (isCustom) {
                                wallpaperInput.removeAttribute('disabled');
                            } else {
                                wallpaperInput.setAttribute('disabled', 'disabled');
                            }
                        }
                        if (wallpaperUploadAction) {
                            wallpaperUploadAction.classList.toggle('doom-toggle-hidden', !isCustom);
                        }
                        if (wallpaperPreview) {
                            var customUrl = wallpaperPreview.getAttribute('data-wallpaper-custom') || '';
                            var customWebp = wallpaperPreview.getAttribute('data-wallpaper-custom-webp') || '';
                            var presetUrl = getPresetPreviewUrl();
                            var presetWebp = getPresetPreviewWebpUrl();
                            wallpaperPreview.setAttribute('data-wallpaper-image', isCustom ? customUrl : presetUrl);
                            wallpaperPreview.setAttribute('data-wallpaper-image-webp', isCustom ? customWebp : presetWebp);
                            updateWallpaperPreview();
                        }
                    };

                    if (previewButton) {
                        previewButton.addEventListener('mouseenter', function () {
                            isPreviewButtonHovered = true;
                            applyButtonHoverState();
                        });
                        previewButton.addEventListener('mouseleave', function () {
                            isPreviewButtonHovered = false;
                            applyButtonHoverState();
                        });
                    }

                    var updateAllPreviews = function () {
                        updateLayoutPreview(previewLayout);
                        updateLayoutPreview(previewPanel);
                        updateButtonPreview();
                        updateFieldPreview();
                        updateIconPreview();
                        updateWallpaperPreview();
                    };
                    if (colorPicker) {
                        colorPicker.addEventListener('input', function (event) {
                            var value = normalizeHex(event.target.value);
                            if (value) {
                                if (colorModeSelect) {
                                    colorModeSelect.value = 'custom';
                                }
                                toggleColorMode();
                            }
                        });
                    }

                    if (wallpaperInput && form) {
                        wallpaperInput.addEventListener('change', function () {
                            if (wallpaperInput.files && wallpaperInput.files.length) {
                                if (wallpaperPreview) {
                                    var fileUrl = URL.createObjectURL(wallpaperInput.files[0]);
                                    wallpaperPreview.setAttribute('data-wallpaper-custom', fileUrl);
                                    wallpaperPreview.setAttribute('data-wallpaper-image', fileUrl);
                                    wallpaperPreview.setAttribute('data-wallpaper-custom-webp', '');
                                    wallpaperPreview.setAttribute('data-wallpaper-image-webp', '');
                                }
                                if (wallpaperSourceInput) {
                                    wallpaperSourceInput.value = 'custom';
                                }
                                updateWallpaperPreview();
                                if (wallpaperRemove) {
                                    wallpaperRemove.checked = false;
                                }
                            }
                        });
                    }

                    if (wallpaperDelete && wallpaperRemove && form) {
                        wallpaperDelete.addEventListener('click', function () {
                            wallpaperRemove.checked = true;
                            if (wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'custom';
                            }
                            var saveActionButton = form.querySelector('button[name="doom_action"][value="save_login_settings"]');
                            if (typeof form.requestSubmit === 'function' && saveActionButton) {
                                form.requestSubmit(saveActionButton);
                                return;
                            }
                            var actionInput = form.querySelector('input[name="doom_action"]');
                            if (!actionInput) {
                                actionInput = document.createElement('input');
                                actionInput.type = 'hidden';
                                actionInput.name = 'doom_action';
                                form.appendChild(actionInput);
                            }
                            actionInput.value = 'save_login_settings';
                            form.submit();
                        });
                    }

                    [
                        wallpaperSizeInput,
                        wallpaperPositionInput,
                        wallpaperAttachmentInput,
                        wallpaperOverlayColorInput,
                        wallpaperOverlayOpacityInput
                    ].forEach(function (input) {
                        if (!input) {
                            return;
                        }
                        input.addEventListener('input', updateWallpaperPreview);
                        input.addEventListener('change', updateWallpaperPreview);
                    });

                    if (wallpaperSourceInput) {
                        wallpaperSourceInput.addEventListener('change', toggleWallpaperSource);
                    }

                    if (wallpaperPresetInput) {
                        wallpaperPresetInput.addEventListener('change', function () {
                            if (wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'preset';
                            }
                            toggleWallpaperSource();
                        });
                    }

                    if (form) {
                        form.addEventListener('submit', function () {
                            if (!wallpaperInput || !wallpaperInput.files || !wallpaperInput.files.length) {
                                return;
                            }
                            if (wallpaperSourceInput) {
                                wallpaperSourceInput.value = 'custom';
                            }
                            wallpaperInput.removeAttribute('disabled');
                        });
                    }

                    if (layoutSelect) {
                        layoutSelect.addEventListener('change', function () {
                            toggleSplitControls();
                            togglePanelSizeControls();
                        });
                    }

                    if (panelTransparent) {
                        panelTransparent.addEventListener('change', function () {
                            togglePanelSizeControls();
                        });
                    }

                    if (logoPosition) {
                        logoPosition.addEventListener('change', function () {
                            toggleLogoOffsetControls();
                        });
                    }

                    if (fieldStyleSelect) {
                        fieldStyleSelect.addEventListener('change', function () {
                            toggleFieldFocusControls();
                        });
                    }

                    if (fieldBorderWidthInput) {
                        fieldBorderWidthInput.addEventListener('input', pulseFieldPreview);
                    }

                    if (fieldTextSizeInput) {
                        fieldTextSizeInput.addEventListener('input', pulseFieldPreview);
                    }

                    if (fieldWidthInput) {
                        fieldWidthInput.addEventListener('input', pulseFieldPreview);
                    }

                    if (colorModeSelect) {
                        colorModeSelect.addEventListener('change', function () {
                            toggleColorMode();
                        });
                    }

                    if (hoverEffectSelect) {
                        hoverEffectSelect.addEventListener('change', function () {
                            toggleHoverOptions();
                        });
                    }

                    if (iconColor) {
                        iconColor.addEventListener('input', function () {
                            var iconScopes = document.querySelectorAll('.doom-icon-grid-scope');
                            var isLight = isLightColor(iconColor.value);
                            var bgColor = isLight ? '#0f172a' : '#ffffff';
                            iconScopes.forEach(function (scope) {
                                scope.style.setProperty('--doom-icon-color', iconColor.value);
                                scope.style.setProperty('--doom-icon-bg', bgColor);
                            });
                        });
                    }

                    if (form) {
                        form.addEventListener('input', updateAllPreviews);
                        form.addEventListener('change', updateAllPreviews);
                    }

                    toggleColorMode();
                    toggleHoverOptions();
                    toggleSplitControls();
                    togglePanelSizeControls();
                    toggleLogoOffsetControls();
                    toggleFieldFocusControls();
                    toggleWallpaperSource();
                    updateAllPreviews();
                    document.querySelectorAll('input[type="range"]').forEach(function (input) {
                        var output = document.getElementById(input.id + '_value');
                        if (!output) {
                            return;
                        }
                        var unitMatch = output.textContent.match(/[^0-9.]+$/);
                        var unit = unitMatch ? unitMatch[0] : '';
                        var updateValue = function () {
                            output.textContent = input.value + unit;
                        };
                        input.addEventListener('input', updateValue, { passive: true });
                        input.addEventListener('change', updateValue, { passive: true });
                        input.addEventListener('pointermove', updateValue, { passive: true });
                        input.addEventListener('mousemove', updateValue, { passive: true });
                        updateValue();
                    });
                });
            </script>
        <?php elseif ($currentStep === 3) : ?>
            <div class="doom-intro">
                <div class="doom-intro-copy">
                    <span class="doom-intro-eyebrow"><?php echo __('Premium features', 'doom'); ?></span>
                    <h3><?php echo __('Unlock advanced Doom upgrades.', 'doom'); ?></h3>
                    <p><?php echo __('This area is available in the paid version. Get more control and deeper customization for your GLPI experience.', 'doom'); ?></p>
                    <ul>
                        <li><?php echo __('Download and upload configuration presets.', 'doom'); ?></li>
                        <li><?php echo __('Theme customization with one-click presets.', 'doom'); ?></li>
                        <li><?php echo __('Menu color variations and layout tuning for GLPI.', 'doom'); ?></li>
                        <li><?php echo __('Extra custom colors beyond the default GLPI palette.', 'doom'); ?></li>
                    </ul>
                    <div class="doom-intro-actions">
                        <a class="doom-cta primary" href="https://brandkit.marcati.com.br" target="_blank" rel="noopener noreferrer"><?php echo __('See plans', 'doom'); ?></a>
                    </div>
                </div>
                <div class="doom-intro-panel">
                    <div class="doom-intro-card">
                        <span>&#x1F512;</span>
                        <h4><?php echo __('Premium access', 'doom'); ?></h4>
                        <p><?php echo __('Request access to unlock these features for your team.', 'doom'); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php

Html::footer();
