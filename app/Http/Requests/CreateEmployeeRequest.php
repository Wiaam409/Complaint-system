<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'phone'          => 'required|string|unique:users,phone',
            'password'       => 'required|string|min:6',
            'governorate_id' => 'required|exists:governorates,id',
            'department_id'  => 'required|exists:departments,id',
        ];
    }

    public function messages()
    {
        return [
            'governorate_id.required' => 'Governorate is required',
            'department_id.required'  => 'Department is required',
        ];
    }
}
