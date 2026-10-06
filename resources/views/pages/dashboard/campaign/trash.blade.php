<?php

use Livewire\Component;

new class extends Component
{
    #[\Livewire\Attributes\Layout('layouts.dashboard')]
    public function mount()
    {
        abort_if(!auth()->user()->can('campaigns.view'), 403);
    }
};
?>

<div>
    {{-- Nothing worth having comes easy. - Theodore Roosevelt --}}
</div>