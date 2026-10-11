<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDocumentController extends Controller
{
    public function __invoke(Request $request, Lead $lead, LeadDocument $document): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->can('view', $lead), 404);

        return $document->fileResponse();
    }
}
