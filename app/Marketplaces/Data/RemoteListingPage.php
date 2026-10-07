<?php

namespace App\Marketplaces\Data;

final class RemoteListingPage
{
    /** @param  RemoteListing[]  $items */
    public function __construct(
        public readonly array $items,
        public readonly bool $hasMore,
    ) {
    }
}
