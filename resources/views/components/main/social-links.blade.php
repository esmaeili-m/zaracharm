<?php

use Livewire\Component;

new class extends Component
{
    public $socials;

    public function mount($socials)
    {
        $this->socials = $socials;
    }
    public function socialIcon($platform)
    {
        return match ($platform) {

            'instagram' => '<i class="fab fa-instagram"></i>',
            'telegram' => '<i class="fab fa-telegram"></i>',
            'linkedin' => '<i class="fab fa-linkedin"></i>',
            'youtube' => '<i class="fab fa-youtube"></i>',
            'website' => '<i class="fas fa-globe"></i>',

            // ایرانی‌ها (SVG)
            'eitaa' => view('components.main.socials.eitaa')->render(),
            'bale' => view('components.main.socials.bale')->render(),
            'rubika' => view('components.main.socials.rubika')->render(),
            'soroush' => view('components.main.socials.soroush')->render(),

            default => '<i class="fas fa-link"></i>',
        };
    }
};
?>

<div>
    <div class="course-details__Instructor-social">
        @foreach($socials as $social)
            <a href="{{ $social->url }}" target="_blank">
                {!! $this->socialIcon($social->platform) !!}
            </a>
        @endforeach

    </div>
</div>
