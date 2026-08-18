<?php

use App\Models\Story;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component
{
    public $data;

    public function mount($data)
    {
        $this->data = $data;
    }

    public function getStoriesProperty()
    {
        $settings = $this->data['settings'] ?? [];

        $mode = $settings['mode'] ?? 'latest';
        $limit = (int) ($settings['limit'] ?? 8);

        $query = Story::query()->with('media')
            ->where('status', true)
            ->orderByDesc('created_at');

        return match ($mode) {

            'latest' => $query
                ->limit($limit)
                ->get(),

            'random' => Story::query()->with('media')
                ->where('status', true)
                ->inRandomOrder()
                ->limit($limit)
                ->get(),

            'manual' => Story::query()->with('media')
                ->where('status', true)
                ->whereIn(
                    'id',
                    $settings['story_ids'] ?? []
                )
                ->orderByDesc('created_at')
                ->get(),

            default => collect(),

        };

    }
};
?>

<div>

    @if($this->stories->isNotEmpty())
        <section class="pt-12">
            <h2 class="sr-only">استوری های فروشگاه</h2>

            <div class="container">

                <div
                    id="stories-container"
                    role="region"
                    aria-labelledby="stories-title"
                >
                    <h3 id="stories-title" class="sr-only">
                        استوری های فروشگاه
                    </h3>
                </div>

            </div>
        </section>

        @push('scripts')

            <script src="{{ asset('main/js/plugin/story-player/story-player.js') }}"></script>

            <script>
                const stories = @js(
        $this->stories->map(function ($story) {
            $avatar = $story->media
                ->firstWhere('collection', 'avatar');
            $url = $story->media
                ->firstWhere('collection', 'url');
            return [
                'type' => $story->type,
                'user' => $story->user,

                'avatar' => $avatar
                    ? asset('storage/' . $avatar->file_path)
                    : null,

                'url' => $url
                    ? asset('storage/' . $url->file_path)
                    : null,

                'duration' => $story->duration,
                'link' => $story->link,
            ];

        })->values()
    );

                new StoryPlayer('stories-container', stories);
            </script>

        @endpush

    @endif

</div>
