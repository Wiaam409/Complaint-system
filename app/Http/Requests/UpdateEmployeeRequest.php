<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $userId = $this->route('id');

        return [
            'name'           => 'sometimes|string|max:255',
            'email'          => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone'          => [
                'sometimes',
                'string',
                Rule::unique('users', 'phone')->ignore($userId)
            ],
            'password'       => 'sometimes|string|min:6',
            'governorate_id' => 'sometimes|nullable|exists:governorates,id',
            'department_id'  => 'sometimes|nullable|exists:departments,id',
            'is_active'      => 'sometimes|boolean',
        ];
    }
}
