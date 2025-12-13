<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminListComplaintsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'department_id'   => 'required|integer|exists:departments,id',
            'governorate_id'  => 'required|integer|exists:governorates,id',
        ];
    }
}
