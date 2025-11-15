<?php

namespace App\Http\Requests;
USE Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SignUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|min:3',
            'phone' => [
                'required',
                'regex:/^(\+?963|0?9)\d{8}$/',
                'unique:users'
            ],
            'email' => 'required|string|email|unique:users',
            'password' => 'required|min:8|confirmed'
        ];
    }
}
