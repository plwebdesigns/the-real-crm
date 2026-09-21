<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    #[Url]
    public int|string|null $related = null;

    protected function afterFill(): void
    {
        $related = Lead::query()->find($this->related);

        if (! $related instanceof Lead) {
            return;
        }

        $this->form->fill([
            'first_name' => $related->first_name,
            'last_name' => $related->last_name,
            'email' => $related->email,
            'phone' => $related->phone,
            'lead_source_id' => $related->lead_source_id,
            'type' => $related->relatedType(),
            'agents' => $related->agents()->allRelatedIds()->all(),
        ]);
    }
}
