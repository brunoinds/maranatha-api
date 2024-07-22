<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Invoice;

class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::all();
    }

    public function store(StoreExpenseRequest $request)
    {
        $expense = Expense::create($request->validated());
        return response()->json(['message' => 'Expense created', 'expense' => $expense->toArray()]);
    }

    public function show(Expense $expense)
    {
        return response()->json($expense->toArray());
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());
        $expense->save();
        return response()->json(['message' => 'Expense updated', 'expense' => $expense->toArray()]);
    }

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
