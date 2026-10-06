<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('posts.view'), 403);
    }
};
?>

<div>
    {{-- Well begun is half done. - Aristotle --}}
</div>