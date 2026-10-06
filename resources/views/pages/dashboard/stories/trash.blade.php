<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('stories.view'), 403);
    }
};
?>

<div>
    {{-- Breathing in, I calm body and mind. Breathing out, I smile. - Thich Nhat Hanh --}}
</div>