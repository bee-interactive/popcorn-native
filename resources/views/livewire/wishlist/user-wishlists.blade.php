<div>
    @if(!empty($wishlists['data']))
        @foreach($wishlists['data'] as $wishlist)
            @if(is_array($wishlist) || is_object($wishlist))
                @php
                    // Handle both array and object formats, with safe defaults
                    $uuid = is_array($wishlist) 
                        ? ($wishlist['uuid'] ?? null) 
                        : (isset($wishlist->uuid) ? $wishlist->uuid : null);
                    $name = is_array($wishlist) 
                        ? ($wishlist['name'] ?? '') 
                        : (isset($wishlist->name) ? $wishlist->name : '');
                    $isFavorite = is_array($wishlist) 
                        ? (isset($wishlist['is_favorite']) && $wishlist['is_favorite']) 
                        : (isset($wishlist->is_favorite) && $wishlist->is_favorite);
                @endphp
                @if($uuid && $name)
                    <flux:navlist.item badge="{{ $isFavorite ? '★' : null }}" href="{{ route('wishlists.show', $uuid) }}">{{ $name }}</flux:navlist.item>
                @endif
            @endif
        @endforeach
    @endif
</div>
