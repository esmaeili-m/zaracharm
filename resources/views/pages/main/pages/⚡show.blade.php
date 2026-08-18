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
                'rows.sections.section','rows.sections.media'
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
    <main class="space-y-12 lg:px-5">

        @foreach($page->rows as $row)

            <section class="{{ $row->classes() }} transition-colors duration-500">

                <div class="grid grid-cols-12 {{ $row->gapClass() }}">

                    @foreach($row->sections as $pageSection)

                        <div class="{{ $pageSection->classes() }} ">

                            @if($pageSection->section?->is_livewire)
                                <livewire:is
                                    :component="$pageSection->section->component"
                                    :data="$pageSection->data"
                                    :media="$pageSection->media"
                                />

                            @else

                                @include($pageSection->section->component, [
                                    'data' => $pageSection->data,
                                ])

                            @endif

                        </div>

                    @endforeach

                </div>

            </section>

        @endforeach

    </main>
</div>
