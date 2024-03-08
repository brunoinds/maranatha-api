<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Invoice;

class ExpenseController extends Controller
{
        /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Expense::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request)
    {
        $expense = Expense::create($request->validated());
        return response()->json(['message' => 'Expense created', 'expense' => $expense->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        return response()->json($expense->toArray());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());
        $expense->save();
        return response()->json(['message' => 'Expense updated', 'expense' => $expense->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $count = Invoice::where('expense_code', $expense->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Expense has invoices, cannot be deleted'], 400);
        }


        $expense->delete();
        return response()->json(['message' => 'Expense deleted']);
    }
}