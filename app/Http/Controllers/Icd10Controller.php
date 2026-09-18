<?php

namespace App\Http\Controllers;

use App\Models\Icd10Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Icd10Controller extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();

        $codes = Icd10Code::query()
            ->when($term !== '', fn ($query) => $query->search($term))
            ->orderBy('code')
            ->limit(30)
            ->get(['code', 'name', 'chapter']);

        return response()->json($codes);
    }
}
