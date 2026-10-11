<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleDocumentController extends Controller
{
    public function __invoke(Request $request, Sale $sale, SaleDocument $document): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->can('view', $sale), 404);

        return $document->fileResponse();
    }
}
