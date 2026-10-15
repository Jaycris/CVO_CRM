<?php

namespace Tests\Unit;

use App\Models\Reward;
use App\Models\RewardMedia;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RewardMediaSlideshowTest extends TestCase
{
    private function reward(int $count): Reward
    {
        $reward = new Reward(['title' => 'Hotel Stay']);
        $reward->setRelation('media', new Collection(array_map(
            fn ($index) => new RewardMedia(['type' => 'image', 'path' => 'rewards/photo-' . $index . '.jpg']),
            range(1, $count)
        )));

        return $reward;
    }

    public function test_multiple_photos_render_in_saved_order_with_automatic_changes_and_no_controls(): void
    {
        $html = view('rewards.partials.media-slideshow', ['reward' => $this->reward(3)])->render();
        $this->assertLessThan(strpos($html, 'photo-2.jpg'), strpos($html, 'photo-1.jpg'));
        $this->assertLessThan(strpos($html, 'photo-3.jpg'), strpos($html, 'photo-2.jpg'));
        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringContainsString('4500', $html);
    }

    public function test_single_photo_has_no_slideshow_controls(): void
    {
        $html = view('rewards.partials.media-slideshow', ['reward' => $this->reward(1)])->render();
        $this->assertStringContainsString('photo-1.jpg', $html);
        $this->assertStringNotContainsString('aria-label="Next photo"', $html);
        $this->assertStringNotContainsString('Pause slideshow', $html);
    }
}
