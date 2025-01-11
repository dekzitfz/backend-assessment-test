<?php

namespace App\Http\Requests;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Traits\{ApiResponseTrait};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class DebitCardTransactionShowIndexRequest extends FormRequest
{
    use ApiResponseTrait;
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $debitCard = DebitCard::find($this->input('debit_card_id'));

        return $debitCard && $this->user()->can('view', $debitCard);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'debit_card_id' => 'required|integer|exists:debit_cards,id',
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
