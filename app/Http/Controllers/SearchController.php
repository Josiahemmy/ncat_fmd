<?php

namespace App\Http\Controllers;

use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        return response()->json(
            $search->search((string) $request->query('q', ''), $request->user())
        );
    }
}
