<?php

use Livewire\Component;

new class extends Component
{
    public $page;
    public function mount()
    {
        $this->page=\App\Models\Page::where('slug','home')->with('sections')->first();
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
                $media->collection => asset('/media/' . $media->file_path)
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
                                               $media->collection => asset('/media/'.$media->file_path)
                                           ];
                                       })
                                       ->toArray(),

                           ]
                       )

        @endif

    @endforeach


</div>
