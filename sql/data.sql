-- ===================================================================
-- This file is part of AutoMicroEntreprise Module, a module for Dolibarr.
-- Copyright (C) 2013-2018 Fabrice Delliaux <netbox253@gmail.com>
-- Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, version 3 of the License.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.
--
-- Run each time the module is enabled.
-- ===================================================================

delete from llx_ame_rates where year='0';

-- 8.0.0: convert the legacy rates (first.second with "accuracy" decimals).
-- The module now writes 0 in first and second, so a rate is converted once.
update llx_ame_rates set rate = first + second / power(10, accuracy) where rate is null and level > 0 and (first <> 0 or second <> 0);
