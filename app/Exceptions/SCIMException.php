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

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class SCIMException extends Exception
{
	protected $scimType = "invalidValue";
	protected $httpCode = 404;

	protected $errors = [];
	
	public function __construct($message)
    {
		parent::__construct($message);
	}
	
	public function setScimType($scimType) : SCIMException
    {
	    $this->scimType = $scimType;
	    
	    return $this;
	}
	
	public function setCode($code) : SCIMException
    {
	    $this->httpCode = $code;
	    
	    return $this;
	}

	public function setErrors($errors)
    {
        $this->errors = $errors;

        return $this;
	}
	
	public function report()
    {
        Log::debug(sprintf("Validation failed. Errors: %s\n\nMessage: %s\n\nBody: %s",
            json_encode($this->errors, JSON_PRETTY_PRINT),
            $this->getMessage(),
            request()->getContent()
        ));
    }

	
	public function render($request)
    {
		return response((new \ArieTimmerman\Laravel\SCIMServer\SCIM\Error(
		    $this->getMessage(),
            $this->httpCode,
            $this->scimType
        ))->setErrors($this->errors)  ,$this->httpCode) ;
	}
}
