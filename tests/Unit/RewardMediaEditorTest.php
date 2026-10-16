<?php

namespace Tests\Unit;

use App\Models\Reward;
use App\Models\RewardMedia;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RewardMediaEditorTest extends TestCase
{
    public function test_editor_has_compact_tiles_drag_handles_and_no_arrow_buttons(): void
    {
        $reward = new Reward(['title' => 'Hotel Stay']);
        $reward->setRelation('media', new Collection([
            (new RewardMedia)->forceFill(['id' => 1, 'type' => 'image', 'path' => 'room.jpg', 'original_name' => 'Room']),
            (new RewardMedia)->forceFill(['id' => 2, 'type' => 'image', 'path' => 'hotel.jpg', 'original_name' => 'Hotel']),
        ]));
        $html = view('admin.rewards.partials.edit-media', compact('reward'))->render();

        $this->assertStringContainsString('Drag to reorder', $html);
        $this->assertStringContainsString('setPointerCapture', $html);
        $this->assertStringContainsString('touch-action: none', $html);
        $this->assertStringContainsString('keydown.left.prevent', $html);
        $this->assertStringContainsString('10rem', $html);
        $this->assertStringContainsString('height: 6rem', $html);
        $this->assertStringNotContainsString('title="Move earlier"', $html);
        $this->assertStringNotContainsString('title="Move later"', $html);
        $this->assertStringContainsString('name="media_order[]"', $html);
    }
}
