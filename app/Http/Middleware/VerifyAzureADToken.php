<?php

/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Http\Middleware;

use App\Exceptions\SCIMException;
use App\Ldaplibs\SettingsManager;
use Closure;

class VerifyAzureADToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     * @throws SCIMException
     */
    public function handle($request, Closure $next)
    {
        $settingManagement = new SettingsManager();
        $token = $settingManagement->getAzureADAPItoken();

        $authorization = $request->header('authorization');

        if ($authorization !== "Bearer ".$token) {
            throw (new SCIMException('The Authorization token header not found'))->setCode(401);
        }

        return $next($request);
    }
}
