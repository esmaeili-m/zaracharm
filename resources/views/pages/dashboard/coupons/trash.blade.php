<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('coupons.view'), 403);
    }
};
?>

<div>
    {{-- We must ship. - Taylor Otwell --}}
</div>