@if ($paginator->hasPages())
    <nav aria-label="ناوبری صفحه" class="pagination-style-1">
        <ul class="pagination mb-0">

            {{-- Previous --}}
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                <button
                    class="page-link"
                    wire:click="previousPage"
                    @if($paginator->onFirstPage()) disabled @endif>

                    <i class="ri-arrow-left-s-line align-middle"></i>

                </button>
            </li>

            {{-- Numbers --}}
            @foreach ($elements as $element)

                @if(is_string($element))

                    <li class="page-item disabled">
                    <span class="page-link">
                        <i class="bi bi-three-dots"></i>
                    </span>
                    </li>

                @endif

                @if(is_array($element))

                    @foreach($element as $page => $url)

                        <li class="page-item {{ $page == $paginator->currentPage() ? 'active' : '' }}">

                            <button
                                class="page-link"
                                wire:click="gotoPage({{ $page }})">

                                {{ $page }}

                            </button>

                        </li>

                    @endforeach

                @endif

            @endforeach

            {{-- Next --}}
            <li class="page-item {{ !$paginator->hasMorePages() ? 'disabled' : '' }}">
                <button
                    class="page-link"
                    wire:click="nextPage"
                    @if(!$paginator->hasMorePages()) disabled @endif>

                    <i class="ri-arrow-right-s-line align-middle"></i>

                </button>
            </li>

        </ul>
    </nav>
@endif
