@if ($paginator->hasPages())
    <div class="blog-list__pagination">
        <ul class="pg-pagination list-unstyled">

            {{-- Previous --}}
            <li class="prev">
                @if (!$paginator->onFirstPage())
                    <a wire:click="previousPage" type="button" aria-label="prev">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                @endif
            </li>

            {{-- Pages --}}
            @foreach ($elements as $element)
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="count {{ $page == $paginator->currentPage() ? 'active' : '' }}">
                            <a
                                type="button"
                                wire:click="gotoPage({{ $page }})"
                            >
                                {{ str_pad($page, 2, '0', STR_PAD_LEFT) }}
                            </a>
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            <li class="next">
                @if ($paginator->hasMorePages())
                    <a wire:click="nextPage" type="button" aria-label="next">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                @endif
            </li>

        </ul>
    </div>
@endif
