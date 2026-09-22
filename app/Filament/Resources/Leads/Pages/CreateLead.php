<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Models\User;
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
            'location_id' => $related->location_id,
            'type' => $related->relatedType(),
            'agents' => $related->agents()->allRelatedIds()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user instanceof User && ! $user->is_super_admin) {
            $data['location_id'] = $user->location_id;
        }

        return $data;
    }
}
