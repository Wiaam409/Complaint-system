<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminReportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'from'            => 'required|date',
            'to'              => 'required|date|after_or_equal:from',
            'governorate_id'  => 'required|exists:governorates,id',
            'department_id'   => 'required|exists:departments,id',
        ];
    }

    public function messages()
    {
        return [
            'from.required' => 'Start date is required',
            'to.required'   => 'End date is required',
            'to.after_or_equal' => 'End date must be same or after start date',
        ];
    }
}
