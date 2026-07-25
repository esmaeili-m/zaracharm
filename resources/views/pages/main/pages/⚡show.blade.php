<?php

use Livewire\Component;

new class extends Component
{
    public $page,$seo;

    public function mount(?string $slug = null)
    {
        $slug ??= 'home';


        $this->page = \App\Models\Page::query()
            ->with([
                'sections.section',
                'sections.media'
            ])
            ->where('slug', $slug)
            ->where('status', true)
            ->firstOrFail();

        $this->seo = $this->page->getSeoPayload();
        $this->dispatch('seo:update', $this->seo);
    }
};
?>

<div>
    @foreach($page->sections as $pageSection)
        @if($pageSection->section?->livewire)
            <livewire:is
                :component="$pageSection->section->component"
                :data="$pageSection->data"
                :images="$pageSection->media
        ->mapWithKeys(function ($media) {
            return [
                $media->collection => asset('/storage/' . $media->file_path)
            ];
        })
        ->toArray()"
            />
        @else

            @include(
                           $pageSection->section->component,
                           [
                               'data' => $pageSection->data,
                              'images' => $pageSection->media
                                       ->mapWithKeys(function ($media) {
                                           return [
                                               $media->collection => asset('/storage/'.$media->file_path)
                                           ];
                                       })
                                       ->toArray(),

                           ]
                       )

        @endif

    @endforeach

</div>
