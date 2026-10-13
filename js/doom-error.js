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

document.addEventListener('DOMContentLoaded', function () {
  // Doom: remove a linha de copyright do GLPI (fallback por texto, à prova de markup).
  try {
    var nodes = document.querySelectorAll('footer, .footer, .copyright, a[href*="teclib"]');
    for (var i = 0; i < nodes.length; i++) {
      var t = nodes[i].textContent || '';
      if (t.indexOf('GLPI Copyright') !== -1 || t.indexOf('Teclib') !== -1) {
        nodes[i].style.display = 'none';
      }
    }
  } catch (e) {}

  var body = document.body;
  if (!body || !body.classList.contains('horizontal-layout')) {
    return;
  }

  var card = document.querySelector('#page > .card');
  if (!card) {
    return;
  }

  var alert = card.querySelector('.alert');
  if (!alert) {
    return;
  }

  body.classList.add('doom-error-page');
});
