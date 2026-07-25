<?php

use Livewire\Component;

new class extends Component
{
    public $page;
    public function mount()
    {
        dd('a');
        $this->page=\App\Models\Page::where('slug','home')->first();
        dd($this->page);
    }
};
?>

<div>



</div>
