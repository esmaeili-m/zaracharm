<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('services.view'), 403);
    }
};
?>

<div>
    {{-- Be present above all else. - Naval Ravikant --}}
</div>