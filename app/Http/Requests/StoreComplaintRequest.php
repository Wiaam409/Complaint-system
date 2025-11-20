<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{

    public function authorize()
    {
        return true;
    }


    public function rules()
    {
        return [
            'title'         => 'required|string|max:255',
            'description'   => 'required|string|min:5',
            'department_id' => 'required|integer|exists:departments,id',
            'location'      => 'required|string|max:255',
            'governorate_id' => 'required|integer|exists:governorates,id',
            'attachments'   => 'required|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ];
    }


}

