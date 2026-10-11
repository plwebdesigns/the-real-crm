<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDocumentController extends Controller
{
    public function __invoke(Request $request, Lead $lead, LeadDocument $document): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->can('view', $lead), 404);

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        if ($document->isPdf()) {
            return $disk->response($document->path, $document->original_name, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return $disk->download($document->path, $document->original_name);
    }
}
