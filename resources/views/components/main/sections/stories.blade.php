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

        $query = Story::query()->with(['media', 'items.file'])
            ->where('status', true)
            ->orderByDesc('created_at');

        return match ($mode) {

            'latest' => $query
                ->limit($limit)
                ->get(),

            'random' => Story::query()->with(['media', 'items.file'])
                ->where('status', true)
                ->inRandomOrder()
                ->limit($limit)
                ->get(),

            'manual' => Story::query()->with(['media', 'items.file'])
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

            {{-- ?v= برای جلوگیری از کش شدن نسخه قدیمی اسکریپت در مرورگر --}}
            <script src="{{ asset('main/js/plugin/story-player/story-player.js') }}?v={{ @filemtime(public_path('main/js/plugin/story-player/story-player.js')) ?: '3' }}"></script>

            <script>
                const stories = @js(
        $this->stories->map(function ($story) {
            $items = $story->items
                ->map(fn ($item) => [
                    'type' => $item->type === 'video' ? 'video' : 'image',
                    'url' => $item->file_url,
                    'title' => $item->title,
                    'description' => $item->description,
                    'duration' => $item->duration ?: 7000,
                    'link' => $item->link,
                ])
                ->filter(fn ($item) => $item['url'])
                ->values();

            // استوری‌های قدیمی (تک‌فایلی، پیش از آیتم‌ها)
            if ($items->isEmpty() && ($legacy = $story->media->firstWhere('collection', 'url'))) {
                $items = collect([[
                    'type' => $story->type === 'video' ? 'video' : 'image',
                    'url' => asset('storage/' . $legacy->file_path),
                    'title' => null,
                    'description' => null,
                    'duration' => $story->duration ?: 7000,
                    'link' => $story->link,
                ]]);
            }

            $first = $items->first();

            return [
                'user' => $story->user,
                'avatar' => $story->avatar_url,
                'items' => $items,
                // سازگاری با نسخه قدیمی story-player.js (اگر پوشه public هاست هنوز بروز نشده باشد)
                'type' => $first['type'] ?? 'image',
                'url' => $first['url'] ?? null,
                'duration' => $first['duration'] ?? 7000,
                'link' => $first['link'] ?? null,
            ];

        })->filter(fn ($story) => $story['items']->isNotEmpty())->values()
    );

                new StoryPlayer('stories-container', stories);
            </script>

        @endpush

    @endif

</div>
