<?php

namespace App\Http\Controllers;

use App\Http\Requests\DebitCardTransactionCreateRequest;
use App\Http\Requests\DebitCardTransactionDestroyRequest;
use App\Http\Requests\DebitCardTransactionShowIndexRequest;
use App\Http\Requests\DebitCardTransactionShowRequest;
use App\Http\Requests\DebitCardTransactionUpdateRequest;
use App\Http\Resources\DebitCardTransactionResource;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DebitCardTransactionController extends BaseController
{
    /**
     * Get debit card transactions list
     *
     * @param DebitCardTransactionShowIndexRequest $request
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
         
        $validator = Validator::make($request->all(), [
            'debit_card_id' => 'required'
        ]);

        if($validator->fails()){
            return response()->json($validator->messages(), 201);
        }
        $debitCard = DebitCard::find($request->input('debit_card_id'));
        
        $debitCardTransactions = $debitCard
            ->debitCardTransactions()
            ->get();
        return response()->json(DebitCardTransactionResource::collection($debitCardTransactions), HttpResponse::HTTP_OK);
    }

    /**
     * Create a new debit card transaction
     *
     * @param DebitCardTransactionCreateRequest $request
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'debit_card_id' => 'required',
            'amount' => 'required',
            'currency_code' => 'required',
        ]);

        if($validator->fails()){
            return response()->json($validator->messages(), 201);
        }

        $debitCard = DebitCard::find($request->input('debit_card_id'));
        $debitCardTransaction = $debitCard->debitCardTransactions()->create([
            'amount' => $request->input('amount'),
            'currency_code' => $request->input('currency_code'),
        ]);

        return response()->json(new DebitCardTransactionResource($debitCardTransaction), HttpResponse::HTTP_CREATED);
    }

    /**
     * Show a debit card transaction
     *
     * @param DebitCardTransactionShowRequest $request
     * @param DebitCardTransaction            $debitCardTransaction
     *
     * @return JsonResponse
     */
    public function show( $debitCardTransaction)
    {
       $debitCardTransaction =  DebitCardTransaction::find($debitCardTransaction);
        return response()->json(new DebitCardTransactionResource($debitCardTransaction), HttpResponse::HTTP_OK);
    }
}
