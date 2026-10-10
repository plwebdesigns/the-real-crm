<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleDocument>
 */
class SaleDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'sale-documents/'.fake()->uuid().'.pdf',
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
