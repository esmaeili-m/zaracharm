<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('categories.view'), 403);
    }
};
?>

<div>
    {{-- Simplicity is the consequence of refined emotions. - Jean D'Alembert --}}
</div>