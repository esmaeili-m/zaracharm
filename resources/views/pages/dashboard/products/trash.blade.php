<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('products.view'), 403);
    }
};
?>

<div>
    {{-- Because you are alive, everything is possible. - Thich Nhat Hanh --}}
</div>