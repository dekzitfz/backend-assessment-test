<?php

namespace App\Http\Requests;

use App\Models\{DebitCard};
use App\Traits\{ApiResponseTrait};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class DebitCardCreateRequest extends FormRequest
{
    use ApiResponseTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', DebitCard::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $err = [
            'errors' => $validator->errors()
        ];

        $response = $this->validateFailed($err);

        throw new HttpResponseException(
            response()->json(
                $response,
                $response['status_code'],
        ));
    }
}
