#!/bin/bash

#
# ---------------------------------------------------------------------
# SingleSignOn is a plugin which allows to use SSO for auth
# ---------------------------------------------------------------------
# Copyright (C) 2026 Edgard
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
# ---------------------------------------------------------------------
# @copyright Copyright © 2021 - 2026 Edgard
# @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
# @link      https://github.com/edgardmessias/glpi-singlesignon/
# ---------------------------------------------------------------------
#

soft='singlesignon'
email='@edgardmessias'
copyright='glpi-singlesignon Development Team'

# Only strings with domain specified are extracted (use Xt args of keyword param to set number of args needed)

xgettext *.php */*.php --copyright-holder="$copyright" --package-name="$soft" -o locales/singlesignon.pot -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po  \
	--keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t \
	--keyword=_ex:1c,2,3t --keyword=_nx:1c,2,3,5t --keyword=_sx:1c,2,3t --keyword=__sso:1
