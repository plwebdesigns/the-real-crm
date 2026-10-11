<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadDocument>
 */
class LeadDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'lead-documents/'.fake()->uuid().'.pdf',
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
