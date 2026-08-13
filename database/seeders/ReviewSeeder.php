<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Review;
use Illuminate\Database\Seeder;

/**
 * PLACEHOLDER TESTIMONIALS. These are written copy, not real client reviews.
 * They exist so the homepage has something to render during the build. Clear
 * this table and let real reviews come through the app before go live.
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('slug', 'harare-avenues')->firstOrFail();

        $reviews = [
            ['author' => 'Sample review 1', 'rating' => 5, 'comment' => 'Placeholder copy. Replace with a real review once the app is collecting them.'],
            ['author' => 'Sample review 2', 'rating' => 5, 'comment' => 'Placeholder copy. Replace with a real review once the app is collecting them.'],
            ['author' => 'Sample review 3', 'rating' => 4, 'comment' => 'Placeholder copy. Replace with a real review once the app is collecting them.'],
        ];

        foreach ($reviews as $review) {
            Review::updateOrCreate(
                ['author_name' => $review['author']],
                [
                    'branch_id' => $branch->id,
                    'rating' => $review['rating'],
                    'comment' => $review['comment'],
                    'is_public' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
