<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every API form request: Spanish field names in validation
 * messages (lang/es/fields.php). Authorization is done by route permissions.
 */
abstract class ApiRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = trans('fields');

        return is_array($fields) ? $fields : [];
    }
}
